<?php
/**
 * Filtre, sortering og paginering på kategorisider og søgning.
 *
 * Filtrene beregnes i PHP på kategoriens produkter (max et par tusinde), så vi kan vise
 * præcise tal ved hver mulighed. Hovedforespørgslen får derefter post__in med de matchende id'er.
 */

defined( 'ABSPATH' ) || exit;

const PLAYFORM_PER_PAGE = 60;

function playform_is_catalog( ?WP_Query $q = null ): bool {
	$q = $q ?: $GLOBALS['wp_query'];
	return $q->is_tax( 'product_cat' ) || ( $q->is_search() && in_array( $q->get( 'post_type' ), [ 'product', [ 'product' ] ], true ) ) || $q->is_post_type_archive( 'product' );
}

/** Filtergrupper. Hver mulighed har en test, der kører på et produkts metadata. */
function playform_filter_groups(): array {
	$range = static function ( string $key, ?float $min, ?float $max ) {
		return static function ( array $m ) use ( $key, $min, $max ) {
			$v = isset( $m[ $key ] ) ? (float) $m[ $key ] : null;
			return $v !== null && $v > 0 && ( $min === null || $v >= $min ) && ( $max === null || $v < $max );
		};
	};
	return [
		// Kun på sider på tværs af kategorier (fx /tilbud/): filter i stedet for en landingsside pr. kategori.
		'kat'         => [ 'label' => 'Kategori', 'options' => [], 'dynamic' => 'category', 'no_cat' => true, 'collapse' => 8 ],
		'pris'        => [
			'label'   => 'Pris',
			// Intervallerne kommer fra Config::price_bands() (samme som i mega-menuen).
			'options' => array_map(
				static fn( array $b ) => [ $b[0], $range( '_price', (float) $b[1], $b[2] >= PHP_INT_MAX ? null : (float) $b[2] ) ],
				playform_price_bands()
			),
		],
		// Kun på tilbuds- og kampagnesider. Ét valg ad gangen ("Mindst 20 %"), samme rabat som på produktkortet.
		'rabat'       => [
			'label'     => 'Rabat',
			'single'    => true,
			'only_sale' => true,
			'options'   => array_combine(
				[ '10', '20', '30', '40', '50' ],
				array_map( static fn( int $p ) => [ "Mindst $p %", static fn( array $m ) => (int) ( $m['_pct'] ?? 0 ) >= $p ], [ 10, 20, 30, 40, 50 ] )
			),
		],
		'maerke'      => [ 'label' => 'Mærke', 'options' => [], 'dynamic' => 'brand', 'collapse' => 8 ], // mulighederne bygges ud fra kategoriens produkter
		'egenskab'    => [
			'label'   => 'Egenskaber',
			'all'     => true, // alle valgte skal være opfyldt
			'options' => [
				'lager'   => [ 'På lager', static fn( $m ) => ! empty( $m['_pf_in_stock'] ) ],
				'tilbud'  => [ 'Tilbud', static fn( $m ) => ! empty( $m['_pf_shop_sale'] ) ],
			],
		],
		// Fitbase har her filtre for foldbar, incline, hastighed, brugervægt og vægt (Config::attributes()).
		// Kitzhen har ingen egenskabsfiltre endnu.
	];
}

/** Valgte filtre fra URL'en, fx ?pris=3000-6000,6000-10000&egenskab=foldbar */
function playform_active_filters(): array {
	$active = [];
	foreach ( array_keys( playform_filter_groups() ) as $g ) {
		if ( empty( $_GET[ $g ] ) ) {
			continue;
		}
		$raw  = is_array( $_GET[ $g ] ) ? $_GET[ $g ] : explode( ',', (string) wp_unslash( $_GET[ $g ] ) );
		$vals = array_values( array_filter( array_map( 'sanitize_text_field', $raw ) ) );
		if ( $vals ) {
			$active[ $g ] = $vals;
		}
	}
	if ( ! empty( $_GET['tilbud'] ) ) {
		$active['egenskab'][] = 'tilbud';
	}
	return $active;
}

function playform_current_category_slug(): string {
	$term = get_queried_object();
	return $term instanceof WP_Term && $term->taxonomy === 'product_cat' ? $term->slug : '';
}

