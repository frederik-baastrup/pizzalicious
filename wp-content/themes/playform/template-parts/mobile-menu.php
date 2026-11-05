<?php
/**
 * Mobilmenuen: en skuffe i fuld skærm med lag, man klikker sig ind i.
 * Forside → menupunkt (fx Træningsmaskiner) → kategori (fx Løbebånd: underkategorier, pris og tilbud).
 * Vises kun på smalle skærme. Uden JavaScript virker alle overskrifter stadig som links.
 */
defined( 'ABSPATH' ) || exit;

$menu    = playform_menu();
$chev    = playform_icon( 'arrow-right', 16, 1.6 );
$back    = playform_icon( 'arrow-left', 18, 1.6 );
$row_img = static function ( string $src ): string {
	return $src ? '<img class="dr__thumb" src="' . esc_url( $src ) . '" alt="" width="44" height="44" loading="lazy">' : '';
};
$promo   = static function ( string $url, string $label, string $img ) use ( $chev ): void {
	?>
	<a class="dr__promo" href="<?php echo esc_url( $url ); ?>">
		<?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="" loading="lazy"><?php endif; ?>
		<span class="dr__promo-body">
			<span class="dr__promo-tag"><?php echo playform_icon( 'tag', 13, 1.6 ); // phpcs:ignore ?>Tilbud</span>
			<span class="dr__promo-title">Tilbud på <?php echo esc_html( mb_strtolower( $label ) ); ?></span>
		</span>
		<span class="dr__promo-arrow" aria-hidden="true"><?php echo $chev; // phpcs:ignore ?></span>
	</a>
	<?php
};
$head = static function ( string $title ) use ( $back ): void {
	?>
	<div class="dr__bar">
		<button type="button" class="dr__back" data-back aria-label="Tilbage"><?php echo $back; // phpcs:ignore ?></button>
		<span class="dr__heading"><?php echo esc_html( $title ); ?></span>
	</div>
	<?php
};
?>
<div class="dr-bg" data-close-bg hidden></div>
<div class="dr" id="mobilmenu" aria-label="Menu" role="dialog" aria-modal="true" aria-hidden="true" inert>
	<div class="dr__views">
		<?php // Lag 1: menupunkterne ?>
		<section class="dr__view is-active" data-view="root">
			<span class="dr__label">Kategorier</span>
			<ul class="dr__list dr__list--big">
				<?php foreach ( $menu as $item ) : ?>
					<li>
						<a class="dr__row" href="<?php echo esc_url( $item['url'] ); ?>" data-to="m-<?php echo esc_attr( $item['slug'] ); ?>">
							<?php echo $row_img( playform_category_thumb( $item['slug'] ) ); // phpcs:ignore ?>
							<span class="dr__name"><?php echo esc_html( $item['label'] ); ?></span>
							<?php echo $chev; // phpcs:ignore ?>
						</a>
					</li>
				<?php endforeach; ?>
				<li>
					<a class="dr__row dr__row--sale" href="<?php echo esc_url( playform_tilbud_url() ); ?>">
						<span class="dr__thumb dr__thumb--icon"><?php echo playform_icon( 'tag', 20, 1.5 ); // phpcs:ignore ?></span>
						<span class="dr__name">Tilbud<small>Butikkernes aktuelle tilbud</small></span>
						<?php echo $chev; // phpcs:ignore ?>
					</a>
				</li>
			</ul>

			<span class="dr__label">Viden og hjælp</span>
			<ul class="dr__list dr__list--small">
				<li><a href="<?php echo esc_url( playform_guide_url() ); ?>">Købsguides</a></li>
				<li><a href="<?php echo esc_url( home_url( '/viden/' ) ); ?>">Viden &amp; guides</a></li>
				<li><a href="<?php echo esc_url( home_url( '/vores-butikker/' ) ); ?>">Vores butikker</a></li>
				<li><a href="<?php echo esc_url( home_url( '/saadan-tjener-vi-penge/' ) ); ?>">Sådan tjener vi penge</a></li>
			</ul>
		</section>

		<?php // Lag 2: kategorierne under et menupunkt ?>
		<?php foreach ( $menu as $item ) : ?>
			<?php
			$thumbs = array_map( static fn( $p ) => playform_category_thumb( $p['slug'] ), $item['primary'] );
			// Billeder kun, når kategorierne har hver deres – ellers ser listen ens ud.
			$use_thumbs = count( array_unique( array_filter( $thumbs ) ) ) === count( $item['primary'] );
			?>
			<section class="dr__view" data-view="m-<?php echo esc_attr( $item['slug'] ); ?>">
				<?php $head( $item['label'] ); ?>
				<a class="dr__all" href="<?php echo esc_url( $item['url'] ); ?>">Se alle <?php echo esc_html( mb_strtolower( $item['label'] ) ); ?><?php echo $chev; // phpcs:ignore ?></a>
				<ul class="dr__list<?php echo $use_thumbs ? ' dr__list--big' : ''; ?>">
					<?php foreach ( $item['primary'] as $i => $p ) : ?>
						<?php $deeper = $p['subs'] || $p['prices']; ?>
						<li>
							<a class="dr__row" href="<?php echo esc_url( $p['url'] ); ?>"<?php echo $deeper ? ' data-to="c-' . esc_attr( $p['slug'] ) . '"' : ''; ?>>
								<?php echo $use_thumbs ? $row_img( $thumbs[ $i ] ) : ''; // phpcs:ignore ?>
								<span class="dr__name"><?php echo esc_html( $p['label'] ); ?></span>
								<?php echo $chev; // phpcs:ignore ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php $promo( $item['tilbud'], $item['label'], playform_category_image( $item['slug'] ) ); ?>
			</section>
		<?php endforeach; ?>

		<?php // Lag 3: én kategori – underkategorier, pris og tilbud ?>
		<?php foreach ( $menu as $item ) : ?>
			<?php foreach ( $item['primary'] as $p ) : ?>
				<?php
				if ( ! $p['subs'] && ! $p['prices'] ) {
					continue;
				}
				?>
				<section class="dr__view" data-view="c-<?php echo esc_attr( $p['slug'] ); ?>">
					<?php $head( $p['label'] ); ?>
					<a class="dr__all" href="<?php echo esc_url( $p['url'] ); ?>">Se alle <?php echo esc_html( mb_strtolower( $p['label'] ) ); ?><?php echo $chev; // phpcs:ignore ?></a>
					<?php if ( $p['subs'] ) : ?>
						<ul class="dr__list">
							<?php foreach ( $p['subs'] as [ $label, $url ] ) : ?>
								<li><a class="dr__row" href="<?php echo esc_url( $url ); ?>"><span class="dr__name"><?php echo esc_html( $label ); ?></span><?php echo $chev; // phpcs:ignore ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $p['prices'] ) : ?>
						<span class="dr__label">Pris</span>
						<div class="dr__chips">
							<?php foreach ( $p['prices'] as [ $label, $url ] ) : ?>
								<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php $promo( $p['tilbud'], $p['label'], $p['img'] ); ?>
				</section>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>

	<p class="dr__foot">Siden indeholder annoncer. <a href="<?php echo esc_url( home_url( '/saadan-tjener-vi-penge/' ) ); ?>">Sådan tjener vi penge</a></p>
</div>
