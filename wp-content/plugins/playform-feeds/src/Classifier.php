<?php
namespace Playform\Feeds;

/**
 * Placerer et produkt i sitets kategoritræ (Config::categories()) ud fra navn og butikkens kategori.
 * Returnerer en kategori-slug, 'exclude' (skal ikke med) eller Config::uncategorized_slug().
 */
final class Classifier {

	public static function classify( array $offer ): string {
		$name = mb_strtolower( $offer['name'] );
		// Mærkenavnet må ikke styre kategorien (fx "American Barbell Squat Stand").
		$brand = mb_strtolower( trim( $offer['brand'] ) );
		$match_name = $brand !== '' && mb_strlen( $brand ) > 2 ? trim( str_replace( $brand, ' ', $name ) ) : $name;
		$cat  = mb_strtolower( $offer['shop_category'] );

		if ( $offer['price'] <= 0 ) {
			return 'exclude';
		}
		// Butikker med fast kategori-oversættelse (fx cykelbutikker): kun de nævnte butikskategorier kommer med.
		$shop = Config::shops()[ $offer['shop'] ] ?? [];
		$map  = $shop['category_map'] ?? null;
		if ( is_array( $map ) ) {
			$mapped = $map[ $cat ] ?? 'exclude';
			// 'refine': butikken sælger kun nichens varer – vores egne regler må placere varen mere præcist
			// (fx en pizzasaks i butikkens "Andet pizzaudstyr"), ellers bruges butikkens kategori.
			if ( 'exclude' !== $mapped && ! empty( $shop['refine'] ) ) {
				return self::first_match( $match_name ) ?: $mapped;
			}
			return $mapped;
		}
		foreach ( Config::exclude() as $pattern ) {
			if ( self::m( $pattern, $name ) || self::m( $pattern, $cat ) ) {
				return 'exclude';
			}
		}

		$found = self::first_match( $match_name );
		if ( ! $found && ! in_array( $cat, Config::meaningless_shop_categories(), true ) && $cat !== mb_strtolower( $offer['brand'] ) ) {
			$found = self::first_match( $cat );
		}
		// Tilbehør til maskiner (reservedele, filtre …) placeres for sig (Config::machine_categories()).
		if ( $found && in_array( $found, Config::machine_categories(), true ) && self::m( Config::accessory_pattern(), $name ) ) {
			$found = Config::accessory_target();
		}
		// Mærker, der kun laver udstyr til nichen (fx Ooni til pizza): tilbehør uden kategoriord i navnet.
		if ( ! $found && $brand !== '' && method_exists( Config::class, 'brand_categories' ) ) {
			$found = Config::brand_categories()[ $brand ] ?? null;
		}
		return $found ?: Config::uncategorized_slug();
	}

	private static function first_match( string $text ): ?string {
		if ( $text === '' ) {
			return null;
		}
		foreach ( Config::categories() as $slug => $def ) {
			if ( self::m( $def['match'], $text ) && ( empty( $def['not'] ) || ! self::m( $def['not'], $text ) ) ) {
				return $slug;
			}
		}
		return null;
	}

	private static function m( string $pattern, string $text ): bool {
		return (bool) preg_match( '~' . $pattern . '~iu', $text );
	}
}
