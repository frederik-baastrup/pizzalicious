<?php
/**
 * Grundlæggende SEO: titler, metabeskrivelser og noindex på filtrerede URL'er.
 * Bruger du senere Rank Math eller Yoast, slår temaet sine egne beskrivelser fra automatisk.
 */

defined( 'ABSPATH' ) || exit;

function playform_has_seo_plugin(): bool {
	return defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' );
}

/** Filtre, sortering og fritekst skaber uendeligt mange URL'er – de skal ikke i Google. */
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( ! playform_is_catalog() ) {
			return $robots;
		}
		$noise = array_intersect( array_keys( $_GET ), array_merge( array_keys( playform_filter_groups() ), [ 'sortering', 'q', 'tilbud' ] ) ); // phpcs:ignore
		// En tilbudsside uden tilbud lige nu bliver stående, men skal ikke i Google som tom side.
		$empty_offers = playform_is_tilbud_page() && ! playform_catalog_state()['total'];
		if ( $noise || is_search() || $empty_offers ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
);

add_filter(
	'document_title_parts',
	static function ( array $parts ): array {
		if ( playform_has_seo_plugin() ) {
			return $parts;
		}
		if ( is_front_page() ) {
			return [ 'title' => playform_site( 'name' ) . '.dk – Find det rigtige ' . playform_site( 'niche' ) ];
		}
		if ( function_exists( 'playform_current_campaign' ) && playform_current_campaign() ) {
			$c              = playform_current_campaign();
			$parts['title'] = playform_campaign_defs()[ $c ]['name'] . ' ' . wp_date( 'Y', playform_campaign_next_period( $c )[0]->getTimestamp() ) . ' – tilbud på ' . playform_site( 'niche' );
		} elseif ( playform_is_tilbud_page() ) {
			$t              = get_queried_object();
			$parts['title'] = $t instanceof WP_Term ? 'Tilbud på ' . mb_strtolower( playform_cat_label( $t ) ) . ' – aktuelle tilbud fra danske shops' : 'Tilbud på ' . playform_site( 'niche' ) . ' – aktuelle tilbud fra danske shops';
		} elseif ( is_tax( 'product_cat' ) ) {
			$parts['title'] = 'Køb ' . mb_strtolower( playform_cat_label( get_queried_object() ) ) . ' – stort udvalg fra danske shops';
		} elseif ( is_singular( 'product' ) ) {
			$parts['title'] = get_the_title() . ' – se pris';		} elseif ( is_search() ) {
			$parts['title'] = 'Søgeresultater for “' . get_search_query() . '”';
		} elseif ( is_404() ) {
			$parts['title'] = 'Siden findes ikke';
		}
		$parts['site'] = playform_site( 'name' );
		unset( $parts['tagline'] );
		return $parts;
	}
);
add_filter( 'document_title_separator', static fn() => '|' );

