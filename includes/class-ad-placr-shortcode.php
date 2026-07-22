<?php
/**
 * Manual shortcode: [ad_placr placement="…"] / [ad_placr ad="…"].
 *
 * Resolves attributes then delegates to the shared Renderer. Reads ads and
 * placements only through Ad/Placement accessors (same META_* constants as
 * admin writes) — never hard-coded meta key strings.
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
	 * Normalize shortcode attributes into a typed request.
	 *
	 * Pure aside from absint(). When both placement and ad are set, placement
	 * wins so editors can leave a stale ad attr without changing output.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string, mixed>|string $atts Raw shortcode attributes.
	 * @return array{type:string,id:int}|array{} Empty when nothing valid.
	 */
	public static function resolve_request( $atts ): array {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		$placement = isset( $atts['placement'] ) ? absint( $atts['placement'] ) : 0;
		$ad        = isset( $atts['ad'] ) ? absint( $atts['ad'] ) : 0;

		if ( $placement > 0 ) {
			return array(
				'type' => 'placement',
				'id'   => $placement,
			);
		}

		if ( $ad > 0 ) {
			return array(
				'type' => 'ad',
				'id'   => $ad,
			);
		}

		return array();
	}

	/**
	 * BEM modifier class for a resolved request type.
	 *
	 * @since 2.3.0
	 *
	 * @param string $type placement|ad.
	 * @return string Modifier class, or empty for unknown types.
	 */
	public static function modifier_for( string $type ): string {
		return match ( $type ) {
			'placement' => 'ad-placr--manual-shortcode',
			'ad'        => 'ad-placr--manual-ad',
			default     => '',
		};
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
		$req = self::resolve_request( $atts );
		if ( empty( $req ) ) {
			return '';
		}

		$type = $req['type'];
		$id   = $req['id'];
		$args = array(
			'dom_id'         => 'ad-placr-sc-' . $id,
			'modifier_class' => self::modifier_for( $type ),
			'breakpoint'     => Ad_Placr_Renderer::resolve_breakpoint(),
		);

		if ( 'placement' === $type ) {
			$ctx = Ad_Placr_Targeting::build_request_context();
			if ( ! Ad_Placr_Targeting::should_display( $id, $ctx ) ) {
				return '';
			}

			return Ad_Placr_Renderer::render_placement( $id, $args );
		}

		return Ad_Placr_Renderer::render_ad( $id, $args );
	}
}
