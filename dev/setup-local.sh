#!/usr/bin/env bash
# Opretter en lokal WordPress-udviklingsside i .local/ med SQLite (ingen MySQL nødvendig).
# Brug: dev/setup-local.sh <mappe-med-kildefiler>
#   Kildemappen skal indeholde: wordpress/, wc/woocommerce/, sqlite-database-integration/
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:?Angiv mappe med kildefiler}"
WP="$ROOT/.local/wp"
WPCLI="${WPCLI:-wp}"
URL="${PLAYFORM_URL:-http://localhost:8080}"

rm -rf "$WP"
mkdir -p "$WP"
cp -r "$SRC/wordpress/." "$WP/"
rm -rf "$WP/.git"

# SQLite-driver
SQLITE_SRC="$SRC/sqlite-database-integration/packages/plugin-sqlite-database-integration"
SQLITE_DST="$WP/wp-content/plugins/sqlite-database-integration"
cp -rL "$SQLITE_SRC" "$SQLITE_DST"
sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SQLITE_DST#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
  "$SQLITE_DST/db.copy" > "$WP/wp-content/db.php"

# WooCommerce
cp -r "$SRC/wc/woocommerce" "$WP/wp-content/plugins/woocommerce"

# Vores egen kode linkes ind, så ændringer slår igennem med det samme
ln -s "$ROOT/wp-content/plugins/playform-feeds" "$WP/wp-content/plugins/playform-feeds"
ln -s "$ROOT/wp-content/themes/playform" "$WP/wp-content/themes/playform"

cat > "$WP/wp-config.php" <<PHP
<?php
define( 'DB_NAME', 'playform' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', 'playform.sqlite' );
\$table_prefix = 'wp_';
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );
define( 'WP_HOME', '$URL' );
define( 'WP_SITEURL', '$URL' );
define( 'WP_MEMORY_LIMIT', '512M' );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP

cd "$WP"
$WPCLI core install --url="$URL" --title="Playform" --admin_user=admin --admin_password=admin \
  --admin_email=dev@playform.local --skip-email --allow-root
$WPCLI plugin activate woocommerce --allow-root
$WPCLI plugin activate playform-feeds --allow-root || true
$WPCLI theme activate playform --allow-root || true
$WPCLI rewrite structure '/%postname%/' --allow-root
$WPCLI option update blogdescription "Fitnessudstyr fra flere danske shops" --allow-root
$WPCLI option update woocommerce_currency DKK --allow-root
$WPCLI option update woocommerce_default_country DK --allow-root
$WPCLI option update woocommerce_price_thousand_sep "." --allow-root
$WPCLI option update woocommerce_price_decimal_sep "," --allow-root
$WPCLI option update woocommerce_price_num_decimals 0 --allow-root
$WPCLI option update woocommerce_currency_pos right_space --allow-root
echo "Lokal WordPress klar i $WP ($URL) – login: admin/admin"
