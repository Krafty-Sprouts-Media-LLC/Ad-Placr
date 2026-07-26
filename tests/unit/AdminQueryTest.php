<?php
/**
 * Tests for the unified Ads list query.
 *
 * @package AdPlacr
 * @since 2.7.0
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Verifies retained migration sources stay out of the ordinary Ads list only.
 *
 * @since 2.7.0
 */
final class AdminQueryTest extends TestCase {

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
	 * The main Ads list excludes retained source Ads without losing prior exclusions.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_main_ads_list_excludes_retained_migration_sources(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_option' )->alias(
			static function ( string $name, $fallback = false ) {
				if ( Ad_Placr_Migration::OPTION_MIGRATION_MAP === $name ) {
					return array(
						'settings'      => array(),
						'placements'    => array(),
						'source_ad_ids' => array( 12, 8, 12 ),
					);
				}

				return $fallback;
			}
		);

		$query = new WP_Query(
			array(
				'post_type'    => Ad_Placr_Ad::POST_TYPE,
				'post__not_in' => array( 4, 8 ),
			)
		);

		Ad_Placr_Admin::exclude_migration_source_ads( $query );

		$this->assertSame( array( 4, 8, 12 ), $query->get( 'post__not_in' ) );
	}

	/**
	 * Secondary queries are not changed by the admin-list exclusion.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_secondary_ad_query_is_not_changed(): void {
		Functions\when( 'is_admin' )->justReturn( true );

		$query = new WP_Query(
			array(
				'post_type'    => Ad_Placr_Ad::POST_TYPE,
				'post__not_in' => array( 4 ),
			),
			false
		);

		Ad_Placr_Admin::exclude_migration_source_ads( $query );

		$this->assertSame( array( 4 ), $query->get( 'post__not_in' ) );
	}
}
