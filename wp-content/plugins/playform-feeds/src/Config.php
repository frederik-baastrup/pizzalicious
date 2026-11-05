<?php
namespace Playform\Feeds;

/**
 * Al opsætning for nichen ét sted: sitets navn, shops, kategoritræ, sorteringsregler og filtre.
 * Pizzalicious (pizzaudstyr). Samme kode som Kitzhen og Fitbase; kun denne fil er særlig for nichen.
 */
final class Config {

	/** Sitets navn og kontaktoplysninger. Bruges af temaet (playform_site()) og importens tekster. */
	public static function site(): array {
		return [
			'name'     => 'Pizzalicious',
			'domain'   => 'pizzalicious.dk',
			'email'    => 'kontakt@pizzalicious.dk',
			'niche'    => 'pizzaudstyr', // "Find pizzaudstyr fra …"
			'tagline'  => 'Pizzaovne, pizzasten og alt til pizza fra danske webshops samlet ét sted',
			'company'  => 'Mondae',
			'cvr'      => '37208329',
			'address'  => 'Rønnevej 19 B, kl., 5270 Odense N',
			'owner'    => 'Frederik Baastrup',
			'accent'   => '#CE2B37', // den røde fra logoet
		];
	}

	/**
	 * Partner-ads feeds – de samme som Kitzhen (partnerid 14854, ejerens valg 5. nov. 2026). Kun pizzaudstyr
	 * og -ingredienser kommer med: alt, der ikke matcher en kategori nedenfor, havner i "ukategoriseret" og vises ikke.
	 * Kan overstyres med option 'playform_shops'.
	 */
	public static function shops(): array {
		$base     = 'https://www.partner-ads.com/dk/feed_udlaes.php?partnerid=14854';
		$defaults = [
			'kitchenone'       => [ 'name' => 'KitchenOne.dk', 'url' => "$base&bannerid=79283&feedid=1862" ],
			'barlife'          => [ 'name' => 'Barlife.dk', 'url' => "$base&bannerid=46609&feedid=651" ],
			'hvidevareshoppen' => [ 'name' => 'HvidevareShoppen.dk', 'url' => "$base&bannerid=56457&feedid=1000" ],
			'bagestaalet'      => [ 'name' => 'Bagestålet.dk', 'url' => "$base&bannerid=90982&feedid=2362" ],
			'gourmetshoppen'   => [ 'name' => 'Gourmetshoppen.com', 'url' => "$base&bannerid=80950&feedid=1925" ],
			'cibumic'          => [ 'name' => 'Cibumic.dk', 'url' => "$base&bannerid=59757&feedid=1113" ],
			// Moreland: kun pizzaovne og pizzatilbehør (fast oversættelse; alt andet udelukkes).
			'moreland'         => [
				'name'         => 'Moreland',
				'url'          => "$base&bannerid=16450&feedid=6",
				'category_map' => [ 'pizzaovn' => 'pizzaovne' ],
			],
		];
		$custom = get_option( 'playform_shops' );
		return is_array( $custom ) && $custom ? $custom : $defaults;
	}

