<?php
/**
 * Canonical ad-position registry.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for placement positions.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Positions {

	public const IN_CONTENT_BEFORE_PARAGRAPH = 'in_content_before_paragraph';
	public const IN_CONTENT_AFTER_PARAGRAPH  = 'in_content_after_paragraph';
	public const BEFORE_POST_CONTENT         = 'before_post_content';
	public const AFTER_POST_CONTENT          = 'after_post_content';
	public const BEFORE_HEADER               = 'before_header';
	public const AFTER_HEADER                = 'after_header';
	public const BEFORE_FOOTER               = 'before_footer';
	public const AFTER_FOOTER                = 'after_footer';
	public const STICKY_FOOTER               = 'sticky_footer';
	public const STICKY_LEFT_RAIL            = 'sticky_left_rail';
	public const STICKY_RIGHT_RAIL           = 'sticky_right_rail';
	public const FRONT_PAGE_TOP              = 'front_page_top';
	public const FRONT_PAGE_BOTTOM           = 'front_page_bottom';
	public const BLOG_INDEX_TOP              = 'blog_index_top';
	public const BLOG_INDEX_BOTTOM           = 'blog_index_bottom';
	public const ARCHIVE_TOP                 = 'archive_top';
	public const ARCHIVE_BOTTOM              = 'archive_bottom';
	public const SIDEBAR_WIDGET              = 'sidebar_widget';
	public const MANUAL_SHORTCODE            = 'manual_shortcode';
	public const MANUAL_BLOCK                = 'manual_block';

	/**
	 * Unfiltered registry. Pure (no WordPress calls) so it is unit-testable.
	 *
	 * Each descriptor includes label/group/context plus hook metadata used by
	 * the Frontend dispatcher: hook, priority, render_mode, handler.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{
	 *     label:string,
	 *     group:string,
	 *     context:string,
	 *     hook:?string,
	 *     priority:int,
	 *     render_mode:string,
	 *     handler:string
	 * }>
	 */
	public static function defaults(): array {
		return array(
			self::IN_CONTENT_BEFORE_PARAGRAPH => array(
				'label'       => 'Before paragraph N',
				'group'       => 'in_content',
				'context'     => 'singular',
				'hook'        => null,
				'priority'    => 0,
				'render_mode' => 'none',
				'handler'     => 'special',
			),
			self::IN_CONTENT_AFTER_PARAGRAPH  => array(
				'label'       => 'After paragraph N',
				'group'       => 'in_content',
				'context'     => 'singular',
				'hook'        => null,
				'priority'    => 0,
				'render_mode' => 'none',
				'handler'     => 'special',
			),
			self::BEFORE_POST_CONTENT         => array(
				'label'       => 'Before post content',
				'group'       => 'content',
				'context'     => 'singular',
				'hook'        => 'the_content',
				'priority'    => 11,
				'render_mode' => 'content',
				'handler'     => 'frontend',
			),
			self::AFTER_POST_CONTENT          => array(
				'label'       => 'After post content',
				'group'       => 'content',
				'context'     => 'singular',
				'hook'        => 'the_content',
				'priority'    => 13,
				'render_mode' => 'content',
				'handler'     => 'frontend',
			),
			self::BEFORE_HEADER               => array(
				'label'       => 'Before header',
				'group'       => 'structure',
				'context'     => 'global',
				'hook'        => 'wp_body_open',
				'priority'    => 5,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::AFTER_HEADER                => array(
				'label'       => 'After header',
				'group'       => 'structure',
				'context'     => 'global',
				'hook'        => 'wp_body_open',
				'priority'    => 20,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::BEFORE_FOOTER               => array(
				'label'       => 'Before footer',
				'group'       => 'structure',
				'context'     => 'global',
				'hook'        => 'get_footer',
				'priority'    => 5,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::AFTER_FOOTER                => array(
				'label'       => 'After footer',
				'group'       => 'structure',
				'context'     => 'global',
				'hook'        => 'wp_footer',
				'priority'    => 20,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::STICKY_FOOTER               => array(
				'label'       => 'Sticky footer',
				'group'       => 'sticky',
				'context'     => 'global',
				'hook'        => 'wp_footer',
				'priority'    => 100,
				'render_mode' => 'echo',
				'handler'     => 'special',
			),
			self::STICKY_LEFT_RAIL            => array(
				'label'       => 'Sticky left rail',
				'group'       => 'sticky',
				'context'     => 'global',
				'hook'        => 'wp_footer',
				'priority'    => 99,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::STICKY_RIGHT_RAIL           => array(
				'label'       => 'Sticky right rail',
				'group'       => 'sticky',
				'context'     => 'global',
				'hook'        => 'wp_footer',
				'priority'    => 99,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::FRONT_PAGE_TOP              => array(
				'label'       => 'Front page top',
				'group'       => 'listing',
				'context'     => 'front_page',
				'hook'        => 'loop_start',
				'priority'    => 5,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::FRONT_PAGE_BOTTOM           => array(
				'label'       => 'Front page bottom',
				'group'       => 'listing',
				'context'     => 'front_page',
				'hook'        => 'wp_footer',
				'priority'    => 15,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::BLOG_INDEX_TOP              => array(
				'label'       => 'Blog index top',
				'group'       => 'listing',
				'context'     => 'blog_index',
				'hook'        => 'loop_start',
				'priority'    => 5,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::BLOG_INDEX_BOTTOM           => array(
				'label'       => 'Blog index bottom',
				'group'       => 'listing',
				'context'     => 'blog_index',
				'hook'        => 'wp_footer',
				'priority'    => 15,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::ARCHIVE_TOP                 => array(
				'label'       => 'Archive top',
				'group'       => 'listing',
				'context'     => 'archive',
				'hook'        => 'loop_start',
				'priority'    => 5,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::ARCHIVE_BOTTOM              => array(
				'label'       => 'Archive bottom',
				'group'       => 'listing',
				'context'     => 'archive',
				'hook'        => 'wp_footer',
				'priority'    => 15,
				'render_mode' => 'echo',
				'handler'     => 'frontend',
			),
			self::SIDEBAR_WIDGET              => array(
				'label'       => 'Sidebar widget',
				'group'       => 'manual',
				'context'     => 'widget',
				'hook'        => null,
				'priority'    => 0,
				'render_mode' => 'none',
				'handler'     => 'manual',
			),
			self::MANUAL_SHORTCODE            => array(
				'label'       => 'Shortcode',
				'group'       => 'manual',
				'context'     => 'manual',
				'hook'        => null,
				'priority'    => 0,
				'render_mode' => 'none',
				'handler'     => 'manual',
			),
			self::MANUAL_BLOCK                => array(
				'label'       => 'Block',
				'group'       => 'manual',
				'context'     => 'manual',
				'hook'        => null,
				'priority'    => 0,
				'render_mode' => 'none',
				'handler'     => 'manual',
			),
		);
	}

	/**
	 * Filtered registry. Extensions add positions here without editing core.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{
	 *     label:string,
	 *     group:string,
	 *     context:string,
	 *     hook:?string,
	 *     priority:int,
	 *     render_mode:string,
	 *     handler:string
	 * }>
	 */
	public static function all(): array {
		/**
		 * Filter the registered ad positions.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, array> $positions Position key => descriptor.
		 * @var   mixed                $positions Filter return (defensive is_array below).
		 */
		$positions = apply_filters( 'ad_placr_positions', self::defaults() );

		return is_array( $positions ) ? $positions : self::defaults();
	}

	/**
	 * All registered position keys.
	 *
	 * @since 2.0.0
	 *
	 * @return string[]
	 */
	public static function keys(): array {
		return array_keys( self::all() );
	}

	/**
	 * Whether a position key is registered.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Position key.
	 * @return bool
	 */
	public static function exists( string $key ): bool {
		return array_key_exists( $key, self::all() );
	}

	/**
	 * Human label for a position, or empty string if unknown.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Position key.
	 * @return string
	 */
	public static function label( string $key ): string {
		$all = self::all();

		return isset( $all[ $key ]['label'] ) ? (string) $all[ $key ]['label'] : '';
	}

	/**
	 * Whether an automatic position can honor an alignment preference.
	 *
	 * Only positions placed inside or directly around flowing post content
	 * have meaningful horizontal alignment; pinned bars, rails, listing
	 * strips, and manual embeds do not.
	 *
	 * @since 2.8.0
	 *
	 * @param string $key Canonical position key.
	 * @return bool True when the position supports alignment.
	 */
	public static function supports_alignment( string $key ): bool {
		if ( ! self::exists( $key ) ) {
			return false;
		}

		$all   = self::all();
		$group = isset( $all[ $key ]['group'] ) ? (string) $all[ $key ]['group'] : '';

		return in_array( $group, array( 'in_content', 'content' ), true );
	}

	/**
	 * Position keys whose handler matches, optionally from a given registry.
	 *
	 * Pass `$registry` (e.g. defaults()) for pure unit tests; omit to use all().
	 *
	 * @since 2.2.0
	 *
	 * @param string                                     $handler  One of frontend|special|manual.
	 * @param array<string, array{handler?:string}>|null $registry Optional registry override.
	 * @return string[]
	 */
	public static function keys_by_handler( string $handler, ?array $registry = null ): array {
		$registry = null === $registry ? self::all() : $registry;
		$keys     = array();

		foreach ( $registry as $key => $descriptor ) {
			if ( ! is_array( $descriptor ) ) {
				continue;
			}
			if ( isset( $descriptor['handler'] ) && $handler === $descriptor['handler'] ) {
				$keys[] = (string) $key;
			}
		}

		return $keys;
	}

	/**
	 * Partition a registry into frontend / special / manual key lists.
	 *
	 * Used by orphan-invariant tests and callers that need a pure split
	 * without going through apply_filters.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, array{handler?:string}> $registry Position registry.
	 * @return array{frontend:string[], special:string[], manual:string[]}
	 */
	public static function partition_from( array $registry ): array {
		return array(
			'frontend' => self::keys_by_handler( 'frontend', $registry ),
			'special'  => self::keys_by_handler( 'special', $registry ),
			'manual'   => self::keys_by_handler( 'manual', $registry ),
		);
	}

	/**
	 * Keys handled by the Frontend dispatcher (filtered registry).
	 *
	 * @since 2.2.0
	 *
	 * @return string[]
	 */
	public static function frontend_keys(): array {
		return self::keys_by_handler( 'frontend' );
	}

	/**
	 * Keys with specialized renderers (sticky footer, in-content).
	 *
	 * @since 2.2.0
	 *
	 * @return string[]
	 */
	public static function special_keys(): array {
		return self::keys_by_handler( 'special' );
	}

	/**
	 * Keys rendered only via shortcode / block / widget (not Frontend).
	 *
	 * @since 2.2.0
	 *
	 * @return string[]
	 */
	public static function manual_keys(): array {
		return self::keys_by_handler( 'manual' );
	}

	/**
	 * Keys that can render automatically: frontend union special.
	 *
	 * Manual keys are excluded -- they never go through Frontend.
	 *
	 * @since 2.2.0
	 *
	 * @return string[]
	 */
	public static function renderable_keys(): array {
		return array_merge( self::frontend_keys(), self::special_keys() );
	}
}
