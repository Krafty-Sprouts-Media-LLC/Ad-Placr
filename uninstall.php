<?php
/**
 * Uninstall handler — removes plugin options.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ad_placr_settings' );
