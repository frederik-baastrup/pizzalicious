<?php
/**
 * Omskrevet fra det gamle pizzalicious.dk (/bedste-pizzastaal-test/). Fakta kun fra butikkernes produktdata. Ingen påstand om egne test.
 */
defined( 'ABSPATH' ) || exit;

return [
	'slug'     => 'bedste-pizzastaal',
	'title'    => 'Pizzastål: Vores bud på de 5 bedste til pizza i almindelig ovn',
	'excerpt'  => 'Et pizzastål giver en sprød bund i den almindelige ovn – og knækker ikke som en pizzasten. Her er vores bud på de 5 bedste pizzastål fra de butikker, vi samarbejder med.',
	'more_cat' => 'bagestaal',
	'intro'    => <<<'HTML'
<p>Et pizzastål er en tyk stålplade, du lægger i ovnen og varmer op, før pizzaen kommer på. Stål leder varmen hurtigere end sten, så bunden får et kraftigt skub af varme med det samme. Det er den nemmeste måde at få en sprød pizza ud af en almindelig ovn, der ikke kan blive lige så varm som en pizzaovn.</p>
<p>Et pizzastål kan heller ikke knække, hvis det bliver for hurtigt varmt eller koldt, som en pizzasten kan. Herunder er vores bud på de 5 bedste pizzastål hos de butikker, vi samarbejder med, lige nu.</p>
HTML,
	'picks'    => [
		[
			'ean'     => '7350100570167',
			'price'   => 599,
			'name'    => 'Gourmetstål rundt pizzastål 39 cm',
			'role'    => 'Bedst samlet',
			'reviews' => [],
			'verdict' => 'Rundt pizzastål fra svenske Gourmetstål – formet som en klassisk pizzasten, men i stål, der ikke knækker.',
			'why'     => <<<'HTML'
<p>Gourmetstål er et svensk firma. Det runde pizzastål er inspireret af de klassiske pizzasten, men lavet i et robust materiale, så du ikke skal være nervøs for, at det knækker.</p>
<p>Den runde form passer til en kuglegrill og en kamado, og med 39 cm er der plads til en stor pizza. Det runde stål findes også i andre størrelser og med greb.</p>
HTML,
			'good'    => [ 'Ovn og grill', 'Store runde pizzaer' ],
			'less'    => [ 'Brød på hele pladen' ],
		],
		[
			'ean'     => '7350101311721',
			'price'   => 439,
			'name'    => 'Kockums Jernverk bage/pizzastål 35 cm',
			'role'    => 'Bedst til pizza og brød',
			'reviews' => [],
			'verdict' => 'Svensk pizzastål i 6 mm kulstofstål, der holder godt på varmen.',
			'why'     => <<<'HTML'
<p>Kockums Jernverk laver pizzastålet i Sverige af 6 mm kulstofstål fra SSAB. Stålet holder godt på varmen, og det er det, der giver brød og pizza en god skorpe og krumme.</p>
<p>Det er et alsidigt stål til dig, der både vil bage pizza og brød på det.</p>
HTML,
			'good'    => [ 'Pizza og brød', 'Dig, der vil have svensk kvalitet' ],
			'less'    => [ 'Meget store pizzaer' ],
		],
		[
			'ean'     => '7350101114728',
			'price'   => 199,
			'name'    => 'Mareld pizzastål 37x33 cm',
			'role'    => 'Bedst til prisen',
			'reviews' => [],
			'verdict' => 'Kraftig, valset stålplade på 6 mm til ovnen og grillen – til en lav pris.',
			'why'     => <<<'HTML'
<p>Mareld pizzastål er en kraftig, valset plade på 6 millimeter, som du sætter i ovnen eller på grillen. Pladen giver en jævn og høj varme til pizzaen.</p>
<p>Til prisen er det en billig måde at prøve pizzastål på. Den firkantede form udnytter pladsen i ovnen godt.</p>
HTML,
			'good'    => [ 'Lavt budget', 'Firkantede pizzaer og brød' ],
			'less'    => [ 'Runde kamadogrills' ],
		],
		[
			'ean'     => '7392750587157',
			'price'   => 329,
			'name'    => 'Dorre bage-/pizzastål',
			'role'    => 'Godt alternativ',
			'reviews' => [],
			'verdict' => 'Bage- og pizzastål i kulstofstål, der giver en sprød skorpe som i Italien.',
			'why'     => <<<'HTML'
<p>Dorre bagestål er lavet til at bage sprøde pizzaer med en sprød skorpe, "ligesom du får dem i Italien", og det er lavet af kulstofstål. Det er et enkelt, billigt alternativ til de svenske mærker.</p>
HTML,
			'good'    => [ 'Lavt budget', 'Pizza i almindelig ovn' ],
			'less'    => [ 'Dig, der vil have det tykkeste stål' ],
		],
		[
			'ean'     => '5700437334246',
			'price'   => 579,
			'name'    => 'Bagestålet',
			'role'    => 'Dansk og 6 mm tykt',
			'reviews' => [],
			'verdict' => 'Det danske pizzastål: 6 mm højkonduktivt stål, der giver pizzabunden et hurtigt varmeskub – med 10 års brudgaranti.',
			'why'     => <<<'HTML'
<p>Drømmer du om en pizza med sprød, plettet bund fra din helt almindelige ovn, er Bagestålet et oplagt bud. Det er et dansk produkt, og stålet er 6 mm tykt kvalitetsstål fra svenske SSAB. Det leder varmen hurtigt, så bunden får fart på, så snart pizzaen lander.</p>
<p>Pladen er firkantet og findes i flere størrelser, så den udnytter hele ristens flade. Dermed er der også plads til pizza på plade, focaccia og brød, når du ikke bager runde pizzaer. Den er lavet til ovnen, men du kan også tage den med ud på grillen.</p>
<p>Stålet er fødevaregodkendt, og du får 10 års brudgaranti. Med tiden bliver overfladen mørkere, og det er bare et tegn på, at den bliver brugt.</p>
HTML,
			'good'    => [ 'Sprød pizzabund i almindelig ovn', 'Dig, der også bager brød', 'Ovn og grill' ],
			'less'    => [ 'Runde kamadogrills' ],
		],
	],
	'guide'    => <<<'HTML'
<h2>Sådan bruger du et pizzastål</h2>
<h3>Varm det godt op</h3>
<p>Læg stålet på den øverste eller næstøverste rille, og tænd ovnen på højeste temperatur mindst 45 minutter før, pizzaen skal ind. Stålet skal være gennemvarmt – det er varmen i stålet, der bager bunden.</p>
<h3>Brug grillelementet</h3>
<p>Har ovnen grill, så slå den til de sidste minutter. Så får toppen og kanten farve, mens stålet bager bunden. Det kommer tæt på resultatet fra en pizzaovn.</p>
<h3>Tykkelse</h3>
<p>Et tykt stål holder mere varme og kan bage flere pizzaer i træk uden at blive koldt, men det er tungt og tager længere tid at varme op. 6 mm er et godt kompromis til de fleste ovne.</p>
<h3>Pleje</h3>
<p>Stål kan ruste. Tør det af efter brug, og giv det et tyndt lag olie, så det holder sig.</p>
<p>Se også vores guide til <a href="/bedste-bagestaal/">bagestål</a> og til <a href="/bedste-pizzaspade/">pizzaspader</a>, og se alle <a href="/pizzasten/bagestaal/">bagestål og pizzastål</a> og <a href="/pizzasten/pizzasten-sten/">pizzasten</a>.</p>
HTML,
	'faq'      => [
		[ 'Hvad er forskellen på pizzastål og pizzasten?', 'Stål leder varmen hurtigere og knækker ikke. En pizzasten er billigere og lettere, men kan revne ved store temperaturforskelle.' ],
		[ 'Kan et pizzastål bruges på grillen?', 'Ja, de fleste pizzastål kan bruges både i ovnen og på grillen. Tjek butikkens oplysninger for den enkelte model.' ],
		[ 'Skal et pizzastål vaskes?', 'Skrab det rent, og tør det af. Undgå opvaskemaskinen, og giv stålet et tyndt lag olie, så det ikke ruster.' ],
	],
];
