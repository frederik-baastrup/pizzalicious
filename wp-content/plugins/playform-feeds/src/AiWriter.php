<?php
namespace Playform\Feeds;

/**
 * Skriver produkttekster med Claude.
 *
 * Ejeren godkender ikke hver tekst. I stedet faktatjekkes teksten automatisk (check()): alle tal skal
 * stå i butikkens data, og der må ikke være reklamesuperlativer. Består den, udgives den med det samme.
 * Ellers venter den under "Tekster til godkendelse" med en forklaring.
 */
final class AiWriter {

	public const META_DRAFT  = '_pf_ai_draft';
	public const META_STATUS = '_pf_ai_status';   // pending | approved | rejected
	public const META_DATE   = '_pf_ai_date';
	public const META_VERSION = '_pf_ai_version'; // 3 = kort, unik tekst (nov. 2026); 2 = lang tekst med faste afsnit
	public const VERSION      = 3;
	public const OPT_SENTENCES = 'playform_ai_sentences'; // sætnings-hash → produkt-id (unikhedskontrol)
	public const MAX_WORDS     = 125;

	private const SYSTEM = <<<TXT
Du skriver korte, unikke produkttekster på dansk til {SITE}, en side der samler {NICHE} fra flere danske webshops. Læseren vil hurtigt forstå, hvad produktet er, og hvad der kendetegner det.

Regler – de er vigtigere end alt andet:
1. Brug KUN fakta, der står i produktdataene. Opfind aldrig mål, vægt, effekt, kapacitet, materialer, garanti, programmer eller andre specifikationer. Tal skal stå i dataene.
2. Skriv kort. Er der få fakta, så er en kort tekst helt i orden (40 ord er fint). Fyld ALDRIG ud med generel købsrådgivning, forklaringer af begreber, gæt ud fra produktnavnet, "hvem passer det til"-afsnit eller andre fyldord.
3. Teksten må ikke ligne butikkens beskrivelse. Omformulér med egne ord. Højst 3 ord i træk må være identiske med beskrivelsen, og kopiér aldrig sætninger.
4. Hver tekst skal være unik for netop dette produkt. Ingen standardsætninger, der kunne stå på andre produkter ("er et godt valg til …", "perfekt til hjemmet", "med dette produkt får du"). Start ikke tekster med produktnavnet efterfulgt af "er" – start med en konkret egenskab eller anvendelse.
5. Ingen superlativer uden belæg ("markedets bedste", "perfekt"). Ingen priser, rabatter eller butiksnavne.
6. Naturligt, venligt dansk. Konkret og nyttigt.

Opbygning af html (70–100 ord i alt, aldrig over 110):
- Ét <p> med 2–3 sætninger: hvad produktet er, og det vigtigste, der adskiller det – ud fra dataene.
- Valgfrit: en <ul> med 3–5 korte <li> med konkrete fakta fra dataene (fx mål, belastning, materiale, medfølgende dele). Kun hvis dataene indeholder dem. Ingen overskrifter.

Svar KUN med JSON:
{"html": "<p>…</p><ul><li>…</li></ul>", "fordele": [], "ulemper": []}
- html: kun <p>, <ul>, <li>, <strong>.
- fordele og ulemper skal altid være tomme lister.
TXT;

	/** Systemprompten med sitets navn og niche (Config::site()). */
	private static function system(): string {
		$site = Config::site();
		return strtr( self::SYSTEM, [ '{SITE}' => $site['name'], '{NICHE}' => $site['niche'] ] );
	}

	/** Loft over antal tekster pr. døgn, så et fejlbehæftet job aldrig kan løbe løbsk. */
	public const DAILY_CAP_DEFAULT = 1000; // ejerens valg 5. nov. 2026 (før 200)

