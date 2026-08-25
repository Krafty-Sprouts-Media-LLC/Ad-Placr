<?php
/**
 * Position alignment-support tests.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../includes/class-ad-placr-positions.php';

/**
 * @covers Ad_Placr_Positions::supports_alignment
 */
final class PositionsAlignmentTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_content_positions_support_alignment(): void {
		self::assertTrue( Ad_Placr_Positions::supports_alignment( 'in_content_after_paragraph' ) );
		self::assertTrue( Ad_Placr_Positions::supports_alignment( 'before_post_content' ) );
	}

	public function test_non_content_positions_do_not(): void {
		self::assertFalse( Ad_Placr_Positions::supports_alignment( 'sticky_footer' ) );
		self::assertFalse( Ad_Placr_Positions::supports_alignment( 'before_header' ) );
		self::assertFalse( Ad_Placr_Positions::supports_alignment( 'front_page_top' ) );
		self::assertFalse( Ad_Placr_Positions::supports_alignment( 'manual_shortcode' ) );
	}

	public function test_unknown_key_is_false(): void {
		self::assertFalse( Ad_Placr_Positions::supports_alignment( 'not_a_position' ) );
		self::assertFalse( Ad_Placr_Positions::supports_alignment( '' ) );
	}
}
