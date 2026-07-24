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

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal mutable post record for migration integration tests.
	 *
	 * @since 2.7.0
	 */
	class WP_Post {

		/**
		 * Post ID.
		 *
		 * @since 2.7.0
		 *
		 * @var int
		 */
		public int $ID;

		/**
		 * Post type.
		 *
		 * @since 2.7.0
		 *
		 * @var string
		 */
		public string $post_type;

		/**
		 * Post status.
		 *
		 * @since 2.7.0
		 *
		 * @var string
		 */
		public string $post_status;

		/**
		 * Post title.
		 *
		 * @since 2.7.0
		 *
		 * @var string
		 */
		public string $post_title;

		/**
		 * Post slug.
		 *
		 * @since 2.7.0
		 *
		 * @var string
		 */
		public string $post_name;

		/**
		 * Populate the post fields used by the migration.
		 *
		 * @since 2.7.0
		 *
		 * @param array<string, mixed> $values Post values.
		 */
		public function __construct( array $values ) {
			$this->ID          = (int) ( $values['ID'] ?? 0 );
			$this->post_type   = (string) ( $values['post_type'] ?? 'post' );
			$this->post_status = (string) ( $values['post_status'] ?? 'draft' );
			$this->post_title  = (string) ( $values['post_title'] ?? '' );
			$this->post_name   = (string) ( $values['post_name'] ?? '' );
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WordPress error value for migration integration tests.
	 *
	 * @since 2.7.0
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @since 2.7.0
		 *
		 * @var string
		 */
		public string $code;

		/**
		 * Error message.
		 *
		 * @since 2.7.0
		 *
		 * @var string
		 */
		public string $message;

		/**
		 * Store one error.
		 *
		 * @since 2.7.0
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	/**
	 * Minimal options-table adapter for atomic stale-lock tests.
	 *
	 * @since 2.7.0
	 */
	class wpdb {

		/**
		 * Options table name.
		 *
		 * @since 2.7.0
		 *
		 * @var string
		 */
		public string $options = 'wp_options';

		/**
		 * Test callback that implements a conditional row delete.
		 *
		 * @since 2.7.0
		 *
		 * @var callable
		 */
		public $delete_callback;

		/**
		 * Test callback that implements a unique option-row insert.
		 *
		 * @since 2.7.0
		 *
		 * @var callable
		 */
		public $insert_callback;

		/**
		 * Delete one row through the configured test callback.
		 *
		 * @since 2.7.0
		 *
		 * @param string               $table        Table name.
		 * @param array<string, mixed> $where        Equality conditions.
		 * @param string[]             $where_format Condition formats.
		 * @return int|false
		 */
		public function delete( string $table, array $where, array $where_format ) {
			return ( $this->delete_callback )( $table, $where, $where_format );
		}

		/**
		 * Insert one row through the configured test callback.
		 *
		 * @since 2.7.0
		 *
		 * @param string               $table  Table name.
		 * @param array<string, mixed> $data   Column values.
		 * @param string[]             $format Column formats.
		 * @return int|false
		 */
		public function insert( string $table, array $data, array $format ) {
			return ( $this->insert_callback )( $table, $data, $format );
		}
	}
}

/**
 * Verifies that retained settings and two-record data become complete Ads.
 *
 * @since 2.7.0
 */
final class MigrationBuilderTest extends TestCase {

	/**
	 * In-memory WordPress state used by runtime migration tests.
	 *
	 * @since 2.7.0
	 *
	 * @var array<string, mixed>
	 */
	private array $wp;

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

		if ( ! class_exists( 'Ad_Placr_Plugin' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-ad-placr-plugin.php';
		}

		$this->wp = array(
			'options'                   => array(),
			'option_autoload'           => array(),
			'posts'                     => array(),
			'meta'                      => array(),
			'next_post_id'              => 100,
			'insert_calls'              => 0,
			'add_option_calls'          => array(),
			'atomic_lock_inserts'       => array(),
			'delete_option_calls'       => array(),
			'conditional_lock_deletes'  => 0,
			'replace_lock_before_delete' => false,
			'cache_deletes'             => array(),
			'deleted_posts'             => array(),
			'filtered_positions'        => null,
			'fail_update_option_once'   => array(),
			'fail_update_post_meta_once' => array(),
			'fail_delete_post_once'     => false,
		);

		$wpdb                  = new wpdb();
		$wpdb->insert_callback = function ( string $table, array $data, array $format ) {
			unset( $format );
			if ( 'wp_options' !== $table || Ad_Placr_Migration::OPTION_MIGRATION_LOCK !== ( $data['option_name'] ?? '' ) ) {
				return false;
			}

			$this->wp['atomic_lock_inserts'][] = $data;
			$name = (string) $data['option_name'];
			if ( array_key_exists( $name, $this->wp['options'] ) ) {
				return false;
			}

			$serialized = (string) ( $data['option_value'] ?? '' );
			$value      = @unserialize( $serialized );
			$this->wp['options'][ $name ]         = false === $value ? $serialized : $value;
			$this->wp['option_autoload'][ $name ] = $data['autoload'] ?? null;

			return 1;
		};
		$wpdb->delete_callback = function ( string $table, array $where, array $where_format ) {
			unset( $where_format );
			if ( 'wp_options' !== $table || Ad_Placr_Migration::OPTION_MIGRATION_LOCK !== ( $where['option_name'] ?? '' ) ) {
				return false;
			}

			if ( $this->wp['replace_lock_before_delete'] ) {
				$this->wp['replace_lock_before_delete'] = false;
				$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ] = array(
					'token'      => 'replacement-request',
					'created_at' => time(),
				);
			}

			++$this->wp['conditional_lock_deletes'];
			$current = $this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ] ?? null;
			if ( serialize( $current ) !== ( $where['option_value'] ?? '' ) ) {
				return 0;
			}

			unset(
				$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ],
				$this->wp['option_autoload'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ]
			);
			return 1;
		};
		$GLOBALS['wpdb']       = $wpdb;

		$this->register_wordpress_runtime();
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
	 * Register stateful WordPress-function replacements for runtime tests.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	private function register_wordpress_runtime(): void {
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, $value ) {
				if ( 'ad_placr_positions' === $hook && is_array( $this->wp['filtered_positions'] ) ) {
					return $this->wp['filtered_positions'];
				}

				return $value;
			}
		);

		Functions\when( 'get_option' )->alias(
			function ( string $name, $default = false ) {
				return array_key_exists( $name, $this->wp['options'] )
					? $this->wp['options'][ $name ]
					: $default;
			}
		);

		Functions\when( 'add_option' )->alias(
			function ( string $name, $value = '', string $deprecated = '', $autoload = null ): bool {
				$this->wp['add_option_calls'][] = array( $name, $value, $deprecated, $autoload );
				if ( array_key_exists( $name, $this->wp['options'] ) ) {
					return false;
				}

				$this->wp['options'][ $name ]         = $value;
				$this->wp['option_autoload'][ $name ] = $autoload;

				return true;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( string $name, $value, $autoload = null ): bool {
				if ( ! empty( $this->wp['fail_update_option_once'][ $name ] ) ) {
					--$this->wp['fail_update_option_once'][ $name ];
					return false;
				}

				$unchanged = array_key_exists( $name, $this->wp['options'] )
					&& $this->wp['options'][ $name ] === $value;
				$this->wp['options'][ $name ] = $value;
				if ( null !== $autoload ) {
					$this->wp['option_autoload'][ $name ] = $autoload;
				}

				return ! $unchanged;
			}
		);

		Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				$this->wp['delete_option_calls'][] = $name;
				if ( ! array_key_exists( $name, $this->wp['options'] ) ) {
					return false;
				}

				if ( Ad_Placr_Migration::OPTION_MIGRATION_LOCK === $name && $this->wp['replace_lock_before_delete'] ) {
					$this->wp['replace_lock_before_delete'] = false;
					$this->wp['options'][ $name ] = array(
						'token'      => 'replacement-request',
						'created_at' => time(),
					);
				}

				unset( $this->wp['options'][ $name ], $this->wp['option_autoload'][ $name ] );
				return true;
			}
		);

		Functions\when( 'get_posts' )->alias(
			function ( array $args ): array {
				$statuses = isset( $args['post_status'] ) && is_array( $args['post_status'] )
					? $args['post_status']
					: array( (string) ( $args['post_status'] ?? 'publish' ) );
				$ids      = array();

				foreach ( $this->wp['posts'] as $post ) {
					if ( ! $post instanceof WP_Post ) {
						continue;
					}
					if ( ( $args['post_type'] ?? '' ) !== $post->post_type ) {
						continue;
					}
					if ( ! in_array( $post->post_status, $statuses, true ) ) {
						continue;
					}
					$ids[] = $post->ID;
				}

				sort( $ids, SORT_NUMERIC );
				return $ids;
			}
		);

		Functions\when( 'get_post' )->alias(
			function ( int $post_id ) {
				return $this->wp['posts'][ $post_id ] ?? null;
			}
		);

		Functions\when( 'get_post_meta' )->alias(
			function ( int $post_id, string $meta_key, bool $single = false ) {
				unset( $single );
				return $this->wp['meta'][ $post_id ][ $meta_key ] ?? '';
			}
		);

		Functions\when( 'wp_insert_post' )->alias(
			function ( array $values, bool $wp_error = false ) {
				unset( $wp_error );
				$post_id = $this->wp['next_post_id']++;
				$post    = new WP_Post(
					array(
						'ID'          => $post_id,
						'post_type'   => $values['post_type'] ?? 'post',
						'post_status' => $values['post_status'] ?? 'draft',
						'post_title'  => $this->unslash_value( $values['post_title'] ?? '' ),
						'post_name'   => $values['post_name'] ?? '',
					)
				);

				$this->wp['posts'][ $post_id ] = $post;
				++$this->wp['insert_calls'];

				return $post_id;
			}
		);

		Functions\when( 'wp_update_post' )->alias(
			function ( array $values, bool $wp_error = false ) {
				$post_id = (int) ( $values['ID'] ?? 0 );
				if ( ! isset( $this->wp['posts'][ $post_id ] ) ) {
					return $wp_error ? new WP_Error( 'missing_post', 'Missing post.' ) : 0;
				}

				$post = $this->wp['posts'][ $post_id ];
				if ( isset( $values['post_status'] ) ) {
					$post->post_status = (string) $values['post_status'];
				}
				if ( isset( $values['post_title'] ) ) {
					$post->post_title = (string) $this->unslash_value( $values['post_title'] );
				}

				return $post_id;
			}
		);

		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $meta_key, $meta_value ) {
				if ( ! empty( $this->wp['fail_update_post_meta_once'][ $meta_key ] ) ) {
					--$this->wp['fail_update_post_meta_once'][ $meta_key ];
					return false;
				}

				$stored = $this->unslash_value( $meta_value );
				if ( array_key_exists( $meta_key, $this->wp['meta'][ $post_id ] ?? array() )
					&& $this->wp['meta'][ $post_id ][ $meta_key ] === $stored ) {
					return false;
				}

				$this->wp['meta'][ $post_id ][ $meta_key ] = $stored;
				return 1;
			}
		);

		Functions\when( 'wp_delete_post' )->alias(
			function ( int $post_id, bool $force_delete = false ) {
				unset( $force_delete );
				if ( $this->wp['fail_delete_post_once'] ) {
					$this->wp['fail_delete_post_once'] = false;
					return false;
				}

				if ( ! isset( $this->wp['posts'][ $post_id ] ) ) {
					return false;
				}

				$post = $this->wp['posts'][ $post_id ];
				unset( $this->wp['posts'][ $post_id ], $this->wp['meta'][ $post_id ] );
				$this->wp['deleted_posts'][] = $post_id;
				return $post;
			}
		);

		Functions\when( 'get_page_by_path' )->alias(
			function ( string $path, string $output = 'OBJECT', string $post_type = 'page' ) {
				unset( $output );
				foreach ( $this->wp['posts'] as $post ) {
					if ( $post instanceof WP_Post && $post_type === $post->post_type && $path === $post->post_name ) {
						return $post;
					}
				}

				return null;
			}
		);

		Functions\when( 'wp_slash' )->alias( fn( $value ) => $this->slash_value( $value ) );
		Functions\when( 'wp_unslash' )->alias( fn( $value ) => $this->unslash_value( $value ) );
		Functions\when( 'maybe_serialize' )->alias(
			static fn( $value ) => is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value
		);
		Functions\when( 'wp_cache_delete' )->alias(
			function ( string $key, string $group = '' ): bool {
				$this->wp['cache_deletes'][] = array( $key, $group );
				return true;
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn( $value ): bool => $value instanceof WP_Error );
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, array $defaults = array() ): array {
				return array_merge( $defaults, is_array( $args ) ? $args : array() );
			}
		);
	}

	/**
	 * Recursively add one WordPress escaping layer.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private function slash_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'slash_value' ), $value );
		}

		return is_string( $value ) ? addslashes( $value ) : $value;
	}

	/**
	 * Recursively remove the escaping layer applied by WordPress persistence.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $value Slashed value.
	 * @return mixed
	 */
	private function unslash_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'unslash_value' ), $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
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
				10 => array( 'title' => 'Blue', 'post_status' => 'publish', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
				11 => array( 'title' => 'Red', 'post_status' => 'publish', 'code' => '<ins>red</ins>', 'mobile_code' => '<ins>red-mobile</ins>', 'status' => 'active' ),
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
				10 => array( 'title' => 'Blue', 'post_status' => 'publish', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
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
				10 => array( 'title' => 'Blue', 'post_status' => 'publish', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'inactive' ),
			)
		);

		$this->assertFalse( $definition['versions'][0]['enabled'] );
		$this->assertSame( 'draft', $definition['post_status'] );
	}

	/**
	 * Unpublished source Ads remain disabled even when legacy meta says active.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_unpublished_source_ads_produce_disabled_versions(): void {
		$definition = Ad_Placr_Migration::build_legacy_placement_ad(
			array(
				'id'          => 90,
				'title'       => 'Draft creative',
				'post_status' => 'publish',
				'position'    => 'after_header',
				'status'      => 'active',
				'targeting'   => array(),
				'ads'         => array( array( 'ad_id' => 10, 'weight' => 1 ) ),
			),
			array(
				10 => array( 'title' => 'Blue', 'post_status' => 'draft', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
			)
		);

		$this->assertFalse( $definition['versions'][0]['enabled'] );
		$this->assertSame( 'draft', $definition['post_status'] );
	}

	/**
	 * A mobile-only creative is eligible when the source post and status are active.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_mobile_only_source_ads_produce_enabled_versions(): void {
		$definition = Ad_Placr_Migration::build_legacy_placement_ad(
			array(
				'id'          => 90,
				'title'       => 'Mobile-only creative',
				'post_status' => 'publish',
				'position'    => 'after_header',
				'status'      => 'active',
				'targeting'   => array(),
				'ads'         => array( array( 'ad_id' => 10, 'weight' => 1 ) ),
			),
			array(
				10 => array( 'title' => 'Blue', 'post_status' => 'publish', 'code' => '  ', 'mobile_code' => '<ins>mobile</ins>', 'status' => 'active' ),
			)
		);

		$this->assertTrue( $definition['versions'][0]['enabled'] );
		$this->assertSame( 'publish', $definition['post_status'] );
	}

	/**
	 * Positions registered through the public filter survive migration.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_filtered_registered_position_is_preserved(): void {
		$this->wp['filtered_positions'] = Ad_Placr_Positions::defaults();
		$this->wp['filtered_positions']['custom_registered'] = array(
			'label'       => 'Custom',
			'group'       => 'structure',
			'context'     => 'global',
			'hook'        => 'wp_footer',
			'priority'    => 10,
			'render_mode' => 'echo',
			'handler'     => 'frontend',
		);

		$definition = Ad_Placr_Migration::build_legacy_placement_ad(
			array(
				'id'          => 90,
				'title'       => 'Custom position',
				'post_status' => 'publish',
				'position'    => 'custom_registered',
				'status'      => 'active',
				'targeting'   => array(),
				'ads'         => array( array( 'ad_id' => 10, 'weight' => 1 ) ),
			),
			array(
				10 => array( 'title' => 'Blue', 'post_status' => 'publish', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
			)
		);

		$this->assertSame( 'custom_registered', $definition['position'] );
		$this->assertSame( 'publish', $definition['post_status'] );
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
				10 => array( 'title' => 'Blue', 'post_status' => 'publish', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
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
		$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ] = array(
			'settings'      => 'invalid',
			'placements'    => array(),
			'source_ad_ids' => array( '11', 0, 10, 11, -4, 'invalid' ),
		);

		$this->assertSame( array( 10, 11 ), Ad_Placr_Migration::source_ad_ids() );
	}

	/**
	 * Placement sources win over settings and backslashes survive persistence.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_run_prefers_placements_and_persists_unslashed_values_losslessly(): void {
		$expected = $this->seed_legacy_migration();

		$this->assertTrue( Ad_Placr_Migration::run() );

		$map    = $this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ];
		$new_id = $map['placements']['90'];

		$this->assertSame( array(), $map['settings'] );
		$this->assertSame( array( 10 ), $map['source_ad_ids'] );
		$this->assertContains(
			$this->wp['option_autoload'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ],
			array( 'no', false ),
			true
		);
		$map_creations = array_values(
			array_filter(
				$this->wp['add_option_calls'],
				static fn( array $call ): bool => Ad_Placr_Migration::OPTION_MIGRATION_MAP === $call[0]
			)
		);
		$this->assertSame( 'no', $map_creations[0][3] );
		$this->assertSame( $expected['code'], $this->wp['meta'][ $new_id ][ Ad_Placr_Ad::META_VERSIONS ][0]['code'] );
		$this->assertSame( $expected['targeting'], $this->wp['meta'][ $new_id ][ Ad_Placr_Ad::META_TARGETING ] );
		$this->assertSame( $expected['notes'], $this->wp['meta'][ $new_id ][ Ad_Placr_Ad::META_NOTES ] );
	}

	/**
	 * A mapped migration is idempotent and DB-version gating avoids all new work.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_run_maps_once_releases_lock_and_db_version_gates_rerun(): void {
		$this->seed_legacy_migration();

		$this->assertTrue( Ad_Placr_Migration::run() );
		$this->assertSame( 1, $this->wp['insert_calls'] );
		$this->assertArrayNotHasKey( Ad_Placr_Migration::OPTION_MIGRATION_LOCK, $this->wp['options'] );
		$legacy_lock_creations = array_values(
			array_filter(
				$this->wp['add_option_calls'],
				static fn( array $call ): bool => Ad_Placr_Migration::OPTION_MIGRATION_LOCK === $call[0]
			)
		);
		$this->assertSame( array(), $legacy_lock_creations );
		$this->assertCount( 1, $this->wp['atomic_lock_inserts'] );
		$this->assertSame( 'no', $this->wp['atomic_lock_inserts'][0]['autoload'] );
		$this->assertContains(
			array( Ad_Placr_Migration::OPTION_MIGRATION_LOCK, 'options' ),
			$this->wp['cache_deletes']
		);
		$this->assertContains( array( 'notoptions', 'options' ), $this->wp['cache_deletes'] );

		$lock_inserts = count( $this->wp['atomic_lock_inserts'] );
		$this->assertTrue( Ad_Placr_Migration::run() );
		$this->assertSame( 1, $this->wp['insert_calls'] );
		$this->assertSame( $lock_inserts, count( $this->wp['atomic_lock_inserts'] ) );
		$this->assertSame( Ad_Placr_Migration::DB_VERSION, $this->wp['options'][ Ad_Placr_Migration::OPTION_DB_VERSION ] );
	}

	/**
	 * A live migration lock prevents a concurrent request from inserting.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_run_stops_when_a_fresh_lock_is_held(): void {
		$this->seed_legacy_migration();
		$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ] = array(
			'token'      => 'another-request',
			'created_at' => time(),
		);

		$this->assertFalse( Ad_Placr_Migration::run() );
		$this->assertSame( 0, $this->wp['insert_calls'] );
		$this->assertSame(
			'another-request',
			$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ]['token']
		);
		$this->assertCount( 1, $this->wp['atomic_lock_inserts'] );
		$this->assertArrayNotHasKey( Ad_Placr_Migration::OPTION_DB_VERSION, $this->wp['options'] );
	}

	/**
	 * A stale migration lock is replaced and released after successful recovery.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_run_recovers_a_stale_lock(): void {
		$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ] = array(
			'token'      => 'dead-request',
			'created_at' => time() - 301,
		);

		$this->assertTrue( Ad_Placr_Migration::run() );
		$this->assertSame( 2, $this->wp['conditional_lock_deletes'] );
		$this->assertArrayNotHasKey( Ad_Placr_Migration::OPTION_MIGRATION_LOCK, $this->wp['options'] );
		$this->assertSame( Ad_Placr_Migration::DB_VERSION, $this->wp['options'][ Ad_Placr_Migration::OPTION_DB_VERSION ] );
	}

	/**
	 * Stale recovery never deletes a fresh lock installed after its read.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_stale_recovery_does_not_delete_a_replacement_lock(): void {
		$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ] = array(
			'token'      => 'dead-request',
			'created_at' => time() - 301,
		);
		$this->wp['replace_lock_before_delete'] = true;

		$this->assertFalse( Ad_Placr_Migration::run() );
		$this->assertSame(
			'replacement-request',
			$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ]['token']
		);
		$this->assertSame( 0, $this->wp['insert_calls'] );
		$this->assertArrayNotHasKey( Ad_Placr_Migration::OPTION_DB_VERSION, $this->wp['options'] );
	}

	/**
	 * Lock release never deletes a replacement installed after its ownership read.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_release_does_not_delete_a_replacement_lock(): void {
		$this->wp['replace_lock_before_delete'] = true;

		$this->assertTrue( Ad_Placr_Migration::run() );
		$this->assertSame(
			'replacement-request',
			$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_LOCK ]['token']
		);
		$this->assertSame( 1, $this->wp['conditional_lock_deletes'] );
	}

	/**
	 * A metadata failure remains unmapped and retries the same destination stub.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_meta_failure_remains_unmapped_and_retries_the_same_destination(): void {
		$this->seed_legacy_migration();
		$this->wp['fail_update_post_meta_once'][ Ad_Placr_Ad::META_VERSIONS ] = 1;

		$this->assertFalse( Ad_Placr_Migration::run() );

		$map = $this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ];
		$this->assertArrayNotHasKey( '90', $map['placements'] );
		$new_id = $this->destination_ids()[0];
		$this->assertSame( 'draft', $this->wp['posts'][ $new_id ]->post_status );
		$this->assertSame( 1, $this->wp['insert_calls'] );
		$this->assertArrayNotHasKey( Ad_Placr_Migration::OPTION_DB_VERSION, $this->wp['options'] );
		$this->assertArrayNotHasKey( Ad_Placr_Migration::OPTION_MIGRATION_LOCK, $this->wp['options'] );

		$this->assertTrue( Ad_Placr_Migration::run() );
		$this->assertSame( 1, $this->wp['insert_calls'] );
		$this->assertSame( 'publish', $this->wp['posts'][ $new_id ]->post_status );
		$this->assertSame( $new_id, $this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ]['placements']['90'] );
	}

	/**
	 * An orphan left by failed map persistence is recovered by its source slug.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_map_failure_and_failed_cleanup_recovers_the_orphan(): void {
		$this->seed_legacy_migration();
		$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ] = array(
			'settings'      => array(),
			'placements'    => array(),
			'source_ad_ids' => array( 10 ),
		);
		$this->wp['option_autoload'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ] = false;
		$this->wp['fail_update_option_once'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ] = 1;
		$this->wp['fail_delete_post_once'] = true;

		$this->assertFalse( Ad_Placr_Migration::run() );
		$this->assertSame( 1, $this->wp['insert_calls'] );
		$this->assertSame( array(), $this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ]['placements'] );
		$this->assertCount( 1, $this->destination_ids() );
		$orphan_id = $this->destination_ids()[0];
		$this->assertSame( 'publish', $this->wp['posts'][ $orphan_id ]->post_status );
		$this->assertSame(
			'after_header',
			$this->wp['meta'][ $orphan_id ][ Ad_Placr_Ad::META_POSITION ]
		);

		$this->assertTrue( Ad_Placr_Migration::run() );
		$this->assertSame( 1, $this->wp['insert_calls'] );
		$this->assertCount( 1, $this->destination_ids() );
		$this->assertSame(
			$this->destination_ids()[0],
			$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ]['placements']['90']
		);
	}

	/**
	 * Identical metadata returning false is verified and accepted as success.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_identical_meta_values_are_not_treated_as_persistence_failure(): void {
		$this->seed_legacy_migration();
		$definition = Ad_Placr_Migration::build_legacy_placement_ad(
			$this->legacy_placement_values(),
			$this->legacy_source_values()
		);
		$new_id     = 100;

		$this->wp['next_post_id'] = 101;
		$this->wp['posts'][ $new_id ] = new WP_Post(
			array(
				'ID'          => $new_id,
				'post_type'   => Ad_Placr_Ad::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $definition['title'],
				'post_name'   => '',
			)
		);
		$this->wp['meta'][ $new_id ] = array(
			Ad_Placr_Ad::META_POSITION  => $definition['position'],
			Ad_Placr_Ad::META_TARGETING => $definition['targeting'],
			Ad_Placr_Ad::META_VERSIONS  => $definition['versions'],
			Ad_Placr_Ad::META_NOTES     => $definition['notes'],
		);
		$this->wp['options'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ] = array(
			'settings'      => array(),
			'placements'    => array( '90' => $new_id ),
			'source_ad_ids' => array( 10 ),
		);
		$this->wp['option_autoload'][ Ad_Placr_Migration::OPTION_MIGRATION_MAP ] = false;

		$this->assertTrue( Ad_Placr_Migration::run() );
		$this->assertSame( 0, $this->wp['insert_calls'] );
		$this->assertSame( 'publish', $this->wp['posts'][ $new_id ]->post_status );
		$this->assertArrayNotHasKey( $new_id, array_flip( $this->wp['deleted_posts'] ) );
	}

	/**
	 * Seed one retained Placement, one source Ad, and conflicting public settings.
	 *
	 * @since 2.7.0
	 *
	 * @return array{code:string,targeting:array<string,mixed>,notes:string}
	 */
	private function seed_legacy_migration(): array {
		$placement = $this->legacy_placement_values();
		$source    = $this->legacy_source_values()[10];

		$this->wp['posts'][90] = new WP_Post(
			array(
				'ID'          => 90,
				'post_type'   => 'ad_placr_placement',
				'post_status' => $placement['post_status'],
				'post_title'  => $placement['title'],
				'post_name'   => 'legacy-placement',
			)
		);
		$this->wp['posts'][10] = new WP_Post(
			array(
				'ID'          => 10,
				'post_type'   => Ad_Placr_Ad::POST_TYPE,
				'post_status' => $source['post_status'],
				'post_title'  => $source['title'],
				'post_name'   => 'legacy-source',
			)
		);
		$this->wp['meta'][90]  = array(
			'_ad_placr_position'  => $placement['position'],
			'_ad_placr_status'    => $placement['status'],
			'_ad_placr_targeting' => $placement['targeting'],
			'_ad_placr_ads'       => $placement['ads'],
			'_ad_placr_notes'     => $placement['notes'],
		);
		$this->wp['meta'][10]  = array(
			'_ad_placr_code'        => $source['code'],
			'_ad_placr_mobile_code' => $source['mobile_code'],
			'_ad_placr_status'      => $source['status'],
		);
		$this->wp['options'][ Ad_Placr_Settings_Page::OPTION_NAME ] = array(
			'footer_sticky' => array(
				'enabled'     => true,
				'code'        => '<ins>settings-must-not-run</ins>',
				'mobile_code' => '',
			),
		);

		return array(
			'code'      => $source['code'],
			'targeting' => $placement['targeting'],
			'notes'     => $placement['notes'],
		);
	}

	/**
	 * Return one complete retained Placement value set.
	 *
	 * @since 2.7.0
	 *
	 * @return array<string, mixed>
	 */
	private function legacy_placement_values(): array {
		return array(
			'id'          => 90,
			'title'       => 'Header regex',
			'post_status' => 'publish',
			'position'    => 'after_header',
			'status'      => 'active',
			'targeting'   => array(
				'contexts'    => array( 'all' ),
				'url_contains' => array( '^/news/\d+$' ),
			),
			'ads'         => array( array( 'ad_id' => 10, 'weight' => 3 ) ),
			'notes'       => 'Regex \d+ and path C:\ads\unit',
		);
	}

	/**
	 * Return one complete retained source Ad value set.
	 *
	 * @since 2.7.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function legacy_source_values(): array {
		return array(
			10 => array(
				'title'       => 'Regex creative',
				'post_status' => 'publish',
				'code'        => '<script>window.slot = /\d+\\path/;</script>',
				'mobile_code' => '',
				'status'      => 'active',
			),
		);
	}

	/**
	 * Return destination Ad IDs while excluding the retained source Ad.
	 *
	 * @since 2.7.0
	 *
	 * @return int[]
	 */
	private function destination_ids(): array {
		$ids = array();
		foreach ( $this->wp['posts'] as $post ) {
			if ( $post instanceof WP_Post && Ad_Placr_Ad::POST_TYPE === $post->post_type && 10 !== $post->ID ) {
				$ids[] = $post->ID;
			}
		}

		sort( $ids, SORT_NUMERIC );
		return $ids;
	}
}
