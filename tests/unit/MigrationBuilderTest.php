<?php
/**
 * Tests for pure unified-Ad migration builders.
 *
 * @package AdPlacr
 * @since 2.7.0
 */

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Verifies that retained settings and two-record data become complete Ads.
 *
 * @since 2.7.0
 */
final class MigrationBuilderTest extends TestCase {

	/**
	 * Start WordPress-function mocks for migration option reads.
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
	 * Tear down WordPress-function mocks after each migration test.
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
	 * Footer settings become one complete sticky-footer Ad.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_footer_settings_become_one_complete_ad(): void {
		$ads = Ad_Placr_Migration::build_settings_ads(
			array(
				'footer_sticky' => array(
					'enabled'     => true,
					'code'        => '<ins>desktop</ins>',
					'mobile_code' => '<ins>mobile</ins>',
				),
			)
		);

		$this->assertCount( 1, $ads );
		$this->assertSame(
			array( 'source_key', 'title', 'post_status', 'position', 'targeting', 'versions', 'notes' ),
			array_keys( $ads[0] )
		);
		$this->assertSame( 'sticky_footer', $ads[0]['position'] );
		$this->assertSame( '<ins>desktop</ins>', $ads[0]['versions'][0]['code'] );
		$this->assertSame( '<ins>mobile</ins>', $ads[0]['versions'][0]['mobile_code'] );
		$this->assertSame( array( 'desktop', 'tablet', 'mobile' ), $ads[0]['targeting']['devices'] );
	}

	/**
	 * Disabled or empty public settings do not create Ads.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_disabled_or_empty_public_settings_are_skipped(): void {
		$ads = Ad_Placr_Migration::build_settings_ads(
			array(
				'footer_sticky'    => array(
					'enabled'     => false,
					'code'        => '<ins>disabled</ins>',
					'mobile_code' => '',
				),
				'in_content_slots' => array(
					array(
						'id'          => 'disabled-slot',
						'enabled'     => false,
						'code'        => '<ins>disabled</ins>',
						'mobile_code' => '',
					),
					array(
						'id'          => 'empty-slot',
						'enabled'     => true,
						'code'        => ' ',
						'mobile_code' => '',
					),
				),
			)
		);

		$this->assertSame( array(), $ads );
	}

	/**
	 * In-content settings retain their paragraph and post-type rules.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_in_content_settings_preserve_paragraph_and_post_types(): void {
		$ads = Ad_Placr_Migration::build_settings_ads(
			array(
				'in_content_slots' => array(
					array(
						'id'              => 'slot-a',
						'enabled'         => true,
						'title'           => 'Article Ad',
						'paragraph_index' => 4,
						'position'        => 'before',
						'post_types'      => array( 'post' ),
						'code'            => '<ins>a</ins>',
						'mobile_code'     => '',
					),
				),
			)
		);

		$this->assertSame( 'in_content_before_paragraph', $ads[0]['position'] );
		$this->assertSame( 4, $ads[0]['targeting']['paragraph'] );
		$this->assertSame( array( 'post' ), $ads[0]['targeting']['post_types'] );
	}

	/**
	 * One retained Placement becomes one Ad with ordered weighted versions.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_two_record_data_becomes_one_ad_with_weighted_versions(): void {
		$definition = Ad_Placr_Migration::build_legacy_placement_ad(
			array(
				'id'          => 90,
				'title'       => 'Header choices',
				'post_status' => 'publish',
				'position'    => 'after_header',
				'status'      => 'active',
				'targeting'   => array( 'contexts' => array( 'all' ) ),
				'ads'         => array(
					array( 'ad_id' => 10, 'weight' => 3 ),
					array( 'ad_id' => 11, 'weight' => 1 ),
				),
				'notes'       => 'Retained note',
			),
			array(
				10 => array( 'title' => 'Blue', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
				11 => array( 'title' => 'Red', 'code' => '<ins>red</ins>', 'mobile_code' => '<ins>red-mobile</ins>', 'status' => 'active' ),
			)
		);

		$this->assertSame(
			array( 'source_key', 'title', 'post_status', 'position', 'targeting', 'versions', 'notes' ),
			array_keys( $definition )
		);
		$this->assertSame( 'after_header', $definition['position'] );
		$this->assertSame( array( 3, 1 ), array_column( $definition['versions'], 'weight' ) );
		$this->assertSame( '<ins>red-mobile</ins>', $definition['versions'][1]['mobile_code'] );
		$this->assertSame( 'Retained note', $definition['notes'] );
		$this->assertSame(
			Ad_Placr_Migration::source_version_id( 'ad:10' ),
			$definition['versions'][0]['version_id']
		);
	}

	/**
	 * A paused Placement remains a draft even when it has eligible creative.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_paused_placement_state_produces_draft(): void {
		$definition = Ad_Placr_Migration::build_legacy_placement_ad(
			array(
				'id'          => 90,
				'title'       => 'Paused header',
				'post_status' => 'publish',
				'position'    => 'after_header',
				'status'      => 'inactive',
				'targeting'   => array(),
				'ads'         => array( array( 'ad_id' => 10, 'weight' => 1 ) ),
			),
			array(
				10 => array( 'title' => 'Blue', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
			)
		);

		$this->assertSame( 'draft', $definition['post_status'] );
	}

	/**
	 * Inactive source Ads become disabled versions and cannot activate the Ad.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_inactive_source_ads_produce_disabled_versions(): void {
		$definition = Ad_Placr_Migration::build_legacy_placement_ad(
			array(
				'id'          => 90,
				'title'       => 'Inactive creative',
				'post_status' => 'publish',
				'position'    => 'after_header',
				'status'      => 'active',
				'targeting'   => array(),
				'ads'         => array( array( 'ad_id' => 10, 'weight' => 1 ) ),
			),
			array(
				10 => array( 'title' => 'Blue', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'inactive' ),
			)
		);

		$this->assertFalse( $definition['versions'][0]['enabled'] );
		$this->assertSame( 'draft', $definition['post_status'] );
	}

	/**
	 * Missing linked source Ads are ignored without reordering the retained rows.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_missing_source_ads_are_ignored(): void {
		$definition = Ad_Placr_Migration::build_legacy_placement_ad(
			array(
				'id'          => 90,
				'title'       => 'Missing creative',
				'post_status' => 'publish',
				'position'    => 'after_header',
				'status'      => 'active',
				'targeting'   => array(),
				'ads'         => array(
					array( 'ad_id' => 404, 'weight' => 5 ),
					array( 'ad_id' => 10, 'weight' => 2 ),
				),
			),
			array(
				10 => array( 'title' => 'Blue', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
			)
		);

		$this->assertCount( 1, $definition['versions'] );
		$this->assertSame( 'Blue', $definition['versions'][0]['name'] );
		$this->assertSame( 2, $definition['versions'][0]['weight'] );
	}

	/**
	 * A source key always derives the same UUID-shaped version ID.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_identical_source_keys_return_the_same_version_id(): void {
		$first  = Ad_Placr_Migration::source_version_id( 'ad:10' );
		$second = Ad_Placr_Migration::source_version_id( 'ad:10' );

		$this->assertSame( $first, $second );
		$this->assertMatchesRegularExpression(
			'/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-8[a-f0-9]{3}-[a-f0-9]{12}$/',
			$first
		);
	}

	/**
	 * Source Ad IDs are positive, unique, numeric, and deterministic.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_source_ad_ids_normalize_the_migration_map(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Ad_Placr_Migration::OPTION_MIGRATION_MAP, array() )
			->andReturn(
				array(
					'settings'      => 'invalid',
					'placements'    => array(),
					'source_ad_ids' => array( '11', 0, 10, 11, -4, 'invalid' ),
				)
			);

		$this->assertSame( array( 10, 11 ), Ad_Placr_Migration::source_ad_ids() );
	}
}
