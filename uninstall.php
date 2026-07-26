<?php
/**
 * Uninstall handler — removes plugin options and analytics table.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ad_placr_settings' );
delete_option( 'ad_placr_analytics_schema' );

global $wpdb;
$table = $wpdb->prefix . 'ad_placr_events';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

wp_clear_scheduled_hook( 'ad_placr_analytics_cleanup' );
