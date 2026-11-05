<?php
/**
 * Omskrevet fra det gamle pizzalicious.dk (/bedste-bagestaal/). Fakta kun fra butikkernes produktdata. Ingen påstand om egne test.
 */
defined( 'ABSPATH' ) || exit;

return [
	'slug'     => 'bedste-bagestaal',
	'title'    => 'Bagestål: Vores bud på de 4 bedste til sprød pizza og brød',
	'excerpt'  => 'Et bagestål fylder hele ovnristen og bager både pizza, brød og focaccia med en sprød bund. Her er vores bud på de 4 bedste bagestål fra danske butikker.',
	'more_cat' => 'bagestaal',
	'intro'    => <<<'HTML'
<p>Et bagestål er en stor, firkantet stålplade, der ligger i ovnen som en ekstra bund. Det fungerer som en pizzasten, men leder varmen hurtigere og kan ikke knække. Fordi det fylder det meste af ovnristen, er det lige så godt til brød, focaccia og pizza på plade som til runde pizzaer.</p>
<p>Herunder er vores bud på de 4 bedste bagestål hos danske butikker lige nu. Er du mest til runde pizzaer, så se også vores guide til <a href="/bedste-pizzastaal/">pizzastål</a>.</p>
HTML,
	'picks'    => [
		[
			'ean'     => '7350100570020',
			'price'   => 629,
			'name'    => 'Gourmetstål Classic',
			'role'    => 'Bedst samlet',
			'reviews' => [],
			'verdict' => 'Klassisk bagestål på 9 kg, der ikke knækker ved store temperaturudsving.',
			'why'     => <<<'HTML'
<p>Gourmetstål Classic er ifølge butikken det samme som en pizzasten, bare af stål. Forskellen er, at stålet ikke knækker ved store temperaturudsving, og at pladen vejer 9 kg.</p>
<p>Vægten er en fordel: der er meget varme gemt i pladen, så du kan bage flere pizzaer eller et stort brød uden at stålet bliver koldt. Til gengæld skal du løfte 9 kg ind og ud af ovnen. Valget er vores vurdering ud fra butikkens oplysninger.</p>
HTML,
			'good'    => [ 'Flere pizzaer i træk', 'Brød og focaccia' ],
			'less'    => [ 'Dig, der vil have noget let' ],
		],
		[
			'ean'     => '7350100570037',
			'price'   => 699,
			'name'    => 'Gourmetstål Norrland',
			'role'    => 'Bedst premium',
			'reviews' => [],
			'verdict' => 'Gourmetståls premium-model – til dig, der bager meget.',
			'why'     => <<<'HTML'
<p>Norrland er ifølge butikken den ultimative premium-model i Gourmetståls kollektion. Som de andre stål fra mærket fungerer den som en pizzasten, men med mindre risiko for, at den flækker.</p>
<p>Bager du ofte og vil have det bedste fra mærket, er det denne. Valget er vores vurdering ud fra butikkens oplysninger.</p>
HTML,
			'good'    => [ 'Dig, der bager hver uge' ],
			'less'    => [ 'Lavt budget' ],
		],
		[
			'ean'     => '7350100570013',
			'price'   => 499,
			'name'    => 'Gourmetstål Light',
			'role'    => 'Bedst let',
			'reviews' => [],
			'verdict' => 'Lettere bagestål i varmevalset stål, der tåler store temperaturudsving.',
			'why'     => <<<'HTML'
<p>Light er den lette udgave fra Gourmetstål. Butikken skriver, at stålet er varmevalset og kan tåle store temperaturudsving, og at det fungerer præcis som en pizzasten.</p>
<p>Et lettere stål bliver hurtigere varmt og er nemmere at håndtere, men holder lidt mindre varme. Det er et godt valg til den almindelige ovn, hvor du bager én pizza ad gangen. Valget er vores vurdering.</p>
HTML,
			'good'    => [ 'Dig, der skal løfte stålet ofte', 'Små ovne' ],
			'less'    => [ 'Mange pizzaer i træk' ],
		],
		[
			'ean'     => '7350101312131',
			'price'   => 699,
			'name'    => 'Kockums Jernverk bage/pizzastål opfoldet 37x32 cm',
			'role'    => 'Bedst til grill og bål',
			'reviews' => [],
			'verdict' => 'Svensk bagestål med opfoldede kanter, der kan bruges i ovn, på grill, over bål og på kogepladen.',
			'why'     => <<<'HTML'
<p>Kockums Jernverk laver dette stål i Sverige af kulstofstål. Kanterne er foldet op, så det er 3 cm højt, 33 cm bredt og 37 cm langt, skriver butikken, og det kan bruges i ovn, på grill, over åben ild og på alle typer kogeplader.</p>
<p>De høje kanter gør det til en mellemting mellem et bagestål og en pande – godt til pizza på plade og til at stege direkte på stålet. Valget er vores vurdering ud fra butikkens oplysninger.</p>
HTML,
			'good'    => [ 'Grill og bål', 'Pizza på plade' ],
			'less'    => [ 'Runde napolitanske pizzaer' ],
		],
	],
	'guide'    => <<<'HTML'
<h2>Sådan vælger du et bagestål</h2>
<h3>Størrelse</h3>
<p>Mål ovnen, før du køber. Stålet skal kunne ligge på risten med lidt luft rundt om, så varmen kan cirkulere.</p>
<h3>Tykkelse og vægt</h3>
<p>Et tykt, tungt stål holder mest varme og er bedst, når du bager mange pizzaer eller store brød. Et tyndere stål bliver hurtigere varmt og er lettere at håndtere.</p>
<h3>Bagestål eller pizzasten?</h3>
<p>Stål leder varmen hurtigere og kan ikke knække. En pizzasten er billigere og lettere. Til den almindelige ovn, der ikke bliver så varm, giver stål som regel den sprødeste bund.</p>
<p>Se også vores guides til <a href="/bedste-pizzastaal/">pizzastål</a> og <a href="/bedste-pizzaspade/">pizzaspader</a>, og se alle <a href="/pizzasten/bagestaal/">bagestål</a>.</p>
HTML,
	'faq'      => [
		[ 'Hvor længe skal et bagestål varme op?', 'Giv det mindst 45 minutter ved ovnens højeste temperatur, så stålet er gennemvarmt.' ],
		[ 'Kan man bage brød på et bagestål?', 'Ja, et bagestål giver brød en god skorpe og en jævn bund – præcis som en bagesten.' ],
		[ 'Ruster et bagestål?', 'Kulstofstål kan ruste. Tør det af efter brug, og giv det et tyndt lag olie.' ],
	],
];
