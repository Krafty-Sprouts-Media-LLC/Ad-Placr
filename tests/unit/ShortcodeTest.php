<?php
/**
 * Shortcode attribute resolution (pure).
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

/**
 * Verifies manual-shortcode attribute resolution and wrapper conventions.
 *
 * @since 2.3.0
 */
final class ShortcodeTest extends TestCase {

	/**
	 * Assert that a positive shortcode Ad attribute resolves to its integer ID.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_resolve_ad_id_accepts_positive_ad_attribute(): void {
		$this->assertSame( 42, Ad_Placr_Shortcode::resolve_ad_id( array( 'ad' => '42' ) ) );
	}

	/**
	 * Assert that omitted, negative, and legacy attributes cannot select an Ad.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_resolve_ad_id_rejects_missing_or_invalid_values(): void {
		$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array() ) );
		$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array( 'ad' => '-1' ) ) );
		$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array( 'placement' => '9' ) ) );
	}

	/**
	 * Assert that shortcode output uses the canonical manual-location modifier.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_modifier_uses_plain_manual_location_language(): void {
		$this->assertSame( 'ad-placr--shortcode', Ad_Placr_Shortcode::modifier_class() );
	}

	/**
	 * Assert that the optional sidebar sticky modifier remains unchanged.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function test_widget_sticky_modifier(): void {
		$this->assertSame( 'ad-placr--widget-sticky', Ad_Placr_Widget::sticky_modifier( true ) );
		$this->assertSame( '', Ad_Placr_Widget::sticky_modifier( false ) );
	}
}
