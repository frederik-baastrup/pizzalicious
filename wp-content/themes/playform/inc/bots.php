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

/**
 * Adresser med parametre (?maerke=, ?pris=, ?side= …) uden menneske-cookie (pf_h, sat af main.js ved første klik,
 * tryk eller scroll) får en lille JavaScript-side, der sætter cookien og genindlæser. Rigtige besøgende mærker det
 * ikke; scrapere, der ikke kører JavaScript, koster næsten ingen CPU. Indført 24. nov. 2026 efter et
 * angreb på Myappeal: 70 kald i sekundet på /produkter?maerke=… fra tusindvis af IP-adresser.
 */
function playform_needs_challenge(): bool {
	if ( empty( $_SERVER['QUERY_STRING'] ) || ! empty( $_COOKIE['pf_h'] ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		return false;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	foreach ( array_keys( $_COOKIE ) as $c ) {
		if ( 0 === strpos( (string) $c, 'wordpress_logged_in_' ) ) {
			return false;
		}
	}
	$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	return ! preg_match( '~^/(wp-|go/|xmlrpc|feed|sitemap)|[?&](pf_list|preview|rest_route|wc-ajax|p|page_id)=~', $uri );
}

if ( playform_needs_challenge() ) {
	status_header( 200 );
	header( 'Cache-Control: no-store, no-cache, private, max-age=0' );
	header( 'X-Cache-Enabled: False' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>…</title>'
		. '<script>document.cookie="pf_h=1;path=/;max-age=31536000;SameSite=Lax";location.reload();</script>'
		. '<noscript><p style="font:16px system-ui,sans-serif;margin:40px">Slå JavaScript til for at bruge filtrene.</p></noscript>';
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
