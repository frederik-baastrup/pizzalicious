<?php
namespace Playform\Feeds;

/**
 * Udtrækker filter-egenskaber fra navn og beskrivelse.
 * Kun det, der står i teksten – intet gættes. Ukendt = null.
 */
final class Attributes {

	public static function extract( array $offer, string $category ): array {
		// Kun de egenskaber, nichen bruger (Config::attributes()). Kitzhen: ingen endnu.
		$enabled = Config::attributes();
		if ( ! $enabled ) {
			return [];
		}
		$text = mb_strtolower( $offer['name'] . ' ' . $offer['description'] );
		$attr = [
			'foldable'   => null,
			'incline'    => null,
			'max_speed'  => null,
			'max_weight' => null,
			'weight_kg'  => null,
		];

		if ( preg_match( '/foldbar|sammenfoldelig|kan foldes|sammenklappelig|foldes sammen|foldable/u', $text ) ) {
			$attr['foldable'] = true;
		}
		if ( preg_match( '/(?<!\p{L})(incline|stigning|hældning)/u', $text ) ) {
			$attr['incline'] = ! preg_match( '/uden (incline|stigning|hældning)/u', $text );
		}
		if ( preg_match( '/(\d{1,2}(?:[.,]\d)?)\s*km\s*\/?\s*t/u', $text, $m ) ) {
			$speed = (float) str_replace( ',', '.', $m[1] );
			if ( $speed >= 3 && $speed <= 30 ) {
				$attr['max_speed'] = $speed;
			}
		}
		if ( preg_match( '/(?:max(?:imal)?\.?\s*(?:bruger)?vægt|brugervægt|belastning)[^\d]{0,20}(\d{2,3})\s*kg/u', $text, $m ) ) {
			$attr['max_weight'] = (int) $m[1];
		}
		if ( $category === 'vaegte-kettlebells' && preg_match( '/(\d{1,3}(?:[.,]\d)?)\s*kg/u', mb_strtolower( $offer['name'] ), $m ) ) {
			$attr['weight_kg'] = (float) str_replace( ',', '.', $m[1] );
		}
		return array_intersect_key( array_filter( $attr, static fn( $v ) => $v !== null ), array_flip( $enabled ) );
	}
}
