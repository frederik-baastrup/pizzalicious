<?php
/**
 * Spar CPU (SiteGround-kvote ramt 18. nov. 2026): stop tunge robotter tidligt og hold søgemaskiner væk fra
 * filter-, sorterings- og søgeadresser, som giver uendelig mange varianter af de samme sider.
 */
defined( 'ABSPATH' ) || exit;

/** SEO-/AI-crawlere, der ikke giver besøgende, men koster CPU. Søgemaskiner (Google, Bing, DuckDuckGo) er ikke med. */
function playform_heavy_bot(): bool {
	$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	return '' !== $ua && (bool) preg_match( '~AhrefsBot|SemrushBot|MJ12bot|DotBot|PetalBot|Bytespider|GPTBot|ChatGPT-User|ClaudeBot|anthropic-ai|CCBot|Amazonbot|meta-externalagent|FacebookBot|DataForSeoBot|BLEXBot|SeznamBot|serpstatbot|Barkrowler|ImagesiftBot|YandexBot|Baiduspider|Sogou|AwarioBot|MauiBot|Timpibot|omgili|Diffbot|PerplexityBot|YouBot|SiteAuditBot|ZoominfoBot~i', $ua );
}

/** Robot på en adresse med parametre (filtre, sortering, søgning)? Alle sitets rigtige sider har rene adresser. */
function playform_bot_on_filter_url(): bool {
	if ( empty( $_GET ) ) {
		return false;
	}
	return (bool) preg_match( '~bot|crawl|spider|slurp~i', (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
}

if ( playform_heavy_bot() || playform_bot_on_filter_url() ) {
	status_header( 403 );
	// Aldrig cache: SiteGrounds cache skelner ikke mellem robotter og mennesker (en cachet 403 lukkede forsider ude, 20. nov. 2026).
	header( 'Cache-Control: no-store, no-cache, private, max-age=0' );
	header( 'X-Cache-Enabled: False' );
	header( 'X-Robots-Tag: noindex' );
	exit;
}

add_filter(
	'robots_txt',
	static function ( $out ) {
		$rules  = "Disallow: /*?\nCrawl-delay: 5\n";
		$out    = (string) $out;
		$out    = preg_replace( '~^(User-agent:\s*\*\s*)$~mi', "$1\n" . rtrim( $rules ), $out, 1, $n );
		if ( ! $n ) {
			$out .= "\nUser-agent: *\n" . $rules;
		}
		foreach ( [ 'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'PetalBot', 'Bytespider', 'GPTBot', 'ClaudeBot', 'CCBot', 'Amazonbot', 'meta-externalagent', 'DataForSeoBot', 'BLEXBot' ] as $b ) {
			$out .= "\nUser-agent: $b\nDisallow: /\n";
		}
		return $out;
	},
	98
);

// Spar CPU (21. nov. 2026): WooCommerce' baggrundsjob til Analytics, Marketing og forslag bruges ikke.
add_filter( 'woocommerce_admin_disabled', '__return_true' );
add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
add_filter( 'pre_option_woocommerce_analytics_enabled', static fn() => 'no' );
add_filter( 'pre_option_woocommerce_feature_marketplace_enabled', static fn() => 'no' );
add_filter( 'action_scheduler_retention_period', static fn() => DAY_IN_SECONDS );