/** Hovedkategorien for den aktuelle side (for en underkategori: forælderen). */
function playform_current_top_slug(): string {
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term || $term->taxonomy !== 'product_cat' ) {
		return '';
	}
	while ( $term->parent ) {
		$term = get_term( $term->parent, 'product_cat' );
	}
	return $term->slug;
}

/**
 * Som ovenfor, men stopper ved den nærmeste RIGTIGE kategori (springer de virtuelle
 * grupperings-kategorier som Kondiudstyr/Styrketræning over). Bruges til filterregler
 * ('only'), der er sat op pr. rigtig kategori og ikke kender til de virtuelle grupper.
 */
function playform_current_real_top_slug(): string {
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term || $term->taxonomy !== 'product_cat' ) {
		return '';
	}
	while ( $term->parent ) {
		$parent_term = get_term( $term->parent, 'product_cat' );
		if ( ! $parent_term instanceof WP_Term || playform_is_virtual_category( $parent_term->slug ) ) {
			break;
		}
		$term = $parent_term;
	}
	return $term->slug;
}

/**
 * Beregner matchende produkter og tal pr. filtermulighed. Gemmes til resten af requestet.
 */
function playform_catalog_state(): array {
	static $state = null;
	if ( $state !== null ) {
		return $state;
	}
	$cat    = playform_current_category_slug();
	$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$search = get_search_query();
	$args   = [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	];
	if ( $cat ) {
		$args['tax_query'] = [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat ] ];
	}
	if ( $q !== '' || $search !== '' ) {
		$args['s'] = trim( "$search $q" );
	}
	$tilbud = playform_is_tilbud_page();
	if ( $tilbud ) { // tilbudssider: kun produkter, butikken har på tilbud – i stedet for at læse alle produkter
		$args['meta_query'] = [ [ 'key' => '_pf_shop_sale', 'compare' => 'EXISTS' ] ];
	}
	// Data til filtrene ændrer sig kun ved en import, så de gemmes (undtagen ved søgning) indtil næste import.
	// Produkt-id'erne hentes altid (hurtigt); antal og højeste id indgår i nøglen, så cachen også fornyes, når
	// "Kør import nu" i admin tilføjer produkter (den opdaterer ikke playform_feeds_last_run). Før blev en kategori
	// cachet som tom i 12 timer, hvis den blev vist, før importen var færdig.
	$ids       = get_posts( $args );
	$cache_key = '';
	if ( empty( $args['s'] ) ) {
		$last      = (array) get_option( 'playform_feeds_last_run', [] );
		$cache_key = 'pf_catmeta3_' . md5( $cat . '|' . (int) $tilbud . '|' . ( $last['finished'] ?? '' ) . '|' . PLAYFORM_THEME_VERSION . '|' . count( $ids ) . '|' . ( $ids ? max( $ids ) : 0 ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$meta = $cached;
		}
	}
	if ( ! isset( $meta ) ) {
		$meta = playform_catalog_meta( $ids, $cat );
		if ( $cache_key ) {
			set_transient( $cache_key, $meta, 12 * HOUR_IN_SECONDS );
		}
	}

	$groups = playform_filter_groups();
	$active = playform_active_filters();

	// Mærker: ét valg pr. mærke (store/små bogstaver og mellemrum ensrettes), flest produkter først.
	if ( isset( $groups['maerke'] ) ) {
		$brands = [];
		foreach ( $meta as $m ) {
			$raw = trim( (string) ( $m['_pf_brand'] ?? '' ) );
			$key = $m['_bk'] ?? sanitize_title( $raw );
			if ( $key === '' ) {
				continue;
			}
			$brands[ $key ]['n']              = ( $brands[ $key ]['n'] ?? 0 ) + 1;
			$brands[ $key ]['names'][ $raw ]  = ( $brands[ $key ]['names'][ $raw ] ?? 0 ) + 1;
		}
		uasort( $brands, static fn( $a, $b ) => $b['n'] <=> $a['n'] );
		$opts = [];
		foreach ( $brands as $key => $info ) {
			arsort( $info['names'] );
			$label        = (string) array_key_first( $info['names'] ); // den mest brugte stavemåde
			if ( $label === mb_strtolower( $label ) ) {
				$label = mb_convert_case( $label, MB_CASE_TITLE ); // rent småt i feedet → stort begyndelsesbogstav
			}
			$opts[ $key ] = [ $label, static fn( array $m ) => ( $m['_bk'] ?? '' ) === $key ];
		}
		$groups['maerke']['options'] = $opts;
		$groups['maerke']['bucket']  = static fn( array $m ): array => [ $m['_bk'] ?? '' ];
	}

	// Kategori: de rigtige kategorier (ikke de virtuelle grupper), i menuens rækkefølge.
	if ( isset( $groups['kat'] ) ) {
		$opts = [];
		if ( ! $cat ) {
			foreach ( playform_tilbud_slugs() as $slug ) {
				$term = get_term_by( 'slug', $slug, 'product_cat' );
				if ( ! $term instanceof WP_Term || isset( $opts[ $slug ] ) || playform_is_virtual_category( $slug ) ) {
					continue;
				}
				$opts[ $slug ] = [ playform_cat_label( $term ), static fn( array $m ) => in_array( $slug, $m['_cats'] ?? [], true ) ];
			}
		}
		$groups['kat']['options'] = $opts;
		$groups['kat']['bucket']  = static fn( array $m ): array => $m['_cats'] ?? [];
	}

	$passes = static function ( array $m, ?string $skip ) use ( $groups, $active ): bool {
		foreach ( $active as $g => $vals ) {
			if ( $g === $skip || empty( $groups[ $g ] ) ) {
				continue;
			}
			$opts = $groups[ $g ]['options'];
			$hits = array_filter( $vals, static fn( $v ) => isset( $opts[ $v ] ) && $opts[ $v ][1]( $m ) );
			$known = array_filter( $vals, static fn( $v ) => isset( $opts[ $v ] ) );
			if ( ! $known ) {
				continue;
			}
			if ( ! empty( $groups[ $g ]['all'] ) ? count( $hits ) < count( $known ) : ! $hits ) {
				return false;
			}
		}
		return true;
	};

	$matching = [];
	foreach ( $meta as $id => $m ) {
		if ( $passes( $m, null ) ) {
			$matching[] = $id;
		}
	}

	$facets = [];
	foreach ( $groups as $g => $def ) {
		if ( ! empty( $def['only'] ) && ! in_array( playform_current_real_top_slug(), $def['only'], true ) ) {
			continue;
		}
		if ( ! empty( $def['only_sale'] ) && ! playform_is_tilbud_page() ) {
			continue;
		}
		if ( ! empty( $def['no_cat'] ) && $cat ) {
			continue;
		}
		$opts = [];
		// Produkter, der opfylder alle ANDRE filtre – beregnes én gang pr. gruppe, ikke pr. mulighed.
		$base = [];
		foreach ( $meta as $m ) {
			if ( $passes( $m, empty( $def['all'] ) ? $g : null ) ) {
				$base[] = $m;
			}
		}
		// Mærke og kategori: tæl i én gennemgang (hundredvis af valg × tusindvis af produkter var langsomt).
		$counts = null;
		if ( isset( $def['bucket'] ) ) {
			$counts = [];
			foreach ( $base as $m ) {
				foreach ( $def['bucket']( $m ) as $k ) {
					$counts[ $k ] = ( $counts[ $k ] ?? 0 ) + 1;
				}
			}
		}
		foreach ( $def['options'] as $key => [ $label, $test ] ) {
			if ( $key === 'tilbud' && playform_is_tilbud_page() ) {
				continue;
			}
			$n = 0;
			if ( null !== $counts ) {
				$n = $counts[ $key ] ?? 0;
			} else {
				foreach ( $base as $m ) {
					if ( $test( $m ) ) {
						$n++;
					}
				}
			}
			$checked = in_array( $key, $active[ $g ] ?? [], true );
			if ( $n > 0 || $checked ) {
				$opts[] = [ 'key' => $key, 'label' => $label, 'count' => $n, 'checked' => $checked ];
			}
		}
		if ( $opts ) {
			$facets[ $g ] = [ 'label' => $def['label'], 'options' => $opts, 'collapse' => (int) ( $def['collapse'] ?? 0 ) ];
		}
	}

	$tags = [];
	foreach ( $active as $g => $vals ) {
		foreach ( $vals as $v ) {
			if ( isset( $groups[ $g ]['options'][ $v ] ) ) {
				$rest = array_values( array_diff( $vals, [ $v ] ) );
				$tags[] = [ 'label' => $groups[ $g ]['options'][ $v ][0], 'url' => add_query_arg( $g, $rest ? implode( ',', $rest ) : false, remove_query_arg( [ 'paged', 'tilbud' ] ) ) ];
			}
		}
	}
	if ( $q !== '' ) {
		$tags[] = [ 'label' => '"' . $q . '"', 'url' => remove_query_arg( [ 'q', 'paged' ] ) ];
	}

	$pct = [];
	foreach ( $matching as $id ) {
		$pct[ $id ] = [ (int) ! empty( $meta[ $id ]['_pf_in_stock'] ), (int) ( $meta[ $id ]['_pct'] ?? 0 ) ];
	}
	// "Mest populære": vi har ingen salgstal, så vi vægter det, almindelige købere typisk vælger: på lager,
	// en pris tæt på det typiske i kategorien (ikke de dyreste specialmaskiner), flere butikker og tilbud.
	$prices = [];
	foreach ( $matching as $id ) {
		if ( ! empty( $meta[ $id ]['_pf_in_stock'] ) && (float) ( $meta[ $id ]['_price'] ?? 0 ) > 0 ) {
			$prices[] = (float) $meta[ $id ]['_price'];
		}
	}
	sort( $prices );
	$median = $prices ? $prices[ intdiv( count( $prices ), 2 ) ] : 0.0;
	$pop    = [];
	foreach ( $matching as $id ) {
		$m     = $meta[ $id ];
		$price = (float) ( $m['_price'] ?? 0 );
		$score = $price > 0 && $median > 0 ? -abs( log( $price / ( $median * 1.3 ) ) ) : -5.0; // lidt over medianen er typisk "den gode model"
		$score += 0.35 * max( 0, substr_count( (string) ( $m['_pf_shop_list'] ?? '' ), '|' ) - 2 ); // sælges af flere butikker
		$score += ! empty( $m['_pct'] ) ? 0.3 : 0.0; // på tilbud
		$pop[ $id ] = [ (int) ! empty( $m['_pf_in_stock'] ), $score ];
	}
	$state = [ 'ids' => $matching, 'facets' => $facets, 'tags' => $tags, 'total' => count( $matching ), 'q' => $q, 'pct' => $pct, 'pop' => $pop ];
	return $state;
}

