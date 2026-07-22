<?php
/**
 * Ad creative post type: reusable ad code.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `ad_placr_ad` post type and its meta.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Ad {

	public const POST_TYPE = 'ad_placr_ad';

	public const META_CODE        = '_ad_placr_code';
	public const META_MOBILE_CODE = '_ad_placr_mobile_code';
	public const META_DEVICES     = '_ad_placr_devices';
	public const META_STATUS      = '_ad_placr_status';
	public const META_NOTES       = '_ad_placr_notes';

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
					'menu_name'     => __( 'Ad Placr', 'ad-placr' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-megaphone',
				'menu_position'   => 26,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);

		$string_meta = array( self::META_CODE, self::META_MOBILE_CODE, self::META_STATUS, self::META_NOTES );

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

		register_post_meta(
			self::POST_TYPE,
			self::META_DEVICES,
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

	/**
	 * Normalize a raw status value to a known value. Pure.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $raw Raw status.
	 * @return string 'active' or 'inactive'.
	 */
	public static function normalize_status( $raw ): string {
		return 'active' === strtolower( (string) $raw ) ? 'active' : 'inactive';
	}

	/**
	 * Whether an ad is published and marked active.
	 *
	 * @since 2.0.0
	 *
	 * @param int $ad_id Ad post ID.
	 * @return bool
	 */
	public static function is_active( int $ad_id ): bool {
		if ( self::POST_TYPE !== get_post_type( $ad_id ) ) {
			return false;
		}

		if ( 'publish' !== get_post_status( $ad_id ) ) {
			return false;
		}

		return 'active' === self::normalize_status( get_post_meta( $ad_id, self::META_STATUS, true ) );
	}
}
