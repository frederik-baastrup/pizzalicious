<?php
namespace Playform\Feeds;

/**
 * Tilmelding til nyhedsbrev via Brevo eller MailerLite – altid med bekræftelsesmail (double opt-in).
 * Vi gemmer ingen e-mails selv; de sendes direkte videre til tjenesten.
 *
 * Indstillinger (Værktøjer › Playform Feeds):
 *   playform_nl_provider  'brevo' | 'mailerlite' | ''
 *   playform_nl_key       API-nøgle (kan også sættes som PLAYFORM_NEWSLETTER_KEY i wp-config.php)
 *   playform_nl_list      Brevo: liste-id · MailerLite: gruppe-id
 *   playform_nl_template  Brevo: id på skabelonen til bekræftelsesmailen
 */
final class Newsletter {

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function enabled(): bool {
		return self::provider() !== '' && self::key() !== '';
	}

	public static function provider(): string {
		return (string) get_option( 'playform_nl_provider', '' );
	}

	private static function key(): string {
		return defined( 'PLAYFORM_NEWSLETTER_KEY' ) ? (string) PLAYFORM_NEWSLETTER_KEY : (string) get_option( 'playform_nl_key', '' );
	}

	public static function routes(): void {
		register_rest_route(
			'playform/v1',
			'/newsletter',
			[
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => [ self::class, 'subscribe' ],
				'args'                => [
					'email'   => [ 'required' => true, 'type' => 'string' ],
					'website' => [ 'required' => false, 'type' => 'string' ], // honningkrukke mod spam-robotter
				],
			]
		);
	}

	public static function subscribe( \WP_REST_Request $req ) {
		$email = sanitize_email( (string) $req->get_param( 'email' ) );
		if ( (string) $req->get_param( 'website' ) !== '' ) {
			return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Tak! Tjek din indbakke for at bekræfte.' ] );
		}
		if ( ! is_email( $email ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'message' => 'Skriv en gyldig e-mailadresse.' ], 400 );
		}
		// Højst 5 forsøg i timen pr. IP.
		$ip_key = 'pf_nl_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$tries  = (int) get_transient( $ip_key );
		if ( $tries >= 5 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'message' => 'Prøv igen om lidt.' ], 429 );
		}
		set_transient( $ip_key, $tries + 1, HOUR_IN_SECONDS );

		if ( ! self::enabled() ) {
			return new \WP_REST_Response( [ 'ok' => false, 'message' => 'Nyhedsbrevet er ikke sat op endnu.' ], 503 );
		}
		$ok = self::provider() === 'brevo' ? self::brevo( $email ) : self::mailerlite( $email );
		return $ok
			? new \WP_REST_Response( [ 'ok' => true, 'message' => 'Tak! Tjek din indbakke og bekræft tilmeldingen.' ] )
			: new \WP_REST_Response( [ 'ok' => false, 'message' => 'Noget gik galt. Prøv igen senere.' ], 502 );
	}

	private static function brevo( string $email ): bool {
		$res = wp_remote_post(
			'https://api.brevo.com/v3/contacts/doubleOptinConfirmation',
			[
				'timeout' => 15,
				'headers' => [ 'api-key' => self::key(), 'content-type' => 'application/json', 'accept' => 'application/json' ],
				'body'    => wp_json_encode(
					[
						'email'          => $email,
						'includeListIds' => [ (int) get_option( 'playform_nl_list' ) ],
						'templateId'     => (int) get_option( 'playform_nl_template' ),
						'redirectionUrl' => home_url( '/?nyhedsbrev=bekraeftet' ),
					]
				),
			]
		);
		$code = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );
		return $code >= 200 && $code < 300;
	}

	private static function mailerlite( string $email ): bool {
		$body = [ 'email' => $email, 'status' => 'unconfirmed' ];
		if ( get_option( 'playform_nl_list' ) ) {
			$body['groups'] = [ (string) get_option( 'playform_nl_list' ) ];
		}
		$res  = wp_remote_post(
			'https://connect.mailerlite.com/api/subscribers',
			[
				'timeout' => 15,
				'headers' => [ 'Authorization' => 'Bearer ' . self::key(), 'content-type' => 'application/json', 'accept' => 'application/json' ],
				'body'    => wp_json_encode( $body ),
			]
		);
		$code = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );
		return $code >= 200 && $code < 300;
	}
}
