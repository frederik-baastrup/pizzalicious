<?php
/**
 * Siden "Sådan fungerer {navn}" (/saadan-fungerer-det/): hvor produkterne kommer fra (butikkernes egne feeds via
 * Partner-ads), hvordan vi tjener penge, og hvad loven siger om at vise mærkevarer. Ejerens ønske 5. nov. 2026:
 * en side, han kan sende til butikker og leverandører, der spørger, om vi må vise produkterne.
 * Teksten ligger her i koden (ikke i databasen). Den er bevidst formuleret forskelligt på hvert site (samme fakta),
 * så søgemaskiner ikke ser dublet-indhold på tværs af domænerne. Ret fakta på alle sites, men kopiér ikke teksten.
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
				'post_excerpt' => 'Om produktfeeds, provision og reglerne for at vise mærkevarer.',
				'post_content' => '',
			]
		);
	} else {
		$upd = [];
		if ( $page->post_title !== playform_how_title() ) {
			$upd['post_title'] = playform_how_title();
		}
		// Uddraget (bruges som metabeskrivelse) skiftes kun, hvis det stadig er den gamle fælles tekst.
		if ( $page->post_excerpt === 'Hvor produkterne kommer fra, hvordan vi tjener penge, og hvorfor vi må vise dem.' ) {
			$upd['post_excerpt'] = 'Om produktfeeds, provision og reglerne for at vise mærkevarer.';
		}
		if ( $upd ) {
			wp_update_post( [ 'ID' => $page->ID ] + $upd );
		}
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
<p class="lead">Hos $name kan du sammenligne $niche, men du kan ikke købe noget direkte hos os. Vi er ingen butik: Vi har intet lager, sælger ikke selv og importerer ingen varer. Hvert produkt på siden kommer fra en af de $shopsl, vi samarbejder med, og når du trykker på "Gå til butik", er det butikken, du handler med.</p>

<h2 id="feeds">Hvor kommer pizzaovnene og tilbehøret fra?</h2>
<p>Alle data kommer fra butikkerne selv. Hver butik laver et produktfeed – en fil med navn, pris, lagerstatus, billeder og beskrivelse af de varer, den vil markedsføre – og deler det via Partner-ads, som er et dansk affiliate-netværk. Hele idéen med feedet er, at sider som $name må vise produkterne.</p>
<ul>
<li>Kun butikker med et affiliate-program og et produktfeed, som har sagt ja til $name som partner, er med.</li>
<li>Vi viser alene de varer, butikken har lagt i feedet, med den pris og lagerstatus, der står i feedet.</li>
<li>To gange i døgnet henter vi nye data. En ny pris eller en vare, der er fjernet fra feedet, bliver derfor opdateret her ved næste kørsel.</li>
<li>Produktbilleder og -data er butikkens egne. Vi lægger billederne på vores egen server, så siden indlæses hurtigt, og så andre ikke får oplysninger om dine besøg.</li>
</ul>

<h2 id="penge">Sådan tjener vi penge</h2>
<p>Du bruger $name gratis. Vi lever af provision: Når du går videre til en butik og køber noget, betaler butikken os en andel af salget – uden at prisen stiger for dig. Links til butikkerne er derfor annoncer, og det fremgår tydeligt af siden, at den indeholder reklamelinks. Mere om det på <a href="$money">Sådan tjener vi penge</a>.</p>
<p>På tilbudssiderne finder du kun tilbud, som butikkerne selv har sat op, og de vises i højst 14 dage. Hvis vi har registreret butikkens pris i de 30 dage før tilbuddet, er førprisen den laveste pris i den periode. Hvis ikke, viser vi butikkens egen førpris, og så står der tydeligt, at det er butikkens.</p>

<h2 id="lovgivning">Hvad siger reglerne?</h2>
<p>Et spørgsmål, vi møder en gang imellem, er, om en importør eller distributør kan stoppe et mærke i at blive vist eller solgt i Danmark. Svaret i korte træk: Er originale varer først solgt lovligt i EU, har mærkeejeren ikke kontrol over, hvem der videresælger dem. Reglerne bag ser sådan ud:</p>
<h3>Videresalg af originale varer (konsumption)</h3>
<p>Er en vare sat i omsætning i EU/EØS af varemærkeindehaveren eller med dennes samtykke, er varemærkeretten til netop den vare konsumeret. Mærkeejeren kan altså ikke bruge varemærket til at stoppe videresalg eller markedsføring af varen under mærkets navn. Det fremgår af § 10 a i varemærkeloven og artikel 15 i EU's varemærkedirektiv. Kun hvis der er en rimelig grund, gælder det ikke – det kan være, at varen er ændret eller forringet.</p>
<h3>Fri bevægelighed i EU</h3>
<p>Som hovedregel må en vare, der er lovligt solgt i ét EU-land, også sælges i de andre EU-lande (artikel 34 i Traktaten om Den Europæiske Unions Funktionsmåde). En eksklusiv distributionsaftale for Danmark forpligter kun de parter, der har indgået den, og den giver ikke distributøren nogen ret til at stoppe andres salg af varer, der er indkøbt lovligt i et andet EU-land.</p>
<h3>Salg og annoncering online</h3>
<p>Med EU's konkurrenceregler for leverandøraftaler (gruppefritagelsesforordningen for vertikale aftaler, forordning 2022/720) kan en leverandør som udgangspunkt ikke forbyde forhandlerne at sælge online eller lukke dem helt ude fra en hel annoncekanal som fx prissammenligningstjenester. Krav til, hvordan varen præsenteres, må leverandøren gerne stille – men de hører hjemme i forholdet mellem leverandøren og butikken.</p>
<h3>Mærkenavne og billeder</h3>
<p>Et mærkenavn må bruges til at vise, hvilken vare der sælges, forudsat at det sker i tråd med god markedsføringsskik og ikke antyder en forbindelse til mærkeejeren, som ikke er der (artikel 14 i EU's varemærkedirektiv). Billederne og teksterne har vi fra butikkens feed, som butikken stiller til rådighed præcis til det formål.</p>
<h3>Markedsføring og priser</h3>
<p>Reklame skal kunne genkendes som reklame efter markedsføringsloven, og efter prismærkningsreglerne skal førprisen ved et tilbud være den laveste pris fra de 30 dage før. Begge dele følger vi.</p>
<p class="note">Ovenstående er en generel orientering og kan ikke erstatte juridisk rådgivning.</p>

<h2 id="leverandoerer">Til leverandører, distributører og mærkeejere</h2>
<p>$name køber og sælger ikke varer. Hvert produkt her er et tilbud fra en navngiven dansk butik, der selv har besluttet at markedsføre varen gennem sit feed. Har du spørgsmål til, om en bestemt butik må forhandle et produkt, skal det derfor afklares mellem leverandøren og butikken. Så snart butikken fjerner varen fra feedet, forsvinder den fra $name ved den efterfølgende opdatering.</p>
<p>Vi præsenterer os aldrig som forhandler, officiel partner eller autoriseret af et mærke. Mærkenavne står der kun for at vise, hvilken vare det er.</p>

<h2 id="kontakt">Bør et produkt ikke vises her?</h2>
<p>Skriv til $mail, hvis du som butik, leverandør eller rettighedshaver mener, at et produkt, et billede eller en tekst ikke bør stå på $name. Send et link til produktet og en kort begrundelse med. Vi svarer hurtigt, og er der grund til det, fjerner vi indholdet. Vil din butik gerne have sine produkter med på siden, er du også velkommen til at kontakte os.</p>
<p>$name drives af $co.</p>
HTML;
}
