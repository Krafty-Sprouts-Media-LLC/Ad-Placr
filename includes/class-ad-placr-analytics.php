<?php
/**
 * Analytics: external hooks (always) + opt-in first-party event storage.
 *
 * Table `{prefix}ad_placr_events` stores impression/click rows with no PII.
 * Retention cron deletes rows older than 90 days. Storage writes are gated by
 * `analytics_enabled`; `do_action` hooks always fire from `track()`.
 *
 * @package AdPlacr
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks ad events and maintains the events table + cleanup cron.
 *
 * @since 2.5.0
 */
final class Ad_Placr_Analytics {

	/**
	 * Cron hook name (must always have a registered callback).
	 *
	 * @since 2.5.0
	 */
	public const CRON_HOOK = 'ad_placr_analytics_cleanup';

	/**
	 * Retention window in days.
	 *
	 * @since 2.5.0
	 */
	public const RETENTION_DAYS = 90;

	/**
	 * Schema version option for dbDelta upgrades.
	 *
	 * @since 2.5.0
	 */
	public const OPTION_SCHEMA_VERSION = 'ad_placr_analytics_schema';

	/**
	 * Current analytics schema version.
	 *
	 * @since 2.5.0
	 */
	public const SCHEMA_VERSION = 2;

	/**
	 * Register cron callback and front-end tracking assets.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_cleanup' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracking_assets' ) );
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
	}

	/**
	 * Create/upgrade schema on plugin update without requiring reactivation.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( self::OPTION_SCHEMA_VERSION, 0 ) >= self::SCHEMA_VERSION ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
			return;
		}

		self::install();
	}

	/**
	 * Table basename without `$wpdb->prefix`.
	 *
	 * @since 2.5.0
	 *
	 * @return string
	 */
	public static function table_basename(): string {
		return 'ad_placr_events';
	}

	/**
	 * Fully qualified table name.
	 *
	 * @since 2.5.0
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::table_basename();
	}

	/**
	 * Normalize a raw event type to impression|click, or null if invalid.
	 *
	 * @since 2.5.0
	 *
	 * @param string $raw Raw event string.
	 * @return string|null
	 */
	public static function normalize_event_type( string $raw ): ?string {
		$type = strtolower( trim( $raw ) );
		if ( in_array( $type, array( 'impression', 'click' ), true ) ) {
			return $type;
		}

		return null;
	}

