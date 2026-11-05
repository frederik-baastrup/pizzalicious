<?php defined( 'ABSPATH' ) || exit; ?>
</main>
<footer class="site-footer">
	<div class="wrap">
		<div class="foot">
			<div class="foot__about">
				<img src="<?php echo esc_url( playform_asset( 'logo-white.svg' ) ); ?>" alt="<?php echo esc_attr( playform_site( 'name' ) ); ?>" width="146" height="46">
				<p>Vi samler <?php echo esc_html( playform_site( 'niche' ) ); ?> fra danske webshops ét sted, så du nemt kan finde rundt i hele udvalget.</p>
				<span><?php echo esc_html( playform_site( 'email' ) ); ?></span>
			</div>
			<div class="foot__col">
				<span class="foot__title">Kategorier</span>
				<?php foreach ( array_slice( playform_menu(), 0, 7 ) as $item ) : ?>
					<a href="<?php echo esc_url( playform_category_url( $item['slug'] ) ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
			<div class="foot__col">
				<span class="foot__title">Populære</span>
				<?php foreach ( playform_footer_popular() as [ $label, $slug ] ) : ?>
					<a href="<?php echo esc_url( playform_category_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
			<div class="foot__col">
				<span class="foot__title">Viden</span>
				<a href="<?php echo esc_url( playform_guide_url() ); ?>">Købsguides</a>
				<a href="<?php echo esc_url( playform_campaign_url( 'black-friday' ) ); ?>">Black Friday</a>
				<a href="<?php echo esc_url( home_url( '/viden/' ) ); ?>">Nyheder</a>
			</div>
			<div class="foot__col">
				<span class="foot__title">Om <?php echo esc_html( playform_site( 'name' ) ); ?></span>
				<a href="<?php echo esc_url( playform_how_url() ); ?>"><?php echo esc_html( playform_how_title() ); ?></a>
				<a href="<?php echo esc_url( home_url( '/vores-butikker/' ) ); ?>">Vores butikker</a>
				<a href="<?php echo esc_url( home_url( '/saadan-tjener-vi-penge/' ) ); ?>">Sådan tjener vi penge</a>
				<a href="<?php echo esc_url( home_url( '/privatlivspolitik/' ) ); ?>">Privatlivspolitik</a>
				<a href="<?php echo esc_url( home_url( '/cookies/' ) ); ?>">Cookies</a>
			</div>
		</div>
		<div class="foot__bottom">
			<span><?php echo esc_html( playform_site( 'domain' ) ); ?> indeholder annoncer. Når du klikker videre og handler hos en af vores partnere, kan vi modtage en provision. Det koster ikke dig noget ekstra. Priser og lagerstatus kan have ændret sig siden seneste opdatering.</span>
			<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( playform_site( 'domain' ) . ' · ' . playform_site( 'company' ) . ' · CVR ' . playform_site( 'cvr' ) ); ?> · <button type="button" class="foot__link" data-cc-open>Cookie-indstillinger</button></span>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
