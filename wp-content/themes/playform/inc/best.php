<?php
/**
 * Købsguides af typen "Vores bud på de 3 bedste X" (Pizzalicious: omskrevet fra det gamle pizzalicious.dk).
 *
 * - Ligger på samme URL som på det gamle site: /romaskine-med-vand/ (ikke /viden/…), så de beholder placeringer og links.
 * - Indholdet er almindelig tekst i indlægget. Kun de levende dele (pris, butik, lager, knap) kommer fra shortcodes,
 *   som læser produktvalgene i metafeltet _pf_picks.
 * - Top 3 bygger på rigtige anmeldelser, hvor de findes (links i 'reviews'); ellers står der tydeligt "vores vurdering".
 * - Ingen påstand om, at vi selv har testet noget.
 *
 * Shortcodes: [fitbase_metode] [fitbase_kort] [fitbase_valg nr="1"] [fitbase_tabel] [fitbase_flere kategori="…"]
 */
defined( 'ABSPATH' ) || exit;

/** Finder produktet bag et valg: EAN først (samme på tværs af importer), ellers URL-navnet. */
function playform_pick_product( array $pick ): ?WC_Product {
	static $cache = [];
	// 'ean' og evt. 'eans' (fx andre farver af samme model): den første, der er på lager, ellers den første, der findes.
	$eans = array_values( array_filter( array_map( 'strval', array_merge( [ $pick['ean'] ?? '' ], (array) ( $pick['eans'] ?? [] ) ) ) ) );
	$key  = implode( ',', $eans ) . '|' . ( $pick['slug'] ?? '' );
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}
	$id = 0;
	if ( $eans ) {
		$ids = get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 20, 'meta_query' => [ [ 'key' => '_pf_ean', 'value' => $eans, 'compare' => 'IN' ] ] ] ); // phpcs:ignore
		usort( $ids, static fn( $a, $b ) => array_search( (string) get_post_meta( $a, '_pf_ean', true ), $eans, true ) <=> array_search( (string) get_post_meta( $b, '_pf_ean', true ), $eans, true ) );
		foreach ( $ids as $cand ) {
			if ( get_post_meta( $cand, '_pf_in_stock', true ) ) {
				$id = (int) $cand;
				break;
			}
		}
		$id = $id ?: ( $ids ? (int) $ids[0] : 0 );
	}
	if ( ! $id && ! empty( $pick['slug'] ) ) {
		$p  = get_page_by_path( (string) $pick['slug'], OBJECT, 'product' );
		$id = $p ? (int) $p->ID : 0;
	}
	$cache[ $key ] = $id ? ( wc_get_product( $id ) ?: null ) : null;
	return $cache[ $key ];
}

function playform_picks( int $post_id = 0 ): array {
	$picks = get_post_meta( $post_id ?: get_the_ID(), '_pf_picks', true );
	return is_array( $picks ) ? $picks : [];
}

/** Korte fakta om produktet ud fra feed-data (intet gættes). */
function playform_pick_facts( int $id ): array {
	$a = (array) get_post_meta( $id, '_pf_attributes', true );
	$f = [];
	if ( ! empty( $a['max_weight'] ) ) {
		$f[] = 'Maks. ' . (int) $a['max_weight'] . ' kg';
	}
	if ( ! empty( $a['max_speed'] ) ) {
		$f[] = 'Op til ' . rtrim( rtrim( number_format( (float) $a['max_speed'], 1, ',', '' ), '0' ), ',' ) . ' km/t';
	}
	if ( ! empty( $a['foldable'] ) ) {
		$f[] = 'Foldbar';
	}
	if ( ! empty( $a['incline'] ) ) {
		$f[] = 'Med incline';
	}
	return $f;
}

/** Billigste butik med lager (det er den, knappen sender videre til). */
function playform_pick_offer( int $id ): ?array {
	if ( ! class_exists( '\Playform\Feeds\Frontend' ) ) {
		return null;
	}
	foreach ( \Playform\Feeds\Frontend::offers( $id ) as $o ) {
		if ( $o['in_stock'] ) {
			return $o;
		}
	}
	return null;
}

