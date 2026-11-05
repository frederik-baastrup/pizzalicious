<?php
/**
 * Søgeforslag i headeren: kategorier og produkter, mens man skriver.
 * Kører på vores egen server (ingen tredjepart).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'playform/v1',
			'/search',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => [ 'q' => [ 'type' => 'string', 'required' => true ] ],
				'callback'            => 'playform_search_suggest',
			]
		);
	}
);

function playform_search_suggest( WP_REST_Request $request ): array {
	$q = trim( sanitize_text_field( (string) $request->get_param( 'q' ) ) );
	if ( mb_strlen( $q ) < 2 ) {
		return [ 'cats' => [], 'products' => [] ];
	}
	$q = mb_substr( $q, 0, 80 );

	$cats = [];
	foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'name__like' => $q, 'hide_empty' => true, 'number' => 4, 'orderby' => 'count', 'order' => 'DESC' ] ) as $t ) {
		if ( $t instanceof WP_Term && ! playform_is_virtual_category( $t->slug ) ) {
			$cats[] = [ 'name' => wp_specialchars_decode( $t->name ), 'url' => get_term_link( $t ), 'count' => (int) $t->count ];
		}
	}

	$ids = get_posts(
		[
			'post_type'      => 'product',
			'post_status'    => 'publish',
			's'              => $q,
			'fields'         => 'ids',
			'posts_per_page' => 24,
			'no_found_rows'  => true,
		]
	);
	// På lager og med billede først – ellers i søgningens rækkefølge.
	$rank = static fn( int $id ) => ( get_post_meta( $id, '_pf_in_stock', true ) ? 0 : 2 ) + ( get_post_meta( $id, '_pf_image', true ) ? 0 : 1 );
	$pos  = array_flip( $ids );
	usort( $ids, static fn( $a, $b ) => [ $rank( $a ), $pos[ $a ] ] <=> [ $rank( $b ), $pos[ $b ] ] );

	$products = [];
	foreach ( array_slice( $ids, 0, 6 ) as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$d          = playform_card_data( $p );
		$products[] = [
			'name'  => wp_specialchars_decode( $d['name'] ),
			'brand' => $d['brand'],
			'url'   => $d['url'],
			'image' => $d['image'],
			'price' => $d['price'] > 0 ? playform_kr( $d['price'] ) : '',
		];
	}

	return [
		'cats'     => $cats,
		'products' => $products,
		'all'      => add_query_arg( [ 's' => $q, 'post_type' => 'product' ], home_url( '/' ) ),
	];
}
