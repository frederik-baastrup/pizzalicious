<?php
/**
 * Siden "Sådan fungerer {navn}" (/saadan-fungerer-det/): hvor produkterne kommer fra (butikkernes egne feeds via
 * Partner-ads), hvordan vi tjener penge, og hvad loven siger om at vise mærkevarer. Ejerens ønske 5. nov. 2026:
 * en side, han kan sende til butikker og leverandører, der spørger, om vi må vise produkterne.
 * Teksten ligger her i koden (ikke i databasen), så den er ens på alle sites og kan rettes ét sted.
 */

defined( 'ABSPATH' ) || exit;

const PLAYFORM_HOW_SLUG = 'saadan-fungerer-det';

function playform_how_url( string $anchor = '' ): string {
	return home_url( '/' . PLAYFORM_HOW_SLUG . '/' ) . ( $anchor !== '' ? '#' . $anchor : '' );
}

function playform_how_title(): string {
	return 'Sådan fungerer ' . playform_site( 'name' );
}

/** Opretter siden, hvis den mangler (kaldes efter hver udrulning). Indholdet skrives af skabelonen. */
function playform_ensure_how_page(): void {
	$page = get_page_by_path( PLAYFORM_HOW_SLUG );
	if ( ! $page ) {
		wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => PLAYFORM_HOW_SLUG,
				'post_title'   => playform_how_title(),
				'post_excerpt' => 'Hvor produkterne kommer fra, hvordan vi tjener penge, og hvorfor vi må vise dem.',
				'post_content' => '',
			]
		);
	} elseif ( $page->post_title !== playform_how_title() ) {
		wp_update_post( [ 'ID' => $page->ID, 'post_title' => playform_how_title() ] );
	}
}

