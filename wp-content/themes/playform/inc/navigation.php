<?php
/**
 * Hovedmenu og mega-menu.
 *
 * Alle menupunkter har samme panel: Kategorier (venstre) | underkategorier for den markerede (midt) | Pris (højre).
 * Grupperingskategorier (playform_virtual_groups()) samler rigtige kategorier og har en oversigtsside
 * i stedet for et produktgitter. Fitbase bruger dem (Træningsmaskiner, Styrketræning); Kitzhen har ingen.
 */

defined( 'ABSPATH' ) || exit;

/** Grupperingskategorier og de rigtige kategorier, de samler (bruges også af opsætningen). */
function playform_virtual_groups(): array {
	return [];
}

/** Er det en grupperingskategori (oversigtsside uden egne produkter)? */
function playform_is_virtual_category( string $slug ): bool {
	return isset( playform_virtual_groups()[ $slug ] );
}

function playform_category_url( string $slug, array $args = [] ): string {
	if ( isset( $args['sub'] ) ) {
		$slug = $args['sub'];
		unset( $args['sub'] );
	}
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	$url  = $term ? get_term_link( $term ) : home_url( '/' );
	if ( is_wp_error( $url ) ) {
		$url = home_url( '/' );
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

/** Underkategorier med produkter: [ [ navn, [ 'sub' => slug ], antal ], … ] */
function playform_sub_links( string $parent ): array {
	$out  = [];
	$defs = class_exists( '\Playform\Feeds\Config' ) ? ( \Playform\Feeds\Config::subcategories()[ $parent ] ?? [] ) : [];
	foreach ( $defs as $slug => $def ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term && $term->count > 0 ) {
			$out[] = [ $term->name, [ 'sub' => $slug ], (int) $term->count ];
		}
	}
	return $out;
}

/** Den øverste kategori (menupunktet) for en kategori-slug. */
function playform_root_slug( string $slug ): string {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term ) {
		return '';
	}
	foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $id ) {
		$a = get_term( $id, 'product_cat' );
		if ( $a instanceof WP_Term ) {
			$term = $a;
		}
	}
	return $term->slug;
}

/**
 * Prisintervaller med produkter i en kategori (inkl. underkategorier). Caches, da de kræver en optælling.
 *
 * @return array<int,array{0:string,1:string}> [ [ label, nøgle til ?pris= ], … ]
 */
function playform_price_ranges( WP_Term $term ): array {
	$ranges = playform_price_bands();
	$all = get_transient( 'playform_price_ranges' );
	if ( ! is_array( $all ) ) {
		$all = [];
	}
	if ( ! isset( $all[ $term->term_id ] ) ) {
		global $wpdb;
		$ids   = array_merge( [ $term->term_id ], array_map( 'intval', (array) get_term_children( $term->term_id, 'product_cat' ) ) );
		$tt    = array_map( 'intval', (array) get_terms( [ 'taxonomy' => 'product_cat', 'include' => $ids, 'hide_empty' => false, 'fields' => 'tt_ids' ] ) );
		$found = [];
		if ( $tt ) {
			$in    = implode( ',', $tt );
			$cases = [];
			$i = 0;
			foreach ( $ranges as [ , $min, $max ] ) {
				$cases[] = $wpdb->prepare( 'SUM(CASE WHEN pm.meta_value + 0 >= %d AND pm.meta_value + 0 < %d THEN 1 ELSE 0 END)', $min, min( $max, 999999999 ) ) . ' AS r' . $i++;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				'SELECT ' . implode( ', ', $cases ) . " FROM (SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr WHERE tr.term_taxonomy_id IN ($in)) x
				JOIN {$wpdb->posts} p ON p.ID = x.object_id AND p.post_status = 'publish'
				JOIN {$wpdb->postmeta} pm ON pm.post_id = x.object_id AND pm.meta_key = '_price'",
				ARRAY_A
			);
			$i = 0;
			foreach ( array_keys( $ranges ) as $key ) {
				if ( (int) ( $row[ 'r' . $i++ ] ?? 0 ) > 0 ) {
					$found[] = $key;
				}
			}
		}
		$all[ $term->term_id ] = $found;
		set_transient( 'playform_price_ranges', $all, 12 * HOUR_IN_SECONDS );
	}
	$out = [];
	foreach ( $all[ $term->term_id ] as $key ) {
		$out[] = [ $ranges[ $key ][0], $key ];
	}
	return $out;
}

