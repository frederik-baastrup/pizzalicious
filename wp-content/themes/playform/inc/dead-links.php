<?php
/**
 * Lukkede/afviste Partner-ads-programmer (Partner-ads → Linkfejl, 22. nov. 2026): links i vores EGNE artikler
 * peger i stedet på sitets søgning efter samme vare, så klikket ikke går tabt. Solgte artikler (_pf_sponsored)
 * røres aldrig. Nye lukninger: tilføj bannerid i listen.
 */
defined( 'ABSPATH' ) || exit;

function playform_dead_banners(): array {
	return [ 3256, 18987, 22409, 33585, 33612, 34494, 39630, 44882, 45423, 45583, 49371, 53458, 53461, 53878, 57067, 58660, 58661, 60673, 66239, 67224, 69477, 69986, 70499, 71365, 73018, 73206, 73716, 74418, 74707, 80590, 83383, 83986, 84226, 85166, 89036, 90063, 90173, 90289, 93582, 96410, 100168, 104004 ];
}

add_filter(
	'the_content',
	static function ( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, 'partner-ads.com' ) || get_post_meta( get_the_ID(), '_pf_sponsored', true ) ) {
			return $html;
		}
		$dead = array_flip( playform_dead_banners() );
		return (string) preg_replace_callback(
			'~<a\b([^>]*?)href=(["\'])(https?://(?:www\.)?partner-ads\.com/[^"\']+)\2([^>]*)>(.*?)</a>~is',
			static function ( $m ) use ( $dead ) {
				$url = html_entity_decode( $m[3] );
				parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
				if ( ! isset( $q['bannerid'] ) || ! isset( $dead[ (int) $q['bannerid'] ] ) ) {
					return $m[0];
				}
				$dest  = (string) ( $q['htmlurl'] ?? $q['desturl'] ?? '' );
				$path  = trim( (string) wp_parse_url( $dest, PHP_URL_PATH ), '/' );
				$last  = $path ? basename( $path ) : '';
				$words = preg_split( '~[-_+\s]+~', preg_replace( '~\.(html?|aspx|php)$|_\d.*$~i', '', rawurldecode( $last ) ) );
				$words = array_slice( array_filter( $words, static fn( $w ) => mb_strlen( $w ) > 1 && ! preg_match( '~^\d+$|^(products?|vare|shop|pi|p)$~i', $w ) ), 0, 4 );
				if ( ! $words ) {
					return $m[5]; // forside-link til et lukket program: behold kun teksten
				}
				return '<a href="' . esc_url( home_url( '/?s=' . rawurlencode( implode( ' ', $words ) ) . '&post_type=product' ) ) . '">' . $m[5] . '</a>';
			},
			$html
		);
	},
	20
);
