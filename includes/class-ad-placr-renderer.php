<?php
/**
 * Shared front-end renderer: pure HTML/CSS builders for complete Ad wrappers.
 *
 * Pure builders concatenate markup without echoing. Ad network code strings are
 * inserted raw by design (privileged storage); wrapper attributes are escaped.
 * The renderer selects one eligible Ad version for each render request.
 *
 * @package AdPlacr
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Selects complete Ad versions and builds their responsive front-end markup.
 *
 * @since 2.1.0
 */
final class Ad_Placr_Renderer {

	/**
	 * Visible slot stack for responsive inline CSS.
	 *
	 * @since 2.1.0
	 */
	public const SLOT_VISIBLE_INLINE = 'display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;width:100%!important;box-sizing:border-box!important;';

	/**
	 * Build inner slot divs for universal and optional mobile code.
	 *
	 * Callers pass privileged ad-network snippets; those strings are not escaped.
	 *
	 * @since 2.1.0
	 *
	 * @param string $code        Universal / desktop ad code (raw).
	 * @param string $mobile_code Mobile override ad code (raw); empty means one slot.
	 * @return string Inner HTML containing raw trusted code.
	 */
	public static function build_slots_inner_html( string $code, string $mobile_code ): string {
		$has_mobile = '' !== trim( $mobile_code );

		/*
		 * Dual-slot mode keeps both snippets in the DOM. Responsive CSS toggles
		 * their visibility, avoiding unreliable user-agent conditional rendering.
		 */
		if ( $has_mobile ) {
			return '<div class="ad-placr__slot ad-placr__slot--universal">' . $code . '</div>'
				. '<div class="ad-placr__slot ad-placr__slot--mobile">' . $mobile_code . '</div>';
		}

		return '<div class="ad-placr__slot ad-placr__slot--all">' . $code . '</div>';
	}

