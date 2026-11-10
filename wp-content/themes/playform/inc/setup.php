<?php
/**
 * Grundopsætning, når temaet aktiveres: danske URL'er, forside og standardsider.
 * Kan køres igen sikkert (opretter kun det, der mangler).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_switch_theme', 'playform_setup_site' );

/**
 * Opdateringer efter en udrulning: kategorier, tekster, plugin-tabeller og URL-regler.
 *
 * Udrulningen (GitHub Actions) uploader filerne én ad gangen og lægger til sidst et nyt id i
 * wp-content/playform-deploy.txt. Først når id'et skifter, er alle filer på plads, og så kører vi
 * opdateringerne. (Tidligere brugte vi kun versionsnumre, men en sidevisning midt i uploaden kunne
 * så køre opdateringen med halvt gamle filer og markere den som færdig.)
 * Lokalt, uden id-fil, køres de, når PLAYFORM_THEME_VERSION ændres.
 */
add_action(
	'init',
	static function () {
		$file      = WP_CONTENT_DIR . '/playform-deploy.txt';
		$deploy_id = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : ''; // phpcs:ignore
		$want      = $deploy_id !== '' ? $deploy_id : PLAYFORM_THEME_VERSION;
		if ( get_option( 'playform_content_version' ) === $want || get_transient( 'playform_upgrading' ) ) {
			return;
		}
		set_transient( 'playform_upgrading', 1, 5 * MINUTE_IN_SECONDS ); // kun én ad gangen
		@set_time_limit( 300 ); // phpcs:ignore
		playform_run_upgrades();
		$GLOBALS['pf_upgrade_ran'] = true;
		update_option( 'playform_content_version', $want, false );
		delete_transient( 'playform_upgrading' );
		playform_purge_everything(); // nye filer → tøm sidecachen, så besøgende ikke får gammel HTML
	},
	25
);

/**
 * Tømmer SiteGrounds sidecache (SiteGround Optimizer). Prøver alle kendte indgange og returnerer, hvad der blev brugt
 * ("none" = ingen fundet). Resultatet sendes som header ved udrulningens kald, så det kan ses i GitHub Actions.
 */
/** Sider, der kan ligge i SiteGrounds sidecache. 'terms' = forside + alle kategorisider (vigtigst), 'rest' = tilbudssider, guides og artikler. */
function playform_purge_urls( string $group ): array {
	$urls = [];
	if ( $group === 'first' ) {
		$urls[] = home_url( '/' );
		$urls[] = home_url( user_trailingslashit( 'tilbud' ) );
		foreach ( function_exists( 'playform_campaign_defs' ) ? array_keys( playform_campaign_defs() ) : [] as $cslug ) {
			$urls[] = home_url( user_trailingslashit( $cslug ) );
		}
		return $urls;
	}
	if ( $group === 'terms' ) {
		$urls[] = home_url( '/' );
		// Tilbud og kampagnesider (/tilbud/, /black-friday/ …) – stod ikke på listen, så de kunne vise en gammel udgave i timevis.
		$urls[] = home_url( user_trailingslashit( 'tilbud' ) );
		if ( function_exists( 'playform_campaign_defs' ) ) {
			foreach ( array_keys( playform_campaign_defs() ) as $cslug ) {
				$urls[] = home_url( user_trailingslashit( $cslug ) );
			}
		}
		foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $t ) {
			$l = get_term_link( $t );
			if ( ! is_wp_error( $l ) ) {
				$urls[] = $l;
			}
		}
	} else {
		$urls = [ home_url( '/koebsguide/' ), home_url( '/viden/' ), home_url( '/vores-butikker/' ) ];
		foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $t ) {
			$l = get_term_link( $t );
			if ( ! is_wp_error( $l ) ) {
				$urls[] = trailingslashit( $l ) . 'tilbud/';
			}
		}
		foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'post_status' => 'publish', 'posts_per_page' => 300, 'fields' => 'ids', 'orderby' => 'ID' ] ) as $pid ) {
			$urls[] = get_permalink( $pid );
		}
	}
	return array_values( array_unique( $urls ) );
}

/**
 * Tømmer SiteGrounds sidecache side for side (SiteGrounds samlede "tøm alt" rammer ikke undersider). SiteGround tager kun
 * imod ca. én tømning pr. sekund. $group: 'terms' (forside + kategorier, ca. 1 min) eller 'rest' (resten, via cron).
 */
function playform_purge_cache( string $group = 'terms' ): string {
	delete_transient( 'playform_front_mobile_cats' );
	@set_time_limit( 300 ); // phpcs:ignore
	$S = '\\SiteGround_Optimizer\\Supercacher\\Supercacher';
	if ( class_exists( $S ) && method_exists( $S, 'purge_cache_request' ) ) {
		$purge = static fn( string $u ) => $S::purge_cache_request( $u, false );
		$used  = 'Supercacher::purge_cache_request';
	} elseif ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		$purge = static fn( string $u ) => sg_cachepress_purge_cache( $u );
		$used  = 'sg_cachepress_purge_cache(url)';
	} else {
		update_option( 'playform_last_purge', gmdate( 'c' ) . ' none', false );
		return 'none';
	}
	$urls = playform_purge_urls( $group );
	foreach ( $urls as $i => $u ) {
		if ( $i ) {
			usleep( 1100000 ); // SiteGround: højst én tømning pr. sekund
		}
		$purge( $u );
	}
	$used .= sprintf( ' | %s: %d sider', $group, count( $urls ) );
	update_option( 'playform_last_purge', gmdate( 'c' ) . ' ' . $used, false );
	return $used;
}

/** Forside + kategorier tømmes straks; resten (tilbudssider, guides, artikler) lægges i cron og tømmes inden for 15 minutter. */
function playform_purge_everything(): void {
	playform_purge_cache( 'first' ); // forside, /tilbud/ og kampagnesider først – få sekunder, så de altid når at blive tømt
	playform_purge_cache( 'terms' );
	if ( ! wp_next_scheduled( 'playform_purge_rest' ) ) {
		wp_schedule_single_event( time() + 5, 'playform_purge_rest' );
	}
}
add_action( 'playform_purge_rest', static fn() => playform_purge_cache( 'rest' ) );

