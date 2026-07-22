<?php
/**
 * Pure context predicate helpers for Ad_Placr_Frontend.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

/**
 * Matrix coverage for Ad_Placr_Frontend::context_matches().
 */
final class FrontendContextTest extends TestCase {

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
}
