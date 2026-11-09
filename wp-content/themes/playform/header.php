<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#indhold">Gå til indhold</a>

<?php
$pf_money = home_url( '/saadan-tjener-vi-penge/' );
$pf_usps  = [
	[ 'grid', 'Danske shops', 'samlet ét sted' ],
	[ 'tag', 'Aktuelle tilbud', 'direkte fra butikkerne' ],
	[ 'refresh', 'Priser opdateret', 'to gange dagligt' ],
	[ 'store', 'Køb direkte', 'hos shoppen' ],
];
?>
<div class="topbar">
	<div class="wrap topbar__in">
		<ul class="topbar__usp">
			<?php foreach ( $pf_usps as [ $icon, $lead, $rest ] ) : ?>
				<li><?php echo playform_icon( $icon, 14, 1.5 ); // phpcs:ignore ?><span><b><?php echo esc_html( $lead ); ?></b> <?php echo esc_html( $rest ); ?></span></li>
			<?php endforeach; ?>
		</ul>
		<a class="topbar__ad" href="<?php echo esc_url( $pf_money ); ?>"><span class="topbar__tag">Annonce</span>Siden indeholder affiliate-links</a>
	</div>
	<?php // Mobil: én linje, hvor fordelene skifter roligt ?>
	<div class="topbar__mob">
		<a class="topbar__tag" href="<?php echo esc_url( $pf_money ); ?>" title="Siden indeholder affiliate-links (reklamelinks)">Reklamelinks</a>
		<span class="topbar__rot" aria-live="off">
			<?php foreach ( $pf_usps as $i => [ $icon, $lead, $rest ] ) : ?>
				<span class="topbar__msg<?php echo $i === 0 ? ' is-on' : ''; ?>"><?php echo playform_icon( $icon, 13, 1.5 ); // phpcs:ignore ?><b><?php echo esc_html( $lead ); ?></b> <?php echo esc_html( $rest ); ?></span>
			<?php endforeach; ?>
		</span>
	</div>
</div>

