<?php
/**
 * Front-end dispatcher: registers automatic positions and applies context gates.
 *
 * Hook registration and echo/filter callbacks land in a later task. This file
 * starts with the pure context predicate used by those callbacks.
 *
 * @package AdPlacr
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry-driven front-end placement dispatcher.
 *
 * @since 2.2.0
 */
final class Ad_Placr_Frontend {

	/**
	 * Register automatic position hooks (implemented in a later task).
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public static function register(): void {
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
}
