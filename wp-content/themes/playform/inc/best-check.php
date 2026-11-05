<?php
/**
 * Ugentligt tjek af købsguides ("Vores bud på de 3 bedste").
 *
 * Kører efter en import (højst én gang pr. uge) og kan startes manuelt under Indlæg → Tjek af købsguides.
 * - Findes produktet stadig, er det på lager, og har en butik det?
 * - Virker links til anmeldelser?
 * Resultatet gemmes på indlægget (_pf_checked, _pf_issues), som en samlet rapport (option + JSON-fil med
 * forslag til erstatninger, som Claude bruger til at skrive artiklerne om) og sendes på mail, hvis der er problemer.
 */
defined( 'ABSPATH' ) || exit;

const PLAYFORM_CHECK_DAYS = 7;

add_action(
	'playform_feeds_after_import',
	static function () {
		$last = (string) get_option( 'playform_best_checked', '' );
		if ( $last === '' || strtotime( $last ) < time() - PLAYFORM_CHECK_DAYS * DAY_IN_SECONDS + HOUR_IN_SECONDS ) {
			playform_best_check( true );
		}
	}
);

/** Alternativer i samme kategori, tættest på prisen – bruges både på siden og i rapporten. */
function playform_pick_alternatives( string $cat, float $price, array $exclude = [], int $n = 5 ): array {
	if ( $cat === '' ) {
		return [];
	}
	$ids = get_posts(
		[
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 60,
			'post__not_in'   => $exclude, // phpcs:ignore
			'tax_query'      => [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat ] ], // phpcs:ignore
			'meta_query'     => [ // phpcs:ignore
				[ 'key' => '_pf_in_stock', 'value' => 1, 'type' => 'NUMERIC' ],
				[ 'key' => '_pf_image', 'value' => '', 'compare' => '!=' ],
			],
		]
	);
	$rows = [];
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( $p ) {
			$rows[] = [ abs( (float) $p->get_price() - $price ), $p ];
		}
	}
	usort( $rows, static fn( $a, $b ) => $a[0] <=> $b[0] );
	return array_map( static fn( $r ) => $r[1], array_slice( $rows, 0, $n ) );
}

/** Den kategori, et valg hører til: produktets egen, ellers artiklens "flere"-kategori. */
function playform_pick_category( ?WC_Product $p, int $post_id ): string {
	if ( $p ) {
		$c = (string) get_post_meta( $p->get_id(), '_pf_category', true );
		$t = $c ? get_term_by( 'slug', $c, 'product_cat' ) : null;
		if ( $t ) {
			// Brug den dybeste kategori, produktet ligger i (fx romaskine-vand frem for romaskine).
			$deep = wp_get_post_terms( $p->get_id(), 'product_cat', [ 'fields' => 'all' ] );
			if ( ! is_wp_error( $deep ) && $deep ) {
				usort( $deep, static fn( $a, $b ) => count( get_ancestors( $b->term_id, 'product_cat' ) ) <=> count( get_ancestors( $a->term_id, 'product_cat' ) ) );
				return $deep[0]->slug;
			}
			return $t->slug;
		}
	}
	return (string) get_post_meta( $post_id, '_pf_more_cat', true );
}