add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! playform_is_catalog( $q ) ) {
			return;
		}
		$t0    = microtime( true );
		$state = playform_catalog_state_for_query( $q );
		$GLOBALS['playform_timing']['state'] = microtime( true ) - $t0;
		$q->set( 'post__in', $state['ids'] ?: [ 0 ] );
		$q->set( 'posts_per_page', PLAYFORM_PER_PAGE );
		$q->set( 'post_type', 'product' );
		$q->set( 'meta_query', [ 'stock' => [ 'key' => '_pf_in_stock', 'type' => 'NUMERIC' ] ] );

		switch ( playform_current_sort() ) {
			case 'pris-lav':
				$q->set( 'meta_query', [ 'stock' => [ 'key' => '_pf_in_stock', 'type' => 'NUMERIC' ], 'price' => [ 'key' => '_price', 'type' => 'DECIMAL(12,2)' ] ] );
				$q->set( 'orderby', [ 'stock' => 'DESC', 'price' => 'ASC' ] );
				break;
			case 'pris-hoj':
				$q->set( 'meta_query', [ 'stock' => [ 'key' => '_pf_in_stock', 'type' => 'NUMERIC' ], 'price' => [ 'key' => '_price', 'type' => 'DECIMAL(12,2)' ] ] );
				$q->set( 'orderby', [ 'stock' => 'DESC', 'price' => 'DESC' ] );
				break;
			case 'nyeste':
				$q->set( 'orderby', [ 'stock' => 'DESC', 'date' => 'DESC' ] );
				break;
			case 'rabat':
				// Største rabat først – samme rabat som på produktkortet. Sorteres her i PHP: en meta_query med
				// EXISTS/NOT EXISTS i OR joiner alle metafelter og sorterede efter tilfældige værdier.
				$ids = $state['ids'];
				$pct = $state['pct'] ?? [];
				usort( $ids, static fn( $a, $b ) => ( $pct[ $b ] ?? [ 0, 0 ] ) <=> ( $pct[ $a ] ?? [ 0, 0 ] ) ?: $a <=> $b );
				playform_catalog_page_slice( $q, $ids );
				break;
			default:
				// "Mest populære": se 'pop' i playform_catalog_state() – på lager, typisk pris, flere butikker, tilbud.
				$ids = $state['ids'];
				$pop = $state['pop'] ?? [];
				usort( $ids, static fn( $a, $b ) => ( $pop[ $b ] ?? [ 0, -9 ] ) <=> ( $pop[ $a ] ?? [ 0, -9 ] ) ?: $a <=> $b );
				playform_catalog_page_slice( $q, $ids );
		}
	},
	20
);

