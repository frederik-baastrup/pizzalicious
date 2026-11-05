<?php
namespace Playform\Feeds;

/**
 * Hele importen: hent feeds → rens → sorter → gem tilbud → opdater produkter → gem prishistorik.
 */
final class Importer {

	private array $stats = [];
	private array $dirty = [];
	/** @var callable|null */
	private $log;

	public function __construct( ?callable $log = null ) {
		$this->log = $log;
	}

	/**
	 * @param string[]|null         $only   kun disse shops (nøgler)
	 * @param array<string,string>  $files  lokale filer pr. shop (til test); ellers hentes feedet
	 */
	public function run( ?array $only = null, array $files = [], int $limit = 0 ): array {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		@set_time_limit( 0 );
		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );

		$started = current_time( 'mysql', true );
		$today   = current_time( 'Y-m-d' );
		$shops   = Config::shops();

		foreach ( $shops as $key => $shop ) {
			if ( $only && ! in_array( $key, $only, true ) ) {
				continue;
			}
			if ( empty( $shop['url'] ) && ! isset( $files[ $key ] ) ) {
				continue; // butik uden feed-adresse endnu
			}
			$this->stats[ $key ] = [ 'name' => $shop['name'], 'read' => 0, 'excluded' => 0, 'new' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0, 'error' => '' ];
			try {
				$file = $files[ $key ] ?? FeedReader::download( $shop['url'], $key );
				$this->log( "Læser {$shop['name']} …" );
				$this->import_shop( $key, $shop['name'], $file, $started, $today, $limit );
				// Kun når feedet blev læst helt, må manglende varer markeres som væk.
				if ( ! $limit ) {
					$gone                           = Offers::deactivate_missing( $key, $started );
					$this->stats[ $key ]['removed'] = count( $gone );
					foreach ( $gone as $id ) {
						$this->dirty[ $id ] = true;
					}
				}
			} catch ( \Throwable $e ) {
				$this->stats[ $key ]['error'] = $e->getMessage();
				$this->log( "FEJL {$shop['name']}: " . $e->getMessage() );
			}
		}

		$this->log( 'Opdaterer ' . count( $this->dirty ) . ' produkter …' );
		$n = 0;
		foreach ( array_keys( $this->dirty ) as $product_id ) {
			ProductSync::refresh( (int) $product_id, $today );
			if ( ++$n % 500 === 0 ) {
				$this->log( "  $n …" );
				wp_cache_flush();
			}
		}

		// Én gang i døgnet genberegnes rabatter for alle, da de afhænger af datoen.
		if ( get_option( 'playform_feeds_drops_day' ) !== $today && ! $limit ) {
			$this->log( 'Genberegner rabatter …' );
			self::recalc_drops( $today );
			update_option( 'playform_feeds_drops_day', $today, false );
		}

		// Butikstilbud udløber med datoen, så de genberegnes efter hver import.
		if ( ! $limit ) {
			$this->log( 'Opdaterer butikstilbud …' );
			Sales::refresh_all( $today );
		}

		wp_suspend_cache_invalidation( false );
		wp_defer_term_counting( false );

