<?php
namespace Playform\Feeds;

/**
 * Opretter og opdaterer WooCommerce-produkter (type "eksternt produkt") ud fra deres tilbud.
 * Felter, som redaktøren selv har rettet, overskrives ikke (se Locks).
 */
final class ProductSync {

	public const META_EAN        = '_pf_ean';
	public const META_BRAND      = '_pf_brand';
	public const META_IMAGE      = '_pf_image';
	public const META_FEED_DESC  = '_pf_feed_description';
	public const META_ATTR       = '_pf_attributes';
	public const META_SHOP       = '_pf_best_shop';
	public const META_SHOPS      = '_pf_shop_count';
	public const META_DELIVERY   = '_pf_delivery';
	public const META_RESTOCK    = '_pf_restock_date';
	public const META_DROP       = '_pf_drop_percent';
	public const META_REF_PRICE  = '_pf_reference_price';
	public const META_LOCKS      = '_pf_locks';
	public const META_CATEGORY   = '_pf_category';
	/** Eksterne produkter kan ikke have lagerstatus i WooCommerce, så vi gemmer den selv. */
	public const META_IN_STOCK   = '_pf_in_stock';

	public static function create( array $offer, string $category ): int {
		$product = new \WC_Product_External();
		$product->set_name( $offer['name'] );
		$product->set_status( self::status_for( $category ) );
		$product->set_regular_price( (string) $offer['price'] );
		$product->set_product_url( $offer['url'] );
		$product->set_button_text( 'Gå til butik' );
		$product->set_category_ids( array_filter( [ Install::category_id( $category ) ] ) );
		$product->set_short_description( '' );
		$id = $product->save();

		update_post_meta( $id, self::META_EAN, $offer['ean'] );
		update_post_meta( $id, self::META_BRAND, $offer['brand'] );
		update_post_meta( $id, self::META_CATEGORY, $category );
		return $id;
	}

	/**
	 * Genberegner produktet ud fra dets aktive tilbud: pris, link, lager, billede, antal butikker og rabat.
	 */
	public static function refresh( int $product_id, string $today ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product_External ) {
			return;
		}
		$offers = Offers::for_product( $product_id );
		$locks  = (array) get_post_meta( $product_id, self::META_LOCKS, true );

		if ( ! $offers ) {
			// Ingen butik sælger det længere: siden bliver liggende som "ikke på lager".
			update_post_meta( $product_id, self::META_IN_STOCK, 0 );
			update_post_meta( $product_id, self::META_SHOPS, 0 );
			// Uden tilbud og uden billede er der intet at vise: skjul produktet (siden bliver ikke slettet).
			if ( '' === (string) get_post_meta( $product_id, self::META_IMAGE, true ) && 'publish' === $product->get_status() ) {
				$product->set_status( 'draft' );
				$product->save();
			}
			delete_post_meta( $product_id, self::META_DROP );
			return;
		}

		$best  = $offers[0];
		$price = (float) $best['price'];
		$product->set_regular_price( (string) $price );
		$product->set_sale_price( '' );
		$product->set_product_url( $best['url'] );

		$category = $best['category'];
		if ( empty( $locks['category'] ) && $category ) {
			$subs = Subcategories::match( $category, $best['name'], (array) get_post_meta( $product_id, self::META_ATTR, true ), (string) get_post_meta( $product_id, self::META_FEED_DESC, true ) );
			$product->set_category_ids( Subcategories::term_ids( $category, $subs ) );
			update_post_meta( $product_id, self::META_CATEGORY, $category );
			if ( $product->get_status() !== 'publish' || $category === Config::uncategorized_slug() ) {
				$product->set_status( self::status_for( $category ) );
			}
		}
		if ( empty( $locks['name'] ) ) {
			$product->set_name( $best['name'] );
		}
		$image = '';
		foreach ( $offers as $o ) {
			if ( $o['image'] ) {
				$image = $o['image'];
				break;
			}
		}
		// Uden billede er det typisk en udgået vare eller en reservedel – den vises ikke, før der kommer et billede.
		if ( '' === $image ) {
			$product->set_status( 'draft' );
		} elseif ( 'draft' === $product->get_status() && ( $product->get_meta( self::META_CATEGORY ) ?: $category ) !== Config::uncategorized_slug() ) {
			$product->set_status( 'publish' );
		}
		$product->save();

