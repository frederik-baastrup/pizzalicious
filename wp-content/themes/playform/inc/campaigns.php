<?php
/**
 * Sæsonkampagner: Black Friday (hele november), januarudsalg, sommerudsalg og efterårstilbud.
 *
 * Kører helt automatisk ud fra datoen (ejerens ønske 4. nov. 2026 – ingen opsætning i WordPress):
 * - en sort bar under menuen i kampagnens periode, med tekst der skifter undervejs (fx nedtælling til Black Friday)
 * - en fast side pr. kampagne (/black-friday/ osv.), der samme adresse år efter år samler links og placeringer.
 *   Siden viser butikkernes aktuelle tilbud med de samme regler som /tilbud/ (højst 14 dage gamle, kontrolleret
 *   førpris). Uden for perioden står den stadig, med en note om, hvornår kampagnen kommer igen.
 *
 * Vi lover aldrig mere, end tilbuddene holder: ingen "vilde rabatter", kun "butikkernes tilbud".
 */

defined( 'ABSPATH' ) || exit;

/** Black Friday: fredagen efter fjerde torsdag i november. */
function playform_black_friday( int $year ): DateTimeImmutable {
	$tz    = wp_timezone();
	$first = new DateTimeImmutable( "$year-11-01", $tz );
	$dow   = (int) $first->format( 'N' ); // 1 = mandag … 7 = søndag
	$thu   = $first->modify( '+' . ( ( 4 - $dow + 7 ) % 7 ) . ' days' ); // første torsdag
	return $thu->modify( '+22 days' ); // fjerde torsdag + 1 dag
}

/**
 * Kampagnerne. 'period' giver [ start, slut ] (inkl.) for et år. Rækkefølgen er uden betydning; perioderne overlapper ikke.
 *
 * @return array<string,array{name:string,title:string,intro:string,period:callable}>
 */
function playform_campaign_defs(): array {
	$niche   = playform_site( 'niche' );
	// Vi viser kun tilbud fra de butikker, vi samarbejder med – ikke hele markedet. Det skal teksten sige.
	$from    = "fra de danske webshops, vi samarbejder med";
	return [
		'black-friday'    => [
			'name'   => 'Black Friday',
			'title'  => "Black Friday – Tilbud på $niche",
			'intro'  => "Se Black Friday-tilbud på $niche samlet ét sted. Hele november samler vi butikkernes egne tilbud $from, så du nemt kan sammenligne dem. Klik videre og køb direkte hos butikken.",
			'period' => static function ( int $y ): array {
				$tz  = wp_timezone();
				$cm  = playform_black_friday( $y )->modify( '+3 days' ); // Cyber Monday
				$end = new DateTimeImmutable( "$y-11-30", $tz );
				return [ new DateTimeImmutable( "$y-11-01", $tz ), $cm > $end ? $cm : $end ];
			},
		],
		'januarudsalg'    => [
			'name'   => 'Januarudsalg',
			'title'  => "Januarudsalg – Tilbud på $niche",
			'intro'  => "Se januartilbud på $niche samlet ét sted. Vi samler butikkernes udsalg $from, så du nemt kan sammenligne dem. Klik videre og køb direkte hos butikken.",
			'period' => static fn( int $y ): array => [ new DateTimeImmutable( "$y-01-01", wp_timezone() ), new DateTimeImmutable( "$y-01-31", wp_timezone() ) ],
		],
		'sommerudsalg'    => [
			'name'   => 'Sommerudsalg',
			'title'  => "Sommerudsalg – Tilbud på $niche",
			'intro'  => "Se sommertilbud på $niche samlet ét sted. Vi samler butikkernes udsalg $from, så du nemt kan sammenligne dem. Klik videre og køb direkte hos butikken.",
			'period' => static fn( int $y ): array => [ new DateTimeImmutable( "$y-06-25", wp_timezone() ), new DateTimeImmutable( "$y-08-10", wp_timezone() ) ],
		],
		'efteraarstilbud' => [
			'name'   => 'Efterårstilbud',
			'title'  => "Efterårstilbud – Tilbud på $niche",
			'intro'  => "Se efterårstilbud på $niche samlet ét sted. Vi samler butikkernes egne tilbud $from, så du nemt kan sammenligne dem. Klik videre og køb direkte hos butikken.",
			'period' => static fn( int $y ): array => [ new DateTimeImmutable( "$y-09-15", wp_timezone() ), new DateTimeImmutable( "$y-10-31", wp_timezone() ) ],
		],
	];
}

