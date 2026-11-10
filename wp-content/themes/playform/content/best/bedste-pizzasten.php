<?php
/**
 * Ny guide 10. nov. 2026 (søgeord "pizzasten", også "pizzasten til grill" og "biscotto sten"). Kun pizzasten – pizzastål
 * og bagestål har egne guides. Fakta kun fra butikkernes produktdata. Vi har ikke selv afprøvet produkterne.
 */
defined( 'ABSPATH' ) || exit;

return [
	'slug'     => 'bedste-pizzasten',
	'date'     => '2026-11-10 10:00:00',
	'keyword'  => 'pizzasten',
	'title'    => 'Pizzasten: Vores bud på de 5 bedste til ovn og grill',
	'excerpt'  => 'En pizzasten giver dig en sprød, gylden bund i ovnen og på grillen. Her er vores bud på de 5 bedste pizzasten i cordierit og keramik fra de butikker, vi samarbejder med – og hvad biscottosten kan.',
	'more_cat' => 'pizzasten-sten',
	'intro'    => <<<'HTML'
<p>Den hjemmebagte pizza har ofte samme problem: toppen er færdig, men bunden er bleg og blød. En bageplade bliver simpelthen ikke varm nok. En pizzasten løser det. Den suger varmen til sig, mens ovnen eller grillen varmer op, og sender den direkte op i dejen, så snart pizzaen lander. Samtidig trækker den porøse overflade fugt ud af bunden, så den bliver sprød i stedet for sej.</p>
<p>Her er vores bud på de 5 bedste pizzasten fra de butikker, vi samarbejder med – runde og firkantede, til ovnen, kuglegrillen, gasgrillen og kamadoen. Længere nede forklarer vi forskellen på cordierit, keramik og biscotto, og hvornår et pizzastål er et bedre valg.</p>
HTML,
	'picks'    => [
		[
			'ean'     => '5745000869107',
			'price'   => 495,
			'name'    => 'Fredstone rund pizzasten Ø 36 cm',
			'role'    => 'Bedst til grillen',
			'reviews' => [],
			'verdict' => 'Rund pizzasten på 1,6 cm, lavet til at bage sprød pizza på kuglegrill, gasgrill og kulgrill.',
			'why'     => <<<'HTML'
<p>Står din grill alligevel og bliver varm hele sommeren, kan den lige så godt bage pizza. Fredstones runde pizzasten på Ø 36 cm er lavet til netop det – på kuglegrill, gasgrill og kulgrill.</p>
<p>Med 1,6 cm i tykkelse er den en tyndere sten, og en tynd sten bliver hurtigere varm. Det betyder kortere ventetid, fra du tænder grillen, til den første pizza kan komme på.</p>
<p>Den runde form passer naturligt til en rund grill og til klassiske, runde pizzaer. Fredstone har også firkantede sten og sten skræddersyet til bestemte grills, så kig under <a href="/pizzasten/pizzasten-til-grill/">pizzasten til grill</a>, hvis du vil finde en, der passer præcis til din model.</p>
HTML,
			'good'    => [ 'Kuglegrill og kulgrill', 'Gasgrill', 'Kort opvarmning' ],
			'less'    => [ 'Store, firkantede pizzaer' ],
		],
		[
			'ean'     => '7350097263905',
			'price'   => 329,
			'name'    => 'Kamado Sumo dobbelt pizzasten 38 cm',
			'role'    => 'Bedst til kamado',
			'reviews' => [],
			'verdict' => 'Todelt pizzasten i kordierit, der tåler op til 800 °C – i ovnen og på gas- og kulgrill.',
			'why'     => <<<'HTML'
<p>En kamado kan blive rigtig varm, og så skal stenen kunne følge med. Kamado Sumos pizzasten er lavet af kordierit, der kan klare temperaturer helt op til 800 °C. Den er lavet til kamadogrillen, men du kan også bruge den i ovnen og på en gas- eller kulgrill.</p>
<p>Stenen er todelt og er lavet til at give en sprød bund og et jævnt bagt fyld. Med en diameter på 38 cm er der plads til en stor pizza på risten.</p>
<p>Overvejer du i stedet en ovn, der kun er lavet til pizza, så se vores bud på den <a href="/bedste-pizzaovn/">bedste pizzaovn</a>.</p>
HTML,
			'good'    => [ 'Kamadogrill', 'Meget høje temperaturer', 'Ovn og grill' ],
			'less'    => [ 'Dig, der vil have en firkantet sten til ovnen' ],
		],
		[
			'ean'     => '3289313476147',
			'price'   => 879,
			'name'    => 'Emile Henry rillet pizzasten Ø 40 cm',
			'role'    => 'Premiumvalget',
			'reviews' => [],
			'verdict' => 'Rillet pizzasten i ildfast HR-keramik fra Emile Henry med jævn varmefordeling over hele fladen.',
			'why'     => <<<'HTML'
<p>Emile Henrys pizzasten skiller sig ud på to måder. Den er lavet af ildfast HR-keramik i stedet for rå cordierit, og overfladen er rillet. Resultatet er en sten, der fordeler varmen jævnt, så bunden bliver bagt ensartet fra midten og ud til kanten.</p>
<p>Med en diameter på 40 cm er der god plads til en stor pizza, og den røde farve gør den til et flot indslag i køkkenet.</p>
<p>Den er til dig, der bager pizza i ovnen ofte og gerne vil investere i en sten, du får glæde af længe.</p>
HTML,
			'good'    => [ 'Pizza i ovnen hver uge', 'Store pizzaer', 'Dig, der vil have en sten med farve' ],
			'less'    => [ 'Det stramme budget' ],
		],
		[
			'ean'     => '060162698149',
			'price'   => 399,
			'name'    => 'Broil King pizzasten 38 cm',
			'role'    => 'Tykkeste sten',
			'reviews' => [],
			'verdict' => 'Ekstra tyk pizzasten på 1,9 cm i keramisk komposit, der fordeler varmen og skærmer bunden mod direkte flammer.',
			'why'     => <<<'HTML'
<p>På en gasgrill sidder flammerne lige under stenen, og det kan give en sort bund, før osten er smeltet. Broil Kings pizzasten er 1,9 cm tyk – tykkere end de fleste – så den fordeler varmen jævnt og isolerer pizzaen mod de direkte flammer.</p>
<p>Materialet er keramisk komposit, der tåler at bage ved høj temperatur. En tyk sten holder også godt på varmen, så du kan bage flere pizzaer i træk, uden at bunden bliver blegere for hver gang.</p>
<p>Den passer godt til dig, der laver pizzaaften for mange og vil kunne sætte den ene pizza på efter den anden.</p>
HTML,
			'good'    => [ 'Gasgrill med kraftige brændere', 'Mange pizzaer i træk', 'Pizzaaftener for familien' ],
			'less'    => [ 'Dig, der vil have kort opvarmningstid' ],
		],
		[
			'ean'     => '7350101111673',
			'price'   => 249,
			'name'    => 'Mareld pizzasten 40 x 30 cm',
			'role'    => 'Bedst til prisen',
			'reviews' => [],
			'verdict' => 'Rektangulær pizzasten i cordierit til en lav pris – et nemt sted at starte.',
			'why'     => <<<'HTML'
<p>Er du ny i pizzabagning, er Mareld pizzasten en billig vej til en sprødere bund. Den er lavet af cordierit, det samme materiale, som mange dyrere pizzasten bruger, fordi det tåler høj varme og pludselige temperaturskift bedre end almindelig keramik.</p>
<p>Formen er rektangulær, så den udnytter pladsen på ovnristen godt. Det giver plads til en stor pizza eller et par mindre – og du kan også bage brød og focaccia på den.</p>
<p>Vil du hellere have en rund sten, findes der en rund Mareld på 42 cm. Se alle <a href="/pizzasten/rund-pizzasten/">runde pizzasten</a>.</p>
HTML,
			'good'    => [ 'Almindelig ovn', 'Lavt budget', 'Brød og focaccia' ],
			'less'    => [ 'Runde kuglegrills' ],
		],
	],
	'guide'    => <<<'HTML'
<h2>Sådan vælger du den rigtige pizzasten</h2>
<p>En pizzasten er en enkel ting, men materiale, tykkelse og form gør en stor forskel for resultatet. Her er det, du skal tage stilling til. Se alle <a href="/pizzasten/pizzasten-sten/">pizzasten</a> med aktuelle priser.</p>
<h3>Cordierit, keramik og biscotto</h3>
<p><strong>Cordierit</strong> (også skrevet kordierit) er det mest almindelige materiale. Det tåler høj varme og skiftende temperaturer godt, og det er derfor, de fleste pizzasten til ovn og grill er lavet af det. <strong>Keramik og keramisk komposit</strong> bruges i nogle af de mere eksklusive sten. Glaserede udgaver er lettere at holde rene og kan stå på bordet som serveringsfad.</p>
<p><strong>Biscottosten</strong> er en italiensk type brændt ler, kendt fra de napolitanske pizzeriaer. Leret leder varmen langsommere end cordierit, så bunden ikke brænder, selv når ovnen er meget varm. Derfor er biscotto populær i pizzaovne, der kommer op over 400 °C. Biscottosten sælges som regel som erstatningssten til en bestemt pizzaovn, så tjek målene, før du køber. Find dem under <a href="/tilbehoer/">tilbehør til pizzaovne</a>.</p>
<h3>Pizzasten til grill</h3>
<p>På grillen kommer varmen nedefra, og det er bunden, der risikerer at brænde. Vælg en tykkere sten, hvis du har en gasgrill med kraftige brændere, og hold låget lukket, så varmen også kommer fra oven. Til kuglegrill og kamado er en rund sten oplagt, mens en firkantet ofte passer bedst på gasgrillens rist. Se <a href="/pizzasten/pizzasten-til-grill/">pizzasten til grill</a>.</p>
<h3>Tykkelse og størrelse</h3>
<p>En tynd sten bliver hurtigt varm, mens en tyk sten holder mere varme og kan bage flere pizzaer i træk. Mål din ovn eller grill, og efterlad et par centimeter rundt om stenen, så den varme luft kan cirkulere.</p>
<h3>Pizzasten eller pizzastål?</h3>
<p>Et pizzastål leder varmen hurtigere end en sten, så bunden får et kraftigere skub, og det kan ikke knække. Det gør stålet til et godt valg i en almindelig ovn, der ikke kommer over 250–275 °C. En pizzasten er lettere, ofte billigere og mere skånsom ved meget høje temperaturer, så den er stærk på grillen og i pizzaovnen. Læs mere i vores guides til <a href="/bedste-pizzastaal/">pizzastål</a> og <a href="/bedste-bagestaal/">bagestål</a>.</p>
<h3>Brug og pleje</h3>
<p>Læg stenen i den kolde ovn eller på den kolde grill, og varm den op sammen med den – mindst 30–45 minutter. En kold sten i en varm ovn kan revne. Drys lidt mel eller semolina på en <a href="/bedste-pizzaspade/">pizzaspade</a>, så pizzaen glider let over på stenen. Rengør stenen med en skraber eller børste, når den er kølet af, og vask den aldrig med sæbe – den porøse sten suger det op. Mørke pletter er helt normale og et tegn på, at den bliver brugt.</p>
HTML,
	'faq'      => [
		[ 'Hvad er den bedste pizzasten?', 'Det afhænger af, hvor du bager. Til grill er en rund eller tyk cordieritsten et godt valg, til ovnen en rektangulær sten, der udnytter risten, og til pizzaovne over 400 °C er biscotto populær, fordi bunden ikke brænder så let.' ],
		[ 'Hvad er en biscottosten?', 'En biscottosten er lavet af brændt ler efter italiensk tradition. Den leder varmen langsommere end cordierit og bruges især i varme pizzaovne, hvor en almindelig sten kan give en brændt bund.' ],
		[ 'Kan man bruge en pizzasten på grillen?', 'Ja. De fleste pizzasten kan bruges på kuglegrill, gasgrill og kamado. Varm stenen op sammen med grillen, og hold låget lukket, mens pizzaen bager.' ],
		[ 'Hvorfor revner min pizzasten?', 'Som regel på grund af et pludseligt temperaturskift – fx en kold sten i en varm ovn eller vand på en varm sten. Varm altid stenen op langsomt, og lad den køle af i ovnen.' ],
		[ 'Er pizzastål bedre end pizzasten?', 'Ikke bedre, men anderledes. Stål giver en hurtigere og kraftigere varme i en almindelig ovn og kan ikke knække. Sten er lettere, billigere og mere skånsom ved meget høje temperaturer.' ],
	],
];
