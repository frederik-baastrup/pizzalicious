<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<section class="wrap page-wrap prose">
	<?php if ( is_home() ) : ?>
		<h1>Viden &amp; guides</h1>
	<?php endif; ?>
	<?php
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			if ( is_singular() ) {
				the_title( '<h1>', '</h1>' );
				the_content();
			} else {
				echo '<h2><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2>';
				the_excerpt();
			}
		}
	} else {
		echo '<p class="muted">Der er ingen indlæg endnu.</p>';
	}
	?>
</section>
<?php
get_footer();
