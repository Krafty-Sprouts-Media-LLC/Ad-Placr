<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class PositionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_defaults_include_core_positions(): void {
		$defaults = Ad_Placr_Positions::defaults();
		$this->assertArrayHasKey( 'sticky_footer', $defaults );
		$this->assertArrayHasKey( 'in_content_after_paragraph', $defaults );
		$this->assertArrayHasKey( 'before_header', $defaults );
	}

	public function test_every_position_has_complete_descriptor(): void {
		foreach ( Ad_Placr_Positions::defaults() as $key => $descriptor ) {
			$this->assertIsString( $key );
			$this->assertArrayHasKey( 'label', $descriptor, "$key missing label" );
			$this->assertArrayHasKey( 'group', $descriptor, "$key missing group" );
			$this->assertArrayHasKey( 'context', $descriptor, "$key missing context" );
			$this->assertNotSame( '', $descriptor['label'], "$key has empty label" );
		}
	}

	public function test_constants_match_registry_keys(): void {
		$defaults = Ad_Placr_Positions::defaults();
		$this->assertArrayHasKey( Ad_Placr_Positions::STICKY_FOOTER, $defaults );
		$this->assertArrayHasKey( Ad_Placr_Positions::BEFORE_HEADER, $defaults );
	}

	public function test_exists_and_label(): void {
		$this->assertTrue( Ad_Placr_Positions::exists( 'sticky_footer' ) );
		$this->assertFalse( Ad_Placr_Positions::exists( 'not_a_position' ) );
		$this->assertNotSame( '', Ad_Placr_Positions::label( 'sticky_footer' ) );
		$this->assertSame( '', Ad_Placr_Positions::label( 'not_a_position' ) );
	}
}