	public static function generate( int $product_id ): array {
		$cap   = (int) get_option( 'playform_ai_daily_cap', self::DAILY_CAP_DEFAULT );
		$today = current_time( 'Y-m-d' );
		$used  = (array) get_option( 'playform_ai_usage', [] );
		if ( ( $used[ $today ] ?? 0 ) >= $cap ) {
			throw new \RuntimeException( "Dagens loft på $cap tekster er nået." );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			throw new \RuntimeException( "Produkt $product_id findes ikke." );
		}
		$attrs = (array) get_post_meta( $product_id, ProductSync::META_ATTR, true );
		$cat   = (string) get_post_meta( $product_id, ProductSync::META_CATEGORY, true );
		$facts = [
			'navn'        => $product->get_name(),
			'mærke'       => (string) get_post_meta( $product_id, ProductSync::META_BRAND, true ),
			'kategori'    => Config::categories()[ $cat ]['name'] ?? $cat,
			'beskrivelse' => (string) get_post_meta( $product_id, ProductSync::META_FEED_DESC, true ),
			'egenskaber'  => $attrs,
			'kategorier'  => array_values( array_filter( (array) wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] ) ) ),
		];
		$prompt   = "Produktdata:\n" . wp_json_encode( $facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		$problems = [];
		$draft    = [];
		for ( $try = 0; $try < 3; $try++ ) { // op til to omskrivninger, hvis kontrollen finder noget (kopi, dublet, for lang, tal)
			$ask    = $problems ? $prompt . "\n\nDit forrige forsøg blev afvist:\n- " . implode( "\n- ", $problems ) . "\nSkriv teksten om, så problemerne er væk (kortere, med egne ord, ingen sætninger der kan stå på andre produkter)." : $prompt;
			$answer = Ai::json( Ai::ask( Ai::MODEL_WRITING, self::system(), $ask, 900 ) );
			if ( ! is_array( $answer ) || empty( $answer['html'] ) ) {
				throw new \RuntimeException( 'Uventet svar fra Claude.' );
			}
			$draft = [
				'html'    => wp_kses( $answer['html'], [ 'p' => [], 'ul' => [], 'li' => [], 'strong' => [] ] ),
				'pros'    => [],
				'cons'    => [],
				'sources' => $facts,
			];
			$problems = self::check( $draft, $facts, $product_id );
			if ( ! $problems ) {
				break;
			}
		}
		$draft['problems'] = $problems;
		update_post_meta( $product_id, self::META_DRAFT, $draft );
		update_post_meta( $product_id, self::META_STATUS, 'pending' );
		update_post_meta( $product_id, self::META_DATE, current_time( 'mysql' ) );
		update_post_meta( $product_id, self::META_VERSION, self::VERSION );
		$used = array_slice( [ $today => ( $used[ $today ] ?? 0 ) + 1 ] + $used, 0, 60, true );
		update_option( 'playform_ai_usage', $used, false );
		if ( ! $draft['problems'] && get_option( 'playform_ai_autopublish', '1' ) === '1' ) {
			self::approve( $product_id );
		}
		return $draft;
	}

	/**
	 * Automatisk faktatjek. Returnerer en liste med problemer (tom = teksten må udgives).
	 * - Hvert tal i teksten skal findes i produktdataene (ellers kan det være opfundet).
	 * - Ingen reklamesuperlativer.
	 */
	public static function check( array $draft, array $facts, int $product_id = 0 ): array {
		$text   = wp_strip_all_tags( ( $draft['html'] ?? '' ) . ' ' . implode( ' ', (array) ( $draft['pros'] ?? [] ) ) . ' ' . implode( ' ', (array) ( $draft['cons'] ?? [] ) ) );
		$source = wp_json_encode( $facts, JSON_UNESCAPED_UNICODE );
		$nums   = static function ( string $s ): array {
			preg_match_all( '/\d+(?:[.,]\d+)*/u', $s, $m );
			// "1.500" og "1500" er samme tal; "2,5" og "2.5" også.
			return array_unique( array_map( static fn( $n ) => preg_match( '/^\d{1,3}(\.\d{3})+$/', $n ) ? str_replace( '.', '', $n ) : str_replace( ',', '.', $n ), $m[0] ) );
		};
		$known    = array_flip( $nums( $source ) );
		$problems = [];
		foreach ( $nums( $text ) as $n ) {
			if ( ! isset( $known[ $n ] ) ) {
				$problems[] = "Tallet $n står ikke i butikkens data.";
			}
		}
		// Længde: korte tekster.
		$words = count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) );
		if ( $words > self::MAX_WORDS ) {
			$problems[] = "Teksten er for lang ($words ord, højst " . self::MAX_WORDS . ').';
		}
		// Kopi af butikkens beskrivelse (duplicate content): 6 ord i træk, der også står i beskrivelsen.
		$shingles = static function ( string $t, int $n ): array {
			$w = preg_split( '/\s+/u', mb_strtolower( preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', wp_strip_all_tags( $t ) ) ), -1, PREG_SPLIT_NO_EMPTY );
			$o = [];
			for ( $i = 0; $i + $n <= count( $w ); $i++ ) {
				$o[ implode( ' ', array_slice( $w, $i, $n ) ) ] = true;
			}
			return $o;
		};
		$desc = (string) ( $facts['beskrivelse'] ?? '' );
		if ( $desc !== '' && array_intersect_key( $shingles( $text, 6 ), $shingles( $desc, 6 ) ) ) {
			$problems[] = 'Teksten kopierer butikkens beskrivelse (6 ord i træk er identiske) – omformulér med egne ord.';
		}
		// Dublet: sætninger (7+ ord), der allerede står på et andet produkt.
		if ( $product_id ) {
			$index = (array) get_option( self::OPT_SENTENCES, [] );
			foreach ( self::sentences( $text ) as $h => $sentence ) {
				if ( isset( $index[ $h ] ) && (int) $index[ $h ] !== $product_id ) {
					$problems[] = 'Sætningen "' . mb_substr( $sentence, 0, 80 ) . '…" står allerede på et andet produkt.';
				}
			}
		}
		if ( preg_match( '/\b(markedets bedste|bedste på markedet|perfekt|perfekte|uovertruffen|verdens bedste|garanteret)\b/iu', $text, $m ) ) {
			$problems[] = 'Reklameord: "' . $m[1] . '".';
		}
		return $problems;
	}

	/** Sætninger på 7+ ord, normaliseret til sammenligning: [ hash => sætning ]. */
	public static function sentences( string $text ): array {
		$out = [];
		foreach ( preg_split( '/(?<=[.!?])\s+|\n+/u', wp_strip_all_tags( str_replace( [ '</li>', '</p>' ], [ ". ", ". " ], $text ) ), -1, PREG_SPLIT_NO_EMPTY ) as $sentence ) {
			$norm  = trim( preg_replace( '/\s+/u', ' ', mb_strtolower( preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $sentence ) ) ) );
			$count = count( explode( ' ', $norm ) );
			if ( $count >= 7 ) {
				$out[ substr( md5( $norm ), 0, 10 ) ] = trim( $sentence );
			}
		}
		return $out;
	}

	/** Gemmer produktets sætninger i indekset (og fjerner dets gamle), så kommende tekster kan tjekkes mod dem. */
	public static function index_text( int $product_id, string $html ): void {
		$index = array_filter( (array) get_option( self::OPT_SENTENCES, [] ), static fn( $pid ) => (int) $pid !== $product_id );
		foreach ( array_keys( self::sentences( $html ) ) as $h ) {
			$index[ $h ] = $product_id;
		}
		update_option( self::OPT_SENTENCES, $index, false );
	}

	/** Godkender udkastet (evt. rettet af redaktøren) og viser det på siden. */
	public static function approve( int $product_id, ?string $html = null, ?array $pros = null, ?array $cons = null ): void {
		$draft = (array) get_post_meta( $product_id, self::META_DRAFT, true );
		$html  = $html ?? ( $draft['html'] ?? '' );
		wp_update_post( [ 'ID' => $product_id, 'post_content' => wp_kses_post( $html ) ] );
		update_post_meta( $product_id, '_pf_pros', array_values( array_filter( $pros ?? ( $draft['pros'] ?? [] ) ) ) );
		update_post_meta( $product_id, '_pf_cons', array_values( array_filter( $cons ?? ( $draft['cons'] ?? [] ) ) ) );
		update_post_meta( $product_id, self::META_STATUS, 'approved' );
		self::index_text( $product_id, $html );
	}

	public static function reject( int $product_id ): void {
		update_post_meta( $product_id, self::META_STATUS, 'rejected' );
	}

	/**
	 * Produkter, der skal have tekst: først dem uden tekst, derefter automatiske tekster i et ældre format
	 * (uden META_VERSION eller med lavere version end VERSION). Vigtigste først (på lager, højeste pris).
	 */
	public static function queue( int $limit, string $category = '' ): array {
		$base = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'meta_key'       => '_price',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		];
		if ( $category ) {
			$base['tax_query'] = [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $category ] ];
		}
		$in_stock = [ 'key' => ProductSync::META_IN_STOCK, 'value' => 1, 'type' => 'NUMERIC' ];
		$ids      = get_posts( $base + [ 'posts_per_page' => $limit, 'meta_query' => [ [ 'key' => self::META_STATUS, 'compare' => 'NOT EXISTS' ], $in_stock ] ] );
		if ( count( $ids ) < $limit ) {
			$old = get_posts(
				$base + [
					'posts_per_page' => $limit - count( $ids ),
					'meta_query'     => [
						[ 'key' => self::META_STATUS, 'value' => 'approved' ],
						[ 'relation' => 'OR', [ 'key' => self::META_VERSION, 'compare' => 'NOT EXISTS' ], [ 'key' => self::META_VERSION, 'value' => self::VERSION, 'type' => 'NUMERIC', 'compare' => '<' ] ],
						$in_stock,
					],
				]
			);
			$ids = array_merge( $ids, $old );
		}
		return $ids;
	}
}
