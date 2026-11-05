<?php
/**
 * Samtykke til cookies (ePrivacy / cookiebekendtgørelsen / GDPR).
 *
 * Regler, koden følger:
 *  - Ikke-nødvendige scripts og cookies indlæses først efter aktivt samtykke (opt-in, aldrig forhåndsafkrydset).
 *  - "Kun nødvendige" og "Acceptér alle" er lige tydelige og lige nemme.
 *  - Samtykket kan ændres eller trækkes tilbage lige så let (link "Cookie-indstillinger" i bunden).
 *  - Valget gemmes i én nødvendig førstepartscookie (pf_consent) i højst 12 måneder, med tidspunkt og version.
 *  - Ændres kategorierne, hæves PLAYFORM_CONSENT_VERSION, og alle bliver spurgt igen.
 *
 * Tilføj tracking ALDRIG direkte i skabelonerne. Registrér det i stedet, så det kun køres efter samtykke:
 *
 *   add_filter( 'playform_consent_scripts', function ( array $s ): array {
 *       $s[] = [ 'category' => 'statistik', 'src' => 'https://…/script.js', 'attrs' => [ 'data-domain' => 'kitzhen.dk' ] ];
 *       $s[] = [ 'category' => 'marketing', 'inline' => 'fbq("init","123");' ];
 *       return $s;
 *   } );
 *
 * Kategorier: 'statistik' og 'marketing'. Husk at opdatere cookiepolitikken (inc/legal.php) og hæve versionerne.
 */

defined( 'ABSPATH' ) || exit;

const PLAYFORM_CONSENT_VERSION = 1;

function playform_consent_scripts(): array {
	return (array) apply_filters( 'playform_consent_scripts', [] );
}

// Registrerede scripts udskrives som inaktive (text/plain) og aktiveres af main.js, når kategorien er accepteret.
add_action(
	'wp_footer',
	static function () {
		foreach ( playform_consent_scripts() as $s ) {
			$cat = in_array( $s['category'] ?? '', [ 'statistik', 'marketing' ], true ) ? $s['category'] : 'marketing';
			$att = '';
			foreach ( (array) ( $s['attrs'] ?? [] ) as $k => $v ) {
				$att .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
			}
			if ( ! empty( $s['src'] ) ) {
				echo '<script type="text/plain" data-consent="' . esc_attr( $cat ) . '" data-src="' . esc_url( $s['src'] ) . '"' . $att . '></script>' . "\n"; // phpcs:ignore
			} elseif ( ! empty( $s['inline'] ) ) {
				echo '<script type="text/plain" data-consent="' . esc_attr( $cat ) . '"' . $att . '>' . $s['inline'] . '</script>' . "\n"; // phpcs:ignore
			}
		}
		get_template_part( 'template-parts/consent' );
	},
	5
);

/**
 * Matomo (WordPress-pluginnet "Matomo Analytics", kører på vores egen server).
 * Uden samtykke tæller Matomo uden cookies (requireCookieConsent). Har brugeren sagt ja til statistik,
 * må Matomo sætte sine cookies (_pk_id i højst 12 måneder). Køres før pluginets egen trackingkode,
 * som bruger samme _paq-kø. Trækkes samtykket tilbage, sletter main.js cookiesne og genindlæser siden.
 */
add_action(
	'wp_head',
	static function () {
		$v = (int) PLAYFORM_CONSENT_VERSION;
		echo "<script>(function(){var q=window._paq=window._paq||[];q.push(['requireCookieConsent']);q.push(['setVisitorCookieTimeout',31536000]);"
			. "var ok=function(){q.push(['setCookieConsentGiven']);};"
			. "try{var m=document.cookie.match(/(?:^|; )pf_consent=([^;]*)/),c=m&&JSON.parse(decodeURIComponent(m[1]));if(c&&c.v===$v&&c.statistik)ok();}catch(e){}"
			. "document.addEventListener('pf:consent',function(e){if(e.detail&&e.detail.statistik)ok();});})();</script>\n"; // phpcs:ignore
	},
	-9999 // før Matomo-pluginets egen kode, som også ligger tidligt i <head>
);
