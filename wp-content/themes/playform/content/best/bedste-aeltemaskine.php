<?php
/**
 * Omskrevet fra det gamle pizzalicious.dk (/bedste-aeltemaskine/). Fakta kun fra butikkernes produktdata. Ingen påstand om egne test.
 */
defined( 'ABSPATH' ) || exit;

return [
	'slug'     => 'bedste-aeltemaskine',
	'title'    => 'Æltemaskine: Vores bud på de 4 bedste til pizzadej',
	'excerpt'  => 'En god pizzadej skal æltes længe, og det er hårdt arbejde i hånden. Her er vores bud på de 4 bedste æltemaskiner til pizzadej fra danske butikker.',
	'more_cat' => 'aeltemaskine',
	'intro'    => <<<'HTML'
<p>Pizzadej skal æltes, til glutenen er udviklet, og dejen er glat og elastisk. Det kan sagtens gøres i hånden, men laver du pizza til mange – eller ofte – er en æltemaskine en stor hjælp. Pizzadej er fast, så maskinen skal have kræfter og en skål, der kan rumme en stor portion.</p>
<p>Herunder er vores bud på de 4 bedste æltemaskiner til pizzadej hos danske butikker lige nu.</p>
HTML,
	'picks'    => [
		[
			'ean'     => '7350061086097',
			'price'   => 5495,
			'name'    => 'Ankarsrum Assistent Original AKM 6230',
			'role'    => 'Bedst til store portioner',
			'reviews' => [],
			'verdict' => 'Svensk klassiker med 7 liters skål, der kan ælte op til 5 kg dej.',
			'why'     => <<<'HTML'
<p>Ankarsrum er bygget anderledes end de fleste røremaskiner: skålen drejer rundt, og motoren sidder i bunden. Den har en kapacitet på 7 liter og kan klare op til 5 kg dej, og hastigheden kan reguleres.</p>
<p>Det gør den til et oplagt valg, når der skal laves pizzadej til hele familien eller til at fryse ned. Den sælges af flere butikker og findes i mange farver.</p>
HTML,
			'good'    => [ 'Store portioner dej', 'Brød og pizza' ],
			'less'    => [ 'Små køkkener', 'Dig, der mest pisker flødeskum' ],
		],
		[
			'ean'     => '5060967395316',
			'price'   => 5990,
			'name'    => 'Ooni Halo Pro',
			'role'    => 'Bedst til pizzadej',
			'reviews' => [],
			'verdict' => 'Spiralælter fra pizzaovnsmærket Ooni – lavet til at ælte dej skånsomt og effektivt.',
			'why'     => <<<'HTML'
<p>Ooni Halo Pro er en spiralælter, den type maskine pizzeriaer bruger. Den ælter dejen skånsomt og effektivt og giver gode resultater med alt fra brød til pizzadej, og hastigheden kan justeres.</p>
<p>Den er bedst til dig, der primært bager pizza og brød og vil have en maskine, der er tænkt til dej.</p>
HTML,
			'good'    => [ 'Pizzadej og brød', 'Dig med en Ooni-pizzaovn' ],
			'less'    => [ 'Lavt budget' ],
		],
		[
			'ean'     => '7044876020314',
			'price'   => 5990,
			'name'    => 'Wilfa Probaker',
			'role'    => 'Godt alternativ',
			'reviews' => [],
			'verdict' => 'Køkkenmaskine med roterende 7 liters skål og 20 hastigheder, udviklet med det norske kokkelandshold.',
			'why'     => <<<'HTML'
<p>Wilfa Probaker er designet i samarbejde med det norske kokkelandshold. Den store roterende skål på 7 liter kan ælte en dej på op til 5 kg, og der er 20 hastighedsindstillinger.</p>
<p>Med den roterende skål minder den om Ankarsrum og er et godt alternativ, hvis du laver store portioner.</p>
HTML,
			'good'    => [ 'Store portioner dej', 'Fin kontrol med hastigheden' ],
			'less'    => [ 'Små køkkener' ],
		],
		[
			'ean'     => '5413184400286',
			'price'   => 3999,
			'name'    => 'KitchenAid Artisan KSM175',
			'role'    => 'Bedst allround',
			'reviews' => [],
			'verdict' => 'Den ikoniske røremaskine med 4,8 liters skål – til dej, kager og alt det andet.',
			'why'     => <<<'HTML'
<p>KitchenAid Artisan KSM175 er en røremaskine med en kapacitet på 4,8 liter, hvor der er plads til op mod 1 kg mel. Det rækker til pizzadej til en familie.</p>
<p>Den er ikke bygget specielt til tung dej som Ankarsrum og Ooni Halo, men den kan meget andet, og den findes i mange farver. Vil du have én maskine til både pizzadej, kager og flødeskum, er den et godt valg.</p>
HTML,
			'good'    => [ 'Både dej og kager', 'Design' ],
			'less'    => [ 'Meget store portioner pizzadej' ],
		],
	],
	'guide'    => <<<'HTML'
<h2>Sådan vælger du en æltemaskine til pizzadej</h2>
<h3>Spiralælter, roterende skål eller planetarisk røremaskine?</h3>
<p>En spiralælter er lavet til dej og er det, pizzeriaer bruger. En maskine med roterende skål, som Ankarsrum og Wilfa Probaker, er god til store portioner tung dej. En klassisk planetarisk røremaskine, som KitchenAid, kan mest forskelligt, men har sværere ved meget store portioner fast dej.</p>
<h3>Kapacitet</h3>
<p>Kig efter, hvor meget dej eller mel maskinen kan klare – ikke kun skålens størrelse i liter. Pizzadej er fast og kræver mere af motoren end kagedej.</p>
<h3>Lad dejen arbejde</h3>
<p>Selv den bedste maskine skal ikke køre for hurtigt. Ælt ved lav hastighed, og lad dejen hvile og hæve – det er tiden, der giver smagen.</p>
<p>Når dejen er klar, så se vores guides til <a href="/bedste-pizzaovn/">pizzaovne</a>, <a href="/bedste-pizzastaal/">pizzastål</a> og <a href="/bedste-pizzaspade/">pizzaspader</a>. Se alle <a href="/dej/aeltemaskine/">æltemaskiner</a> og <a href="/dej/dejkasse/">dejkasser</a>.</p>
HTML,
	'faq'      => [
		[ 'Kan man bruge en almindelig røremaskine til pizzadej?', 'Ja, de fleste røremaskiner kan ælte pizzadej i mindre portioner. Til store portioner fast dej er en maskine med roterende skål eller en spiralælter bedre.' ],
		[ 'Hvor længe skal pizzadej æltes i maskine?', 'Typisk 8–12 minutter ved lav hastighed, til dejen er glat og elastisk. Det afhænger af opskriften og maskinen.' ],
		[ 'Hvad er en spiralælter?', 'En æltemaskine med en spiralformet krog og en skål, der drejer. Den ælter dejen skånsomt og bruges i pizzeriaer og bagerier.' ],
	],
];
