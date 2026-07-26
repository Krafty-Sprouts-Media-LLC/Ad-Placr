<?php
/**
 * Front-end output for sticky-footer Ads.
 *
 * Prints fixed footer regions on `wp_footer` (priority 100). Output is driven by
 * active unified Ads assigned to `sticky_footer` via `Ad_Placr_Renderer`.
 * Device switching stays CSS-only (no user-agent sniffing).
 *
 * @package AdPlacr
 * @since 0.1.0
 */

/**
 * Renders floating footer Ad regions from unified Ad records.
 *
 * @since 0.1.0
 */
final class Ad_Placr_Footer_Sticky {

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 100 );
	}

	/**
	 * Enqueue front-end styles when a displayable sticky-footer Ad exists.
	 *
	 * Responsive slot CSS is emitted by the renderer for the selected version,
	 * so this path no longer scans all candidate creative metadata.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() || ! self::should_output() ) {
			return;
		}

		wp_enqueue_style(
			'ad-placr-footer-sticky',
			AD_PLACR_PLUGIN_URL . 'assets/css/footer-sticky.css',
			array(),
			AD_PLACR_VERSION
		);
	}

	/**
	 * Whether any active sticky-footer Ad should display on this request.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private static function should_output(): bool {
		return ! empty( self::get_displayable_ad_ids() );
	}

	/**
	 * Active sticky-footer Ad IDs that pass targeting for this request.
	 *
	 * @since 2.1.0
	 *
	 * @return int[]
	 */
	private static function get_displayable_ad_ids(): array {
		$ids = Ad_Placr_Ad::query_ids_for_position( Ad_Placr_Positions::STICKY_FOOTER );
		$ctx = Ad_Placr_Targeting::build_request_context();
		$out = array();

		foreach ( $ids as $ad_id ) {
			if ( Ad_Placr_Targeting::should_display( $ad_id, $ctx ) ) {
				$out[] = $ad_id;
			}
		}

		return $out;
	}

	/**
	 * Print markup for every matching sticky-footer Ad.
	 *
	 * Each wrapper ends in its Ad ID so several matching records can coexist
	 * without duplicate DOM IDs or cross-scoped responsive CSS.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( is_admin() ) {
			return;
		}

		$ad_ids = self::get_displayable_ad_ids();
		if ( empty( $ad_ids ) ) {
			return;
		}

		$context = array( 'ad_ids' => $ad_ids );

		/**
		 * Filter whether the footer sticky Ads should render.
		 *
		 * @since 0.1.0
		 *
		 * @param bool                 $display Whether to display the Ads.
		 * @param array<string, mixed> $context Request context (`ad_ids` int[]).
		 */
		$display = apply_filters( 'ad_placr_footer_sticky_should_display', true, $context );

		if ( true !== $display ) {
			return;
		}

		$breakpoint = self::resolve_mobile_breakpoint( $context );
		$html       = Ad_Placr_Frontend::with_mobile_breakpoint(
			$breakpoint,
			static function () use ( $ad_ids ): string {
				$output = '';

				/*
				 * Preserve query order while the outer footer region owns fixed
				 * positioning. Child wrappers keep unique responsive selectors.
				 */
				foreach ( $ad_ids as $ad_id ) {
					$output .= Ad_Placr_Renderer::render_ad(
						$ad_id,
						array(
							'dom_id'         => 'ad-placr-footer-sticky-' . $ad_id,
							'modifier_class' => 'ad-placr--footer-sticky-item',
							'echo'           => false,
						)
					);
				}

				return $output;
			}
		);
		$html       = Ad_Placr_Frontend::wrap_sticky_ads( Ad_Placr_Positions::STICKY_FOOTER, $html );

		if ( '' === $html ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network code; stored by privileged users.
		echo $html;
	}

	/**
	 * Resolve the CSS max-width breakpoint for mobile versus desktop slots.
	 *
	 * Uses WordPress's standard 782px small-screen breakpoint by default. The
	 * filter lets site code adjust it while responsive output stays renderer-owned.
	 *
	 * @since 0.1.2
	 *
	 * @param array<string, mixed> $context Request context (see render filter).
	 * @return int Breakpoint in pixels (clamped 320-1200).
	 */
	private static function resolve_mobile_breakpoint( array $context ): int {
		$default = 782;

		/**
		 * Filter the max-width breakpoint for switching mobile footer Ad code.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $default Default breakpoint (782).
		 * @param array<string, mixed> $context Request context (`ad_ids` int[]).
		 */
		$breakpoint = (int) apply_filters( 'ad_placr_footer_sticky_mobile_breakpoint', $default, $context );

		if ( $breakpoint < 320 ) {
			$breakpoint = 320;
		} elseif ( $breakpoint > 1200 ) {
			$breakpoint = 1200;
		}

		return $breakpoint;
	}
}
