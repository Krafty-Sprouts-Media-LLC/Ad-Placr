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
	 * @since 2.0.0
	 *
	 * @return array<string, array{label:string, group:string, context:string}>
	 */
	public static function defaults(): array {
		return array(
			self::IN_CONTENT_BEFORE_PARAGRAPH => array(
				'label'   => 'Before paragraph N',
				'group'   => 'in_content',
				'context' => 'singular',
			),
			self::IN_CONTENT_AFTER_PARAGRAPH  => array(
				'label'   => 'After paragraph N',
				'group'   => 'in_content',
				'context' => 'singular',
			),
			self::BEFORE_POST_CONTENT         => array(
				'label'   => 'Before post content',
				'group'   => 'content',
				'context' => 'singular',
			),
			self::AFTER_POST_CONTENT          => array(
				'label'   => 'After post content',
				'group'   => 'content',
				'context' => 'singular',
			),
			self::BEFORE_HEADER               => array(
				'label'   => 'Before header',
				'group'   => 'structure',
				'context' => 'global',
			),
			self::AFTER_HEADER                => array(
				'label'   => 'After header',
				'group'   => 'structure',
				'context' => 'global',
			),
			self::BEFORE_FOOTER               => array(
				'label'   => 'Before footer',
				'group'   => 'structure',
				'context' => 'global',
			),
			self::AFTER_FOOTER                => array(
				'label'   => 'After footer',
				'group'   => 'structure',
				'context' => 'global',
			),
			self::STICKY_FOOTER               => array(
				'label'   => 'Sticky footer',
				'group'   => 'sticky',
				'context' => 'global',
			),
			self::STICKY_LEFT_RAIL            => array(
				'label'   => 'Sticky left rail',
				'group'   => 'sticky',
				'context' => 'global',
			),
			self::STICKY_RIGHT_RAIL           => array(
				'label'   => 'Sticky right rail',
				'group'   => 'sticky',
				'context' => 'global',
			),
			self::FRONT_PAGE_TOP              => array(
				'label'   => 'Front page top',
				'group'   => 'listing',
				'context' => 'front_page',
			),
			self::FRONT_PAGE_BOTTOM           => array(
				'label'   => 'Front page bottom',
				'group'   => 'listing',
				'context' => 'front_page',
			),
			self::BLOG_INDEX_TOP              => array(
				'label'   => 'Blog index top',
				'group'   => 'listing',
				'context' => 'blog_index',
			),
			self::BLOG_INDEX_BOTTOM           => array(
				'label'   => 'Blog index bottom',
				'group'   => 'listing',
				'context' => 'blog_index',
			),
			self::ARCHIVE_TOP                 => array(
				'label'   => 'Archive top',
				'group'   => 'listing',
				'context' => 'archive',
			),
			self::ARCHIVE_BOTTOM              => array(
				'label'   => 'Archive bottom',
				'group'   => 'listing',
				'context' => 'archive',
			),
			self::SIDEBAR_WIDGET              => array(
				'label'   => 'Sidebar widget',
				'group'   => 'manual',
				'context' => 'widget',
			),
			self::MANUAL_SHORTCODE            => array(
				'label'   => 'Shortcode',
				'group'   => 'manual',
				'context' => 'manual',
			),
			self::MANUAL_BLOCK                => array(
				'label'   => 'Block',
				'group'   => 'manual',
				'context' => 'manual',
			),
		);
	}

	/**
	 * Filtered registry. Extensions add positions here without editing core.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{label:string, group:string, context:string}>
	 */
	public static function all(): array {
		/**
		 * Filter the registered ad positions.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, array> $positions Position key => descriptor.
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
}