	/**
	 * Normalize a stable Ad version identifier for storage and external hooks.
	 *
	 * @since 2.6.0
	 *
	 * @param string $version_id Raw stable version identifier.
	 * @return string Safe identifier bounded to the database column width.
	 */
	public static function normalize_version_id( string $version_id ): string {
		$version_id = (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', $version_id );

		return substr( $version_id, 0, 64 );
	}

	/**
	 * Normalize an event context without retaining obsolete Placement data.
	 *
	 * @since 2.6.0
	 *
	 * @param array<string, mixed> $context Raw event context.
	 * @return array{event:string,ad_id:int,version_id:string}
	 */
	public static function normalize_tracking_context( array $context ): array {
		$event = self::normalize_event_type( (string) ( $context['event'] ?? '' ) );

		return array(
			'event'      => null === $event ? '' : $event,
			'ad_id'      => max( 0, (int) ( $context['ad_id'] ?? 0 ) ),
			'version_id' => self::normalize_version_id( (string) ( $context['version_id'] ?? '' ) ),
		);
	}

	/**
	 * Confirm the physical event table has exactly the schema-v2 columns.
	 *
	 * @since 2.6.0
	 *
	 * @param array<int, string> $columns Physical column names.
	 * @return bool True when the clean Ad/version event shape is present.
	 */
	public static function is_event_table_schema_current( array $columns ): bool {
		$expected = array( 'ad_id', 'created_at', 'event_type', 'id', 'version_id' );
		$actual   = array_values( array_unique( $columns ) );
		sort( $actual );

		return $expected === $actual;
	}

	/**
	 * GMT datetime string for the retention cutoff.
	 *
	 * @since 2.5.0
	 *
	 * @param int $now  Unix timestamp (UTC interpretation).
	 * @param int $days Retention days (default 90).
	 * @return string MySQL DATETIME.
	 */
	public static function retention_cutoff_gmt( int $now, int $days = self::RETENTION_DAYS ): string {
		$seconds = max( 1, $days ) * ( defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400 );

		return gmdate( 'Y-m-d H:i:s', $now - $seconds );
	}

	/**
	 * Whether first-party storage should write a row.
	 *
	 * Pure: callers pass the resolved toggle. Hooks still fire regardless.
	 *
	 * @since 2.5.0
	 *
	 * @param bool $enabled analytics_enabled setting.
	 * @return bool
	 */
	public static function should_persist( bool $enabled ): bool {
		return $enabled;
	}

	/**
	 * Whether opt-in storage is enabled in settings.
	 *
	 * @since 2.5.0
	 *
	 * @return bool
	 */
	public static function is_storage_enabled(): bool {
		$settings = Ad_Placr_Plugin::get_settings();

		return ! empty( $settings['analytics_enabled'] );
	}

	/**
	 * Format a list-table stats cell.
	 *
	 * @since 2.6.0
	 *
	 * @param int  $count            Event count.
	 * @param bool $storage_enabled  Whether first-party storage is on.
	 * @return string Count as string, or em dash when storage is off.
	 */
	public static function format_stat_cell( int $count, bool $storage_enabled ): string {
		if ( ! $storage_enabled ) {
			return '—';
		}

		return (string) max( 0, $count );
	}

	/**
	 * Count stored events, optionally filtered by type, Ad, or version.
	 *
	 * Returns 0 when the table is missing or storage has never been installed.
	 *
	 * @since 2.6.0
	 *
	 * @param string|null $event_type impression|click|null (all).
	 * @param int         $ad_id      Ad ID filter (0 = any).
	 * @param string      $version_id Stable version ID filter (empty = any).
	 * @return int
	 */
	public static function count_events( ?string $event_type = null, int $ad_id = 0, string $version_id = '' ): int {
		global $wpdb;

		if ( (int) get_option( self::OPTION_SCHEMA_VERSION, 0 ) < self::SCHEMA_VERSION ) {
			return 0;
		}

		$table = self::table_name();
		$where = array( '1=1' );
		$args  = array();

		if ( null !== $event_type ) {
			$type = self::normalize_event_type( $event_type );
			if ( null === $type ) {
				return 0;
			}
			$where[] = 'event_type = %s';
			$args[]  = $type;
		}

		if ( $ad_id > 0 ) {
			$where[] = 'ad_id = %d';
			$args[]  = $ad_id;
		}

		$version_id = self::normalize_version_id( $version_id );
		if ( '' !== $version_id ) {
			$where[] = 'version_id = %s';
			$args[]  = $version_id;
		}

		$sql = 'SELECT COUNT(*) FROM {table} WHERE ' . implode( ' AND ', $where );
		$sql = str_replace( '{table}', $table, $sql );

		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders built above.
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Aggregates from analytics table.
		$count = $wpdb->get_var( $sql );

		return absint( $count );
	}

	/**
	 * Record an event: always fire the action; optionally insert a row.
	 *
	 * @since 2.5.0
	 *
	 * @param string $event_type impression|click.
	 * @param int    $ad_id      Ad post ID.
	 * @param string $version_id Stable Ad version identifier.
	 * @return bool True when the event type was valid (hook fired).
	 */
	public static function track( string $event_type, int $ad_id, string $version_id = '' ): bool {
		$ctx = self::normalize_tracking_context(
			array(
				'event'      => $event_type,
				'ad_id'      => $ad_id,
				'version_id' => $version_id,
			)
		);

		if ( '' === $ctx['event'] || $ctx['ad_id'] < 1 ) {
			return false;
		}

		if ( 'impression' === $ctx['event'] ) {
			/**
			 * Fires on a tracked ad impression (always, even if storage is off).
			 *
			 * @since 2.5.0
			 *
			 * @param int                                            $ad_id Ad post ID.
			 * @param array{event:string,ad_id:int,version_id:string} $ctx   Event context (no PII).
			 */
			do_action( 'ad_placr_impression', $ctx['ad_id'], $ctx );
		} else {
			/**
			 * Fires on a tracked ad click (always, even if storage is off).
			 *
			 * @since 2.5.0
			 *
			 * @param int                                            $ad_id Ad post ID.
			 * @param array{event:string,ad_id:int,version_id:string} $ctx   Event context (no PII).
			 */
			do_action( 'ad_placr_click', $ctx['ad_id'], $ctx );
		}

		if ( self::should_persist( self::is_storage_enabled() ) ) {
			self::insert_event( $ctx['event'], $ctx['ad_id'], $ctx['version_id'] );
		}

		return true;
	}

	/**
	 * Insert one event row (no PII columns).
	 *
	 * @since 2.5.0
	 *
	 * @param string $event_type Normalized type.
	 * @param int    $ad_id      Ad ID.
	 * @param string $version_id Stable Ad version identifier.
	 * @return bool
	 */
	public static function insert_event( string $event_type, int $ad_id, string $version_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Single analytics write path by design.
		$result = $wpdb->insert(
			self::table_name(),
			array(
				'event_type' => $event_type,
				'ad_id'      => $ad_id,
				'version_id' => self::normalize_version_id( $version_id ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%d', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Cron callback — prune expired rows (void for WP cron).
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public static function cron_cleanup(): void {
		self::cleanup();
	}

	/**
	 * Delete events older than the retention window.
	 *
	 * @since 2.5.0
	 *
	 * @return int Rows deleted.
	 */
	public static function cleanup(): int {
		global $wpdb;

		$table  = self::table_name();
		$cutoff = self::retention_cutoff_gmt( time() );

		/*
		 * Table name is `$wpdb->prefix` + constant only — splice after prepare
		 * so the datetime stays parameterized.
		 */
		$sql = $wpdb->prepare( 'DELETE FROM {table} WHERE created_at < %s', $cutoff );
		$sql = str_replace( '{table}', $table, $sql );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Table name spliced from trusted prefix+constant.
		$deleted = $wpdb->query( $sql );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Create / upgrade the events table (dbDelta) and schedule cleanup.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$installed       = (int) get_option( self::OPTION_SCHEMA_VERSION, 0 );

		/*
		 * Schema v1 was local and unreleased, and its Placement rows cannot be
		 * mapped reliably to stable Ad versions. Replace it before dbDelta so
		 * no obsolete column or misleading statistics survive the upgrade.
		 */
		if ( 1 === $installed ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is trusted prefix plus a class constant.
			$dropped = $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
			if ( false === $dropped ) {
				return;
			}
		}

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(16) NOT NULL,
			ad_id bigint(20) unsigned NOT NULL,
			version_id varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY ad_event (ad_id, event_type),
			KEY version_event (version_id, event_type)
		) {$charset_collate};";

		dbDelta( $sql );

		/*
		 * dbDelta can partially apply a schema and does not remove extra columns.
		 * Read the physical shape before recording success so an incomplete schema
		 * update is retried on the next request.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is trusted prefix plus a class constant.
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		if ( ! self::is_event_table_schema_current( $columns ) ) {
			return;
		}

		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Drop the events table (uninstall).
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::OPTION_SCHEMA_VERSION );
	}

	/**
	 * Enqueue front-end tracking script (IntersectionObserver + clicks).
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public static function enqueue_tracking_assets(): void {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'ad-placr-tracking',
			AD_PLACR_PLUGIN_URL . 'assets/js/tracking.js',
			array(),
			AD_PLACR_VERSION,
			true
		);

		wp_localize_script(
			'ad-placr-tracking',
			'adPlacrTrack',
			array(
				'restUrl' => esc_url_raw( rest_url( 'ad-placr/v1/track' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
