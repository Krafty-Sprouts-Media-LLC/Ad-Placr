<?php
/**
 * Manual handlers must not invent meta keys (Adsly bug #1 guard).
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class ManualMetaKeysTest extends TestCase {

	public function test_ad_meta_constants_match_documented_keys(): void {
		$this->assertSame( '_ad_placr_code', Ad_Placr_Ad::META_CODE );
		$this->assertSame( '_ad_placr_mobile_code', Ad_Placr_Ad::META_MOBILE_CODE );
		$this->assertSame( '_ad_placr_status', Ad_Placr_Ad::META_STATUS );
	}

	public function test_placement_meta_constants_match_documented_keys(): void {
		$this->assertSame( '_ad_placr_position', Ad_Placr_Placement::META_POSITION );
		$this->assertSame( '_ad_placr_status', Ad_Placr_Placement::META_STATUS );
		$this->assertSame( '_ad_placr_targeting', Ad_Placr_Placement::META_TARGETING );
		$this->assertSame( '_ad_placr_ads', Ad_Placr_Placement::META_ADS );
	}

	public function test_shortcode_and_widget_sources_have_no_hardcoded_meta_keys(): void {
		$files = array(
			dirname( __DIR__, 2 ) . '/includes/class-ad-placr-shortcode.php',
			dirname( __DIR__, 2 ) . '/includes/class-ad-placr-widget.php',
		);

		foreach ( $files as $path ) {
			$this->assertFileExists( $path );
			$src = file_get_contents( $path );
			$this->assertIsString( $src );
			$this->assertStringNotContainsString(
				"'_ad_placr_",
				$src,
				basename( $path ) . ' must not hard-code meta key strings'
			);
			$this->assertStringNotContainsString(
				'"_ad_placr_',
				$src,
				basename( $path ) . ' must not hard-code meta key strings'
			);
		}
	}
}