// Sådan har vi valgt – står øverst i alle anbefalinger.
add_shortcode(
	'fitbase_metode',
	static function (): string {
		$money = esc_url( home_url( '/saadan-tjener-vi-penge/' ) );
		// Teksten afhænger af, om guiden faktisk har anmeldelseslinks (alle, nogle eller ingen af valgene).
		$picks = (array) get_post_meta( get_the_ID(), '_pf_picks', true );
		$with  = count( array_filter( $picks, static fn( $p ) => ! empty( $p['reviews'] ) ) );
		if ( $picks && $with === count( $picks ) ) {
			$how = 'Vores valg bygger på uafhængige anmeldelser fra andre medier (linket ved hvert produkt) og på butikkernes oplysninger om specifikationer, pris og garanti.';
			$sel = 'anmeldelser og produktdata';
		} elseif ( $with > 0 ) {
			$how = 'Er et produkt anmeldt af et uafhængigt medie, linker vi til anmeldelsen ved produktet. De andre har vi selv vurderet ud fra butikkernes oplysninger om specifikationer, pris og garanti – det fremgår ved hvert produkt.';
			$sel = 'anmeldelser og produktdata';
		} else {
			$how = 'Valgene bygger på vores egen vurdering af specifikationer, materialer, pris og garanti, som butikkerne oplyser dem.';
			$sel = 'produktdata';
		}
		return '<aside class="method"><span class="method__kicker">Sådan har vi valgt</span><strong class="method__title">Vi har ikke selv testet modellerne</strong>'
			. '<p>' . $how . ' <a href="' . esc_url( playform_method_url() ) . '">Sådan vælger vi produkter</a>.</p>'
			. '<p>Priser og lager opdateres to gange om dagen. Vi tjener provision, når du køber gennem vores links, men det er ' . $sel . ', der afgør valgene – ikke provisionen. <a href="' . $money . '">Sådan tjener vi penge</a>.</p></aside>';
	}
);

// Kort fortalt: de 3 valg med rolle, pris og knap.
add_shortcode(
	'fitbase_kort',
	static function (): string {
		$out = '';
		foreach ( playform_picks() as $i => $pick ) {
			$p = playform_pick_product( $pick );
			if ( ! $p ) {
				continue;
			}
			$d    = playform_card_data( $p );
			$out .= '<a class="tldr__item" href="#valg-' . ( $i + 1 ) . '">'
				. '<span class="tldr__n">' . ( $i + 1 ) . '</span>'
				. ( $d['image'] ? '<img src="' . esc_url( $d['image'] ) . '" alt="" loading="lazy">' : '<span class="tldr__ph"></span>' )
				. '<span class="tldr__txt"><small>' . esc_html( $pick['role'] ?? '' ) . '</small><b>' . esc_html( $d['name'] ) . '</b></span>'
				. '<span class="tldr__price">' . ( $d['in_stock'] ? esc_html( playform_kr( $d['price'] ) ) : 'Ikke på lager' ) . '</span></a>';
		}
		$n = count( playform_picks() );
		$kw = (string) get_post_meta( get_the_ID(), '_pf_keyword', true );
		$t  = ( $n === 3 ? 'Top 3' : 'Vores valg' ) . ( $kw ? ' – bedste ' . $kw . ' i overblik' : ' i overblik' );
		return $out ? '<div class="tldr"><span class="tldr__title">' . esc_html( $t ) . '</span>' . $out . '</div>' : '';
	}
);

