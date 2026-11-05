<?php
/**
 * Forfattere: navn i stedet for e-mail, forfatterside (/viden/forfatter/navn/), forfatterboks under
 * artikler og struktureret data (Person/ProfilePage/Article) – det, Google kigger efter ved E-E-A-T.
 *
 * Profilen redigeres under Brugere → Profil: navn, "Biografiske oplysninger" og felterne under "<sitenavn>-forfatter".
 * Billeder hentes aldrig fra Gravatar (ingen tredjepartskald) – brug feltet "Profilbillede".
 */
defined( 'ABSPATH' ) || exit;

/** Standardtekster, indtil profilen er udfyldt. Ingen påståede kvalifikationer – kun hvad sitet er. */
function playform_author_defaults(): array {
	return [
		'role' => 'Stifter og redaktør',
		'bio'  => sprintf( 'Frederik Baastrup står bag %s. Han samler %s fra de danske webshops ét sted og skriver købsguiderne, så du kan sammenligne priser, specifikationer og tilbud, før du køber.', playform_site( 'name' ), playform_site( 'niche' ) ),
	];
}

add_action( 'init', static function () {
	global $wp_rewrite;
	$wp_rewrite->author_base = 'forfatter';
}, 5 );

/**
 * Engangsrettelse: administratoren, der står som forfatter, havde e-mailen som visningsnavn.
 * Kun hvis visningsnavnet er en e-mail – et navn, man selv har valgt, røres ikke.
 */
function playform_fix_author_name(): void {
	foreach ( get_users( [ 'role' => 'administrator' ] ) as $u ) {
		if ( ! str_contains( $u->display_name, '@' ) ) {
			continue;
		}
		$first = get_user_meta( $u->ID, 'first_name', true ) ?: 'Frederik';
		$last  = get_user_meta( $u->ID, 'last_name', true ) ?: 'Baastrup';
		update_user_meta( $u->ID, 'first_name', $first );
		update_user_meta( $u->ID, 'last_name', $last );
		update_user_meta( $u->ID, 'nickname', "$first $last" );
		wp_update_user( [ 'ID' => $u->ID, 'display_name' => "$first $last", 'user_nicename' => sanitize_title( "$first $last" ) ] );
		if ( ! get_user_meta( $u->ID, 'pf_role', true ) ) {
			update_user_meta( $u->ID, 'pf_role', playform_author_defaults()['role'] );
		}
	}
}