function playform_campaign_today(): DateTimeImmutable {
	return new DateTimeImmutable( current_time( 'Y-m-d' ), wp_timezone() );
}

/** Kampagnens periode, der er i gang eller kommer næst efter $day: [ start, slut ]. */
function playform_campaign_next_period( string $slug, ?DateTimeImmutable $day = null ): array {
	$day = $day ?: playform_campaign_today();
	$def = playform_campaign_defs()[ $slug ];
	$y   = (int) $day->format( 'Y' );
	[ $s, $e ] = ( $def['period'] )( $y );
	return $e < $day ? ( $def['period'] )( $y + 1 ) : [ $s, $e ];
}

/** Er kampagnen i gang på dagen? */
function playform_campaign_running( string $slug, ?DateTimeImmutable $day = null ): bool {
	$day = $day ?: playform_campaign_today();
	[ $s, $e ] = playform_campaign_next_period( $slug, $day );
	return $s <= $day && $day <= $e;
}

/** Den kampagne, der kører i dag (slug), eller ''. */
function playform_active_campaign( ?DateTimeImmutable $day = null ): string {
	foreach ( array_keys( playform_campaign_defs() ) as $slug ) {
		if ( playform_campaign_running( $slug, $day ) ) {
			return $slug;
		}
	}
	return '';
}

/** Kampagnen for den aktuelle side (/black-friday/ osv.), eller ''. */
function playform_current_campaign(): string {
	$slug = (string) get_query_var( 'pf_kampagne' );
	return isset( playform_campaign_defs()[ $slug ] ) ? $slug : '';
}

function playform_campaign_url( string $slug ): string {
	return home_url( user_trailingslashit( $slug ) );
}

/**
 * Barens tekst for dagen: [ fed tekst, resten ]. Black Friday skifter undervejs i november.
 * "Resten" kan være en liste af beskeder, der skiftes mellem i båndet.
 * Kun det, vi kan stå inde for: tilbuddene er butikkernes egne, højst 14 dage gamle, samlet fra flere webshops.
 */
function playform_campaign_bar_text( string $slug, ?DateTimeImmutable $day = null ): array {
	$day   = $day ?: playform_campaign_today();
	$niche = playform_site( 'niche' );
	$pitch = [ "Spar på $niche – tilbud fra danske webshops samlet ét sted", 'Kun friske tilbud – aldrig ældre end 14 dage' ];
	if ( $slug === 'black-friday' ) {
		$bf   = playform_black_friday( (int) $day->format( 'Y' ) );
		$days = (int) $day->diff( $bf )->format( '%r%a' );
		if ( $days > 7 ) {
			return [ 'Kæmpe besparelser til Black Month', 'Se de vilde tilbud hos butikkerne her!' ]; // ejerens tekst 4. nov. 2026
		}
		if ( $days > 1 ) {
			return [ "Black Friday om $days dage", [ 'Mange tilbud er allerede startet – kom før de andre', $pitch[1] ] ];
		}
		if ( $days === 1 ) {
			return [ 'Black Friday i morgen', [ 'Mange tilbud er allerede startet – kom før de andre', $pitch[1] ] ];
		}
		if ( $days === 0 ) {
			return [ 'Black Friday', [ 'Tilbuddene er i gang – find dem samlet ét sted', $pitch[1] ] ];
		}
		if ( $days >= -2 ) {
			return [ 'Black Weekend', [ 'Tilbuddene fortsætter hele weekenden', $pitch[0] ] ];
		}
		if ( $days === -3 ) {
			return [ 'Cyber Monday', [ 'Sidste chance for mange af tilbuddene', $pitch[0] ] ];
		}
		return [ 'Black Month', [ 'De sidste dage med Black Month-tilbud', $pitch[1] ] ];
	}
	$def = playform_campaign_defs()[ $slug ];
	return [ $def['name'], $pitch ];
}