// Ét produktvalg: billede, navn, grundlag (anmeldelser eller vores vurdering), fakta, levende pris og knap.
add_shortcode(
	'fitbase_valg',
	static function ( $atts ): string {
		$n     = max( 1, (int) ( $atts['nr'] ?? 1 ) );
		$picks = playform_picks();
		$pick  = $picks[ $n - 1 ] ?? null;
		$p     = $pick ? playform_pick_product( $pick ) : null;
		// Udsolgt eller væk: vis et alternativ i samme kategori og prisklasse, indtil artiklen er opdateret.
		$alt_html = '';
		// Lagerstatus efter tilbuddene (ikke kun metafeltet): ingen butik på lager med en levende side = ikke på lager.
		$offer = $p ? playform_pick_offer( $p->get_id() ) : null;
		if ( $p && ! $offer && get_post_meta( $p->get_id(), '_pf_in_stock', true ) && class_exists( '\\Playform\\Feeds\\ProductSync' ) && ! get_transient( 'pf_heal_' . $p->get_id() ) ) {
			set_transient( 'pf_heal_' . $p->get_id(), 1, HOUR_IN_SECONDS );
			\Playform\Feeds\ProductSync::refresh( $p->get_id(), current_time( 'Y-m-d' ) ); // ret metafelterne med det samme
		}
		if ( ! $p || ! $offer ) {
			$used = array_filter( array_map( static fn( $pk ) => ( $x = playform_pick_product( $pk ) ) ? $x->get_id() : 0, $picks ) );
			$alts = function_exists( 'playform_pick_alternatives' ) ? playform_pick_alternatives( playform_pick_category( $p, get_the_ID() ), $p ? (float) $p->get_price() : (float) ( $pick['price'] ?? 0 ), $used, 1 ) : [];
			if ( $alts ) {
				$a        = playform_card_data( $alts[0] );
				$ao       = playform_pick_offer( $alts[0]->get_id() );
				$alt_html = '<div class="pick-alt"><span class="pick-alt__label">' . ( $p ? 'Ikke på lager i øjeblikket' : 'Føres ikke længere' ) . ' – et bud i samme prisleje:</span>'
					. '<a class="pick-alt__item" href="' . esc_url( $a['url'] ) . '">' . ( $a['image'] ? '<img src="' . esc_url( $a['image'] ) . '" alt="" loading="lazy">' : '' )
					. '<span><b>' . esc_html( $a['name'] ) . '</b><small>' . esc_html( playform_kr( $a['price'] ) . ' · Hos ' . $a['shop'] ) . '</small></span></a>'
					. ( $ao ? '<a class="pick-alt__cta" href="' . esc_url( playform_aff_url( $ao['url'], get_post_field( 'post_name', $alts[0]->get_id() ), get_post_field( 'post_name', get_the_ID() ) . '-alternativ' ) ) . '" rel="sponsored nofollow noopener" referrerpolicy="strict-origin-when-cross-origin" target="_blank">Se pris</a><span class="adnote pick-alt__ad"><b>Annonce</b>Provision, hvis du køber</span>' : '' ) . '</div>';
			}
		}
		if ( ! $p ) {
			return '<div class="pick pick--gone"><p>Butikkerne har ikke længere produktet.</p>' . $alt_html . '</div>';
		}
		$id    = $p->get_id();
		$d     = playform_card_data( $p );
		$d['in_stock'] = (bool) $offer;
		$facts = playform_pick_facts( $id );
		$rev   = (array) ( $pick['reviews'] ?? [] );
		ob_start();
		?>
		<div class="pick">
			<a class="pick__media" href="<?php echo esc_url( $d['url'] ); ?>">
				<?php if ( $d['image'] ) : ?><img src="<?php echo esc_url( $d['image'] ); ?>" alt="<?php echo esc_attr( $d['name'] ); ?>" loading="lazy"><?php endif; ?>
				<span class="pick__rank"><?php echo (int) $n; ?></span>
			</a>
			<div class="pick__body">
				<span class="pick__role"><?php echo esc_html( $pick['role'] ?? '' ); ?></span>
				<a class="pick__name" href="<?php echo esc_url( $d['url'] ); ?>"><?php echo esc_html( $d['name'] ); ?></a>
				<?php if ( $rev ) : ?>
					<span class="pick__basis pick__basis--rev"><?php echo playform_icon( 'check', 14, 1.8 ); // phpcs:ignore ?>Anmeldt af
						<?php
						echo implode( // phpcs:ignore
							', ',
							array_map( static fn( $r ) => '<a href="' . esc_url( $r[1] ) . '" target="_blank" rel="noopener">' . esc_html( $r[0] ) . '</a>', $rev )
						);
						?>
					</span>
				<?php else : ?>
					<span class="pick__basis">Ikke afprøvet af os – vurderet ud fra specifikationer, pris og garanti</span>
				<?php endif; ?>
				<?php if ( $facts ) : ?>
					<ul class="pick__facts"><?php foreach ( $facts as $f ) : ?><li><?php echo esc_html( $f ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<div class="pick__buy">
					<div class="pick__price">
						<?php if ( $d['in_stock'] ) : ?>
							<b<?php echo $d['was'] > $d['price'] ? ' class="is-sale"' : ''; ?>><?php echo esc_html( playform_kr( $d['price'] ) ); ?></b>
							<?php if ( $d['was'] > $d['price'] ) : ?><s title="<?php echo 'shop' === ( $d['was_src'] ?? '' ) ? 'Butikkens førpris' : 'Laveste pris de 30 dage før tilbuddet'; ?>"><?php echo esc_html( playform_kr( $d['was'] ) ); ?></s><?php endif; ?>
							<small><?php echo esc_html( $d['sale'] ? 'Tilbud hos ' . $d['shop'] : ( $d['shops'] > 1 ? 'Billigst hos ' . $d['shop'] . ' · ' . $d['shops'] . ' butikker' : 'Hos ' . $d['shop'] ) ); ?></small>
						<?php else : ?>
							<b class="pick__out">Ikke på lager i øjeblikket</b>
						<?php endif; ?>
					</div>
					<?php if ( $offer ) : ?>
						<a class="btn-shop pick__cta" href="<?php echo esc_url( playform_aff_url( $offer['url'], get_post_field( 'post_name', $p->get_id() ), get_post_field( 'post_name', get_the_ID() ) ) ); ?>" rel="sponsored nofollow noopener" referrerpolicy="strict-origin-when-cross-origin" target="_blank">Se pris hos <?php echo esc_html( $offer['shop'] ); ?> <?php echo playform_icon( 'external', 16 ); // phpcs:ignore ?></a>
						<span class="adnote pick__adnote"><b>Annonce</b>Vi tjener provision, hvis du køber via knappen.</span>
					<?php else : ?>
						<a class="pick__more" href="<?php echo esc_url( $d['url'] ); ?>">Se produktet</a>
					<?php endif; ?>
				</div>
				<?php echo $alt_html; // phpcs:ignore -- bygget og escapet ovenfor ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
);

// Sammenligning af de 3 valg.
add_shortcode(
	'fitbase_tabel',
	static function (): string {
		$rows = '';
		foreach ( playform_picks() as $i => $pick ) {
			$p = playform_pick_product( $pick );
			if ( ! $p ) {
				continue;
			}
			$d     = playform_card_data( $p );
			$rows .= '<tr><td><a href="#valg-' . ( $i + 1 ) . '">' . esc_html( $d['name'] ) . '</a></td><td>' . esc_html( $pick['role'] ?? '' ) . '</td><td>'
				. ( $d['in_stock'] ? esc_html( playform_kr( $d['price'] ) ) : 'Ikke på lager' ) . '</td><td>' . esc_html( implode( ' · ', playform_pick_facts( $p->get_id() ) ) ?: '–' ) . '</td><td>'
				. ( empty( $pick['reviews'] ) ? 'Vores vurdering' : 'Anmeldt' ) . '</td></tr>';
		}
		return $rows ? '<table><thead><tr><th>Model</th><th>Bedst til</th><th>Aktuel pris</th><th>Fakta</th><th>Grundlag</th></tr></thead><tbody>' . $rows . '</tbody></table>' : '';
	}
);

// Flere populære modeller i kategorien (automatisk – ingen påstand om anbefaling).
add_shortcode(
	'fitbase_flere',
	static function ( $atts ): string {
		// Ejerens ønske 4. nov. 2026: vis butikkernes tilbud i kategorien først (højst 14 dage gamle, største rabat først),
		// og fyld op med populære modeller på lager, hvis der ikke er tilbud nok. Overskriften følger indholdet.
		$cat     = sanitize_title( (string) ( $atts['kategori'] ?? '' ) );
		$n       = max( 2, min( 8, (int) ( $atts['antal'] ?? 4 ) ) );
		$exclude = array_filter( array_map( static fn( $pk ) => ( $p = playform_pick_product( $pk ) ) ? $p->get_id() : 0, playform_picks() ) );
		$base    = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => $n,
			'post__not_in'   => $exclude, // phpcs:ignore
			'tax_query'      => $cat ? [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat ] ] : [], // phpcs:ignore
			'meta_query'     => [ // phpcs:ignore
				[ 'key' => '_pf_in_stock', 'value' => 1, 'type' => 'NUMERIC' ],
				[ 'key' => '_pf_image', 'value' => '', 'compare' => '!=' ],
			],
		];
		$q                 = $base;
		$q['meta_query'][] = [ 'key' => '_pf_shop_sale', 'compare' => 'EXISTS' ];
		$q['meta_key']     = '_pf_sale_percent'; // phpcs:ignore
		$q['orderby']      = 'meta_value_num';
		$q['order']        = 'DESC';
		$sale              = get_posts( $q );
		$ids               = $sale;
		if ( count( $ids ) < $n ) {
			$q                   = $base;
			$q['posts_per_page'] = $n - count( $ids );
			$q['post__not_in']   = array_merge( $exclude, $ids ); // phpcs:ignore
			$q['orderby']        = 'rand';
			$ckey                = 'pf_flere_' . md5( get_the_ID() . '|' . wp_json_encode( $q ) );
			$rand                = get_transient( $ckey );
			if ( ! is_array( $rand ) ) { // stabilt udvalg i 6 timer: sparer en RAND()-forespørgsel pr. visning
				$rand = get_posts( $q );
				set_transient( $ckey, $rand, 6 * HOUR_IN_SECONDS );
			}
			$ids = array_merge( $ids, $rand );
		}
		if ( ! $ids ) {
			return '';
		}
		$term  = $cat ? get_term_by( 'slug', $cat, 'product_cat' ) : null;
		$label = $term instanceof WP_Term ? mb_strtolower( playform_cat_label( $term ) ) : '';
		if ( count( $sale ) >= min( 4, $n ) ) {
			$title = $label ? 'Tilbud på ' . $label . ' lige nu' : 'Tilbud lige nu';
			$note  = 'Tilbud, butikkerne selv har sat (højst ' . ( class_exists( '\\Playform\\Feeds\\Sales' ) ? \Playform\Feeds\Sales::DAYS : 14 ) . ' dage gamle). De hører ikke til vores anbefaling – tjek altid den endelige pris hos butikken.';
		} elseif ( $sale ) {
			$title = 'Tilbud og populære modeller';
			$note  = 'Øverst står butikkernes egne tilbud, derefter et udvalg af modeller på lager, der skifter løbende – ikke en del af vores anbefaling.';
		} else {
			$title = 'Flere populære modeller';
			$note  = 'Modeller på lager hos vores butikker i et udvalg, der skifter – ikke en del af vores anbefaling.';
		}
		ob_start();
		echo '<h2>' . esc_html( $title ) . '</h2><p class="more-note">' . esc_html( $note ) . '</p><div class="grid grid--4 guide-products">';
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p ) {
				playform_product_card( $p );
			}
		}
		echo '</div>';
		if ( $term instanceof WP_Term ) {
			echo '<p><a class="btn-ghost" href="' . esc_url( $sale ? playform_tilbud_url( $term ) : get_term_link( $term ) ) . '">' . esc_html( $sale ? 'Se alle tilbud på ' . $label : 'Se alle ' . $label . ' (' . (int) $term->count . ')' ) . '</a></p>';
		}
		return (string) ob_get_clean();
	}
);

