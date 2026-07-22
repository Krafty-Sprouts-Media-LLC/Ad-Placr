<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Orphan-invariant and partition helpers for the position registry.
 *
 * Pure helpers use defaults(); wrappers that call all() stub apply_filters.
 */
final class PositionsRegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_partition_from_covers_all_defaults_without_overlap(): void {
		$defaults  = Ad_Placr_Positions::defaults();
		$partition = Ad_Placr_Positions::partition_from( $defaults );
		$all       = array_keys( $defaults );
		$union     = array_merge(
			$partition['frontend'],
			$partition['special'],
			$partition['manual']
		);

		sort( $all );
		sort( $union );

		$this->assertSame( $all, $union );
		$this->assertSame(
			array(),
			array_intersect( $partition['frontend'], $partition['special'] )
		);
		$this->assertSame(
			array(),
			array_intersect( $partition['frontend'], $partition['manual'] )
		);
		$this->assertSame(
			array(),
			array_intersect( $partition['special'], $partition['manual'] )
		);
	}

	public function test_keys_by_handler_accepts_registry_override(): void {
		$registry = array(
			'a' => array( 'handler' => 'frontend' ),
			'b' => array( 'handler' => 'special' ),
			'c' => array( 'handler' => 'manual' ),
		);

		$this->assertSame( array( 'a' ), Ad_Placr_Positions::keys_by_handler( 'frontend', $registry ) );
		$this->assertSame( array( 'b' ), Ad_Placr_Positions::keys_by_handler( 'special', $registry ) );
		$this->assertSame( array( 'c' ), Ad_Placr_Positions::keys_by_handler( 'manual', $registry ) );
	}

	public function test_renderable_keys_are_frontend_union_special(): void {
		$partition  = Ad_Placr_Positions::partition_from( Ad_Placr_Positions::defaults() );
		$expected   = array_merge( $partition['frontend'], $partition['special'] );
		$renderable = Ad_Placr_Positions::renderable_keys();

		sort( $expected );
		sort( $renderable );

		$this->assertSame( $expected, $renderable );
		$this->assertNotContains( 'sidebar_widget', $renderable );
		$this->assertNotContains( 'manual_shortcode', $renderable );
		$this->assertNotContains( 'manual_block', $renderable );
		$this->assertContains( 'sticky_footer', $renderable );
		$this->assertContains( 'before_header', $renderable );
	}

	public function test_special_keys_include_sticky_and_in_content(): void {
		$special = Ad_Placr_Positions::special_keys();
		$this->assertContains( 'sticky_footer', $special );
		$this->assertContains( 'in_content_before_paragraph', $special );
		$this->assertContains( 'in_content_after_paragraph', $special );
		$this->assertCount( 3, $special );
	}

	public function test_every_frontend_key_has_non_empty_hook(): void {
		$partition = Ad_Placr_Positions::partition_from( Ad_Placr_Positions::defaults() );

		foreach ( $partition['frontend'] as $key ) {
			$d = Ad_Placr_Positions::defaults()[ $key ];
			$this->assertNotSame( '', (string) $d['hook'], "$key missing hook" );
			$this->assertContains( $d['render_mode'], array( 'echo', 'content' ) );
		}
	}
}