function playform_how_html(): string {
	$name   = esc_html( playform_site( 'name' ) );
	$niche  = esc_html( playform_site( 'niche' ) );
	$email  = sanitize_email( playform_site( 'email' ) );
	$mail   = '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
	$shops  = function_exists( 'playform_shop_names' ) ? count( playform_shop_names() ) : 0;
	$shopsl = '<a href="' . esc_url( home_url( '/vores-butikker/' ) ) . '">' . ( $shops > 1 ? "$shops danske webshops" : 'danske webshops' ) . '</a>';
	$money  = esc_url( home_url( '/saadan-tjener-vi-penge/' ) );
	$co     = esc_html( playform_site( 'company' ) . ', CVR ' . playform_site( 'cvr' ) );

	return <<<HTML
<p class="lead">$name er en sammenligningstjeneste for $niche, ikke en butik. Vi sælger ikke selv noget, vi har intet lager, og vi importerer ingen varer. Alt, du ser her, er tilbud fra $shopsl. Når du klikker på "Gå til butik", handler du direkte med butikken.</p>

<h2 id="feeds">Hvor kommer produkterne fra?</h2>
<p>Butikkerne stiller selv deres produkter til rådighed i et såkaldt produktfeed: en fil med navn, pris, lagerstatus, billeder og beskrivelse af de varer, butikken ønsker at markedsføre. Feedet leveres gennem det danske affiliate-netværk Partner-ads, og butikken laver det netop for, at sider som $name må vise produkterne.</p>
<ul>
<li>Vi viser kun produkter fra butikker, der har et affiliate-program med produktfeed, og som har godkendt $name som partner.</li>
<li>Vi viser kun de produkter, butikken selv har lagt i sit feed, til den pris og med den lagerstatus, butikken oplyser.</li>
<li>Vi henter feedsne to gange i døgnet. Ændrer butikken en pris eller fjerner et produkt fra feedet, slår det igennem her ved næste opdatering.</li>
<li>Billeder og produktdata stammer fra butikkens feed. Billederne gemmes på vores egen server, så siden er hurtig, og så dine besøg ikke deles med andre.</li>
</ul>

<h2 id="penge">Sådan tjener vi penge</h2>
<p>$name er gratis at bruge. Når du klikker videre til en butik og handler, får vi en provision fra butikken. Det koster ikke dig noget ekstra. Derfor er alle links til butikkerne annoncer, og siden er tydeligt mærket med, at den indeholder reklamelinks. Læs mere under <a href="$money">Sådan tjener vi penge</a>.</p>
<p>Som tilbud viser vi kun butikkernes egne tilbud, og kun i højst 14 dage. Har vi selv fulgt prisen hos butikken i de 30 dage før tilbuddet, viser vi den laveste pris i perioden som førpris. Ellers viser vi butikkens egen førpris, tydeligt mærket som butikkens.</p>

<h2 id="leverandoerer">Til leverandører, distributører og mærkeejere</h2>
<p>Hvert produkt på $name er et tilbud fra en navngiven dansk butik, som selv har valgt at markedsføre varen gennem sit feed. Vi køber og sælger ikke varerne. Spørgsmål om, hvorvidt en bestemt butik må forhandle et produkt, hører derfor hjemme mellem leverandøren og butikken. Fjerner butikken produktet fra sit feed, forsvinder det også fra $name ved næste opdatering.</p>
<p>Vi skriver aldrig, at vi er forhandler, officiel partner eller autoriseret af et mærke. Mærkenavne bruges kun til at fortælle, hvilken vare der er tale om.</p>

<h2 id="lovgivning">Hvad siger loven?</h2>
<p>Vi får af og til spørgsmålet, om en importør eller distributør kan forhindre, at et mærke vises eller sælges i Danmark. Kort fortalt: Når originale varer først er solgt lovligt i EU, kan mærkeejeren ikke styre, hvem der sælger dem videre. Her er de vigtigste regler:</p>
<h3>Videresalg af originale varer (konsumption)</h3>
<p>Når en vare er bragt i omsætning i EU/EØS af varemærkeindehaveren eller med dennes samtykke, er varemærkeretten til den enkelte vare "brugt op". Mærkeejeren kan derfor ikke bruge sit varemærke til at forhindre, at varen sælges videre eller markedsføres under mærkets navn. Det følger af varemærkeloven § 10 a og artikel 15 i EU's varemærkedirektiv. Undtagelsen er, hvis der er en rimelig grund, fx at varen er ændret eller forringet.</p>
<h3>Fri bevægelighed i EU</h3>
<p>Varer, der er lovligt solgt i ét EU-land, må som udgangspunkt sælges i de andre (artikel 34 i Traktaten om Den Europæiske Unions Funktionsmåde). En eksklusiv distributionsaftale for Danmark binder kun aftalens parter. Den giver ikke distributøren ret til at forhindre andre i at sælge varer, der er købt lovligt et andet sted i EU.</p>
<h3>Salg og annoncering online</h3>
<p>EU's konkurrenceregler for leverandøraftaler (gruppefritagelsesforordningen for vertikale aftaler, forordning 2022/720) betyder, at en leverandør som udgangspunkt ikke må forbyde sine forhandlere at sælge online eller helt afskære dem fra en hel annoncekanal som prissammenligningstjenester. Leverandøren kan godt stille kvalitetskrav til, hvordan varen præsenteres, men det er et forhold mellem leverandøren og butikken.</p>
<h3>Mærkenavne og billeder</h3>
<p>Det er lovligt at bruge et mærkenavn til at angive, hvilken vare der sælges, så længe det sker i overensstemmelse med god markedsføringsskik og ikke giver indtryk af en forbindelse til mærkeejeren, som ikke findes (artikel 14 i EU's varemærkedirektiv). Billeder og tekster kommer fra butikkens eget feed, som butikken stiller til rådighed til netop denne brug.</p>
<h3>Markedsføring og priser</h3>
<p>Vi følger markedsføringslovens krav om, at reklame skal kunne genkendes som reklame, og prismærkningsreglernes krav om, at en førpris skal være den laveste pris i de 30 dage før et tilbud.</p>
<p class="note">Teksten er en generel orientering og ikke juridisk rådgivning.</p>

<h2 id="kontakt">Mener du, et produkt ikke bør vises?</h2>
<p>Er du butik, leverandør eller rettighedshaver og mener, at et produkt, et billede eller en tekst ikke bør vises på $name, så skriv til $mail med et link til produktet og en kort begrundelse. Vi svarer hurtigt og fjerner indholdet, hvis der er grund til det. Er du butik og vil have dine produkter med, er du også velkommen til at skrive.</p>
<p>$name drives af $co.</p>
HTML;
}
