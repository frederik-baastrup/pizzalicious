<?php
/**
 * Omskrevet fra det gamle pizzalicious.dk (/bedste-pizzaspade-test/). Fakta kun fra butikkernes produktdata. Ingen påstand om egne test.
 */
defined( 'ABSPATH' ) || exit;

return [
	'slug'     => 'bedste-pizzaspade',
	'title'    => 'Pizzaspade: Vores bud på de 4 bedste i træ og metal',
	'excerpt'  => 'Pizzaspaden er det vigtigste stykke værktøj ved pizzaovnen. Her er vores bud på de 4 bedste pizzaspader i bambus og aluminium fra danske butikker.',
	'more_cat' => 'pizzaspader',
	'intro'    => <<<'HTML'
<p>Uden en pizzaspade kommer pizzaen ikke ind i ovnen – i hvert fald ikke i ét stykke. Spaden bruges til at flytte den rå pizza over på den varme sten og til at tage den ud igen. Der findes spader i træ og bambus, som dejen hænger mindre fast i, og spader i aluminium, der er tynde og glider let ind under den færdige pizza.</p>
<p>Herunder er vores bud på de 4 bedste pizzaspader hos danske butikker lige nu.</p>
HTML,
	'picks'    => [
		[
			'ean'     => '5060568341149',
			'price'   => 499,
			'name'    => 'Ooni perforeret pizzaspade 35,5 cm',
			'role'    => 'Bedst samlet',
			'reviews' => [],
			'verdict' => 'Perforeret aluminiumsspade, hvor hullerne lader damp og overskydende mel slippe væk.',
			'why'     => <<<'HTML'
<p>Hullerne i en perforeret pizzaspade lader varmen fra pizzaens bund slippe væk, før den lægger sig som damp, der kan gøre bunden mindre sprød. Det overskydende mel falder også igennem hullerne i stedet for at brænde på stenen.</p>
<p>Med 35,5 cm passer den til store pizzaer og 16" ovne.</p>
HTML,
			'good'    => [ 'Store pizzaovne', 'Sprød bund uden brændt mel' ],
			'less'    => [ 'Små 12" ovne' ],
		],
		[
			'ean'     => '5060568341606',
			'price'   => 295,
			'name'    => 'Ooni pizzaspade i bambus 35,5 cm',
			'role'    => 'Bedst til at bygge pizzaen',
			'reviews' => [],
			'verdict' => 'Stor bambusspade til pizzaer op til 35,5 cm – rå dej hænger mindre fast i træ end i metal.',
			'why'     => <<<'HTML'
<p>Ooni bambusspaden har plads til pizzaer på op til 35,5 cm i diameter og passer til Oonis største ovne.</p>
<p>Mange bygger pizzaen direkte på en træspade, fordi den rå dej hænger mindre fast i træ end i metal, og bruger en metalspade til at tage pizzaen ud.</p>
HTML,
			'good'    => [ 'At bygge pizzaen på spaden', 'Servering' ],
			'less'    => [ 'At tage pizzaen ud af ovnen' ],
		],
		[
			'ean'     => '5707582034304',
			'price'   => 599,
			'name'    => 'Witt pizzaspade 14" Soft Grip',
			'role'    => 'Bedst til store pizzaer',
			'reviews' => [],
			'verdict' => 'Stabil, perforeret spade til store pizzaer med blødt greb.',
			'why'     => <<<'HTML'
<p>Witt Pizzaspade 14" Soft Grip er udviklet til sikker og kontrolleret håndtering af større pizzaer, og den perforerede overflade hjælper bunden med at holde sig sprød.</p>
<p>Det bløde greb gør den behagelig at holde, når du står ved ovnen. Witt er dansk og laver også pizzaovne.</p>
HTML,
			'good'    => [ 'Store pizzaer', 'Witt-pizzaovne' ],
			'less'    => [ 'Lavt budget' ],
		],
		[
			'ean'     => '6410416147987',
			'price'   => 370,
			'name'    => 'Mustang Premium pizzaspade 33 cm',
			'role'    => 'Bedst til prisen',
			'reviews' => [],
			'verdict' => 'Let og holdbar pizzaspade i aluminium til en fornuftig pris.',
			'why'     => <<<'HTML'
<p>Mustang Premium pizzaspaden er lavet i aluminium, så den er både let og holdbar. Den er et robust og pålideligt redskab til både hobbykokke og pizzafans.</p>
HTML,
			'good'    => [ 'Lavt budget', 'Pizzaovne på 12–13"' ],
			'less'    => [ 'De største pizzaer' ],
		],
	],
	'guide'    => <<<'HTML'
<h2>Sådan vælger du en pizzaspade</h2>
<h3>Træ eller metal?</h3>
<p>Rå dej hænger mindre fast i træ og bambus, så en træspade er bedst til at bygge pizzaen på og sætte den ind. En tynd metalspade glider lettere ind under den færdige pizza og er bedst til at tage den ud. Mange har en af hver.</p>
<h3>Perforeret</h3>
<p>Hullerne lader overskydende mel falde fra, så det ikke brænder på stenen og giver en bitter smag.</p>
<h3>Størrelse</h3>
<p>Spaden skal være lidt større end pizzaen – men ikke større end ovnens åbning. Til en 12" ovn passer en spade på ca. 30 cm, til en 16" ovn ca. 35–40 cm.</p>
<h3>Pizzavender</h3>
<p>En lille, rund spade på et langt skaft bruges til at dreje pizzaen inde i ovnen, så den bager jævnt. Den er praktisk, hvis din ovn ikke har roterende sten.</p>
<p>Se også vores guides til <a href="/bedste-pizzaovn/">pizzaovne</a> og <a href="/bedste-pizzaskaerer/">pizzaskærere</a>, og se alle <a href="/pizzaspader/">pizzaspader</a>.</p>
HTML,
	'faq'      => [
		[ 'Hvordan undgår man, at pizzaen hænger fast i spaden?', 'Drys spaden med lidt mel eller semolina, byg pizzaen hurtigt, og ryst spaden let, før du sætter den ind, så du kan mærke, at pizzaen glider.' ],
		[ 'Hvor stor skal en pizzaspade være?', 'Lidt større end pizzaen, men den skal kunne komme ind gennem ovnens åbning. Ca. 30 cm til en 12" ovn og 35–40 cm til en 16" ovn.' ],
		[ 'Hvad er en perforeret pizzaspade?', 'En metalspade med huller, så overskydende mel falder fra og ikke brænder på stenen.' ],
	],
];
