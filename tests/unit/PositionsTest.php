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
			$this->assertArrayHasKey( 'hook', $descriptor, "$key missing hook" );
			$this->assertArrayHasKey( 'priority', $descriptor, "$key missing priority" );
			$this->assertArrayHasKey( 'render_mode', $descriptor, "$key missing render_mode" );
			$this->assertArrayHasKey( 'handler', $descriptor, "$key missing handler" );
			$this->assertNotSame( '', $descriptor['label'], "$key has empty label" );
			$this->assertIsInt( $descriptor['priority'], "$key priority must be int" );
			$this->assertContains(
				$descriptor['render_mode'],
				array( 'echo', 'content', 'none' ),
				"$key has invalid render_mode"
			);
			$this->assertContains(
				$descriptor['handler'],
				array( 'frontend', 'special', 'manual' ),
				"$key has invalid handler"
			);
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

	public function test_handlers_partition_all_keys(): void {
		$all   = array_keys( Ad_Placr_Positions::defaults() );
		$parts = array_merge(
			Ad_Placr_Positions::frontend_keys(),
			Ad_Placr_Positions::special_keys(),
			Ad_Placr_Positions::manual_keys()
		);
		sort( $all );
		sort( $parts );
		$this->assertSame( $all, $parts );
	}

	public function test_frontend_keys_have_hooks(): void {
		foreach ( Ad_Placr_Positions::frontend_keys() as $key ) {
			$d = Ad_Placr_Positions::defaults()[ $key ];
			$this->assertNotSame( '', (string) $d['hook'], "$key frontend hook empty" );
			$this->assertContains( $d['render_mode'], array( 'echo', 'content' ) );
		}
	}

	public function test_hook_metadata_matches_spec(): void {
		$defaults = Ad_Placr_Positions::defaults();

		$this->assertSame( null, $defaults['in_content_before_paragraph']['hook'] );
		$this->assertSame( 0, $defaults['in_content_before_paragraph']['priority'] );
		$this->assertSame( 'none', $defaults['in_content_before_paragraph']['render_mode'] );
		$this->assertSame( 'special', $defaults['in_content_before_paragraph']['handler'] );

		$this->assertSame( null, $defaults['in_content_after_paragraph']['hook'] );
		$this->assertSame( 'special', $defaults['in_content_after_paragraph']['handler'] );

		$this->assertSame( 'wp_footer', $defaults['sticky_footer']['hook'] );
		$this->assertSame( 100, $defaults['sticky_footer']['priority'] );
		$this->assertSame( 'echo', $defaults['sticky_footer']['render_mode'] );
		$this->assertSame( 'special', $defaults['sticky_footer']['handler'] );

		$this->assertSame( 'the_content', $defaults['before_post_content']['hook'] );
		$this->assertSame( 11, $defaults['before_post_content']['priority'] );
		$this->assertSame( 'content', $defaults['before_post_content']['render_mode'] );
		$this->assertSame( 'frontend', $defaults['before_post_content']['handler'] );

		$this->assertSame( 'the_content', $defaults['after_post_content']['hook'] );
		$this->assertSame( 13, $defaults['after_post_content']['priority'] );
		$this->assertSame( 'content', $defaults['after_post_content']['render_mode'] );

		$this->assertSame( 'wp_body_open', $defaults['before_header']['hook'] );
		$this->assertSame( 5, $defaults['before_header']['priority'] );
		$this->assertSame( 'echo', $defaults['before_header']['render_mode'] );

		$this->assertSame( 'wp_body_open', $defaults['after_header']['hook'] );
		$this->assertSame( 20, $defaults['after_header']['priority'] );

		$this->assertSame( 'get_footer', $defaults['before_footer']['hook'] );
		$this->assertSame( 5, $defaults['before_footer']['priority'] );

		$this->assertSame( 'wp_footer', $defaults['after_footer']['hook'] );
		$this->assertSame( 20, $defaults['after_footer']['priority'] );

		$this->assertSame( 'wp_footer', $defaults['sticky_left_rail']['hook'] );
		$this->assertSame( 99, $defaults['sticky_left_rail']['priority'] );
		$this->assertSame( 'frontend', $defaults['sticky_left_rail']['handler'] );

		$this->assertSame( 'wp_footer', $defaults['sticky_right_rail']['hook'] );
		$this->assertSame( 99, $defaults['sticky_right_rail']['priority'] );

		foreach ( array( 'front_page_top', 'blog_index_top', 'archive_top' ) as $key ) {
			$this->assertSame( 'loop_start', $defaults[ $key ]['hook'], "$key hook" );
			$this->assertSame( 5, $defaults[ $key ]['priority'], "$key priority" );
			$this->assertSame( 'echo', $defaults[ $key ]['render_mode'], "$key render_mode" );
			$this->assertSame( 'frontend', $defaults[ $key ]['handler'], "$key handler" );
		}

		foreach ( array( 'front_page_bottom', 'blog_index_bottom', 'archive_bottom' ) as $key ) {
			$this->assertSame( 'wp_footer', $defaults[ $key ]['hook'], "$key hook" );
			$this->assertSame( 15, $defaults[ $key ]['priority'], "$key priority" );
			$this->assertSame( 'frontend', $defaults[ $key ]['handler'], "$key handler" );
		}

		foreach ( array( 'sidebar_widget', 'manual_shortcode', 'manual_block' ) as $key ) {
			$this->assertSame( null, $defaults[ $key ]['hook'], "$key hook" );
			$this->assertSame( 0, $defaults[ $key ]['priority'], "$key priority" );
			$this->assertSame( 'none', $defaults[ $key ]['render_mode'], "$key render_mode" );
			$this->assertSame( 'manual', $defaults[ $key ]['handler'], "$key handler" );
		}
	}
}
