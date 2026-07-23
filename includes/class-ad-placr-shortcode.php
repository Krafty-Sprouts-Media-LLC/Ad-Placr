<?php
/**
 * Manual shortcode: [ad_placr ad="…"].
 *
 * Resolves one Ad ID, confirms the manual-shortcode display location, then
 * delegates targeting and output to the shared Ad services.
 *
 * @package AdPlacr
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the ad_placr shortcode.
 *
 * @since 2.3.0
 */
final class Ad_Placr_Shortcode {

	/**
	 * Shortcode tag.
	 *
	 * @since 2.3.0
	 */
	public const TAG = 'ad_placr';

	/**
	 * Hook shortcode registration.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Resolve the one supported Ad ID from shortcode attributes.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string, mixed> $atts Raw shortcode attributes.
	 * @return int Positive Ad ID, or zero when the attribute is missing or invalid.
	 */
	public static function resolve_ad_id( array $atts ): int {
		if ( ! isset( $atts['ad'] ) ) {
			return 0;
		}

		return max( 0, (int) $atts['ad'] );
	}

	/**
	 * Return the manual-shortcode wrapper modifier.
	 *
	 * @since 2.3.0
	 *
	 * @return string BEM modifier class for shortcode output.
	 */
	public static function modifier_class(): string {
		return 'ad-placr--shortcode';
	}

	/**
	 * Shortcode callback — returns HTML (never echoes).
	 *
	 * @since 2.3.0
	 *
	 * @param array<string, mixed>|string $atts    Attributes.
	 * @param string|null                 $content Enclosed content (unused).
	 * @return string Wrapper HTML or empty string.
	 */
	public static function render( $atts, $content = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$ad_id = self::resolve_ad_id( is_array( $atts ) ? $atts : array() );
		if ( $ad_id < 1 ) {
			return '';
		}

		if ( Ad_Placr_Positions::MANUAL_SHORTCODE !== Ad_Placr_Ad::get_position( $ad_id ) ) {
			return '';
		}

		if ( ! Ad_Placr_Targeting::should_display( $ad_id, Ad_Placr_Targeting::build_request_context() ) ) {
			return '';
		}

		return Ad_Placr_Renderer::render_ad(
			$ad_id,
			array(
				'dom_id'         => 'ad-placr-shortcode-' . $ad_id,
				'modifier_class' => self::modifier_class(),
			)
		);
	}
}
