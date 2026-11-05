<?php
/**
 * Kategoriside, "Alle produkter" og produktsøgning.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$term   = get_queried_object();
$is_cat = $term instanceof WP_Term && $term->taxonomy === 'product_cat';
$offers = playform_is_tilbud_page();

// Træningsmaskiner og Styrketræning: oversigtsside med kategorikort i stedet for produkter.
if ( $is_cat && ! $offers && playform_is_virtual_category( $term->slug ) ) {
	get_template_part( 'template-parts/category-overview', null, [ 'term' => $term ] );
	get_footer();
	return;
}

$state  = playform_catalog_state();
$search = get_search_query();

// $top = kategorien, hvis underkategorier vises som faner: den nærmeste (inkl. den selv), der har
// underkategorier. $parent sættes, når vi står på en af dem.
$subcats = class_exists( '\Playform\Feeds\Config' ) ? \Playform\Feeds\Config::subcategories() : [];
$top     = null;
if ( $is_cat ) {
	$top = $term;
	while ( ! isset( $subcats[ $top->slug ] ) && $top->parent ) {
		$up = get_term( $top->parent, 'product_cat' );
		if ( ! $up instanceof WP_Term || playform_is_virtual_category( $up->slug ) ) {
			$top = $term;
			break;
		}
		$top = $up;
	}
}
$parent = $is_cat && $top && $top->term_id !== $term->term_id ? $top : null;
$label  = $is_cat ? playform_cat_label( $term ) : '';
$campaign = playform_current_campaign();
$cnote    = '';
if ( $campaign ) {
	$ct    = playform_campaign_page_text( $campaign );
	$title = $ct['title'];
	$intro = $ct['intro'];
	$cnote = $ct['note'];
} elseif ( $offers ) {
	$title = $is_cat ? 'Tilbud på ' . mb_strtolower( $label ) : 'Tilbud';
	$intro = sprintf(
		'Her er %s, som butikkerne selv har sat på tilbud lige nu. Vi viser kun tilbud, der højst er %d dage gamle, og prisen er butikkens egen – tjek altid den endelige pris hos butikken.',
		$is_cat ? mb_strtolower( $label ) : playform_site( 'niche' ),
		class_exists( '\Playform\Feeds\Sales' ) ? \Playform\Feeds\Sales::DAYS : 14
	);
} elseif ( $is_cat ) {
	$title = $term->name;
	$intro = $term->description ?: sprintf(
		'Se %s fra flere danske webshops samlet ét sted. Priser og lagerstatus opdateres to gange i døgnet, og du handler direkte hos butikken.',
		mb_strtolower( $label )
	);
} elseif ( $search !== '' ) {
	$title = sprintf( 'Søgeresultater for "%s"', $search );
	$intro = '';
} else {
	$title = 'Alle produkter';
	$intro = '';
}

$crumb = [ [ 'Forside', home_url( '/' ) ] ];
if ( $is_cat ) {
	foreach ( array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) as $aid ) {
		$a = get_term( $aid, 'product_cat' );
		if ( $a instanceof WP_Term ) {
			$crumb[] = [ $a->name, get_term_link( $a ) ];
		}
	}
	if ( $offers ) {
		$crumb[] = [ $term->name, get_term_link( $term ) ];
	}
}
$crumb[] = [ $campaign ? playform_campaign_defs()[ $campaign ]['name'] : ( $offers ? 'Tilbud' : $title ), '' ];
$tabs    = $top ? playform_sub_links( $top->slug ) : [];
$sort    = playform_current_sort();
$updated = playform_last_update_label();
$has_offer_tab = $top && in_array( $top->slug, playform_tilbud_slugs(), true );
global $wp_query;
?>
<section class="wrap cat-top">
	<?php playform_breadcrumbs( $crumb ); ?>
	<div class="cat-top__head">
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php if ( $intro ) : ?><p><?php echo esc_html( wp_strip_all_tags( $intro ) ); ?></p><?php endif; ?>
		<?php if ( $cnote ) : ?><p class="campaign-note"><?php echo esc_html( $cnote ); ?></p><?php endif; ?>
	</div>
	<?php if ( $tabs || $has_offer_tab ) : ?>
		<nav class="tabs" aria-label="Underkategorier">
			<?php $on = ! $parent && ! $offers; ?>
			<a href="<?php echo esc_url( get_term_link( $top ) ); ?>" class="tab<?php echo $on ? ' is-active' : ''; ?>"<?php echo $on ? ' aria-current="page"' : ''; ?>>Alle <?php echo esc_html( mb_strtolower( playform_cat_label( $top ) ) ); ?><span class="tab__n"><?php echo (int) ( $on ? $state['total'] : $top->count ); ?></span></a>
			<?php foreach ( $tabs as [ $tlabel, $args, $count ] ) : ?>
				<?php $on = $parent && ! $offers && $term->slug === $args['sub']; ?>
				<a href="<?php echo esc_url( playform_category_url( $top->slug, $args ) ); ?>" class="tab<?php echo $on ? ' is-active' : ''; ?>"<?php echo $on ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tlabel ); ?><span class="tab__n"><?php echo (int) $count; ?></span></a>
			<?php endforeach; ?>
			<?php if ( $has_offer_tab ) : ?>
				<?php $on = $offers && $term->term_id === $top->term_id; ?>
				<a href="<?php echo esc_url( playform_tilbud_url( $top ) ); ?>" class="tab tab--sale<?php echo $on ? ' is-active' : ''; ?>"<?php echo $on ? ' aria-current="page"' : ''; ?>>Tilbud<?php echo $on ? '<span class="tab__n">' . (int) $state['total'] . '</span>' : ''; ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
	<?php
	// Sider på tværs af kategorier (Tilbud, Alle produkter): kategorierne som runde faner, der filtrerer på siden.
	$kat = ( ! $is_cat && '' === $search && ! empty( $state['facets']['kat'] ) ) ? $state['facets']['kat']['options'] : [];
	if ( $kat ) :
		$kat_on = array_values( array_filter( $kat, static fn( $o ) => $o['checked'] ) );
		?>
		<nav class="tabs" aria-label="Kategorier">
			<a href="<?php echo esc_url( remove_query_arg( [ 'kat', 'paged' ] ) ); ?>" class="tab<?php echo $kat_on ? '' : ' is-active'; ?>"<?php echo $kat_on ? '' : ' aria-current="page"'; ?>><?php echo $offers ? 'Alle tilbud' : 'Alle produkter'; ?><?php echo $kat_on ? '' : '<span class="tab__n">' . (int) $state['total'] . '</span>'; ?></a>
			<?php foreach ( $kat as $o ) : ?>
				<a href="<?php echo esc_url( $o['checked'] ? remove_query_arg( [ 'kat', 'paged' ] ) : add_query_arg( 'kat', $o['key'], remove_query_arg( 'paged' ) ) ); ?>" class="tab<?php echo $o['checked'] ? ' is-active' : ''; ?>" rel="nofollow"<?php echo $o['checked'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $o['label'] ); ?><span class="tab__n"><?php echo (int) $o['count']; ?></span></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</section>

<section class="wrap catalog">
	<div class="layout">
		<aside class="filters" aria-label="Filtre">
			<details class="filters__mobile" open>
				<summary class="filters__head">
					<span>Filtrer</span>
					<?php if ( $state['tags'] ) : ?><a href="<?php echo esc_url( playform_reset_url() ); ?>">Nulstil</a><?php endif; ?>
				</summary>
				<?php foreach ( $state['facets'] as $g => $facet ) : ?>
					<?php if ( 'kat' === $g ) { continue; } // kategorierne står som faner øverst ?>
					<details class="fsec"<?php echo in_array( $g, [ 'brugervaegt' ], true ) && empty( playform_active_filters()[ $g ] ) ? '' : ' open'; ?>>
						<summary><?php echo esc_html( $facet['label'] ); ?><?php echo playform_icon( 'chevron', 16 ); // phpcs:ignore ?></summary>
						<div class="fsec__opts">
							<?php
							// Lange lister (mærker): vis de største + valgte, resten bag "Vis flere".
							$cut   = ! empty( $facet['collapse'] ) && count( $facet['options'] ) > $facet['collapse'] + 2 ? $facet['collapse'] : 0;
							$extra = 0;
							foreach ( $facet['options'] as $oi => $o ) :
								if ( $cut && $oi >= $cut && ! $o['checked'] ) {
									++$extra;
									continue;
								}
							?>
								<a class="opt<?php echo $o['checked'] ? ' is-checked' : ''; ?>" href="<?php echo esc_url( playform_filter_toggle_url( $g, $o['key'] ) ); ?>" rel="nofollow" role="checkbox" aria-checked="<?php echo $o['checked'] ? 'true' : 'false'; ?>">
									<span class="opt__box"><?php echo $o['checked'] ? playform_icon( 'check', 14, 2.2 ) : ''; // phpcs:ignore ?></span>
									<?php echo esc_html( $o['label'] ); ?>
									<span class="opt__n"><?php echo (int) $o['count']; ?></span>
								</a>
							<?php endforeach; ?>
							<?php if ( $extra ) : ?>
								<details class="fmore">
									<summary><span class="fmore__more">Vis flere (<?php echo (int) $extra; ?>)</span><span class="fmore__less">Vis færre</span></summary>
									<?php foreach ( $facet['options'] as $oi => $o ) : if ( $oi < $cut || $o['checked'] ) { continue; } ?>
										<a class="opt" href="<?php echo esc_url( playform_filter_toggle_url( $g, $o['key'] ) ); ?>" rel="nofollow" role="checkbox" aria-checked="false">
											<span class="opt__box"></span>
											<?php echo esc_html( $o['label'] ); ?>
											<span class="opt__n"><?php echo (int) $o['count']; ?></span>
										</a>
									<?php endforeach; ?>
								</details>
							<?php endif; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</details>
		</aside>

		<div class="results">
			<p class="adline"><b>Annonce</b><span>Siden indeholder reklamelinks. Vi får provision, hvis du handler hos en butik via dem. <a href="<?php echo esc_url( home_url( '/saadan-tjener-vi-penge/' ) ); ?>">Sådan tjener vi penge</a></span></p>

			<div class="results__bar">
				<button type="button" class="filterbtn" data-filter-btn aria-expanded="false"><?php echo playform_icon( 'filter', 16, 1.8 ); // phpcs:ignore ?>Filtrer<?php $n_act = count( array_filter( $state['tags'], static fn( $t ) => ! str_starts_with( $t['label'], '"' ) ) ); echo $n_act ? '<span class="filterbtn__n">' . (int) $n_act . '</span>' : ''; ?></button>
				<span class="results__count"><span class="results__n"><b><?php echo (int) $state['total']; ?></b> produkter</span><?php echo $updated ? ' <span class="muted"><i class="dot">·</i> Priser opdateret ' . esc_html( $updated ) . '</span>' : ''; ?></span>
				<form method="get" class="sort">
					<?php foreach ( $_GET as $k => $v ) : // phpcs:ignore ?>
						<?php if ( $k !== 'sortering' && $k !== 'paged' && ! is_array( $v ) ) : ?><input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( wp_unslash( $v ) ); ?>"><?php endif; ?>
					<?php endforeach; ?>
					<label for="sortering">Sortér efter</label>
					<span class="select">
						<select id="sortering" name="sortering" data-autosubmit>
							<?php foreach ( [ 'populaer' => 'Mest populære', 'rabat' => 'Største rabat', 'pris-lav' => 'Laveste pris', 'pris-hoj' => 'Højeste pris', 'nyeste' => 'Nyeste' ] as $k => $slabel ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $sort, $k ); ?>><?php echo esc_html( $slabel ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php echo playform_icon( 'chevron', 14, 1.8 ); // phpcs:ignore ?>
					</span>
					<noscript><button type="submit">Sortér</button></noscript>
				</form>
			</div>

			<?php if ( $state['tags'] ) : ?>
				<div class="tags">
					<?php foreach ( $state['tags'] as $t ) : ?>
						<a class="tag" href="<?php echo esc_url( $t['url'] ); ?>" rel="nofollow" aria-label="<?php echo esc_attr( 'Fjern filter ' . $t['label'] ); ?>"><?php echo esc_html( $t['label'] ); ?><?php echo playform_icon( 'close', 12, 2 ); // phpcs:ignore ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>


			<?php if ( have_posts() ) : ?>
				<div class="grid">
					<?php
					while ( have_posts() ) {
						the_post();
						$p = wc_get_product( get_the_ID() );
						if ( $p ) {
							playform_product_card( $p );
						}
					}
					?>
				</div>
				<?php
				$pages = (int) $wp_query->max_num_pages;
				$cur   = max( 1, (int) get_query_var( 'paged' ) );
				if ( $pages > 1 ) :
					?>
					<nav class="pager" aria-label="Sider">
						<div class="pager__row">
							<?php if ( $cur > 1 ) : ?><a class="pg" href="<?php echo esc_url( get_pagenum_link( $cur - 1 ) ); ?>" aria-label="Forrige side"><?php echo playform_icon( 'arrow-left', 16 ); // phpcs:ignore ?></a><?php endif; ?>
							<?php
							$window = array_unique( array_filter( [ 1, $cur - 1, $cur, $cur + 1, $pages ], static fn( $n ) => $n >= 1 && $n <= $pages ) );
							sort( $window );
							$prev = 0;
							foreach ( $window as $n ) {
								if ( $prev && $n > $prev + 1 ) {
									echo '<span class="pg pg--gap">…</span>';
								}
								printf( '<a class="pg%s" href="%s"%s>%d</a>', $n === $cur ? ' is-on' : '', esc_url( get_pagenum_link( $n ) ), $n === $cur ? ' aria-current="page"' : '', $n ); // phpcs:ignore
								$prev = $n;
							}
							?>
							<?php if ( $cur < $pages ) : ?><a class="pg" href="<?php echo esc_url( get_pagenum_link( $cur + 1 ) ); ?>" aria-label="Næste side"><?php echo playform_icon( 'arrow-right', 16 ); // phpcs:ignore ?></a><?php endif; ?>
						</div>
						<span class="muted">Side <?php echo (int) $cur; ?> af <?php echo (int) $pages; ?> · <?php echo (int) PLAYFORM_PER_PAGE; ?> produkter pr. side</span>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<div class="empty">
					<?php if ( $offers && ! $state['tags'] ) : ?>
						<b>Ingen tilbud<?php echo $is_cat ? ' på ' . esc_html( mb_strtolower( $label ) ) : ''; ?> lige nu</b>
						<span>Vi viser kun tilbud, som butikkerne selv har markeret, og som højst er <?php echo (int) ( class_exists( '\Playform\Feeds\Sales' ) ? \Playform\Feeds\Sales::DAYS : 14 ); ?> dage gamle. Kig forbi igen – priserne opdateres to gange i døgnet.</span>
						<?php if ( $is_cat ) : ?><a class="btn" href="<?php echo esc_url( get_term_link( $term ) ); ?>">Se alle <?php echo esc_html( mb_strtolower( $label ) ); ?></a><?php endif; ?>
					<?php elseif ( in_array( 'tilbud', playform_active_filters()['egenskab'] ?? [], true ) ) : ?>
						<b>Ingen tilbud matcher dine filtre</b>
						<span>Vi viser kun tilbud, som butikkerne selv har markeret, og som højst er 14 dage gamle. Prøv at fjerne et filter.</span>
						<a class="btn" href="<?php echo esc_url( playform_reset_url() ); ?>">Nulstil filtre</a>
					<?php else : ?>
					<?php elseif ( function_exists( 'playform_active_filters' ) && playform_active_filters() ) : ?>
						<b>Ingen produkter matcher dine filtre</b>
						<span>Prøv at fjerne et eller flere filtre.</span>
						<a class="btn" href="<?php echo esc_url( playform_reset_url() ); ?>">Nulstil filtre</a>
					<?php else : ?>
						<b>Ingen produkter i kategorien lige nu</b>
						<span>Butikkerne har ikke varer på lager her i øjeblikket. Udvalget opdateres to gange i døgnet – kig forbi igen.</span>
						<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Til forsiden</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			if ( $campaign && max( 1, (int) get_query_var( 'paged' ) ) === 1 ) {
				get_template_part( 'template-parts/seo-text', null, [ 'html' => playform_campaign_seo( $campaign ) ] );
			}
			if ( $is_cat && ! $offers ) { // tilbudssider gentager ikke kategoriens tekst
				$faq = get_term_meta( $term->term_id, 'pf_faq', true );
				if ( is_array( $faq ) && $faq && max( 1, (int) get_query_var( 'paged' ) ) === 1 ) {
					echo '<div class="faq-wrap"><h2>Ofte stillede spørgsmål om ' . esc_html( mb_strtolower( $term->name ) ) . '</h2>';
					foreach ( $faq as [ $q, $a ] ) {
						echo '<details class="faq"><summary>' . esc_html( $q ) . playform_icon( 'plus', 18 ) . '</summary><p>' . esc_html( $a ) . '</p></details>'; // phpcs:ignore
					}
					echo '</div>';
					playform_faq_schema( $faq );
				}
				$seo = get_term_meta( $term->term_id, 'pf_seo_text', true );
				if ( $seo && max( 1, (int) get_query_var( 'paged' ) ) === 1 ) {
					get_template_part( 'template-parts/seo-text', null, [ 'html' => $seo ] );
				}
			}
			if ( $is_cat && max( 1, (int) get_query_var( 'paged' ) ) === 1 ) {
				playform_related_block( $term, $offers ? 'Kig videre' : 'Mere om ' . mb_strtolower( $label ) );
			}
			?>
		</div>
	</div>
</section>
<?php
get_footer();
