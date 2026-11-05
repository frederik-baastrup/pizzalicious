<?php
namespace Playform\Feeds;

/**
 * Vores egen prishistorik. Rabatter vises KUN ud fra den – aldrig ud fra butikkernes førpris.
 * Reglen følger førprisreglerne: sammenligningsprisen er den laveste pris i de 30 dage før i dag.
 */
final class PriceHistory {

	public const MIN_DAYS = 30;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pf_price_history';
	}

	/** Gemmer dagens laveste pris for produktet hos butikken. */
	public static function record( int $product_id, string $shop, float $price, string $day ): void {
		global $wpdb;
		if ( $product_id <= 0 || $price <= 0 ) {
			return;
		}
		$t   = self::table();
		$cur = $wpdb->get_var( $wpdb->prepare( "SELECT price FROM $t WHERE product_id = %d AND shop = %s AND recorded_on = %s", $product_id, $shop, $day ) );
		if ( $cur === null ) {
			$wpdb->insert( $t, [ 'product_id' => $product_id, 'shop' => $shop, 'price' => $price, 'recorded_on' => $day ] );
		} elseif ( $price < (float) $cur ) {
			$wpdb->update( $t, [ 'price' => $price ], [ 'product_id' => $product_id, 'shop' => $shop, 'recorded_on' => $day ] );
		}
	}

	/** Laveste pris pr. dag (på tværs af butikker) for de seneste $days dage. */
	public static function daily( int $product_id, int $days = 90 ): array {
		global $wpdb;
		$t    = self::table();
		$from = gmdate( 'Y-m-d', strtotime( "-$days days" ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT recorded_on, MIN(price) AS price FROM $t WHERE product_id = %d AND recorded_on >= %s GROUP BY recorded_on ORDER BY recorded_on", $product_id, $from ),
			ARRAY_A
		) ?: [];
		return array_map( static fn( $r ) => [ 'day' => $r['recorded_on'], 'price' => (float) $r['price'] ], $rows );
	}

	/**
	 * Reel rabat målt af os selv.
	 * @return array{reference:float,percent:int}|null
	 */
	public static function drop( int $product_id, float $current, string $today ): ?array {
		if ( $current <= 0 ) {
			return null;
		}
		$series = self::daily( $product_id, 120 );
		if ( count( $series ) < 2 ) {
			return null;
		}
		// Find den dag, hvor den nuværende (lave) pris begyndte.
		$start = null;
		for ( $i = count( $series ) - 1; $i >= 0; $i-- ) {
			if ( $series[ $i ]['price'] <= $current + 0.001 ) {
				$start = $series[ $i ]['day'];
			} else {
				break;
			}
		}
		if ( ! $start ) {
			return null;
		}
		$window_from = gmdate( 'Y-m-d', strtotime( $start . ' -' . self::MIN_DAYS . ' days' ) );
		// Vi skal have fulgt prisen i hele perioden på 30 dage før nedsættelsen.
		if ( $series[0]['day'] > $window_from ) {
			return null;
		}
		$ref = null;
		foreach ( $series as $p ) {
			if ( $p['day'] >= $window_from && $p['day'] < $start ) {
				$ref = $ref === null ? $p['price'] : min( $ref, $p['price'] );
			}
		}
		if ( ! $ref || $current >= $ref ) {
			return null;
		}
		$percent = (int) round( ( $ref - $current ) / $ref * 100 );
		return $percent >= 5 ? [ 'reference' => $ref, 'percent' => $percent, 'since' => $start ] : null;
	}
}
