<?php
namespace Playform\Feeds;

/**
 * Læser et Partner-ads-feed som en strøm, så store filer ikke fylder hukommelsen.
 * XMLReader respekterer filens encoding-erklæring (iso-8859-1), så æøå kommer korrekt ud som UTF-8.
 */
final class FeedReader {

	/** Henter feedet til en lokal fil og returnerer stien. */
	public static function download( string $url, string $shop_key ): string {
		$dir = self::cache_dir();
		$tmp = download_url( $url, 300 );
		if ( is_wp_error( $tmp ) ) {
			throw new \RuntimeException( "Kunne ikke hente feed for $shop_key: " . $tmp->get_error_message() );
		}
		$dest = "$dir/$shop_key.xml";
		if ( ! @rename( $tmp, $dest ) ) {
			copy( $tmp, $dest );
			@unlink( $tmp );
		}
		return $dest;
	}

	public static function cache_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'playform-feeds';
		wp_mkdir_p( $dir );
		return $dir;
	}

	/**
	 * @return \Generator<array<string,string>> ét array pr. <produkt>
	 */
	public static function read( string $file ): \Generator {
		$reader = new \XMLReader();
		if ( ! $reader->open( $file, null, LIBXML_NONET | LIBXML_COMPACT ) ) {
			throw new \RuntimeException( "Kan ikke åbne $file" );
		}
		while ( $reader->read() ) {
			if ( $reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'produkt' ) {
				continue;
			}
			$node = simplexml_load_string( $reader->readOuterXml(), 'SimpleXMLElement', LIBXML_NOCDATA );
			if ( ! $node ) {
				continue;
			}
			$row = [];
			foreach ( $node->children() as $child ) {
				$row[ $child->getName() ] = trim( html_entity_decode( (string) $child, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			}
			yield $row;
		}
		$reader->close();
	}
}
