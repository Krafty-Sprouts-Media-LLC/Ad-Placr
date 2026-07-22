<?php
/**
 * In-content placements: multiple slots before/after numbered paragraphs (HTML <p> blocks).
 *
 * Hooks `the_content` at priority 12. Slots are collected into before/after maps keyed by
 * 1-based paragraph index, then injected in one walk over `<p>…</p>` chunks so several
 * slots can target the same paragraph without re-parsing the HTML repeatedly.
 *
 * @package AdPlacr
 * @since 0.1.6
 */

/**
 * Injects one or more ad regions into post content via a single paragraph-block pass.
 *
 * @since 0.1.6
 */
final class Ad_Placr_In_Content {

	/**
	 * Visible slot stack for inline CSS (must match assets/css/in-content.css).
	 *
	 * @since 0.1.6
	 */
	private const SLOT_VISIBLE_INLINE = 'display:flex !important;flex-direction:column !important;align-items:center !important;justify-content:center !important;width:100% !important;box-sizing:border-box !important;';

	/**
	 * Register hooks.
	 *
	 * @since 0.1.6
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 12 );
	}

	/**
	 * Enqueue styles when any active slot may render.
	 *
	 * @since 0.1.6
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() || ! self::should_enqueue_context() ) {
			return;
		}

		wp_enqueue_style(
			'ad-placr-in-content',
			AD_PLACR_PLUGIN_URL . 'assets/css/in-content.css',
			array(),
			AD_PLACR_VERSION
		);

		$inline = self::collect_inline_css_for_slots( self::get_active_slots_for_request() );

		if ( '' !== $inline ) {
			wp_add_inline_style( 'ad-placr-in-content', $inline );
		}
	}

	/**
	 * Whether any slot could run on this request (singular + post type + has code).
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private static function should_enqueue_context(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		foreach ( self::get_slots() as $slot ) {
			if ( self::slot_has_output( $slot ) && self::slot_matches_post_type( $slot ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * All slots from settings (after migration).
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_slots(): array {
		$settings = Ad_Placr_Plugin::get_settings();
		$slots    = isset( $settings['in_content_slots'] ) && is_array( $settings['in_content_slots'] ) ? $settings['in_content_slots'] : array();

		return $slots;
	}

	/**
	 * Slots that are enabled, have code, and match current post type.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_active_slots_for_request(): array {
		$out = array();

		foreach ( self::get_slots() as $slot ) {
			if ( ! self::slot_has_output( $slot ) ) {
				continue;
			}

			if ( ! self::slot_matches_post_type( $slot ) ) {
				continue;
			}

			$out[] = $slot;
		}

		return $out;
	}

	/**
	 * Whether a slot is enabled and has at least one non-empty ad snippet.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $slot Slot config.
	 * @return bool
	 */
	private static function slot_has_output( array $slot ): bool {
		if ( empty( $slot['enabled'] ) ) {
			return false;
		}

		$c = isset( $slot['code'] ) ? trim( (string) $slot['code'] ) : '';
		$m = isset( $slot['mobile_code'] ) ? trim( (string) $slot['mobile_code'] ) : '';

		return '' !== $c || '' !== $m;
	}

	/**
	 * Whether the slot’s allowed post types include the current singular post type.
	 *
	 * Empty `post_types` means “match nothing” — admins must opt in per type.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $slot Slot config.
	 * @return bool
	 */
	private static function slot_matches_post_type( array $slot ): bool {
		$pt = get_post_type();
		if ( ! is_string( $pt ) ) {
			return false;
		}

		$types = isset( $slot['post_types'] ) && is_array( $slot['post_types'] ) ? $slot['post_types'] : array();

		return in_array( $pt, $types, true );
	}

