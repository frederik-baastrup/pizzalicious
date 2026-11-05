<?php
/**
 * Omskrevet fra det gamle pizzalicious.dk (/bedste-pizzaskaerer/ og /pizzahjul/). Fakta kun fra butikkernes produktdata. Ingen påstand om egne test.
 */
defined( 'ABSPATH' ) || exit;

return [
	'slug'     => 'bedste-pizzaskaerer',
	'title'    => 'Pizzaskærer: Vores bud på de 4 bedste',
	'excerpt'  => 'Pizzahjul, vippekniv eller pizzasaks? Her er vores bud på de 4 bedste pizzaskærere fra danske butikker – og hvilken type der passer til din pizza.',
	'more_cat' => 'pizzaskaerere',
	'intro'    => <<<'HTML'
<p>En god pizzaskærer skærer rent igennem bunden i ét træk uden at trække osten og fyldet med. Der findes tre typer: det klassiske pizzahjul, den store vippekniv, som pizzeriaer bruger, og pizzasaksen, der skærer som en saks og løfter stykket på samme tid.</p>
<p>Herunder er vores bud på de 4 bedste pizzaskærere hos danske butikker lige nu.</p>
HTML,
	'picks'    => [
		[
			'ean'     => '5060568341156',
			'price'   => 165,
			'name'    => 'Ooni pizzahjul',
			'role'    => 'Bedst samlet',
			'reviews' => [],
			'verdict' => 'Pizzahjul med skarpt blad i rustfrit stål og et slidstærkt håndtag i glasforstærket nylon.',
			'why'     => <<<'HTML'
<p>Ooni pizzahjulet har ifølge butikken et skarpt blad af rustfrit stål og et slidstærkt håndtag af glasforstærket nylon. Det er et enkelt, solidt pizzahjul fra pizzaovnsmærket Ooni.</p>
<p>Et pizzahjul er det mest alsidige valg til almindelige runde pizzaer. Valget er vores vurdering ud fra butikkens oplysninger og prisen.</p>
HTML,
			'good'    => [ 'Runde pizzaer', 'Hverdagsbrug' ],
			'less'    => [ 'Tykke deep pan-pizzaer' ],
		],
		[
			'ean'     => '5060568341163',
			'price'   => 255,
			'name'    => 'Ooni pizzaskærer (vippekniv)',
			'role'    => 'Bedst til tyk pizza',
			'reviews' => [],
			'verdict' => 'Stor vippekniv, der skærer igennem tykke deep pan-pizzaer, hvor et pizzahjul har svært ved det.',
			'why'     => <<<'HTML'
<p>Med en stor pizzaskærer som denne kan du lægge kræfterne i, skriver butikken. Designet gør den særligt velegnet til deep pan-pizzaer, som kan være svære at skære ud med et klassisk pizzahjul.</p>
<p>Du vipper bladet fra side til side og skærer hele pizzaen over i ét. Det er hurtigt og giver pæne snit, men kræver plads i skuffen. Valget er vores vurdering.</p>
HTML,
			'good'    => [ 'Deep pan og pizza på plade', 'Mange pizzaer i træk' ],
			'less'    => [ 'Små skuffer' ],
		],
		[
			'ean'     => '9334084002010',
			'price'   => 131,
			'name'    => 'Dreamfarm Scizza pizzasaks',
			'role'    => 'Bedst til nonstick',
			'reviews' => [],
			'verdict' => 'Pizzasaks med en spartelbund, der glider ind under pizzaen uden at ridse panden.',
			'why'     => <<<'HTML'
<p>Scizza er en pizzasaks fra australske Dreamfarm. Ifølge butikken skærer den enhver pizzabund uanset tykkelse og form, og den har en spartelbund, der glider ind under pizzaen, så knivene ikke ridser nonstick-belægningen.</p>
<p>Det gør den til det bedste valg, når pizzaen ligger på en bageplade eller i en pande, hvor et pizzahjul ville ridse. Valget er vores vurdering ud fra butikkens oplysninger.</p>
HTML,
			'good'    => [ 'Pizza i pande eller på bageplade', 'Børn, der selv skærer' ],
			'less'    => [ 'Store pizzaer, der skal skæres på én gang' ],
		],
		[
			'ean'     => '719812001531',
			'price'   => 100,
			'name'    => 'OXO pizzahjul 9 cm',
			'role'    => 'Bedst til prisen',
			'reviews' => [],
			'verdict' => 'Billigt pizzahjul med skarpt stålhjul og et skridsikkert greb, der beskytter tommelfingeren.',
			'why'     => <<<'HTML'
<p>OXO-pizzahjulet har ifølge butikken et skarpt skærehjul af rustfrit stål, som skærer gennem selv tykke pizzabunde med meget fyld. Håndtaget har skridsikkert gummi og en beskyttelse til tommelfingeren.</p>
<p>Det er et godt, billigt pizzahjul til hverdagspizzaen. Valget er vores vurdering ud fra pris.</p>
HTML,
			'good'    => [ 'Lavt budget', 'Hverdagspizza' ],
			'less'    => [ 'Meget store pizzaer' ],
		],
	],
	'guide'    => <<<'HTML'
<h2>Sådan vælger du en pizzaskærer</h2>
<h3>Pizzahjul</h3>
<p>Det mest almindelige. Et stort hjul med skarp kant skærer bedst – et lille hjul har svært ved tykke kanter og meget fyld.</p>
<h3>Vippekniv</h3>
<p>En lang, buet kniv, du vipper hen over pizzaen. Den skærer hele pizzaen over på én gang og er god til tykke pizzaer og pizza på plade.</p>
<h3>Pizzasaks</h3>
<p>Skærer som en saks og kan ikke ridse pander og bageplader. God til pizza i pande og til børn.</p>
<p>Se også vores guides til <a href="/bedste-pizzaspade/">pizzaspader</a> og <a href="/bedste-pizzaovn/">pizzaovne</a>, og se alle <a href="/pizzaskaerere/">pizzaskærere</a>.</p>
HTML,
	'faq'      => [
		[ 'Hvordan skærer man pizza uden at osten følger med?', 'Brug et skarpt blad, og skær i ét bestemt træk. Lad pizzaen hvile et minut efter bagningen, så osten sætter sig.' ],
		[ 'Kan et pizzahjul ridse en bageplade?', 'Ja, et metalhjul kan ridse nonstick. Brug en pizzasaks eller flyt pizzaen over på et skærebræt først.' ],
		[ 'Kan pizzaskæreren komme i opvaskemaskinen?', 'Det afhænger af modellen og håndtaget. Tjek producentens anvisning – træhåndtag har det bedst med opvask i hånden.' ],
	],
];
