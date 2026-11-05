<?php
/**
 * Omskrevet fra det gamle pizzalicious.dk (/bedste-pizzaovn-test/). Fakta kun fra butikkernes produktdata. Ingen påstand om egne test.
 */
defined( 'ABSPATH' ) || exit;

return [
	'slug'     => 'bedste-pizzaovn',
	'title'    => 'Pizzaovn: Vores bud på de 4 bedste til haven',
	'excerpt'  => 'En pizzaovn til haven bager en pizza på få minutter ved en varme, en almindelig ovn ikke kan nå. Her er vores bud på de 4 bedste pizzaovne til gas og træ fra danske butikker.',
	'more_cat' => 'pizzaovne',
	'intro'    => <<<'HTML'
<p>Den største forskel på pizza fra en pizzeria og pizza fra køkkenovnen er varmen. En almindelig ovn stopper omkring 250–275 grader, mens en pizzaovn til haven kommer langt højere op. Det giver en bund, der er sprød på undersiden og blød indeni, og en kant, der hæver og får de små sorte pletter, man kender fra Napoli – på et par minutter.</p>
<p>Der findes pizzaovne til gas, til træ og træpiller og ovne, der kan begge dele. Herunder er vores bud på de 4 bedste hos danske butikker lige nu, og nederst kan du læse, hvad du skal tænke over, før du vælger.</p>
HTML,
	'picks'    => [
		[
			'ean'     => '5060967395200',
			'price'   => 5490,
			'name'    => 'Ooni Koda 2 Pro',
			'role'    => 'Bedst samlet',
			'reviews' => [],
			'verdict' => 'Stor gasovn med en brænder, der kan styres fra høj til lav varme – til dig, der vil bage mange pizzaer og mere end pizza.',
			'why'     => <<<'HTML'
<p>Ooni Koda 2 Pro er Oonis store gasovn. Ooni G2 Gas Technology fordeler varmen jævnt i hele ovnen, så du kan lave mad ved høj, medium og lav varme. Brænderen er dobbelt og konisk og designet til, at du kan styre flammens position og størrelse præcist.</p>
<p>Det betyder, at ovnen ikke kun er til napolitansk pizza ved fuld kraft, men også til tykkere pizzaer, der skal have lidt lavere varme og længere tid. Gas er nemt: du tænder, venter på, at stenen er varm, og så kører det.</p>
HTML,
			'good'    => [ 'Dig, der bager pizza til mange', 'Både tynd og tyk pizza', 'Nem start med gas' ],
			'less'    => [ 'Lille altan', 'Dig, der vil have røgsmag fra træ' ],
		],
		[
			'ean'     => '5060967394821',
			'price'   => 2790,
			'name'    => 'Ooni Karu 2',
			'role'    => 'Bedst til træ',
			'reviews' => [],
			'verdict' => 'Kompakt pizzaovn til træ, der også kan bruge gas med en gasbrænder, som købes ved siden af.',
			'why'     => <<<'HTML'
<p>Ooni Karu 2 er til dig, der vil have flammer og røgsmag fra træ. Den er kompakt og robust, og den kan bage en pizza på 60 sekunder. Den er lille nok til at stå på et bord i haven eller tage med i sommerhuset.</p>
<p>Ovnen kan også bruge gas: Ooni sælger en gasbrænder til Karu 2, som du kan købe ved siden af. Så kan du bruge træ, når du har tid, og gas en hverdagsaften. Træ kræver lidt mere øvelse, fordi du selv skal holde ilden ved lige.</p>
HTML,
			'good'    => [ 'Røgsmag og den klassiske oplevelse', 'Sommerhuset og turen', 'Dig, der vil kunne skifte til gas' ],
			'less'    => [ 'Dig, der vil have det helt nemt', 'Store pizzaer' ],
		],
		[
			'ean'     => '5707582008510',
			'price'   => 5995,
			'name'    => 'Witt Etna Rotante',
			'role'    => 'Bedst til begyndere',
			'reviews' => [],
			'verdict' => 'Gasovn med en pizzasten, der drejer rundt af sig selv, så du ikke skal vende pizzaen.',
			'why'     => <<<'HTML'
<p>Det sværeste ved en pizzaovn er at vende pizzaen, så den ikke brænder på den side, der vender mod flammen. Witt Etna Rotante løser det med en pizzasten, der roterer – den giver en jævn og sprød overflade hele vejen rundt.</p>
<p>Benene har gummifødder og kan foldes væk, når ovnen skal stå et andet sted. Witt er et dansk mærke, så der er kort vej til service og reservedele. Den er dyrere end en ovn uden roterende sten, men sparer dig for den mest typiske fejl.</p>
HTML,
			'good'    => [ 'Første pizzaovn', 'Dig, der ikke vil stå med pizzavenderen', 'Jævnt bagte pizzaer' ],
			'less'    => [ 'Lavt budget', 'Træfyring' ],
		],
		[
			'ean'     => '6414676027355',
			'price'   => 2990,
			'name'    => 'Muurikka Pizzaovn gas 16"',
			'role'    => 'Bedst til prisen',
			'reviews' => [],
			'verdict' => 'Kompakt gasovn til 16" pizzaer, der hurtigt bliver varm – til en fornuftig pris.',
			'why'     => <<<'HTML'
<p>Muurikka er et finsk mærke, der laver udstyr til madlavning udendørs. Deres 16" pizzaovn til gas er kompakt og kraftfuld, og den hurtigt når en høj arbejdstemperatur og giver en ensartet varme over hele pizzaen.</p>
<p>Med plads til 16" pizzaer er den på størrelse med de dyreste ovne, men koster en del mindre. Den er et godt valg, hvis du vil prøve kræfter med pizzaovn uden at bruge mange penge.</p>
HTML,
			'good'    => [ 'Lavt budget', 'Store pizzaer', 'Nem start med gas' ],
			'less'    => [ 'Dig, der vil lave meget andet end pizza' ],
		],
	],
	'guide'    => <<<'HTML'
<h2>Sådan vælger du en pizzaovn</h2>
<h3>Gas, træ eller begge dele?</h3>
<p>Gas er det nemmeste: du tænder på et tryk, varmen er stabil, og du kan skrue op og ned. Træ og træpiller giver røgsmag og flammer, men kræver, at du holder ilden ved lige, og det tager længere tid at få ovnen varm. Mange ovne til træ kan få en gasbrænder som tilbehør – det er et godt kompromis.</p>
<h3>12 eller 16 tommer?</h3>
<p>Størrelsen angiver, hvor stor en pizza der er plads til. En 12" ovn er mindre og lettere at flytte, men det er sværere at få pizzaen ind og vende den. En 16" ovn giver mere plads at arbejde med og er nemmere for begyndere, men fylder og vejer mere.</p>
<h3>Roterende sten</h3>
<p>I en ovn med flammen bagerst skal pizzaen vendes undervejs. En roterende sten gør det for dig. Det er især rart, når du er ny med pizzaovn.</p>
<h3>Det skal du også bruge</h3>
<p>En <a href="/bedste-pizzaspade/">pizzaspade</a> til at sætte pizzaen ind, en mindre spade til at vende den, et infrarødt termometer til at måle stenen og en <a href="/bedste-pizzaskaerer/">pizzaskærer</a>. Har du ikke plads til en pizzaovn, så se vores guide til <a href="/bedste-pizzastaal/">pizzastål</a> – det giver en sprød bund i den almindelige ovn. Vil du bage indenfor, så se <a href="/bedste-elektrisk-pizzaovn/">elektriske pizzaovne</a>.</p>
<p>Se alle <a href="/pizzaovne/">pizzaovne</a>, <a href="/pizzaovne/gas-pizzaovn/">gas pizzaovne</a> og <a href="/pizzaovne/trae-pizzaovn/">pizzaovne til træ</a>.</p>
HTML,
	'faq'      => [
		[ 'Hvor varm skal en pizzaovn være?', 'Til napolitansk pizza skal stenen være meget varm – mange bager ved 400 grader eller mere. Tykkere pizzaer bages ved lavere varme i længere tid. Brug et infrarødt termometer til at måle stenen.' ],
		[ 'Hvor lang tid tager en pizza i en pizzaovn?', 'En tynd pizza tager typisk et til to minutter i en pizzaovn ved høj varme. Læs mere i artiklen om, hvor lang tid en pizza skal have i en pizzaovn.' ],
		[ 'Kan man bruge en pizzaovn om vinteren?', 'Ja, men ovnen er længere om at blive varm, når det er koldt og blæser. Stil den i læ, og brug et overtræk, når den ikke er i brug.' ],
		[ 'Gas eller træ – hvad er bedst?', 'Gas er nemmest og mest stabilt. Træ giver røgsmag og den klassiske oplevelse, men kræver mere øvelse. Vælger du en ovn, der kan begge dele, kan du skifte efter humør.' ],
	],
];
