<?php
/**
 * Placement post type: a position + targeting + weighted ad list.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `ad_placr_placement` post type and holds placement logic.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Placement {

	public const POST_TYPE = 'ad_placr_placement';

	public const META_POSITION  = '_ad_placr_position';
	public const META_STATUS    = '_ad_placr_status';
	public const META_TARGETING = '_ad_placr_targeting';
	public const META_ADS       = '_ad_placr_ads';

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
					'name'          => __( 'Placements', 'ad-placr' ),
					'singular_name' => __( 'Placement', 'ad-placr' ),
					'add_new_item'  => __( 'Add New Placement', 'ad-placr' ),
					'edit_item'     => __( 'Edit Placement', 'ad-placr' ),
					'menu_name'     => __( 'Placements', 'ad-placr' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . Ad_Placr_Ad::POST_TYPE,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_POSITION,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_STATUS,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		foreach ( array( self::META_TARGETING, self::META_ADS ) as $key ) {
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
	 * Normalize a raw ad list into `{ad_id:int, weight:int}` rows. Pure.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $raw Raw meta value.
	 * @return array<int, array{ad_id:int, weight:int}>
	 */
	public static function normalize_ads( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['ad_id'] ) ) {
				continue;
			}

			$ad_id = (int) $row['ad_id'];
			if ( $ad_id <= 0 ) {
				continue;
			}

			$weight = isset( $row['weight'] ) ? (int) $row['weight'] : 1;
			$weight = max( 1, $weight );

			$out[] = array(
				'ad_id'  => $ad_id,
				'weight' => $weight,
			);
		}

		return $out;
	}

	/**
	 * Deterministic weighted selection. Pure.
	 *
	 * Roll is taken modulo the sum of positive weights so callers can pass any
	 * non-negative integer (e.g. a random draw or hash) and still land in a band.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, array{ad_id:int, weight:int}> $weighted Weighted rows.
	 * @param int                                      $roll     Non-negative roll value.
	 * @return int|null Selected ad ID, or null when no positive weight exists.
	 */
	public static function choose_weighted( array $weighted, int $roll ): ?int {
		$total = 0;
		foreach ( $weighted as $row ) {
			$total += max( 0, (int) $row['weight'] );
		}

		if ( $total <= 0 ) {
			return null;
		}

		$roll = ( ( $roll % $total ) + $total ) % $total;

		$cursor = 0;
		foreach ( $weighted as $row ) {
			$weight = max( 0, (int) $row['weight'] );
			if ( $weight <= 0 ) {
				continue;
			}

			$cursor += $weight;
			if ( $roll < $cursor ) {
				return (int) $row['ad_id'];
			}
		}

		return null;
	}

	/**
	 * Read and normalize a placement's ad list.
	 *
	 * @since 2.0.0
	 *
	 * @param int $placement_id Placement post ID.
	 * @return array<int, array{ad_id:int, weight:int}>
	 */
	public static function get_ads( int $placement_id ): array {
		return self::normalize_ads( get_post_meta( $placement_id, self::META_ADS, true ) );
	}

	/**
	 * Normalize placement status. Pure.
	 *
	 * @since 2.1.0
	 *
	 * @param mixed $raw Raw status.
	 * @return string
	 */
	public static function normalize_status( $raw ): string {
		return 'active' === strtolower( (string) $raw ) ? 'active' : 'inactive';
	}

	/**
	 * Whether targeting allows this singular post type. Pure.
	 *
	 * `contexts` containing `all` always matches. `singular` requires `$post_type`
	 * to be listed in `post_types` (empty allow-list → no match).
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, mixed> $targeting Targeting blob.
	 * @param string               $post_type Current post type.
	 * @return bool
	 */
	public static function targeting_matches_singular( array $targeting, string $post_type ): bool {
		$contexts = isset( $targeting['contexts'] ) && is_array( $targeting['contexts'] )
			? $targeting['contexts']
			: array();

		if ( in_array( 'all', $contexts, true ) ) {
			return true;
		}

		if ( ! in_array( 'singular', $contexts, true ) ) {
			return false;
		}

		$types = isset( $targeting['post_types'] ) && is_array( $targeting['post_types'] )
			? $targeting['post_types']
			: array();

		return in_array( $post_type, $types, true );
	}

	/**
	 * Whether a placement is published and marked active.
	 *
	 * @since 2.1.0
	 *
	 * @param int $placement_id Placement post ID.
	 * @return bool
	 */
	public static function is_active( int $placement_id ): bool {
		if ( self::POST_TYPE !== get_post_type( $placement_id ) ) {
			return false;
		}

		if ( 'publish' !== get_post_status( $placement_id ) ) {
			return false;
		}

		return 'active' === self::normalize_status( get_post_meta( $placement_id, self::META_STATUS, true ) );
	}

	/**
	 * Canonical position key for a placement.
	 *
	 * @since 2.1.0
	 *
	 * @param int $id Placement post ID.
	 * @return string Position key, or empty string when unset.
	 */
	public static function get_position( int $id ): string {
		return (string) get_post_meta( $id, self::META_POSITION, true );
	}

	/**
	 * Targeting blob for a placement.
	 *
	 * @since 2.1.0
	 *
	 * @param int $id Placement post ID.
	 * @return array<string, mixed>
	 */
	public static function get_targeting( int $id ): array {
		$raw = get_post_meta( $id, self::META_TARGETING, true );

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Published placement IDs assigned to a canonical position key.
	 *
	 * Thin `get_posts` wrapper — no status/active filter here; callers use
	 * `is_active()` (and targeting) after this query.
	 *
	 * @since 2.1.0
	 *
	 * @param string $position_key Canonical position taxonomy key.
	 * @return int[]
	 */
	public static function query_ids_for_position( string $position_key ): array {
		$ids = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Position lookup by design.
					array(
						'key'   => self::META_POSITION,
						'value' => $position_key,
					),
				),
			)
		);

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map( 'intval', $ids );
	}
}