// Diagnose: udrulningens kald til /wp-json/?pf_deploy=… får at vide, om opdateringerne kørte, og hvordan cachen blev tømt.
add_filter(
	'rest_post_dispatch',
	static function ( $response ) {
		if ( isset( $_GET['pf_deploy'] ) && $response instanceof WP_REST_Response ) { // phpcs:ignore
			$response->header( 'X-PF-Upgrade', ! empty( $GLOBALS['pf_upgrade_ran'] ) ? 'ran' : 'skipped' );
			$response->header( 'X-PF-Purge', (string) get_option( 'playform_last_purge', 'never' ) );
			$response->header( 'X-PF-Css', (string) filemtime( get_template_directory() . '/assets/css/main.css' ) );
			// Produkter i købsguides, der ikke kan købes: guide:EAN=status (status, lager, kategori).
			if ( function_exists( 'playform_root_posts' ) ) {
				global $wpdb;
				$bad = [];
				foreach ( playform_root_posts() as $slug => $pid ) {
					foreach ( playform_picks( (int) $pid ) as $pk ) {
						$ean = (string) ( $pk['ean'] ?? '' );
						$row = $wpdb->get_row( $wpdb->prepare( "SELECT p.ID, p.post_status FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_pf_ean' WHERE p.post_type = 'product' AND m.meta_value = %s LIMIT 1", $ean ) );
						if ( ! $row ) {
							$offer = $wpdb->get_var( $wpdb->prepare( "SELECT CONCAT(shop, '/', product_id) FROM {$wpdb->prefix}pf_offers WHERE ean = %s LIMIT 1", $ean ) );
							$bad[] = "$slug:$ean=none(offer:" . ( $offer ?: '-' ) . ')';
						} elseif ( $row->post_status !== 'publish' || ! get_post_meta( $row->ID, '_pf_in_stock', true ) ) {
							$bad[] = "$slug:$ean={$row->ID}/{$row->post_status}/stock" . (int) get_post_meta( $row->ID, '_pf_in_stock', true ) . '/img' . ( get_post_meta( $row->ID, '_pf_image', true ) ? 1 : 0 ) . '/' . get_post_meta( $row->ID, '_pf_category', true );
						}
					}
				}
				$response->header( 'X-PF-Picks', $bad ? implode( ' ', $bad ) : 'ok' );
				$response->header( 'X-PF-Imageless', (string) get_option( 'playform_imageless_hidden', 'unset' ) );
				$last = (array) get_option( 'playform_feeds_last_run', [] );
				$sum  = [ 'started=' . ( $last['started'] ?? '-' ), 'finished=' . ( $last['finished'] ?? '-' ) ];
				foreach ( (array) ( $last['shops'] ?? [] ) as $k => $st ) {
					$sum[] = $k . ':' . (int) ( $st['read'] ?? 0 ) . 'r/' . (int) ( $st['new'] ?? 0 ) . 'n' . ( ! empty( $st['error'] ) ? '/ERR ' . substr( (string) $st['error'], 0, 80 ) : '' );
				}
				$response->header( 'X-PF-Lastrun', implode( ' ', $sum ) );
				$counts = $wpdb->get_results( "SELECT shop, COUNT(*) n FROM {$wpdb->prefix}pf_offers GROUP BY shop", ARRAY_A ) ?: [];
				$response->header( 'X-PF-Offers', implode( ' ', array_map( static fn( $r ) => $r['shop'] . '=' . $r['n'], $counts ) ) );
				$state = get_option( 'playform_import_state' );
				$response->header( 'X-PF-State', $state ? wp_json_encode( array_intersect_key( (array) $state, array_flip( [ 'shop', 'offset', 'queue', 'started', 'today', 'i' ] ) ) ) : 'none' );
				$response->header( 'X-PF-Legacy', (string) wp_json_encode( array_count_values( array_map( static fn( $v ) => explode( ':', (string) $v )[0], (array) get_option( 'playform_legacy_imgs', [] ) ) ) ) );
				$response->header( 'X-PF-Running', get_transient( 'playform_feeds_running' ) ? 'yes' : 'no' );
			}
		}
		return $response;
	}
);

// Efter hver import har priser og lager ændret sig – tøm også cachen her.
add_action( 'playform_feeds_after_import', 'playform_purge_everything', 99 );


/**
 * Billigste butik regnes nu inkl. fragt (4. nov. 2026). Produkter, der sælges af flere butikker, genberegnes én gang,
 * så "Billigst hos" og knappen peger på den rigtige butik med det samme. 300 ad gangen via planlagte kørsler.
 */
function playform_refresh_multishop( int $batch = 300 ): void {
	if ( get_option( 'playform_multishop_refreshed' ) || ! class_exists( '\\Playform\\Feeds\\ProductSync' ) ) {
		return;
	}
	global $wpdb;
	$after = (int) get_option( 'playform_multishop_after', 0 );
	$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pf_shop_count' AND CAST(meta_value AS UNSIGNED) > 1 AND post_id > %d ORDER BY post_id ASC LIMIT %d", $after, $batch ) );
	$today = current_time( 'Y-m-d' );
	foreach ( $ids as $id ) {
		\Playform\Feeds\ProductSync::refresh( (int) $id, $today );
		update_option( 'playform_multishop_after', (int) $id, false );
	}
	if ( count( $ids ) < $batch ) {
		update_option( 'playform_multishop_refreshed', 1, false );
		if ( function_exists( 'playform_purge_everything' ) ) {
			playform_purge_everything();
		}
	} elseif ( ! wp_next_scheduled( 'playform_refresh_multishop' ) ) {
		wp_schedule_single_event( time() + 60, 'playform_refresh_multishop' );
	}
}
add_action( 'playform_refresh_multishop', static fn() => playform_refresh_multishop() );

/**
 * Lancering 4. nov. 2026 (kitzhen.dk): slår WooCommerce' "Kommer snart"-tilstand fra og åbner for søgemaskiner.
 * Kører kun én gang – slår ejeren det til igen, bliver det ikke slået fra på ny.
 */
function playform_launch(): void {
	// Køres efter hver udrulning: WooCommerce' "Kommer snart"-tilstand skjuler butikken for besøgende, og den kan
	// blive slået til igen af WooCommerce' opsætning. Den skal altid være slået fra.
	update_option( 'woocommerce_coming_soon', 'no' );
	update_option( 'woocommerce_store_pages_only', 'no' );
	// Søgemaskiner må kun komme, når sitet ligger på sit rigtige domæne (ikke på SiteGrounds midlertidige adresse).
	// SiteGround kan selv have skiftet WordPress-adressen ved domæneskiftet, så det tjekkes hver gang.
	$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
	$want = str_ends_with( $home, '.sg-host.com' ) || 'localhost' === $home ? '0' : '1';
	if ( get_option( 'blog_public' ) !== $want ) {
		update_option( 'blog_public', $want );
	}
	if ( ! get_option( 'playform_launched' ) ) {
		update_option( 'playform_launched', current_time( 'mysql' ), false );
	}
}