// Struktureret data: listen af valg (ItemList). Ingen Review-markup – vi har ikke testet produkterne.
add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( 'post' ) || ! function_exists( 'playform_json_ld' ) ) {
			return;
		}
		$items = [];
		foreach ( playform_picks( get_queried_object_id() ) as $i => $pick ) {
			$p = playform_pick_product( $pick );
			if ( $p ) {
				$items[] = [ '@type' => 'ListItem', 'position' => $i + 1, 'url' => get_permalink( $p->get_id() ), 'name' => $p->get_name() ];
			}
		}
		if ( $items ) {
			playform_json_ld( [ '@type' => 'ItemList', 'name' => get_the_title( get_queried_object_id() ), 'itemListElement' => $items ] );
		}
	}
);

/* -------------------------------------------------------------------------
 * Adresser
 * ---------------------------------------------------------------------- */

/**
 * Mappe for købsguides. Tom = guides ligger direkte under roden (/bedste-gasgrill/), som på det gamle
 * Kitzhen – så de beholder deres placeringer. Fitbase bruger 'koebsguide' (/koebsguide/romaskine-med-vand/).
 * Kategori-slugs må derfor aldrig starte med "bedste-" (se permalinks.php).
 */
const PLAYFORM_GUIDE_BASE = '';

/** Oversigten over købsguides (emnet "Købsguides"): /koebsguide/. */
const PLAYFORM_GUIDE_INDEX = 'koebsguide';

