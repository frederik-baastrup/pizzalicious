<?php
/**
 * Artikler fra det gamle pizzalicious.dk, der flyttes 1:1 (samme adresse, samme tekst, samme links og billede).
 * Flere af dem er solgte artikler med links til andre sites (linkbuilding) – teksten og linkene må ikke ændres.
 * 'hidden' => true: artiklen ligger på sin adresse og i sitemap, men vises ikke i lister, på forsiden eller under
 * "Læs også" (emnet ligger uden for pizza). Importen henter dem fra det gamle sites REST API ved udrulning,
 * så længe pizzalicious.dk endnu ikke peger på det nye site (se playform_import_legacy_posts()).
 */
defined( 'ABSPATH' ) || exit;

return [
	'hvem-opfandt-pizza'                                => [ 'hidden' => false ],
	'hvor-lang-tid-skal-en-pizza-have-i-en-pizzaovn'    => [ 'hidden' => false ],
	'en-pizza-kan-vaere-en-gudespise-men-ogsaa-det-modsatte' => [ 'hidden' => false ],
	'book-et-pizzakursus-og-laer-at-lave-pizza-som-i-italien' => [ 'hidden' => false ],
	'populaere-retter-buffet'                           => [ 'hidden' => true ],
	'vores-bud-menu-konfirmation'                       => [ 'hidden' => true ],
	'sadan-finder-du-det-rette-sage-vandfilter-til-din-kaffemaskine' => [ 'hidden' => true ],
	'klassiske-smagsoplevelser-leveret-direkte-til-doeren-catering-der-skaber-mindevaerdige-maaltider-hjemme' => [ 'hidden' => true ],
	'mexicansk-food-truck-til-fest-et-spaendende-alternativ-til-pizza' => [ 'hidden' => true ],
	'faa-fjernet-tandsmerter-hos-en-tandlaege-oesterbro-saa-du-igen-kan-nyde-at-spise-pizza' => [ 'hidden' => true ],
	'saadan-planlaegger-du-den-bedste-tur-til-italien'  => [ 'hidden' => true ],
];
