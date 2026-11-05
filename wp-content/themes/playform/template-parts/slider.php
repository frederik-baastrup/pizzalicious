<?php
/**
 * Produkt-slider. Forventer $args: title, products (WC_Product[]), id, link (valgfri).
 */
defined( 'ABSPATH' ) || exit;
if ( empty( $args['products'] ) ) {
	return;
}
?>
<section class="wrap section">
	<div class="section__head">
		<h2><?php echo esc_html( $args['title'] ); ?></h2>
		<span class="section__line"></span>
		<?php if ( ! empty( $args['link'] ) ) : ?><a class="section__more" href="<?php echo esc_url( $args['link'] ); ?>"><?php
			$label = (string) ( $args['link_label'] ?? 'Se alle' );
			// "Se alle espressomaskiner": på mobil står kun "Se alle", så overskriften kan være der.
			echo str_starts_with( $label, 'Se alle ' ) ? 'Se alle<span class="section__more-x"> ' . esc_html( substr( $label, 8 ) ) . '</span>' : esc_html( $label ); // phpcs:ignore
			?></a><?php endif; ?>
		<div class="slider__arrows" data-slider-arrows="<?php echo esc_attr( $args['id'] ); ?>">
			<button type="button" class="round" data-dir="-1" aria-label="Forrige"><?php echo playform_icon( 'arrow-left', 18 ); // phpcs:ignore ?></button>
			<button type="button" class="round" data-dir="1" aria-label="Næste"><?php echo playform_icon( 'arrow-right', 18 ); // phpcs:ignore ?></button>
		</div>
	</div>
	<div class="slider" id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php foreach ( $args['products'] as $p ) : ?>
			<?php playform_product_card( $p, 'pcard slider__item' ); ?>
		<?php endforeach; ?>
	</div>
</section>