add_action(
	'wp_head',
	static function () {
		if ( playform_has_seo_plugin() ) {
			return;
		}
		$desc = '';
		if ( is_front_page() ) {
			$desc = 'Find pizzaovne, pizzasten, pizzaspader og alt til hjemmelavet pizza fra danske webshops ét sted. Sammenlign priser, se tilbud og læs vores købsguides.';
		} elseif ( function_exists( 'playform_current_campaign' ) && playform_current_campaign() ) {
			$desc = playform_campaign_page_text( playform_current_campaign() )['intro'];
		} elseif ( playform_is_tilbud_page() ) {
			$t    = get_queried_object();
			$desc = sprintf( 'Se %s, som danske webshops har sat på tilbud lige nu. Vi samler butikkernes egne tilbud ét sted, og du handler direkte hos butikken.', $t instanceof WP_Term ? mb_strtolower( playform_cat_label( $t ) ) : playform_site( 'niche' ) );
		} elseif ( is_tax( 'product_cat' ) ) {
			$desc = wp_strip_all_tags( term_description() );
		} elseif ( is_singular( 'product' ) ) {
			$id   = get_the_ID();
			$p    = wc_get_product( $id );
			$shop = playform_shop_name( (string) get_post_meta( $id, '_pf_best_shop', true ) );
			$desc = $p ? sprintf( '%s til %s hos %s. Se pris, lagerstatus og prishistorik på %s.', $p->get_name(), playform_kr( (float) $p->get_price() ), $shop, playform_site( 'name' ) ) : '';
		} elseif ( is_singular( 'post' ) ) {
			$desc = (string) get_the_excerpt();
		} elseif ( is_home() ) {
			$desc = 'Viden og guides om ' . playform_site( 'niche' ) . ': købsguides, råd og forklaringer, der gør det nemmere at vælge rigtigt.';
		} elseif ( is_category() ) {
			$desc = wp_strip_all_tags( term_description() ) ?: single_cat_title( '', false ) . ': guides og artikler om ' . playform_site( 'niche' ) . ' på ' . playform_site( 'name' ) . '.';
		} elseif ( is_page() ) {
			$desc = (string) get_the_excerpt();
		}
		$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $desc ) ) );
		$GLOBALS['playform_meta_desc'] = $desc;
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( mb_substr( $desc, 0, 160 ) ) . '">' . "\n";
		}
		if ( ! is_tax( 'product_cat' ) && ( is_home() || is_category() || ( function_exists( 'playform_current_campaign' ) && playform_current_campaign() ) || ( playform_is_tilbud_page() && ! get_queried_object() instanceof WP_Term ) ) && ! $_GET ) { // phpcs:ignore
			$paged = max( 1, (int) get_query_var( 'paged' ) );
			$path  = strtok( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), '?' ); // phpcs:ignore
			echo '<link rel="canonical" href="' . esc_url( $paged > 1 ? get_pagenum_link( $paged ) : home_url( $path ) ) . '">' . "\n";
		}
		if ( is_tax( 'product_cat' ) && ! array_intersect( array_keys( $_GET ), array_keys( playform_filter_groups() ) ) ) { // phpcs:ignore
			$paged = max( 1, (int) get_query_var( 'paged' ) );
			$base  = playform_is_tilbud_page() ? playform_tilbud_url( get_queried_object() ) : get_term_link( get_queried_object() );
			echo '<link rel="canonical" href="' . esc_url( $paged > 1 ? get_pagenum_link( $paged ) : $base ) . '">' . "\n";
		}
	},
	2
);

// Stort begyndelsesbogstav (også æ, ø, å) – titler må aldrig starte med lille.
function playform_ucfirst( string $s ): string {
	return mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 );
}

/**
 * Open Graph og Twitter-kort: titel, beskrivelse og billede, når et link deles (Facebook, Messenger, Slack, LinkedIn …).
 * Billedet er altid fra vores egen server.
 */
add_action(
	'wp_head',
	static function () {
		if ( playform_has_seo_plugin() || is_404() || is_search() ) {
			return;
		}
		$img = '';
		if ( is_singular( 'product' ) && class_exists( '\Playform\Feeds\Frontend' ) ) {
			$img = \Playform\Feeds\Frontend::image_url( get_the_ID() );
			$img = str_starts_with( $img, home_url() ) ? $img : ''; // kun lokalt gemte billeder
		} elseif ( is_singular() && has_post_thumbnail() ) {
			$img = (string) get_the_post_thumbnail_url( null, 'large' );
		} elseif ( is_tax( 'product_cat' ) && function_exists( 'playform_category_image' ) ) {
			$img = playform_category_image( get_queried_object()->slug );
			$img = str_ends_with( $img, '.svg' ) ? '' : $img;
		}
		if ( ! $img && function_exists( 'playform_front_og_image' ) ) {
			$img = playform_front_og_image();
		}
		$url  = is_singular() ? get_permalink() : home_url( strtok( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), '?' ) ); // phpcs:ignore
		$tags = [
			'og:locale'      => 'da_DK',
			'og:site_name'   => playform_site( 'name' ),
			'og:type'        => is_singular( 'post' ) ? 'article' : ( is_singular( 'product' ) ? 'product' : 'website' ),
			'og:title'       => wp_get_document_title(),
			'og:description' => (string) ( $GLOBALS['playform_meta_desc'] ?? '' ),
			'og:url'         => $url,
			'og:image'       => $img,
		];
		foreach ( $tags as $k => $v ) {
			if ( '' !== $v ) {
				echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n";
			}
		}
		echo '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	},
	3
);

/** Delingsbillede for sider uden eget billede: den første kategoris foto. */
function playform_front_og_image(): string {
	foreach ( glob( get_template_directory() . '/assets/img/{cat,hero}-*.jpg', GLOB_BRACE ) ?: [] as $f ) {
		if ( ! str_ends_with( $f, '-800.jpg' ) ) {
			return get_template_directory_uri() . '/assets/img/' . basename( $f );
		}
	}
	return '';
}
