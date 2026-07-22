<?php
/**
 * One-time migration from the legacy option to Ad/Placement post types.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts `ad_placr_settings` (footer sticky + in-content slots) into posts.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Migration {

	public const DB_VERSION        = 1;
	public const OPTION_DB_VERSION = 'ad_placr_db_version';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 20 );
	}

	/**
	 * Run migration once when the stored DB version is behind.
	 *
	 * Bumps `ad_placr_db_version` only when `run()` succeeds. Failed inserts leave
	 * the option unchanged so a later request can retry (version-only idempotency).
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

		if ( self::run() ) {
			update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
		}
	}

	/**
	 * Persist the built definitions as Ad + Placement posts.
	 *
	 * Success rules: if `build_definitions` yields N ads/placements, every insert
	 * must succeed; if N=0 (nothing to migrate), returns true so the version still bumps.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True when migration completed (or had nothing to do); false if any required insert failed.
	 */
	public static function run(): bool {
		$settings = Ad_Placr_Plugin::get_settings();
		$defs     = self::build_definitions( $settings );

		$expected_ads        = count( $defs['ads'] );
		$expected_placements = count( $defs['placements'] );

		// Nothing to migrate still counts as success so the DB version advances.
		if ( 0 === $expected_ads && 0 === $expected_placements ) {
			return true;
		}

		$ad_ids             = array();
		$placements_created = 0;

		foreach ( $defs['ads'] as $ad ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => Ad_Placr_Ad::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $ad['title'],
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return false;
			}

			update_post_meta( $post_id, Ad_Placr_Ad::META_CODE, $ad['code'] );
			update_post_meta( $post_id, Ad_Placr_Ad::META_MOBILE_CODE, $ad['mobile_code'] );
			update_post_meta( $post_id, Ad_Placr_Ad::META_STATUS, 'active' );

			$ad_ids[ $ad['key'] ] = (int) $post_id;
		}

		foreach ( $defs['placements'] as $placement ) {
			if ( ! isset( $ad_ids[ $placement['ad_key'] ] ) ) {
				return false;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => Ad_Placr_Placement::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $placement['title'],
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return false;
			}

			update_post_meta( $post_id, Ad_Placr_Placement::META_POSITION, $placement['position'] );
			update_post_meta( $post_id, Ad_Placr_Placement::META_STATUS, 'active' );

			/*
			 * Paragraph targeting lives on the placement targeting blob (not a separate meta key)
			 * so the renderer can read one structure. Only set when migrating in-content slots.
			 */
			$targeting = $placement['targeting'];
			if ( null !== $placement['paragraph'] ) {
				$targeting['paragraph'] = $placement['paragraph'];
			}

			update_post_meta( $post_id, Ad_Placr_Placement::META_TARGETING, $targeting );
			update_post_meta(
				$post_id,
				Ad_Placr_Placement::META_ADS,
				array(
					array(
						'ad_id'  => $ad_ids[ $placement['ad_key'] ],
						'weight' => 1,
					),
				)
			);

			++$placements_created;
		}

		return count( $ad_ids ) === $expected_ads && $placements_created === $expected_placements;
	}

	/**
	 * Build ad + placement definitions from legacy settings. Pure.
	 *
	 * Maps footer sticky → sticky_footer and each enabled in-content slot →
	 * in_content_before/after_paragraph. Skips disabled or empty creatives so
	 * inactive legacy config does not become published posts.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $settings Legacy `ad_placr_settings` array.
	 * @return array{ads: array<int, array<string, mixed>>, placements: array<int, array<string, mixed>>}
	 */
	public static function build_definitions( array $settings ): array {
		$defs = array(
			'ads'        => array(),
			'placements' => array(),
		);

		$fs = isset( $settings['footer_sticky'] ) && is_array( $settings['footer_sticky'] ) ? $settings['footer_sticky'] : array();

		$fs_code   = (string) ( $fs['code'] ?? '' );
		$fs_mobile = (string) ( $fs['mobile_code'] ?? '' );

		if ( ! empty( $fs['enabled'] ) && ( '' !== trim( $fs_code ) || '' !== trim( $fs_mobile ) ) ) {
			$key = 'footer_sticky';

			$defs['ads'][] = array(
				'key'         => $key,
				'title'       => 'Footer sticky',
				'code'        => $fs_code,
				'mobile_code' => $fs_mobile,
			);

			$defs['placements'][] = array(
				'title'     => 'Footer sticky',
				'position'  => Ad_Placr_Positions::STICKY_FOOTER,
				'ad_key'    => $key,
				'paragraph' => null,
				'targeting' => array(
					'post_types' => array(),
					'contexts'   => array( 'all' ),
					'devices'    => array( 'desktop', 'tablet', 'mobile' ),
				),
			);
		}

		$slots = isset( $settings['in_content_slots'] ) && is_array( $settings['in_content_slots'] ) ? $settings['in_content_slots'] : array();

		foreach ( $slots as $index => $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}

			$code   = (string) ( $slot['code'] ?? '' );
			$mobile = (string) ( $slot['mobile_code'] ?? '' );

			if ( empty( $slot['enabled'] ) || ( '' === trim( $code ) && '' === trim( $mobile ) ) ) {
				continue;
			}

			$key = 'in_content_' . (string) ( $slot['id'] ?? $index );

			$defs['ads'][] = array(
				'key'         => $key,
				'title'       => '' !== (string) ( $slot['title'] ?? '' ) ? (string) $slot['title'] : 'In-content ad',
				'code'        => $code,
				'mobile_code' => $mobile,
			);

			$position = 'before' === ( $slot['position'] ?? 'after' )
				? Ad_Placr_Positions::IN_CONTENT_BEFORE_PARAGRAPH
				: Ad_Placr_Positions::IN_CONTENT_AFTER_PARAGRAPH;

			$post_types = isset( $slot['post_types'] ) && is_array( $slot['post_types'] ) ? array_values( $slot['post_types'] ) : array();

			$defs['placements'][] = array(
				'title'     => '' !== (string) ( $slot['title'] ?? '' ) ? (string) $slot['title'] : 'In-content placement',
				'position'  => $position,
				'ad_key'    => $key,
				'paragraph' => (int) ( $slot['paragraph_index'] ?? 1 ),
				'targeting' => array(
					'post_types' => $post_types,
					'contexts'   => array( 'singular' ),
					'devices'    => array( 'desktop', 'tablet', 'mobile' ),
				),
			);
		}

		return $defs;
	}
}
