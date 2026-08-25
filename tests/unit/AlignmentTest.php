<?php
/**
 * Alignment normalization tests.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class-ad-placr-ad.php';

/**
 * @covers Ad_Placr_Ad::normalize_alignment
 */
final class AlignmentTest extends TestCase {

	public function test_valid_alignments_pass_through(): void {
		self::assertSame( 'left', Ad_Placr_Ad::normalize_alignment( 'left' ) );
		self::assertSame( 'center', Ad_Placr_Ad::normalize_alignment( 'center' ) );
		self::assertSame( 'right', Ad_Placr_Ad::normalize_alignment( 'right' ) );
	}

	public function test_none_and_empty_and_garbage_become_none(): void {
		self::assertSame( 'none', Ad_Placr_Ad::normalize_alignment( 'none' ) );
		self::assertSame( 'none', Ad_Placr_Ad::normalize_alignment( '' ) );
		self::assertSame( 'none', Ad_Placr_Ad::normalize_alignment( null ) );
		self::assertSame( 'none', Ad_Placr_Ad::normalize_alignment( 'middle' ) );
		self::assertSame( 'none', Ad_Placr_Ad::normalize_alignment( array( 'left' ) ) );
	}

	public function test_input_is_trimmed_and_lowercased(): void {
		self::assertSame( 'center', Ad_Placr_Ad::normalize_alignment( ' CENTER ' ) );
	}
}