/** Alt, skabelonerne skal bruge om en forfatter. */
function playform_author( int $user_id ): array {
	$u    = get_userdata( $user_id );
	$name = $u ? $u->display_name : playform_site( 'name' );
	$bio  = trim( (string) get_user_meta( $user_id, 'description', true ) );
	$links = array_values(
		array_filter(
			[
				$u && $u->user_url && wp_parse_url( $u->user_url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ? [ 'Website', $u->user_url ] : null,
				get_user_meta( $user_id, 'pf_linkedin', true ) ? [ 'LinkedIn', (string) get_user_meta( $user_id, 'pf_linkedin', true ) ] : null,
				get_user_meta( $user_id, 'pf_instagram', true ) ? [ 'Instagram', (string) get_user_meta( $user_id, 'pf_instagram', true ) ] : null,
			]
		)
	);
	$parts    = preg_split( '/\s+/', trim( $name ) );
	$initials = mb_strtoupper( mb_substr( $parts[0] ?? '', 0, 1 ) . mb_substr( count( $parts ) > 1 ? end( $parts ) : '', 0, 1 ) );
	return [
		'id'       => $user_id,
		'name'     => $name,
		'url'      => get_author_posts_url( $user_id ),
		'role'     => (string) ( get_user_meta( $user_id, 'pf_role', true ) ?: playform_author_defaults()['role'] ),
		'bio'      => $bio !== '' ? $bio : playform_author_defaults()['bio'],
		'photo'    => esc_url_raw( (string) get_user_meta( $user_id, 'pf_photo', true ) ),
		'initials' => $initials,
		'links'    => $links,
	];
}

/** Rundt profilbillede – eller initialer, hvis der ikke er et billede. */
function playform_author_avatar( array $a, int $size = 56 ): string {
	if ( $a['photo'] ) {
		return '<img class="avatar-pf" src="' . esc_url( $a['photo'] ) . '" alt="' . esc_attr( $a['name'] ) . '" width="' . $size . '" height="' . $size . '" loading="lazy" style="width:' . $size . 'px;height:' . $size . 'px">';
	}
	return '<span class="avatar-pf avatar-pf--initials" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round( $size * .36 ) . 'px" aria-hidden="true">' . esc_html( $a['initials'] ) . '</span>';
}

/** Schema.org-person til JSON-LD. */
function playform_author_schema( array $a ): array {
	$p = [
		'@type'    => 'Person',
		'@id'      => $a['url'] . '#person',
		'name'     => $a['name'],
		'url'      => $a['url'],
		'jobTitle' => $a['role'],
		'worksFor' => [ '@type' => 'Organization', 'name' => playform_site( 'name' ), 'url' => home_url( '/' ) ],
	];
	if ( $a['photo'] ) {
		$p['image'] = $a['photo'];
	}
	if ( $a['links'] ) {
		$p['sameAs'] = array_column( $a['links'], 1 );
	}
	return $p;
}

function playform_json_ld( array $data ): void {
	echo '<script type="application/ld+json">' . wp_json_encode( [ '@context' => 'https://schema.org' ] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

// Struktureret data: artikel med forfatter, og forfattersiden som ProfilePage.
add_action(
	'wp_head',
	static function () {
		if ( is_singular( 'post' ) ) {
			$id = get_queried_object_id();
			$a  = playform_author( (int) get_post_field( 'post_author', $id ) );
			$d  = [
				'@type'         => 'Article',
				'headline'      => get_the_title( $id ),
				'description'   => wp_strip_all_tags( get_the_excerpt( $id ) ),
				'datePublished' => get_the_date( 'c', $id ),
				'dateModified'  => get_the_modified_date( 'c', $id ),
				'mainEntityOfPage' => get_permalink( $id ),
				'author'        => playform_author_schema( $a ),
				'publisher'     => [ '@type' => 'Organization', 'name' => playform_site( 'name' ), 'url' => home_url( '/' ), 'logo' => [ '@type' => 'ImageObject', 'url' => playform_asset( 'logo.svg' ) ] ],
			];
			if ( has_post_thumbnail( $id ) ) {
				$d['image'] = get_the_post_thumbnail_url( $id, 'large' );
			}
			playform_json_ld( $d );
		} elseif ( is_author() ) {
			$a = playform_author( (int) get_queried_object_id() );
			playform_json_ld( [ '@type' => 'ProfilePage', 'url' => $a['url'], 'mainEntity' => playform_author_schema( $a ) ] );
		}
	}
);

// Metabeskrivelse og titel på forfattersiden.
add_filter(
	'document_title_parts',
	static function ( array $parts ): array {
		if ( is_author() ) {
			$a              = playform_author( (int) get_queried_object_id() );
			$parts['title'] = $a['name'] . ' – ' . mb_strtolower( $a['role'] );
		}
		return $parts;
	},
	20
);
add_action(
	'wp_head',
	static function () {
		if ( is_author() && ! playform_has_seo_plugin() ) {
			$a = playform_author( (int) get_queried_object_id() );
			echo '<meta name="description" content="' . esc_attr( wp_trim_words( $a['bio'], 28, '…' ) ) . '">' . "\n";
		}
	},
	2
);

// Ekstra felter på brugerprofilen.
$playform_author_fields = static function ( WP_User $u ) {
	$f = [
		'pf_role'      => [ 'Titel', 'Fx "Stifter og redaktør" eller "Personlig træner"' ],
		'pf_photo'     => [ 'Profilbillede (URL)', 'Upload et kvadratisk billede under Medier og indsæt adressen her. Ingen Gravatar.' ],
		'pf_linkedin'  => [ 'LinkedIn', 'Fuld adresse til din profil' ],
		'pf_instagram' => [ 'Instagram', 'Fuld adresse til din profil' ],
	];
	echo '<h2>' . esc_html( playform_site( 'name' ) ) . '-forfatter</h2><p>Vises på forfattersiden og under artiklerne. Biografien skrives i feltet "Biografiske oplysninger" ovenfor.</p><table class="form-table">';
	foreach ( $f as $key => [ $label, $help ] ) {
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) get_user_meta( $u->ID, $key, true ) ) . '"><p class="description">' . esc_html( $help ) . '</p></td></tr>';
	}
	echo '</table>';
};
add_action( 'show_user_profile', $playform_author_fields );
add_action( 'edit_user_profile', $playform_author_fields );
$playform_author_save = static function ( int $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	foreach ( [ 'pf_role', 'pf_photo', 'pf_linkedin', 'pf_instagram' ] as $key ) {
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- WordPress tjekker profilens nonce
			$v = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore
			update_user_meta( $user_id, $key, $key === 'pf_role' ? $v : esc_url_raw( $v ) );
		}
	}
};
add_action( 'personal_options_update', $playform_author_save );
add_action( 'edit_user_profile_update', $playform_author_save );

/**
 * Frederiks profil (2. nov. 2026): bio og profilbillede, hvis felterne stadig er tomme eller står på standardteksten.
 * Rettes de i WordPress (Brugere → Profil), bliver de ikke overskrevet.
 * Kun fakta, Frederik selv har oplyst – ingen påståede kvalifikationer.
 */
function playform_author_profile(): void {
	if ( get_option( 'playform_author_profile' ) || '' === playform_site( 'niche' ) ) { // uden importpluginnet kender vi hverken navn eller niche
		return;
	}
	$users = get_users( [ 'search' => 'Frederik Baastrup', 'search_columns' => [ 'display_name' ], 'number' => 1 ] )
		?: get_users( [ 'role' => 'administrator', 'number' => 1 ] );
	foreach ( $users as $u ) {
		$bio = trim( (string) get_user_meta( $u->ID, 'description', true ) );
		if ( '' === $bio || playform_author_defaults()['bio'] === $bio ) {
			update_user_meta( $u->ID, 'description', sprintf( 'Frederik Baastrup er stifter og redaktør af %1$s. Til daglig driver han Mondae, hvor han arbejder med webdesign og digitale løsninger. Med %1$s samler han %2$s fra danske webshops ét sted og skriver købsguides, der gør det nemt at sammenligne priser, specifikationer og tilbud – med åbenhed om, hvordan produkterne er valgt, og hvordan siden tjener penge.', playform_site( 'name' ), playform_site( 'niche' ) ) );
		}
		if ( '' === (string) get_user_meta( $u->ID, 'pf_photo', true ) ) {
			update_user_meta( $u->ID, 'pf_photo', playform_asset( 'authors/frederik-baastrup.jpg' ) );
		}
	}
	update_option( 'playform_author_profile', 1, false );
}
