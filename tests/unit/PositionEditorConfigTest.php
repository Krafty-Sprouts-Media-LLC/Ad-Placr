<?php
/**
 * Editor position-config tests.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../includes/class-ad-placr-positions.php';
require_once __DIR__ . '/../../includes/class-ad-placr-admin.php';

/**
 * @covers Ad_Placr_Admin::position_editor_config
 */
final class PositionEditorConfigTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_every_registered_position_has_a_config_row(): void {
		$config = Ad_Placr_Admin::position_editor_config();
		foreach ( array_keys( Ad_Placr_Positions::all() ) as $key ) {
			self::assertArrayHasKey( $key, $config );
		}
	}

	public function test_in_content_after_paragraph_shape(): void {
		$row = Ad_Placr_Admin::position_editor_config()['in_content_after_paragraph'];
		self::assertSame( 'in_content', $row['group'] );
		self::assertSame( 'singular', $row['context'] );
		self::assertSame( array( 'posttypes', 'taxonomies' ), $row['rules'] );
		self::assertTrue( $row['align'] );
		self::assertSame( 'after', $row['para'] );
	}

	public function test_global_sticky_shape(): void {
		$row = Ad_Placr_Admin::position_editor_config()['sticky_footer'];
		self::assertSame( array( 'pagetypes', 'urlcontains' ), $row['rules'] );
		self::assertFalse( $row['align'] );
		self::assertNull( $row['para'] );
	}
}
