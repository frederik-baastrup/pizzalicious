<?php
namespace Playform\Feeds;

/**
 * Tjekker, at butikkernes produktsider stadig findes. Feeds indeholder af og til varer, hvis side er
 * fjernet hos butikken (giver 404) – så må vi ikke sende folk derhen.
 *
 * Efter hver import tjekkes en portion tilbud (de længst utjekkede først), så alle aktive tilbud på lager
 * gennemgås løbende. Svarer siden 404/410 – eller sender den videre til butikkens forside – gemmes adressen
 * i link_dead_url, og tilbuddet tæller ikke med (Offers::for_product). Ændrer butikken adressen i feedet,
 * er tilbuddet automatisk med igen (link_dead_url matcher ikke længere).
 */
final class LinkCheck {

	public const PER_RUN = 300;

	public static function run( int $limit = self::PER_RUN ): array {
		global $wpdb;
		$t    = Offers::table();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_id, url FROM $t WHERE active = 1 AND in_stock = 1 AND product_id > 0
				 ORDER BY (link_checked IS NULL) DESC, link_checked ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		) ?: [];
		$dead    = 0;
		$touched = [];
		foreach ( $rows as $r ) {
			$is_dead = self::is_dead( (string) $r['url'] );
			if ( null === $is_dead ) { // kunne ikke afgøres (timeout, blokering) – prøv igen næste gang
				$wpdb->update( $t, [ 'link_checked' => current_time( 'mysql', true ) ], [ 'id' => $r['id'] ] );
				continue;
			}
			$wpdb->update(
				$t,
				[ 'link_checked' => current_time( 'mysql', true ), 'link_dead_url' => $is_dead ? $r['url'] : null ],
				[ 'id' => $r['id'] ]
			);
			if ( $is_dead ) {
				++$dead;
				$touched[ (int) $r['product_id'] ] = true;
			}
		}
		foreach ( array_keys( $touched ) as $pid ) {
			ProductSync::refresh( $pid, current_time( 'Y-m-d' ) );
		}
		return [ 'checked' => count( $rows ), 'dead' => $dead ];
	}

	/** Butikkens egen adresse i et Partner-ads-link (htmlurl=…), ellers linket selv. */
	public static function target( string $url ): string {
		$q = (string) wp_parse_url( html_entity_decode( $url ), PHP_URL_QUERY );
		parse_str( $q, $args );
		return ! empty( $args['htmlurl'] ) ? (string) $args['htmlurl'] : $url;
	}

	/** true = siden findes ikke, false = den findes, null = kunne ikke afgøres. */
	public static function is_dead( string $url ): ?bool {
		$target = self::target( $url );
		$start  = wp_parse_url( $target );
		if ( empty( $start['host'] ) ) {
			return null;
		}
		$current = $target;
		for ( $hop = 0; $hop < 5; $hop++ ) {
			$res = wp_remote_get(
				$current,
				[
					'timeout'             => 10,
					'redirection'         => 0,
					'limit_response_size' => 8192,
					'user-agent'          => 'Mozilla/5.0 (compatible; ' . Config::site()['name'] . 'LinkCheck/1.0; +https://' . Config::site()['domain'] . ')',
				]
			);
			if ( is_wp_error( $res ) ) {
				return null;
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			if ( 404 === $code || 410 === $code ) {
				return true;
			}
			if ( $code >= 300 && $code < 400 ) {
				$loc = (string) wp_remote_retrieve_header( $res, 'location' );
				if ( '' === $loc ) {
					return null;
				}
				$next = str_starts_with( $loc, 'http' ) ? $loc : ( $start['scheme'] ?? 'https' ) . '://' . $start['host'] . '/' . ltrim( $loc, '/' );
				// En vare, der sendes videre til butikkens forside, findes ikke længere.
				$path = trim( (string) wp_parse_url( $next, PHP_URL_PATH ), '/' );
				if ( '' === $path && '' !== trim( (string) ( $start['path'] ?? '' ), '/' ) ) {
					return true;
				}
				$current = $next;
				continue;
			}
			return $code >= 200 && $code < 300 ? false : null; // 403/429/5xx: blokering eller fejl – ikke bevis
		}
		return null;
	}
}
