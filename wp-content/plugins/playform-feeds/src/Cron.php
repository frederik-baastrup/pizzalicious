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
		if ( ! get_option( 'playform_ai_per_run_v3' ) ) {
			update_option( 'playform_ai_texts_per_run', 100 ); // 6. nov. 2026: tilbage til 100 pr. import (200 i døgnet)
			update_option( 'playform_ai_per_run_v3', 1, false );
		}
		$n    = (int) get_option( 'playform_ai_texts_per_run', 100 ); // 2 importer i døgnet × 100 = dagens loft på 200
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
		// Besked til ejeren, når der ikke er flere produkter at skrive til (første gang) – og hvis jobbet er stoppet af en fejl.
		$site = Config::site();
		$to   = (string) get_option( 'admin_email' );
		if ( $n > 0 && ! $queue && $to && ! get_option( 'playform_ai_done_mailed' ) ) {
			update_option( 'playform_ai_done_mailed', current_time( 'mysql' ), false );
			wp_mail( $to, $site['name'] . ': alle produkter har nu en tekst', "Hej\n\nAI-jobbet på " . $site['name'] . " har ikke flere produkter uden tekst. Nye produkter fra kommende importer får tekst automatisk.\n\nTekster, der ikke bestod faktatjekket, venter under Produkter → Tekster til godkendelse.\n" );
		} elseif ( $error && ! str_contains( $error, 'loft' ) && $to && get_option( 'playform_ai_error_mailed' ) !== substr( $error, 0, 80 ) ) {
			update_option( 'playform_ai_error_mailed', substr( $error, 0, 80 ), false );
			wp_mail( $to, $site['name'] . ': AI-teksterne er stoppet', "Hej\n\nAI-jobbet på " . $site['name'] . " stoppede med fejlen:\n\n" . $error . "\n\nTjek API-nøglen og kreditten hos Anthropic. Jobbet prøver igen efter næste import.\n" );
		}
		if ( ! $queue ) {
			// Køen er tom nu, men nye produkter kan komme: tillad en ny "alle har tekst"-mail, hvis køen senere fyldes og tømmes igen.
			update_option( 'playform_ai_queue_was_empty', 1, false );
		} elseif ( get_option( 'playform_ai_queue_was_empty' ) ) {
			delete_option( 'playform_ai_queue_was_empty' );
			delete_option( 'playform_ai_done_mailed' );
		}
		return $done;
	}
}
