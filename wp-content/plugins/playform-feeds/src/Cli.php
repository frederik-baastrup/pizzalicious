<?php
namespace Playform\Feeds;

/**
 * WP-CLI-kommandoer.
 *
 *   wp playform import [--shop=apuls,traeningspartner] [--file=apuls:/sti/til/feed.xml] [--limit=100]
 *   wp playform stats
 *   wp playform drops
 */
final class Cli {

	/**
	 * Importerer feeds.
	 *
	 * [--shop=<keys>]
	 * : Kommasepareret liste over shops. Standard: alle.
	 *
	 * [--file=<shop_path>]
	 * : Brug en lokal fil i stedet for at hente feedet. Kan gentages med komma.
	 *
	 * [--limit=<n>]
	 * : Læs højst n produkter pr. shop (til test).
	 */
	public function import( $args, $assoc ): void {
		$only  = isset( $assoc['shop'] ) ? array_map( 'trim', explode( ',', $assoc['shop'] ) ) : null;
		$files = [];
		if ( ! empty( $assoc['file'] ) ) {
			foreach ( explode( ',', $assoc['file'] ) as $pair ) {
				[ $k, $p ] = array_map( 'trim', explode( ':', $pair, 2 ) );
				$files[ $k ] = $p;
			}
		}
		$t0      = microtime( true );
		$summary = ( new Importer( static fn( $m ) => \WP_CLI::log( $m ) ) )->run( $only, $files, (int) ( $assoc['limit'] ?? 0 ) );
		$rows    = [];
		foreach ( $summary['shops'] as $key => $s ) {
			$rows[] = [ 'shop' => $s['name'], 'læst' => $s['read'], 'fravalgt' => $s['excluded'], 'nye' => $s['new'], 'ændret' => $s['updated'], 'uændret' => $s['unchanged'], 'væk' => $s['removed'], 'fejl' => $s['error'] ];
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array_keys( $rows[0] ?? [ 'shop' => '' ] ) );
		if ( $only === null && ! $files && empty( $assoc['limit'] ) ) {
			ImageCache::run( 1500 );
			$texts = Cron::after_import();
			if ( $texts ) {
				\WP_CLI::log( "$texts nye produkttekster skrevet." );
			}
		}
		\WP_CLI::success( sprintf( 'Færdig på %ds. %d produkter opdateret.', microtime( true ) - $t0, $summary['products_refreshed'] ) );
	}

