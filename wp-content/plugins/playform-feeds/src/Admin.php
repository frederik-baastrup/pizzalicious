<?php
namespace Playform\Feeds;

/**
 * Oversigt under Værktøjer › Playform Feeds: seneste import, antal pr. kategori og en knap til at køre importen nu.
 */
final class Admin {

	/** Hvor mange produkter der læses i hver AJAX-runde, og hvor mange billeder. */
	private const PRODUCT_CHUNK = 250;
	private const IMAGE_CHUNK   = 80;
	private const STATE_OPTION  = 'playform_import_state';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_post_playform_run_import', [ self::class, 'queue_import' ] );
		add_action( 'admin_post_playform_save_ai', [ self::class, 'save_ai' ] );
		add_action( 'admin_post_playform_ai_test', [ self::class, 'ai_test' ] );
		add_action( 'admin_post_playform_clear_cache', [ self::class, 'clear_cache' ] );
		add_action( 'wp_ajax_playform_import_step', [ self::class, 'ajax_step' ] );
	}

	/** Forsidens "Populære"/"Tilbud"/"Nyt"-sliders caches 6 timer – ryddes efter hver import. */
	public static function clear_front_cache(): void {
		delete_transient( 'playform_front_popular' );
		delete_transient( 'playform_front_deals' );
		delete_transient( 'playform_front_new' );
		foreach ( [ 'espressomaskine', 'gasgrill', 'roeremaskine', 'pizzaovn', 'airfryer' ] as $c ) {
			delete_transient( 'playform_front_cat_' . $c ); // forsidens kategori-sektioner
		}
	}

	public static function clear_cache(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Ingen adgang' );
		}
		check_admin_referer( 'playform_clear_cache' );
		self::clear_front_cache();
		wp_safe_redirect( admin_url( 'tools.php?page=playform-feeds&cleared=1' ) );
		exit;
	}

	public static function menu(): void {
		add_management_page( 'Playform Feeds', 'Playform Feeds', 'manage_woocommerce', 'playform-feeds', [ self::class, 'page' ] );
	}

	public static function queue_import(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Ingen adgang' );
		}
		check_admin_referer( 'playform_run_import' );
		wp_schedule_single_event( time(), Cron::HOOK );
		wp_safe_redirect( admin_url( 'tools.php?page=playform-feeds&queued=1' ) );
		exit;
	}

	/**
	 * Én runde af importen, kaldt fra JavaScript igen og igen, så vi aldrig rammer PHP's
	 * tidsgrænse: hver runde læser kun en bid af én butiks feed, eller henter nogle billeder.
	 */
	public static function ajax_step(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Ingen adgang' ], 403 );
		}
		check_ajax_referer( 'playform_import_step' );

		$state = get_option( self::STATE_OPTION );
		if ( ! empty( $_POST['reset'] ) || ! is_array( $state ) ) {
			$state = [
				'started'     => current_time( 'mysql', true ),
				'today'       => current_time( 'Y-m-d' ),
				'shop_keys'   => array_keys( array_filter( Config::shops(), static fn( $s ) => ! empty( $s['url'] ) ) ),
				'shop_index'  => 0,
				'offset'      => 0,
				'file'        => null,
				'shops_stats' => [],
				'phase'       => 'shops',
				'images'      => [ 'fetched' => 0, 'failed' => 0 ],
			];
		}

		try {
			$result = self::run_one_unit( $state );
		} catch ( \Throwable $e ) {
			update_option( self::STATE_OPTION, $state, false );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
		update_option( self::STATE_OPTION, $state, false );
		wp_send_json_success( $result );
	}

	/** Udfører én bid af importen og opdaterer $state (sendes som reference). */
	private static function run_one_unit( array &$state ): array {
		$shops = Config::shops();

		if ( $state['phase'] === 'shops' ) {
			if ( $state['shop_index'] >= count( $state['shop_keys'] ) ) {
				$state['phase'] = 'drops';
				return [ 'status' => 'Genberegner rabatter …', 'line' => '', 'done' => false ];
			}
			$key  = $state['shop_keys'][ $state['shop_index'] ];
			$name = $shops[ $key ]['name'] ?? $key;
			if ( ! isset( $state['shops_stats'][ $key ] ) ) {
				$state['shops_stats'][ $key ] = [ 'name' => $name, 'read' => 0, 'excluded' => 0, 'new' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0, 'error' => '' ];
			}
			if ( ! $state['file'] ) {
				try {
					$state['file'] = FeedReader::download( $shops[ $key ]['url'], $key );
				} catch ( \Throwable $e ) {
					$state['shops_stats'][ $key ]['error'] = $e->getMessage();
					$state['shop_index']++;
					$state['offset'] = 0;
					$state['file']   = null;
					return [ 'status' => "Fejl hos $name – springer videre …", 'line' => "FEJL $name: " . $e->getMessage(), 'done' => false ];
				}
			}

			$importer = new Importer();
			$chunk    = $importer->run_chunk( $key, $state['file'], $state['offset'], self::PRODUCT_CHUNK, $state['started'], $state['today'] );
			foreach ( $chunk['stats'] as $k => $v ) {
				$state['shops_stats'][ $key ][ $k ] += $v;
			}
			$state['offset'] = $chunk['next_offset'];

			if ( $chunk['eof'] ) {
				$state['shops_stats'][ $key ]['removed'] = Importer::finish_shop( $key, $state['started'] );
				$state['shop_index']++;
				$state['offset'] = 0;
				$state['file']   = null;
			}

			$s = $state['shops_stats'][ $key ];
			return [
				'status' => sprintf( '%s: %d læst (%d nye, %d ændret)', $name, $s['read'], $s['new'], $s['updated'] ),
				'line'   => $chunk['eof'] ? sprintf( '%s færdig – %d læst, %d nye, %d ændret, %d væk', $name, $s['read'], $s['new'], $s['updated'], $s['removed'] ) : '',
				'done'   => false,
			];
		}

		if ( $state['phase'] === 'drops' ) {
			Importer::recalc_drops( $state['today'] );
			$sales          = Sales::refresh_all( $state['today'] );
			$state['phase'] = 'images';
			return [ 'status' => 'Henter produktbilleder …', 'line' => sprintf( 'Rabatter genberegnet. %d produkter på tilbud hos butikkerne.', $sales ), 'done' => false ];
		}

		if ( $state['phase'] === 'images' ) {
			$res = ImageCache::run( self::IMAGE_CHUNK );
			$state['images']['fetched'] += $res['fetched'];
			$state['images']['failed']  += $res['failed'];
			if ( ( $res['fetched'] + $res['failed'] ) < self::IMAGE_CHUNK ) {
				$state['phase'] = 'done';
			}
			return [
				'status' => sprintf( 'Billeder: %d hentet, %d mislykkedes', $state['images']['fetched'], $state['images']['failed'] ),
				'line'   => '',
				'done'   => false,
			];
		}

		// phase === 'done'
		$summary = [
			'started'  => $state['started'],
			'finished' => current_time( 'mysql', true ),
			'shops'    => $state['shops_stats'],
		];
		update_option( 'playform_feeds_last_run', $summary, false );
		delete_option( self::STATE_OPTION );
		self::clear_front_cache();
		$total_new = array_sum( wp_list_pluck( $state['shops_stats'], 'new' ) );
		$total_upd = array_sum( wp_list_pluck( $state['shops_stats'], 'updated' ) );
		return [
			'status' => sprintf( 'Import færdig – %d nye, %d ændret, %d billeder hentet.', $total_new, $total_upd, $state['images']['fetched'] ),
			'line'   => 'Importen er færdig.',
			'done'   => true,
		];
	}

	public static function ai_test(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Ingen adgang' );
		}
		check_admin_referer( 'playform_ai_test' );
		@set_time_limit( 300 ); // phpcs:ignore
		Cron::write_texts( 3 );
		wp_safe_redirect( admin_url( 'tools.php?page=playform-feeds' ) );
		exit;
	}

	public static function save_ai(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Ingen adgang' );
		}
		check_admin_referer( 'playform_save_ai' );
		$key = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		if ( $key !== '' ) {
			update_option( 'playform_anthropic_api_key', $key, false );
		}
		if ( ! empty( $_POST['remove_key'] ) ) {
			delete_option( 'playform_anthropic_api_key' );
		}
		update_option( 'playform_ai_texts_per_run', max( 0, (int) ( $_POST['texts_per_run'] ?? 0 ) ) );
		$provider = sanitize_key( $_POST['nl_provider'] ?? '' );
		update_option( 'playform_nl_provider', in_array( $provider, [ 'brevo', 'mailerlite' ], true ) ? $provider : '' );
		$nl_key = trim( sanitize_text_field( wp_unslash( $_POST['nl_key'] ?? '' ) ) );
		if ( $nl_key !== '' ) {
			update_option( 'playform_nl_key', $nl_key, false );
		}
		update_option( 'playform_nl_list', sanitize_text_field( wp_unslash( $_POST['nl_list'] ?? '' ) ) );
		update_option( 'playform_nl_template', (int) ( $_POST['nl_template'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'tools.php?page=playform-feeds&saved=1' ) );
		exit;
	}

	public static function page(): void {
		global $wpdb;
		$last = get_option( 'playform_feeds_last_run' );
		$t    = Offers::table();
		$cats = $wpdb->get_results( "SELECT category, COUNT(DISTINCT product_id) AS n, SUM(active AND in_stock) AS stock FROM $t GROUP BY category ORDER BY n DESC", ARRAY_A ) ?: [];
		$names = wp_list_pluck( Config::categories(), 'name' );
		echo '<div class="wrap"><h1>Playform Feeds</h1>';
		if ( isset( $_GET['queued'] ) ) {
			echo '<div class="notice notice-success"><p>Importen er sat i gang og kører i baggrunden.</p></div>';
		}
		if ( isset( $_GET['cleared'] ) ) {
			echo '<div class="notice notice-success"><p>Forsidens cache er ryddet – genindlæs forsiden.</p></div>';
		}
		$nonce = wp_create_nonce( 'playform_import_step' );
		echo '<p><button type="button" id="playform-import-btn" class="button button-primary">Kør import nu</button> ';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		wp_nonce_field( 'playform_clear_cache' );
		echo '<input type="hidden" name="action" value="playform_clear_cache"><button class="button">Ryd forside-cache</button></form> ';
		echo '<span id="playform-import-status" style="margin-left:8px"></span></p>';
		echo '<p class="description">Forsidens "Populære produkter" m.fl. caches 6 timer. Hvis de står tomme efter en import, så tryk "Ryd forside-cache".</p>';
		echo '<div id="playform-import-log" style="font-family:monospace;font-size:12px;max-height:260px;overflow:auto;background:#f6f7f7;border:1px solid #dcdcde;padding:8px;display:none;white-space:pre-wrap"></div>';
		?>
		<script>
		(function () {
			var btn = document.getElementById('playform-import-btn');
			var status = document.getElementById('playform-import-status');
			var log = document.getElementById('playform-import-log');
			if (!btn) return;
			function write(line) {
				log.style.display = 'block';
				log.textContent += line + "\n";
				log.scrollTop = log.scrollHeight;
			}
			function step(reset) {
				var body = new URLSearchParams();
				body.set('action', 'playform_import_step');
				body.set('_ajax_nonce', '<?php echo esc_js( $nonce ); ?>');
				if (reset) body.set('reset', '1');
				fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (!res.success) {
							status.textContent = 'Fejl: ' + (res.data && res.data.message ? res.data.message : 'ukendt fejl');
							btn.disabled = false;
							btn.textContent = 'Prøv igen';
							return;
						}
						var d = res.data;
						status.textContent = d.status;
						if (d.line) write(d.line);
						if (d.done) {
							btn.disabled = false;
							btn.textContent = 'Kør import nu';
							status.textContent = 'Færdig! ' + d.status;
							setTimeout(function () { window.location.reload(); }, 1200);
							return;
						}
						step(false);
					})
					.catch(function (err) {
						status.textContent = 'Netværksfejl – prøver igen om 3 sekunder …';
						setTimeout(function () { step(false); }, 3000);
					});
			}
			btn.addEventListener('click', function () {
				btn.disabled = true;
				btn.textContent = 'Kører …';
				log.textContent = '';
				status.textContent = 'Starter …';
				step(true);
			});
		})();
		</script>
		<?php

		if ( $last ) {
			echo '<h2>Seneste import</h2><p>' . esc_html( get_date_from_gmt( $last['finished'], 'j. M Y H:i' ) ) . '</p>';
			echo '<table class="widefat striped"><thead><tr><th>Butik</th><th>Læst</th><th>Fravalgt</th><th>Nye</th><th>Ændret</th><th>Uændret</th><th>Væk</th><th>Fejl</th></tr></thead><tbody>';
			foreach ( $last['shops'] as $s ) {
				printf(
					'<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%s</td></tr>',
					esc_html( $s['name'] ), $s['read'], $s['excluded'], $s['new'], $s['updated'], $s['unchanged'], $s['removed'], esc_html( $s['error'] )
				);
			}
			echo '</tbody></table>';
		}
		$st = (array) get_option( 'playform_ai_status', [] );
		echo '<h2>AI</h2>';
		echo '<p><strong>Produkttekster:</strong> ' . ( $st ? esc_html( sprintf( 'Seneste kørsel %s: %d skrevet', $st['time'] ?? '', (int) ( $st['done'] ?? 0 ) ) ) . ( ! empty( $st['error'] ) ? ' – <span style="color:#b32d2e">fejl: ' . esc_html( $st['error'] ) . '</span>' : '' ) : 'har ikke kørt endnu (kører efter hver fuld import).' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:12px">';
		wp_nonce_field( 'playform_ai_test' );
		echo '<input type="hidden" name="action" value="playform_ai_test"><button class="button">Skriv 3 tekster nu</button> <span class="description">Test af nøgle og kredit – tager ca. et minut.</span></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'playform_save_ai' );
		echo '<input type="hidden" name="action" value="playform_save_ai"><table class="form-table"><tr><th><label for="api_key">Claude API-nøgle</label></th><td>';
		if ( defined( 'PLAYFORM_ANTHROPIC_API_KEY' ) ) {
			echo 'Sat i wp-config.php';
		} else {
			echo '<input type="password" id="api_key" name="api_key" class="regular-text" autocomplete="off" placeholder="' . ( Ai::enabled() ? 'Gemt – skriv en ny for at skifte' : 'sk-ant-…' ) . '">';
			if ( Ai::enabled() ) {
				echo ' <label><input type="checkbox" name="remove_key" value="1"> Fjern nøglen</label>';
			}
		}
		echo '</td></tr><tr><th><label for="texts_per_run">Nye tekster pr. import</label></th><td><input type="number" min="0" max="500" id="texts_per_run" name="texts_per_run" value="' . (int) get_option( 'playform_ai_texts_per_run', 100 ) . '"> <span class="description">0 = kun når du selv beder om det. Tekster, der består faktatjekket, udgives automatisk; resten venter under Produkter → Tekster til godkendelse.</span></td></tr></table>';
		$prov = (string) get_option( 'playform_nl_provider', '' );
		echo '<h2>Nyhedsbrev</h2><table class="form-table">';
		echo '<tr><th><label for="nl_provider">Tjeneste</label></th><td><select id="nl_provider" name="nl_provider"><option value="">Ikke sat op</option><option value="brevo"' . selected( $prov, 'brevo', false ) . '>Brevo</option><option value="mailerlite"' . selected( $prov, 'mailerlite', false ) . '>MailerLite</option></select></td></tr>';
		echo '<tr><th><label for="nl_key">API-nøgle</label></th><td>' . ( defined( 'PLAYFORM_NEWSLETTER_KEY' ) ? 'Sat i wp-config.php' : '<input type="password" id="nl_key" name="nl_key" class="regular-text" autocomplete="off" placeholder="' . ( get_option( 'playform_nl_key' ) ? 'Gemt – skriv en ny for at skifte' : '' ) . '">' ) . '</td></tr>';
		echo '<tr><th><label for="nl_list">Liste-id (Brevo) / gruppe-id (MailerLite)</label></th><td><input type="text" id="nl_list" name="nl_list" value="' . esc_attr( (string) get_option( 'playform_nl_list', '' ) ) . '"></td></tr>';
		echo '<tr><th><label for="nl_template">Skabelon til bekræftelsesmail (kun Brevo)</label></th><td><input type="number" id="nl_template" name="nl_template" value="' . (int) get_option( 'playform_nl_template', 0 ) . '"> <span class="description">MailerLite: slå "Double opt-in for API" til i din konto.</span></td></tr></table>';
		echo '<p><button class="button">Gem</button></p></form>';
		echo '<h2>Produkter pr. kategori</h2><table class="widefat striped" style="max-width:520px"><thead><tr><th>Kategori</th><th>Produkter</th><th>På lager</th></tr></thead><tbody>';
		foreach ( $cats as $c ) {
			$label = $names[ $c['category'] ] ?? ( $c['category'] === Config::uncategorized_slug() ? 'Ukategoriseret (skjult)' : $c['category'] );
			printf( '<tr><td>%s</td><td>%d</td><td>%d</td></tr>', esc_html( $label ), $c['n'], $c['stock'] );
		}
		echo '</tbody></table></div>';
	}
}