function playform_run_upgrades(): void {
	playform_launch();
	playform_rename_categories(); // før Install::activate, så der ikke oprettes en ny kategori ved siden af den gamle
	playform_refresh_multishop();
	playform_update_legal_pages();
	playform_ensure_how_page();
	playform_ensure_method_page();
	playform_vary_default_texts();
	playform_fix_default_site_name();
	playform_author_profile();
	playform_hide_imageless_products();
	playform_fix_brand_case();
	// Importens tabeller og kategorier (kolonner, underkategorier, omsortering).
	if ( class_exists( '\Playform\Feeds\Install' ) ) {
		\Playform\Feeds\Install::activate();
	}
	playform_restructure_menu_categories();
	playform_seed_category_texts();
	playform_seed_articles();
	playform_fix_author_name();
	playform_seed_best();
	// Er sorteringsreglerne (Config.php) ændret, sorteres alle tilbud igen i baggrunden.
	if ( class_exists( '\\Playform\\Feeds\\Importer' ) && method_exists( '\\Playform\\Feeds\\Importer', 'reclassify' ) ) {
		$hash = (string) md5_file( WP_PLUGIN_DIR . '/playform-feeds/src/Config.php' );
		if ( ( get_option( 'playform_config_hash' ) !== $hash || get_option( 'playform_resub_v' ) !== '1' ) && ! wp_next_scheduled( 'playform_reclassify' ) ) {
			update_option( 'playform_resub_v', '1', false ); // 5. nov. 2026: underkategorier sættes på alle produkter én gang
			wp_schedule_single_event( time() + 60, 'playform_reclassify' );
		}
	}
	// Er der aldrig kørt en fuld import (kun "Kør import nu" for enkelte butikker), køres den via cron med det samme.
	if ( class_exists( '\\Playform\\Feeds\\Cron' ) && ! get_option( 'playform_feeds_last_run' ) && ! wp_next_scheduled( \Playform\Feeds\Cron::HOOK, [ 'first' ] ) ) {
		wp_schedule_single_event( time() + 300, \Playform\Feeds\Cron::HOOK, [ 'first' ] );
	}
	playform_import_legacy_posts();
	if ( function_exists( 'playform_best_check' ) && ! get_option( 'playform_best_checked' ) ) {
		playform_best_check( false ); // første rapport med det samme
	}
	playform_legacy_article_images();
	playform_article_images();
	playform_guide_topics();
	if ( class_exists( '\Playform\Feeds\Sales' ) ) {
		\Playform\Feeds\Sales::refresh_all( current_time( 'Y-m-d' ) ); // kontrollerede førpriser
	}
	foreach ( [ 'playform_front_popular', 'playform_front_deals', 'playform_front_new', 'playform_price_ranges', 'playform_front_cat_gas-pizzaovn', 'playform_front_cat_elektrisk-pizzaovn', 'playform_front_cat_pizzasten-sten', 'playform_front_cat_pizzaspader', 'playform_front_cat_aeltemaskine' ] as $t ) {
		delete_transient( $t );
	}
	// Indlæg (guides) ligger direkte under roden: /bedste-gasgrill/ (samme adresser som det gamle site).
	if ( get_option( 'permalink_structure' ) !== '/%postname%/' ) {
		update_option( 'permalink_structure', '/%postname%/' );
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
	}
	// WooCommerce kræver et præfiks. Det bruges kun til gamle links, som omdirigeres (se permalinks.php).
	$perma = (array) get_option( 'woocommerce_permalinks', [] );
	if ( ( $perma['category_base'] ?? '' ) !== 'kategori' ) {
		$perma['category_base'] = 'kategori';
		update_option( 'woocommerce_permalinks', $perma );
	}
	flush_rewrite_rules( false );
}

/**
 * Opretter grupperingskategorierne (playform_virtual_groups()) og flytter de rigtige kategorier
 * ind under dem. Kitzhen har ingen grupper endnu. Kan køres igen sikkert.
 */
function playform_restructure_menu_categories(): void {
	foreach ( playform_virtual_groups() as $slug => $group ) {
		$parent = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $parent ) {
			$result    = wp_insert_term( $group['label'], 'product_cat', [ 'slug' => $slug ] );
			$parent_id = is_wp_error( $result ) ? 0 : (int) $result['term_id'];
		} else {
			$parent_id = (int) $parent->term_id;
		}
		if ( ! $parent_id ) {
			continue;
		}
		foreach ( $group['children'] as $child_slug ) {
			$child = get_term_by( 'slug', $child_slug, 'product_cat' );
			if ( $child && (int) $child->parent !== $parent_id ) {
				wp_update_term( $child->term_id, 'product_cat', [ 'parent' => $parent_id ] );
			}
		}
	}
}

/** Retter en bestemt sætning på en fast side (kun hvis den står der uændret – redaktørens rettelser vinder). */
function playform_update_text( string $slug, string $old, string $new ): void {
	$page = get_page_by_path( $slug );
	if ( $page && str_contains( $page->post_content, $old ) ) {
		wp_update_post( [ 'ID' => $page->ID, 'post_content' => str_replace( $old, $new, $page->post_content ) ] );
	}
}

/**
 * Henter de gamle artikelbilleder fra kitzhen.dk (content/legacy-images.php) og sætter dem som udvalgt billede.
 * Kun billeder på mindst 1200 px i bredden bruges (ingen pixelerede billeder) – ellers beholdes Pexels-billedet.
 * Højst $limit pr. kørsel; resten tages af en planlagt kørsel et par minutter senere. Status i option playform_legacy_imgs.
 */
function playform_legacy_article_images( int $limit = 4 ): void {
	$file = get_template_directory() . '/content/legacy-images.php';
	$map  = is_readable( $file ) ? require $file : [];
	$done = (array) get_option( 'playform_legacy_imgs', [] );
	$todo = array_diff_key( $map, $done );
	if ( ! $todo ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	@set_time_limit( 300 ); // phpcs:ignore
	// Planlæg næste kørsel først – stopper denne kørsel undervejs (timeout), fortsætter den næste, hvor vi slap.
	if ( count( $todo ) > $limit && ! wp_next_scheduled( 'playform_legacy_images' ) ) {
		wp_schedule_single_event( time() + 120, 'playform_legacy_images' );
	}
	foreach ( array_slice( $todo, 0, $limit, true ) as $slug => $url ) {
		$done[ $slug ] = 'fejl'; // gemmes med det samme, så et billede, der får kørslen til at gå ned, ikke blokerer resten
		update_option( 'playform_legacy_imgs', $done, false );
		$post = get_posts( [ 'name' => $slug, 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ] );
		if ( ! $post ) {
			continue; // guiden er ikke oprettet endnu – prøv igen senere
		}
		$id  = $post[0]->ID;
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			$done[ $slug ] = 'fejl';
			continue;
		}
		$size = @getimagesize( $tmp ); // phpcs:ignore
		if ( ! $size || (int) $size[0] < 1200 ) {
			@unlink( $tmp ); // phpcs:ignore
			$done[ $slug ] = 'for lille:' . (int) ( $size[0] ?? 0 );
			continue;
		}
		$ext = image_type_to_extension( (int) $size[2], false ) ?: 'jpg';
		$att = media_handle_sideload( [ 'name' => $slug . '.' . $ext, 'tmp_name' => $tmp ], $id, get_the_title( $id ) );
		if ( is_wp_error( $att ) ) {
			@unlink( $tmp ); // phpcs:ignore
			$done[ $slug ] = 'fejl';
			continue;
		}
		update_post_meta( $att, '_pf_legacy_img', $url );
		update_post_meta( $att, '_wp_attachment_image_alt', get_the_title( $id ) );
		set_post_thumbnail( $id, $att );
		$done[ $slug ] = 'ok';
		update_option( 'playform_legacy_imgs', $done, false );
	}
	update_option( 'playform_legacy_imgs', $done, false );
	if ( array_diff_key( $map, $done ) && ! wp_next_scheduled( 'playform_legacy_images' ) ) {
		wp_schedule_single_event( time() + 120, 'playform_legacy_images' );
	}
}
add_action( 'playform_legacy_images', static fn() => playform_legacy_article_images() );


/**
 * Artikelbilleder (Pexels, fri licens) fra assets/img/articles/<slug>.jpg.
 * Sættes på indlæg uden billede eller med et for lille billede (< 1200 px) – et billede,
 * redaktøren selv har valgt i den rigtige størrelse, bliver stående.
 */