	/**
	 * Pizzalicious' kategorier. 'match' testes først mod produktnavnet, derefter mod butikkens kategori.
	 * Rækkefølgen betyder noget: den første kategori, der matcher, vinder. Kun pizzarelaterede produkter
	 * matcher – resten af butikkernes sortiment bliver ukategoriseret og skjult.
	 * Slugs må aldrig starte med "bedste-" – det er forbeholdt guides på roden (se permalinks.php).
	 */
	public static function categories(): array {
		return [
			'ingredienser'    => [ 'name' => 'Ingredienser', 'match' => 'pizzamel|tipo ?0|\bsemola|manitoba|caputo|san marzano|passata|pelati|pizzasauce|pizza sauce|pizzatopping|pizza topping|pizzakrydderi|\boregano\b(?!.*(tæppe|gulv))|chiliolie|olio santo|pizzagær' ],
			'pizzaovne'       => [ 'name' => 'Pizzaovne', 'match' => 'pizzaovn|pizza ?ovn|pizza oven|pizzaiolo|pizza ?maker|pizzetta|\booni (karu|koda|volt|fyra|pro)\b|gozney|roccbox|effeuno|rotisseri (&|og) pizzaovn|pizzaovnsindsats', 'not' => 'overtræk|cover|taske|termometer|børste|bord|handsk|spade|sten|gasbrænder|brænder til|indsats til|træpiller|brænde eg|\\bgreb\\b|dør til|glasdør|fod|krog' ],
			'pizzasten'       => [ 'name' => 'Pizzasten & bagestål', 'match' => 'pizzasten|pizza ?sten|bagesten|bagest[åa]l|pizzastål|pizzaplade|pizzapande|pizzaform|detroit style|pizzamåtte' ],
			'pizzaspader'     => [ 'name' => 'Pizzaspader', 'match' => 'pizzaspade|pizza ?spade|pizza paddle|pizza pagaj|pizzaskovl|pizzavender|bagespade|pizza- og bagespatel' ],
			'pizzaskaerere'   => [ 'name' => 'Pizzaskærere', 'match' => 'pizzaskærer|pizza ?skærer|pizzahjul|pizza ?hjul|pizza slicer|pizzasaks|pizzakniv|pizza- og steakknive|pizza/steak' ],
			'dej'             => [ 'name' => 'Dej & æltning', 'match' => 'æltemaskine\\b|spiral ?mixer|røremaskine\\b|køkkenmaskine\\b|køkkenassistent\\b|stand ?mixer\\b|dejkasse|pizzadejsæske|dejsæske|dejæske|dejbakke|dejdeler|pizzarulle|pizzavægt|hævekurv', 'not' => 'skål til|piskeris til|dejkrog til|tilbehør til|låg til|reservedel|overtræk til|cover til' ],
			'tilbehoer'       => [ 'name' => 'Tilbehør', 'match' => '\booni\b|pizzaovnstermometer|termometer til pizzaovn|infrarødt termometer|pizzaovnsbørste|børste til pizzaovn|pizzahandsker|overtræk til pizza|cover til pizzaovn|taske til pizzaovn|pizzavogn|toppingstation|topping station|pizzasæt|pizzakit|pizza kit|pizzabræt|pizza ?board|pizzatallerken|pizza tallerken|pizzabestik|pizza serveringssæt|pizza serveringsbræt' , 'not' => 'melooni|krus' ],
		];
	}

	/**
	 * Underkategorier = egne landingssider, fx /pizzaovne/gas-pizzaovn/. 'match' testes mod produktnavnet,
	 * 'desc' mod butikkens beskrivelse, 'not' udelukker ud fra navnet. Et produkt kan ligge i flere.
	 */
	public static function subcategories(): array {
		return [
			'pizzaovne' => [
				'gas-pizzaovn'       => [ 'name' => 'Gas pizzaovn', 'match' => '\bgas\b|gasdrevet|til gas|koda|multi ?fuel|karu', 'desc' => 'gas' ],
				'trae-pizzaovn'      => [ 'name' => 'Pizzaovn til træ', 'match' => 'træpiller|træfyret|brænde|\bfyra\b|karu|multi ?fuel|\bpellet', 'not' => 'el\b|elektrisk' ],
				'elektrisk-pizzaovn' => [ 'name' => 'Elektrisk pizzaovn', 'match' => 'elektrisk|\bvolt\b|pizzaiolo|pizza ?maker|\d{3,4} ?w\b|effeuno|panetti|pizzetta|g3 ?ferrari|\b230 ?v', 'not' => '\bgas\b' ],
				'pizzaovn-til-grill' => [ 'name' => 'Pizzaovn til grill', 'match' => 'til grill|til kamado|kamado|grill pizza|rotisseri|indsats' ],
			],
			'pizzasten' => [
				'pizzasten-sten'     => [ 'name' => 'Pizzasten', 'match' => 'pizzasten|pizza ?sten|bagesten|cordierit|keramisk' ],
				'bagestaal'          => [ 'name' => 'Bagestål', 'match' => 'bagest[åa]l|pizzastål|stålplade' ],
				'pizzapande'         => [ 'name' => 'Pizzapande & pizzaform', 'match' => 'pizzapande|pizzaform|pizzaplade|detroit|pande' ],
			],
			'dej' => [
				'aeltemaskine'       => [ 'name' => 'Æltemaskine', 'match' => 'æltemaskine|spiral ?mixer|røremaskine|køkkenmaskine|køkkenassistent|assistent original|stand ?mixer' ],
				'dejkasse'           => [ 'name' => 'Dejkasser', 'match' => 'dejkasse|dejsæske|dejæske|dejbakke' ],
			],
			'ingredienser' => [
				'pizzamel'           => [ 'name' => 'Pizzamel', 'match' => 'mel\b|tipo|semola|manitoba|caputo' ],
				'tomater'            => [ 'name' => 'Tomater & pizzasauce', 'match' => 'tomat|passata|pelati|sauce|san marzano' ],
			],
		];
	}

