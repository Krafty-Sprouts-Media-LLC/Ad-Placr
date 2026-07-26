<?php
/**
 * Front-end dispatcher: registers automatic positions and applies context gates.
 *
 * Loops registry keys with handler=frontend, attaches echo/content callbacks,
 * then queries and renders every matching Ad for each position.
 *
 * @package AdPlacr
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry-driven front-end Ad dispatcher.
 *
 * @since 2.2.0
 */
final class Ad_Placr_Frontend {

	/**
	 * Register automatic position hooks and rail CSS enqueue.
	 *
	 * Only handler=frontend keys are wired. Manual/special keys are never
	 * registered here (specialized classes and Phase 4 own those).
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_rail_assets' ) );

		foreach ( Ad_Placr_Positions::keys_by_handler( 'frontend', Ad_Placr_Positions::all() ) as $key ) {
			$descriptor = self::descriptor_for( $key );
			if ( null === $descriptor ) {
				continue;
			}

			$hook        = isset( $descriptor['hook'] ) ? (string) $descriptor['hook'] : '';
			$priority    = isset( $descriptor['priority'] ) ? (int) $descriptor['priority'] : 10;
			$render_mode = isset( $descriptor['render_mode'] ) ? (string) $descriptor['render_mode'] : '';

			/*
			 * Ignore incomplete descriptors (empty hook / unknown mode) so a bad
			 * ad_placr_positions filter cannot fatally register a no-op hook name.
			 */
			if ( '' === $hook ) {
				continue;
			}

			if ( 'echo' === $render_mode ) {
				/*
				 * loop_start receives the WP_Query instance. Secondary loops must
				 * not print listing tops — check the passed query, not the global
				 * is_main_query() (which stays true while $wp_query is still main).
				 */
				if ( 'loop_start' === $hook ) {
					add_action(
						$hook,
						static function ( $query ) use ( $key ): void {
							if ( ! $query instanceof WP_Query || ! $query->is_main_query() ) {
								return;
							}
							self::echo_position( $key );
						},
						$priority
					);
					continue;
				}

				add_action(
					$hook,
					static function () use ( $key ): void {
						self::echo_position( $key );
					},
					$priority
				);
				continue;
			}