/** Købsguides fra content/best/. slug => post-ID. */
function playform_root_posts(): array {
	return (array) get_option( 'playform_root_posts', [] );
}

/** Adressen på en købsguide – eller oversigten, uden slug. */
function playform_guide_url( string $slug = '' ): string {
	if ( '' === $slug ) {
		return home_url( user_trailingslashit( PLAYFORM_GUIDE_INDEX ) );
	}
	return home_url( user_trailingslashit( ( '' !== PLAYFORM_GUIDE_BASE ? PLAYFORM_GUIDE_BASE . '/' : '' ) . $slug ) );
}

// Guides under en mappe (Fitbase): indlæggets adresse er /<mappe>/<slug>/ i stedet for den normale permalink.
add_filter(
	'post_link',
	static function ( string $link, WP_Post $post ): string {
		if ( '' === PLAYFORM_GUIDE_BASE ) {
			return $link;
		}
		$root = playform_root_posts();
		return isset( $root[ $post->post_name ] ) && (int) $root[ $post->post_name ] === (int) $post->ID ? playform_guide_url( $post->post_name ) : $link;
	},
	10,
	2
);

// Emnet Købsguides har sin oversigt på /koebsguide/ i stedet for /viden/emne/kobsguide/.
add_filter(
	'term_link',
	static function ( string $link, WP_Term $term, string $taxonomy ): string {
		return ( 'category' === $taxonomy && 'kobsguide' === $term->slug ) ? playform_guide_url() : $link;
	},
	10,
	3
);

add_filter(
	'rewrite_rules_array',
	static function ( array $rules ): array {
		$index = preg_quote( PLAYFORM_GUIDE_INDEX, '#' );
		$new   = [
			'^' . $index . '/?$'                => 'index.php?category_name=kobsguide',
			'^' . $index . '/page/([0-9]+)/?$' => 'index.php?category_name=kobsguide&paged=$matches[1]',
		];
		if ( '' !== PLAYFORM_GUIDE_BASE ) {
			$base = preg_quote( PLAYFORM_GUIDE_BASE, '#' );
			foreach ( playform_root_posts() as $slug => $id ) {
				$new[ '^' . $base . '/' . preg_quote( $slug, '#' ) . '/?$' ] = 'index.php?p=' . (int) $id;
			}
		}
		return $new + $rules;
	},
	20
);

/**
 * Gamle adresser fra det tidligere pizzalicious.dk, der ikke findes mere: sti => ny adresse (301) eller 410 (fjernet).
 * Guides fik "bedste-" foran og mistede "-test" (samme valg som på Kitzhen). Artiklerne beholder deres adresser
 * (de importeres 1:1, se playform_import_legacy_posts()), så de står ikke her.
 */
function playform_legacy_redirects(): array {
	return [
		// Købsguides (sitemap på det gamle site, 5. nov. 2026)
		'bedste-pizzaovn-test'   => '/bedste-pizzaovn/',
		'pizzaovn-til-koekkenet' => '/bedste-elektrisk-pizzaovn/',
		'bedste-pizzastaal-test' => '/bedste-pizzastaal/',
		'bedste-pizzaspade-test' => '/bedste-pizzaspade/',
		'pizzahjul'              => '/bedste-pizzaskaerer/',
		// Sider og kategorier fra det gamle site
		'om-os'       => '/viden/forfatter/frederik-baastrup/',
		'inspiration' => '/viden/',
		'guides'      => '/viden/',
		'artikler'    => '/viden/',
		'koebsguides' => '/koebsguide/',
		'udstyr'      => '/tilbehoer/',
		'annonce'     => '/saadan-tjener-vi-penge/',
		'category/koebsguides' => '/koebsguide/',
		'category/inspiration' => '/viden/',
		'category/guides'      => '/viden/',
		'category/artikler'    => '/viden/',
		'category/pizzaovne'   => '/pizzaovne/',
		'category/udstyr'      => '/tilbehoer/',
		'category/annonce'     => '/viden/',
		// Gamle sitemaps fra Yoast i Search Console
		'sitemap_index.xml'    => '/wp-sitemap.xml',
		'post-sitemap.xml'     => '/wp-sitemap.xml',
		'page-sitemap.xml'     => '/wp-sitemap.xml',
		'category-sitemap.xml' => '/wp-sitemap.xml',
	];
}

