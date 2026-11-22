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

	/**
	 * Laveste pris pr. dag (på tværs af butikker) for de seneste $days dage.
	 * Historikken gemmes komprimeret (22. nov. 2026, databasen ramte 1 GB): uændrede dage midt i en periode slettes
	 * af compact(), så dagene udfyldes her med den seneste kendte pris pr. butik.
	 */
	public static function daily( int $product_id, int $days = 90 ): array {
		global $wpdb;
		$t     = self::table();
		$today = current_time( 'Y-m-d' );
		$from  = gmdate( 'Y-m-d', strtotime( $today . " -$days days" ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT shop, recorded_on, price FROM $t WHERE product_id = %d AND recorded_on >= %s ORDER BY recorded_on", $product_id, $from ),
			ARRAY_A
		) ?: [];
		// Seneste pris før vinduet pr. butik, så første dag i vinduet også har en pris.
		$before = $wpdb->get_results(
			$wpdb->prepare( "SELECT h.shop, h.price, h.recorded_on FROM $t h JOIN ( SELECT shop, MAX(recorded_on) d FROM $t WHERE product_id = %d AND recorded_on < %s GROUP BY shop ) b ON b.shop = h.shop AND b.d = h.recorded_on WHERE h.product_id = %d", $product_id, $from, $product_id ),
			ARRAY_A
		) ?: [];
		if ( ! $rows && ! $before ) {
			return [];
		}
		$by_day = [];
		foreach ( $rows as $r ) {
			$by_day[ $r['recorded_on'] ][ $r['shop'] ] = (float) $r['price'];
		}
		$cur   = [];
		$start = $rows ? $rows[0]['recorded_on'] : $today;
		foreach ( $before as $b ) {
			$cur[ $b['shop'] ] = (float) $b['price'];
			$start             = $from;
		}
		$last = $rows ? end( $rows )['recorded_on'] : $start;
		$out  = [];
		for ( $d = $start; $d <= $last; $d = gmdate( 'Y-m-d', strtotime( $d . ' +1 day' ) ) ) {
			foreach ( $by_day[ $d ] ?? [] as $shop => $price ) {
				$cur[ $shop ] = $price;
			}
			if ( $cur ) {
				$out[] = [ 'day' => $d, 'price' => min( $cur ) ];
			}
		}
		return $out;
	}

	/**
	 * Komprimér: slet dage, hvor prisen er den samme som dagen før og dagen efter hos samme butik.
	 * Første og sidste dag i hver periode bliver (daily() og Sales::reference() udfylder hullerne).
	 * Kører i bidder pr. produkt-id for at skåne serveren. Returnerer antal slettede rækker.
	 */
	public static function compact( int $max_seconds = 120 ): int {
		global $wpdb;
		$t       = self::table();
		$cutoff  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -1 day' ) );
		$max_id  = (int) $wpdb->get_var( "SELECT MAX(product_id) FROM $t" );
		$from_id = (int) get_option( 'playform_history_compact_from', 0 );
		$deleted = 0;
		$t0      = time();
		while ( $from_id <= $max_id && time() - $t0 < $max_seconds ) {
			$to_id = $from_id + 500;
			$ids   = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT h.id FROM $t h
					JOIN $t p ON p.product_id = h.product_id AND p.shop = h.shop AND p.recorded_on = DATE_SUB(h.recorded_on, INTERVAL 1 DAY) AND p.price = h.price
					JOIN $t n ON n.product_id = h.product_id AND n.shop = h.shop AND n.recorded_on = DATE_ADD(h.recorded_on, INTERVAL 1 DAY) AND n.price = h.price
					WHERE h.product_id >= %d AND h.product_id < %d AND h.recorded_on < %s",
					$from_id,
					$to_id,
					$cutoff
				)
			);
			// Slet hver anden række i en sammenhængende periode ad gangen er ikke nødvendigt: en række uden ændring
			// på begge sider er altid midt i en periode, og endepunkterne er aldrig med i $ids.
			foreach ( array_chunk( array_map( 'intval', $ids ), 1000 ) as $chunk ) {
				$deleted += (int) $wpdb->query( "DELETE FROM $t WHERE id IN (" . implode( ',', $chunk ) . ')' );
			}
			$from_id = $to_id;
		}
		update_option( 'playform_history_compact_from', $from_id > $max_id ? 0 : $from_id, false );
		return $deleted;
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