	/** Mærker, der kun laver pizzaudstyr: deres produkter uden et kategoriord i navnet lander her. */
	public static function brand_categories(): array {
		return [ 'ooni' => 'tilbehoer', 'gozney' => 'tilbehoer', 'effeuno' => 'pizzaovne' ];
	}

	/** Produkter, der aldrig skal med (navn eller butikkens kategori). */
	public static function exclude(): array {
		return [
			'gavekort',
			'reservedel|spare ?part|replacement part',
			'\bguide\b|opskriftsbog|kogebog|\bbog\b|bøger',
			'gulvtæppe|\btæppe|plaid|forklæde|viskestykke|\bt-shirt\b',
			// KitchenOnes kamadogrills med en pizzaindsats er grills, ikke pizzaovne
			'kamadogrill|kamado ?grill|\bsignature (midi|pro)\b|\bicon (maxi|large|midi)\b',
			// Airfryere med pizzafunktion hører ikke hjemme her
			'airfryer|air ?fryer',
		];
	}

	/** Butikskategorier, hvor kun vores egne regler må afgøre placeringen (de siger intet om produktet). */
	public static function meaningless_shop_categories(): array {
		return [ 'brands', 'mærker', 'tilbud', 'udsalg', 'nyheder', 'gaveideer', 'gaver', 'alle produkter', 'køkken', 'køkkenudstyr', 'black friday', 'black month' ];
	}

	/**
	 * Maskinkategorier, hvor tilbehør (filtre, reservedele, låg …) i stedet skal til 'accessory_target'.
	 * Fitbase bruger det til løbebånd m.m.; Pizzalicious bruger det ikke.
	 */
	public static function machine_categories(): array {
		return [];
	}

	public static function accessory_pattern(): string {
		return 'reservedel|filter til|kande til|skål til|knivblad til|tilbehør til';
	}

	public static function accessory_target(): string {
		return 'tilbehoer';
	}

	/**
	 * Hvilke filteregenskaber Attributes skal udtrække fra teksten (se Attributes::extract()).
	 * Fitbase: foldable, incline, max_speed, max_weight, weight_kg. Pizzalicious: ingen endnu.
	 */
	public static function attributes(): array {
		return [];
	}

	/** Prisintervaller i filtre og mega-menu: nøgle => [ label, min, max ]. */
	public static function price_bands(): array {
		return [
			'0-200'     => [ 'Under 200 kr', 0, 200 ],
			'200-500'   => [ '200–500 kr', 200, 500 ],
			'500-1500'  => [ '500–1.500 kr', 500, 1500 ],
			'1500-3000' => [ '1.500–3.000 kr', 1500, 3000 ],
			'3000-6000' => [ '3.000–6.000 kr', 3000, 6000 ],
			'6000-'     => [ 'Over 6.000 kr', 6000, PHP_INT_MAX ],
		];
	}

	public static function uncategorized_slug(): string {
		return 'ukategoriseret';
	}
}
