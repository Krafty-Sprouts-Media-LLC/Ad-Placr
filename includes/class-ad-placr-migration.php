<?php
/**
 * Non-destructive migration into complete unified Ad records.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts public settings or retained two-record data into complete Ads.
 *
 * A non-autoloaded source map makes retries idempotent while source settings,
 * Placement posts, and creative-only Ad posts remain available for audit.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Migration {

	/**
	 * Current unified data-model migration version.
	 *
	 * @since 2.0.0
	 */
	public const DB_VERSION = 2;

	/**
	 * Option that records the completed data-model version.
	 *
	 * @since 2.0.0
	 */
	public const OPTION_DB_VERSION = 'ad_placr_db_version';

	/**
	 * Non-autoloaded source-to-result audit map.
	 *
	 * @since 2.7.0
	 */
	public const OPTION_MIGRATION_MAP = 'ad_placr_unified_migration_map';

	/**
	 * Retained Placement post type used only as a migration source.
	 *
	 * @since 2.7.0
	 */
	private const LEGACY_PLACEMENT_POST_TYPE = 'ad_placr_placement';

	/**
	 * Retained display-position meta key.
	 *
	 * @since 2.7.0
	 */
	private const LEGACY_META_POSITION = '_ad_placr_position';

	/**
	 * Retained active/inactive meta key.
	 *
	 * @since 2.7.0
	 */
	private const LEGACY_META_STATUS = '_ad_placr_status';

	/**
	 * Retained display-rule meta key.
	 *
	 * @since 2.7.0
	 */
	private const LEGACY_META_TARGETING = '_ad_placr_targeting';

	/**
	 * Retained weighted creative-list meta key.
	 *
	 * @since 2.7.0
	 */
	private const LEGACY_META_ADS = '_ad_placr_ads';

	/**
	 * Retained universal creative-code meta key.
	 *
	 * @since 2.7.0
	 */
	private const LEGACY_META_CODE = '_ad_placr_code';

	/**
	 * Retained mobile creative-code meta key.
	 *
	 * @since 2.7.0
	 */
	private const LEGACY_META_MOBILE_CODE = '_ad_placr_mobile_code';

	/**
	 * Retained private-notes meta key.
	 *
	 * @since 2.7.0
	 */
	private const LEGACY_META_NOTES = '_ad_placr_notes';

	/**
	 * Register the migration check after post types are available.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 20 );
	}

	/**
	 * Run the migration only while the stored version is behind.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		$current = (int) get_option( self::OPTION_DB_VERSION, 0 );

		if ( $current >= self::DB_VERSION ) {
			return;
		}

		self::run();
	}

	/**
	 * Persist every required source as one complete unified Ad.
	 *
	 * Each successful insert is mapped immediately. A failed insert or map write
	 * leaves the DB version behind so a later request retries only unmapped data.
	 * Retained source records and public settings are never changed.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True when every required definition is mapped and the DB version is current.
	 */
	public static function run(): bool {
		$map = self::load_migration_map();
		if ( is_wp_error( $map ) ) {
			return false;
		}

		$placement_ids = self::legacy_placement_ids();

		if ( ! empty( $placement_ids ) ) {
			foreach ( $placement_ids as $placement_id ) {
				$placement = self::read_legacy_placement( $placement_id );
				if ( null === $placement ) {
					continue;
				}

				$links         = self::normalize_legacy_ad_links( $placement['ads'] );
				$source_ad_ids = array_merge( $map['source_ad_ids'], array_column( $links, 'ad_id' ) );
				$next_map      = $map;

				$next_map['source_ad_ids'] = self::normalize_source_ids( $source_ad_ids );

				if ( $next_map !== $map ) {
					if ( ! self::save_migration_map( $next_map ) ) {
						return false;
					}
					$map = $next_map;
				}

				$map_key = (string) $placement_id;
				if ( isset( $map['placements'][ $map_key ] ) ) {
					continue;
				}

				$definition = self::build_legacy_placement_ad(
					$placement,
					self::read_legacy_source_ads( $links )
				);
				$new_id     = self::insert_unified_ad( $definition );
				if ( is_wp_error( $new_id ) ) {
					return false;
				}

				$next_map = $map;

				$next_map['placements'][ $map_key ] = $new_id;
				if ( ! self::save_migration_map( $next_map ) ) {
					wp_delete_post( $new_id, true );
					return false;
				}
				$map = $next_map;
			}
		} else {
			$definitions = self::build_settings_ads( Ad_Placr_Plugin::get_settings() );

			foreach ( $definitions as $definition ) {
				$source_key = (string) $definition['source_key'];
				if ( isset( $map['settings'][ $source_key ] ) ) {
					continue;
				}

				$new_id = self::insert_unified_ad( $definition );
				if ( is_wp_error( $new_id ) ) {
					return false;
				}

				$next_map = $map;

				$next_map['settings'][ $source_key ] = $new_id;
				if ( ! self::save_migration_map( $next_map ) ) {
					wp_delete_post( $new_id, true );
					return false;
				}
				$map = $next_map;
			}
		}

		if ( (int) get_option( self::OPTION_DB_VERSION, 0 ) >= self::DB_VERSION ) {
			return true;
		}

		return update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	/**
	 * Build complete unified Ads from the public settings option.
	 *
	 * Disabled rows and rows without universal or mobile code are intentionally
	 * omitted; retained public settings remain unchanged for later verification.
	 *
	 * @since 2.7.0
	 *
	 * @param array<string, mixed> $settings Legacy public settings.
	 * @return array<int, array{
	 *     source_key:string,
	 *     title:string,
	 *     post_status:string,
	 *     position:string,
	 *     targeting:array<string,mixed>,
	 *     versions:array<int,array{version_id:string,name:string,code:string,mobile_code:string,weight:int,enabled:bool}>,
	 *     notes:string
	 * }>
	 */
	public static function build_settings_ads( array $settings ): array {
		$definitions   = array();
		$footer        = isset( $settings['footer_sticky'] ) && is_array( $settings['footer_sticky'] )
			? $settings['footer_sticky']
			: array();
		$footer_code   = isset( $footer['code'] ) ? (string) $footer['code'] : '';
		$footer_mobile = isset( $footer['mobile_code'] ) ? (string) $footer['mobile_code'] : '';

		if ( ! empty( $footer['enabled'] ) && self::has_creative( $footer_code, $footer_mobile ) ) {
			$source_key    = 'settings:footer_sticky';
			$definitions[] = array(
				'source_key'  => $source_key,
				'title'       => 'Footer sticky',
				'post_status' => 'publish',
				'position'    => Ad_Placr_Positions::STICKY_FOOTER,
				'targeting'   => array(
					'post_types' => array(),
					'contexts'   => array( 'all' ),
					'devices'    => array( 'desktop', 'tablet', 'mobile' ),
				),
				'versions'    => array(
					array(
						'version_id'  => self::source_version_id( $source_key ),
						'name'        => 'Version A',
						'code'        => $footer_code,
						'mobile_code' => $footer_mobile,
						'weight'      => 1,
						'enabled'     => true,
					),
				),
				'notes'       => '',
			);
		}

		$slots = isset( $settings['in_content_slots'] ) && is_array( $settings['in_content_slots'] )
			? $settings['in_content_slots']
			: array();

		foreach ( $slots as $index => $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}

			$code   = isset( $slot['code'] ) ? (string) $slot['code'] : '';
			$mobile = isset( $slot['mobile_code'] ) ? (string) $slot['mobile_code'] : '';
			if ( empty( $slot['enabled'] ) || ! self::has_creative( $code, $mobile ) ) {
				continue;
			}

			$raw_slot_id = isset( $slot['id'] ) ? (string) $slot['id'] : '';
			$slot_id     = strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', $raw_slot_id ) );
			$source_part = '' !== $slot_id ? $slot_id : (string) $index;
			$source_key  = 'settings:in_content:' . $source_part;
			$title       = isset( $slot['title'] ) ? trim( (string) $slot['title'] ) : '';
			$post_types  = self::normalize_string_list( $slot['post_types'] ?? array() );
			$position    = 'before' === ( $slot['position'] ?? 'after' )
				? Ad_Placr_Positions::IN_CONTENT_BEFORE_PARAGRAPH
				: Ad_Placr_Positions::IN_CONTENT_AFTER_PARAGRAPH;

			$definitions[] = array(
				'source_key'  => $source_key,
				'title'       => '' !== $title ? $title : 'In-content ad',
				'post_status' => 'publish',
				'position'    => $position,
				'targeting'   => array(
					'post_types' => $post_types,
					'contexts'   => array( 'singular' ),
					'devices'    => array( 'desktop', 'tablet', 'mobile' ),
					'paragraph'  => max( 1, (int) ( $slot['paragraph_index'] ?? 1 ) ),
					'slot_id'    => $slot_id,
				),
				'versions'    => array(
					array(
						'version_id'  => self::source_version_id( $source_key ),
						'name'        => 'Version A',
						'code'        => $code,
						'mobile_code' => $mobile,
						'weight'      => 1,
						'enabled'     => true,
					),
				),
				'notes'       => '',
			);
		}

		return $definitions;
	}

	/**
	 * Build one complete unified Ad from a retained Placement and its Ads.
	 *
	 * Linked Ads are processed in saved order. Missing source records are ignored,
	 * while inactive or empty source Ads remain visible as disabled versions.
	 *
	 * @since 2.7.0
	 *
	 * @param array<string, mixed> $placement Retained Placement values.
	 * @param array<int, mixed>    $source_ads Source creative values keyed by source post ID.
	 * @return array{
	 *     source_key:string,
	 *     title:string,
	 *     post_status:string,
	 *     position:string,
	 *     targeting:array<string,mixed>,
	 *     versions:array<int,array{version_id:string,name:string,code:string,mobile_code:string,weight:int,enabled:bool}>,
	 *     notes:string
	 * }
	 */
	public static function build_legacy_placement_ad( array $placement, array $source_ads ): array {
		$versions = array();
		$links    = self::normalize_legacy_ad_links( $placement['ads'] ?? array() );

		foreach ( $links as $index => $link ) {
			$source_ad_id = $link['ad_id'];
			if ( ! isset( $source_ads[ $source_ad_id ] ) || ! is_array( $source_ads[ $source_ad_id ] ) ) {
				continue;
			}

			$source      = $source_ads[ $source_ad_id ];
			$code        = isset( $source['code'] ) ? (string) $source['code'] : '';
			$mobile_code = isset( $source['mobile_code'] ) ? (string) $source['mobile_code'] : '';
			$source_name = isset( $source['title'] ) ? trim( (string) $source['title'] ) : '';
			$enabled     = 'active' === strtolower( (string) ( $source['status'] ?? '' ) )
				&& self::has_creative( $code, $mobile_code );

			$versions[] = array(
				'version_id'  => self::source_version_id( 'ad:' . $source_ad_id ),
				'name'        => '' !== $source_name ? $source_name : 'Version ' . ( $index + 1 ),
				'code'        => $code,
				'mobile_code' => $mobile_code,
				'weight'      => $link['weight'],
				'enabled'     => $enabled,
			);
		}

		$raw_position = isset( $placement['position'] ) ? (string) $placement['position'] : '';
		$position     = array_key_exists( $raw_position, Ad_Placr_Positions::defaults() ) ? $raw_position : '';
		$targeting    = isset( $placement['targeting'] ) && is_array( $placement['targeting'] )
			? $placement['targeting']
			: array();
		$has_eligible = false;

		foreach ( $versions as $version ) {
			if ( $version['enabled'] ) {
				$has_eligible = true;
				break;
			}
		}

		$is_active = 'publish' === ( $placement['post_status'] ?? '' )
			&& 'active' === strtolower( (string) ( $placement['status'] ?? '' ) )
			&& '' !== $position
			&& $has_eligible;
		$title     = isset( $placement['title'] ) ? trim( (string) $placement['title'] ) : '';
		$source_id = max( 0, (int) ( $placement['id'] ?? 0 ) );

		return array(
			'source_key'  => 'placement:' . $source_id,
			'title'       => '' !== $title ? $title : 'Migrated Ad',
			'post_status' => $is_active ? 'publish' : 'draft',
			'position'    => $position,
			'targeting'   => $targeting,
			'versions'    => $versions,
			'notes'       => isset( $placement['notes'] ) ? (string) $placement['notes'] : '',
		);
	}

	/**
	 * Derive a stable UUID-shaped version identity from a retained source key.
	 *
	 * The digest is an identity seed rather than a security primitive. Explicit
	 * UUID version/variant nibbles keep the result compatible with saved versions.
	 *
	 * @since 2.7.0
	 *
	 * @param string $source_key Stable retained source identifier.
	 * @return string Deterministic UUID-shaped version ID.
	 */
	public static function source_version_id( string $source_key ): string {
		$hex = md5( 'ad-placr-version:' . $source_key );

		return substr( $hex, 0, 8 ) . '-' .
			substr( $hex, 8, 4 ) . '-4' .
			substr( $hex, 13, 3 ) . '-8' .
			substr( $hex, 17, 3 ) . '-' .
			substr( $hex, 20, 12 );
	}

	/**
	 * Return normalized retained creative IDs for the Ads-list exclusion.
	 *
	 * @since 2.7.0
	 *
	 * @return int[] Positive unique source Ad IDs.
	 */
	public static function source_ad_ids(): array {
		$map = self::normalize_migration_map( get_option( self::OPTION_MIGRATION_MAP, array() ) );

		return $map['source_ad_ids'];
	}

	/**
	 * Load and repair the non-autoloaded source map before migration work.
	 *
	 * @since 2.7.0
	 *
	 * @return array{settings:array<string,int>,placements:array<string,int>,source_ad_ids:int[]}|WP_Error
	 */
	private static function load_migration_map(): array|WP_Error {
		$raw = get_option( self::OPTION_MIGRATION_MAP, null );
		$map = self::normalize_migration_map( $raw );

		if ( null === $raw ) {
			// @phpstan-ignore-next-line The string form is required for WordPress 6.0 compatibility.
			if ( add_option( self::OPTION_MIGRATION_MAP, $map, '', 'no' ) ) {
				return $map;
			}

			/*
			 * Another request may have created the option between get/add. Re-read
			 * it before treating the failed add as a storage failure.
			 */
			$concurrent = get_option( self::OPTION_MIGRATION_MAP, null );
			if ( null !== $concurrent ) {
				return self::normalize_migration_map( $concurrent );
			}

			return new WP_Error( 'ad_placr_migration_map_create_failed', 'The Ad migration map could not be created.' );
		}

		if ( $raw !== $map && ! update_option( self::OPTION_MIGRATION_MAP, $map, false ) ) {
			return new WP_Error( 'ad_placr_migration_map_normalize_failed', 'The Ad migration map could not be normalized.' );
		}

		return $map;
	}

	/**
	 * Normalize a migration map into its stable audit shape.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $raw Untrusted option value.
	 * @return array{settings:array<string,int>,placements:array<string,int>,source_ad_ids:int[]}
	 */
	private static function normalize_migration_map( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();

		return array(
			'settings'      => self::normalize_result_map( $raw['settings'] ?? array() ),
			'placements'    => self::normalize_result_map( $raw['placements'] ?? array() ),
			'source_ad_ids' => self::normalize_source_ids( $raw['source_ad_ids'] ?? array() ),
		);
	}

	/**
	 * Normalize one source-key-to-post-ID map.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $raw Untrusted associative mapping.
	 * @return array<string, int>
	 */
	private static function normalize_result_map( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $raw as $source_key => $post_id ) {
			$source_key = (string) $source_key;
			$post_id    = (int) $post_id;
			if ( '' === $source_key || $post_id < 1 ) {
				continue;
			}
			$normalized[ $source_key ] = $post_id;
		}

		return $normalized;
	}

	/**
	 * Normalize source creative IDs for stable audit and exclusion use.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $raw Untrusted source ID list.
	 * @return int[]
	 */
	private static function normalize_source_ids( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();
		foreach ( $raw as $source_id ) {
			$source_id = (int) $source_id;
			if ( $source_id > 0 ) {
				$ids[] = $source_id;
			}
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids, SORT_NUMERIC );

		return $ids;
	}

	/**
	 * Persist a changed source map without enabling autoload.
	 *
	 * @since 2.7.0
	 *
	 * @param array{settings:array<string,int>,placements:array<string,int>,source_ad_ids:int[]} $map Source map.
	 * @return bool Whether WordPress persisted the changed value.
	 */
	private static function save_migration_map( array $map ): bool {
		return update_option( self::OPTION_MIGRATION_MAP, self::normalize_migration_map( $map ), false );
	}

	/**
	 * Query every retained Placement status in a deterministic order.
	 *
	 * @since 2.7.0
	 *
	 * @return int[] Placement post IDs.
	 */
	private static function legacy_placement_ids(): array {
		$ids = get_posts(
			array(
				'post_type'              => self::LEGACY_PLACEMENT_POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'trash', 'future' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);

		return self::normalize_source_ids( $ids );
	}

	/**
	 * Read one retained Placement without depending on its retired class.
	 *
	 * @since 2.7.0
	 *
	 * @param int $placement_id Retained Placement post ID.
	 * @return array<string, mixed>|null Source values, or null when the post disappeared.
	 */
	private static function read_legacy_placement( int $placement_id ): ?array {
		$post = get_post( $placement_id );
		if ( ! $post instanceof WP_Post || self::LEGACY_PLACEMENT_POST_TYPE !== $post->post_type ) {
			return null;
		}

		$targeting = get_post_meta( $placement_id, self::LEGACY_META_TARGETING, true );

		return array(
			'id'          => $placement_id,
			'title'       => (string) $post->post_title,
			'post_status' => (string) $post->post_status,
			'position'    => (string) get_post_meta( $placement_id, self::LEGACY_META_POSITION, true ),
			'status'      => (string) get_post_meta( $placement_id, self::LEGACY_META_STATUS, true ),
			'targeting'   => is_array( $targeting ) ? $targeting : array(),
			'ads'         => get_post_meta( $placement_id, self::LEGACY_META_ADS, true ),
			'notes'       => (string) get_post_meta( $placement_id, self::LEGACY_META_NOTES, true ),
		);
	}

	/**
	 * Normalize retained weighted links without calling the retired Placement class.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $raw Untrusted retained weighted rows.
	 * @return array<int, array{ad_id:int,weight:int}>
	 */
	private static function normalize_legacy_ad_links( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$links = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$ad_id = (int) ( $row['ad_id'] ?? 0 );
			if ( $ad_id < 1 ) {
				continue;
			}

			$links[] = array(
				'ad_id'  => $ad_id,
				'weight' => max( 1, (int) ( $row['weight'] ?? 1 ) ),
			);
		}

		return $links;
	}

	/**
	 * Read retained creative values referenced by one Placement.
	 *
	 * @since 2.7.0
	 *
	 * @param array<int, array{ad_id:int,weight:int}> $links Normalized weighted source links.
	 * @return array<int, array<string, mixed>> Source Ad values keyed by post ID.
	 */
	private static function read_legacy_source_ads( array $links ): array {
		$source_ads = array();

		foreach ( $links as $link ) {
			$source_id = $link['ad_id'];
			if ( isset( $source_ads[ $source_id ] ) ) {
				continue;
			}

			$post = get_post( $source_id );
			if ( ! $post instanceof WP_Post || Ad_Placr_Ad::POST_TYPE !== $post->post_type ) {
				continue;
			}

			$source_ads[ $source_id ] = array(
				'title'       => (string) $post->post_title,
				'code'        => (string) get_post_meta( $source_id, self::LEGACY_META_CODE, true ),
				'mobile_code' => (string) get_post_meta( $source_id, self::LEGACY_META_MOBILE_CODE, true ),
				'status'      => (string) get_post_meta( $source_id, self::LEGACY_META_STATUS, true ),
			);
		}

		return $source_ads;
	}

	/**
	 * Insert one complete unified Ad and all four unified meta values.
	 *
	 * A metadata failure removes only the incomplete new destination post. Source
	 * records remain untouched, and the absent map entry makes the source retryable.
	 *
	 * @since 2.7.0
	 *
	 * @param array<string, mixed> $definition Complete unified Ad definition.
	 * @return int|WP_Error New unified Ad ID or persistence failure.
	 */
	private static function insert_unified_ad( array $definition ): int|WP_Error {
		$post_id = wp_insert_post(
			array(
				'post_type'   => Ad_Placr_Ad::POST_TYPE,
				'post_status' => 'publish' === ( $definition['post_status'] ?? '' ) ? 'publish' : 'draft',
				'post_title'  => wp_slash( (string) ( $definition['title'] ?? 'Migrated Ad' ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		$meta = array(
			Ad_Placr_Ad::META_POSITION  => (string) ( $definition['position'] ?? '' ),
			Ad_Placr_Ad::META_TARGETING => isset( $definition['targeting'] ) && is_array( $definition['targeting'] )
				? $definition['targeting']
				: array(),
			Ad_Placr_Ad::META_VERSIONS  => isset( $definition['versions'] ) && is_array( $definition['versions'] )
				? $definition['versions']
				: array(),
			Ad_Placr_Ad::META_NOTES     => (string) ( $definition['notes'] ?? '' ),
		);

		foreach ( $meta as $meta_key => $meta_value ) {
			if ( false === update_post_meta( $post_id, $meta_key, $meta_value ) ) {
				wp_delete_post( $post_id, true );

				return new WP_Error( 'ad_placr_migration_meta_failed', 'The unified Ad metadata could not be saved.' );
			}
		}

		return $post_id;
	}

	/**
	 * Whether either retained code field contains non-whitespace content.
	 *
	 * @since 2.7.0
	 *
	 * @param string $code        Universal creative code.
	 * @param string $mobile_code Mobile override creative code.
	 * @return bool
	 */
	private static function has_creative( string $code, string $mobile_code ): bool {
		return '' !== trim( $code ) || '' !== trim( $mobile_code );
	}

	/**
	 * Preserve a stable ordered list of non-empty scalar strings.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $raw Untrusted list.
	 * @return string[]
	 */
	private static function normalize_string_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$strings = array();
		foreach ( $raw as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = (string) $value;
			if ( '' !== $value ) {
				$strings[] = $value;
			}
		}

		return array_values( array_unique( $strings ) );
	}
}