	/**
	 * Build concatenated per-slot mobile override CSS (scoped by wrapper id).
	 *
	 * Only slots with *both* universal and mobile code need media queries. CSS is
	 * scoped to `#ad-placr-ic-{id}` so breakpoints never leak across slots.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $slots Active slots.
	 * @return string
	 */
	private static function collect_inline_css_for_slots( array $slots ): string {
		$css = '';

		foreach ( $slots as $slot ) {
			$univ   = isset( $slot['code'] ) ? trim( (string) $slot['code'] ) : '';
			$mobile = isset( $slot['mobile_code'] ) ? trim( (string) $slot['mobile_code'] ) : '';
			if ( '' === $mobile || '' === $univ ) {
				continue;
			}

			$sid        = self::slot_dom_id( $slot );
			$breakpoint = self::resolve_mobile_breakpoint( $slot );
			$bp         = (int) $breakpoint;

			$css .= sprintf(
				'@media screen and (max-width: %1$dpx){#%4$s .ad-placr__slot--universal{display:none !important;}#%4$s .ad-placr__slot--mobile{%2$s}}' .
				'@media screen and (min-width: %3$dpx){#%4$s .ad-placr__slot--universal{%2$s}#%4$s .ad-placr__slot--mobile{display:none !important;}}',
				$bp,
				self::SLOT_VISIBLE_INLINE,
				$bp + 1,
				$sid
			);
		}

		return $css;
	}

	/**
	 * HTML-safe id for the outer wrapper.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $slot Slot config.
	 * @return string
	 */
	private static function slot_dom_id( array $slot ): string {
		$id = isset( $slot['id'] ) ? (string) $slot['id'] : '';
		$id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $id );

		if ( '' === $id ) {
			$id = 'ic_auto';
		}

