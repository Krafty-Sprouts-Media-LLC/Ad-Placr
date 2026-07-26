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
delete_option( 'ad_placr_db_version' );
delete_option( 'ad_placr_analytics_schema' );
delete_option( 'ad_placr_unified_migration_map' );
delete_option( 'ad_placr_unified_migration_lock' );

global $wpdb;
$table = $wpdb->prefix . 'ad_placr_events';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

$timestamp = wp_next_scheduled( 'ad_placr_analytics_cleanup' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'ad_placr_analytics_cleanup' );
}
