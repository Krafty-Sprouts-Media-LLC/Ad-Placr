<?php
/**
 * Analytics helpers: event normalization, retention cutoff, persist gate.
 *
 * @package AdPlacr
 * @since 2.5.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers analytics normalization and pure schema/retention decisions.
 *
 * @since 2.5.0
 */
final class AnalyticsTest extends TestCase {

	/**
	 * Supported event names normalize while unknown names are rejected.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public function test_normalize_event_type(): void {
		$this->assertSame( 'impression', Ad_Placr_Analytics::normalize_event_type( 'impression' ) );
		$this->assertSame( 'click', Ad_Placr_Analytics::normalize_event_type( 'CLICK' ) );
		$this->assertNull( Ad_Placr_Analytics::normalize_event_type( 'view' ) );
		$this->assertNull( Ad_Placr_Analytics::normalize_event_type( '' ) );
	}

	/**
	 * Retention uses the documented ninety-day GMT cutoff.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public function test_retention_cutoff_is_ninety_days(): void {
		$now    = 1_700_000_000;
		$cutoff = Ad_Placr_Analytics::retention_cutoff_gmt( $now );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', $now - ( 90 * DAY_IN_SECONDS ) ), $cutoff );
	}

	/**
	 * First-party persistence follows the explicit opt-in toggle.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public function test_should_persist_respects_toggle(): void {
		$this->assertTrue( Ad_Placr_Analytics::should_persist( true ) );
		$this->assertFalse( Ad_Placr_Analytics::should_persist( false ) );
	}

	/**
	 * The event table basename remains stable for migration and uninstall.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public function test_table_name_uses_prefix_placeholder_shape(): void {
		$name = Ad_Placr_Analytics::table_basename();
		$this->assertSame( 'ad_placr_events', $name );
	}

	/**
	 * The cleanup hook name remains paired with its registered callback.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public function test_cron_hook_constant(): void {
		$this->assertSame( 'ad_placr_analytics_cleanup', Ad_Placr_Analytics::CRON_HOOK );
	}

	/**
	 * Deactivation clears every scheduled analytics cleanup event.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_deactivation_clears_analytics_cleanup_schedule(): void {
		$GLOBALS['ad_placr_test_cleared_hooks'] = array();

		Ad_Placr_Plugin::instance()->deactivate();

		$this->assertSame(
			array( Ad_Placr_Analytics::CRON_HOOK ),
			$GLOBALS['ad_placr_test_cleared_hooks']
		);
	}

	/**
	 * Statistics cells distinguish disabled storage from a zero count.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function test_format_stat_cell(): void {
		$this->assertSame( '—', Ad_Placr_Analytics::format_stat_cell( 12, false ) );
		$this->assertSame( '12', Ad_Placr_Analytics::format_stat_cell( 12, true ) );
		$this->assertSame( '0', Ad_Placr_Analytics::format_stat_cell( 0, true ) );
	}

	/**
	 * Version identifiers discard unsafe characters and fit the schema column.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function test_version_id_normalization_is_bounded_and_safe(): void {
		$this->assertSame(
			'11111111-1111-4111-8111-111111111111',
			Ad_Placr_Analytics::normalize_version_id( '11111111-1111-4111-8111-111111111111***' )
		);
		$this->assertSame( '', Ad_Placr_Analytics::normalize_version_id( '***' ) );
	}

	/**
	 * Tracking context contains stable version data and no Placement fallback.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function test_tracking_context_uses_version_not_placement(): void {
		$context = Ad_Placr_Analytics::normalize_tracking_context(
			array(
				'event'      => 'click',
				'ad_id'      => 42,
				'version_id' => 'version-a',
			)
		);

		$this->assertSame( 'version-a', $context['version_id'] );
		$this->assertArrayNotHasKey( 'placement_id', $context );
	}

	/**
	 * The exact clean version-two event table shape is accepted.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function test_event_table_schema_accepts_clean_version_columns(): void {
		$this->assertTrue(
			Ad_Placr_Analytics::is_event_table_schema_current(
				array( 'id', 'event_type', 'ad_id', 'version_id', 'created_at' )
			)
		);
	}

	/**
	 * Missing version data and retained Placement columns reject the schema.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function test_event_table_schema_rejects_missing_or_legacy_columns(): void {
		$this->assertFalse(
			Ad_Placr_Analytics::is_event_table_schema_current(
				array( 'id', 'event_type', 'ad_id', 'created_at' )
			)
		);
		$this->assertFalse(
			Ad_Placr_Analytics::is_event_table_schema_current(
				array( 'id', 'event_type', 'ad_id', 'version_id', 'placement_id', 'created_at' )
			)
		);
	}
}