function playform_article_images(): void {
	$files = glob( get_template_directory() . '/assets/img/articles/*.jpg' ) ?: [];
	if ( ! $files ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	foreach ( $files as $src ) {
		$slug = basename( $src, '.jpg' );
		$post = get_posts( [ 'name' => $slug, 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ] );
		if ( ! $post ) {
			continue;
		}
		$id    = $post[0]->ID;
		$thumb = get_post_thumbnail_id( $id );
		$hash  = md5_file( $src );
		if ( $thumb ) {
			if ( get_post_meta( $thumb, '_pf_legacy_img', true ) ) {
				continue; // billedet fra det gamle kitzhen.dk vinder
			}
			if ( get_post_meta( $thumb, '_pf_auto_img', true ) === $hash ) {
				continue; // allerede dette billede
			}
			$meta = wp_get_attachment_metadata( $thumb );
			if ( (int) ( $meta['width'] ?? 0 ) >= 1200 && ! get_post_meta( $thumb, '_pf_auto_img', true ) ) {
				continue; // redaktørens eget billede i god størrelse
			}
		}
		$tmp = wp_tempnam( $slug . '.jpg' );
		copy( $src, $tmp );
		$att = media_handle_sideload( [ 'name' => $slug . '.jpg', 'tmp_name' => $tmp ], $id, get_the_title( $id ) );
		if ( is_wp_error( $att ) ) {
			@unlink( $tmp ); // phpcs:ignore
			continue;
		}
		update_post_meta( $att, '_pf_auto_img', $hash );
		update_post_meta( $att, '_wp_attachment_image_alt', get_the_title( $id ) );
		set_post_thumbnail( $id, $att );
	}
}

/** Emner under Viden & guides: /viden/emne/<emne>/ med en kort beskrivelse (kun hvis den er tom). */
function playform_guide_topics(): void {
	if ( get_option( 'category_base' ) !== 'viden/emne' ) {
		update_option( 'category_base', 'viden/emne' );
		// Kategoriens adresse er allerede registreret i denne forespørgsel – ret den, så flush_rewrite_rules() bruger den nye.
		global $wp_rewrite;
		$wp_rewrite->set_category_base( 'viden/emne' );
		if ( isset( $wp_rewrite->extra_permastructs['category'] ) ) {
			$wp_rewrite->extra_permastructs['category']['struct'] = 'viden/emne/%category%';
		}
	}
	$texts = [
		'Købsguides' => 'Vi gennemgår pizzaovne, pizzasten, pizzaspader og andet pizzaudstyr, peger på de modeller, vi synes er værd at kigge på, og forklarer, hvad du skal være opmærksom på.',
		'Viden'      => 'Svar på de spørgsmål, der dukker op, når du laver pizza og bruger din pizzaovn.',
	];
	foreach ( $texts as $name => $desc ) {
		$t = get_term_by( 'name', $name, 'category' );
		if ( $t && ( trim( $t->description ) === '' || ( 'Købsguides' === $name && trim( $t->description ) === 'Vores bud på de bedste modeller, og hvad du skal kigge efter, før du køber pizzaovn, pizzasten, pizzaspade og andet pizzaudstyr.' ) ) ) { // den gamle fælles formulering skiftes også
			wp_update_term( $t->term_id, 'category', [ 'description' => $desc ] );
		}
	}
}

function playform_setup_site(): void {
	update_option( 'timezone_string', 'Europe/Copenhagen' );
	// Guides ligger direkte under roden: /bedste-gasgrill/
	update_option( 'permalink_structure', '/%postname%/' );
	// WooCommerce' ordresporing sætter cookies (sourcebuster) – den bruges ikke, da der ikke handles på siden.
	update_option( 'woocommerce_feature_order_attribution_enabled', 'no' );
	update_option( 'date_format', 'j. F Y' );
	update_option( 'WPLANG', 'da_DK' );
	update_option( 'blogname', playform_site( 'name' ) );
	update_option( 'blogdescription', playform_site( 'tagline' ) );
	// Danske URL'er: /produkt/navn/. Kategorier får flade URL'er (se permalinks.php).
	$perma = (array) get_option( 'woocommerce_permalinks', [] );
	$perma['category_base'] = 'kategori';
	$perma['tag_base']      = 'emne';
	$perma['product_base']  = 'produkt';
	update_option( 'woocommerce_permalinks', $perma );

	$pages = [
		'forside'                => [ 'Forside', '' ],
		'produkter'              => [ 'Alle produkter', '' ],
		'viden'                  => [ 'Viden & guides', '' ],
		'vores-butikker'         => [ 'Vores butikker', playform_default_text( 'butikker' ) ],
		'saadan-tjener-vi-penge' => [ 'Sådan tjener vi penge', playform_default_text( 'penge' ) ],
		'privatlivspolitik'      => [ 'Privatlivspolitik', playform_legal_privacy() ],
		'cookies'                => [ 'Cookiepolitik', playform_legal_cookies() ],
	];
	foreach ( $pages as $slug => [ $title, $content ] ) {
		$existing = get_page_by_path( $slug );
		if ( $existing && str_starts_with( trim( $existing->post_content ), '<p>[' ) && $content ) {
			wp_update_post( [ 'ID' => $existing->ID, 'post_title' => $title, 'post_content' => $content ] );
		}
		if ( ! $existing ) {
			wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
		}
	}
	$front = get_page_by_path( 'forside' );
	$blog  = get_page_by_path( 'viden' );
	if ( $front && $blog ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front->ID );
		update_option( 'page_for_posts', $blog->ID );
	}
	$shop = get_page_by_path( 'produkter' );
	if ( $shop ) {
		update_option( 'woocommerce_shop_page_id', $shop->ID );
	}
	playform_seed_category_texts();
	// WordPress' eksempelindhold fjernes.
	foreach ( [ 'hello-world' => 'post', 'sample-page' => 'page' ] as $slug => $type ) {
		$p = get_page_by_path( $slug, OBJECT, $type );
		if ( $p ) {
			wp_delete_post( $p->ID, true );
		}
	}
	flush_rewrite_rules();
}

function playform_default_text( string $which ): string {
	$name  = playform_site( 'name' );
	$niche = playform_site( 'niche' );
	if ( $which === 'butikker' ) {
		return '<p>' . "$name giver dig overblik over $niche fra de butikker, vi samarbejder med. Produkter, priser og lagerstatus henter vi hos butikkerne morgen og aften. Trykker du på \"Gå til butik\", lander du hos butikken og handler der som vanligt." . "</p><h2>Butikker på $name</h2>";
	}
	return '<p>' . "Du betaler ikke noget for at bruge $name. Vi får en lille provision fra butikken, når du klikker videre og køber noget – uden at det gør varen dyrere for dig. Du vælger selv, om produkterne skal sorteres efter pris, tilbud eller nyeste." . '</p><p>' . "Vi viser udelukkende butikkernes egne tilbud, og højst i 14 dage. Kender vi butikkens pris fra de 30 dage før tilbuddet, er førprisen den laveste pris i den periode. Ellers vises butikkens egen førpris, tydeligt markeret som butikkens. Se altid den endelige pris hos butikken, før du bestiller." . '</p>';
}

