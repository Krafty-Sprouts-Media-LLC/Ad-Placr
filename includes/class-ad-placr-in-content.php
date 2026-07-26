<?php
/**
 * In-content Ads: inject Ads before/after numbered paragraphs (HTML <p> blocks).
 *
 * Hooks `the_content` at priority 12. Output is driven by active unified Ads at
 * `in_content_before_paragraph` / `in_content_after_paragraph` via
 * `Ad_Placr_Renderer`. Ads are bucketed into before/after maps keyed by
 * 1-based paragraph index, then injected in one walk over `<p>…</p>` chunks so
 * several Ads can target the same paragraph without re-parsing HTML.
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
	 * Enqueue styles when any displayable in-content Ad may render.
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
	}

	/**
	 * Whether any in-content Ad could run on this request (singular + targeting).
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private static function should_enqueue_context(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		return ! empty( self::get_displayable_ads() );
	}

	/**
	 * Active in-content Ads that match singular targeting for this request.
	 *
	 * Each row carries Ad ID, before/after position, targeting, a slot-shaped
	 * filter payload, and a stable DOM slug for wrapper / CSS scoping.
	 *
	 * @since 2.1.0
	 *
	 * @return array<int, array{
	 *     ad_id: int,
	 *     position: string,
	 *     targeting: array<string, mixed>,
	 *     slot: array<string, mixed>,
	 *     dom_slug: string
	 * }>
	 */
	private static function get_displayable_ads(): array {
		$out = array();

		/*
		 * Query both paragraph positions. Position taxonomy key decides before vs after;
		 * paragraph index lives on the targeting blob saved by the Ad editor.
		 */
		$by_position = array(
			Ad_Placr_Positions::IN_CONTENT_BEFORE_PARAGRAPH => 'before',
			Ad_Placr_Positions::IN_CONTENT_AFTER_PARAGRAPH => 'after',
		);

		$ctx = Ad_Placr_Targeting::build_request_context();

		foreach ( $by_position as $position_key => $before_after ) {
			foreach ( Ad_Placr_Ad::query_ids_for_position( $position_key ) as $ad_id ) {
				if ( ! Ad_Placr_Targeting::should_display( $ad_id, $ctx ) ) {
					continue;
				}

				$targeting = Ad_Placr_Ad::get_targeting( $ad_id );

				$paragraph = self::clamp_paragraph_index( $targeting );
				$dom_slug  = self::resolve_dom_slug( $targeting, $ad_id );
				$slot      = self::build_slot_shaped_array( $targeting, $dom_slug, $paragraph, $before_after );

				$out[] = array(
					'ad_id'     => $ad_id,
					'position'  => $before_after,
					'targeting' => $targeting,
					'slot'      => $slot,
					'dom_slug'  => $dom_slug,
				);
			}
		}

		return $out;
	}

	/**
	 * Clamp targeting paragraph to 1–100 (1-based index into `<p>` blocks).
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, mixed> $targeting Ad targeting blob.
	 * @return int
	 */
	private static function clamp_paragraph_index( array $targeting ): int {
		$n = isset( $targeting['paragraph'] ) ? absint( $targeting['paragraph'] ) : 1;

		return max( 1, min( 100, $n ) );
	}

	/**
	 * Stable DOM id slug ending in the unified Ad post ID.
	 *
	 * An optional saved `slot_id` keeps the wrapper readable while the Ad ID suffix
	 * guarantees uniqueness when several Ads target the same paragraph.
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, mixed> $targeting Ad targeting blob.
	 * @param int                  $ad_id     Unified Ad post ID.
	 * @return string
	 */
	private static function resolve_dom_slug( array $targeting, int $ad_id ): string {
		$raw = '';

		if ( isset( $targeting['slot_id'] ) && is_scalar( $targeting['slot_id'] ) ) {
			$raw = (string) $targeting['slot_id'];
		}

		$slug = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $raw );
		if ( ! is_string( $slug ) || '' === $slug ) {
			return (string) $ad_id;
		}

		return $slug . '-' . $ad_id;
	}

	/**
	 * Slot-shaped payload for in-content display and breakpoint filters.
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, mixed> $targeting    Ad targeting blob.
	 * @param string               $dom_slug     Sanitized slug used in `id` / DOM id.
	 * @param int                  $paragraph    Clamped 1-based paragraph index.
	 * @param string               $before_after `before` or `after`.
	 * @return array<string, mixed>
	 */
	private static function build_slot_shaped_array( array $targeting, string $dom_slug, int $paragraph, string $before_after ): array {
		$post_types = isset( $targeting['post_types'] ) && is_array( $targeting['post_types'] )
			? $targeting['post_types']
			: array();
		$slot_id    = '';

		if ( isset( $targeting['slot_id'] ) && is_scalar( $targeting['slot_id'] ) ) {
			$resolved = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $targeting['slot_id'] );
			$slot_id  = is_string( $resolved ) ? $resolved : '';
		}

		/*
		 * The filter payload retains the saved slot identity. The separate DOM slug
		 * may include the Ad ID suffix needed for unique wrappers.
		 */
		if ( '' === $slot_id ) {
			$slot_id = $dom_slug;
		}

		return array(
			'id'              => $slot_id,
			'paragraph_index' => $paragraph,
			'position'        => $before_after,
			'post_types'      => $post_types,
		);
	}

	/**
	 * Insert all applicable unified Ads into post content.
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

		$ads = self::get_displayable_ads();

		if ( empty( $ads ) ) {
			return $content;
		}

		$post_id = get_the_ID();

		/*
		 * Bucket Ad HTML by paragraph index first, then inject once.
		 * Re-walking the content per Ad would renumber paragraphs after each
		 * insert and break “after paragraph 3” when another slot already inserted above it.
		 */
		$before_by_para = array();
		$after_by_para  = array();

		foreach ( $ads as $row ) {
			$slot = $row['slot'];

			/**
			 * Filter whether this in-content slot should output for the current post.
			 *
			 * @since 1.1.0
			 *
			 * @param bool                 $show    Whether to show this slot.
			 * @param array<string, mixed> $slot    Slot-shaped configuration (BC).
			 * @param int                  $post_id Current post ID.
			 */
			$show = apply_filters( 'ad_placr_in_content_slot_should_display', true, $slot, $post_id );

			if ( true !== $show ) {
				continue;
			}

			$breakpoint = self::resolve_mobile_breakpoint( $slot );
			$dom_id     = 'ad-placr-ic-' . (string) $row['dom_slug'];

			$html = Ad_Placr_Frontend::with_mobile_breakpoint(
				$breakpoint,
				static function () use ( $row, $dom_id ): string {
					return Ad_Placr_Renderer::render_ad(
						(int) $row['ad_id'],
						array(
							'dom_id'         => $dom_id,
							'modifier_class' => 'ad-placr--in-content',
							'echo'           => false,
						)
					);
				}
			);

			if ( '' === $html ) {
				continue;
			}

			$n = (int) $slot['paragraph_index'];
			$p = (string) $row['position'];

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
	 * Resolve mobile breakpoint (782px default).
	 *
	 * @since 0.1.6
	 *
	 * @param array<string, mixed> $slot Slot-shaped configuration (BC for the filter).
	 * @return int
	 */
	private static function resolve_mobile_breakpoint( array $slot ): int {
		$default = 782;

		/**
		 * Filter max-width breakpoint (px) for in-content mobile vs universal slots.
		 *
		 * @since 0.1.6
		 *
		 * @param int                  $default Default breakpoint (782).
		 * @param array<string, mixed> $slot    Slot-shaped configuration (BC).
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
