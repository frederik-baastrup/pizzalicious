<?php
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article class="wrap page-wrap prose">
		<?php playform_breadcrumbs( [ [ 'Forside', home_url( '/' ) ], [ get_the_title(), '' ] ] ); ?>
		<h1 style="margin-top:16px"><?php the_title(); ?></h1>
		<?php echo preg_replace( '#<p>\s*\[Listen over butikker[^<]*</p>#u', '', apply_filters( 'the_content', get_the_content() ) ); // phpcs:ignore ?>
		<?php if ( get_post_field( 'post_name' ) === 'vores-butikker' ) : ?>
			<ul>
				<?php foreach ( playform_shop_names() as $name ) : ?>
					<li><?php echo esc_html( $name ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>Læs også <a href="<?php echo esc_url( playform_how_url() ); ?>"><?php echo esc_html( playform_how_title() ); ?></a>: hvor produkterne kommer fra, og hvorfor vi må vise dem.</p>
		<?php endif; ?>
		<?php if ( get_post_field( 'post_name' ) === PLAYFORM_HOW_SLUG ) : ?>
			<div class="how"><?php echo playform_how_html(); // phpcs:ignore ?></div>
		<?php endif; ?>
	</article>
	<?php
endwhile;
get_footer();