add_action(
	'template_redirect',
	static function () {
		$path = trim( rawurldecode( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ), '/' ); // phpcs:ignore
		$root = playform_root_posts();
		// Guides under en mappe (Fitbase): én adresse pr. købsguide.
		if ( is_singular( 'post' ) ) {
			$post = get_queried_object();
			if ( '' !== PLAYFORM_GUIDE_BASE && $post instanceof WP_Post && (int) ( $root[ $post->post_name ] ?? 0 ) === (int) $post->ID && PLAYFORM_GUIDE_BASE . '/' . $post->post_name !== $path ) {
				wp_safe_redirect( get_permalink( $post ), 301 );
				exit;
			}
			return;
		}
		// Den gamle emneside for købsguides → /koebsguide/
		if ( preg_match( '#^viden/emne/kobsguide(?:/page/(\d+))?$#', $path, $m ) ) {
			wp_safe_redirect( ! empty( $m[1] ) ? home_url( user_trailingslashit( PLAYFORM_GUIDE_INDEX . '/page/' . $m[1] ) ) : playform_guide_url(), 301 );
			exit;
		}
		if ( ! is_404() ) {
			return;
		}
		$path = preg_replace( '#/(feed|amp)$#', '', $path );
		if ( '' !== PLAYFORM_GUIDE_BASE && isset( $root[ $path ] ) ) {
			wp_safe_redirect( playform_guide_url( $path ), 301 );
			exit;
		}
		$map = playform_legacy_redirects();
		if ( ! isset( $map[ $path ] ) ) {
			return;
		}
		if ( 410 === $map[ $path ] ) {
			// Fjernet uden erstatning: 410 fortæller Google, at siden er væk med vilje (hurtigere ud af indekset end 404).
			status_header( 410 );
			nocache_headers();
			return; // WordPress viser 404-skabelonen med status 410
		}
		wp_safe_redirect( str_starts_with( (string) $map[ $path ], '/' ) ? home_url( $map[ $path ] ) : $map[ $path ], 301 );
		exit;
	},
	4
);

/* -------------------------------------------------------------------------
 * Indlæsning fra content/best/*.php
 * ---------------------------------------------------------------------- */

/**
 * Opretter eller opdaterer anbefalingerne. Et indlæg, redaktøren selv har rettet, røres ikke
 * (vi sammenligner med fingeraftrykket fra sidste indlæsning).
 */
function playform_seed_best(): void {
	$root = playform_root_posts();
	$cat  = get_term_by( 'slug', 'kobsguide', 'category' );
	if ( ! $cat ) {
		$res = wp_insert_term( 'Købsguides', 'category', [ 'slug' => 'kobsguide' ] );
		$cat = is_wp_error( $res ) ? null : get_term( (int) $res['term_id'], 'category' );
	}
	$author = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	foreach ( glob( get_template_directory() . '/content/best/*.php' ) ?: [] as $file ) {
		$a = require $file;
		if ( ! is_array( $a ) || empty( $a['slug'] ) ) {
			continue;
		}
		$html     = playform_best_html( $a );
		$existing = get_posts( [ 'name' => $a['slug'], 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ] );
		$data     = [
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_name'     => $a['slug'],
			'post_title'    => playform_guide_title( $a ),
			'post_excerpt'  => $a['excerpt'],
			'post_content'  => $html,
			'post_category' => $cat ? [ (int) $cat->term_id ] : [],
			'post_author'   => $author ? (int) $author[0] : 1,
		];
		if ( $existing ) {
			$post = $existing[0];
			if ( get_post_meta( $post->ID, '_pf_seed_hash', true ) !== md5( $post->post_content ) ) {
				$id = $post->ID; // redaktøren har rettet teksten – lad den være
			} else {
				$data['ID'] = $post->ID;
				$id         = wp_update_post( wp_slash( $data ) );
			}
		} else {
			$id = wp_insert_post( wp_slash( $data ) );
		}
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		$saved = get_post_field( 'post_content', $id );
		if ( ! $existing || (int) ( $data['ID'] ?? 0 ) === (int) $id ) {
			update_post_meta( $id, '_pf_seed_hash', md5( $saved ) );
		}
		update_post_meta( $id, '_pf_picks', $a['picks'] ?? [] );
		update_post_meta( $id, '_pf_keyword', playform_guide_keyword( $a ) );
		update_post_meta( $id, '_pf_more_cat', (string) ( $a['more_cat'] ?? '' ) );
		$root[ $a['slug'] ] = (int) $id;
	}
	update_option( 'playform_root_posts', $root, true );
}

