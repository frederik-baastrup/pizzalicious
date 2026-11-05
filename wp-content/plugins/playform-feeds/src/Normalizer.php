<?php
namespace Playform\Feeds;

/**
 * Gør de forskellige shops' feeds ens.
 */
final class Normalizer {

	/**
	 * @return array{shop:string,shop_name:string,shop_product_id:string,ean:string,name:string,brand:string,shop_category:string,description:string,price:float,list_price:float,shipping:?float,in_stock:bool,stock_qty:?int,delivery:string,restock_date:?string,url:string,image:string,color:string}
	 */
	public static function offer( array $row, string $shop_key, string $shop_name ): array {
		[ $in_stock, $qty ] = self::stock( $row['lagerantal'] ?? '' );
		[ $delivery, $restock ] = self::delivery( $row['leveringstid'] ?? '' );
		return [
			'shop'            => $shop_key,
			'shop_name'       => $shop_name,
			'shop_product_id' => (string) ( $row['produktid'] ?? '' ),
			'ean'             => self::ean( $row['ean'] ?? '' ),
			'name'            => self::clean_text( $row['produktnavn'] ?? '' ),
			'brand'           => self::clean_text( $row['brand'] ?? '' ),
			'shop_category'   => self::clean_text( $row['kategorinavn'] ?? '' ),
			'description'     => self::clean_text( $row['beskrivelse'] ?? '' ),
			'price'           => self::price( $row['nypris'] ?? '' ),
			'list_price'      => self::price( $row['glpris'] ?? '' ),
			'shipping'        => isset( $row['fragtomk'] ) && $row['fragtomk'] !== '' ? self::price( $row['fragtomk'] ) : null,
			'in_stock'        => $in_stock,
			'stock_qty'       => $qty,
			'delivery'        => $delivery,
			'restock_date'    => $restock,
			'url'             => trim( $row['vareurl'] ?? '' ),
			'image'           => self::image_url( $row['billedurl'] ?? '' ),
			'color'           => self::clean_text( $row['color'] ?? '' ),
		];
	}

	public static function price( string $v ): float {
		$v = str_replace( [ ' ', ',' ], [ '', '.' ], trim( $v ) );
		return is_numeric( $v ) ? round( (float) $v, 2 ) : 0.0;
	}

	public static function ean( string $v ): string {
		$v = preg_replace( '/\D/', '', $v );
		if ( ! preg_match( '/^(\d{8}|\d{12,14})$/', $v ) || trim( $v, '0' ) === '' ) {
			return '';
		}
		// UPC-12 og EAN-13 med foranstillede nuller skal matche hinanden.
		return strlen( $v ) === 8 ? $v : str_pad( ltrim( $v, '0' ), 13, '0', STR_PAD_LEFT );
	}

	/** @return array{0:bool,1:?int} */
	public static function stock( string $v ): array {
		$v = strtolower( trim( $v ) );
		if ( $v === '' ) {
			return [ false, null ];
		}
		if ( is_numeric( $v ) ) {
			$n = (int) $v;
			return [ $n > 0, $n ];
		}
		// Restordre/forudbestilling (fx KitchenOnes "backorder") er ikke på lager: butikken har ikke varen hjemme.
		$out = (bool) preg_match( '/out[ _-]?of[ _-]?stock|udsolgt|ikke på lager|^nej$|back[ _-]?order|pre[ _-]?order|restordre|bestillingsvare/', $v );
		return [ ! $out, null ];
	}

	/**
	 * Leveringstid som tekst. Træningspartner sender en dato, når varen er i restordre.
	 * @return array{0:string,1:?string}
	 */
	public static function delivery( string $v ): array {
		$v = trim( $v );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) ) {
			return [ '', "$m[1]-$m[2]-$m[3]" ];
		}
		if ( preg_match( '/^(\d+)\s*-\s*(\d+)\s*(d|dage|hverdage)?$/i', $v, $m ) ) {
			return [ "$m[1]–$m[2] hverdage", null ];
		}
		if ( stripos( $v, 'kontakt' ) !== false ) {
			return [ '', null ];
		}
		return [ $v, null ];
	}

	public static function image_url( string $v ): string {
		$v = trim( $v );
		if ( $v === '' ) {
			return '';
		}
		$parts = wp_parse_url( $v );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$path = implode( '/', array_map( 'rawurlencode', array_map( 'rawurldecode', explode( '/', $parts['path'] ?? '' ) ) ) );
		$url  = 'https://' . $parts['host'] . $path;
		return isset( $parts['query'] ) ? "$url?{$parts['query']}" : $url;
	}

	public static function clean_text( string $v ): string {
		$v = wp_strip_all_tags( $v );
		// Nogle shops sender UTF-8 inde i et Latin-1-feed (fx "â¯"). Det repareres her.
		$v = preg_replace_callback(
			'/[\x{00C2}-\x{00F4}][\x{0080}-\x{00BF}]{1,3}/u',
			static function ( $m ) {
				$bytes = mb_convert_encoding( $m[0], 'ISO-8859-1', 'UTF-8' );
				return mb_check_encoding( $bytes, 'UTF-8' ) ? $bytes : $m[0];
			},
			$v
		);
		$v = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $v );
		$v = preg_replace( '/[\x{2028}\x{2029}\x{00A0}\x{202F}]/u', ' ', $v );
		$v = preg_replace( '/\s+/u', ' ', $v );
		return trim( $v );
	}
}
