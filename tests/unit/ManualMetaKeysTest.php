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
	}
}
