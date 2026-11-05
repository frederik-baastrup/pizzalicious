<?php
namespace Playform\Feeds;

/**
 * Butikkernes egne tilbud.
 *
 * Et produkt er "på tilbud", når den billigste butik på lager selv har markeret det med en førpris,
 * og tilbuddet højst er DAYS dage gammelt. Forbrugerombudsmanden regner en tilbudspris, der har stået
 * mere end to uger, som en ny normalpris – så faste "førpriser" falder ud af sig selv.
 *
 * Førpris: Har vores egen prishistorik fulgt butikkens pris i alle 30 dage før tilbuddet begyndte, viser vi
 * den laveste pris i de 30 dage som kontrolleret førpris (førprisreglen) – gemt i _pf_sale_ref.
 * Ellers viser vi butikkens egen førpris (glpris), tydeligt mærket "butikkens førpris" – gemt i _pf_sale_list
 * (ejerens valg, indtil vores historik er lang nok). _pf_sale_percent er butikkens egen rabat og bruges til sortering.
 */
final class Sales {

	public const DAYS         = 14;
	public const META_SALE    = '_pf_shop_sale';
	public const META_PERCENT = '_pf_sale_percent';
	public const META_REF     = '_pf_sale_ref';
	public const META_LIST    = '_pf_sale_list';
	public const REF_DAYS     = 30;

	/** Genberegner tilbuds-felterne for alle produkter. Kaldes efter hver import (tilbud udløber med datoen). */
	public static function refresh_all( string $today ): int {
		global $wpdb;
		$t   = Offers::table();
		$cut = gmdate( 'Y-m-d', strtotime( $today . ' -' . ( self::DAYS - 1 ) . ' days' ) );

		// Den billigste aktive butik på lager pr. produkt – samme som den, produktkortet viser.
		$rows = $wpdb->get_results(
			"SELECT product_id, shop, price, list_price, sale_since FROM $t WHERE active = 1 AND in_stock = 1 AND product_id > 0 ORDER BY product_id, price + COALESCE(shipping, 0) ASC, COALESCE(shipping, 0) ASC, id ASC",
			ARRAY_A
		) ?: [];
		$sale = [];
		$seen = [];
		foreach ( $rows as $r ) {
			$id = (int) $r['product_id'];
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$price       = (float) $r['price'];
			$list        = (float) $r['list_price'];
			if ( $r['sale_since'] && $r['sale_since'] >= $cut && $list > $price && $price > 0 ) {
				$sale[ $id ] = [
					'percent' => (int) round( ( $list - $price ) / $list * 100 ),
					'list'    => $list,
					'ref'     => self::reference( $id, (string) $r['shop'], $price, (string) $r['sale_since'] ),
				];
			}
		}

		$old = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_SALE ) ) );
		foreach ( array_diff( $old, array_keys( $sale ) ) as $id ) {
			delete_post_meta( $id, self::META_SALE );
			delete_post_meta( $id, self::META_PERCENT );
			delete_post_meta( $id, self::META_REF );
			delete_post_meta( $id, self::META_LIST );
		}
		foreach ( $sale as $id => $s ) {
			update_post_meta( $id, self::META_SALE, 1 );
			update_post_meta( $id, self::META_PERCENT, $s['percent'] );
			update_post_meta( $id, self::META_LIST, $s['list'] );
			if ( $s['ref'] ) {
				update_post_meta( $id, self::META_REF, $s['ref'] );
			} else {
				delete_post_meta( $id, self::META_REF );
			}
		}
		return count( $sale );
	}

	/**
	 * Kontrolleret førpris: den laveste pris hos butikken i de 30 dage før tilbuddet begyndte.
	 * Kræver, at vores historik for butikken går mindst 30 dage tilbage fra tilbudsstarten. Ellers null.
	 */
	public static function reference( int $product_id, string $shop, float $price, string $since ): ?float {
		global $wpdb;
		$h    = PriceHistory::table();
		$from = gmdate( 'Y-m-d', strtotime( $since . ' -' . self::REF_DAYS . ' days' ) );
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MIN(recorded_on) AS first_day, MIN(CASE WHEN recorded_on >= %s AND recorded_on < %s THEN price END) AS ref FROM $h WHERE product_id = %d AND shop = %s",
				$from,
				$since,
				$product_id,
				$shop
			),
			ARRAY_A
		);
		if ( ! $row || ! $row['first_day'] || $row['first_day'] > $from || $row['ref'] === null ) {
			return null;
		}
		$ref = (float) $row['ref'];
		return $ref > $price ? $ref : null;
	}
}
