<?php
/**
 * Plugin Name: Playform Feeds
 * Description: Importerer produktfeeds fra Partner-ads, sorterer dem i sitets kategorier (Config.php) og holder priser, lager og prishistorik opdateret.
 * Version: 0.1.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Text Domain: playform-feeds
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAYFORM_FEEDS_VERSION', '0.3.0' );
define( 'PLAYFORM_FEEDS_DIR', __DIR__ );

require_once __DIR__ . '/src/autoload.php';

register_activation_hook( __FILE__, [ \Playform\Feeds\Install::class, 'activate' ] );

add_action( 'plugins_loaded', [ \Playform\Feeds\Plugin::class, 'boot' ] );