			if ( 'content' === $render_mode ) {
				add_filter(
					$hook,
					static function ( $content ) use ( $key ) {
						return self::filter_content_position( $key, (string) $content );
					},
					$priority
				);
			}
		}
	}

	/**
	 * Enqueue sticky rail CSS when a left/right rail Ad may display.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public static function enqueue_rail_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$rail_keys = array(
			Ad_Placr_Positions::STICKY_LEFT_RAIL,
			Ad_Placr_Positions::STICKY_RIGHT_RAIL,
		);

		$has_candidate = false;
		foreach ( $rail_keys as $key ) {
			if ( ! empty( self::get_displayable_ad_ids( $key ) ) ) {
				$has_candidate = true;
				break;
			}
		}

		if ( ! $has_candidate ) {
			return;
		}

		wp_enqueue_style(
			'ad-placr-rails',
			AD_PLACR_PLUGIN_URL . 'assets/css/rails.css',
			array(),
			AD_PLACR_VERSION
		);
	}

	/**
	 * Echo all non-empty Ad HTML for an automatic position key.
	 *
	 * @since 2.2.0
	 *
	 * @param string $key Canonical position key.
	 * @return void
	 */
	public static function echo_position( string $key ): void {
		if ( is_admin() ) {
			return;
		}

		$html = self::render_all_for_position( $key );
		if ( '' === $html ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network code; stored by privileged users.
		echo $html;
	}

	/**
	 * Prepend or append Ad HTML around post content for content-mode keys.
	 *
	 * Same loop/feed guards as in-content: main query loop only, never admin/feed.
	 *
	 * @since 2.2.0
	 *
	 * @param string $key     Canonical position key (before/after_post_content).
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_content_position( string $key, string $content ): string {
		if ( is_admin() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( is_feed() ) {
			return $content;
		}

		$html = self::render_all_for_position( $key );
		if ( '' === $html ) {
			return $content;
		}

		if ( Ad_Placr_Positions::BEFORE_POST_CONTENT === $key ) {
			return $html . $content;
		}

		if ( Ad_Placr_Positions::AFTER_POST_CONTENT === $key ) {
			return $content . $html;
		}

		// Unknown content-mode key: append as a safe default.
		return $content . $html;
	}

	/**
	 * Whether a registry context key matches injected request flags.
	 *
	 * Pure helper: no WordPress conditionals. Callers pass flags from
	 * current_request_flags() (or a test double). Manual/widget contexts never
	 * match — Frontend does not own those handlers.
	 *
	 * @since 2.2.0
	 *
	 * @param string             $context Registry context key.
	 * @param array<string,bool> $flags   Keys: singular, front_page, blog_index, archive, main_query.
	 * @return bool
	 */
	public static function context_matches( string $context, array $flags ): bool {
		$singular   = ! empty( $flags['singular'] );
		$front_page = ! empty( $flags['front_page'] );
		$blog_index = ! empty( $flags['blog_index'] );
		$archive    = ! empty( $flags['archive'] );
		$main_query = ! empty( $flags['main_query'] );

		switch ( $context ) {
			case 'global':
				return true;
			case 'singular':
				return $singular && $main_query;
			case 'front_page':
				return $front_page;
			case 'blog_index':
				return $blog_index;
			case 'archive':
				return $archive;
			case 'widget':
			case 'manual':
				return false;
			default:
				return false;
		}
	}

	/**
	 * Current request flags for context_matches() (not unit-tested).
	 *
	 * The blog_index flag mirrors is_home() && ! is_front_page() so a static
	 * front page does not also count as the posts index.
	 *
	 * @since 2.2.0
	 *
	 * @return array<string,bool>
	 */
	public static function current_request_flags(): array {
		return array(
			'singular'   => is_singular(),
			'front_page' => is_front_page(),
			'blog_index' => is_home() && ! is_front_page(),
			'archive'    => is_archive(),
			'main_query' => is_main_query(),
		);
	}

	/**
	 * Render every displayable Ad for a position in deterministic query order.
	 *
	 * Applies registry context, then active + targeting gates. Each Ad receives
	 * a unique wrapper ID so responsive styles remain scoped across siblings.
	 *
	 * @since 2.2.0
	 *
	 * @param string $key Canonical position key.
	 * @return string
	 */
	public static function render_all_for_position( string $key ): string {
		$descriptor = self::descriptor_for( $key );
		if ( null === $descriptor ) {
			return '';
		}

		$registry_context = isset( $descriptor['context'] ) ? (string) $descriptor['context'] : '';
		$flags            = self::current_request_flags();

		if ( ! self::context_matches( $registry_context, $flags ) ) {
			return '';
		}

		$safe_key       = sanitize_key( $key );
		$modifier_class = self::modifier_class_for( $safe_key );

		$html = self::join_rendered_ads(
			self::get_displayable_ad_ids( $key ),
			static function ( int $ad_id ) use ( $safe_key, $modifier_class ): string {
				return Ad_Placr_Renderer::render_ad(
					$ad_id,
					array(
						'dom_id'         => 'ad-placr-pos-' . $safe_key . '-' . $ad_id,
						'modifier_class' => $modifier_class,
						'echo'           => false,
					)
				);
			}
		);

		return self::wrap_sticky_ads( $key, $html );
	}

	/**
	 * Concatenate rendered Ad HTML while preserving the supplied ID order.
	 *
	 * Empty renderer results naturally contribute no markup, allowing one
	 * ineligible Ad to be skipped without suppressing later matching records.
	 *
	 * @since 2.7.0
	 *
	 * @param int[]                $ad_ids Ad IDs in deterministic query order.
	 * @param callable(int):string $render Renderer callback for one Ad ID.
	 * @return string
	 */
	public static function join_rendered_ads( array $ad_ids, callable $render ): string {
		$html = '';

		foreach ( $ad_ids as $ad_id ) {
			$html .= (string) $render( (int) $ad_id );
		}

		return $html;
	}

	/**
	 * Give sticky siblings one positioned stack owner.
	 *
	 * Footer and rail styles position their modifier class. Applying that class
	 * to each Ad would place siblings at identical coordinates, so only this
	 * outer region owns positioning while child Ad IDs remain unique.
	 *
	 * @since 2.7.0
	 *
	 * @param string $key  Canonical position key.
	 * @param string $html Concatenated rendered Ad HTML.
	 * @return string
	 */
	public static function wrap_sticky_ads( string $key, string $html ): string {
		if ( '' === $html ) {
			return '';
		}

		if ( Ad_Placr_Positions::STICKY_FOOTER === $key ) {
			$dom_id   = 'ad-placr-footer-sticky';
			$modifier = 'ad-placr--footer-sticky';
		} elseif ( Ad_Placr_Positions::STICKY_LEFT_RAIL === $key ) {
			$dom_id   = 'ad-placr-pos-sticky_left_rail-stack';
			$modifier = 'ad-placr--rail-left';
		} elseif ( Ad_Placr_Positions::STICKY_RIGHT_RAIL === $key ) {
			$dom_id   = 'ad-placr-pos-sticky_right_rail-stack';
			$modifier = 'ad-placr--rail-right';
		} else {
			return $html;
		}

		return '<div id="' . esc_attr( $dom_id ) . '" class="ad-placr ' . esc_attr( $modifier ) . '">' . $html . '</div>';
	}

	/**
	 * Temporarily bridge a specialized breakpoint into unified rendering.
	 *
	 * Existing footer and in-content filters resolve their saved breakpoint
	 * before calling this helper. The temporary highest-priority callback makes
	 * that value authoritative only during the associated renderer call.
	 *
	 * @since 2.7.0
	 *
	 * @param int               $breakpoint Resolved specialized mobile maximum.
	 * @param callable():string $render     Renderer callback.
	 * @return string
	 */
	public static function with_mobile_breakpoint( int $breakpoint, callable $render ): string {
		$override = static function () use ( $breakpoint ): int {
			return $breakpoint;
		};

		add_filter( 'ad_placr_mobile_breakpoint', $override, PHP_INT_MAX );

		try {
			return (string) $render();
		} finally {
			remove_filter( 'ad_placr_mobile_breakpoint', $override, PHP_INT_MAX );
		}
	}

	/**
	 * Active Ad IDs for a position that pass targeting for this request.
	 *
	 * @since 2.2.0
	 *
	 * @param string $key Canonical position key.
	 * @return int[]
	 */
	private static function get_displayable_ad_ids( string $key ): array {
		if ( null === self::descriptor_for( $key ) ) {
			return array();
		}

		$ctx = Ad_Placr_Targeting::build_request_context();
		$out = array();

		foreach ( Ad_Placr_Ad::query_ids_for_position( $key ) as $ad_id ) {
			if ( Ad_Placr_Targeting::should_display( $ad_id, $ctx ) ) {
				$out[] = $ad_id;
			}
		}

		return $out;
	}
	/**
	 * BEM modifier class for a position key.
	 *
	 * Sticky positioning belongs to the aggregate stack wrapper so sibling Ads
	 * flow inside one fixed owner instead of overlapping at one coordinate.
	 *
	 * @since 2.2.0
	 *
	 * @param string $safe_key Sanitized key.
	 * @return string
	 */
	private static function modifier_class_for( string $safe_key ): string {
		return 'ad-placr--pos-' . $safe_key;
	}

	/**
	 * Filtered registry descriptor for a key, or null when missing/invalid.
	 *
	 * @since 2.2.0
	 *
	 * @param string $key Canonical position key.
	 * @return array<string, mixed>|null
	 */
	private static function descriptor_for( string $key ): ?array {
		$all = Ad_Placr_Positions::all();
		if ( ! array_key_exists( $key, $all ) ) {
			return null;
		}

		return $all[ $key ];
	}
}