/** Valgt sortering. Tilbudssider sorterer som standard efter største rabat. */
function playform_current_sort(): string {
	$sort = isset( $_GET['sortering'] ) ? sanitize_key( $_GET['sortering'] ) : ''; // phpcs:ignore
	$ok   = [ 'populaer', 'pris-lav', 'pris-hoj', 'nyeste', 'rabat' ];
	return in_array( $sort, $ok, true ) ? $sort : ( playform_is_tilbud_page() ? 'rabat' : 'populaer' );
}

function playform_catalog_state_for_query( WP_Query $q ): array {
	return playform_catalog_state();
}

/** URL til en filtermulighed (slår den til/fra). */
function playform_filter_toggle_url( string $group, string $key ): string {
	$active = playform_active_filters()[ $group ] ?? [];
	$next   = in_array( $key, $active, true ) ? array_diff( $active, [ $key ] ) : array_merge( $active, [ $key ] );
	if ( ! empty( playform_filter_groups()[ $group ]['single'] ) ) { // ét valg ad gangen: et nyt valg erstatter det gamle
		$next = in_array( $key, $active, true ) ? [] : [ $key ];
	}
	return add_query_arg( $group, $next ? implode( ',', $next ) : false, remove_query_arg( [ 'paged', 'tilbud' ], get_pagenum_link( 1, false ) ) );
}