		return 'ad-placr-ic-' . $id;
	}

	/**
	 * Insert all applicable slots into post content.
	 *
	 * Guards: main loop only (avoids widgets / secondary queries), no feeds, singular
	 * views only. Global + per-slot filters can short-circuit without touching HTML.
	 *
	 * @since 0.1.6
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_content( string $content ): string {
		// Avoid injecting into admin previews of secondary loops, widgets, or block renders outside the main query.
		if ( is_admin() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( is_feed() ) {
			return $content;
		}

		if ( ! is_singular() ) {
			return $content;
		}

		/**
		 * Filter whether any in-content injection runs on this request.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $inject Whether to run paragraph injection.
		 */
		$inject = apply_filters( 'ad_placr_in_content_should_inject', true );

		if ( true !== $inject ) {
			return $content;
		}

		$slots = self::get_active_slots_for_request();

		if ( empty( $slots ) ) {
			return $content;
		}

		$post_id = get_the_ID();

		/*
		 * Bucket slot HTML by paragraph index first, then inject once.
		 * Re-walking the content per slot would renumber paragraphs after each insert
		 * and break “after paragraph 3” when another slot already inserted above it.
		 */
		$before_by_para = array();
		$after_by_para  = array();

		foreach ( $slots as $slot ) {
			/**
			 * Filter whether this in-content slot should output for the current post.
			 *
			 * @since 1.1.0
			 *
			 * @param bool  $show    Whether to show this slot.
			 * @param array $slot    Slot configuration.
			 * @param int   $post_id Current post ID.
			 */
			$show = apply_filters( 'ad_placr_in_content_slot_should_display', true, $slot, $post_id );

			if ( true !== $show ) {
				continue;
			}

			$html = self::build_wrapper_html( $slot );

			if ( '' === $html ) {
				continue;
			}

			$n = isset( $slot['paragraph_index'] ) ? max( 1, min( 100, absint( $slot['paragraph_index'] ) ) ) : 1;
			$p = isset( $slot['position'] ) && 'before' === $slot['position'] ? 'before' : 'after';

			if ( 'before' === $p ) {
				if ( ! isset( $before_by_para[ $n ] ) ) {
					$before_by_para[ $n ] = array();
				}
				$before_by_para[ $n ][] = $html;
			} else {
				if ( ! isset( $after_by_para[ $n ] ) ) {
					$after_by_para[ $n ] = array();
				}
				$after_by_para[ $n ][] = $html;
			}
		}

		if ( empty( $before_by_para ) && empty( $after_by_para ) ) {
			return $content;
		}

		return self::inject_by_paragraph_blocks( $content, $before_by_para, $after_by_para );
	}

	/**
	 * Walk `<p>…</p>` chunks and inject HTML before/after numbered paragraphs.
	 *
	 * Splits with PREG_SPLIT_DELIM_CAPTURE so paragraph tags and the text between them
	 * stay as separate parts. Only parts that look like a `<p>` increment the counter —
	 * headings, lists, and Gutenberg wrappers between paragraphs are left untouched.
	 * If no `<p>` blocks exist, content is returned unchanged (no fallback insert).
	 *
	 * @since 1.1.0
	 *
	 * @param string               $content        HTML content.
	 * @param array<int, string[]> $before_by_para 1-based paragraph index => HTML strings.
	 * @param array<int, string[]> $after_by_para  1-based paragraph index => HTML strings.
	 * @return string
	 */
	private static function inject_by_paragraph_blocks( string $content, array $before_by_para, array $after_by_para ): string {
		$parts = preg_split( '/(<p[^>]*>.*?<\/p>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		if ( empty( $parts ) ) {
			return $content;
		}

		$out      = '';
		$para_num = 0;

		foreach ( $parts as $part ) {
			if ( preg_match( '/<p[^>]*>/i', $part ) ) {
				++$para_num;

				if ( isset( $before_by_para[ $para_num ] ) ) {
					$out .= implode( '', $before_by_para[ $para_num ] );
				}

				$out .= $part;

				if ( isset( $after_by_para[ $para_num ] ) ) {
					$out .= implode( '', $after_by_para[ $para_num ] );
				}
			} else {
				$out .= $part;
			}
		}

		if ( 0 === $para_num ) {
			return $content;
		}

		return $out;
	}

	/**
	 * Build outer HTML for one slot.
	 *
	 * @since 0.1.6
	 *
	 * @param array<string, mixed> $slot Slot configuration.
	 * @return string
	 */
	private static function build_wrapper_html( array $slot ): string {
		$code                = isset( $slot['code'] ) ? (string) $slot['code'] : '';
		$mobile_code         = isset( $slot['mobile_code'] ) ? (string) $slot['mobile_code'] : '';
		$has_mobile_override = '' !== trim( $mobile_code );
		$has_universal       = '' !== trim( $code );
		$breakpoint          = self::resolve_mobile_breakpoint( $slot );
		$dom_id              = self::slot_dom_id( $slot );

		ob_start();

		echo '<div id="' . esc_attr( $dom_id ) . '" class="ad-placr ad-placr--in-content" data-mobile-max="' . esc_attr( (string) $breakpoint ) . '">';

		if ( $has_mobile_override && $has_universal ) {
			echo '<div class="ad-placr__slot ad-placr__slot--universal">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network HTML/scripts; stored by privileged users.
			echo $code;
			echo '</div>';
			echo '<div class="ad-placr__slot ad-placr__slot--mobile">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $mobile_code;
			echo '</div>';
		} else {
			$out_code = $has_universal ? $code : $mobile_code;
			echo '<div class="ad-placr__slot ad-placr__slot--all">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $out_code;
			echo '</div>';
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Resolve mobile breakpoint (782px default).
	 *
	 * @since 0.1.6
	 *
	 * @param array<string, mixed> $slot Slot configuration.
	 * @return int
	 */
	private static function resolve_mobile_breakpoint( array $slot ): int {
		$default = 782;

		/**
		 * Filter max-width breakpoint (px) for in-content mobile vs universal slots.
		 *
		 * @since 0.1.6
		 *
		 * @param int   $default Default breakpoint (782).
		 * @param array $slot    Slot configuration.
		 */
		$breakpoint = (int) apply_filters( 'ad_placr_in_content_mobile_breakpoint', $default, $slot );

		if ( $breakpoint < 320 ) {
			$breakpoint = 320;
		} elseif ( $breakpoint > 1200 ) {
			$breakpoint = 1200;
		}

		return $breakpoint;
	}
}
