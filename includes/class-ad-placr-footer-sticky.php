<?php
/**
 * Front-end output for the sticky footer placement.
 *
 * Prints a fixed footer region on `wp_footer` (priority 100). Output is driven by
 * active `sticky_footer` Placement CPTs via `Ad_Placr_Renderer` — not option ad code.
 * Device switching stays CSS-only (no user-agent sniffing).
 *
 * @package AdPlacr
 * @since 0.1.0
 */

/**
 * Renders the floating footer ad region from CPT placements.
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
	 * Enqueue front-end styles when a displayable sticky_footer placement exists.
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

		$placement_ids = self::get_displayable_placement_ids();
		$context       = array( 'placement_ids' => $placement_ids );
		$breakpoint    = self::resolve_mobile_breakpoint( $context );

		/*
		 * Practical approach: if any candidate placement lists an ad with mobile
		 * override code, enqueue dual-slot CSS for the single footer band id.
		 * The eventual weighted pick may or may not use that ad.
		 */
		if ( self::candidates_need_mobile_css( $placement_ids ) ) {
			$inline = Ad_Placr_Renderer::build_mobile_pair_css( '#ad-placr-footer-sticky', $breakpoint );
			if ( '' !== $inline ) {
				wp_add_inline_style( 'ad-placr-footer-sticky', $inline );
			}
		}
	}

	/**
	 * Whether any active sticky_footer placement should display on this request.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private static function should_output(): bool {
		return ! empty( self::get_displayable_placement_ids() );
	}

	/**
	 * Active sticky_footer placement IDs that pass targeting for this request.
	 *
	 * @since 2.1.0
	 *
	 * @return int[]
	 */
	private static function get_displayable_placement_ids(): array {
		$ids = Ad_Placr_Placement::query_ids_for_position( Ad_Placr_Positions::STICKY_FOOTER );
		$out = array();

		foreach ( $ids as $placement_id ) {
			if ( self::placement_should_display( $placement_id ) ) {
				$out[] = $placement_id;
			}
		}

		return $out;
	}

	/**
	 * Whether a sticky_footer placement may output on the current front request.
	 *
	 * `contexts` containing `all` allows any front request. Otherwise require a
	 * singular view whose post type matches targeting.
	 *
	 * @since 2.1.0
	 *
	 * @param int $placement_id Placement post ID.
	 * @return bool
	 */
	private static function placement_should_display( int $placement_id ): bool {
		if ( ! Ad_Placr_Placement::is_active( $placement_id ) ) {
			return false;
		}

		$targeting = Ad_Placr_Placement::get_targeting( $placement_id );
		$contexts  = isset( $targeting['contexts'] ) && is_array( $targeting['contexts'] )
			? $targeting['contexts']
			: array();

		if ( in_array( 'all', $contexts, true ) ) {
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post_type = get_post_type();

		return is_string( $post_type ) && Ad_Placr_Placement::targeting_matches_singular( $targeting, $post_type );
	}

	/**
	 * Whether any displayable candidate has an ad with non-empty mobile code.
	 *
	 * @since 2.1.0
	 *
	 * @param int[] $placement_ids Displayable placement IDs.
	 * @return bool
	 */
	private static function candidates_need_mobile_css( array $placement_ids ): bool {
		foreach ( $placement_ids as $placement_id ) {
			$ads = Ad_Placr_Placement::get_ads( $placement_id );
			foreach ( $ads as $row ) {
				$ad_id = (int) $row['ad_id'];
				if ( $ad_id <= 0 ) {
					continue;
				}
				if ( '' !== trim( Ad_Placr_Ad::get_mobile_code( $ad_id ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Print markup in the footer — first non-empty sticky_footer placement only.
	 *
	 * A single DOM id (`ad-placr-footer-sticky`) is reserved for one footer band.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( is_admin() ) {
			return;
		}

		$placement_ids = self::get_displayable_placement_ids();
		if ( empty( $placement_ids ) ) {
			return;
		}

		$context = array( 'placement_ids' => $placement_ids );

		/**
		 * Filter whether the footer sticky placement should render.
		 *
		 * @since 0.1.0
		 *
		 * @param bool                 $display Whether to display the placement.
		 * @param array<string, mixed> $context Request context (`placement_ids` int[]).
		 */
		$display = apply_filters( 'ad_placr_footer_sticky_should_display', true, $context );

		if ( true !== $display ) {
			return;
		}

		$breakpoint = self::resolve_mobile_breakpoint( $context );

		/*
		 * One sticky footer band only: walk candidates in query order and stop at
		 * the first placement that returns non-empty renderer HTML.
		 */
		foreach ( $placement_ids as $placement_id ) {
			$html = Ad_Placr_Renderer::render_placement(
				$placement_id,
				array(
					'dom_id'         => 'ad-placr-footer-sticky',
					'modifier_class' => 'ad-placr--footer-sticky',
					'breakpoint'     => $breakpoint,
					'echo'           => false,
				)
			);

			if ( '' === $html ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network code; stored by privileged users.
			echo $html;
			return;
		}
	}

	/**
	 * Resolve the CSS max-width breakpoint for “mobile” vs “desktop” slots.
	 *
	 * Uses WordPress’s standard 782px small-screen breakpoint by default. Override via the
	 * `ad_placr_footer_sticky_mobile_breakpoint` filter from a theme or companion plugin.
	 *
	 * @since 0.1.2
	 *
	 * @param array<string, mixed> $context Request context (see render filter).
	 * @return int Breakpoint in pixels (clamped 320–1200).
	 */
	private static function resolve_mobile_breakpoint( array $context ): int {
		$default = 782;

		/**
		 * Filter the max-width breakpoint (pixels) for switching mobile vs universal footer ad code.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $default Default breakpoint (782).
		 * @param array<string, mixed> $context Request context (`placement_ids` int[]).
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
