<?php
/**
 * Grundlæggende sikring af WordPress. Supplerer SiteGrounds egen beskyttelse (firewall, Security Optimizer).
 */

defined( 'ABSPATH' ) || exit;

// Ingen redigering af tema- og plugin-filer fra administrationen (koden kommer fra GitHub).
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// Vis ikke WordPress-versionen.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// Brugernavne må ikke kunne fiskes ud via REST (/wp-json/wp/v2/users) af besøgende, der ikke er logget ind.
add_filter(
	'rest_endpoints',
	static function ( array $endpoints ): array {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}
);

// …eller via /?author=1 (forfattersiden har sin egen pæne adresse).
add_action(
	'template_redirect',
	static function () {
		if ( ! is_user_logged_in() && isset( $_GET['author'] ) && is_numeric( $_GET['author'] ) ) { // phpcs:ignore
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);

// XML-RPC bruges ikke – lukkes (en almindelig vej for login-angreb).
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter(
	'wp_headers',
	static function ( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

// Sikkerheds-headers på alle sider.
add_action(
	'send_headers',
	static function () {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
		header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
	}
);

// Samme fejlbesked ved login, uanset om brugernavnet findes (ellers kan man afprøve brugernavne).
add_filter( 'login_errors', static fn() => 'Brugernavn eller adgangskode er forkert.' );

// Adgangskoder til programmer (Application Passwords) bruges ikke – lukkes, medmindre der er behov.
add_filter( 'wp_is_application_passwords_available', '__return_false' );

/**
 * Hastighed: fjern WordPress' standardtilføjelser, siden ikke bruger (emoji, embeds, blok-CSS, RSD/WLW, shortlink).
 */
add_action(
	'init',
	static function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'wp_head', 'wp_resource_hints', 2 );
	}
);
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	},
	100
);