/**
 * Teksterne på "Vores butikker" og "Sådan tjener vi penge" var ens på alle sites (dublet-indhold på tværs af domæner).
 * Skifter de gamle standardafsnit ud med dette sites egen formulering – kun hvor afsnittet står uændret
 * (playform_update_text), så redaktørens rettelser vinder. Kan køres igen sikkert.
 */
function playform_vary_default_texts(): void {
	$name  = playform_site( 'name' );
	$niche = playform_site( 'niche' );
	if ( '' === $niche ) {
		return;
	}
	playform_update_text( 'vores-butikker', "$name samler $niche fra de butikker, vi samarbejder med, ét sted. Vi henter produkter, priser og lagerstatus direkte fra butikkerne to gange i døgnet. Når du klikker på \"Gå til butik\", sendes du videre til butikken, hvor du handler som normalt.", "$name giver dig overblik over $niche fra de butikker, vi samarbejder med. Produkter, priser og lagerstatus henter vi hos butikkerne morgen og aften. Trykker du på \"Gå til butik\", lander du hos butikken og handler der som vanligt." );
	playform_update_text( 'saadan-tjener-vi-penge', "$name er gratis at bruge. Når du klikker videre til en butik og handler, får vi en lille provision fra butikken. Det koster ikke dig noget ekstra. Du kan altid sortere efter pris, tilbud eller nyeste.", "Du betaler ikke noget for at bruge $name. Vi får en lille provision fra butikken, når du klikker videre og køber noget – uden at det gør varen dyrere for dig. Du vælger selv, om produkterne skal sorteres efter pris, tilbud eller nyeste." );
	playform_update_text( 'saadan-tjener-vi-penge', "Som tilbud viser vi kun butikkernes egne tilbud, og kun i højst 14 dage. Har vi selv fulgt prisen hos butikken i de 30 dage før tilbuddet, viser vi den laveste pris i perioden som førpris. Ellers viser vi butikkens egen førpris, tydeligt mærket som butikkens. Tjek altid den endelige pris hos butikken.", "Vi viser udelukkende butikkernes egne tilbud, og højst i 14 dage. Kender vi butikkens pris fra de 30 dage før tilbuddet, er førprisen den laveste pris i den periode. Ellers vises butikkens egen førpris, tydeligt markeret som butikkens. Se altid den endelige pris hos butikken, før du bestiller." );
}

/**
 * Introtekst, SEO-tekst og FAQ til kategorierne og underkategorierne fra content/category-texts.php.
 * Tomme felter udfyldes; felter med tekst røres ikke (redaktøren kan rette dem under Produkter › Kategorier).
 */
function playform_seed_category_texts(): void {
	$texts = (array) require get_template_directory() . '/content/category-texts.php';
	// Én gang (10. nov. 2026): gemte tekster må ikke antyde, at vi dækker alle danske butikker.
	if ( (int) get_option( 'playform_cat_texts_fix' ) < 1 ) {
		$fix = static fn( string $v ): string => (string) preg_replace(
			[ '/fra (flere )?danske (webshops|butikker|shops)( samlet| ét sted)/u', '/fra (flere )?danske (webshops|butikker|shops)(?!,? vi samarbejder)/u' ],
			[ 'fra de butikker, vi samarbejder med,$3', 'fra de butikker, vi samarbejder med' ],
			$v
		);
		foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $tm ) {
			if ( $tm->description !== $fix( $tm->description ) ) {
				wp_update_term( $tm->term_id, 'product_cat', [ 'description' => $fix( $tm->description ) ] );
			}
			$seo = (string) get_term_meta( $tm->term_id, 'pf_seo_text', true );
			if ( $seo !== '' && $seo !== $fix( $seo ) ) {
				update_term_meta( $tm->term_id, 'pf_seo_text', $fix( $seo ) );
			}
		}
		update_option( 'playform_cat_texts_fix', 1, false );
	}
	foreach ( $texts as $slug => $t ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term ) {
			continue;
		}
		// 'refresh' => N i category-texts.php: overskriv de gemte tekster én gang (fx når en underkategori har fået nyt indhold).
		if ( ! empty( $t['refresh'] ) && (int) get_term_meta( $term->term_id, 'pf_text_rev', true ) < (int) $t['refresh'] ) {
			wp_update_term( $term->term_id, 'product_cat', [ 'description' => '' ] );
			delete_term_meta( $term->term_id, 'pf_seo_text' );
			delete_term_meta( $term->term_id, 'pf_faq' );
			update_term_meta( $term->term_id, 'pf_text_rev', (int) $t['refresh'] );
			$term = get_term( $term->term_id, 'product_cat' );
		}
		if ( ! $term->description && ! empty( $t['intro'] ) ) {
			wp_update_term( $term->term_id, 'product_cat', [ 'description' => $t['intro'] ] );
		}
		if ( ! empty( $t['seo'] ) && ! get_term_meta( $term->term_id, 'pf_seo_text', true ) ) {
			update_term_meta( $term->term_id, 'pf_seo_text', $t['seo'] );
		}
		if ( ! empty( $t['faq'] ) && ! get_term_meta( $term->term_id, 'pf_faq', true ) ) {
			update_term_meta( $term->term_id, 'pf_faq', $t['faq'] );
		}
	}
}

/**
 * Opretter artiklerne i content/articles-*.php som KLADDER, hvis de ikke findes i forvejen
 * (uanset status). Eksisterende indlæg røres aldrig, så redaktørens rettelser bevares.
 */
function playform_seed_articles(): void {
	$articles = [];
	foreach ( glob( get_template_directory() . '/content/articles-*.php' ) ?: [] as $file ) {
		$articles = array_merge( $articles, (array) require $file );
	}
	if ( ! $articles ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$author = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	foreach ( $articles as $a ) {
		$exists = get_posts( [ 'name' => $a['slug'], 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ] );
		if ( $exists ) {
			continue;
		}
		$cat = term_exists( $a['cat'], 'category' ) ?: wp_insert_term( $a['cat'], 'category' );
		$id  = wp_insert_post(
			[
				'post_type'     => 'post',
				'post_status'   => $a['status'] ?? 'draft',
				'post_name'     => $a['slug'],
				'post_title'    => $a['title'],
				'post_excerpt'  => $a['excerpt'],
				'post_content'  => trim( $a['html'] ),
				'post_category' => is_wp_error( $cat ) ? [] : [ (int) ( is_array( $cat ) ? $cat['term_id'] : $cat ) ],
				'post_author'   => $author ? (int) $author[0] : 1,
			]
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		$src = get_template_directory() . '/assets/img/' . ( $a['img'] ?? '' );
		if ( ! empty( $a['img'] ) && is_readable( $src ) ) {
			$tmp = wp_tempnam( $a['img'] );
			copy( $src, $tmp );
			$att = media_handle_sideload( [ 'name' => $a['slug'] . '.jpg', 'tmp_name' => $tmp ], $id );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $id, $att );
			}
		}
	}
}

// Viden & guides: 1 fremhævet + 12 i gitteret pr. side.
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( ! is_admin() && $q->is_main_query() && ( $q->is_home() || $q->is_category() ) ) {
			$q->set( 'posts_per_page', 13 );
		}
	}
);

