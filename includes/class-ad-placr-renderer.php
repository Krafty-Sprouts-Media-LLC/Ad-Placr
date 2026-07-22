<?php
/**
 * Shared front-end renderer: pure HTML/CSS builders for placement wrappers.
 *
 * Pure builders concatenate markup without echoing. Ad network code strings are
 * inserted raw by design (privileged storage); structure attrs and disclosure
 * are escaped. Handlers pass an already-resolved breakpoint into the wrappers.
 *
 * @package AdPlacr
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds placement wrapper markup and scoped mobile-pair CSS.
 *
 * @since 2.1.0
 */
final class Ad_Placr_Renderer {

	/**
	 * Visible slot stack for inline CSS (must match footer/in-content assets).
	 *
	 * @since 2.1.0
	 */
	public const SLOT_VISIBLE_INLINE = 'display:flex !important;flex-direction:column !important;align-items:center !important;justify-content:center !important;width:100% !important;box-sizing:border-box !important;';

	/**
	 * Inner slot divs only: dual universal/mobile when mobile code is non-empty,
	 * otherwise a single --all slot.
	 *
	 * Callers pass privileged ad-network snippets; those strings are not escaped.
	 *
	 * @since 2.1.0
	 *
	 * @param string $code        Universal / desktop ad code (raw).
	 * @param string $mobile_code Mobile override ad code (raw); empty → single slot.
	 * @return string Inner HTML (slot divs only).
	 */
	public static function build_slots_inner_html( string $code, string $mobile_code ): string {
		$has_mobile = '' !== trim( $mobile_code );

		/*
		 * Dual-slot mode keeps both snippets in the DOM; build_mobile_pair_css()
		 * toggles visibility at the breakpoint. Single-slot uses --all (no swap).
		 * Ad network code is concatenated raw — see AGENTS.md §1.4.
		 */
		if ( $has_mobile ) {
			return '<div class="ad-placr__slot ad-placr__slot--universal">' . $code . '</div>'
				. '<div class="ad-placr__slot ad-placr__slot--mobile">' . $mobile_code . '</div>';
		}

		return '<div class="ad-placr__slot ad-placr__slot--all">' . $code . '</div>';
	}

