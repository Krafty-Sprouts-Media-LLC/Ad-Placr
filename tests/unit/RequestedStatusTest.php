<?php
/**
 * Requested-status resolution tests.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

require_once __DIR__ . '/../../includes/class-ad-placr-admin.php';

/**
 * @covers Ad_Placr_Admin::normalize_requested_status
 */
final class RequestedStatusTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_native_publish_wins(): void {
		self::assertSame( 'publish', Ad_Placr_Admin::normalize_requested_status( 'publish', 'draft' ) );
	}

	public function test_anything_but_publish_is_draft(): void {
		self::assertSame( 'draft', Ad_Placr_Admin::normalize_requested_status( 'pending', 'publish' ) );
		self::assertSame( 'draft', Ad_Placr_Admin::normalize_requested_status( null, null ) );
	}

	public function test_legacy_fallback_still_works(): void {
		self::assertSame( 'publish', Ad_Placr_Admin::normalize_requested_status( null, 'publish' ) );
		self::assertSame( 'draft', Ad_Placr_Admin::normalize_requested_status( '', 'paused' ) );
	}
}