/**
 * Flytter WordPress fra SiteGrounds midlertidige adresse ($from, fx frederikb10.sg-host.com) til domænet ($to).
 * Sker automatisk første gang, siden kaldes på domænet (se init nedenfor) – ingen opsætning i WordPress.
 * Erstatter adressen overalt i databasen; serialiserede værdier pakkes ud og ind igen, så længderne passer.
 * Samme metode som Fitbases flytning til fitbase.dk 2. nov. 2026.
 */
function playform_move_domain( string $from, string $to ): void {
	update_option( 'home', 'https://' . $to );
	update_option( 'siteurl', 'https://' . $to );
	$pairs = [
		'https://' . $from          => 'https://' . $to,
		'http://' . $from           => 'https://' . $to,
		'https:\\/\\/' . $from      => 'https:\\/\\/' . $to, // JSON-escapet
		'http:\\/\\/' . $from       => 'https:\\/\\/' . $to,
		$from                       => $to,
	];
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $from ) . '%';
	$fix  = static function ( $value ) use ( &$fix, $pairs ) {
		if ( is_string( $value ) ) {
			if ( is_serialized( $value ) ) {
				$data = @unserialize( $value, [ 'allowed_classes' => false ] ); // phpcs:ignore
				return false === $data && 'b:0;' !== $value ? strtr( $value, $pairs ) : serialize( $fix( $data ) );
			}
			return strtr( $value, $pairs );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $fix( $v );
			}
		}
		return $value;
	};
	$tables = [
		$wpdb->posts         => [ 'ID', [ 'post_content', 'post_excerpt', 'guid', 'post_content_filtered' ] ],
		$wpdb->postmeta      => [ 'meta_id', [ 'meta_value' ] ],
		$wpdb->options       => [ 'option_id', [ 'option_value' ] ],
		$wpdb->termmeta      => [ 'meta_id', [ 'meta_value' ] ],
		$wpdb->term_taxonomy => [ 'term_taxonomy_id', [ 'description' ] ],
		$wpdb->comments      => [ 'comment_ID', [ 'comment_content', 'comment_author_url' ] ],
		$wpdb->commentmeta   => [ 'meta_id', [ 'meta_value' ] ],
		$wpdb->users         => [ 'ID', [ 'user_url' ] ],
		$wpdb->usermeta      => [ 'umeta_id', [ 'meta_value' ] ],
	];
	foreach ( $tables as $table => [ $id_col, $cols ] ) {
		foreach ( $cols as $col ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT `$id_col` AS id, `$col` AS v FROM `$table` WHERE `$col` LIKE %s", $like ) ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				$new = $fix( $r->v );
				if ( $new !== $r->v ) {
					$wpdb->update( $table, [ $col => $new ], [ $id_col => $r->id ] );
				}
			}
		}
	}
	update_option( 'playform_moved_domain', $from . ' -> ' . $to . ' ' . current_time( 'mysql' ), false );
	update_option( 'blog_public', '1' ); // på det rigtige domæne må søgemaskinerne komme
	wp_cache_flush();
	if ( function_exists( 'playform_purge_everything' ) ) {
		playform_purge_everything();
	}
}

/**
 * Én adresse: https://<domæne> uden www. www-adressen og http sendes videre med 301.
 * Gælder kun på sitets eget domæne (Config::site()['domain']), så SiteGrounds midlertidige
 * adresse og localhost virker, indtil domænet peger på den nye hosting.
 */
add_action(
	'init',
	static function () {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || 'cli' === PHP_SAPI ) {
			return;
		}
		$domain = playform_site( 'domain' );
		$host   = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ); // phpcs:ignore
		$host   = preg_replace( '/:\d+$/', '', $host );
		$home   = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
		// Domænet peger nu på den nye hosting, men WordPress står stadig på SiteGrounds midlertidige adresse: flyt.
		if ( $domain !== '' && in_array( $host, [ $domain, 'www.' . $domain ], true ) && $home !== '' && $home !== $domain && str_ends_with( $home, '.sg-host.com' ) ) {
			playform_move_domain( $home, $domain );
			$home = $domain;
		}
		// Efter flytningen sendes den midlertidige adresse videre til domænet.
		// (Uden importpluginnet er "domænet" blot WordPress-adressen – så må den midlertidige adresse ikke sende videre til sig selv.)
		if ( $home === $domain && ! str_ends_with( $domain, '.sg-host.com' ) && $host !== '' && str_ends_with( $host, '.sg-host.com' ) ) {
			wp_redirect( 'https://' . $domain . ( $_SERVER['REQUEST_URI'] ?? '/' ), 301 ); // phpcs:ignore
			exit;
		}
		if ( $domain === '' || ! in_array( $host, [ $domain, 'www.' . $domain ], true ) ) {
			return;
		}
		$https = is_ssl()
			|| 'https' === strtolower( (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) ) // phpcs:ignore
			|| 'on' === strtolower( (string) ( $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '' ) ); // phpcs:ignore
		if ( $domain !== $host || ! $https ) {
			wp_redirect( 'https://' . $domain . ( $_SERVER['REQUEST_URI'] ?? '/' ), 301 ); // phpcs:ignore
			exit;
		}
	},
	0
);

// Advarsel i administrationen, hvis siden står som skjult for søgemaskiner (fx før lanceringen).
add_action(
	'admin_notices',
	static function () {
		if ( '0' === (string) get_option( 'blog_public' ) && current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error"><p><b>' . esc_html( playform_site( 'name' ) ) . ' er skjult for Google.</b> Gå til Indstillinger → Læsning og fjern markeringen i "Frarådet søgemaskiner at indeksere dette websted", når siden skal lanceres.</p></div>';
		}
	}
);

/**
 * Produkter uden billede er typisk udgåede varer eller reservedele, hvis side hos butikken ofte er væk.
 * De skjules (kladde). Får de et billede, viser importen dem igen. Kører én gang.
 */
function playform_hide_imageless_products(): void {
	// Kører ved hver udrulning: produkter uden billede må ikke stå offentligt (projektregel 4).
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_pf_image'
		 WHERE p.post_type = 'product' AND p.post_status = 'publish' AND ( m.meta_value IS NULL OR m.meta_value = '' )"
	);
	foreach ( (array) $ids as $id ) {
		wp_update_post( [ 'ID' => (int) $id, 'post_status' => 'draft' ] );
	}
	update_option( 'playform_imageless_hidden', count( (array) $ids ), false );
}

/**
 * Favicon: SVG til moderne browsere, PNG (192 px, deler af 48) til Google og Android, Apple-ikon til iOS.
 * /favicon.ico (som mange browsere og crawlere beder om uden at læse siden) sendes videre til PNG'en.
 */
add_action(
	'wp_head',
	static function () {
		$u = get_template_directory_uri() . '/assets/img/';
		echo '<link rel="icon" href="' . esc_url( $u . 'favicon.svg' ) . '" type="image/svg+xml">' . "\n";
		echo '<link rel="icon" href="' . esc_url( $u . 'favicon-32.png' ) . '" type="image/png" sizes="32x32">' . "\n";
		echo '<link rel="icon" href="' . esc_url( $u . 'favicon-192.png' ) . '" type="image/png" sizes="192x192">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $u . 'apple-touch-icon.png' ) . '">' . "\n";
	},
	2
);
add_action(
	'template_redirect',
	static function () {
		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ); // phpcs:ignore
		if ( '/favicon.ico' === $path ) {
			wp_safe_redirect( get_template_directory_uri() . '/assets/img/favicon-32.png', 301 );
			exit;
		}
	},
	0
);

