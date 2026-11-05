# Pizzalicious

Pizzaovne og pizzaudstyr fra flere danske shops samlet ét sted (pizzalicious.dk). Samme kodebase som Kitzhen og Fitbase; nichen er sat op i `wp-content/plugins/playform-feeds/src/Config.php`. WordPress + WooCommerce med et eget importplugin og et eget tema.

## Indhold

| Mappe | Hvad |
|---|---|
| `wp-content/plugins/playform-feeds/` | Importen: Partner-ads-feeds, sortering, priser, lager, prishistorik, AI-tekster |
| `wp-content/themes/playform/` | Temaet: forside, kategoriside, produktside |
| `dev/` | Lokal udvikling (WordPress med SQLite) |
| `CLAUDE.md` | Noter til Claude Code om projektets regler |

## Sådan sættes siden op på hosting

1. **Hosting** med PHP 8.1+, MySQL/MariaDB og SSH/WP-CLI. 7.000+ produkter kræver mindst 512 MB PHP-hukommelse og gerne objekt-cache (Redis).
2. **Installer WordPress og WooCommerce** som normalt.
3. **Upload** `wp-content/plugins/playform-feeds` og `wp-content/themes/playform` (fx via git eller SFTP).
4. **Aktiver** pluginnet *Playform Feeds* og temaet *Playform*. Temaet opretter forside, "Alle produkter", "Vores butikker" m.fl. og sætter danske URL'er (`/kategori/…`, `/produkt/…`).
5. **Indstillinger › Permalinks › Gem** én gang.
6. **Første import** – ingen SSH nødvendig: gå til **Værktøjer › Playform Feeds** og tryk **"Kør import nu"**. Knappen arbejder sig igennem feeds og billeder i mange små bidder (så serverens 120-sekunders grænse pr. kald aldrig rammes) og viser status, til den skriver "Færdig!". Det tager typisk 10–20 minutter – lad siden stå åben. Har du SSH/WP-CLI, går det hurtigere:
   ```bash
   wp playform import
   wp playform images
   ```
7. **Skrift:** Læg BDO Grotesk som woff2 i `wp-content/themes/playform/assets/fonts/` (se README der).
8. **Cronjob** (i stedet for WordPress' egen cron), så importen også kører automatisk kl. 05:30/17:30. Tilføj `define( 'DISABLE_WP_CRON', true );` i `wp-config.php` og læg dette i serverens crontab (eller Site Tools › Devs › Cron Jobs på SiteGround):
   ```
   30 5,17 * * *  cd /sti/til/wordpress && wp playform import --quiet
   */5 * * * *    cd /sti/til/wordpress && wp cron event run --due-now --quiet
   ```

## AI (valgfrit)

Tilføj din Claude API-nøgle i `wp-config.php`:

```php
define( 'PLAYFORM_ANTHROPIC_API_KEY', 'sk-ant-…' );
```

eller under **Værktøjer › Playform Feeds**.

```bash
wp playform ai-classify                         # sorter produkter, reglerne ikke kunne placere
wp playform ai-texts --category=lobebaand --limit=50   # skriv tekstudkast
```

Tekster godkendes under **Produkter › Tekster til godkendelse** eller på det enkelte produkt. Der er et loft på 200 tekster pr. døgn (`playform_ai_daily_cap`). Sæt også en udgiftsgrænse i Claude Console.

## Nyhedsbrev

Vælg Brevo eller MailerLite under **Værktøjer › Playform Feeds** og indsæt API-nøgle og liste-/gruppe-id. Tilmeldinger sendes direkte til tjenesten med bekræftelsesmail (double opt-in); WordPress gemmer ingen e-mails.

- **Brevo:** opret en liste og en skabelon til bekræftelsesmailen (Double opt-in-skabelon) og angiv begges id.
- **MailerLite:** opret en gruppe og slå "Double opt-in for API and integrations" til under kontoindstillinger.

## Guides

`wp-content/themes/playform/content/guides.php` opretter de første fire guides som kladder: `wp eval-file wp-content/themes/playform/content/guides.php`. Produkter vises i guides med shortcoden `[playform_produkter kategori="gaabaand" antal="4"]`.

## Andre kommandoer

```bash
wp playform stats          # produkter pr. kategori
wp playform reclassify     # kør sorteringsreglerne igen efter ændringer i Config.php
wp playform refresh        # genberegn alle produkter
wp playform drops          # genberegn målte rabatter
wp playform images         # hent produktbilleder til egen server (kører også efter hver import)
```

## Lokal udvikling

```bash
dev/setup-local.sh <mappe med wordpress/, wc/woocommerce/ og sqlite-database-integration/>
cd .local/wp && php -S localhost:8080 -t . ../../dev/router.php
```

Login: admin / admin.

## Ny niche (fx hvidevarer)

Kopiér projektet, ret `Config.php` (shops, kategorier, regler) og `inc/navigation.php` i temaet, skift logo og billeder.
