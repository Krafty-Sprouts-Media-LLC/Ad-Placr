<?php
/**
 * Tests the unified Ad domain-model contract.
 *
 * @package AdPlacr
 * @since 2.7.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers version normalization, eligibility, and weighted selection.
 *
 * @since 2.7.0
 */
final class UnifiedAdTest extends TestCase {

	/**
	 * Preserve a version's stable identifier and its persisted order.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_normalize_versions_preserves_stable_ids_and_order(): void {
		$raw = array(
			array(
				'version_id'  => '11111111-1111-4111-8111-111111111111',
				'name'        => 'Version B',
				'code'        => '<ins>b</ins>',
				'mobile_code' => '',
				'weight'      => 3,
				'enabled'     => true,
			),
			array(
				'version_id'  => '22222222-2222-4222-8222-222222222222',
				'name'        => 'Version A',
				'code'        => '<ins>a</ins>',
				'mobile_code' => '<ins>mobile</ins>',
				'weight'      => 1,
				'enabled'     => false,
			),
		);

		$normalized = Ad_Placr_Ad::normalize_versions( $raw );

		$this->assertSame( '11111111-1111-4111-8111-111111111111', $normalized[0]['version_id'] );
		$this->assertSame( '22222222-2222-4222-8222-222222222222', $normalized[1]['version_id'] );
		$this->assertSame( 3, $normalized[0]['weight'] );
		$this->assertFalse( $normalized[1]['enabled'] );
	}

	/**
	 * Ignore version rows that cannot be assigned a stable identifier.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_normalize_versions_drops_rows_without_a_stable_id(): void {
		$normalized = Ad_Placr_Ad::normalize_versions(
			array(
				array(
					'name'    => 'Missing ID',
					'code'    => '<ins>x</ins>',
					'weight'  => 1,
					'enabled' => true,
				),
				'not-an-array',
			)
		);

		$this->assertSame( array(), $normalized );
	}

	/**
	 * Exclude disabled rows and rows without usable creative code.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_eligible_versions_excludes_disabled_and_empty_rows(): void {
		$versions = Ad_Placr_Ad::normalize_versions(
			array(
				array(
					'version_id'  => 'a',
					'name'        => 'A',
					'code'        => '<ins>a</ins>',
					'mobile_code' => '',
					'weight'      => 2,
					'enabled'     => true,
				),
				array(
					'version_id'  => 'b',
					'name'        => 'B',
					'code'        => '',
					'mobile_code' => '',
					'weight'      => 5,
					'enabled'     => true,
				),
				array(
					'version_id'  => 'c',
					'name'        => 'C',
					'code'        => '<ins>c</ins>',
					'mobile_code' => '',
					'weight'      => 3,
					'enabled'     => false,
				),
				array(
					'version_id'  => 'd',
					'name'        => 'D',
					'code'        => '',
					'mobile_code' => '<ins>mobile</ins>',
					'weight'      => 1,
					'enabled'     => true,
				),
			)
		);

		$eligible = Ad_Placr_Ad::eligible_versions( $versions );

		$this->assertSame( array( 'a', 'd' ), array_column( $eligible, 'version_id' ) );
	}

	/**
	 * Allocate weighted rolls only across versions eligible for display.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_weighted_selection_uses_only_eligible_versions(): void {
		$versions = Ad_Placr_Ad::normalize_versions(
			array(
				array(
					'version_id'  => 'a',
					'name'        => 'A',
					'code'        => 'A',
					'mobile_code' => '',
					'weight'      => 3,
					'enabled'     => true,
				),
				array(
					'version_id'  => 'b',
					'name'        => 'B',
					'code'        => 'B',
					'mobile_code' => '',
					'weight'      => 99,
					'enabled'     => false,
				),
				array(
					'version_id'  => 'c',
					'name'        => 'C',
					'code'        => 'C',
					'mobile_code' => '',
					'weight'      => 1,
					'enabled'     => true,
				),
			)
		);

		$this->assertSame( 'a', Ad_Placr_Ad::choose_weighted_version( $versions, 0 )['version_id'] );
		$this->assertSame( 'a', Ad_Placr_Ad::choose_weighted_version( $versions, 2 )['version_id'] );
		$this->assertSame( 'c', Ad_Placr_Ad::choose_weighted_version( $versions, 3 )['version_id'] );
		$this->assertNull( Ad_Placr_Ad::choose_weighted_version( array(), 0 ) );
	}

	/**
	 * Keep the legacy API loadable until the staged consumer migration completes.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_legacy_shims_remain_available_during_the_staged_migration(): void {
		$this->assertSame( '_ad_placr_code', Ad_Placr_Ad::META_CODE );
		$this->assertSame( '_ad_placr_mobile_code', Ad_Placr_Ad::META_MOBILE_CODE );
		$this->assertSame( '_ad_placr_status', Ad_Placr_Ad::META_STATUS );
		$this->assertSame( 'active', Ad_Placr_Ad::normalize_status( 'ACTIVE' ) );
		$this->assertTrue( is_callable( array( Ad_Placr_Ad::class, 'get_code' ) ) );
		$this->assertTrue( is_callable( array( Ad_Placr_Ad::class, 'get_mobile_code' ) ) );
	}
}