/** Den sorte bar under menuen. Vises kun, mens en kampagne kører (og ikke på selve kampagnesiden). */
function playform_campaign_bar(): void {
	$slug = playform_active_campaign();
	if ( $slug === '' || playform_current_campaign() === $slug ) {
		return;
	}
	[ $lead, $rest ] = playform_campaign_bar_text( $slug );
	$rest  = array_values( (array) $rest );
	$url   = playform_campaign_url( $slug );
	// Teksten løber fra venstre mod højre i et bånd med fade i begge sider. To ens grupper, så løkken er sømløs.
	$group = '';
	for ( $k = 0; $k < 6; $k++ ) {
		$group .= '<span class="campaign-bar__item"><b>' . esc_html( $lead ) . '</b><i aria-hidden="true"></i>' . esc_html( $rest[ $k % count( $rest ) ] ) . '<i aria-hidden="true"></i></span>';
	}
	$rest = $rest[0];
	printf(
		'<div class="campaign-bar"><div class="wrap campaign-bar__in"><a class="campaign-bar__track" href="%1$s" tabindex="-1" aria-hidden="true"><span class="campaign-bar__run"><span class="campaign-bar__group">%2$s</span><span class="campaign-bar__group">%2$s</span></span></a><span class="sr-only">%3$s: %4$s.</span><a class="campaign-bar__btn" href="%1$s">Se tilbuddene %5$s</a></div></div>',
		esc_url( $url ),
		$group, // phpcs:ignore -- escaped ovenfor
		esc_html( $lead ),
		esc_html( $rest ),
		playform_icon( 'chevron', 14, 1.8 ) // phpcs:ignore
	);
}

/** Overskrift, intro og evt. note til kampagnesiden. */
function playform_campaign_page_text( string $slug ): array {
	$def   = playform_campaign_defs()[ $slug ];
	$today = playform_campaign_today();
	[ $s, $e ] = playform_campaign_next_period( $slug, $today );
	$note = '';
	if ( ! playform_campaign_running( $slug, $today ) ) {
		$when = $slug === 'black-friday'
			? 'Black Friday er ' . wp_date( 'j. F Y', playform_black_friday( (int) $s->format( 'Y' ) )->getTimestamp() )
			: $def['name'] . ' starter ' . wp_date( 'j. F Y', $s->getTimestamp() );
		$note = $def['name'] . ' er ikke i gang lige nu. ' . $when . '. Indtil da kan du se butikkernes aktuelle tilbud her.';
	}
	return [ 'title' => $def['title'], 'intro' => $def['intro'], 'note' => $note ];
}

/**
 * Lang SEO-tekst nederst på kampagnesiden (content/campaign-seo.php, ét HTML-felt pr. kampagne).
 * {cat:slug} og {cat:slug|tekst} bliver til links til kategorien (eller bare tekst, hvis kategorien ikke findes).
 */
function playform_campaign_seo( string $slug ): string {
	$file = get_template_directory() . '/content/campaign-seo.php';
	$all  = is_readable( $file ) ? require $file : [];
	$html = (string) ( $all[ $slug ] ?? '' );
	return (string) preg_replace_callback(
		'/\{cat:([a-z0-9-]+)(?:\|([^}]+))?\}/',
		static function ( $m ) {
			$term  = get_term_by( 'slug', $m[1], 'product_cat' );
			$label = $m[2] ?? ( $term ? mb_strtolower( $term->name ) : $m[1] );
			$link  = $term ? get_term_link( $term ) : '';
			return $link && ! is_wp_error( $link ) ? '<a href="' . esc_url( $link ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label );
		},
		$html
	);
}

// Faste adresser: /black-friday/ og /black-friday/page/2/ viser tilbudslisten med kampagnens tekst.
add_filter(
	'rewrite_rules_array',
	static function ( array $rules ): array {
		$new = [];
		foreach ( array_keys( playform_campaign_defs() ) as $slug ) {
			$q = 'index.php?post_type=product&pf_tilbud=1&pf_kampagne=' . $slug;
			$new[ '^' . $slug . '/page/?([0-9]{1,})/?$' ] = $q . '&paged=$matches[1]';
			$new[ '^' . $slug . '/?$' ]                    = $q;
		}
		return $new + $rules;
	}
);
add_filter( 'query_vars', static fn( array $vars ): array => array_merge( $vars, [ 'pf_kampagne' ] ) );

// Kampagnesiderne i WordPress' sitemap, så Google kender dem hele året.
add_action(
	'init',
	static function () {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		$provider = new class() extends WP_Sitemaps_Provider {
			public function __construct() {
				$this->name        = 'kampagner';
				$this->object_type = 'kampagner';
			}
			public function get_url_list( $page_num, $object_subtype = '' ) {
				return $page_num > 1 ? [] : array_map( static fn( $s ) => [ 'loc' => playform_campaign_url( $s ) ], array_keys( playform_campaign_defs() ) );
			}
			public function get_max_num_pages( $object_subtype = '' ) {
				return 1;
			}
		};
		wp_register_sitemap_provider( 'kampagner', $provider );
	}
);
