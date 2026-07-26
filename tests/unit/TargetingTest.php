<?php
/**
 * Unified Ad targeting matrix.
 *
 * @package AdPlacr
 * @since 2.4.0
 */

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Covers pure targeting rules and unified Ad gating.
 *
 * @since 2.4.0
 */
final class TargetingTest extends TestCase {

	/**
	 * Start Brain Monkey for each WordPress-function test.
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
	 * Tear down Brain Monkey expectations after each test.
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
	 * @return array<string, mixed>
	 */
	private function base_ctx( array $overrides = array() ): array {
		return array_merge(
			array(
				'view'          => 'singular',
				'post_type'     => 'post',
				'is_singular'   => true,
				'user_state'    => 'guest',
				'url_path'      => '/hello-world/',
				'category_ids'  => array( 3, 7 ),
				'tag_ids'       => array( 11 ),
				'now'           => 1_700_000_000,
			),
			$overrides
		);
	}

	public function test_empty_targeting_fail_open(): void {
		$this->assertTrue( Ad_Placr_Targeting::matches( array(), $this->base_ctx() ) );
	}

	public function test_contexts_all_passes_any_view(): void {
		$t = array( 'contexts' => array( 'all' ) );
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'view' => 'archive' ) ) ) );
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'view' => 'search' ) ) ) );
	}

	public function test_contexts_list_requires_view(): void {
		$t = array(
			'contexts'   => array( 'singular', 'archive' ),
			'post_types' => array( 'post' ),
		);
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'view' => 'singular' ) ) ) );
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'view' => 'archive', 'is_singular' => false, 'post_type' => '' ) ) ) );
		$this->assertFalse( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'view' => 'search', 'is_singular' => false, 'post_type' => '' ) ) ) );
	}

	public function test_singular_empty_post_types_hides(): void {
		$t = array(
			'contexts'   => array( 'singular' ),
			'post_types' => array(),
		);
		$this->assertFalse( Ad_Placr_Targeting::matches( $t, $this->base_ctx() ) );
	}

	public function test_singular_post_types_allow_list(): void {
		$t = array(
			'contexts'   => array( 'singular' ),
			'post_types' => array( 'post' ),
		);
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx() ) );
		$this->assertFalse( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'post_type' => 'page' ) ) ) );
	}

	public function test_all_with_nonempty_post_types_filters_singular(): void {
		$t = array(
			'contexts'   => array( 'all' ),
			'post_types' => array( 'post' ),
		);
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx() ) );
		$this->assertFalse( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'post_type' => 'page' ) ) ) );
		// Non-singular + all: post_types do not apply.
		$this->assertTrue(
			Ad_Placr_Targeting::matches(
				$t,
				$this->base_ctx(
					array(
						'view'        => 'archive',
						'is_singular' => false,
						'post_type'   => '',
					)
				)
			)
		);
	}

	public function test_user_logged_in_gate(): void {
		$t = array( 'user' => 'logged_in' );
		$this->assertFalse( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'user_state' => 'guest' ) ) ) );
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'user_state' => 'logged_in' ) ) ) );
	}

	public function test_user_any_and_missing(): void {
		$this->assertTrue( Ad_Placr_Targeting::matches( array(), $this->base_ctx() ) );
		$this->assertTrue( Ad_Placr_Targeting::matches( array( 'user' => 'any' ), $this->base_ctx() ) );
	}

	public function test_schedule_window(): void {
		$now = 1_700_000_000;
		$t   = array(
			'schedule' => array(
				'start' => '2023-01-01 00:00:00',
				'end'   => '2024-01-01 00:00:00',
			),
		);
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'now' => $now ) ) ) );
		$this->assertFalse( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'now' => 1_600_000_000 ) ) ) );
		$this->assertFalse( Ad_Placr_Targeting::matches( $t, $this->base_ctx( array( 'now' => 1_800_000_000 ) ) ) );
	}

	public function test_url_contains_or(): void {
		$t = array( 'url_contains' => array( '/nope', '/hello' ) );
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx() ) );
		$this->assertFalse(
			Ad_Placr_Targeting::matches(
				$t,
				$this->base_ctx( array( 'url_path' => '/other/' ) )
			)
		);
	}

	public function test_include_categories_or_on_singular(): void {
		$t = array( 'include_categories' => array( 7, 99 ) );
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx() ) );
		$this->assertFalse(
			Ad_Placr_Targeting::matches(
				$t,
				$this->base_ctx( array( 'category_ids' => array( 1 ) ) )
			)
		);
		$this->assertFalse(
			Ad_Placr_Targeting::matches(
				$t,
				$this->base_ctx(
					array(
						'view'          => 'archive',
						'is_singular'   => false,
						'category_ids'  => array(),
					)
				)
			)
		);
	}

	public function test_include_tags_or_on_singular(): void {
		$t = array( 'include_tags' => array( 11 ) );
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx() ) );
		$this->assertFalse(
			Ad_Placr_Targeting::matches(
				$t,
				$this->base_ctx( array( 'tag_ids' => array( 2 ) ) )
			)
		);
	}

	public function test_and_across_families(): void {
		$t = array(
			'contexts'   => array( 'singular' ),
			'post_types' => array( 'post' ),
			'user'       => 'guest',
			'url_contains' => array( '/hello' ),
		);
		$this->assertTrue( Ad_Placr_Targeting::matches( $t, $this->base_ctx() ) );
		$this->assertFalse(
			Ad_Placr_Targeting::matches(
				$t,
				$this->base_ctx( array( 'user_state' => 'logged_in' ) )
			)
		);
	}

	public function test_normalize_context_defaults(): void {
		$ctx = Ad_Placr_Targeting::normalize_context( array( 'view' => 'singular' ) );
		$this->assertSame( 'singular', $ctx['view'] );
		$this->assertSame( 'guest', $ctx['user_state'] );
		$this->assertSame( '/', $ctx['url_path'] );
		$this->assertIsArray( $ctx['category_ids'] );
	}

	/**
	 * Unpublished unified Ads fail the shared targeting gate.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_should_display_rejects_an_unpublished_ad(): void {
		Functions\expect( 'get_post_type' )
			->once()
			->with( 91 )
			->andReturn( Ad_Placr_Ad::POST_TYPE );
		Functions\expect( 'get_post_status' )
			->once()
			->with( 91 )
			->andReturn( 'draft' );
		Functions\expect( 'get_post_meta' )->never();

		$this->assertFalse( Ad_Placr_Targeting::should_display( 91, $this->base_ctx() ) );
	}

	/**
	 * Published Ads use unified targeting metadata and the preserved filter.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_should_display_reads_unified_ad_targeting_and_filters_the_result(): void {
		$targeting = array(
			'contexts'   => array( 'singular' ),
			'post_types' => array( 'post' ),
		);
		$ctx       = $this->base_ctx();

		Functions\expect( 'get_post_type' )
			->once()
			->with( 42 )
			->andReturn( Ad_Placr_Ad::POST_TYPE );
		Functions\expect( 'get_post_status' )
			->once()
			->with( 42 )
			->andReturn( 'publish' );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, Ad_Placr_Ad::META_TARGETING, true )
			->andReturn( $targeting );
		Functions\expect( 'apply_filters' )
			->once()
			->with(
				'ad_placr_targeting_should_display',
				true,
				42,
				Ad_Placr_Targeting::normalize_context( $ctx ),
				$targeting
			)
			->andReturn( true );

		$this->assertTrue( Ad_Placr_Targeting::should_display( 42, $ctx ) );
	}

}