	/**
	 * Full wrapper: outer div + optional disclosure + slot inner HTML.
	 *
	 * Escapes `$dom_id`, `$modifier_class`, and the breakpoint attribute; escapes
	 * disclosure text with esc_html. Does not escape `$code` / `$mobile_code`.
	 *
	 * @since 2.1.0
	 *
	 * @param string $dom_id         Outer element id (e.g. ad-placr-footer-sticky).
	 * @param string $modifier_class BEM modifier (e.g. ad-placr--footer-sticky).
	 * @param int    $breakpoint     Mobile max-width in pixels (for data-mobile-max).
	 * @param string $code           Universal / desktop ad code (raw).
	 * @param string $mobile_code    Mobile override ad code (raw).
	 * @param string $disclosure     Optional label; empty → omitted.
	 * @return string Complete wrapper HTML string.
	 */
	public static function build_wrapper_html(
		string $dom_id,
		string $modifier_class,
		int $breakpoint,
		string $code,
		string $mobile_code,
		string $disclosure
	): string {
		$html  = '<div id="' . esc_attr( $dom_id ) . '" class="ad-placr ' . esc_attr( $modifier_class ) . '" data-mobile-max="' . esc_attr( (string) $breakpoint ) . '">';
		$label = trim( $disclosure );

		if ( '' !== $label ) {
			$html .= '<div class="ad-placr__disclosure">' . esc_html( $label ) . '</div>';
		}

		$html .= self::build_slots_inner_html( $code, $mobile_code );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Scoped media queries for dual universal/mobile slots.
	 *
	 * Mirrors existing footer/in-content flex visibility rules. `$dom_id_selector`
	 * is a CSS selector such as `#ad-placr-footer-sticky` or `#ad-placr-ic-1`.
	 *
	 * @since 2.1.0
	 *
	 * @param string $dom_id_selector CSS selector for the wrapper (usually #id).
	 * @param int    $breakpoint      Mobile max-width in pixels.
	 * @return string Inline CSS string (may be empty only if caller skips dual mode).
	 */
	public static function build_mobile_pair_css( string $dom_id_selector, int $breakpoint ): string {
		$bp       = (int) $breakpoint;
		$selector = $dom_id_selector;
		$visible  = self::SLOT_VISIBLE_INLINE;

		return sprintf(
			'@media screen and (max-width: %1$dpx){%4$s .ad-placr__slot--universal{display:none !important;}%4$s .ad-placr__slot--mobile{%2$s}}' .
			'@media screen and (min-width: %3$dpx){%4$s .ad-placr__slot--universal{%2$s}%4$s .ad-placr__slot--mobile{display:none !important;}}',
			$bp,
			$visible,
			$bp + 1,
			$selector
		);
	}

	/**
	 * Resolve the unified mobile breakpoint (default 782px), clamped to 320–1200.
	 *
	 * Handlers that need legacy filter names should call
	 * `ad_placr_footer_sticky_mobile_breakpoint` / `ad_placr_in_content_mobile_breakpoint`
	 * themselves and pass the result into the builders; this method only fires
	 * `ad_placr_mobile_breakpoint`.
	 *
	 * @since 2.1.0
	 *
	 * @param int $fallback Default breakpoint in pixels.
	 * @return int Clamped breakpoint.
	 */
	public static function resolve_breakpoint( int $fallback = 782 ): int {
		/**
		 * Filter the max-width breakpoint (pixels) for mobile vs universal ad slots.
		 *
		 * @since 2.1.0
		 *
		 * @param int $fallback Default breakpoint (782).
		 */
		$breakpoint = (int) apply_filters( 'ad_placr_mobile_breakpoint', $fallback );

		if ( $breakpoint < 320 ) {
			$breakpoint = 320;
		} elseif ( $breakpoint > 1200 ) {
			$breakpoint = 1200;
		}

		return $breakpoint;
	}

	/**
	 * Pick a weighted active ad for a placement and build wrapper HTML.
	 *
	 * Returns an empty string when the placement/ad is inactive or both codes
	 * are blank. `$args` keys: `dom_id`, `modifier_class`, `breakpoint` (int),
	 * optional `echo` (bool, default false) for optional output.
	 *
	 * @since 2.1.0
	 *
	 * @param int                  $placement_id Placement post ID.
	 * @param array<string, mixed> $args         Wrapper args (see summary).
	 * @return string Wrapper HTML, or empty string when nothing should render.
	 */
	public static function render_placement( int $placement_id, array $args ): string {
		if ( ! Ad_Placr_Placement::is_active( $placement_id ) ) {
			return '';
		}

		$ads = Ad_Placr_Placement::get_ads( $placement_id );
		if ( empty( $ads ) ) {
			return '';
		}

		/*
		 * Weighted pick uses a full-range roll; choose_weighted reduces it modulo
		 * the weight sum so any non-negative int is a valid band selector.
		 */
		$roll  = function_exists( 'wp_rand' ) ? wp_rand( 0, PHP_INT_MAX ) : random_int( 0, PHP_INT_MAX );
		$ad_id = Ad_Placr_Placement::choose_weighted( $ads, $roll );

		if ( null === $ad_id || ! Ad_Placr_Ad::is_active( $ad_id ) ) {
			return '';
		}

		$code        = Ad_Placr_Ad::get_code( $ad_id );
		$mobile_code = Ad_Placr_Ad::get_mobile_code( $ad_id );

		if ( '' === trim( $code ) && '' === trim( $mobile_code ) ) {
			return '';
		}

		$settings   = Ad_Placr_Plugin::get_settings();
		$disclosure = isset( $settings['disclosure_text'] ) ? trim( (string) $settings['disclosure_text'] ) : '';

		$dom_id         = isset( $args['dom_id'] ) ? (string) $args['dom_id'] : '';
		$modifier_class = isset( $args['modifier_class'] ) ? (string) $args['modifier_class'] : '';
		$breakpoint     = isset( $args['breakpoint'] ) ? (int) $args['breakpoint'] : 782;
		$do_echo        = ! empty( $args['echo'] );

		$html = self::build_wrapper_html(
			$dom_id,
			$modifier_class,
			$breakpoint,
			$code,
			$mobile_code,
			$disclosure
		);

		if ( $do_echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network code; stored by privileged users.
			echo $html;
		}

		return $html;
	}
}
