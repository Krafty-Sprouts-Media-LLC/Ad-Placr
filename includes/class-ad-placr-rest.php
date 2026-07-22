<?php
/**
 * Public REST route for front-end impression/click tracking.
 *
 * @package AdPlacr
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `POST /wp-json/ad-placr/v1/track`.
 *
 * @since 2.5.0
 */
final class Ad_Placr_Rest {

	/**
	 * Hook rest_api_init.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @since 2.5.0
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			'ad-placr/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_track' ),
				'permission_callback' => array( __CLASS__, 'permission_track' ),
				'args'                => array(
					'event'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'ad_id'        => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'placement_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Require a valid WP REST nonce (public; no capability).
	 *
	 * @since 2.5.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function permission_track( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'ad_placr_rest_forbidden',
				__( 'Invalid tracking nonce.', 'ad-placr' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Track callback.
	 *
	 * @since 2.5.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_track( WP_REST_Request $request ) {
		$event        = (string) $request->get_param( 'event' );
		$ad_id        = (int) $request->get_param( 'ad_id' );
		$placement_id = (int) $request->get_param( 'placement_id' );

		if ( null === Ad_Placr_Analytics::normalize_event_type( $event ) || $ad_id < 1 ) {
			return new WP_Error(
				'ad_placr_rest_invalid',
				__( 'Invalid tracking payload.', 'ad-placr' ),
				array( 'status' => 400 )
			);
		}

		$ok = Ad_Placr_Analytics::track( $event, $ad_id, $placement_id );

		return rest_ensure_response(
			array(
				'ok' => $ok,
			)
		);
	}
}
