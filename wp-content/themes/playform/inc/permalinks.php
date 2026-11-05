<?php
/**
 * Flade kategori-URL'er: /traeningsmaskiner/lobebaand/gaabaand/ i stedet for /kategori/…
 *
 * WooCommerce kræver et præfiks (er feltet tomt, bruger den "vare-kategori"), så vi laver selv
 * links og rewrite-regler ud fra kategoritræet. Gamle adresser omdirigeres med 301.
 */

defined( 'ABSPATH' ) || exit;

/** Kategoriens sti fra roden, fx "traeningsmaskiner/lobebaand/gaabaand". */
function playform_cat_path( WP_Term $term ): string {
	$slugs = [ $term->slug ];
	foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $id ) {
		$a = get_term( $id, 'product_cat' );
		if ( $a instanceof WP_Term ) {
			array_unshift( $slugs, $a->slug );
		}
	}
	return implode( '/', $slugs );
}

add_filter(
	'term_link',
	static function ( string $url, $term, string $taxonomy ): string {
		return $taxonomy === 'product_cat' && $term instanceof WP_Term ? home_url( user_trailingslashit( playform_cat_path( $term ) ) ) : $url;
	},
	10,
	3
);

add_filter(
	'rewrite_rules_array',
	static function ( array $rules ): array {
		$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		if ( is_wp_error( $terms ) ) {
			return $rules;
		}
		$new     = [
			'^tilbud/page/?([0-9]{1,})/?$' => 'index.php?post_type=product&pf_tilbud=1&paged=$matches[1]',
			'^tilbud/?$'                   => 'index.php?post_type=product&pf_tilbud=1',
		];
		$tilbud  = playform_tilbud_slugs();
		foreach ( $terms as $t ) {
			if ( in_array( $t->slug, [ 'uncategorized', 'ukategoriseret' ], true ) ) {
				continue;
			}
			$p = preg_quote( playform_cat_path( $t ), '#' );
			if ( in_array( $t->slug, $tilbud, true ) ) {
				$new[ "^$p/tilbud/page/?([0-9]{1,})/?$" ] = 'index.php?product_cat=' . $t->slug . '&pf_tilbud=1&paged=$matches[1]';
				$new[ "^$p/tilbud/?$" ]                    = 'index.php?product_cat=' . $t->slug . '&pf_tilbud=1';
			}
			$new[ "^$p/page/?([0-9]{1,})/?$" ] = 'index.php?product_cat=' . $t->slug . '&paged=$matches[1]';
			$new[ "^$p/?$" ]                    = 'index.php?product_cat=' . $t->slug;
		}
		return $new + $rules;
	}
);

add_filter( 'query_vars', static fn( array $vars ): array => array_merge( $vars, [ 'pf_tilbud' ] ) );

// Reglerne bygges ud fra kategorierne, så de skal genopbygges, når en kategori oprettes, flyttes eller slettes.
foreach ( [ 'created_product_cat', 'edited_product_cat', 'delete_product_cat' ] as $hook ) {
	add_action( $hook, static fn() => update_option( 'playform_flush_rules', 1, true ) );
}
add_action(
	'wp_loaded',
	static function () {
		if ( get_option( 'playform_flush_rules' ) ) {
			delete_option( 'playform_flush_rules' );
			flush_rewrite_rules( false );
		}
	}
);

/** Omdiriger gamle og forkerte kategori-adresser til den rigtige. */
add_action(
	'template_redirect',
	static function () {
		$uri   = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore
		$path  = rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		$go    = static function ( string $url ) use ( $path, $query ) {
			if ( rtrim( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ), '/' ) === rtrim( $path, '/' ) ) {
				return; // allerede den rigtige adresse – undgå uendelig løkke
			}
			wp_safe_redirect( $query ? $url . '?' . $query : $url, 301 );
			exit;
		};
		$with_page = static function ( WP_Term $t, int $page ): string {
			$url = playform_is_tilbud_page() ? playform_tilbud_url( $t ) : get_term_link( $t );
			return $page > 1 ? trailingslashit( $url ) . user_trailingslashit( "page/$page", 'paged' ) : $url;
		};

		// Kategorisiden virker, men under en anden adresse (fx /kategori/… eller /vare-kategori/…).
		if ( is_tax( 'product_cat' ) ) {
			$t = get_queried_object();
			if ( $t instanceof WP_Term ) {
				$go( $with_page( $t, (int) get_query_var( 'paged' ) ) );
			}
			return;
		}

		// Ukendt adresse, der ligner en gammel kategori-adresse.
		if ( ! is_404() ) {
			return;
		}
		$segs = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
		$page = 0;
		$n    = count( $segs );
		if ( $n >= 2 && $segs[ $n - 2 ] === 'page' ) {
			$page = (int) array_pop( $segs );
			array_pop( $segs );
		}
		if ( ! $segs ) {
			return;
		}
		$aliases = []; // gamle kategorinavne => nye (Fitbase: kondiudstyr => traeningsmaskiner)
		$first   = $aliases[ $segs[0] ] ?? $segs[0];
		$is_cat  = in_array( $first, [ 'kategori', 'vare-kategori', 'product-category' ], true ) || get_term_by( 'slug', sanitize_title( $first ), 'product_cat' );
		if ( ! $is_cat ) {
			return;
		}
		$last = end( $segs );
		$last = $aliases[ $last ] ?? $last;
		$t    = get_term_by( 'slug', sanitize_title( $last ), 'product_cat' );
		if ( $t ) {
			$go( $with_page( $t, $page ) );
		}
	},
	5
);

/**
 * Kategorier ligger direkte under roden ligesom guiderne (/grill/ og /bedste-gasgrill/). Kategoriernes regler
 * vinder over indlæg med samme adresse, så en kategori må aldrig hedde "bedste-…" – den adresse er forbeholdt guides.
 */
function playform_reserved_slug( string $slug ): bool {
	return str_starts_with( $slug, 'bedste-' );
}

add_filter(
	'pre_insert_term',
	static function ( $term, string $taxonomy, array $args = [] ) {
		if ( 'product_cat' !== $taxonomy || is_wp_error( $term ) ) {
			return $term;
		}
		$slug = sanitize_title( $args['slug'] ?? '' ) ?: sanitize_title( (string) $term );
		return playform_reserved_slug( $slug )
			? new WP_Error( 'playform_reserved_slug', 'Kategorier må ikke starte med "bedste-". Den adresse er forbeholdt købsguides.' )
			: $term;
	},
	10,
	3
);

add_filter(
	'wp_update_term_data',
	static function ( array $data, int $term_id, string $taxonomy ): array {
		if ( 'product_cat' === $taxonomy && playform_reserved_slug( (string) ( $data['slug'] ?? '' ) ) ) {
			$data['slug'] = (string) get_term_field( 'slug', $term_id, 'product_cat' ); // behold den gamle adresse
		}
		return $data;
	},
	10,
	3
);
