<?php
/**
 * Manual handlers must not invent meta keys (Adsly bug #1 guard).
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

/**
 * Verifies the unified Ad model owns every manual meta key.
 *
 * @since 2.7.0
 */
final class ManualMetaKeysTest extends TestCase {

	/**
	 * Assert the unified meta-key contract uses one canonical constant per key.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_ad_meta_constants_match_documented_keys(): void {
		$this->assertSame( '_ad_placr_position', Ad_Placr_Ad::META_POSITION );
		$this->assertSame( '_ad_placr_targeting', Ad_Placr_Ad::META_TARGETING );
		$this->assertSame( '_ad_placr_versions', Ad_Placr_Ad::META_VERSIONS );
		$this->assertSame( '_ad_placr_notes', Ad_Placr_Ad::META_NOTES );
		$this->assertSame( '_ad_placr_alignment', Ad_Placr_Ad::META_ALIGNMENT );
	}

	/**
	 * Assert that manual handlers do not couple to transitional storage classes.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_manual_handlers_do_not_read_legacy_placement_or_meta_contracts(): void {
		$root = dirname( __DIR__, 2 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test verifies local source contracts.
		$shortcode_source = (string) file_get_contents( $root . '/includes/class-ad-placr-shortcode.php' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test verifies local source contracts.
		$widget_source = (string) file_get_contents( $root . '/includes/class-ad-placr-widget.php' );

		foreach ( array( $shortcode_source, $widget_source ) as $source ) {
			$this->assertStringNotContainsString( 'META_', $source );
			$this->assertStringNotContainsString( 'Ad_Placr_Placement', $source );
		}
	}
}
