<?php
/**
 * Pure targeting helpers for placements.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class PlacementTargetingTest extends TestCase {

	public function test_contexts_all_matches_any_post_type(): void {
		$t = array(
			'post_types' => array(),
			'contexts'   => array( 'all' ),
		);
		$this->assertTrue( Ad_Placr_Placement::targeting_matches_singular( $t, 'post' ) );
		$this->assertTrue( Ad_Placr_Placement::targeting_matches_singular( $t, 'page' ) );
	}

	public function test_singular_requires_post_type_allow_list(): void {
		$t = array(
			'post_types' => array( 'post' ),
			'contexts'   => array( 'singular' ),
		);
		$this->assertTrue( Ad_Placr_Placement::targeting_matches_singular( $t, 'post' ) );
		$this->assertFalse( Ad_Placr_Placement::targeting_matches_singular( $t, 'page' ) );
	}

	public function test_singular_empty_post_types_matches_nothing(): void {
		$t = array(
			'post_types' => array(),
			'contexts'   => array( 'singular' ),
		);
		$this->assertFalse( Ad_Placr_Placement::targeting_matches_singular( $t, 'post' ) );
	}

	public function test_placement_normalize_status(): void {
		$this->assertSame( 'inactive', Ad_Placr_Placement::normalize_status( '' ) );
		$this->assertSame( 'active', Ad_Placr_Placement::normalize_status( 'ACTIVE' ) );
	}
}
