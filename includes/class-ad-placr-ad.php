<?php
/**
 * Unified ad post type: complete display records.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines complete Ad display records and their version-selection behavior.
 *
 * The class registers the private Ad post type, its unified meta contract, and
 * validated accessors for the renderers that consume those records.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Ad {

	/**
	 * Post type used to store complete ad display records.
	 *
	 * @since 2.0.0
	 */
	public const POST_TYPE = 'ad_placr_ad';

	/**
	 * Display-location meta key.
	 *
	 * @since 2.7.0
	 */
	public const META_POSITION = '_ad_placr_position';

	/**
	 * Display-rule meta key.
	 *
	 * @since 2.7.0
	 */
	public const META_TARGETING = '_ad_placr_targeting';

	/**
	 * Ordered ad-version meta key.
	 *
	 * @since 2.7.0
	 */
	public const META_VERSIONS = '_ad_placr_versions';

	/**
	 * Private administrator notes meta key.
	 *
	 * @since 2.0.0
	 */
	public const META_NOTES = '_ad_placr_notes';

	/**
	 * Meta key storing the ad alignment preference.
	 *
	 * @since 2.8.0
	 */
	public const META_ALIGNMENT = '_ad_placr_alignment';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the CPT and its meta.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Ads', 'ad-placr' ),
					'singular_name' => __( 'Ad', 'ad-placr' ),
					'add_new_item'  => __( 'Add New Ad', 'ad-placr' ),
					'edit_item'     => __( 'Edit Ad', 'ad-placr' ),
					'menu_name'     => __( 'Ads', 'ad-placr' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-megaphone',
				'menu_position'   => 26,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_post'              => 'manage_options',
					'read_post'              => 'manage_options',
					'delete_post'            => 'manage_options',
					'edit_posts'             => 'manage_options',
					'edit_others_posts'      => 'manage_options',
					'publish_posts'          => 'manage_options',
					'read_private_posts'     => 'manage_options',
					'delete_posts'           => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'edit_private_posts'     => 'manage_options',
					'edit_published_posts'   => 'manage_options',
				),
				'map_meta_cap'    => false,
			)
		);

		$string_meta = array(
			self::META_POSITION,
			self::META_NOTES,
			self::META_ALIGNMENT,
		);
		foreach ( $string_meta as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}

		$array_meta = array( self::META_TARGETING, self::META_VERSIONS );

		foreach ( $array_meta as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'array',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}
	}

	/**
	 * Normalize raw version rows into a stable ordered representation.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $raw Untrusted version rows from post meta.
	 * @return array<int, array{version_id:string, name:string, code:string, mobile_code:string, weight:int, enabled:bool}>
	 */
	public static function normalize_versions( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$versions = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$version_id = isset( $row['version_id'] ) ? trim( (string) $row['version_id'] ) : '';
			$version_id = (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', $version_id );
			if ( '' === $version_id ) {
				continue;
			}

			$versions[] = array(
				'version_id'  => substr( $version_id, 0, 64 ),
				'name'        => isset( $row['name'] ) ? trim( (string) $row['name'] ) : '',
				'code'        => isset( $row['code'] ) ? (string) $row['code'] : '',
				'mobile_code' => isset( $row['mobile_code'] ) ? (string) $row['mobile_code'] : '',
				'weight'      => max( 1, isset( $row['weight'] ) ? (int) $row['weight'] : 1 ),
				'enabled'     => ! empty( $row['enabled'] ),
			);
		}

		return $versions;
	}

	/**
	 * Return enabled versions that include desktop or mobile creative code.
	 *
	 * @since 2.7.0
	 *
	 * @param array<int, array<string, mixed>> $versions Raw or normalized version rows.
	 * @return array<int, array{version_id:string, name:string, code:string, mobile_code:string, weight:int, enabled:bool}>
	 */
	public static function eligible_versions( array $versions ): array {
		$eligible = array();
		foreach ( self::normalize_versions( $versions ) as $version ) {
			if ( ! $version['enabled'] ) {
				continue;
			}
			if ( '' === trim( $version['code'] ) && '' === trim( $version['mobile_code'] ) ) {
				continue;
			}
			$eligible[] = $version;
		}

		return $eligible;
	}

	/**
	 * Select one eligible version deterministically from a weighted roll.
	 *
	 * Normalizing the roll keeps callers free to provide any signed integer while
	 * preserving a stable weighted distribution over the eligible rows only.
	 *
	 * @since 2.7.0
	 *
	 * @param array<int, array<string, mixed>> $versions Raw or normalized version rows.
	 * @param int                              $roll     Deterministic roll value.
	 * @return array{version_id:string, name:string, code:string, mobile_code:string, weight:int, enabled:bool}|null
	 */
	public static function choose_weighted_version( array $versions, int $roll ): ?array {
		$eligible = self::eligible_versions( $versions );
		$total    = array_sum( array_column( $eligible, 'weight' ) );
		if ( $total < 1 ) {
			return null;
		}

		$roll   = ( ( $roll % $total ) + $total ) % $total;
		$cursor = 0;
		foreach ( $eligible as $version ) {
			$cursor += $version['weight'];
			if ( $roll < $cursor ) {
				return $version;
			}
		}

		return null;
	}

	/**
	 * Whether an ad record is published and therefore active.
	 *
	 * @since 2.7.0
	 *
	 * @param int $ad_id Ad post ID.
	 * @return bool
	 */
	public static function is_active( int $ad_id ): bool {
		return self::POST_TYPE === get_post_type( $ad_id ) && 'publish' === get_post_status( $ad_id );
	}

	/**
	 * Get a valid canonical display position for an ad record.
	 *
	 * @since 2.7.0
	 *
	 * @param int $ad_id Ad post ID.
	 * @return string Canonical position key, or an empty string when invalid.
	 */
	public static function get_position( int $ad_id ): string {
		$position = (string) get_post_meta( $ad_id, self::META_POSITION, true );

		return Ad_Placr_Positions::exists( $position ) ? $position : '';
	}

	/**
	 * Normalize a submitted alignment value to the stored allow-list.
	 *
	 * Unknown values collapse to "none", which renders the ad code untouched.
	 *
	 * @since 2.8.0
	 *
	 * @param mixed $raw Raw value from storage or request.
	 * @return string One of none, left, center, right.
	 */
	public static function normalize_alignment( $raw ): string {
		$value = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';

		return in_array( $value, array( 'none', 'left', 'center', 'right' ), true ) ? $value : 'none';
	}

	/**
	 * Return the saved alignment for an Ad ("none" when unset).
	 *
	 * @since 2.8.0
	 *
	 * @param int $ad_id Ad ID.
	 * @return string One of none, left, center, right.
	 */
	public static function get_alignment( int $ad_id ): string {
		return self::normalize_alignment( get_post_meta( $ad_id, self::META_ALIGNMENT, true ) );
	}

	/**
	 * Get the display rules stored for an ad record.
	 *
	 * @since 2.7.0
	 *
	 * @param int $ad_id Ad post ID.
	 * @return array<string, mixed>
	 */
	public static function get_targeting( int $ad_id ): array {
		$targeting = get_post_meta( $ad_id, self::META_TARGETING, true );

		return is_array( $targeting ) ? $targeting : array();
	}

	/**
	 * Get normalized versions stored for an ad record.
	 *
	 * @since 2.7.0
	 *
	 * @param int $ad_id Ad post ID.
	 * @return array<int, array{version_id:string, name:string, code:string, mobile_code:string, weight:int, enabled:bool}>
	 */
	public static function get_versions( int $ad_id ): array {
		return self::normalize_versions( get_post_meta( $ad_id, self::META_VERSIONS, true ) );
	}

	/**
	 * Query published ad IDs assigned to one canonical position.
	 *
	 * @since 2.7.0
	 *
	 * @param string $position Canonical position key.
	 * @return int[] Ad IDs ordered by menu order, then post ID.
	 */
	public static function query_ids_for_position( string $position ): array {
		if ( ! Ad_Placr_Positions::exists( $position ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Position is the canonical display-location index.
				'meta_key'       => self::META_POSITION,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Position is validated against the canonical registry above.
				'meta_value'     => $position,
				'no_found_rows'  => true,
			)
		);
	}
}
