<?php
/**
 * Forfatterside: hvem skriver guiderne, hvordan vi arbejder, og alle forfatterens artikler.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$a     = playform_author( (int) get_queried_object_id() );
$blog  = get_option( 'page_for_posts' ) ? get_permalink( (int) get_option( 'page_for_posts' ) ) : home_url( '/viden/' );
$posts = get_posts( [ 'post_type' => 'post', 'author' => $a['id'], 'posts_per_page' => 50 ] );
?>
<section class="wrap cat-top author-top">
	<?php playform_breadcrumbs( [ [ 'Forside', home_url( '/' ) ], [ 'Viden & guides', $blog ], [ $a['name'], '' ] ] ); ?>
	<div class="author-hero">
		<?php echo playform_author_avatar( $a, 120 ); // phpcs:ignore ?>
		<div class="author-hero__body">
			<span class="author-box__label"><?php echo esc_html( $a['role'] ); ?></span>
			<h1><?php echo esc_html( $a['name'] ); ?></h1>
			<?php foreach ( preg_split( '/\n\s*\n/', $a['bio'] ) as $para ) : ?>
				<p><?php echo esc_html( trim( $para ) ); ?></p>
			<?php endforeach; ?>
			<?php if ( $a['links'] ) : ?>
				<div class="author-hero__links">
					<?php foreach ( $a['links'] as [ $label, $url ] ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" rel="me noopener" target="_blank"><?php echo esc_html( $label ); ?> <?php echo playform_icon( 'external', 14 ); // phpcs:ignore ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="wrap section author-method">
	<div class="section__head"><h2>Sådan arbejder vi</h2><span class="section__line"></span></div>
	<div class="author-method__grid">
		<div><b>Data direkte fra butikkerne</b><p>Priser, lagerstatus og specifikationer hentes fra de danske butikkers produktdata to gange i døgnet.</p></div>
		<div><b>Ingen opdigtede fakta</b><p>Guiderne bygger på producenternes oplysninger og generel købsrådgivning. Står en oplysning ikke i dataene, skriver vi den ikke.</p></div>
		<div><b>Ærlige tilbud</b><p>Vi viser kun butikkernes egne tilbud i højst 14 dage, og det står altid, hvor en førpris kommer fra.</p></div>
		<div><b>Annoncer</b><p>Siden indeholder affiliate-links, og vi får provision ved køb. <a href="<?php echo esc_url( home_url( '/saadan-tjener-vi-penge/' ) ); ?>">Sådan tjener vi penge</a>.</p></div>
	</div>
</section>

<?php if ( $posts ) : ?>
	<section class="wrap section section--end">
		<div class="section__head"><h2>Guides af <?php echo esc_html( strtok( $a['name'], ' ' ) ); ?></h2><span class="section__line"></span></div>
		<div class="guides-list">
			<?php foreach ( $posts as $m ) : ?>
				<a href="<?php echo esc_url( get_permalink( $m ) ); ?>" class="guide-row">
					<img src="<?php echo esc_url( get_the_post_thumbnail_url( $m, 'thumbnail' ) ?: playform_asset( 'cat-placeholder.svg' ) ); ?>" alt="" loading="lazy">
					<span><b><?php echo esc_html( get_the_title( $m ) ); ?></b><span><?php echo esc_html( get_the_modified_date( 'j. M Y', $m ) ); ?></span></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>
<?php
get_footer();