		$summary = [ 'started' => $started, 'finished' => current_time( 'mysql', true ), 'shops' => $this->stats, 'products_refreshed' => $n ];
		update_option( 'playform_feeds_last_run', $summary, false );
		return $summary;
	}

	/**
	 * Importerer en enkelt bid af én butiks feed – til "Kør import nu"-knappen, som ellers
	 * ville ramme PHP's tidsgrænse. Kaldes gentagne gange med stigende $offset, til 'eof' er sand.
	 *
	 * @return array{stats:array,next_offset:int,eof:bool}
	 */
	public function run_chunk( string $shop_key, string $file, int $offset, int $chunk, string $started, string $today ): array {
		@set_time_limit( 0 );
		$shops = Config::shops();
		if ( ! isset( $shops[ $shop_key ] ) ) {
			throw new \RuntimeException( "Ukendt butik: $shop_key" );
		}
		$name  = $shops[ $shop_key ]['name'];
		$stats = [ 'read' => 0, 'excluded' => 0, 'new' => 0, 'updated' => 0, 'unchanged' => 0 ];
		$pos       = 0;
		$processed = 0;
		$eof       = true;
		foreach ( FeedReader::read( $file ) as $row ) {
			if ( $pos < $offset ) {
				++$pos;
				continue;
			}
			if ( $processed >= $chunk ) {
				$eof = false;
				break;
			}
			++$pos;
			++$processed;
			$stats['read']++;

			$offer = Normalizer::offer( $row, $shop_key, $name );
			if ( ! empty( $shops[ $shop_key ]['group_variants'] ) ) {
				$offer = self::group_variant( $row, $offer, $file );
				if ( ! $offer ) {
					$stats['excluded']++;
					continue;
				}
			}
			if ( $offer['shop_product_id'] === '' || $offer['url'] === '' ) {
				$stats['excluded']++;
				continue;
			}
			$category = Classifier::classify( $offer );
			if ( $category === 'exclude' ) {
				$stats['excluded']++;
				continue;
			}

			$existing   = Offers::find( $shop_key, $offer['shop_product_id'] );
			$product_id = $existing ? (int) $existing['product_id'] : 0;
			if ( $product_id && ! get_post( $product_id ) ) {
				$product_id = 0;
			}
			if ( ! $product_id ) {
				$product_id = Offers::product_for_ean( $offer['ean'], $shop_key );
			}
			if ( ! $product_id ) {
				$product_id = ProductSync::create( $offer, $category );
			}

			$result = Offers::save( $offer, $product_id, $category, $started );
			ProductSync::save_texts_and_attributes( $product_id, $offer, $category );
			PriceHistory::record( $product_id, $shop_key, $offer['in_stock'] ? $offer['price'] : 0, $today );

			if ( $result['is_new'] ) {
				$stats['new']++;
			} elseif ( $result['changed'] ) {
				$stats['updated']++;
			} else {
				$stats['unchanged']++;
			}
			if ( $result['changed'] ) {
				ProductSync::refresh( $product_id, $today );
			}
		}
		return [ 'stats' => $stats, 'next_offset' => $pos, 'eof' => $eof ];
	}

	/**
	 * Butikker med 'group_variants': hver størrelse er en egen række med samme navn. Vi beholder én række
	 * pr. navn – helst en på lager, ellers den billigste – og giver den et fast id ud fra navnet, så
	 * produktet bliver det samme, uanset hvilken størrelse der er på lager i dag.
	 *
	 * @return array<string,bool> butikkens produktid for de valgte rækker => true
	 */
	private static function variant_reps( string $file ): array {
		static $cache = [];
		if ( isset( $cache[ $file ] ) ) {
			return $cache[ $file ];
		}
		$best = [];
		foreach ( FeedReader::read( $file ) as $row ) {
			$key   = self::variant_key( $row );
			$stock = Normalizer::stock( (string) ( $row['lagerantal'] ?? '' ) )[0];
			$price = Normalizer::price( (string) ( $row['nypris'] ?? '' ) );
			$id    = (string) ( $row['produktid'] ?? '' );
			if ( $id === '' ) {
				continue;
			}
			$cur = $best[ $key ] ?? null;
			if ( ! $cur || ( $stock && ! $cur[1] ) || ( $stock === $cur[1] && $price > 0 && $price < $cur[2] ) ) {
				$best[ $key ] = [ $id, $stock, $price ];
			}
		}
		$cache[ $file ] = array_fill_keys( array_column( $best, 0 ), true );
		return $cache[ $file ];
	}

	private static function variant_key( array $row ): string {
		return mb_strtolower( trim( (string) ( $row['brand'] ?? '' ) ) . '|' . trim( (string) ( $row['produktnavn'] ?? '' ) ) );
	}

	/** Anvender 'group_variants': false = rækken springes over; ellers tilbuddet med fast id. */
	private static function group_variant( array $row, array $offer, string $file ) {
		if ( ! isset( self::variant_reps( $file )[ (string) ( $row['produktid'] ?? '' ) ] ) ) {
			return false;
		}
		$offer['shop_product_id'] = 'v-' . substr( md5( self::variant_key( $row ) ), 0, 20 );
		return $offer;
	}

	/** Markerer tilbud, der ikke dukkede op i feedet, som væk – kaldes når en butiks chunks er færdige. */
	public static function finish_shop( string $shop_key, string $started ): int {
		$ids = Offers::deactivate_missing( $shop_key, $started );
		foreach ( $ids as $id ) {
			ProductSync::refresh( (int) $id, current_time( 'Y-m-d' ) );
		}
		return count( $ids );
	}

	private function import_shop( string $key, string $name, string $file, string $now, string $today, int $limit ): void {
		$s = &$this->stats[ $key ];
		foreach ( FeedReader::read( $file ) as $row ) {
			if ( $limit && $s['read'] >= $limit ) {
				break;
			}
			$s['read']++;
			$offer = Normalizer::offer( $row, $key, $name );
			if ( ! empty( Config::shops()[ $key ]['group_variants'] ) ) {
				$offer = self::group_variant( $row, $offer, $file );
				if ( ! $offer ) {
					$s['excluded']++;
					continue;
				}
			}
			if ( $offer['shop_product_id'] === '' || $offer['url'] === '' ) {
				$s['excluded']++;
				continue;
			}
			$category = Classifier::classify( $offer );
			if ( $category === 'exclude' ) {
				$s['excluded']++;
				continue;
			}

			$existing   = Offers::find( $key, $offer['shop_product_id'] );
			$product_id = $existing ? (int) $existing['product_id'] : 0;
			if ( $product_id && ! get_post( $product_id ) ) {
				$product_id = 0; // produktet er slettet i WordPress
			}
			if ( ! $product_id ) {
				$product_id = Offers::product_for_ean( $offer['ean'], $key );
			}
			if ( ! $product_id ) {
				$product_id = ProductSync::create( $offer, $category );
			}

			$result = Offers::save( $offer, $product_id, $category, $now );
			ProductSync::save_texts_and_attributes( $product_id, $offer, $category );
			PriceHistory::record( $product_id, $key, $offer['in_stock'] ? $offer['price'] : 0, $today );

			if ( $result['is_new'] ) {
				$s['new']++;
			} elseif ( $result['changed'] ) {
				$s['updated']++;
			} else {
				$s['unchanged']++;
			}
			if ( $result['changed'] ) {
				$this->dirty[ $product_id ] = true;
			}
		}
	}

	/** Opdaterer rabat-felterne for alle produkter ud fra prishistorikken. */
	public static function recalc_drops( string $today ): int {
		global $wpdb;
		$t    = Offers::table();
		$rows = $wpdb->get_results( "SELECT product_id, MIN(price) AS price FROM $t WHERE active = 1 AND in_stock = 1 AND product_id > 0 GROUP BY product_id", ARRAY_A ) ?: [];
		$n    = 0;
		foreach ( $rows as $r ) {
			$id   = (int) $r['product_id'];
			$drop = PriceHistory::drop( $id, (float) $r['price'], $today );
			if ( $drop ) {
				update_post_meta( $id, ProductSync::META_DROP, $drop['percent'] );
				update_post_meta( $id, ProductSync::META_REF_PRICE, $drop['reference'] );
				$n++;
			} else {
				delete_post_meta( $id, ProductSync::META_DROP );
				delete_post_meta( $id, ProductSync::META_REF_PRICE );
			}
		}
		return $n;
	}

	private function log( string $msg ): void {
		if ( $this->log ) {
			( $this->log )( $msg );
		}
	}
}
