<?php
/**
 * Udkast til privatlivspolitik og cookiepolitik.
 * Skrevet ud fra, hvad siden faktisk gør i dag. Sitets navn, domæne og firma kommer fra playform_site() ({NAME}, {DOMAIN} osv. erstattes i playform_legal_fill()).
 * Ændres teksten, så hæv PLAYFORM_LEGAL_VERSION, så siderne opdateres ved næste udrulning
 * (kun hvis redaktøren ikke selv har rettet dem – se playform_update_legal_pages()).
 */

defined( 'ABSPATH' ) || exit;

const PLAYFORM_LEGAL_VERSION = 6;

/** Indsætter sitets navn, domæne, e-mail m.m. i teksterne. */
function playform_legal_fill( string $html ): string {
	$site = playform_site();
	return strtr(
		$html,
		[
			'{NAME}'    => $site['name'],
			'{DOMAIN}'  => $site['domain'],
			'{EMAIL}'   => $site['email'],
			'{NICHE}'   => $site['niche'],
			'{COMPANY}' => $site['company'],
			'{ADDRESS}' => $site['address'],
			'{CVR}'     => $site['cvr'],
		]
	);
}

function playform_legal_privacy(): string {
	return playform_legal_fill( <<<'HTML'
<p><em>Senest opdateret: 4. november 2026</em></p>

<h2>Hvem er ansvarlig?</h2>
<p>{NAME} ({DOMAIN}) drives af {COMPANY}, {ADDRESS}, CVR {CVR}. Du kan altid kontakte os på <a href="mailto:{EMAIL}">{EMAIL}</a>.</p>

<h2>Hvad {NAME} er</h2>
<p>{NAME} samler {NICHE} fra flere danske webshops ét sted. Du kan ikke købe noget direkte hos os. Når du klikker på "Gå til butik", sendes du videre til butikken, og det er butikken, der behandler dit køb og dine oplysninger i forbindelse med det.</p>

<h2>Hvilke oplysninger vi behandler</h2>
<h3>Når du besøger siden</h3>
<p>Vores webserver registrerer tekniske oplysninger som IP-adresse, browsertype og hvilke sider der vises. Det sker for at kunne levere siden, beskytte den mod misbrug og rette fejl. Oplysningerne gemmes i serverens logfiler i en begrænset periode, hvorefter de slettes automatisk. De bruges ikke til at følge dig eller lave profiler.</p>
<p>Retsgrundlaget er vores legitime interesse i at drive en sikker og velfungerende hjemmeside (databeskyttelsesforordningens artikel 6, stk. 1, litra f).</p>

<h3>Statistik med Matomo</h3>
<p>Vi bruger Matomo til at tælle besøg og se, hvilke sider der bliver brugt, så vi kan gøre siden bedre. Matomo kører på vores egen server, så oplysningerne deles ikke med andre. IP-adressen anonymiseres, og vi laver ikke profiler af dig eller bruger statistikken til markedsføring.</p>
<p>Uden dit samtykke tæller Matomo kun besøget uden at gemme noget på din enhed (ingen cookies). Siger du ja til statistik, sætter Matomo også cookies, så vi kan se, om du har besøgt siden før. Retsgrundlaget er vores legitime interesse i at forbedre siden (artikel 6, stk. 1, litra f) og, for cookies, dit samtykke (artikel 6, stk. 1, litra a).</p>

<h3>Når du tilmelder dig nyhedsbrevet</h3>
<p>Tilmelder du dig nyhedsbrevet, gemmer vi din e-mailadresse og tidspunktet for tilmeldingen. Vi bruger den kun til at sende dig nyhedsbrevet. Du skal bekræfte tilmeldingen via en mail, og du kan altid afmelde dig via linket nederst i hver mail.</p>
<p>Retsgrundlaget er dit samtykke (artikel 6, stk. 1, litra a). Nyhedsbrevet sendes via MailerLite, som behandler oplysningerne på vores vegne som databehandler. Vi sletter din e-mail, når du afmelder dig.</p>

<h3>Når du kontakter os</h3>
<p>Skriver du til os, bruger vi dine oplysninger til at svare dig. Vi sletter korrespondancen, når den ikke længere er relevant, og senest efter 12 måneder.</p>

<h2>Når du klikker videre til en butik</h2>
<p>Linkene til butikkerne går gennem affiliate-netværket Partner-ads. Partner-ads registrerer klikket, så butikken kan se, at du kom fra {NAME}, og så vi kan få provision, hvis du handler. Det sker hos Partner-ads og butikken – ikke på {DOMAIN} – og er omfattet af deres egne privatlivspolitikker. Vi får ingen oplysninger om, hvem du er, eller hvad du køber, kun at der er sket et salg.</p>

<h2>Produktbilleder</h2>
<p>Produktbillederne er leveret af butikkerne og gemmes på vores egen server. Når du ser et produkt, sendes din IP-adresse derfor ikke til butikkerne.</p>

<h2>Hvem vi deler oplysninger med</h2>
<p>Vi sælger aldrig dine oplysninger. Vi bruger disse databehandlere:</p>
<ul>
<li>SiteGround – drift af hjemmesiden og e-mail</li>
<li>MailerLite – udsendelse af nyhedsbrev</li>
</ul>

<h2>Dine rettigheder</h2>
<p>Du har ret til at få indsigt i de oplysninger, vi har om dig, og til at få dem rettet eller slettet. Du kan også gøre indsigelse mod behandlingen, få begrænset behandlingen og få udleveret dine oplysninger (dataportabilitet). Har du givet samtykke, kan du altid trække det tilbage.</p>
<p>Kontakt os på <a href="mailto:{EMAIL}">{EMAIL}</a>, hvis du vil bruge dine rettigheder. Er du utilfreds med, hvordan vi behandler dine oplysninger, kan du klage til Datatilsynet, datatilsynet.dk.</p>
HTML
	);
}

