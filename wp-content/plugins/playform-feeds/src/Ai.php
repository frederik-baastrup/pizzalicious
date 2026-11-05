<?php
namespace Playform\Feeds;

/**
 * Lille klient til Claude API (Messages API).
 * API-nøglen sættes i wp-config.php: define( 'PLAYFORM_ANTHROPIC_API_KEY', '...' );
 * eller under Værktøjer › Playform Feeds.
 */
final class Ai {

	public const MODEL_FAST    = 'claude-haiku-5-5';   // sortering
	public const MODEL_WRITING = 'claude-sonnet-5-5';  // produkttekster

	public static function key(): string {
		if ( defined( 'PLAYFORM_ANTHROPIC_API_KEY' ) && PLAYFORM_ANTHROPIC_API_KEY ) {
			return (string) PLAYFORM_ANTHROPIC_API_KEY;
		}
		return (string) get_option( 'playform_anthropic_api_key', '' );
	}

	public static function enabled(): bool {
		return self::key() !== '';
	}

	/**
	 * Sender én besked og returnerer svaret som tekst.
	 */
	public static function ask( string $model, string $system, string $user, int $max_tokens = 2000 ): string {
		if ( ! self::enabled() ) {
			throw new \RuntimeException( 'Der er ingen API-nøgle til Claude.' );
		}
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'timeout' => 120,
				'headers' => [
					'x-api-key'         => self::key(),
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				],
				'body'    => wp_json_encode(
					[
						'model'      => $model,
						'max_tokens' => $max_tokens,
						'system'     => $system,
						'messages'   => [ [ 'role' => 'user', 'content' => $user ] ],
					]
				),
			]
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code !== 200 ) {
			throw new \RuntimeException( 'Claude API svarede ' . $code . ': ' . ( $body['error']['message'] ?? 'ukendt fejl' ) );
		}
		$text = '';
		foreach ( $body['content'] ?? [] as $block ) {
			if ( ( $block['type'] ?? '' ) === 'text' ) {
				$text .= $block['text'];
			}
		}
		return $text;
	}

	/** Finder det første JSON-objekt eller -array i et svar. */
	public static function json( string $text ) {
		$text = trim( $text );
		$text = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $text );
		$data = json_decode( $text, true );
		if ( $data !== null ) {
			return $data;
		}
		if ( preg_match( '/(\{.*\}|\[.*\])/s', $text, $m ) ) {
			return json_decode( $m[1], true );
		}
		return null;
	}
}
