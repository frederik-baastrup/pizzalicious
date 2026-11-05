<?php
/**
 * Guide / artikel.
 */
defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) :
	the_post();
	$html          = apply_filters( 'the_content', get_the_content() );
	[ $html, $toc ] = playform_guide_prepare( $html );
	$cats          = get_the_category();
	$tag           = $cats ? $cats[0]->name : 'Guide';
	$blog          = get_option( 'page_for_posts' ) ? get_permalink( (int) get_option( 'page_for_posts' ) ) : home_url( '/viden/' );
	$updated       = get_the_modified_date( 'j. F Y' );
	$author        = playform_author( (int) get_the_author_meta( 'ID' ) );
	?>
	<article class="guide">
		<header class="wrap guide__head">
			<?php
			$is_best = (int) ( playform_root_posts()[ get_post_field( 'post_name' ) ] ?? 0 ) === get_the_ID();
			playform_breadcrumbs( [ [ 'Forside', home_url( '/' ) ], $is_best ? [ 'Købsguides', playform_guide_url() ] : [ 'Viden & guides', $blog ], [ get_the_title(), '' ] ] );
			?>
			<?php if ( get_post_meta( get_the_ID(), '_pf_hidden', true ) ) : // Solgte/sponsorerede artikler skal tydeligt markeres som reklame (markedsføringsloven § 6, stk. 4). ?>
				<p class="sponsored-note"><b>Annonce</b>Artiklen er sponsoreret indhold og indeholder betalte links.</p>
			<?php endif; ?>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><p class="guide__lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
			<?php $checked = (string) get_post_meta( get_the_ID(), '_pf_checked', true ); ?>
			<div class="guide__meta">
				<a class="byline" href="<?php echo esc_url( $author['url'] ); ?>" rel="author">
					<?php echo playform_author_avatar( $author, 44 ); // phpcs:ignore ?>
					<span class="byline__txt"><b><span>Af</span> <?php echo esc_html( $author['name'] ); ?></b><em><?php echo esc_html( $author['role'] ); ?> hos <?php echo esc_html( playform_site( 'name' ) ); ?></em></span>
				</a>
				<div class="guide__chips">
				<ul class="guide__facts">
					<?php if ( $cats ) : ?>
						<li><a class="guide__cat" href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo playform_icon( 'grid', 15, 1.4 ); // phpcs:ignore ?><?php echo esc_html( $tag ); ?></a></li>
					<?php endif; ?>
					<li><?php echo playform_icon( 'calendar', 15, 1.4 ); // phpcs:ignore ?><span>Opdateret <span class="gt-long">d. </span><time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date( 'j. M Y' ) ); ?></time></span></li>
					<?php if ( $checked && playform_picks() ) : ?>
						<li><?php echo playform_icon( 'refresh', 15, 1.4 ); // phpcs:ignore ?><span>Produkter tjekket d. <?php echo esc_html( wp_date( 'j. M', strtotime( $checked ) ) ); ?></span></li>
					<?php endif; ?>
				</ul>
				<?php if ( playform_picks() || str_contains( $html, 'sponsored' ) || str_contains( $html, 'pcard' ) ) : ?>
					<a class="guide__trust" href="<?php echo esc_url( home_url( '/saadan-tjener-vi-penge/' ) ); ?>"><?php echo playform_icon( 'tag', 14, 1.6 ); // phpcs:ignore ?><span class="gt-long">Indeholder affiliate-links</span><span class="gt-short">Affiliate-links</span></a>
				<?php endif; ?>
				</div>
			</div>
		</header>
		<?php $hero = playform_post_image( get_the_ID(), 1200 ); ?>
		<?php if ( $hero ) : ?>
			<div class="wrap"><div class="guide__hero"><img src="<?php echo esc_url( $hero ); ?>" alt="" fetchpriority="high"></div></div>
		<?php endif; ?>
		<div class="wrap guide__layout">
			<aside class="guide__toc">
				<?php if ( count( $toc ) > 1 ) : ?>
					<nav class="toc" aria-label="Indhold" data-toc>
						<div class="toc__head"><span class="toc__title">Indhold</span><span class="toc__count"><?php echo count( $toc ); ?> afsnit</span></div>
						<ol>
							<?php foreach ( $toc as $i => [ $id, $text ] ) : ?>
								<li><a href="#<?php echo esc_attr( $id ); ?>"><span class="toc__n"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><span class="toc__t"><?php echo esc_html( $text ); ?></span></a></li>
							<?php endforeach; ?>
						</ol>
					</nav>
				<?php endif; ?>
			</aside>
			<div class="guide__body prose">
				<?php if ( ! playform_picks() && ( str_contains( $html, 'sponsored' ) || str_contains( $html, 'pcard' ) ) ) : // anbefalinger har deres egen boks om metode og provision ?>
				<p class="guide__note">Guiden indeholder links til butikker. Køber du via et link, kan vi få en provision – det koster ikke dig noget ekstra. Anbefalingerne er valgt ud fra produktdata og anmeldelser, ikke ud fra provision.</p>
				<?php endif; ?>
				<?php echo $html; // phpcs:ignore -- indholdet er allerede filtreret af the_content ?>
				<?php
				$n_guides = (int) count_user_posts( $author['id'], 'post', true );
				$n_prods  = (int) wp_count_posts( 'product' )->publish;
				$n_shops  = count( playform_shop_names() );
				?>
				<aside class="author-card" aria-label="Om forfatteren">
					<div class="author-card__head">
						<?php echo playform_author_avatar( $author, 64 ); // phpcs:ignore ?>
						<div class="author-card__who">
							<span class="author-card__label">Skrevet af</span>
							<a class="author-card__name" href="<?php echo esc_url( $author['url'] ); ?>" rel="author"><?php echo esc_html( $author['name'] ); ?></a>
							<span class="author-card__role"><?php echo esc_html( $author['role'] ); ?> hos <?php echo esc_html( playform_site( 'name' ) ); ?></span>
						</div>
						<a class="author-card__profile" href="<?php echo esc_url( $author['url'] ); ?>">Se profil <?php echo playform_icon( 'arrow-right', 14, 1.6 ); // phpcs:ignore ?></a>
					</div>
					<p class="author-card__bio"><?php echo esc_html( $author['bio'] ); ?></p>
					<ul class="author-card__stats">
						<li><b><?php echo esc_html( number_format( $n_guides, 0, ',', '.' ) ); ?></b><span>guides skrevet</span></li>
						<li><b><?php echo esc_html( number_format( $n_prods, 0, ',', '.' ) ); ?></b><span>produkter vi følger</span></li>
						<li><b><?php echo esc_html( (string) $n_shops ); ?></b><span>danske butikker</span></li>
					</ul>
					<ul class="author-card__rules">
						<?php if ( ! get_post_meta( get_the_ID(), '_pf_hidden', true ) ) : ?><li><?php echo playform_icon( 'check', 14, 1.8 ); // phpcs:ignore ?>Ingen betalte placeringer</li><?php endif; ?>
						<li><?php echo playform_icon( 'check', 14, 1.8 ); // phpcs:ignore ?>Fakta fra butikkernes egne data</li>
						<li><?php echo playform_icon( 'check', 14, 1.8 ); // phpcs:ignore ?>Priser opdateres to gange i døgnet</li>
					</ul>
				</aside>
			</div>
		</div>
	</article>

	<?php
	$more = get_posts( [ 'post_type' => 'post', 'posts_per_page' => 3, 'post__not_in' => [ get_the_ID() ] ] );
	if ( $more ) :
		?>
		<section class="wrap section guide-more">
			<div class="section__head"><h2>Flere guides</h2><span class="section__line"></span><a class="section__more" href="<?php echo esc_url( $blog ); ?>">Se alle</a></div>
			<div class="guides-list">
				<?php foreach ( $more as $m ) : ?>
					<a href="<?php echo esc_url( get_permalink( $m ) ); ?>" class="guide-row">
						<img src="<?php echo esc_url( get_the_post_thumbnail_url( $m, 'thumbnail' ) ?: playform_asset( 'cat-placeholder.svg' ) ); ?>" alt="" loading="lazy">
						<span><b><?php echo esc_html( get_the_title( $m ) ); ?></b><span><?php echo esc_html( get_the_date( 'j. M Y', $m ) ); ?></span></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
	<?php
endwhile;
get_footer();
