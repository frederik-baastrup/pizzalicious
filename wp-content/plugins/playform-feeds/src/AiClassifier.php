<?php
namespace Playform\Feeds;

/**
 * Sorterer de produkter, som reglerne ikke kunne placere, med Claude.
 * Kører i portioner på 50 produkter pr. kald.
 */
final class AiClassifier {

	private const BATCH = 50;

	public static function run( int $limit = 500, ?callable $log = null ): array {
		global $wpdb;
		$t    = Offers::table();
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, product_id, name, brand, shop_category FROM $t WHERE category = %s AND active = 1 ORDER BY id LIMIT %d", Config::uncategorized_slug(), $limit ),
			ARRAY_A
		) ?: [];
		$stats = [ 'checked' => 0, 'moved' => 0, 'excluded' => 0, 'kept' => 0 ];
		$today = current_time( 'Y-m-d' );

		foreach ( array_chunk( $rows, self::BATCH ) as $batch ) {
			$answers = self::ask( $batch );
			foreach ( $batch as $r ) {
				$stats['checked']++;
				$cat = $answers[ (int) $r['id'] ] ?? null;
				if ( $cat === 'exclude' ) {
					$wpdb->update( $t, [ 'category' => 'exclude', 'active' => 0 ], [ 'id' => $r['id'] ] );
					if ( ! Offers::for_product( (int) $r['product_id'] ) ) {
						wp_update_post( [ 'ID' => (int) $r['product_id'], 'post_status' => 'draft' ] );
					}
					$stats['excluded']++;
				} elseif ( $cat && isset( Config::categories()[ $cat ] ) ) {
					$wpdb->update( $t, [ 'category' => $cat ], [ 'id' => $r['id'] ] );
					ProductSync::refresh( (int) $r['product_id'], $today );
					$stats['moved']++;
				} else {
					$stats['kept']++;
				}
			}
			if ( $log ) {
				$log( sprintf( '  %d tjekket, %d placeret, %d fravalgt', $stats['checked'], $stats['moved'], $stats['excluded'] ) );
			}
		}
		return $stats;
	}

	/** @return array<int,string> tilbuds-id → kategori-slug, 'exclude' eller 'unknown' */
	private static function ask( array $batch ): array {
		$cats = [];
		foreach ( Config::categories() as $slug => $def ) {
			$cats[] = "- $slug: {$def['name']}";
		}
		$site   = Config::site();
		$system = "Du sorterer produkter for {$site['name']}, en dansk side med {$site['niche']} til hjemmet.\n"
			. "Kategorier:\n" . implode( "\n", $cats ) . "\n"
			. "- exclude: hører ikke til på en side med {$site['niche']} (fødevarer, kaffebønner, tøj, tekstiler, hvidevarer til vask og frys, støvsugere, gavekort, reservedele m.m.)\n"
			. "- unknown: du er i tvivl\n"
			. 'Svar KUN med JSON: et objekt hvor nøglen er produktets id og værdien er kategoriens slug. Ingen forklaring.';
		$lines = array_map(
			static fn( $r ) => wp_json_encode( [ 'id' => (int) $r['id'], 'navn' => $r['name'], 'mærke' => $r['brand'], 'butikkens_kategori' => $r['shop_category'] ], JSON_UNESCAPED_UNICODE ),
			$batch
		);
		$data = Ai::json( Ai::ask( Ai::MODEL_FAST, $system, implode( "\n", $lines ), 3000 ) );
		$out  = [];
		if ( is_array( $data ) ) {
			foreach ( $data as $id => $slug ) {
				$out[ (int) $id ] = is_string( $slug ) ? sanitize_key( $slug ) : 'unknown';
			}
		}
		return $out;
	}
}