/** Billede til oversigtssidernes kategorikort. */
function playform_category_image( string $slug, string $fallback = '' ): string {
	// Billeder lægges i assets/img/cat-<slug>.jpg (og th-<slug>.jpg til mobilmenuen). Mangler det, bruges forælderens
	// og til sidst en neutral pladsholder.
	foreach ( [ $slug, $fallback ] as $s ) {
		if ( $s !== '' && is_file( get_template_directory() . "/assets/img/cat-$s.jpg" ) ) {
			return playform_asset( "cat-$s.jpg" );
		}
	}
	return playform_asset( 'cat-placeholder.svg' );
}

/** Lille, kvadratisk udgave af kategoribilledet til mobilmenuen (assets/img/th-*.jpg). Tom, hvis kategorien ikke har sit eget. */
function playform_category_thumb( string $slug ): string {
	return is_file( get_template_directory() . "/assets/img/th-$slug.jpg" ) ? playform_asset( "th-$slug.jpg" ) : '';
}

/**
 * Menuens opbygning: [ menupunkt, kategorier i venstre kolonne ]. null = menupunktets egne underkategorier.
 * En kategori kan angives som [ slug, visningsnavn ], fx når en underkategori står for sig selv.
 */
function playform_menu_spec(): array {
	return [
		[ 'pizzaovne', null ],
		[ 'pizzasten', null ],
		[ 'pizzaspader', null ],
		[ 'pizzaskaerere', null ],
		[ 'dej', null ],
		[ 'tilbehoer', null ],
		[ 'ingredienser', null ],
	];
}

/** Visningsnavne fra menuen (fx "Romaskiner" for kategorien "Romaskine"), slug => navn. */
function playform_menu_labels(): array {
	// Kategorierne hedder ental (som folk søger), men i menu, faner og "Tilbud på …" læser flertal bedst.
	$out = [
		// Hovedkategorier: korte navne i topmenuen.
		'pizzaovne'          => 'Pizzaovne',
		'pizzasten'          => 'Pizzasten & bagestål',
		'pizzaspader'        => 'Pizzaspader',
		'pizzaskaerere'      => 'Pizzaskærere',
		'dej'                => 'Dej & æltning',
		'tilbehoer'          => 'Tilbehør',
		'ingredienser'       => 'Ingredienser',
		// Underkategorier i flertal.
		'gas-pizzaovn'       => 'Gas pizzaovne',
		'pizzaovn-traepiller' => 'Pizzaovne til træpiller',
		'pizzaovn-til-koekkenet' => 'Pizzaovne til køkkenet',
		'elektrisk-pizzaovn' => 'Elektriske pizzaovne',
		'pizzaovn-til-grill' => 'Pizzaovne til grill',
		'pizzasten-sten'     => 'Pizzasten',
		'bagestaal'          => 'Bagestål',
		'pizzapande'         => 'Pizzapander & -forme',
		'aeltemaskine'       => 'Æltemaskiner',
		'dejkasse'           => 'Dejkasser',
		'pizzamel'           => 'Pizzamel',
		'tomater'            => 'Tomater & pizzasauce',
	];
	foreach ( playform_menu_spec() as [ $slug, $cats ] ) {
		if ( isset( playform_virtual_groups()[ $slug ] ) ) {
			$out[ $slug ] = playform_virtual_groups()[ $slug ]['label'];
		}
		foreach ( (array) $cats as $c ) {
			if ( is_array( $c ) ) {
				$out[ $c[0] ] = $c[1];
			}
		}
	}
	return $out;
}

/** Kategoriens navn, som det står i menuen. */
function playform_cat_label( WP_Term $term ): string {
	return playform_menu_labels()[ $term->slug ] ?? wp_specialchars_decode( $term->name );
}

