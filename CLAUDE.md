# Pizzalicious – projektnoter til Claude

Pizzalicious (pizzalicious.dk) er en affiliate-side, der ligner en webshop. Den samler pizzaovne, pizzasten, pizzaspader, æltemaskiner, tilbehør og ingredienser fra flere danske shops via Partner-ads-produktfeeds. Man køber ikke på siden: "Gå til butik" sender brugeren videre til shoppen via et affiliate-link.

Ejer: Frederik Baastrup (Mondae). Al tekst på siden er på dansk.

**Samme kodebase som Kitzhen og Fitbase.** Pizzalicious startede som en kopi af Kitzhen 5. nov. 2026. Kodenavnene er bevidst de samme: mappenavne `playform`/`playform-feeds`, namespace `Playform\Feeds`, funktioner `playform_*`, metafelter `_pf_*`, shortcodes `[playform_produkter]` og `[fitbase_…]`. Så kan rettelser flyttes mellem de tre sites. Omdøb dem ikke.

Alt, der er særligt for nichen, ligger i `wp-content/plugins/playform-feeds/src/Config.php`: navn og kontaktoplysninger (`site()`), shops, kategorier, underkategorier, udelukkelser, mærker (`brand_categories()`), prisintervaller. Temaet læser navn, domæne m.m. via `playform_site()`. Skriv aldrig "Pizzalicious" direkte i en skabelon.

## Opbygning (det, der er særligt for Pizzalicious)

- `Config.php` – shops = Kitzhens feeds (partnerid 14854, ejerens valg 5. nov. 2026). Kun pizzarelaterede produkter matcher en kategori; resten bliver ukategoriseret og vises ikke. Kategorier: `pizzaovne` (gas, træpiller, elektrisk, til køkkenet, til grill – `trae-pizzaovn` blev til `pizzaovn-traepiller` 5. nov. 2026 med 301), `pizzasten` (pizzasten, bagestål, pizzapande), `pizzaspader`, `pizzaskaerere`, `dej` (æltemaskine, dejkasse), `tilbehoer`, `ingredienser` (pizzamel, tomater). Kategorier kan have `not`. `brand_categories()`: Ooni/Gozney-tilbehør uden kategoriord i navnet → tilbehør.
- `content/best/<slug>.php` – 7 købsguides på roden (`/bedste-pizzaovn/` osv.), omskrevet fra det gamle site. Alle er "vores vurdering" indtil videre (ingen anmeldelseslinks). Højst 5 modeller, valgt via EAN, på lager og med billede.
- `content/legacy-images.php` – guidernes billeder fra det gamle site (ejerens ønske: behold billederne).
- `content/legacy-posts.php` + `playform_import_legacy_posts()` (`inc/setup.php`) – artiklerne fra det gamle site flyttes 1:1 via det gamle sites REST API: samme adresse, tekst, links, dato og billede. **Flere er solgte linkbuilding-artikler: tekst og links må aldrig ændres, og adressen skal blive.** `hidden` = vises ikke i lister (meta `_pf_hidden`, kategori "Annonce"), men står på sin adresse og i sitemap. Importen kører kun, så længe sitet ikke selv ligger på pizzalicious.dk; en kopi af hver artikel gemmes i `uploads/playform/legacy-posts/`.
- `inc/best.php` → `playform_legacy_redirects()` – gamle adresser (`/bedste-pizzaovn-test/` → `/bedste-pizzaovn/` osv.).
- Accentfarve `#CE2B37` (den røde fra logoet) med hvid tekst (`--on-accent`). Logoet er højt (badge + to linjer), så det har egen højde i CSS (sidst i `main.css`).
- Kategoribilleder fra Pexels: pizzaovne 29626981, pizzasten 35968920, pizzaspader 32293382, pizzaskaerere 5953531, dej 32293385, tilbehoer 8250287, ingredienser 32293380.
- Søgemaskiner er slået fra på det midlertidige domæne (`playform_launch()`) og slås til, når `playform_move_domain()` flytter sitet til pizzalicious.dk.

## Vigtige regler

Samme som Kitzhen og Fitbase: butikkernes egne tilbud (højst 14 dage), AI må aldrig opfinde fakta, affiliate-links med `rel="sponsored nofollow"` gennem `playform_aff_url()`, ingen produkter uden billede, ingen tredjepartskald fra den offentlige side, redaktørens rettelser vinder, guides påstår aldrig egen test. Se Kitzhens CLAUDE.md for detaljer om klasserne.

## Udrulning

Hvert push til `main` udrulles via GitHub Actions (FTPS) til SiteGround: `frederikb11.sg-host.com/public_html`. Kræver secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`. Når domænet skiftes til pizzalicious.dk i SiteGround, finder udrulningen selv den nye mappe og FTP-kontoen (`@pizzalicious.dk`).

Cronjobs på SiteGround (som Kitzhen):

```
30 5,17 * * *  cd /home/customer/www/frederikb11.sg-host.com/public_html && wp playform import --quiet
*/5 * * * *    cd /home/customer/www/frederikb11.sg-host.com/public_html && wp cron event run --due-now --quiet
```

## Statistik

Matomo via WordPress-pluginnet "Matomo Analytics", hvis det installeres – `inc/consent.php` er klar (cookiefri indtil samtykke).

**SEO i købsguides (ejerens ønske 5. nov. 2026):** folk søger "bedste <søgeord>" og "<søgeord> test". `playform_guide_keyword()` (`keyword` i artikelfilen, ellers titlen før kolon) giver titlen "Bedste <søgeord>: …", sidetitlen "Bedste <søgeord> <år> → Se vores top N", "bedste <søgeord>" i kort-fortalt og sammenligningen, og nederst et afsnit (`playform_guide_test_note()`), der ærligt siger, at det ikke er en test. Overskriften har ikke ordet "test" (7. nov. 2026, efter Forbrugerombudsmandens sag mod Obsidian Media) – kun brødteksten i afsnittet må nævne "<søgeord> test". Artikler med reklamelinks mærkes "Reklamelinks"/"Annonce" i alle lister og i metabeskrivelsen (`playform_post_ad_label()`), og hvert produktvalg siger "ikke testet". aldrig "testvinder", "bedst i test" som påstand eller lignende.
