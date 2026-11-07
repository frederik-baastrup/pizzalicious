<?php
/**
 * Interne links: hver kategori-, produkt- og guideside peger videre til de nærmeste relevante sider
 * (relaterede kategorier, købsguides, tilbud). Hjælper både besøgende og Googles forståelse af sidens opbygning.
 */

defined( 'ABSPATH' ) || exit;

/** Kategorier tæt på denne: søskende (samme forælder) – eller underkategorier – og ellers andre menupunkter. */
function playform_related_cats( WP_Term $term, int $max = 6 ): array {
	$out = [];
	$add = static function ( WP_Term $t ) use ( &$out, $term ) {
		if ( $t->term_id !== $term->term_id && $t->count > 0 && 'ukategoriseret' !== $t->slug && ! isset( $out[ $t->term_id ] ) ) {
			$out[ $t->term_id ] = $t;
		}
	};
	foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ] ) as $t ) {
		$add( $t );
	}
	if ( $term->parent ) {
		foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'parent' => $term->parent, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ] ) as $t ) {
			$add( $t );
		}
		$parent = get_term( $term->parent, 'product_cat' );
		if ( $parent instanceof WP_Term ) {
			$add( $parent );
		}
	}
	if ( count( $out ) < 4 ) { // fx en hovedkategori: foreslå de andre menupunkter
		foreach ( playform_menu_spec() as [ $slug ] ) {
			$t = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $t instanceof WP_Term ) {
				$add( $t );
			}
		}
	}
	return array_slice( array_values( $out ), 0, $max );
}

/** Købsguides, der hører til kategorien (eller en af dens under-/overkategorier). */
function playform_guides_for_term( WP_Term $term, int $max = 4 ): array {
	$slugs = [ $term->slug ];
	foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'child_of' => $term->term_id, 'fields' => 'slugs', 'hide_empty' => false ] ) as $s ) {
		$slugs[] = $s;
	}
	if ( $term->parent ) {
		$p = get_term( $term->parent, 'product_cat' );
		if ( $p instanceof WP_Term ) {
			$slugs[] = $p->slug;
		}
	}
	$key = 'playform_guides_' . md5( implode( ',', $slugs ) );
	$ids = get_transient( $key );
	if ( false === $ids ) {
		$ids = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $max,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [ [ 'key' => '_pf_more_cat', 'value' => $slugs, 'compare' => 'IN' ] ], // phpcs:ignore
			]
		);
		set_transient( $key, $ids, HOUR_IN_SECONDS * 6 );
	}
	return array_map( 'get_post', (array) $ids );
}

/** Blokken "Læs også / Kig videre" med op til tre lister. */
function playform_related_block( WP_Term $term, string $heading = '' ): void {
	$label  = mb_strtolower( playform_cat_label( $term ) );
	$cats   = playform_related_cats( $term );
	$guides = playform_guides_for_term( $term );
	$tilbud = in_array( $term->slug, playform_tilbud_slugs(), true );
	if ( ! $cats && ! $guides && ! $tilbud ) {
		return;
	}
	?>
	<nav class="rel" aria-label="<?php echo esc_attr( 'Mere om ' . $label ); ?>">
		<h2 class="rel__title"><?php echo esc_html( $heading ?: 'Kig videre' ); ?></h2>
		<div class="rel__grid">
			<?php if ( $guides ) : ?>
				<div class="rel__col">
					<span class="rel__h">Købsguides</span>
					<?php foreach ( $guides as $g ) : ?>
						<a href="<?php echo esc_url( get_permalink( $g ) ); ?>"><?php echo esc_html( preg_replace( '/:.*$/u', '', get_the_title( $g ) ) ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $cats ) : ?>
				<div class="rel__col">
					<span class="rel__h">Se også</span>
					<?php foreach ( $cats as $c ) : ?>
						<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( playform_cat_label( $c ) ); ?> <small><?php echo (int) $c->count; ?></small></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $tilbud ) : ?>
				<div class="rel__col">
					<span class="rel__h">Tilbud</span>
					<a href="<?php echo esc_url( playform_tilbud_url( $term ) ); ?>">Tilbud på <?php echo esc_html( $label ); ?></a>
					<a href="<?php echo esc_url( playform_tilbud_url() ); ?>">Alle butikkernes tilbud</a>
				</div>
			<?php endif; ?>
		</div>
	</nav>
	<?php
}

/** Produktets mest specifikke kategori. */
function playform_product_term( int $product_id ): ?WP_Term {
	$best = null;
	foreach ( (array) get_the_terms( $product_id, 'product_cat' ) as $t ) {
		if ( $t instanceof WP_Term && 'ukategoriseret' !== $t->slug && ( ! $best || $t->parent ) ) {
			$best = $t;
		}
	}
	return $best;
}

// Under købsguider: kig videre til kategorien og dens tilbud.
add_filter(
	'the_content',
	static function ( string $html ): string {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $html;
		}
		$slug = (string) get_post_meta( get_the_ID(), '_pf_more_cat', true );
		$term = $slug ? get_term_by( 'slug', $slug, 'product_cat' ) : null;
		if ( ! $term instanceof WP_Term ) {
			return $html;
		}
		ob_start();
		playform_related_block( $term, 'Kig videre' );
		return $html . ob_get_clean();
	},
	20
);

// Nulstil guide-listerne, når en guide ændres.
add_action(
	'save_post_post',
	static function () {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_playform\_guides\_%' OR option_name LIKE '\_transient\_timeout\_playform\_guides\_%'" ); // phpcs:ignore
	}
);