/** Er de aktuelle URL-parametre præcis $args (bortset fra side og sortering)? */
function playform_query_equals( array $args ): bool {
	$get = [];
	foreach ( $_GET as $k => $v ) { // phpcs:ignore
		if ( in_array( $k, [ 'paged', 'sortering' ], true ) || is_array( $v ) ) {
			continue;
		}
		$get[ $k ] = (string) wp_unslash( $v );
	}
	$want = array_map( 'strval', $args );
	ksort( $get );
	ksort( $want );
	return $want && $get === $want;
}

/** URL uden filtre (beholder kun søgeordet ved søgning). */
function playform_reset_url(): string {
	$term = get_queried_object();
	if ( $term instanceof WP_Term && $term->taxonomy === 'product_cat' ) {
		return playform_is_tilbud_page() ? playform_tilbud_url( $term ) : get_term_link( $term );
	}
	if ( function_exists( 'playform_current_campaign' ) && playform_current_campaign() ) {
		return playform_campaign_url( playform_current_campaign() );
	}
	if ( playform_is_tilbud_page() ) {
		return playform_tilbud_url();
	}
	$search = get_search_query();
	return $search !== '' ? add_query_arg( [ 's' => $search, 'post_type' => 'product' ], home_url( '/' ) ) : get_permalink( wc_get_page_id( 'shop' ) );
}

/**
 * Metadata til filtrene for en liste af produkter. Henter kun de felter, filtrene bruger, i få samlede
 * forespørgsler (get_post_meta pr. produkt indlæste ALLE felter inkl. lange beskrivelser – langsomt på
 * kategorier med tusindvis af produkter).
 */
