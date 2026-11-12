<?php
/**
 * Klik til butikkerne går gennem /go/ (12. nov. 2026, ejerens ønske):
 * - /go/ er lukket i robots.txt og markeret noindex, så Google, Bing, Ahrefs og andre pæne robotter ikke følger
 *   "Se pris"-knapperne. Robotklik fik konverteringen hos Partner-ads til at se dårligere ud, end den er.
 * - Robotter, der ignorerer robots.txt, men selv siger, de er robotter, sendes ikke videre (403).
 * - Rigtige besøgende sendes videre med 302 til Partner-ads med samme uid/uid2. Referrer-Policy på svaret
 *   sørger for, at Partner-ads ser den præcise side, klikket kom fra (når browseren tillader det).
 * - Hvert klik tælles i tabellen wp_pf_clicks (dag, uid2, uid) – vises under Værktøjer → Klik til butikker.
 * Adressen er signeret (HMAC), så /go/ kun sender videre til links, sitet selv har lavet (ingen åben omdirigering).
 */
defined( 'ABSPATH' ) || exit;

/** Signeret /go/-adresse til et Partner-ads-link (kaldes fra playform_aff_url()). */
function playform_go_url( string $dest ): string {
	$dest = html_entity_decode( $dest, ENT_QUOTES );
	$u    = rtrim( strtr( base64_encode( $dest ), '+/', '-_' ), '=' );
	return home_url( '/go/' ) . '?u=' . $u . '&s=' . substr( hash_hmac( 'sha256', $dest, wp_salt( 'auth' ) ), 0, 16 );
}

/** Robot? (brugeragenter, der selv siger det). */
function playform_go_is_bot(): bool {
	$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	return '' === $ua || (bool) preg_match( '~bot|crawl|spider|slurp|preview|fetch|monitor|headless|python|curl|wget|httpclient|java/|go-http|okhttp|scrapy|ahrefs|semrush|mj12|dotbot|petal|bytespider|facebookexternalhit|lighthouse|pagespeed~i', $ua );
}

add_action(
	'parse_request',
	static function (): void {
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		if ( ! preg_match( '~^/go/?$~', $path ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );

		$u    = (string) ( $_GET['u'] ?? '' );
		$s    = (string) ( $_GET['s'] ?? '' );
		$dest = (string) base64_decode( strtr( $u, '-_', '+/' ), true );
		$ok   = '' !== $dest
			&& hash_equals( substr( hash_hmac( 'sha256', $dest, wp_salt( 'auth' ) ), 0, 16 ), $s )
			&& preg_match( '~^https://(www\.)?partner-ads\.com/~i', $dest );
		if ( ! $ok ) {
			status_header( 404 );
			exit;
		}
		if ( playform_go_is_bot() ) {
			status_header( 403 );
			exit;
		}
		playform_go_count( $dest );
		header( 'Referrer-Policy: no-referrer-when-downgrade', true );
		wp_redirect( $dest, 302, 'Playform' );
		exit;
	},
	0
);

/** robots.txt: pæne robotter følger ikke /go/. */
add_filter(
	'robots_txt',
	static function ( $out ) {
		$out = (string) $out;
		if ( str_contains( $out, 'Disallow: /go/' ) ) {
			return $out;
		}
		// I den eksisterende gruppe for alle robotter, ellers som ny gruppe.
		$new = preg_replace( '~^(User-agent:\s*\*\s*)$~mi', "$1\nDisallow: /go/", $out, 1, $n );
		return $n ? $new : rtrim( $out ) . "\n\nUser-agent: *\nDisallow: /go/\n";
	},
	99
);

/** Tæl et klik (dag, uid2, uid). */
function playform_go_count( string $dest ): void {
	global $wpdb;
	parse_str( (string) wp_parse_url( $dest, PHP_URL_QUERY ), $q );
	$src  = substr( sanitize_title( (string) ( $q['uid2'] ?? '' ) ), 0, 80 );
	$item = substr( sanitize_title( (string) ( $q['uid'] ?? '' ) ), 0, 80 );
	playform_go_table();
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}pf_clicks (day, src, item, n) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE n = n + 1", current_time( 'Y-m-d' ), $src, $item ) ); // phpcs:ignore
}

function playform_go_table(): void {
	if ( get_option( 'playform_clicks_db' ) === '1' ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "CREATE TABLE {$wpdb->prefix}pf_clicks (\n day date NOT NULL,\n src varchar(80) NOT NULL DEFAULT '',\n item varchar(80) NOT NULL DEFAULT '',\n n int unsigned NOT NULL DEFAULT 0,\n PRIMARY KEY  (day,src,item)\n) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'playform_clicks_db', '1', false );
}

/** Værktøjer → Klik til butikker: klik pr. side (uid2) og top-produkter de sidste 30 dage. */
add_action(
	'admin_menu',
	static function (): void {
		add_management_page(
			'Klik til butikker',
			'Klik til butikker',
			'manage_options',
			'playform-clicks',
			static function (): void {
				global $wpdb;
				playform_go_table();
				$t     = $wpdb->prefix . 'pf_clicks';
				$since = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -29 days' ) );
				$days  = $wpdb->get_results( $wpdb->prepare( "SELECT day, SUM(n) n FROM $t WHERE day >= %s GROUP BY day ORDER BY day DESC", $since ) ); // phpcs:ignore
				$srcs  = $wpdb->get_results( $wpdb->prepare( "SELECT src, SUM(n) n FROM $t WHERE day >= %s GROUP BY src ORDER BY n DESC LIMIT 50", $since ) ); // phpcs:ignore
				$items = $wpdb->get_results( $wpdb->prepare( "SELECT item, src, SUM(n) n FROM $t WHERE day >= %s GROUP BY item, src ORDER BY n DESC LIMIT 50", $since ) ); // phpcs:ignore
				echo '<div class="wrap"><h1>Klik til butikker (sidste 30 dage)</h1><p>Rigtige besøgende, der klikkede på "Se pris". Robotter tælles ikke. Sammenlign med klik og salg hos Partner-ads (samme uid/uid2).</p>';
				$table = static function ( string $title, array $cols, array $rows ): void {
					echo '<h2>' . esc_html( $title ) . '</h2><table class="widefat striped" style="max-width:900px"><thead><tr>';
					foreach ( $cols as $c ) {
						echo '<th>' . esc_html( $c ) . '</th>';
					}
					echo '</tr></thead><tbody>';
					foreach ( $rows as $r ) {
						echo '<tr>';
						foreach ( (array) $r as $v ) {
							echo '<td>' . esc_html( (string) $v ) . '</td>';
						}
						echo '</tr>';
					}
					echo $rows ? '' : '<tr><td colspan="' . count( $cols ) . '">Ingen klik endnu.</td></tr>';
					echo '</tbody></table>';
				};
				$table( 'Pr. dag', [ 'Dag', 'Klik' ], $days );
				$table( 'Pr. side (uid2)', [ 'Side', 'Klik' ], $srcs );
				$table( 'Top-produkter', [ 'Produkt (uid)', 'Side (uid2)', 'Klik' ], $items );
				echo '</div>';
			}
		);
	}
);
