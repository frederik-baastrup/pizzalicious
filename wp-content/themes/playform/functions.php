<?php
/**
 * Temaet (Fitbase og Kitzhen deler det; sitets navn kommer fra Config::site()).
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAYFORM_THEME_VERSION', '0.6.0' );

require_once __DIR__ . '/inc/site.php';
require_once __DIR__ . '/inc/navigation.php';
require_once __DIR__ . '/inc/permalinks.php';
require_once __DIR__ . '/inc/search.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/go.php';
require_once __DIR__ . '/inc/catalog.php';
require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/product-texts.php';
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/legal.php';
require_once __DIR__ . '/inc/how.php';
require_once __DIR__ . '/inc/method.php';
require_once __DIR__ . '/inc/guides.php';
require_once __DIR__ . '/inc/authors.php';
require_once __DIR__ . '/inc/best.php';
require_once __DIR__ . '/inc/best-check.php';
require_once __DIR__ . '/inc/links.php';
require_once __DIR__ . '/inc/schema.php';
require_once __DIR__ . '/inc/consent.php';
require_once __DIR__ . '/inc/campaigns.php';

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script' ] );
		add_theme_support( 'woocommerce' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$uri = get_template_directory_uri();
		wp_enqueue_style( 'playform', "$uri/assets/css/main.css", [], PLAYFORM_THEME_VERSION . '.' . filemtime( __DIR__ . '/assets/css/main.css' ) ); // ny version ved hver ændring, så mobil/cache ikke viser gammel CSS
		wp_enqueue_script( 'playform', "$uri/assets/js/main.js", [], PLAYFORM_THEME_VERSION . '.' . filemtime( __DIR__ . '/assets/js/main.js' ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
		// WooCommerce' egen CSS bruges ikke – temaet styler alt selv.
		wp_dequeue_style( 'woocommerce-general' );
		wp_dequeue_style( 'woocommerce-layout' );
		wp_dequeue_style( 'woocommerce-smallscreen' );
		wp_dequeue_style( 'wc-blocks-style' );
	},
	20
);

add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/**
 * Skriften ligger på vores egen server (ingen Google Fonts – det sender besøgendes IP til Google).
 * Kun de vægte, hvor filen findes, bliver indlæst.
 */
add_action(
	'wp_head',
	static function () {
		$dir = get_template_directory() . '/assets/fonts';
		$uri = get_template_directory_uri() . '/assets/fonts';
		$css = '';
		foreach ( [ 300, 400, 500, 600, 700, 800, 900 ] as $w ) {
			if ( is_file( "$dir/bdo-grotesk-$w.woff2" ) ) {
				$css .= "@font-face{font-family:'BDO Grotesk';src:url('$uri/bdo-grotesk-$w.woff2') format('woff2');font-weight:$w;font-style:normal;font-display:swap}";
				if ( $w === 400 || $w === 600 ) { // brødtekst og overskrifter
					echo '<link rel="preload" href="' . esc_url( "$uri/bdo-grotesk-$w.woff2" ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
				}
			}
		}
		if ( $css ) {
			echo '<style id="playform-fonts">' . $css . '</style>' . "\n"; // phpcs:ignore
		}
	},
	1
);

/**
 * Siden har ingen kurv, så WooCommerce' frontend-scripts fjernes.
 * Det gør siden hurtigere og undgår WooCommerce' sporings-cookies.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		foreach ( [ 'wc-add-to-cart', 'woocommerce', 'wc-cart-fragments', 'js-cookie', 'jquery-blockui', 'sourcebuster-js', 'wc-order-attribution', 'wc-add-to-cart-variation' ] as $h ) {
			wp_dequeue_script( $h );
			wp_deregister_script( $h );
		}
		if ( ! is_admin() && ! is_user_logged_in() ) {
			wp_deregister_script( 'jquery' );
		}
	},
	100
);

// Ingen overskrift på sideinddelingen ("Posts pagination" o.l.) – ejerens ønske 4. nov. 2026, gælder alle sider.
add_filter(
	'navigation_markup_template',
	static fn() => '<nav class="navigation %1$s" aria-label="%4$s"><div class="nav-links">%3$s</div></nav>'
);
