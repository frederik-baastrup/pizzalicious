<?php
namespace Playform\Feeds;

/**
 * Gemmer produktbilleder på vores egen server.
 * Det er hurtigere, det holder, selvom en butik flytter sine billeder, og besøgendes
 * IP-adresser sendes ikke til butikkernes servere, bare fordi de ser et produktkort.
 */
final class ImageCache {

	private const MAX = 900; // px på den længste led

	public static function dir(): array {
		$u = wp_upload_dir();
		return [ trailingslashit( $u['basedir'] ) . 'playform-images', trailingslashit( $u['baseurl'] ) . 'playform-images' ];
	}

	private static function name( string $remote ): string {
		return substr( md5( $remote ), 0, 20 ) . '.jpg';
	}

	/** Lokal URL, hvis billedet er hentet – ellers tom streng. */
	public static function local_url( string $remote ): string {
		if ( $remote === '' ) {
			return '';
		}
		[ $dir, $url ] = self::dir();
		$file = self::name( $remote );
		return is_file( "$dir/$file" ) ? "$url/$file" : '';
	}

	/** Henter og nedskalerer ét billede. */
	public static function fetch( string $remote ): bool {
		[ $dir ] = self::dir();
		wp_mkdir_p( $dir );
		$dest = $dir . '/' . self::name( $remote );
		if ( is_file( $dest ) ) {
			return true;
		}
		$res = wp_remote_get( $remote, [ 'timeout' => 20, 'redirection' => 3, 'user-agent' => Config::site()['name'] . '/1.0 (+https://' . Config::site()['domain'] . ')' ] );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
			return false;
		}
		$type = (string) wp_remote_retrieve_header( $res, 'content-type' );
		if ( ! str_starts_with( $type, 'image/' ) ) {
			return false;
		}
		$tmp = wp_tempnam( 'pf-img' );
		file_put_contents( $tmp, wp_remote_retrieve_body( $res ) );
		$editor = wp_get_image_editor( $tmp );
		if ( is_wp_error( $editor ) ) {
			@unlink( $tmp );
			return false;
		}
		$editor->resize( self::MAX, self::MAX, false );
		$editor->set_quality( 82 );
		$saved = $editor->save( $dest, 'image/jpeg' );
		@unlink( $tmp );
		return ! is_wp_error( $saved );
	}

	public const HOOK = 'playform_image_cache';

	/** Henter manglende billeder løbende (hvert 5. minut), så nye produkter hurtigt får et lokalt billede. */
	public static function register(): void {
		add_filter( 'cron_schedules', static function ( array $s ): array {
			$s['pf_5min'] = [ 'interval' => 300, 'display' => 'Hvert 5. minut' ];
			return $s;
		} );
		add_action( self::HOOK, [ self::class, 'tick' ] );
		add_action( 'playform_feeds_after_import', static fn() => delete_option( 'playform_img_done' ) );
		add_action( 'init', static function () {
			if ( ! wp_next_scheduled( self::HOOK ) ) {
				wp_schedule_event( time() + 60, 'pf_5min', self::HOOK );
			}
		} );
	}

	public static function tick(): void {
		if ( get_option( 'playform_img_done' ) || get_transient( 'playform_feeds_running' ) || get_transient( 'playform_img_running' ) ) {
			return;
		}
		set_transient( 'playform_img_running', 1, 4 * MINUTE_IN_SECONDS );
		@set_time_limit( 120 ); // phpcs:ignore
		$s = self::run( 400, null, 50 );
		if ( 0 === $s['fetched'] + $s['failed'] ) {
			update_option( 'playform_img_done', time(), false ); // alt er hentet – vent til næste import
		}
		delete_transient( 'playform_img_running' );
	}

	/**
	 * Henter billeder for produkter, der mangler et lokalt billede – produkter på lager først.
	 * Billeder, der fejlede inden for det sidste døgn, springes over.
	 * @return array{fetched:int,failed:int}
	 */
	public static function run( int $limit = 500, ?callable $log = null, int $seconds = 0 ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.post_id, i.meta_value FROM {$wpdb->postmeta} i
				 LEFT JOIN {$wpdb->postmeta} s ON s.post_id = i.post_id AND s.meta_key = '_pf_in_stock'
				 WHERE i.meta_key = %s AND i.meta_value <> ''
				 ORDER BY ( s.meta_value = '1' ) DESC, i.post_id DESC",
				ProductSync::META_IMAGE
			),
			ARRAY_A
		) ?: [];
		$failed = (array) get_option( 'playform_img_failed', [] );
		$now    = time();
		$failed = array_filter( $failed, static fn( $t ) => $t > $now - DAY_IN_SECONDS );
		$start  = microtime( true );
		$stats  = [ 'fetched' => 0, 'failed' => 0 ];
		foreach ( $rows as $r ) {
			if ( $stats['fetched'] + $stats['failed'] >= $limit || ( $seconds && microtime( true ) - $start > $seconds ) ) {
				break;
			}
			$key = md5( $r['meta_value'] );
			if ( isset( $failed[ $key ] ) || self::local_url( $r['meta_value'] ) ) {
				continue;
			}
			if ( self::fetch( $r['meta_value'] ) ) {
				$stats['fetched']++;
			} else {
				$stats['failed']++;
				$failed[ $key ] = $now;
			}
			if ( $log && ( $stats['fetched'] + $stats['failed'] ) % 100 === 0 ) {
				$log( sprintf( '  %d hentet, %d fejlede', $stats['fetched'], $stats['failed'] ) );
			}
		}
		update_option( 'playform_img_failed', $failed, false );
		return $stats;
	}
}