/**
 * Omdøbte kategorier: gammel slug => [ ny slug, nyt navn, gammel intro ]. Kategorien beholder sit term-id,
 * så produkterne følger med. Introen skiftes kun, hvis redaktøren ikke har rettet den.
 */
function playform_renamed_categories(): array {
	return [
		'trae-pizzaovn'       => [ 'pizzaovn-traepiller', 'Pizzaovn til træpiller', 'Pizzaovne til træ og træpiller giver den klassiske røgsmag. Mange kan også bruges med gas. Se modeller fra de butikker, vi samarbejder med.' ],
		'drikkevarekoeleskab' => [ 'vinkoeleskab', 'Vinkøleskab', 'Køleskabe til drikkevarer, vinkøleskabe og ølkølere. Se modeller fra de butikker, vi samarbejder med, samlet ét sted.' ],
	];
}

function playform_rename_categories(): void {
	$texts = file_exists( get_template_directory() . '/content/category-texts.php' ) ? (array) include get_template_directory() . '/content/category-texts.php' : [];
	foreach ( playform_renamed_categories() as $old => [ $new, $name, $old_intro ] ) {
		$term = get_term_by( 'slug', $old, 'product_cat' );
		if ( ! $term || get_term_by( 'slug', $new, 'product_cat' ) ) {
			continue;
		}
		$args = [ 'slug' => $new, 'name' => $name ];
		if ( trim( $term->description ) === $old_intro && ! empty( $texts[ $new ]['intro'] ) ) {
			$args['description'] = $texts[ $new ]['intro'];
		}
		wp_update_term( $term->term_id, 'product_cat', $args );
	}
}

// Gamle kategoriadresser (fx /kaffe-drikke/drikkevarekoeleskab/) → 301 til den nye adresse.
add_action( 'template_redirect', function (): void {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	foreach ( playform_renamed_categories() as $old => [ $new ] ) {
		$parts = explode( '/', $path );
		$i     = array_search( $old, $parts, true );
		if ( false === $i ) {
			continue;
		}
		$term = get_term_by( 'slug', $new, 'product_cat' );
		$url  = $term ? get_term_link( $term ) : '';
		if ( $url && ! is_wp_error( $url ) ) {
			$rest = implode( '/', array_slice( $parts, $i + 1 ) ); // fx tilbud/ eller page/2/
			wp_safe_redirect( trailingslashit( $url ) . ( $rest ? trailingslashit( $rest ) : '' ), 301 );
			exit;
		}
	}
}, 1 );

/**
 * Retter tekster, der blev gemt, mens importpluginnet ikke var aktivt: så hed sitet WordPress' standardnavn
 * ("My WordPress"), og nichen var tom. Kører, indtil intet mere findes – kun når Config er indlæst.
 */
function playform_fix_default_site_name(): void {
	if ( ! class_exists( '\Playform\Feeds\Config' ) || ! method_exists( '\Playform\Feeds\Config', 'site' ) ) {
		return;
	}
	global $wpdb;
	$name  = playform_site( 'name' );
	$niche = playform_site( 'niche' );
	if ( '' === $name || 'My WordPress' === $name ) {
		return;
	}
	foreach ( [ 'My WordPress', 'Mit WordPress' ] as $bad ) {
		if ( in_array( get_option( 'blogname' ), [ $bad, '' ], true ) ) {
			update_option( 'blogname', $name );
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key = 'description' AND meta_value LIKE %s", $bad, $name, '%' . $wpdb->esc_like( $bad ) . '%' ) );
		foreach ( [ 'post_title', 'post_content', 'post_excerpt' ] as $col ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET $col = REPLACE($col, %s, %s) WHERE post_type IN ('post','page','product') AND $col LIKE %s", $bad, $name, '%' . $wpdb->esc_like( $bad ) . '%' ) ); // phpcs:ignore
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->term_taxonomy} SET description = REPLACE(description, %s, %s) WHERE description LIKE %s", $bad, $name, '%' . $wpdb->esc_like( $bad ) . '%' ) );
	}
	if ( '' !== $niche ) { // tom niche gav "samler han  fra de butikker, vi samarbejder med"
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = REPLACE(meta_value, 'samler han  fra', %s) WHERE meta_key = 'description'", "samler han $niche fra" ) );
	}
	wp_cache_flush();
}

/* -------------------------------------------------------------------------
 * Artikler fra det gamle pizzalicious.dk (content/legacy-posts.php)
 * ---------------------------------------------------------------------- */

const PLAYFORM_LEGACY_SOURCE = 'https://pizzalicious.dk';

/**
 * Henter artiklerne 1:1 fra det gamle sites REST API: titel, tekst (med alle links), uddrag, dato og udvalgt billede.
 * Kun sidebyggerens koder ([vc_row] o.l.) fjernes – selve teksten røres ikke. Kører, indtil alle er hentet, og kun så
 * længe sitet ikke selv ligger på pizzalicious.dk (ellers ville vi hente fra os selv). En kopi gemmes i uploads.
 */
function playform_import_legacy_posts( int $limit = 4 ): void {
	$file = get_template_directory() . '/content/legacy-posts.php';
	$list = is_readable( $file ) ? (array) require $file : [];
	$done = (array) get_option( 'playform_legacy_posts', [] );
	$todo = array_diff_key( $list, array_filter( $done, static fn( $v ) => 'ok' === $v ) );
	$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
	if ( ! $todo || str_ends_with( $home, (string) wp_parse_url( PLAYFORM_LEGACY_SOURCE, PHP_URL_HOST ) ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	@set_time_limit( 300 ); // phpcs:ignore
	if ( count( $todo ) > $limit && ! wp_next_scheduled( 'playform_legacy_posts' ) ) {
		wp_schedule_single_event( time() + 120, 'playform_legacy_posts' );
	}
	$author = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	foreach ( array_slice( $todo, 0, $limit, true ) as $slug => $opt ) {
		$done[ $slug ] = 'fejl';
		update_option( 'playform_legacy_posts', $done, false );
		if ( get_posts( [ 'name' => $slug, 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ] ) ) {
			$done[ $slug ] = 'ok';
			update_option( 'playform_legacy_posts', $done, false );
			continue;
		}
		$res = wp_remote_get( PLAYFORM_LEGACY_SOURCE . '/wp-json/wp/v2/posts?slug=' . rawurlencode( $slug ) . '&_embed=wp:featuredmedia', [ 'timeout' => 30 ] );
		$arr = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		$p   = is_array( $arr ) ? ( $arr[0] ?? null ) : null;
		if ( ! $p ) {
			continue;
		}
		$up = wp_upload_dir();
		wp_mkdir_p( $up['basedir'] . '/playform/legacy-posts' );
		file_put_contents( $up['basedir'] . '/playform/legacy-posts/' . $slug . '.json', wp_json_encode( $p ) ); // phpcs:ignore

		$html = (string) ( $p['content']['rendered'] ?? '' );
		$html = preg_replace( '/\[\/?vc_[^\]]*\]/', '', $html );           // sidebyggerens koder
		$html = preg_replace( '#<p>\s*</p>\s*#', '', $html );                 // tomme afsnit, de efterlod
		$cat_name = ! empty( $opt['hidden'] ) ? 'Annonce' : 'Viden';
		$cat      = term_exists( $cat_name, 'category' ) ?: wp_insert_term( $cat_name, 'category' );
		$id       = wp_insert_post(
			[
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_name'     => $slug,
				'post_title'    => html_entity_decode( (string) $p['title']['rendered'], ENT_QUOTES, 'UTF-8' ),
				'post_excerpt'  => trim( html_entity_decode( wp_strip_all_tags( (string) $p['excerpt']['rendered'] ), ENT_QUOTES, 'UTF-8' ) ),
				'post_content'  => trim( $html ),
				'post_date'     => (string) $p['date'],
				'post_date_gmt' => (string) $p['date_gmt'],
				'post_category' => is_wp_error( $cat ) ? [] : [ (int) ( is_array( $cat ) ? $cat['term_id'] : $cat ) ],
				'post_author'   => $author ? (int) $author[0] : 1,
			],
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}
		update_post_meta( $id, '_pf_legacy_post', 1 );
		if ( ! empty( $opt['hidden'] ) ) {
			update_post_meta( $id, '_pf_hidden', 1 );
			delete_transient( 'playform_hidden_posts' );
		}
		$img = (string) ( $p['_embedded']['wp:featuredmedia'][0]['source_url'] ?? '' );
		if ( $img ) {
			$tmp = download_url( $img, 30 );
			if ( ! is_wp_error( $tmp ) ) {
				$att = media_handle_sideload( [ 'name' => basename( (string) wp_parse_url( $img, PHP_URL_PATH ) ), 'tmp_name' => $tmp ], $id );
				if ( ! is_wp_error( $att ) ) {
					set_post_thumbnail( $id, $att );
					update_post_meta( $id, '_pf_legacy_img', $img ); // playform_article_images() lader det være
				}
			}
		}
		$done[ $slug ] = 'ok';
		update_option( 'playform_legacy_posts', $done, false );
	}
}
add_action( 'playform_legacy_posts', static fn() => playform_import_legacy_posts() );

add_action(
	'playform_reclassify',
	static function () {
		@set_time_limit( 0 ); // phpcs:ignore
		$res = \Playform\Feeds\Importer::reclassify();
		update_option( 'playform_config_hash', (string) md5_file( WP_PLUGIN_DIR . '/playform-feeds/src/Config.php' ), false );
		update_option( 'playform_reclassify_last', [ 'time' => current_time( 'mysql' ) ] + $res, false );
	}
);

/**
 * Skjulte artikler (_pf_hidden, se content/legacy-posts.php) vises ikke i lister: Viden & guides, emnesider,
 * forsiden, "Læs også", forfattersiden og søgning. Selve artiklen og sitemap er uændret.
 */
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( is_admin() || $q->is_singular() || $q->get( 'name' ) || $q->get( 'p' ) || $q->get( 'pf_with_hidden' ) ) {
			return;
		}
		$type = $q->get( 'post_type' );
		if ( ! in_array( $type, [ '', 'post', [ 'post' ] ], true ) && ! ( is_array( $type ) && in_array( 'post', $type, true ) ) ) {
			return;
		}
		$ids = get_transient( 'playform_hidden_posts' );
		if ( false === $ids ) {
			global $wpdb;
			$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pf_hidden' AND meta_value = '1'" ) );
			set_transient( 'playform_hidden_posts', $ids, HOUR_IN_SECONDS );
		}
		if ( $ids ) {
			$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), $ids ) );
		}
	}
);
// Skjulte artikler skal stadig i sitemap, så de bliver fundet af søgemaskinerne.
add_filter( 'wp_sitemaps_posts_query_args', static fn( array $args ): array => $args + [ 'pf_with_hidden' => true ] );