	/**
	 * Build the complete outer wrapper around trusted slot markup.
	 *
	 * Escapes wrapper identity values but preserves trusted ad-network markup
	 * inside `$inner` for the privileged-user storage exception.
	 *
	 * @since 2.1.0
	 *
	 * @param string $dom_id         Outer element ID (e.g. ad-placr-footer-sticky).
	 * @param string $modifier_class BEM modifier (e.g. ad-placr--pos-sticky_footer).
	 * @param string $inner          Prebuilt slot HTML containing trusted ad code.
	 * @param int    $ad_id          Complete Ad post ID for tracking attributes.
	 * @param string $version_id     Stable selected Ad-version identifier.
	 * @return string Complete wrapper HTML string.
	 */
	public static function build_wrapper_html( string $dom_id, string $modifier_class, string $inner, int $ad_id, string $version_id ): string {
		$html  = '<div id="' . esc_attr( $dom_id ) . '" class="ad-placr ' . esc_attr( $modifier_class ) . '"';
		$html .= ' data-ad-id="' . esc_attr( (string) $ad_id ) . '"';
		$html .= ' data-version-id="' . esc_attr( $version_id ) . '">';
		$html .= $inner;
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build scoped responsive CSS for one selected Ad version.
	 *
	 * Mobile override code replaces universal code only in the Mobile range. The
	 * wrapper remains in the DOM for all ranges so device targeting can use CSS
	 * instead of unreliable user-agent detection.
	 *
	 * @since 2.7.0
	 *
	 * @param string             $selector        Scoped wrapper selector (usually #id).
	 * @param int                $mobile_max      Mobile maximum width in pixels.
	 * @param int                $tablet_max      Tablet maximum width in pixels.
	 * @param array<int, string> $devices         Selected device ranges.
	 * @param bool               $has_mobile_code Whether this version has mobile override code.
	 * @return string Generated scoped inline CSS, or an empty string when unnecessary.
	 */
	public static function build_responsive_css( string $selector, int $mobile_max, int $tablet_max, array $devices, bool $has_mobile_code ): string {
		$mobile_max = max( 320, min( 1023, $mobile_max ) );
		$tablet_max = max( $mobile_max + 1, min( 1600, $tablet_max ) );
		$devices    = self::normalize_devices( $devices );
		$css        = array();

		if ( $has_mobile_code ) {
			$css[] = '@media (max-width:' . $mobile_max . 'px){' . $selector . ' .ad-placr__universal{display:none!important}' . $selector . ' .ad-placr__mobile{' . self::SLOT_VISIBLE_INLINE . '}}';
			$css[] = '@media (min-width:' . ( $mobile_max + 1 ) . 'px){' . $selector . ' .ad-placr__universal{' . self::SLOT_VISIBLE_INLINE . '}' . $selector . ' .ad-placr__mobile{display:none!important}}';
		}

		if ( ! in_array( 'mobile', $devices, true ) ) {
			$css[] = '@media (max-width:' . $mobile_max . 'px){' . $selector . '{display:none!important}}';
		}
		if ( ! in_array( 'tablet', $devices, true ) ) {
			$css[] = '@media (min-width:' . ( $mobile_max + 1 ) . 'px) and (max-width:' . $tablet_max . 'px){' . $selector . '{display:none!important}}';
		}
		if ( ! in_array( 'desktop', $devices, true ) ) {
			$css[] = '@media (min-width:' . ( $tablet_max + 1 ) . 'px){' . $selector . '{display:none!important}}';
		}

		return implode( '', $css );
	}

	/**
	 * Resolve the unified mobile breakpoint, clamped to the phone range.
	 *
	 * @since 2.7.0
	 *
	 * @return int Mobile maximum width in pixels.
	 */
	public static function resolve_mobile_breakpoint(): int {
		/**
		 * Filter the largest viewport width treated as Mobile.
		 *
		 * @since 2.7.0
		 *
		 * @param int $breakpoint Mobile maximum width in pixels.
		 */
		$breakpoint = (int) apply_filters( 'ad_placr_mobile_breakpoint', 782 );

		return max( 320, min( 1023, $breakpoint ) );
	}

	/**
	 * Resolve the unified tablet breakpoint while preserving a non-empty range.
	 *
	 * @since 2.7.0
	 *
	 * @return int Tablet maximum width in pixels.
	 */
	public static function resolve_tablet_breakpoint(): int {
		/**
		 * Filter the largest viewport width treated as Tablet.
		 *
		 * @since 2.7.0
		 *
		 * @param int $breakpoint Tablet maximum width in pixels.
		 */
		$breakpoint = (int) apply_filters( 'ad_placr_tablet_breakpoint', 1024 );

		return max( self::resolve_mobile_breakpoint() + 1, min( 1600, $breakpoint ) );
	}

	/**
	 * Build legacy mobile-pair CSS through the unified responsive builder.
	 *
	 * Retained for unchanged footer and in-content callers until Tasks 3â€“8 move
	 * them to the complete Ad rendering pipeline.
	 *
	 * @since 2.1.0
	 *
	 * @param string $dom_id_selector CSS selector for the wrapper (usually #id).
	 * @param int    $breakpoint      Mobile maximum width in pixels.
	 * @return string Scoped responsive CSS.
	 */
	public static function build_mobile_pair_css( string $dom_id_selector, int $breakpoint ): string {
		return self::build_responsive_css( $dom_id_selector, $breakpoint, max( $breakpoint + 1, 1024 ), array( 'desktop', 'tablet', 'mobile' ), true );
	}

	/**
	 * Resolve the legacy mobile breakpoint API through the unified filter.
	 *
	 * Retained for unchanged callers until Tasks 3â€“8 remove their legacy
	 * breakpoint plumbing. New renderer code uses resolve_mobile_breakpoint().
	 *
	 * @since 2.1.0
	 *
	 * @param int $fallback Default breakpoint in pixels.
	 * @return int Clamped breakpoint.
	 */
	public static function resolve_breakpoint( int $fallback = 782 ): int {
		if ( 782 === $fallback ) {
			return self::resolve_mobile_breakpoint();
		}

		return max( 320, min( 1023, (int) apply_filters( 'ad_placr_mobile_breakpoint', $fallback ) ) );
	}

	/**
	 * Temporarily bridge unchanged placement callers to unified Ad rendering.
	 *
	 * Task 3 moves automatic callers to Ad IDs and Task 8 removes the Placement
	 * runtime. The bridge never reads legacy Ad creative; selected Ads always
	 * render through the unified version path below.
	 *
	 * @since 2.1.0
	 *
	 * @param int                  $placement_id Temporary Placement post ID.
	 * @param array<string, mixed> $args         Wrapper arguments for render_ad().
	 * @return string Wrapper HTML, or an empty string when nothing should render.
	 */
	public static function render_placement( int $placement_id, array $args ): string {
		if ( ! Ad_Placr_Placement::is_active( $placement_id ) ) {
			return '';
		}

		$ads = Ad_Placr_Placement::get_ads( $placement_id );
		if ( empty( $ads ) ) {
			return '';
		}

		$roll  = function_exists( 'wp_rand' ) ? wp_rand( 0, PHP_INT_MAX ) : random_int( 0, PHP_INT_MAX );
		$ad_id = Ad_Placr_Placement::choose_weighted( $ads, $roll );

		if ( null === $ad_id ) {
			return '';
		}

		return self::render_ad( $ad_id, $args );
	}

	/**
	 * Select one eligible weighted version and build a responsive Ad wrapper.
	 *
	 * `$args` may provide a `dom_id`, `modifier_class`, and optional `echo` flag.
	 * The method performs one weighted roll per render and records the selected
	 * stable version ID in the output for analytics.
	 *
	 * @since 2.3.0
	 *
	 * @param int                  $ad_id Ad post ID.
	 * @param array<string, mixed> $args  Wrapper arguments (see summary).
	 * @return string Wrapper HTML, or an empty string when nothing should render.
	 */
	public static function render_ad( int $ad_id, array $args ): string {
		if ( ! Ad_Placr_Ad::is_active( $ad_id ) ) {
			return '';
		}

		$versions = Ad_Placr_Ad::eligible_versions( Ad_Placr_Ad::get_versions( $ad_id ) );
		if ( empty( $versions ) ) {
			return '';
		}

		$total   = array_sum( array_column( $versions, 'weight' ) );
		$roll    = 1 === $total ? 0 : wp_rand( 0, $total - 1 );
		$version = Ad_Placr_Ad::choose_weighted_version( $versions, $roll );
		if ( null === $version ) {
			return '';
		}

		$dom_id          = isset( $args['dom_id'] ) ? sanitize_html_class( (string) $args['dom_id'] ) : 'ad-placr-' . $ad_id;
		$modifier        = isset( $args['modifier_class'] ) ? (string) $args['modifier_class'] : '';
		$targeting       = Ad_Placr_Ad::get_targeting( $ad_id );
		$devices         = self::normalize_devices( $targeting['devices'] ?? array() );
		$mobile_max      = self::resolve_mobile_breakpoint();
		$tablet_max      = self::resolve_tablet_breakpoint();
		$inner           = self::build_slots_inner_html( $version['code'], $version['mobile_code'] );
		$has_mobile_code = '' !== trim( $version['mobile_code'] );
		$css             = self::build_responsive_css( '#' . $dom_id, $mobile_max, $tablet_max, $devices, $has_mobile_code );
		$html            = '' !== $css ? '<style id="' . esc_attr( $dom_id . '-responsive' ) . '">' . $css . '</style>' : '';
		$html           .= self::build_wrapper_html( $dom_id, $modifier, $inner, $ad_id, $version['version_id'] );

		if ( ! empty( $args['echo'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network code; stored by privileged users.
			echo $html;
		}

		return $html;
	}

	/**
	 * Normalize device targeting to supported viewport ranges.
	 *
	 * Empty or invalid targeting defaults to all ranges so malformed saved data
	 * cannot accidentally suppress an otherwise eligible Ad.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $devices Raw device targeting value.
	 * @return array<int, string> Supported device ranges in canonical order.
	 */
	private static function normalize_devices( $devices ): array {
		$allowed = array( 'desktop', 'tablet', 'mobile' );
		$devices = is_array( $devices ) ? array_map( 'strval', $devices ) : array();
		$devices = array_values( array_intersect( $allowed, $devices ) );

		return empty( $devices ) ? $allowed : $devices;
	}
}
