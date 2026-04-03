<?php
/**
 * Front-end output for the footer sticky placement.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

/**
 * Renders the floating footer ad region.
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
	 * Enqueue front-end styles when the placement is active.
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

		$config              = self::get_config();
		$mobile_code         = (string) $config['mobile_code'];
		$has_mobile_override = '' !== trim( $mobile_code );
		$breakpoint          = self::resolve_mobile_breakpoint( $config );

		$inline = self::build_inline_css( $has_mobile_override, $breakpoint );

		if ( '' !== $inline ) {
			wp_add_inline_style( 'ad-placr-footer-sticky', $inline );
		}
	}

	/**
	 * Whether the footer sticky should render.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private static function should_output(): bool {
		if ( ! self::get_config()['enabled'] ) {
			return false;
		}

		$code        = self::get_config()['code'];
		$mobile_code = self::get_config()['mobile_code'];

		if ( '' === trim( (string) $code ) && '' === trim( (string) $mobile_code ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Parsed footer sticky settings.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	private static function get_config(): array {
		$defaults = Ad_Placr_Plugin::default_settings()['footer_sticky'];
		$opt      = get_option( Ad_Placr_Settings_Page::OPTION_NAME, Ad_Placr_Plugin::default_settings() );

		if ( ! is_array( $opt ) || ! isset( $opt['footer_sticky'] ) || ! is_array( $opt['footer_sticky'] ) ) {
			return $defaults;
		}

		return wp_parse_args( $opt['footer_sticky'], $defaults );
	}

	/**
	 * Print markup in the footer.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( is_admin() || ! self::should_output() ) {
			return;
		}

		$config              = self::get_config();
		$code                = (string) $config['code'];
		$mobile_code         = (string) $config['mobile_code'];
		$has_mobile_override = '' !== trim( $mobile_code );

		/**
		 * Filter whether the footer sticky placement should render.
		 *
		 * @since 0.1.0
		 *
		 * @param bool  $display Whether to display the placement.
		 * @param array $config  Footer sticky configuration.
		 */
		$display = apply_filters( 'ad_placr_footer_sticky_should_display', true, $config );

		if ( true !== $display ) {
			return;
		}

		$breakpoint = self::resolve_mobile_breakpoint( $config );

		echo '<div id="ad-placr-footer-sticky" class="ad-placr ad-placr--footer-sticky" data-mobile-max="' . esc_attr( (string) $breakpoint ) . '">';

		if ( $has_mobile_override ) {
			echo '<div class="ad-placr__slot ad-placr__slot--universal">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network HTML/scripts; stored by privileged users.
			echo $code;
			echo '</div>';
			echo '<div class="ad-placr__slot ad-placr__slot--mobile">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network HTML/scripts; stored by privileged users.
			echo $mobile_code;
			echo '</div>';
		} else {
			echo '<div class="ad-placr__slot ad-placr__slot--all">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network HTML/scripts; stored by privileged users.
			echo $code;
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Build scoped CSS for mobile vs desktop slots.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $has_mobile_override Whether a separate mobile snippet is set.
	 * @param int  $breakpoint          Mobile max-width in pixels.
	 * @return string
	 */
	private static function build_inline_css( bool $has_mobile_override, int $breakpoint ): string {
		if ( ! $has_mobile_override ) {
			return '';
		}

		$bp = (int) $breakpoint;

		return sprintf(
			'@media screen and (max-width: %1$dpx){.ad-placr--footer-sticky .ad-placr__slot--universal{display:none !important;}.ad-placr--footer-sticky .ad-placr__slot--mobile{display:block !important;}}' .
			'@media screen and (min-width: %2$dpx){.ad-placr--footer-sticky .ad-placr__slot--universal{display:block !important;}.ad-placr--footer-sticky .ad-placr__slot--mobile{display:none !important;}}',
			$bp,
			$bp + 1
		);
	}

	/**
	 * Resolve the CSS max-width breakpoint for “mobile” vs “desktop” slots.
	 *
	 * Uses WordPress’s standard 782px small-screen breakpoint by default (same order of magnitude as
	 * `wp-admin/css/common.css` responsive rules). Override via the
	 * `ad_placr_footer_sticky_mobile_breakpoint` filter from a theme or companion plugin.
	 *
	 * @since 0.1.2
	 *
	 * @param array<string, mixed> $config Footer sticky configuration.
	 * @return int Breakpoint in pixels (clamped 320–1200).
	 */
	private static function resolve_mobile_breakpoint( array $config ): int {
		$default = 782;

		/**
		 * Filter the max-width breakpoint (pixels) for switching mobile vs universal footer ad code.
		 *
		 * @since 0.1.0
		 *
		 * @param int   $default  Default breakpoint (782).
		 * @param array $config   Footer sticky configuration.
		 */
		$breakpoint = (int) apply_filters( 'ad_placr_footer_sticky_mobile_breakpoint', $default, $config );

		if ( $breakpoint < 320 ) {
			$breakpoint = 320;
		} elseif ( $breakpoint > 1200 ) {
			$breakpoint = 1200;
		}

		return $breakpoint;
	}
}