/**
 * Én gang: mærker med stort begyndelsesbogstav i de eksisterende produkter (metafelt og starten af navnet).
 * Nye og opdaterede produkter rettes af importen (Normalizer::brand_case). Navne, redaktøren har låst, røres ikke.
 */
function playform_fix_brand_case(): void {
	if ( get_option( 'playform_brand_case_v' ) === '1' || ! method_exists( '\Playform\Feeds\Normalizer', 'brand_case' ) ) {
		return;
	}
	global $wpdb;
	$brands = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_pf_brand' AND meta_value <> ''" );
	foreach ( (array) $brands as $old ) {
		$new = \Playform\Feeds\Normalizer::brand_case( (string) $old );
		if ( $new === $old ) {
			continue;
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pf_brand' AND meta_value = %s", $old ) );
		foreach ( (array) $ids as $id ) {
			update_post_meta( (int) $id, '_pf_brand', $new );
			$locks = (array) get_post_meta( (int) $id, '_pf_locks', true );
			if ( empty( $locks['name'] ) ) {
				$title = (string) get_post_field( 'post_title', (int) $id );
				$fixed = \Playform\Feeds\Normalizer::name_brand_case( $title, (string) $old );
				if ( $fixed !== $title ) {
					$wpdb->update( $wpdb->posts, [ 'post_title' => $fixed ], [ 'ID' => (int) $id ] );
					clean_post_cache( (int) $id );
				}
			}
		}
	}
	update_option( 'playform_brand_case_v', '1', false );
}

// Engangsretning 7. nov. 2026: produkter vist "på lager", selvom ingen butik har et levende tilbud på lager.
add_action(
	'init',
	static function () {
		if ( get_option( 'playform_heal_stock_v' ) || ! class_exists( '\\Playform\\Feeds\\ProductSync' ) || ! method_exists( '\\Playform\\Feeds\\ProductSync', 'heal_stock' ) ) {
			return;
		}
		update_option( 'playform_heal_stock_v', 1, false );
		wp_schedule_single_event( time() + 30, 'playform_heal_stock' );
	},
	30
);
add_action( 'playform_heal_stock', static fn() => \Playform\Feeds\ProductSync::heal_stock() );

/**
 * Solgte artikler, der vises i lister ('sponsored' i content/legacy-posts.php), markeres som annonce (_pf_sponsored):
 * markedsføringsloven § 6, stk. 4 – reklame skal tydeligt fremgå (jf. Forbrugerombudsmandens sag mod Obsidian Media, okt. 2026).
 */
add_action(
	'init',
	static function () {
		$file = get_template_directory() . '/content/legacy-posts.php';
		$list = is_readable( $file ) ? (array) require $file : [];
		$want = array_keys( array_filter( $list, static fn( $o ) => ! empty( $o['sponsored'] ) ) );
		$hash = md5( implode( ',', $want ) );
		if ( get_option( 'playform_sponsored_hash' ) === $hash ) {
			return;
		}
		foreach ( $list as $slug => $o ) {
			$p = get_page_by_path( (string) $slug, OBJECT, 'post' );
			if ( $p ) {
				in_array( $slug, $want, true ) ? update_post_meta( $p->ID, '_pf_sponsored', 1 ) : delete_post_meta( $p->ID, '_pf_sponsored' );
			}
		}
		update_option( 'playform_sponsored_hash', $hash, false );
	},
	40
);
