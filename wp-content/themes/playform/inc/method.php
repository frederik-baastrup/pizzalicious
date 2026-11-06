<?php
/**
 * Siden "Sådan vælger vi produkter" (/saadan-vaelger-vi-produkter/): metoden bag købsguiderne.
 * Teksten ligger pr. site i content/method.php (forskellig formulering på hvert site – ingen duplicate content).
 * Der linkes til siden fra boksen "Sådan har vi valgt" i guiderne, sidefoden, forfattersiden og "Sådan fungerer …".
 */

defined( 'ABSPATH' ) || exit;

const PLAYFORM_METHOD_SLUG = 'saadan-vaelger-vi-produkter';

function playform_method_url(): string {
	return home_url( '/' . PLAYFORM_METHOD_SLUG . '/' );
}

function playform_method_content(): array {
	static $c = null;
	if ( null === $c ) {
		$file = get_template_directory() . '/content/method.php';
		$c    = is_readable( $file ) ? (array) require $file : [];
	}
	return $c;
}

/** Opretter siden ved udrulning. Teksten kommer fra filen, så siden selv har tomt indhold (som "Sådan fungerer …"). */
function playform_ensure_method_page(): void {
	$c = playform_method_content();
	if ( ! $c ) {
		return;
	}
	$page = get_page_by_path( PLAYFORM_METHOD_SLUG );
	if ( ! $page ) {
		wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => PLAYFORM_METHOD_SLUG,
				'post_title'   => (string) $c['title'],
				'post_excerpt' => (string) ( $c['excerpt'] ?? '' ),
				'post_content' => '',
			]
		);
	}
}

function playform_method_html(): string {
	return (string) ( playform_method_content()['html'] ?? '' );
}
