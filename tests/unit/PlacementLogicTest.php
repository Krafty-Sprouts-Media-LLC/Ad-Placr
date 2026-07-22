<?php

use PHPUnit\Framework\TestCase;

final class PlacementLogicTest extends TestCase {

	public function test_ad_normalize_status_defaults_to_inactive(): void {
		$this->assertSame( 'inactive', Ad_Placr_Ad::normalize_status( '' ) );
		$this->assertSame( 'inactive', Ad_Placr_Ad::normalize_status( null ) );
		$this->assertSame( 'inactive', Ad_Placr_Ad::normalize_status( 'garbage' ) );
	}

	public function test_ad_normalize_status_accepts_active(): void {
		$this->assertSame( 'active', Ad_Placr_Ad::normalize_status( 'active' ) );
		$this->assertSame( 'active', Ad_Placr_Ad::normalize_status( 'ACTIVE' ) );
	}

	public function test_normalize_ads_filters_invalid_rows(): void {
		$raw = array(
			array( 'ad_id' => '12', 'weight' => '3' ),
			array( 'ad_id' => 0 ),          // dropped: no id
			array( 'weight' => 5 ),          // dropped: no id
			'nonsense',                       // dropped: not array
			array( 'ad_id' => 7 ),           // weight defaults to 1
		);

		$out = Ad_Placr_Placement::normalize_ads( $raw );

		$this->assertSame(
			array(
				array( 'ad_id' => 12, 'weight' => 3 ),
				array( 'ad_id' => 7, 'weight' => 1 ),
			),
			$out
		);
	}

	public function test_choose_weighted_single_item(): void {
		$weighted = array( array( 'ad_id' => 5, 'weight' => 1 ) );
		$this->assertSame( 5, Ad_Placr_Placement::choose_weighted( $weighted, 0 ) );
	}

	public function test_choose_weighted_respects_bands(): void {
		$weighted = array(
			array( 'ad_id' => 1, 'weight' => 70 ),
			array( 'ad_id' => 2, 'weight' => 30 ),
		);

		$this->assertSame( 1, Ad_Placr_Placement::choose_weighted( $weighted, 0 ) );
		$this->assertSame( 1, Ad_Placr_Placement::choose_weighted( $weighted, 69 ) );
		$this->assertSame( 2, Ad_Placr_Placement::choose_weighted( $weighted, 70 ) );
		$this->assertSame( 2, Ad_Placr_Placement::choose_weighted( $weighted, 99 ) );
	}

	public function test_choose_weighted_normalizes_out_of_range_roll(): void {
		$weighted = array(
			array( 'ad_id' => 1, 'weight' => 70 ),
			array( 'ad_id' => 2, 'weight' => 30 ),
		);
		// roll 170 % 100 = 70 -> band of ad 2.
		$this->assertSame( 2, Ad_Placr_Placement::choose_weighted( $weighted, 170 ) );
	}

	public function test_choose_weighted_empty_returns_null(): void {
		$this->assertNull( Ad_Placr_Placement::choose_weighted( array(), 0 ) );
		$this->assertNull( Ad_Placr_Placement::choose_weighted( array( array( 'ad_id' => 1, 'weight' => 0 ) ), 0 ) );
	}
}
