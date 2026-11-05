<?php
/**
 * Viden & guides: forsiden (/viden/) og emnesiderne (/viden/emne/<emne>/).
 * Emnefaner som på kategorisiderne, en fremhævet guide og et gitter med resten.
 */
defined( 'ABSPATH' ) || exit;

$blog    = get_option( 'page_for_posts' ) ? get_permalink( (int) get_option( 'page_for_posts' ) ) : home_url( '/viden/' );
$current = is_category() ? get_queried_object() : null;
$topics  = get_categories( [ 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'exclude' => [ (int) get_option( 'default_category' ) ] ] );
$total   = (int) wp_count_posts( 'post' )->publish;
$paged   = max( 1, (int) get_query_var( 'paged' ) );
$title   = $current ? $current->name : 'Viden & guides';
$intro   = $current && trim( $current->description ) ? wp_strip_all_tags( $current->description ) : 'Købsguides og svar på det, du sidder og spekulerer på, når du skal vælge og bruge dit ' . playform_site( 'niche' ) . '.';
$crumbs  = [ [ 'Forside', home_url( '/' ) ], [ 'Viden & guides', $current ? $blog : '' ] ];
if ( $current ) {
	$crumbs[] = [ $current->name, '' ];
}

$card = static function ( WP_Post $p, bool $big = false ): void {
	$cats = get_the_category( $p->ID );
	$img  = playform_post_image( $p, $big ? 1200 : 700 ) ?: playform_asset( 'cat-placeholder.svg' );
	$min  = playform_read_minutes( apply_filters( 'the_content', $p->post_content ) );
	$a    = playform_author( (int) $p->post_author );
	?>
	<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" class="gcard<?php echo $big ? ' gcard--big' : ''; ?>">
		<span class="gcard__img"><img src="<?php echo esc_url( $img ); ?>" alt="" loading="<?php echo $big ? 'eager' : 'lazy'; ?>"></span>
		<span class="gcard__body">
			<?php if ( $cats ) : ?><span class="kicker kicker--sm"><i aria-hidden="true"></i><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
			<span class="gcard__title"><?php echo esc_html( get_the_title( $p ) ); ?></span>
			<?php if ( has_excerpt( $p ) ) : ?><span class="gcard__excerpt"><?php echo esc_html( get_the_excerpt( $p ) ); ?></span><?php endif; ?>
			<span class="gcard__meta">
				<?php if ( $big ) : ?><?php echo playform_author_avatar( $a, 28 ); // phpcs:ignore ?><span><?php echo esc_html( $a['name'] ); ?></span><i></i><?php endif; ?>
				<span><?php echo esc_html( get_the_modified_date( 'j. M Y', $p ) ); ?></span><i></i><span><?php echo (int) $min; ?> min. læsning</span>
			</span>
		</span>
	</a>
	<?php
};

global $wp_query;
$posts    = $wp_query->posts;
$featured = $paged === 1 && $posts ? array_shift( $posts ) : null;
?>
<section class="wrap cat-top">
	<?php playform_breadcrumbs( $crumbs ); ?>
	<div class="cat-top__head">
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html( $intro ); ?></p>
	</div>
	<?php if ( $topics ) : ?>
		<nav class="tabs" aria-label="Emner">
			<a href="<?php echo esc_url( $blog ); ?>" class="tab<?php echo $current ? '' : ' is-active'; ?>"<?php echo $current ? '' : ' aria-current="page"'; ?>>Alle guides<span class="tab__n"><?php echo (int) $total; ?></span></a>
			<?php foreach ( $topics as $t ) : ?>
				<?php $on = $current && (int) $current->term_id === (int) $t->term_id; ?>
				<a href="<?php echo esc_url( get_category_link( $t ) ); ?>" class="tab<?php echo $on ? ' is-active' : ''; ?>"<?php echo $on ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $t->name ); ?><span class="tab__n"><?php echo (int) $t->count; ?></span></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</section>

<section class="wrap section section--end guides-index">
	<?php if ( $featured ) : ?>
		<?php $card( $featured, true ); ?>
	<?php endif; ?>
	<?php if ( $posts ) : ?>
		<div class="gcards">
			<?php foreach ( $posts as $p ) : ?>
				<?php $card( $p ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if ( ! $featured && ! $posts ) : ?>
		<p class="muted">Her ligger ingen guides endnu.</p>
	<?php endif; ?>
	<?php the_posts_pagination( [ 'prev_text' => '‹', 'next_text' => '›' ] ); ?>
</section>