function playform_legal_cookies(): string {
	return playform_legal_fill( <<<'HTML'
<p><em>Senest opdateret: 4. november 2026</em></p>

<h2>Bruger {NAME} cookies?</h2>
<p>{NAME} bruger nødvendige cookies og – kun hvis du siger ja – cookies til statistik. Vi bruger ingen cookies til markedsføring. Du kan ændre dit valg når som helst via "Cookie-indstillinger" nederst på alle sider.</p>

<h2>Nødvendige cookies</h2>
<p>Disse cookies er nødvendige og kræver ikke samtykke:</p>
<ul>
<li><b>pf_consent</b> husker dit cookie-valg (hvilke kategorier du har sagt ja eller nej til, og hvornår). Den gemmes i op til 12 måneder, hvorefter vi spørger igen.</li>
<li>Login-cookies sættes kun, hvis du logger ind som redaktør i administrationen.</li>
</ul>

<h2>Statistik (kræver samtykke)</h2>
<p>Vi bruger Matomo, der kører på vores egen server, til besøgsstatistik. Uden samtykke tæller Matomo besøg uden cookies og med anonymiseret IP-adresse. Siger du ja til statistik, sætter Matomo disse cookies:</p>
<ul>
<li><b>_pk_id</b> genkender din browser ved næste besøg, så vi kan se antallet af unikke besøgende. Gemmes i 12 måneder.</li>
<li><b>_pk_ses</b> samler sidevisningerne fra ét besøg. Gemmes i 30 minutter.</li>
</ul>
<p>Oplysningerne bliver hos os og deles ikke med andre. Du kan sige nej og bruge siden på samme måde, og du kan altid trække dit samtykke tilbage – så sletter vi cookiesne fra din browser.</p>

<h2>Markedsføring</h2>
<p>Vi bruger ingen cookies til markedsføring. Begynder vi på det, sættes de først, når du har sagt ja.</p>

<h2>Når du klikker videre til en butik</h2>
<p>Når du klikker på "Gå til butik", sendes du via affiliate-netværket Partner-ads videre til butikken. Partner-ads og butikken sætter deres egne cookies hos sig, så de kan registrere, at du kom fra {NAME}, og vi kan få provision, hvis du handler. Det sker på deres sider og efter du har forladt {NAME}, og de er ansvarlige for det. Du kan læse mere i Partner-ads' og den enkelte butiks cookie- og privatlivspolitik og styre cookies i din browser.</p>

<h2>Spørgsmål</h2>
<p>Kontakt os på <a href="mailto:{EMAIL}">{EMAIL}</a>. {NAME} drives af {COMPANY}, CVR {CVR}.</p>
HTML
	);
}

/**
 * Opdaterer privatlivs- og cookiepolitikken, når PLAYFORM_LEGAL_VERSION hæves – men kun hvis siden stadig
 * er vores egen tekst (enten med skabelonens [felter] eller uændret siden sidste version). Redaktørens
 * egne rettelser overskrives aldrig.
 */
function playform_update_legal_pages(): void {
	if ( (int) get_option( 'playform_legal_version' ) >= PLAYFORM_LEGAL_VERSION ) {
		return;
	}
	foreach ( [ 'privatlivspolitik' => playform_legal_privacy(), 'cookies' => playform_legal_cookies() ] as $slug => $html ) {
		$page = get_page_by_path( $slug );
		if ( ! $page ) {
			continue;
		}
		$ours = str_contains( $page->post_content, '[e-mail]' ) || str_contains( $page->post_content, '[dato]' )
			|| md5( $page->post_content ) === get_post_meta( $page->ID, '_pf_legal_hash', true );
		if ( $ours ) {
			wp_update_post( [ 'ID' => $page->ID, 'post_content' => $html ] );
			update_post_meta( $page->ID, '_pf_legal_hash', md5( get_post_field( 'post_content', $page->ID, 'raw' ) ) );
		}
	}
	update_option( 'playform_legal_version', PLAYFORM_LEGAL_VERSION, false );
}
