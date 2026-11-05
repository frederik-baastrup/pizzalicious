<?php
/**
 * Foldbar SEO-tekst. $args: html, right (bool – 3/4 bredde i højre side).
 */
defined( 'ABSPATH' ) || exit;
if ( empty( $args['html'] ) ) {
	return;
}
?>
<section class="<?php echo ! empty( $args['right'] ) ? 'wrap section seo-wrap' : 'seo-inline'; ?>">
	<div class="seo<?php echo ! empty( $args['right'] ) ? ' seo--right' : ''; ?>">
		<div class="seo__text is-collapsed" data-seo>
			<?php echo wp_kses_post( $args['html'] ); ?>
		</div>
		<button type="button" class="btn-ghost" data-seo-toggle aria-expanded="false">Læs mere</button>
	</div>
</section>
