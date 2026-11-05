<?php
/**
 * Oversigtsside for en grupperingskategori (Træningsmaskiner, Styrketræning):
 * kategorikort som på forsiden, populære produkter og SEO-tekst/FAQ.
 */
defined( 'ABSPATH' ) || exit;

$term = $args['term'];
$item = null;
foreach ( playform_menu() as $m ) {
	if ( $m['slug'] === $term->slug ) {
		$item = $m;
	}
}
$cards = $item ? $item['primary'] : [];

/** Populære produkter i én kategori: på lager, med billede og over 300 kr, blandet og caches i 6 timer. */
$popular = static function ( string $slug ): array {
	$key = 'playform_overview_' . $slug . '_' . PLAYFORM_THEME_VERSION;
	$ids = get_transient( $key );
	if ( $ids === false ) {
		$ids = get_posts(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 12,
				'no_found_rows'  => true,
				'orderby'        => 'rand',
				'tax_query'      => [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [ $slug ] ] ], // phpcs:ignore
				'meta_query'     => [ // phpcs:ignore
					[ 'key' => '_pf_in_stock', 'value' => 1, 'type' => 'NUMERIC' ],
					[ 'key' => '_pf_image', 'value' => '', 'compare' => '!=' ],
					[ 'key' => '_price', 'value' => 300, 'compare' => '>=', 'type' => 'NUMERIC' ],
				],
			]
		);
		set_transient( $key, $ids, 6 * HOUR_IN_SECONDS );
	}
	return array_filter( array_map( 'wc_get_product', $ids ) );
};
?>
<section class="wrap cat-top">
	<?php playform_breadcrumbs( [ [ 'Forside', home_url( '/' ) ], [ $term->name, '' ] ] ); ?>
	<div class="cat-top__head">
		<h1><?php echo esc_html( $term->name ); ?></h1>
		<?php if ( $term->description ) : ?><p><?php echo esc_html( wp_strip_all_tags( $term->description ) ); ?></p><?php endif; ?>
	</div>
</section>

<section class="wrap overview<?php echo count( $cards ) === 2 ? ' overview--2' : ''; ?>">
	<?php foreach ( $cards as $c ) : ?>
		<div class="cat">
			<?php $cimg = playform_category_image( $c['slug'] ); ?><img class="cat__img" src="<?php echo esc_url( $cimg ); ?>"<?php echo playform_srcset( $cimg, '(max-width: 760px) 100vw, 33vw' ); // phpcs:ignore ?> alt="" loading="lazy">
			<span class="cat__shade"></span>
			<a class="cat__main" href="<?php echo esc_url( $c['url'] ); ?>" aria-label="<?php echo esc_attr( $c['label'] ); ?>"></a>
			<div class="cat__body">
				<span class="cat__title"><?php echo esc_html( $c['label'] ); ?></span>
				<?php if ( $c['subs'] ) : ?>
					<nav class="cat__subs" aria-label="<?php echo esc_attr( 'Underkategorier til ' . $c['label'] ); ?>">
						<?php foreach ( $c['subs'] as [ $label, $url ] ) : ?>
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
			</div>
			<span class="cat__arrow" aria-hidden="true"><?php echo playform_icon( 'arrow-right', 20, 1.4 ); // phpcs:ignore ?></span>
		</div>
	<?php endforeach; ?>
</section>

<?php
// Én karrusel pr. kategori: Populære løbebånd, Populære motionscykler …
foreach ( $cards as $c ) {
	$products = $popular( $c['slug'] );
	if ( count( $products ) >= 4 ) {
		get_template_part(
			'template-parts/slider',
			null,
			[
				'title'      => 'Populære ' . mb_strtolower( $c['label'] ),
				'products'   => $products,
				'id'         => 'slider-' . $c['slug'],
				'link'       => $c['url'],
				'link_label' => 'Se alle',
			]
		);
	}
}
?>

<section class="wrap catalog catalog--right">
	<?php
	$faq = get_term_meta( $term->term_id, 'pf_faq', true );
	if ( is_array( $faq ) && $faq ) {
		echo '<div class="faq-wrap"><h2>Ofte stillede spørgsmål om ' . esc_html( mb_strtolower( $term->name ) ) . '</h2>';
		foreach ( $faq as [ $q, $a ] ) {
			echo '<details class="faq"><summary>' . esc_html( $q ) . playform_icon( 'plus', 18 ) . '</summary><p>' . esc_html( $a ) . '</p></details>'; // phpcs:ignore
		}
		echo '</div>';
		playform_faq_schema( $faq );
	}
	$seo = get_term_meta( $term->term_id, 'pf_seo_text', true );
	if ( $seo ) {
		get_template_part( 'template-parts/seo-text', null, [ 'html' => $seo ] );
	}
	?>
</section>
