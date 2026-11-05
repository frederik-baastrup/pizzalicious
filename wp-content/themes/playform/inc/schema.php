<?php
/**
 * Strukturerede data (JSON-LD) ud over produkt, FAQ, artikler og brødkrummer:
 * Organization + WebSite med søgefelt (forsiden) og CollectionPage med ItemList (kategorisider).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	static function () {
		if ( ! function_exists( 'playform_json_ld' ) ) {
			return;
		}
		if ( is_front_page() ) {
			$home = home_url( '/' );
			playform_json_ld(
				[
					'@graph' => [
						[
							'@type' => 'Organization',
							'@id'   => $home . '#organisation',
							'name'  => playform_site( 'name' ),
							'url'   => $home,
							'logo'  => [ '@type' => 'ImageObject', 'url' => get_template_directory_uri() . '/assets/img/favicon-192.png', 'width' => 192, 'height' => 192 ],
						],
						[
							'@type'           => 'WebSite',
							'@id'             => $home . '#website',
							'url'             => $home,
							'name'            => playform_site( 'name' ),
							'inLanguage'      => 'da-DK',
							'publisher'       => [ '@id' => $home . '#organisation' ],
							'potentialAction' => [
								'@type'       => 'SearchAction',
								'target'      => [ '@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}' ],
								'query-input' => 'required name=search_term_string',
							],
						],
					],
				]
			);
			return;
		}
		// Kategorisider (uden filtre): CollectionPage med listen af produkter på siden.
		if ( is_tax( 'product_cat' ) && ! playform_is_tilbud_page() && function_exists( 'playform_filter_groups' ) && ! array_intersect( array_keys( $_GET ), array_merge( array_keys( playform_filter_groups() ), [ 'sortering', 'q' ] ) ) ) { // phpcs:ignore
			global $wp_query;
			$term  = get_queried_object();
			$items = [];
			$pos   = 0;
			foreach ( (array) $wp_query->posts as $post ) {
				$items[] = [ '@type' => 'ListItem', 'position' => ++$pos, 'url' => get_permalink( $post ), 'name' => get_the_title( $post ) ];
			}
			$data = [
				'@type'      => 'CollectionPage',
				'name'       => $term instanceof WP_Term ? wp_specialchars_decode( playform_cat_label( $term ) ) : '',
				'url'        => $term instanceof WP_Term ? get_term_link( $term ) : '',
				'inLanguage' => 'da-DK',
				'isPartOf'   => [ '@type' => 'WebSite', 'name' => playform_site( 'name' ), 'url' => home_url( '/' ) ],
			];
			if ( $items ) {
				$data['mainEntity'] = [ '@type' => 'ItemList', 'numberOfItems' => count( $items ), 'itemListElement' => $items ];
			}
			playform_json_ld( $data );
		}
	},
	4
);
