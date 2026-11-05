<?php
/**
 * Guides: produktkort i artikler, indholdsfortegnelse og læsetid.
 *
 * Shortcode: [playform_produkter kategori="foldbart-lobebaand" antal="4" sortering="pris-lav"]
 */

defined( 'ABSPATH' ) || exit;

add_shortcode(
	'playform_produkter',
	static function ( $atts ): string {
		$a = shortcode_atts( [ 'kategori' => '', 'antal' => 4, 'sortering' => '', 'soeg' => '' ], $atts );
		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 12, (int) $a['antal'] ) ),
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => '_pf_in_stock', 'value' => 1, 'type' => 'NUMERIC' ],
				[ 'key' => '_pf_image', 'value' => '', 'compare' => '!=' ],
			],
		];
		if ( $a['kategori'] ) {
			$args['tax_query'] = [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array_map( 'trim', explode( ',', $a['kategori'] ) ) ] ];
		}
		if ( $a['soeg'] ) {
			$args['s'] = $a['soeg'];
		}
		if ( $a['sortering'] === 'pris-lav' || $a['sortering'] === 'pris-hoj' ) {
			$args['meta_key'] = '_price';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = $a['sortering'] === 'pris-lav' ? 'ASC' : 'DESC';
		} else {
			$args['orderby'] = 'rand';
		}
		$ids = get_posts( $args );
		if ( ! $ids ) {
			return '';
		}
		ob_start();
		echo '<div class="grid grid--4 guide-products">';
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p ) {
				playform_product_card( $p );
			}
		}
		echo '</div>';
		if ( $a['kategori'] ) {
			$first = explode( ',', $a['kategori'] )[0];
			$term  = get_term_by( 'slug', trim( $first ), 'product_cat' );
			if ( $term ) {
				echo '<p class="guide-products__more"><a href="' . esc_url( get_term_link( $term ) ) . '">Se alle ' . esc_html( mb_strtolower( $term->name ) ) . ' →</a></p>';
			}
		}
		return (string) ob_get_clean();
	}
);

/** Giver mellemrubrikker et id, så indholdsfortegnelsen kan linke til dem. */
function playform_guide_prepare( string $html ): array {
	$toc  = [];
	$used = [];
	$html = preg_replace_callback(
		'~<h2([^>]*)>(.*?)</h2>~is',
		static function ( $m ) use ( &$toc, &$used ) {
			$text = wp_strip_all_tags( $m[2] );
			// Har overskriften allerede et id (fx #valg-1 i anbefalinger), bruges det.
			if ( preg_match( '~\sid="([^"]+)"~', $m[1], $own ) ) {
				$used[ $own[1] ] = true;
				$toc[]           = [ $own[1], $text ];
				return $m[0];
			}
			$id   = sanitize_title( $text );
			$n    = 2;
			$base = $id;
			while ( isset( $used[ $id ] ) ) {
				$id = $base . '-' . $n++;
			}
			$used[ $id ] = true;
			$toc[]       = [ $id, $text ];
			return '<h2 id="' . esc_attr( $id ) . '"' . $m[1] . '>' . $m[2] . '</h2>';
		},
		$html
	);
	return [ $html, $toc ];
}

function playform_read_minutes( string $html ): int {
	return max( 1, (int) round( str_word_count( wp_strip_all_tags( $html ) ) / 200 ) );
}
