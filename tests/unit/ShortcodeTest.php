<?php
/**
 * Shortcode attribute resolution (pure).
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class ShortcodeTest extends TestCase {

	public function test_resolve_ad_id_accepts_positive_ad_attribute(): void {
		$this->assertSame( 42, Ad_Placr_Shortcode::resolve_ad_id( array( 'ad' => '42' ) ) );
	}

	public function test_resolve_ad_id_rejects_missing_or_invalid_values(): void {
		$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array() ) );
		$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array( 'ad' => '-1' ) ) );
		$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array( 'placement' => '9' ) ) );
	}

	public function test_modifier_uses_plain_manual_location_language(): void {
		$this->assertSame( 'ad-placr--shortcode', Ad_Placr_Shortcode::modifier_class() );
	}

	public function test_widget_sticky_modifier(): void {
		$this->assertSame( 'ad-placr--widget-sticky', Ad_Placr_Widget::sticky_modifier( true ) );
		$this->assertSame( '', Ad_Placr_Widget::sticky_modifier( false ) );
	}
}
