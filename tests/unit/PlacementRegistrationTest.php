<?php
/**
 * Tests for the retained legacy Placement registration contract.
 *
 * @package AdPlacr
 * @since 2.7.0
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Verifies migration-readable Placement records stay hidden and protected.
 *
 * @since 2.7.0
 */
final class PlacementRegistrationTest extends TestCase {

	/**
	 * Prepare WordPress function mocks.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Release WordPress function mocks.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The retained CPT has no editor/menu and every admin capability is gated.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_retained_placement_registration_is_hidden_and_manage_options_only(): void {
		$args = array();

		Functions\expect( 'register_post_type' )
			->once()
			->with(
				Ad_Placr_Placement::POST_TYPE,
				\Mockery::on(
					static function ( array $registered ) use ( &$args ): bool {
						$args = $registered;

						return true;
					}
				)
			);
		Functions\expect( 'register_post_meta' )->times( 4 )->andReturn( true );

		Ad_Placr_Placement::register_post_type();

		$this->assertFalse( $args['show_ui'] );
		$this->assertFalse( $args['show_in_menu'] );
		$this->assertFalse( $args['show_in_admin_bar'] );
		$this->assertFalse( $args['show_in_nav_menus'] );
		$this->assertFalse( $args['map_meta_cap'] );
		$this->assertNotEmpty( $args['capabilities'] );
		foreach ( $args['capabilities'] as $capability ) {
			$this->assertSame( 'manage_options', $capability );
		}
	}
}