		update_post_meta( $product_id, self::META_IMAGE, $image );
		update_post_meta( $product_id, self::META_IN_STOCK, (int) $best['in_stock'] );
		self::write_flat_attributes( $product_id, (array) get_post_meta( $product_id, self::META_ATTR, true ) );
		update_post_meta( $product_id, self::META_SHOP, $best['shop'] );
		$shop_keys = array_values( array_unique( array_column( $offers, 'shop' ) ) );
		update_post_meta( $product_id, self::META_SHOPS, count( $shop_keys ) );
		update_post_meta( $product_id, '_pf_shop_list', '|' . implode( '|', $shop_keys ) . '|' );
		update_post_meta( $product_id, self::META_DELIVERY, $best['delivery'] );
		update_post_meta( $product_id, self::META_RESTOCK, $best['restock_date'] ?: '' );
		if ( $best['brand'] ) {
			update_post_meta( $product_id, self::META_BRAND, $best['brand'] );
		}

		$drop = (int) $best['in_stock'] ? PriceHistory::drop( $product_id, $price, $today ) : null;
		if ( $drop ) {
			update_post_meta( $product_id, self::META_DROP, $drop['percent'] );
			update_post_meta( $product_id, self::META_REF_PRICE, $drop['reference'] );
		} else {
			delete_post_meta( $product_id, self::META_DROP );
			delete_post_meta( $product_id, self::META_REF_PRICE );
		}
	}

	/**
	 * Retter produkter, hvor lagerstatus/pris/butik (metafelterne) ikke længere passer til tilbuddene –
	 * fx "Hos Pro Outdoor" på lager, men butikkens tilbud er udsolgt eller dets side er væk. Køres efter hver import.
	 * @return int antal rettede produkter
	 */
	public static function heal_stock(): int {
		global $wpdb;
		$t    = Offers::table();
		$live = "o.product_id = m.post_id AND o.active = 1 AND o.in_stock = 1 AND ( o.link_dead_url IS NULL OR o.link_dead_url <> o.url )";
		$ids  = array_map( 'intval', (array) $wpdb->get_col(
			"SELECT m.post_id FROM {$wpdb->postmeta} m WHERE m.meta_key = '" . self::META_IN_STOCK . "' AND (
				( m.meta_value = '1' AND NOT EXISTS ( SELECT 1 FROM $t o WHERE $live ) )
				OR ( m.meta_value <> '1' AND EXISTS ( SELECT 1 FROM $t o WHERE $live ) ) )"
		) );
		$today = current_time( 'Y-m-d' );
		foreach ( $ids as $id ) {
			self::refresh( $id, $today );
		}
		return count( $ids );
	}

	public static function save_texts_and_attributes( int $product_id, array $offer, string $category ): void {
		$current = get_post_meta( $product_id, self::META_FEED_DESC, true );
		if ( strlen( $offer['description'] ) > strlen( (string) $current ) ) {
			update_post_meta( $product_id, self::META_FEED_DESC, $offer['description'] );
		}
		$attr = Attributes::extract( $offer, $category );
		$old  = (array) get_post_meta( $product_id, self::META_ATTR, true );
		$all  = array_merge( $attr, $old );
		update_post_meta( $product_id, self::META_ATTR, $all );
		self::write_flat_attributes( $product_id, $all );
	}

	/** Hver egenskab også som eget felt, så kategorisiden kan filtrere på dem. */
	private static function write_flat_attributes( int $product_id, array $all ): void {
		foreach ( [ 'foldable', 'incline', 'max_speed', 'max_weight', 'weight_kg' ] as $key ) {
			if ( array_key_exists( $key, $all ) ) {
				update_post_meta( $product_id, '_pf_' . $key, is_bool( $all[ $key ] ) ? (int) $all[ $key ] : $all[ $key ] );
			}
		}
	}

	private static function status_for( string $category ): string {
		// Ukategoriserede produkter holdes skjult, indtil de er sorteret.
		return $category === Config::uncategorized_slug() ? 'draft' : 'publish';
	}
}