	/** Viser antal produkter pr. kategori. */
	public function stats(): void {
		global $wpdb;
		$t    = Offers::table();
		$rows = $wpdb->get_results( "SELECT category, COUNT(DISTINCT product_id) AS produkter, SUM(active) AS aktive_tilbud, SUM(active AND in_stock) AS paa_lager FROM $t GROUP BY category ORDER BY produkter DESC", ARRAY_A );
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'category', 'produkter', 'aktive_tilbud', 'paa_lager' ] );
		$multi = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT product_id FROM $t WHERE active = 1 GROUP BY product_id HAVING COUNT(DISTINCT shop) > 1) x" );
		\WP_CLI::log( "Produkter solgt af flere butikker: $multi" );
	}

	/**
	 * Sorterer ukategoriserede produkter med AI.
	 *
	 * [--limit=<n>]
	 * : Højst så mange produkter. Standard 2000.
	 *
	 * @subcommand ai-classify
	 */
	public function ai_classify( $args, $assoc ): void {
		$s = AiClassifier::run( (int) ( $assoc['limit'] ?? 2000 ), static fn( $m ) => \WP_CLI::log( $m ) );
		\WP_CLI::success( sprintf( '%d tjekket: %d placeret, %d fravalgt, %d stadig usikre.', $s['checked'], $s['moved'], $s['excluded'], $s['kept'] ) );
	}

	/**
	 * Skriver AI-produkttekster (gemmes som udkast til godkendelse).
	 *
	 * [--limit=<n>]
	 * : Antal produkter. Standard 20.
	 *
	 * [--category=<slug>]
	 * : Kun denne kategori, fx lobebaand.
	 *
	 * [--id=<id>]
	 * : Kun dette produkt.
	 *
	 * @subcommand ai-texts
	 */
	public function ai_texts( $args, $assoc ): void {
		$ids = ! empty( $assoc['id'] ) ? [ (int) $assoc['id'] ] : AiWriter::queue( (int) ( $assoc['limit'] ?? 20 ), (string) ( $assoc['category'] ?? '' ) );
		$ok  = 0;
		foreach ( $ids as $id ) {
			try {
				AiWriter::generate( (int) $id );
				$ok++;
				\WP_CLI::log( '✓ ' . get_the_title( $id ) );
			} catch ( \Throwable $e ) {
				\WP_CLI::warning( get_the_title( $id ) . ': ' . $e->getMessage() );
			}
		}
		\WP_CLI::success( "$ok tekster venter nu på godkendelse under Produkter › Tekster til godkendelse." );
	}

	/**
	 * Henter produktbilleder til vores egen server.
	 *
	 * [--limit=<n>]
	 * : Højst så mange billeder. Standard 10000.
	 */
	public function images( $args, $assoc ): void {
		$s = ImageCache::run( (int) ( $assoc['limit'] ?? 10000 ), static fn( $m ) => \WP_CLI::log( $m ) );
		\WP_CLI::success( sprintf( '%d billeder hentet, %d fejlede.', $s['fetched'], $s['failed'] ) );
	}

	/** Kører sorteringsreglerne igen på alle tilbud (efter ændringer i Config). */
	public function reclassify(): void {
		global $wpdb;
		$t       = Offers::table();
		$rows    = $wpdb->get_results( "SELECT id, product_id, name, brand, shop_category, price, category FROM $t", ARRAY_A );
		$changed = [];
		$moves   = [];
		foreach ( $rows as $r ) {
			$offer = [ 'name' => $r['name'], 'brand' => $r['brand'], 'shop_category' => $r['shop_category'], 'price' => (float) $r['price'] ];
			$cat   = Classifier::classify( $offer );
			if ( $cat === $r['category'] ) {
				continue;
			}
			$moves[ $r['category'] . ' → ' . $cat ] = ( $moves[ $r['category'] . ' → ' . $cat ] ?? 0 ) + 1;
			if ( $cat === 'exclude' ) {
				$wpdb->update( $t, [ 'category' => 'exclude', 'active' => 0 ], [ 'id' => $r['id'] ] );
			} else {
				$wpdb->update( $t, [ 'category' => $cat ], [ 'id' => $r['id'] ] );
			}
			$changed[ (int) $r['product_id'] ] = true;
		}
		$today = current_time( 'Y-m-d' );
		foreach ( array_keys( $changed ) as $id ) {
			ProductSync::refresh( $id, $today );
			if ( ! Offers::for_product( $id ) ) {
				wp_update_post( [ 'ID' => $id, 'post_status' => 'draft' ] );
			}
		}
		arsort( $moves );
		foreach ( $moves as $k => $n ) {
			\WP_CLI::log( sprintf( '%5d  %s', $n, $k ) );
		}
		\WP_CLI::success( count( $changed ) . ' produkter flyttet.' );
	}

	/** Genberegner alle produkter ud fra deres tilbud (efter ændringer i koden). */
	public function refresh(): void {
		global $wpdb;
		$ids   = $wpdb->get_col( 'SELECT DISTINCT product_id FROM ' . Offers::table() . ' WHERE product_id > 0' );
		$today = current_time( 'Y-m-d' );
		$bar   = \WP_CLI\Utils\make_progress_bar( 'Opdaterer produkter', count( $ids ) );
		foreach ( $ids as $i => $id ) {
			ProductSync::refresh( (int) $id, $today );
			$bar->tick();
			if ( $i % 500 === 0 ) {
				wp_cache_flush();
			}
		}
		$bar->finish();
		\WP_CLI::success( count( $ids ) . ' produkter opdateret.' );
	}

	/** Genberegner butikstilbud (højst Sales::DAYS dage gamle). */
	public function sales(): void {
		$n = Sales::refresh_all( current_time( 'Y-m-d' ) );
		\WP_CLI::success( "$n produkter er på tilbud hos butikken." );
	}

	/** Genberegner rabatter ud fra prishistorikken. */
	public function drops(): void {
		$n = Importer::recalc_drops( current_time( 'Y-m-d' ) );
		\WP_CLI::success( "$n produkter har en målt rabat." );
	}
}
