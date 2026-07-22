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
}
