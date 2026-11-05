<?php
/**
 * Sitets profil: navn, domæne, e-mail, niche og firma. Kommer fra Config::site() i importpluginnet,
 * så Fitbase og Kitzhen kan dele samme tema. Brug playform_site( 'name' ) i stedet for at skrive navnet.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array|string Hele profilen, eller ét felt, når $key er angivet.
 */
function playform_site( string $key = '' ) {
	static $site = null;
	if ( $site === null ) {
		$defaults = [
			'name'    => get_bloginfo( 'name' ),
			'domain'  => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'email'   => '',
			'niche'   => '',
			'tagline' => get_bloginfo( 'description' ),
			'company' => '',
			'cvr'     => '',
			'address' => '',
			'owner'   => '',
			'accent'  => '#24FF00',
		];
		$site = class_exists( '\Playform\Feeds\Config' ) && method_exists( '\Playform\Feeds\Config', 'site' )
			? array_merge( $defaults, \Playform\Feeds\Config::site() )
			: $defaults;
	}
	return $key === '' ? $site : (string) ( $site[ $key ] ?? '' );
}

/** Prisintervaller (Config::price_bands()): nøgle => [ label, min, max ]. */
function playform_price_bands(): array {
	if ( class_exists( '\Playform\Feeds\Config' ) && method_exists( '\Playform\Feeds\Config', 'price_bands' ) ) {
		return \Playform\Feeds\Config::price_bands();
	}
	return [
		'0-1000'     => [ 'Under 1.000 kr', 0, 1000 ],
		'1000-3000'  => [ '1.000–3.000 kr', 1000, 3000 ],
		'3000-6000'  => [ '3.000–6.000 kr', 3000, 6000 ],
		'6000-10000' => [ '6.000–10.000 kr', 6000, 10000 ],
		'10000-'     => [ 'Over 10.000 kr', 10000, PHP_INT_MAX ],
	];
}