function playform_best_check( bool $notify = false ): array {
	$report = [ 'checked' => current_time( 'mysql' ), 'site' => home_url( '/' ), 'articles' => [] ];
	foreach ( playform_root_posts() as $slug => $post_id ) {
		$picks = playform_picks( (int) $post_id );
		if ( ! $picks || get_post_status( (int) $post_id ) !== 'publish' ) {
			continue;
		}
		$issues = [];
		$used   = array_filter( array_map( static fn( $pk ) => ( $x = playform_pick_product( $pk ) ) ? $x->get_id() : 0, $picks ) );
		foreach ( $picks as $i => $pk ) {
			$p       = playform_pick_product( $pk );
			$problem = '';
			if ( ! $p ) {
				$problem = 'findes ikke længere hos butikkerne';
			} elseif ( ! get_post_meta( $p->get_id(), '_pf_in_stock', true ) ) {
				$problem = 'er ikke på lager';
			} elseif ( ! playform_pick_offer( $p->get_id() ) ) {
				$problem = 'har ingen butik med lager';
			}
			if ( $problem ) {
				$alts     = playform_pick_alternatives( playform_pick_category( $p, (int) $post_id ), $p ? (float) $p->get_price() : (float) ( $pk['price'] ?? 0 ), $used );
				$issues[] = [
					'type'         => 'product',
					'nr'           => $i + 1,
					'role'         => $pk['role'] ?? '',
					'name'         => $pk['name'] ?? '',
					'message'      => ( $pk['name'] ?? 'Produkt' ) . ' ' . $problem,
					'alternatives' => array_map(
						static fn( WC_Product $a ) => [
							'ean'         => (string) get_post_meta( $a->get_id(), '_pf_ean', true ),
							'slug'        => get_post_field( 'post_name', $a->get_id() ),
							'name'        => $a->get_name(),
							'brand'       => (string) get_post_meta( $a->get_id(), '_pf_brand', true ),
							'price'       => (float) $a->get_price(),
							'shop'        => (string) get_post_meta( $a->get_id(), '_pf_best_shop', true ),
							'attributes'  => get_post_meta( $a->get_id(), '_pf_attributes', true ),
							'description' => mb_substr( wp_strip_all_tags( (string) get_post_meta( $a->get_id(), '_pf_feed_description', true ) ), 0, 900 ),
						],
						$alts
					),
				];
			}
			foreach ( (array) ( $pk['reviews'] ?? [] ) as [ $label, $url ] ) {
				$res  = wp_remote_head( $url, [ 'timeout' => 12, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (compatible; ' . playform_site( 'name' ) . 'LinkCheck/1.0)' ] );
				$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
				if ( $code === 405 || $code === 403 ) { // nogle sider afviser HEAD – prøv GET
					$res  = wp_remote_get( $url, [ 'timeout' => 15, 'redirection' => 5, 'limit_response_size' => 20000 ] );
					$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
				}
				// Kun 404/410 betyder, at siden er væk. Mange anmeldelsessider (fx Garage Gym Reviews) afviser
				// automatiske kald med 403/429/5xx – det er ikke et dødt link og må ikke fjernes på det grundlag.
				if ( 404 === $code || 410 === $code ) {
					$issues[] = [ 'type' => 'review', 'nr' => $i + 1, 'message' => "Anmeldelseslink ($label) findes ikke længere ($code): $url" ];
				} elseif ( $code < 200 || $code >= 400 ) {
					$issues[] = [ 'type' => 'review_unsure', 'nr' => $i + 1, 'message' => "Anmeldelseslink ($label) kunne ikke tjekkes automatisk (svarer " . ( $code ?: 'ikke' ) . ') – sandsynligvis blokering af robotter. Tjek manuelt, fjern ikke uden bevis: ' . $url ];
				}
			}
		}
		update_post_meta( (int) $post_id, '_pf_checked', current_time( 'Y-m-d' ) );
		update_post_meta( (int) $post_id, '_pf_issues', $issues );
		if ( $issues ) {
			$report['articles'][] = [ 'slug' => $slug, 'title' => get_the_title( (int) $post_id ), 'url' => get_permalink( (int) $post_id ), 'issues' => $issues ];
		}
	}
	update_option( 'playform_best_checked', $report['checked'], false );
	update_option( 'playform_best_report', $report, false );

	// Til Claudes ugentlige gennemgang: samme rapport som JSON (ingen persondata).
	$up = wp_upload_dir();
	wp_mkdir_p( $up['basedir'] . '/playform' );
	file_put_contents( $up['basedir'] . '/playform/best-check.json', wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); // phpcs:ignore

	if ( $notify && $report['articles'] ) {
		$lines = [];
		foreach ( $report['articles'] as $a ) {
			$lines[] = $a['title'] . "\n" . $a['url'];
			foreach ( $a['issues'] as $is ) {
				$lines[] = '  – ' . $is['message'];
			}
			$lines[] = '';
		}
		wp_mail(
			get_option( 'admin_email' ),
			playform_site( 'name' ) . ': ' . count( $report['articles'] ) . ' købsguide(s) skal opdateres',
			"Det ugentlige tjek fandt problemer. Indtil artiklerne er opdateret, viser siden automatisk et alternativ ved udsolgte produkter.\n\n" . implode( "\n", $lines ) . "\nSe også Indlæg → Tjek af købsguides i WordPress."
		);
	}
	return $report;
}

// Admin: Indlæg → Tjek af købsguides
add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'edit.php', 'Tjek af købsguides', 'Tjek af købsguides', 'edit_posts', 'playform-best-check', 'playform_best_check_page' );
	}
);

function playform_best_check_page(): void {
	if ( isset( $_POST['pf_run_check'] ) && check_admin_referer( 'pf_best_check' ) ) {
		playform_best_check( false );
		echo '<div class="notice notice-success"><p>Tjekket er kørt.</p></div>';
	}
	$r = (array) get_option( 'playform_best_report', [] );
	echo '<div class="wrap"><h1>Tjek af købsguides</h1>';
	echo '<p>Tjekket kører automatisk efter en import, højst én gang om ugen. Sidst kørt: <strong>' . esc_html( $r['checked'] ?? 'aldrig' ) . '</strong>.</p>';
	echo '<form method="post">' . wp_nonce_field( 'pf_best_check', '_wpnonce', true, false ) . '<p><button class="button button-primary" name="pf_run_check" value="1">Kør tjek nu</button></p></form>';
	if ( empty( $r['articles'] ) ) {
		echo '<p>✅ Ingen problemer fundet.</p></div>';
		return;
	}
	foreach ( $r['articles'] as $a ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;margin:12px 0;max-width:900px"><h2 style="margin:0 0 6px"><a href="' . esc_url( $a['url'] ) . '">' . esc_html( $a['title'] ) . '</a></h2><ul style="margin:0">';
		foreach ( $a['issues'] as $is ) {
			$alt = ! empty( $is['alternatives'][0] ) ? ' — forslag: ' . esc_html( $is['alternatives'][0]['name'] . ' (' . number_format( (float) $is['alternatives'][0]['price'], 0, ',', '.' ) . ' kr)' ) : '';
			echo '<li>' . esc_html( $is['message'] ) . $alt . '</li>';
		}
		echo '</ul></div>';
	}
	echo '</div>';
}
