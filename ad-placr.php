<?php
/**
 * Plugin Name:       Ad Placr
 * Plugin URI:        https://kraftysprouts.com
 * Description:       Flexible ad placements: footer sticky and in-content paragraph slots.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Krafty Sprouts Media LLC
 * Author URI:        https://kraftysprouts.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ad-placr
 *
 * Bootstrap only: constants, includes, then hand off to Ad_Placr_Plugin.
 * No heavy work at file-load time — subsystems register on hooks inside boot().
 *
 * @package AdPlacr
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AD_PLACR_VERSION', '2.0.0' );
define( 'AD_PLACR_PLUGIN_FILE', __FILE__ );
define( 'AD_PLACR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AD_PLACR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Load order: core singleton first, then settings + placements + updater.
 * Each class exposes ::register() (or boot()) — nothing runs until boot() below.
 */
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-plugin.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-settings-page.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-footer-sticky.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-in-content.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-positions.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-ad.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-placement.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-migration.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-plugin-updater.php';

Ad_Placr_Plugin::instance()->boot();