/**
 * Søgeordet, guiden skal findes på ("bedste <søgeord>"): 'keyword' i artikelfilen, ellers titlen før kolon.
 * Folk søger typisk "bedste gasgrill" og "gasgrill test" (ejerens ønske 5. nov. 2026).
 */
function playform_guide_keyword( array $a ): string {
	if ( ! empty( $a['keyword'] ) ) {
		return (string) $a['keyword'];
	}
	$kw = trim( explode( ':', (string) ( $a['title'] ?? '' ) )[0] );
	$kw = preg_replace( '/^bedste\s+/iu', '', $kw );
	return mb_strtolower( mb_substr( $kw, 0, 1 ) ) . mb_substr( $kw, 1 );
}

/** Titlen begynder med "Bedste <søgeord>" – det, folk skriver i Google. */
function playform_guide_title( array $a ): string {
	$t = (string) ( $a['title'] ?? '' );
	if ( preg_match( '/^bedste\s/iu', $t ) || false === strpos( $t, ':' ) ) {
		return $t;
	}
	return 'Bedste ' . playform_guide_keyword( $a ) . substr( $t, strpos( $t, ':' ) );
}

/**
 * Nederst i guiden: folk søger "<søgeord> test", men vi har ikke testet selv. Det siger vi ærligt – og bruger ordene.
 */
function playform_guide_test_note( array $a ): string {
	$kw  = esc_html( playform_guide_keyword( $a ) );
	$rev = false;
	foreach ( (array) ( $a['picks'] ?? [] ) as $pk ) {
		$rev = $rev || ! empty( $pk['reviews'] );
	}
	// Overskriften uden ordet "test" (Google viser ofte overskrifter i søgeresultatet – jf. Forbrugerombudsmandens sag mod Obsidian Media).
	$h  = '<h2>Har vi selv prøvet modellerne?</h2>\n';
	$h .= '<p>Mange googler <b>' . $kw . ' test</b> eller "bedste ' . $kw . ' i test", inden de vælger. Det her er ikke en ' . $kw . ' test: Produkterne har vi ikke testet selv. '
		. 'Forarbejdet har vi til gengæld gjort for dig – vores bud på bedste ' . $kw . ' bygger på butikkernes produktdata, aktuelle priser og lagerstatus'
		. ( $rev ? ' samt uafhængige anmeldelser og tests fra andre medier af nøjagtig de samme modeller' : '' ) . ".</p>\n";
	$h .= '<p>' . ( $rev
		? 'Leder du efter en egentlig ' . $kw . ' test, finder du linkene til anmeldelserne ved de enkelte modeller længere oppe.'
		: 'Finder du en ' . $kw . ' test hos et uafhængigt medie, så sammenlign gerne med valgene her. Hvor der er anmeldelser af nøjagtig samme model, har vi linket til dem.' )
		. ' Priser og lager opdateres to gange om dagen, så prisen, du ser, er den aktuelle hos de butikker, vi samarbejder med.</p>' . "\n";
	return $h;
}

