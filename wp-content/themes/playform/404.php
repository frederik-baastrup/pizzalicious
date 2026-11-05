<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<section class="wrap page-wrap">
	<h1>Siden findes ikke</h1>
	<p class="muted">Produktet eller siden er måske fjernet. Prøv at søge, eller gå til en af kategorierne.</p>
	<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Til forsiden</a></p>
</section>
<?php
get_footer();