function playform_catalog_meta( array $ids, string $cat ): array {
	global $wpdb;
	$keys = [ '_price', '_pf_in_stock', '_pf_drop_percent', '_pf_foldable', '_pf_incline', '_pf_max_speed', '_pf_max_weight', '_pf_weight_kg', '_pf_shop_list', '_pf_shop_sale', '_pf_brand', '_pf_sale_ref', '_pf_sale_list' ];
	$ids  = array_map( 'intval', $ids );
	$raw  = array_fill_keys( $ids, [] );
	$in_k = "'" . implode( "','", array_map( 'esc_sql', $keys ) ) . "'";
	foreach ( array_chunk( $ids, 2000 ) as $chunk ) {
		$rows = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($in_k) AND post_id IN (" . implode( ',', $chunk ) . ')', ARRAY_A ); // phpcs:ignore
		foreach ( $rows as $r ) {
			$raw[ (int) $r['post_id'] ][ $r['meta_key'] ] ??= $r['meta_value'];
		}
	}
	// Kategorier pr. produkt (inkl. overkategorier) til kategorifilteret – kun på sider på tværs af kategorier.
	$cats_of = [];
	if ( ! $cat && $ids ) {
		$slug_chain = [];
		$by_id      = [];
		foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $t ) {
			$by_id[ $t->term_id ] = $t;
		}
		foreach ( $by_id as $t ) {
			$chain = [];
			for ( $x = $t; $x; $x = $x->parent ? ( $by_id[ $x->parent ] ?? null ) : null ) {
				$chain[] = $x->slug;
			}
			$slug_chain[ $t->term_taxonomy_id ] = $chain;
		}
		foreach ( array_chunk( $ids, 2000 ) as $chunk ) {
			$rows = $wpdb->get_results( "SELECT object_id, term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id IN (" . implode( ',', $chunk ) . ')' ); // phpcs:ignore
			foreach ( $rows as $r ) {
				foreach ( $slug_chain[ $r->term_taxonomy_id ] ?? [] as $sl ) {
					$cats_of[ (int) $r->object_id ][ $sl ] = true;
				}
			}
		}
	}
	$tilbud = playform_is_tilbud_page();
	$meta   = [];
	foreach ( $raw as $id => $m ) {
		// Rabat som på produktkortet: kontrolleret førpris, ellers butikkens førpris (kun på lager).
		if ( ! empty( $m['_pf_shop_sale'] ) && ! empty( $m['_pf_in_stock'] ) ) {
			$was       = (float) ( $m['_pf_sale_ref'] ?? 0 ) ?: (float) ( $m['_pf_sale_list'] ?? 0 );
			$m['_pct'] = playform_save_pct( (float) ( $m['_price'] ?? 0 ), $was );
		}
		// Tilbudssider viser kun produkter, butikken har på tilbud.
		if ( $tilbud && empty( $m['_pf_shop_sale'] ) ) {
			continue;
		}
		if ( ! $cat ) {
			$m['_cats'] = array_keys( $cats_of[ $id ] ?? [] );
		}
		$m['_bk']    = sanitize_title( trim( (string) ( $m['_pf_brand'] ?? '' ) ) ); // mærkenøgle – beregnes én gang, ikke pr. filtervalg
		$meta[ $id ] = $m;
	}
	return $meta;
}

// Server-Timing (ses i browserens udviklerværktøjer): hvor lang tid filterdata og hovedforespørgslen tager.
add_action( 'wp', static function () {
	if ( isset( $GLOBALS['playform_timing'] ) ) {
		$GLOBALS['playform_timing']['wp'] = microtime( true ) - ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
	}
} );
add_action( 'template_redirect', static function () {
	if ( isset( $GLOBALS['playform_timing'] ) && ! headers_sent() ) {
		$t = $GLOBALS['playform_timing'];
		header( sprintf( 'Server-Timing: state;dur=%.0f, untilquery;dur=%.0f', ( $t['state'] ?? 0 ) * 1000, ( $t['wp'] ?? 0 ) * 1000 ) );
	}
}, 0 );

/**
 * Når rækkefølgen er regnet ud i PHP, får SQL kun den aktuelle sides id'er (ikke alle id'er i kategorien, som på
 * /produkter/ er over 20.000 i én ORDER BY FIELD). Det samlede antal sættes bagefter, så sideskift virker som før.
 */
function playform_catalog_page_slice( WP_Query $q, array $ids ): void {
	$per   = PLAYFORM_PER_PAGE;
	$paged = max( 1, (int) $q->get( 'paged' ) );
	$slice = array_slice( $ids, ( $paged - 1 ) * $per, $per );
	$q->set( 'post__in', $slice ?: [ 0 ] );
	$q->set( 'meta_query', [] );
	$q->set( 'orderby', 'post__in' );
	$q->set( 'offset', 0 ); // id'erne er allerede udsnittet – ingen SQL-offset oveni
	$q->set( 'pf_total', count( $ids ) );
}
add_filter(
	'found_posts',
	static function ( int $found, WP_Query $q ): int {
		$total = $q->get( 'pf_total' );
		return is_int( $total ) ? $total : $found;
	},
	10,
	2
);
