<?php
namespace Playform\Feeds;

final class Install {

	public static function activate(): void {
		self::tables();
		self::categories();
		// Engangs-omsorteringer, når reglerne ændres (tælleren sikrer, at de kun kører én gang).
		if ( (int) get_option( 'playform_feeds_migration' ) < 1 ) {
			// Første version af Kitzhen: ingen omsorteringer endnu.
			update_option( 'playform_feeds_migration', 1, false );
		}
		Cron::schedule();
		update_option( 'playform_feeds_db_version', PLAYFORM_FEEDS_VERSION );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'playform_feeds_db_version' ) !== PLAYFORM_FEEDS_VERSION ) {
			self::activate();
		}
	}

	public static function tables(): void {
		global $wpdb;
		$offers  = Offers::table();
		$history = PriceHistory::table();
		$charset = $wpdb->get_charset_collate();

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS $offers (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				shop varchar(64) NOT NULL,
				shop_product_id varchar(191) NOT NULL,
				ean varchar(14) NOT NULL DEFAULT '',
				name text NOT NULL,
				brand varchar(191) NOT NULL DEFAULT '',
				shop_category varchar(191) NOT NULL DEFAULT '',
				category varchar(64) NOT NULL DEFAULT '',
				price decimal(12,2) NOT NULL DEFAULT 0,
				list_price decimal(12,2) NOT NULL DEFAULT 0,
				shipping decimal(10,2) NULL,
				in_stock tinyint(1) NOT NULL DEFAULT 0,
				stock_qty int(11) NULL,
				delivery varchar(191) NOT NULL DEFAULT '',
				restock_date date NULL,
				url text NOT NULL,
				image text NOT NULL,
				hash char(32) NOT NULL DEFAULT '',
				active tinyint(1) NOT NULL DEFAULT 1,
				first_seen datetime NOT NULL,
				last_seen datetime NOT NULL,
				sale_since date NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY shop_item (shop, shop_product_id),
				KEY ean (ean),
				KEY product_id (product_id)
			) $charset"
		);

		// Kolonner tilføjet efter første version.
		$cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $offers" );
		if ( $cols && ! in_array( 'link_checked', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE $offers ADD COLUMN link_checked datetime NULL" );
			$wpdb->query( "ALTER TABLE $offers ADD COLUMN link_dead_url text NULL" );
		}
		if ( $cols && ! in_array( 'sale_since', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE $offers ADD COLUMN sale_since date NULL" );
			// Tilbud, butikkerne allerede har kørende, regnes fra i dag (vi kender ikke deres startdato).
			$today = current_time( 'Y-m-d' );
			$wpdb->query( $wpdb->prepare( "UPDATE $offers SET sale_since = %s WHERE price > 0 AND list_price > price", $today ) );
			Sales::refresh_all( $today );
		}

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS $history (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				shop varchar(64) NOT NULL,
				price decimal(12,2) NOT NULL,
				recorded_on date NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY product_shop_day (product_id, shop, recorded_on),
				KEY recorded_on (recorded_on)
			) $charset"
		);
	}

	public static function categories(): void {
		$all = Config::categories() + [ Config::uncategorized_slug() => [ 'name' => 'Ukategoriseret (skjult)' ] ];
		foreach ( $all as $slug => $def ) {
			if ( ! term_exists( $slug, 'product_cat' ) ) {
				wp_insert_term( $def['name'], 'product_cat', [ 'slug' => $slug ] );
			}
		}
		Subcategories::install();
	}

	/**
	 * Kører sorteringsreglerne igen for én kategoris tilbud og opdaterer produkterne – bruges, når reglerne
	 * eller underkategorierne ændres, da importen kun opdaterer produkter, hvis pris/lager ændrer sig.
	 */
	public static function refresh_category( string $category ): void {
		global $wpdb;
		$t    = Offers::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, product_id, name, brand, shop_category, price, category FROM $t WHERE category = %s", $category ), ARRAY_A ) ?: [];
		$ids  = [];
		foreach ( $rows as $r ) {
			$cat = Classifier::classify( [ 'name' => $r['name'], 'brand' => $r['brand'], 'shop_category' => $r['shop_category'], 'price' => (float) $r['price'] ] );
			if ( $cat !== $r['category'] ) {
				$wpdb->update( $t, $cat === 'exclude' ? [ 'category' => 'exclude', 'active' => 0 ] : [ 'category' => $cat ], [ 'id' => $r['id'] ] );
			}
			if ( (int) $r['product_id'] ) {
				$ids[ (int) $r['product_id'] ] = true;
			}
		}
		$today = current_time( 'Y-m-d' );
		foreach ( array_keys( $ids ) as $id ) {
			ProductSync::refresh( $id, $today );
		}
	}

	public static function category_id( string $slug ): int {
		static $cache = [];
		if ( ! isset( $cache[ $slug ] ) ) {
			$term           = get_term_by( 'slug', $slug, 'product_cat' );
			$cache[ $slug ] = $term ? (int) $term->term_id : 0;
		}
		return $cache[ $slug ];
	}
}
