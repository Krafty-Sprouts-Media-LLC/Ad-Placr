<?php
/**
 * Front-end dispatcher context, aggregation, and compatibility tests.
 *
 * @package AdPlacr
 * @since 2.2.0
 */

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Covers pure front-end dispatch seams and preserved filter behavior.
 *
 * @since 2.2.0
 */
final class FrontendContextTest extends TestCase {

	/**
	 * Start Brain Monkey for automatic-path compatibility tests.
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
	 * Data provider for the context_matches matrix.
	 *
	 * @return array<string,array{0:string,1:array<string,bool>,2:bool}>
	 */
	public function context_matrix_provider(): array {
		$none = array(
			'singular'   => false,
			'front_page' => false,
			'blog_index' => false,
			'archive'    => false,
			'main_query' => false,
		);

		return array(
			'global always true'                   => array(
				'global',
				$none,
				true,
			),
			'singular needs singular and main'     => array(
				'singular',
				array_merge(
					$none,
					array(
						'singular'   => true,
						'main_query' => true,
					)
				),
				true,
			),
			'singular without main_query is false' => array(
				'singular',
				array_merge(
					$none,
					array(
						'singular'   => true,
						'main_query' => false,
					)
				),
				false,
			),
			'singular without singular is false'   => array(
				'singular',
				array_merge(
					$none,
					array(
						'singular'   => false,
						'main_query' => true,
					)
				),
				false,
			),
			'front_page flag'                      => array(
				'front_page',
				array_merge(
					$none,
					array(
						'front_page' => true,
					)
				),
				true,
			),
			'front_page false when flag off'       => array(
				'front_page',
				$none,
				false,
			),
			'blog_index flag'                      => array(
				'blog_index',
				array_merge(
					$none,
					array(
						'blog_index' => true,
					)
				),
				true,
			),
			'blog_index false when flag off'       => array(
				'blog_index',
				$none,
				false,
			),
			'archive flag'                         => array(
				'archive',
				array_merge(
					$none,
					array(
						'archive' => true,
					)
				),
				true,
			),
			'archive false when flag off'          => array(
				'archive',
				$none,
				false,
			),
			'widget never matches'                 => array(
				'widget',
				array_merge(
					$none,
					array(
						'singular'   => true,
						'main_query' => true,
					)
				),
				false,
			),
			'manual never matches'                 => array(
				'manual',
				array_merge(
					$none,
					array(
						'front_page' => true,
					)
				),
				false,
			),
			'unknown context is false'             => array(
				'not_a_real_context',
				array_merge(
					$none,
					array(
						'singular'   => true,
						'main_query' => true,
					)
				),
				false,
			),
		);
	}

	/**
	 * Asserts context_matches for each matrix row.
	 *
	 * @dataProvider context_matrix_provider
	 *
	 * @param string             $context  Registry context key.
	 * @param array<string,bool> $flags    Injected request flags.
	 * @param bool               $expected Expected match result.
	 * @return void
	 */
	public function test_context_matches_matrix( string $context, array $flags, bool $expected ): void {
		$this->assertSame(
			$expected,
			Ad_Placr_Frontend::context_matches( $context, $flags )
		);
	}

	/**
	 * Aggregates every rendered Ad without disturbing query order.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_join_rendered_ads_keeps_query_order_and_drops_empty_output(): void {
		$this->assertSame(
			'<div>first</div><div>second</div>',
			Ad_Placr_Frontend::join_rendered_ads(
				array( 9, 3, 12 ),
				static function ( int $ad_id ): string {
					return array(
						9  => '<div>first</div>',
						3  => '',
						12 => '<div>second</div>',
					)[ $ad_id ];
				}
			)
		);
	}

	/**
	 * Sticky siblings share one positioned owner instead of overlapping.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_wrap_sticky_ads_positions_the_stack_once(): void {
		$children = '<div>first</div><div>second</div>';
		$rail     = Ad_Placr_Frontend::wrap_sticky_ads( Ad_Placr_Positions::STICKY_LEFT_RAIL, $children );
		$footer   = Ad_Placr_Frontend::wrap_sticky_ads( Ad_Placr_Positions::STICKY_FOOTER, $children );

		$this->assertSame( 1, substr_count( $rail, 'ad-placr--rail-left' ) );
		$this->assertStringContainsString( $children, $rail );
		$this->assertSame( 1, substr_count( $footer, 'ad-placr--footer-sticky' ) );
		$this->assertStringContainsString( $children, $footer );
	}

	/**
	 * Specialized breakpoint filters control unified renderer resolution.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_with_mobile_breakpoint_temporarily_bridges_the_unified_filter(): void {
		$installed = null;

		Functions\expect( 'get_option' )
			->once()
			->with( 'ad_placr_settings', array() )
			->andReturn( array() );
		Functions\expect( 'add_filter' )
			->once()
			->with(
				'ad_placr_mobile_breakpoint',
				\Mockery::on(
					static function ( $callback ) use ( &$installed ): bool {
						$installed = $callback;
						return is_callable( $callback );
					}
				),
				PHP_INT_MAX
			)
			->andReturn( true );
		Functions\expect( 'remove_filter' )
			->once()
			->with(
				'ad_placr_mobile_breakpoint',
				\Mockery::on(
					static function ( $callback ) use ( &$installed ): bool {
						return $callback === $installed;
					}
				),
				PHP_INT_MAX
			)
			->andReturn( true );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'ad_placr_mobile_breakpoint', 782 )
			->andReturnUsing(
				static function ( string $hook, int $default ) use ( &$installed ): int {
					unset( $hook );

					return is_callable( $installed ) ? (int) $installed( $default ) : $default;
				}
			);

		$result = Ad_Placr_Frontend::with_mobile_breakpoint(
			1200,
			static function (): string {
				return Ad_Placr_Renderer::build_responsive_css(
					'#ad-placr-bridge',
					Ad_Placr_Renderer::resolve_mobile_breakpoint(),
					1600,
					array( 'desktop', 'tablet', 'mobile' ),
					true
				);
			}
		);

		$this->assertStringContainsString( '@media (max-width:1200px)', $result );
		$this->assertStringContainsString( '@media (min-width:1201px)', $result );
	}
}
