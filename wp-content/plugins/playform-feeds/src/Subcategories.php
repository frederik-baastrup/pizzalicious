<?php
namespace Playform\Feeds;

/**
 * Placerer produkter i underkategorier (landingssider) ud fra navn og egenskaber.
 */
final class Subcategories {

	/** @return string[] slugs på de underkategorier (og deres underkategorier), produktet hører til */
	public static function match( string $category, string $name, array $attrs, string $desc = '' ): array {
		$defs = Config::subcategories()[ $category ] ?? [];
		$name = mb_strtolower( $name );
		$desc = mb_strtolower( $desc );
		$out  = [];
		foreach ( $defs as $slug => $def ) {
			if ( ! empty( $def['not'] ) && preg_match( '~' . $def['not'] . '~iu', $name ) ) {
				continue;
			}
			$hit = ( ! empty( $def['match'] ) && preg_match( '~' . $def['match'] . '~iu', $name ) )
				|| ( ! empty( $def['desc'] ) && $desc !== '' && preg_match( '~' . $def['desc'] . '~iu', $desc ) );
			foreach ( $def['attr'] ?? [] as $key => $want ) {
				if ( ! array_key_exists( $key, $attrs ) ) {
					continue;
				}
				$v = $attrs[ $key ];
				if ( is_array( $want ) ) {
					[ $min, $max ] = $want;
					$hit = $hit || ( (float) $v >= (float) $min && ( $max === null || (float) $v < (float) $max ) );
				} else {
					$hit = $hit || ( (bool) $v === (bool) $want );
				}
			}
			if ( $hit ) {
				$out[] = $slug;
				if ( isset( Config::subcategories()[ $slug ] ) ) {
					array_push( $out, ...self::match( $slug, $name, $attrs, $desc ) );
				}
			}
		}
		return $out;
	}

	/** Term-id'er for kategori + underkategorier. */
	public static function term_ids( string $category, array $subs ): array {
		$ids = [ Install::category_id( $category ) ];
		foreach ( $subs as $slug ) {
			$ids[] = Install::category_id( $slug );
		}
		return array_values( array_filter( $ids ) );
	}

	public static function install(): void {
		foreach ( Config::subcategories() as $parent => $children ) {
			$pid = Install::category_id( $parent );
			if ( ! $pid ) {
				continue;
			}
			foreach ( $children as $slug => $def ) {
				if ( ! term_exists( $slug, 'product_cat' ) ) {
					wp_insert_term( $def['name'], 'product_cat', [ 'slug' => $slug, 'parent' => $pid ] );
				}
			}
		}
	}
}
