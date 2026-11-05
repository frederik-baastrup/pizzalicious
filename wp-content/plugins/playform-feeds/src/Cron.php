<?php
namespace Playform\Feeds;

/**
 * Importen kører kl. 05:30 og 17:30 dansk tid.
 * På hostingen bør et rigtigt cronjob kalde: wp playform import (se README).
 */
final class Cron {

	public const HOOK = 'playform_feeds_import';

	public static function register(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
		ImageCache::register();
	}

	public static function schedule(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$tz   = new \DateTimeZone( 'Europe/Copenhagen' );
		$next = new \DateTime( 'today 05:30', $tz );
		$now  = new \DateTime( 'now', $tz );
		while ( $next <= $now ) {
			$next->modify( '+12 hours' );
		}
		wp_schedule_event( $next->getTimestamp(), 'twicedaily', self::HOOK );
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run(): void {
		if ( get_transient( 'playform_feeds_running' ) ) {
			return;
		}
		set_transient( 'playform_feeds_running', 1, 2 * HOUR_IN_SECONDS );
		try {
			( new Importer() )->run();
			ImageCache::run( 1500 );
			self::after_import();
		} finally {
			delete_transient( 'playform_feeds_running' );
		}
	}

	/** AI-sortering og nye produkttekster. Kører efter hver fuld import (både WP-cron og `wp playform import`). */
	public static function after_import(): int {
		do_action( 'playform_feeds_after_import' ); // fx temaets ugentlige tjek af købsguides
		try {
			LinkCheck::run(); // døde produktsider hos butikkerne
		} catch ( \Throwable $e ) {
			error_log( Config::site()['name'] . ' linktjek: ' . $e->getMessage() ); // phpcs:ignore
		}
		if ( ! Ai::enabled() ) {
			update_option( 'playform_ai_status', [ 'time' => current_time( 'mysql' ), 'done' => 0, 'error' => 'Der er ingen API-nøgle.' ], false );
			return 0;
		}
		$error = '';
		try {
			AiClassifier::run( 400 );
		} catch ( \Throwable $e ) {
			$error = 'Sortering: ' . $e->getMessage(); // stop ikke teksterne, fordi sorteringen fejler
		}
		$n    = (int) get_option( 'playform_ai_texts_per_run', 100 );
		$done = self::write_texts( $n, $error );
		return $done;
	}

	/** Skriver op til $n nye tekster og gemmer status (vises under Værktøjer → Playform Feeds). */
	public static function write_texts( int $n, string $error = '' ): int {
		$done  = 0;
		$queue = $n > 0 ? AiWriter::queue( $n ) : [];
		foreach ( $queue as $id ) {
			try {
				AiWriter::generate( (int) $id );
				++$done;
			} catch ( \Throwable $e ) {
				$error = $e->getMessage(); // fx manglende kredit eller dagens loft – prøv igen næste gang
				break;
			}
		}
		update_option( 'playform_ai_status', [ 'time' => current_time( 'mysql' ), 'done' => $done, 'queued' => count( $queue ), 'error' => $error ], false );
		return $done;
	}
}
