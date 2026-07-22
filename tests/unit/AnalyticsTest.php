<?php
/**
 * Analytics helpers: event normalization, retention cutoff, persist gate.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase {

	public function test_normalize_event_type(): void {
		$this->assertSame( 'impression', Ad_Placr_Analytics::normalize_event_type( 'impression' ) );
		$this->assertSame( 'click', Ad_Placr_Analytics::normalize_event_type( 'CLICK' ) );
		$this->assertNull( Ad_Placr_Analytics::normalize_event_type( 'view' ) );
		$this->assertNull( Ad_Placr_Analytics::normalize_event_type( '' ) );
	}

	public function test_retention_cutoff_is_ninety_days(): void {
		$now    = 1_700_000_000;
		$cutoff = Ad_Placr_Analytics::retention_cutoff_gmt( $now );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', $now - ( 90 * DAY_IN_SECONDS ) ), $cutoff );
	}

	public function test_should_persist_respects_toggle(): void {
		$this->assertTrue( Ad_Placr_Analytics::should_persist( true ) );
		$this->assertFalse( Ad_Placr_Analytics::should_persist( false ) );
	}

	public function test_table_name_uses_prefix_placeholder_shape(): void {
		$name = Ad_Placr_Analytics::table_basename();
		$this->assertSame( 'ad_placr_events', $name );
	}

	public function test_cron_hook_constant(): void {
		$this->assertSame( 'ad_placr_analytics_cleanup', Ad_Placr_Analytics::CRON_HOOK );
	}

	public function test_format_stat_cell(): void {
		$this->assertSame( '—', Ad_Placr_Analytics::format_stat_cell( 12, false ) );
		$this->assertSame( '12', Ad_Placr_Analytics::format_stat_cell( 12, true ) );
		$this->assertSame( '0', Ad_Placr_Analytics::format_stat_cell( 0, true ) );
	}
}
