<?php
// Test af kategorireglerne uden WordPress: php dev/test-classifier.php
function get_option($k){return false;}
$src=__DIR__."/../wp-content/plugins/playform-feeds/src/";
require $src.'Config.php'; require $src.'Classifier.php';
use Playform\Feeds\{Classifier,Config};
$cases=[
['Weber Spirit E-325 GBS gasgrill','grill'],['Ooni Koda 12 pizzaovn','pizza-bagning'],['Le Creuset støbejernsgryde 24 cm','gryder-pander'],
['Scanpan Classic stegepande 28 cm','gryder-pander'],['Garcima paellapande 46 cm','grill'],['Global G-2 kokkekniv 20 cm','knive-redskaber'],
['KitchenAid Artisan røremaskine 4,8 L','koekkenmaskiner'],['Moccamaster KBG Select kaffemaskine','kaffe-drikke'],['Sage Barista Express espressomaskine','kaffe-drikke'],
['Bialetti Moka Express 6 kopper','kaffe-drikke'],['Riedel Veritas rødvinsglas 2 stk','servering'],['Ninja Foodi Dual Zone airfryer','ovne-apparater'],
['Samsung mikroovn 23 L','ovne-apparater'],['Cuisinart isterningmaskine','kaffe-drikke'],['Cuisinart ismaskine 1,5 L','ovne-apparater'],
['Bosch vaskemaskine 8 kg','exclude'],['Samsung køleskab 185 cm','exclude'],['Cylinda vinkøleskab 46 flasker','kaffe-drikke'],
['Køge Kafferisteri kaffebønner 500 g','exclude'],['Bamix stavblender','koekkenmaskiner'],['Weber grillpande støbejern','gryder-pander'],
['Panini grill Tefal','ovne-apparater'],['Bagestål 6 mm til ovn','pizza-bagning'],['Microplane rivejern','knive-redskaber'],
['Ostesæt i bambus 4 dele','servering'],['Mandolin grøntsagshakker','knive-redskaber'],['Elektrisk kartoffelskræller','koekkenmaskiner'],
['Kenwood elkedel 1,7 L','kaffe-drikke'],['Big Green Egg Large','grill'],['Kamado Joe Classic III','grill'],['Tøndekøler NOD party cooler','kaffe-drikke'],
['Wilfa brødrister','ovne-apparater'],['Philips Pasta Maker','pizza-bagning'],['Pastamaskine Marcato Atlas 150','pizza-bagning'],
['Æbleskivepande til induktion','gryder-pander'],['Vakuumpakker Caso','ovne-apparater'],['Fiskars knivsliber','knive-redskaber'],
['Bodum Chambord stempelkande','kaffe-drikke'],['Royal Copenhagen julestel tallerken 27 cm','servering'],
];
$bad=0;foreach($cases as [$n,$want]){ $got=Classifier::classify(['name'=>$n,'brand'=>'','shop_category'=>'','price'=>100,'shop'=>'x']); if($got!==$want){$bad++; echo "FAIL: $n -> $got (ville have $want)\n";}}
echo count($cases)-$bad."/".count($cases)." ok\n";
