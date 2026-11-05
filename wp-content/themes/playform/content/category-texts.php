<?php
/**
 * Introtekster til Pizzalicious' kategorier og underkategorier (landingssiderne).
 * Kun generel købsrådgivning – ingen påstande om konkrete produkter (projektregel 2).
 * Seedes af playform_seed_category_texts() og kan derefter rettes under Produkter › Kategorier.
 * Tomme felter udfyldes ved næste udrulning, felter med tekst røres aldrig (redaktørens rettelser vinder).
 *
 * @return array<string,array{intro?:string,seo?:string,faq?:array<int,array{0:string,1:string}>}>
 */

defined( 'ABSPATH' ) || exit;

return [
	// Hovedkategorier.
	'pizzaovne'     => [ 'intro' => 'Pizzaovne til gas, træ og el – fra små ovne til altanen til store ovne til haven. Se modeller fra flere danske webshops samlet ét sted.' ],
	'pizzasten'     => [ 'intro' => 'Pizzasten, bagestål og pizzapander, der giver en sprød bund i både pizzaovnen og den almindelige ovn. Se udvalget fra flere danske webshops.' ],
	'pizzaspader'   => [ 'intro' => 'Pizzaspader i træ, bambus og aluminium – til at sætte pizzaen ind, vende den og tage den ud igen. Se udvalget fra flere danske webshops.' ],
	'pizzaskaerere' => [ 'intro' => 'Pizzaskærere, pizzahjul og pizzasakse, der skærer rent igennem uden at trække osten med. Se udvalget fra flere danske webshops.' ],
	'dej'           => [ 'intro' => 'Æltemaskiner, dejkasser og andet, der gør det nemmere at lave en god pizzadej. Se udvalget fra flere danske webshops samlet ét sted.' ],
	'tilbehoer'     => [ 'intro' => 'Tilbehør til pizzaovnen: termometre, overtræk, borde, handsker, pander og pizzasæt. Se udvalget fra flere danske webshops samlet ét sted.' ],
	'ingredienser'  => [ 'intro' => 'Pizzamel, tomater, pizzasauce og krydderier til den hjemmelavede pizza. Se udvalget fra flere danske webshops samlet ét sted.' ],

	// Pizzaovne.
	'gas-pizzaovn'       => [ 'intro' => 'Gas pizzaovne bliver hurtigt varme og er nemme at styre – de mest populære ovne til haven og altanen. Se modeller fra flere danske webshops.' ],
	'pizzaovn-traepiller' => [ 'intro' => 'Pizzaovne til træpiller giver høj varme og den klassiske røgsmag, og piller er nemme at fylde på undervejs. Nogle modeller kan også bruges med gas. Se modeller fra flere danske webshops.' ],
	'pizzaovn-til-koekkenet' => [ 'intro' => 'Pizzaovne til køkkenet er elektriske og kan stå på køkkenbordet, så du kan bage pizza hele året uden at gå udenfor. Se modeller fra flere danske webshops.' ],
	'elektrisk-pizzaovn' => [ 'intro' => 'Elektriske pizzaovne, der kan stå i køkkenet og bruges hele året – uden gas, træ eller røg. Se modeller fra flere danske webshops.' ],
	'pizzaovn-til-grill' => [ 'intro' => 'Pizzaovne og pizzaindsatse til grillen, så din gas- eller kamadogrill kan bage pizza. Se udvalget fra flere danske webshops.' ],

	// Pizzasten & bagestål.
	'pizzasten-sten' => [ 'intro' => 'Pizzasten i cordierit og keramik til ovnen, grillen og pizzaovnen. Se udvalget fra flere danske webshops samlet ét sted.' ],
	'bagestaal'      => [ 'intro' => 'Bagestål giver mere varme til bunden end en pizzasten og en sprød pizza i den almindelige ovn. Se udvalget fra flere danske webshops.' ],
	'pizzapande'     => [ 'intro' => 'Pizzapander og pizzaforme til pan pizza, Detroit-pizza og pizza på plade. Se udvalget fra flere danske webshops.' ],

	// Dej & æltning.
	'aeltemaskine' => [ 'intro' => 'Æltemaskiner og røremaskiner, der kan ælte en fast pizzadej. Se modeller fra flere danske webshops samlet ét sted.' ],
	'dejkasse'     => [ 'intro' => 'Dejkasser og dejæsker, så dejboldene kan hæve i ro uden at tørre ud. Se udvalget fra flere danske webshops.' ],

	// Ingredienser.
	'pizzamel' => [ 'intro' => 'Pizzamel og tipo 00-mel til napolitansk pizza, pinsa og pizza på plade. Se udvalget fra flere danske webshops.' ],
	'tomater'  => [ 'intro' => 'Tomater, passata og pizzasauce til pizzaen. Se udvalget fra flere danske webshops samlet ét sted.' ],
];
