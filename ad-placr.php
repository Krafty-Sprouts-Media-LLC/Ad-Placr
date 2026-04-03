<?php
/**
 * Plugin Name:       Ad Placr
 * Plugin URI:        https://kraftysprouts.com
 * Description:       Flexible ad placements for WordPress — starting with a floating footer sticky slot.
 * Version:           0.1.4
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Krafty Sprouts Media LLC
 * Author URI:        https://kraftysprouts.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ad-placr
 *
 * @package AdPlacr
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AD_PLACR_VERSION', '0.1.4' );
define( 'AD_PLACR_PLUGIN_FILE', __FILE__ );
define( 'AD_PLACR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AD_PLACR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-plugin.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-settings-page.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-footer-sticky.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-plugin-updater.php';

Ad_Placr_Plugin::instance()->boot();