<header class="site-header">
	<div class="wrap">
		<div class="site-header__row">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo" aria-label="<?php echo esc_attr( playform_site( 'name' ) ); ?> forside">
				<img src="<?php echo esc_url( playform_asset( 'logo.svg' ) ); ?>" alt="<?php echo esc_attr( playform_site( 'name' ) ); ?>" width="146" height="46">
			</a>
			<nav id="hovedmenu" class="mm" aria-label="Kategorier">
				<button type="button" class="mm__back" hidden><?php echo playform_icon( 'chevron', 16 ); // phpcs:ignore ?><span>Tilbage</span></button>
				<?php
				$current = is_tax( 'product_cat' ) ? playform_current_top_slug() : '';
				if ( is_singular( 'product' ) ) {
					$current = playform_root_slug( (string) get_post_meta( get_the_ID(), '_pf_category', true ) );
				}
				foreach ( playform_menu() as $item ) :
					?>
					<div class="mm__item" data-mm>
						<a href="<?php echo esc_url( $item['url'] ); ?>" class="mm__link<?php echo $current === $item['slug'] ? ' is-active' : ''; ?>" aria-haspopup="true"><?php echo esc_html( $item['label'] ); ?></a>
						<div class="mm__panel">
							<div class="mm__inner">
								<div class="mm__cats<?php echo count( $item['primary'] ) > 7 ? ' mm__cats--2' : ''; ?>">
									<span class="mm__title">Kategorier</span>
									<a class="mm__all" href="<?php echo esc_url( $item['url'] ); ?>">Se alle <?php echo esc_html( mb_strtolower( $item['label'] ) ); ?></a>
									<?php foreach ( $item['primary'] as $i => $p ) : ?>
										<a href="<?php echo esc_url( $p['url'] ); ?>" class="mm__cat<?php echo $i === 0 ? ' is-active' : ''; ?>" data-pane="<?php echo (int) $i; ?>"><?php echo esc_html( $p['label'] ); ?><?php echo playform_icon( 'chevron', 14 ); // phpcs:ignore ?></a>
									<?php endforeach; ?>
								</div>
								<?php foreach ( $item['primary'] as $i => $p ) : ?>
									<div class="mm__pane<?php echo $i === 0 ? ' is-active' : ''; ?>" data-pane="<?php echo (int) $i; ?>">
										<?php if ( $p['subs'] ) : ?>
											<div class="mm__col">
												<span class="mm__title"><?php echo esc_html( $p['label'] ); ?></span>
												<?php foreach ( $p['subs'] as [ $label, $url ] ) : ?>
													<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
												<?php endforeach; ?>
												<a href="<?php echo esc_url( $p['url'] ); ?>" class="mm__more">Se alle <?php echo esc_html( mb_strtolower( $p['label'] ) ); ?> &rarr;</a>
											</div>
											<?php if ( $p['prices'] ) : ?>
												<div class="mm__col">
													<span class="mm__title">Pris</span>
													<?php foreach ( $p['prices'] as [ $label, $url ] ) : ?>
														<a href="<?php echo esc_url( $url ); ?>" rel="nofollow"><?php echo esc_html( $label ); ?></a>
													<?php endforeach; ?>
												</div>
											<?php endif; ?>
										<?php else : ?>
											<?php // Ingen underkategorier: pris står i midten, så panelet ikke ser tomt ud. ?>
											<div class="mm__col">
												<span class="mm__title"><?php echo esc_html( $p['label'] ); ?> efter pris</span>
												<?php foreach ( $p['prices'] as [ $label, $url ] ) : ?>
													<a href="<?php echo esc_url( $url ); ?>" rel="nofollow"><?php echo esc_html( $label ); ?></a>
												<?php endforeach; ?>
												<a href="<?php echo esc_url( $p['url'] ); ?>" class="mm__more">Se hele kategorien &rarr;</a>
											</div>
										<?php endif; ?>
										<a href="<?php echo esc_url( $p['tilbud'] ); ?>" class="mm__promo">
											<img src="<?php echo esc_url( $p['img'] ); ?>"<?php echo playform_srcset( (string) $p['img'], '320px' ); // phpcs:ignore ?> alt="" loading="lazy">
											<span class="mm__promo-body"><span class="mm__promo-title">Tilbud på <?php echo esc_html( mb_strtolower( $p['label'] ) ); ?></span><span class="mm__promo-sub">Butikkernes egne tilbud lige nu</span></span>
											<span class="mm__promo-arrow" aria-hidden="true"><?php echo playform_icon( 'arrow-right', 18, 1.4 ); // phpcs:ignore ?></span>
										</a>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
				<div class="mm__item mm__item--plain">
					<a href="<?php echo esc_url( playform_tilbud_url() ); ?>" class="mm__link mm__link--sale<?php echo playform_is_tilbud_page() && ! is_tax() ? ' is-active' : ''; ?>">Tilbud</a>
				</div>
				<div class="mm__knowledge">
					<a href="<?php echo esc_url( ( $pf_viden = get_category_by_slug( 'viden' ) ) ? get_category_link( $pf_viden ) : home_url( '/viden/' ) ); ?>">Viden</a>
					<a href="<?php echo esc_url( playform_guide_url() ); ?>">Købsguides</a>
				</div>
			</nav>
			<form role="search" method="get" class="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-search data-endpoint="<?php echo esc_url( rest_url( 'playform/v1/search' ) ); ?>">
				<?php echo playform_icon( 'search', 18, 2 ); // phpcs:ignore ?>
				<label for="site-search" class="sr-only">Søg efter produkter</label>
				<input id="site-search" type="search" name="s" placeholder="Søg efter produkter, mærker …" autocomplete="off" value="<?php echo esc_attr( get_search_query() ); ?>">
				<input type="hidden" name="post_type" value="product">
				<button type="submit" class="search__go">Søg</button>
				<div class="search__drop" hidden>
					<div class="search__popular">
						<span class="mm__title">Populære kategorier</span>
						<div class="search__chips">
							<?php foreach ( playform_search_shortcuts() as [ $label, $url ] ) : ?>
								<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="search__results" hidden></div>
				</div>
			</form>
			<nav class="knowledge" aria-label="Viden">
				<a href="<?php echo esc_url( ( $pf_viden = get_category_by_slug( 'viden' ) ) ? get_category_link( $pf_viden ) : home_url( '/viden/' ) ); ?>">Viden</a>
				<a href="<?php echo esc_url( playform_guide_url() ); ?>">Købsguides</a>
				<a href="<?php echo esc_url( playform_tilbud_url() ); ?>" class="knowledge__sale">Tilbud</a>
			</nav>
			<button type="button" class="search-toggle" aria-expanded="false" aria-controls="site-search" aria-label="Søg"><?php echo playform_icon( 'search', 22, 1.6 ); // phpcs:ignore ?></button>
			<button type="button" class="menu-toggle" aria-expanded="false" aria-controls="mobilmenu" aria-label="Åbn menu"><span class="burger" aria-hidden="true"><i></i><i></i><i></i></span></button>
		</div>
	</div>
</header>
<?php playform_campaign_bar(); ?>
<?php get_template_part( 'template-parts/mobile-menu' ); ?>
<?php if ( isset( $_GET['nyhedsbrev'] ) && $_GET['nyhedsbrev'] === 'bekraeftet' ) : // phpcs:ignore ?>
	<div class="notice-bar" role="status"><div class="wrap">Tak – din tilmelding til nyhedsbrevet er bekræftet.</div></div>
<?php endif; ?>
<main id="indhold">