/** Kategorier, der har en "Tilbud på …"-side: alle menupunkter og kategorierne i deres venstre kolonne. */
function playform_tilbud_slugs(): array {
	$out = [];
	foreach ( playform_menu_spec() as [ $slug, $cats ] ) {
		$out[] = $slug;
		if ( $cats === null ) {
			$cats = class_exists( '\Playform\Feeds\Config' ) ? array_keys( \Playform\Feeds\Config::subcategories()[ $slug ] ?? [] ) : [];
		}
		foreach ( $cats as $c ) {
			$out[] = is_array( $c ) ? $c[0] : $c;
		}
	}
	return $out;
}

/** URL til "Tilbud på …" for en kategori (eller alle tilbud). */
function playform_tilbud_url( ?WP_Term $term = null ): string {
	return $term ? trailingslashit( get_term_link( $term ) ) . user_trailingslashit( 'tilbud' ) : home_url( user_trailingslashit( 'tilbud' ) );
}

/** Står vi på en tilbudsside (/…/tilbud/ eller /tilbud/)? */
function playform_is_tilbud_page(): bool {
	return (bool) get_query_var( 'pf_tilbud' );
}

/**
 * Topmenuen. Hvert punkt: label, slug, url og 'primary' = kategorierne i venstre kolonne. Hver af dem har
 * 'subs' (midterkolonnen), 'prices' (højre kolonne) og 'tilbud' (link til Tilbud på …).
 *
 * @return array<int,array>
 */
function playform_menu(): array {
	static $menu = null;
	if ( $menu !== null ) {
		return $menu;
	}
	$menu = [];
	foreach ( playform_menu_spec() as [ $slug, $cats ] ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term ) {
			continue;
		}
		if ( $cats === null ) {
			$cats = array_map( static fn( $s ) => $s[1]['sub'], playform_sub_links( $slug ) );
			// Ingen underkategorier (fx Pizzaskærere): vis kategorien selv, så menupanelet ikke står tomt.
			$cats = $cats ?: [ $slug ];
		}
		$primary = [];
		foreach ( $cats as $c ) {
			$cslug = is_array( $c ) ? $c[0] : $c;
			$ct    = get_term_by( 'slug', $cslug, 'product_cat' );
			if ( ! $ct || $ct->count < 1 ) {
				continue;
			}
			$subs = [];
			foreach ( playform_sub_links( $cslug ) as [ $sl, $args ] ) {
				$subs[] = [ $sl, playform_category_url( $args['sub'] ) ];
			}
			$prices = [];
			foreach ( playform_price_ranges( $ct ) as [ $pl, $key ] ) {
				$prices[] = [ $pl, playform_category_url( $cslug, [ 'pris' => $key ] ) ];
			}
			$primary[] = [
				'label'  => playform_cat_label( $ct ),
				'slug'   => $cslug,
				'url'    => playform_category_url( $cslug ),
				'tilbud' => playform_tilbud_url( $ct ),
				'img'    => playform_category_image( $cslug, $slug ),
				'subs'   => $subs,
				'prices' => $prices,
			];
		}
		$menu[] = [
			'label'   => playform_cat_label( $term ),
			'slug'    => $slug,
			'url'     => playform_category_url( $slug ),
			'tilbud'  => playform_tilbud_url( $term ),
			'primary' => $primary,
		];
	}
	return $menu;
}

/** Populære kategorier, der vises i søgefeltet, før man har skrevet noget. */
function playform_search_shortcuts(): array {
	$out = [];
	foreach ( [ 'gas-pizzaovn', 'elektrisk-pizzaovn', 'pizzasten-sten', 'bagestaal', 'pizzaspader', 'pizzaskaerere', 'aeltemaskine', 'pizzamel' ] as $slug ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $t && $t->count > 0 ) {
			$out[] = [ $t->name, get_term_link( $t ) ];
		}
	}
	return $out;
}

/** "Populære" i sidefoden: [ visningsnavn, kategori-slug ]. */
function playform_footer_popular(): array {
	return [
		[ 'Gas pizzaovne', 'gas-pizzaovn' ],
		[ 'Elektriske pizzaovne', 'elektrisk-pizzaovn' ],
		[ 'Pizzasten', 'pizzasten-sten' ],
		[ 'Bagestål', 'bagestaal' ],
		[ 'Pizzaspader', 'pizzaspader' ],
	];
}