/** Bygger indlæggets HTML ud fra artikelfilen. */
function playform_best_html( array $a ): string {
	$h  = trim( $a['intro'] ?? '' ) . "\n";
	if ( ! empty( $a['picks'] ) ) {
		$h .= "[fitbase_metode]\n[fitbase_kort]\n";
		foreach ( $a['picks'] as $i => $pk ) {
			$n  = $i + 1;
			$h .= '<h2 id="valg-' . $n . '">' . esc_html( (string) ( $pk['role'] ?? '' ) ) . ': ' . esc_html( (string) ( $pk['name'] ?? '' ) ) . "</h2>\n";
			$h .= '[fitbase_valg nr="' . $n . '"]' . "\n";
			if ( ! empty( $pk['verdict'] ) ) {
				$h .= '<p class="pick-verdict"><b>Kort sagt</b>' . esc_html( $pk['verdict'] ) . "</p>\n";
			}
			$why = trim( $pk['why'] ?? '' );
			foreach ( (array) ( $pk['reviews'] ?? [] ) as $r ) { // første omtale af kilden i teksten bliver et link
				$pos = strpos( $why, $r[0] );
				if ( false !== $pos && false === strpos( $why, 'href="' . $r[1] ) ) {
					$why = substr_replace( $why, '<a href="' . esc_url( $r[1] ) . '" target="_blank" rel="noopener">' . esc_html( $r[0] ) . '</a>', $pos, strlen( $r[0] ) );
				}
			}
			$h .= $why . "\n";
			foreach ( (array) ( $pk['reviews'] ?? [] ) as $r ) { // tydeligt kort til den eksterne anmeldelse
				$host = preg_replace( '/^www\./', '', (string) wp_parse_url( $r[1], PHP_URL_HOST ) );
				$h   .= '<a class="review-card" href="' . esc_url( $r[1] ) . '" target="_blank" rel="noopener">'
					. '<span class="review-card__icon">' . playform_icon( 'check', 20, 2 ) . '</span>'
					. '<span class="review-card__body"><small>Uafhængig anmeldelse</small><b>Anmeldelse af ' . esc_html( $pk['name'] ) . ' hos ' . esc_html( $r[0] ) . '</b>'
					. '<span>Hele anmeldelsen kan du læse hos ' . esc_html( $host ) . '</span></span>'
					. '<span class="review-card__go">' . playform_icon( 'external', 18 ) . "</span></a>\n";
			}
			if ( ! empty( $pk['good'] ) || ! empty( $pk['less'] ) ) {
				$h .= '<div class="pick-fit"><div class="pick-fit__good"><b>Godt til</b><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $pk['good'] ?? [] ) ) . '</li></ul></div>'
					. '<div class="pick-fit__less"><b>Mindre godt til</b><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $pk['less'] ?? [] ) ) . "</li></ul></div></div>\n";
			}
		}
		if ( count( $a['picks'] ) > 1 ) {
			$h .= '<h2>Bedste ' . esc_html( playform_guide_keyword( $a ) ) . ': ' . ( count( $a['picks'] ) === 3 ? 'vores top 3' : 'vores valg' ) . ' side om side' . "</h2>\n[fitbase_tabel]\n";
		}
	}
	if ( ! empty( $a['more_cat'] ) ) {
		$h .= '[fitbase_flere kategori="' . esc_attr( $a['more_cat'] ) . '" antal="8"]' . "\n"; // overskriften kommer fra shortcoden (tilbud eller populære)
	}
	$h .= trim( $a['guide'] ?? '' ) . "\n";
	if ( ! empty( $a['faq'] ) ) {
		$h .= "<h2>Ofte stillede spørgsmål</h2>\n";
		foreach ( $a['faq'] as [ $q, $ans ] ) {
			$h .= '<h3>' . esc_html( $q ) . '</h3><p>' . esc_html( $ans ) . "</p>\n";
		}
	}
	if ( ! empty( $a['picks'] ) ) {
		$h .= playform_guide_test_note( $a );
	}
	return $h;
}

/**
 * Advarsel i WordPress-admin, når et produkt i en købsguide ikke findes i butikkerne eller ikke er på lager.
 * Indtil guiden er opdateret, viser [fitbase_valg] automatisk et alternativ i samme kategori og prisklasse.
 */
function playform_unavailable_picks(): array {
	$cached = get_transient( 'playform_unavailable_picks' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$out = [];
	foreach ( playform_root_posts() as $slug => $post_id ) {
		foreach ( playform_picks( (int) $post_id ) as $pick ) {
			$p = playform_pick_product( $pick );
			if ( ! $p || $p->get_status() !== 'publish' ) {
				$out[] = [ (int) $post_id, (string) ( $pick['name'] ?? '' ), 'findes ikke i butikkerne' ];
			} elseif ( ! get_post_meta( $p->get_id(), '_pf_in_stock', true ) ) {
				$out[] = [ (int) $post_id, (string) ( $pick['name'] ?? '' ), 'ikke på lager' ];
			}
		}
	}
	set_transient( 'playform_unavailable_picks', $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) || ! function_exists( 'playform_root_posts' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'edit-post' ], true ) ) {
			return;
		}
		$rows = playform_unavailable_picks();
		if ( ! $rows ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><b>Købsguides: produkter, der ikke kan købes lige nu</b> (guiden viser et alternativ, indtil den er opdateret):</p><ul style="list-style:disc;padding-left:20px">';
		foreach ( $rows as [ $id, $name, $why ] ) {
			printf( '<li><a href="%s">%s</a>: %s – %s</li>', esc_url( (string) get_edit_post_link( $id ) ), esc_html( get_the_title( $id ) ), esc_html( $name ), esc_html( $why ) );
		}
		echo '</ul></div>';
	}
);

/** Sidetitel i Google for købsguides: "Bedste <søgeord> 2026 → Vores top 4". */
add_filter(
	'document_title_parts',
	static function ( array $parts ): array {
		if ( ! is_singular( 'post' ) || ( function_exists( 'playform_has_seo_plugin' ) && playform_has_seo_plugin() ) ) {
			return $parts;
		}
		$id = get_queried_object_id();
		$kw = (string) get_post_meta( $id, '_pf_keyword', true );
		if ( '' === $kw ) {
			return $parts;
		}
		$n              = count( (array) get_post_meta( $id, '_pf_picks', true ) );
		$parts['title'] = 'Bedste ' . $kw . ' ' . wp_date( 'Y' ) . ( $n > 1 ? ' → Se vores top ' . $n : ' – købsguide' );
		// Domænet kommer kun med, når der er plads (ca. 65 tegn i alt) – ejerens ønske 6. nov. 2026.
		$site = ucfirst( (string) playform_site( 'domain' ) );
		if ( mb_strlen( $parts['title'] . ' – ' . $site ) > 65 ) {
			unset( $parts['site'] );
		} else {
			$parts['site'] = $site;
		}
		return $parts;
	},
	20
);
