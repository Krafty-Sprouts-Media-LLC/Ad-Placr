<?php
/**
 * Unified Ad targeting gate: one should_display() for every render path.
 *
 * Pure `matches()` evaluates the targeting blob against an injected request
 * context. Empty/missing rule families fail open. No UA device gating —
 * device presentation stays CSS dual-slot / breakpoint only.
 *
 * @package AdPlacr
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates unified Ad targeting rules.
 *
 * @since 2.4.0
 */
final class Ad_Placr_Targeting {

	/**
	 * Whether an Ad may render for the given request context.
	 *
	 * Loads active state + targeting meta, runs `matches()`, then the
	 * `ad_placr_targeting_should_display` filter.
	 *
	 * @since 2.4.0
	 *
	 * @param int                  $ad_id Unified Ad post ID.
	 * @param array<string, mixed> $ctx   Request context (see normalize_context).
	 * @return bool
	 */
	public static function should_display( int $ad_id, array $ctx ): bool {
		if ( ! Ad_Placr_Ad::is_active( $ad_id ) ) {
			return false;
		}

		$targeting = Ad_Placr_Ad::get_targeting( $ad_id );
		$allowed   = self::matches( $targeting, $ctx );

		/**
		 * Filter whether an Ad should display after its saved rules are checked.
		 *
		 * @since 2.4.0
		 *
		 * @param bool                 $allowed   Core evaluation result.
		 * @param int                  $ad_id     Unified Ad post ID.
		 * @param array<string, mixed> $ctx       Normalized request context.
		 * @param array<string, mixed> $targeting Saved display rules.
		 */
		return (bool) apply_filters( 'ad_placr_targeting_should_display', $allowed, $ad_id, self::normalize_context( $ctx ), $targeting );
	}

	/**
	 * Pure rule evaluation (no WordPress calls).
	 *
	 * AND across rule families; OR within multi-value lists. Empty/missing
	 * families fail open except explicit singular + empty post_types.
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $targeting Targeting blob.
	 * @param array<string, mixed> $ctx       Request context.
	 * @return bool
	 */
	public static function matches( array $targeting, array $ctx ): bool {
		$ctx = self::normalize_context( $ctx );

		if ( ! self::matches_location( $targeting, $ctx ) ) {
			return false;
		}

		if ( ! self::matches_user( $targeting, $ctx ) ) {
			return false;
		}

		if ( ! self::matches_schedule( $targeting, $ctx ) ) {
			return false;
		}

		if ( ! self::matches_url( $targeting, $ctx ) ) {
			return false;
		}

		if ( ! self::matches_terms( $targeting, $ctx, 'include_categories', 'category_ids' ) ) {
			return false;
		}

		if ( ! self::matches_terms( $targeting, $ctx, 'include_tags', 'tag_ids' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Fill missing context keys with safe defaults.
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $ctx Partial context.
	 * @return array{
	 *     view: string,
	 *     post_type: string,
	 *     is_singular: bool,
	 *     user_state: string,
	 *     url_path: string,
	 *     category_ids: int[],
	 *     tag_ids: int[],
	 *     now: int
	 * }
	 */
	public static function normalize_context( array $ctx ): array {
		$view = isset( $ctx['view'] ) ? (string) $ctx['view'] : 'other';
		$user = isset( $ctx['user_state'] ) ? (string) $ctx['user_state'] : 'guest';
		if ( ! in_array( $user, array( 'logged_in', 'guest' ), true ) ) {
			$user = 'guest';
		}

		$path = isset( $ctx['url_path'] ) ? (string) $ctx['url_path'] : '/';
		if ( '' === $path ) {
			$path = '/';
		}

		return array(
			'view'         => $view,
			'post_type'    => isset( $ctx['post_type'] ) ? (string) $ctx['post_type'] : '',
			'is_singular'  => ! empty( $ctx['is_singular'] ),
			'user_state'   => $user,
			'url_path'     => $path,
			'category_ids' => self::int_list( $ctx['category_ids'] ?? array() ),
			'tag_ids'      => self::int_list( $ctx['tag_ids'] ?? array() ),
			'now'          => isset( $ctx['now'] ) ? (int) $ctx['now'] : 0,
		);
	}

	/**
	 * Build a request context from the current WordPress query / user.
	 *
	 * @since 2.4.0
	 *
	 * @return array<string, mixed>
	 */
	public static function build_request_context(): array {
		$view        = 'other';
		$is_singular = function_exists( 'is_singular' ) && is_singular();

		if ( function_exists( 'is_front_page' ) && is_front_page() ) {
			$view = 'front_page';
		} elseif ( function_exists( 'is_home' ) && is_home() && function_exists( 'is_front_page' ) && ! is_front_page() ) {
			$view = 'blog_index';
		} elseif ( $is_singular ) {
			$view = 'singular';
		} elseif ( function_exists( 'is_search' ) && is_search() ) {
			$view = 'search';
		} elseif ( function_exists( 'is_archive' ) && is_archive() ) {
			$view = 'archive';
		}

		$post_type = '';
		if ( $is_singular && function_exists( 'get_post_type' ) ) {
			$raw = get_post_type();
			if ( is_string( $raw ) ) {
				$post_type = $raw;
			}
		}

		$user_state = ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) ? 'logged_in' : 'guest';

		$url_path = '/';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- path only via parse_url.
			$uri    = wp_unslash( (string) $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$parsed = wp_parse_url( $uri, PHP_URL_PATH );
			if ( is_string( $parsed ) && '' !== $parsed ) {
				$url_path = $parsed;
			}
		}

		$category_ids = array();
		$tag_ids      = array();
		if ( $is_singular ) {
			if ( function_exists( 'get_the_category' ) ) {
				foreach ( (array) get_the_category() as $cat ) {
					if ( $cat instanceof WP_Term ) {
						$category_ids[] = (int) $cat->term_id;
					}
				}
			}
			if ( function_exists( 'get_the_tags' ) ) {
				$tags = get_the_tags();
				if ( is_array( $tags ) ) {
					foreach ( $tags as $tag ) {
						if ( $tag instanceof WP_Term ) {
							$tag_ids[] = (int) $tag->term_id;
						}
					}
				}
			}
		}

		$now = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time(); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- intentional site-local now for schedules.

		return self::normalize_context(
			array(
				'view'         => $view,
				'post_type'    => $post_type,
				'is_singular'  => $is_singular,
				'user_state'   => $user_state,
				'url_path'     => $url_path,
				'category_ids' => $category_ids,
				'tag_ids'      => $tag_ids,
				'now'          => $now,
			)
		);
	}

	/**
	 * Location + post_types family.
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $targeting Targeting blob.
	 * @param array<string, mixed> $ctx       Normalized context.
	 * @return bool
	 */
	private static function matches_location( array $targeting, array $ctx ): bool {
		$contexts = isset( $targeting['contexts'] ) && is_array( $targeting['contexts'] )
			? array_values( array_map( 'strval', $targeting['contexts'] ) )
			: array();

		$has_all     = empty( $contexts ) || in_array( 'all', $contexts, true );
		$view        = (string) $ctx['view'];
		$is_singular = ! empty( $ctx['is_singular'] );

		if ( ! $has_all && ! in_array( $view, $contexts, true ) ) {
			return false;
		}

		$post_types = isset( $targeting['post_types'] ) && is_array( $targeting['post_types'] )
			? array_values( array_map( 'strval', $targeting['post_types'] ) )
			: array();

		if ( ! $is_singular ) {
			return true;
		}

		if ( ! empty( $post_types ) ) {
			return in_array( (string) $ctx['post_type'], $post_types, true );
		}

		/*
		 * Empty post_types: fail open when contexts empty/`all`; hide when the
		 * Ad explicitly targets singular-only (2.1.0 behavior).
		 */
		if ( $has_all ) {
			return true;
		}

		return ! in_array( 'singular', $contexts, true );
	}

	/**
	 * Logged-in / guest family.
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $targeting Targeting blob.
	 * @param array<string, mixed> $ctx       Normalized context.
	 * @return bool
	 */
	private static function matches_user( array $targeting, array $ctx ): bool {
		$user = isset( $targeting['user'] ) ? (string) $targeting['user'] : 'any';
		if ( '' === $user || 'any' === $user ) {
			return true;
		}

		return (string) $ctx['user_state'] === $user;
	}

	/**
	 * Schedule start/end family (Unix `now` vs parsed bounds).
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $targeting Targeting blob.
	 * @param array<string, mixed> $ctx       Normalized context.
	 * @return bool
	 */
	private static function matches_schedule( array $targeting, array $ctx ): bool {
		if ( ! isset( $targeting['schedule'] ) || ! is_array( $targeting['schedule'] ) ) {
			return true;
		}

		$schedule = $targeting['schedule'];
		$now      = (int) $ctx['now'];

		if ( isset( $schedule['start'] ) && '' !== (string) $schedule['start'] ) {
			$start = self::parse_schedule_bound( (string) $schedule['start'] );
			if ( null !== $start && $now < $start ) {
				return false;
			}
		}

		if ( isset( $schedule['end'] ) && '' !== (string) $schedule['end'] ) {
			$end = self::parse_schedule_bound( (string) $schedule['end'] );
			if ( null !== $end && $now > $end ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * URL path needle family (OR).
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $targeting Targeting blob.
	 * @param array<string, mixed> $ctx       Normalized context.
	 * @return bool
	 */
	private static function matches_url( array $targeting, array $ctx ): bool {
		if ( ! isset( $targeting['url_contains'] ) || ! is_array( $targeting['url_contains'] ) ) {
			return true;
		}

		$needles = array();
		foreach ( $targeting['url_contains'] as $needle ) {
			if ( is_scalar( $needle ) ) {
				$n = trim( (string) $needle );
				if ( '' !== $n ) {
					$needles[] = $n;
				}
			}
		}

		if ( empty( $needles ) ) {
			return true;
		}

		$path = (string) $ctx['url_path'];
		foreach ( $needles as $needle ) {
			if ( false !== stripos( $path, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Category / tag allow-list family (OR on singular; non-singular fails if list set).
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $targeting Targeting blob.
	 * @param array<string, mixed> $ctx       Normalized context.
	 * @param string               $key       Targeting key.
	 * @param string               $ctx_key   Context id list key.
	 * @return bool
	 */
	private static function matches_terms( array $targeting, array $ctx, string $key, string $ctx_key ): bool {
		if ( ! isset( $targeting[ $key ] ) || ! is_array( $targeting[ $key ] ) ) {
			return true;
		}

		$wanted = self::int_list( $targeting[ $key ] );
		if ( empty( $wanted ) ) {
			return true;
		}

		if ( empty( $ctx['is_singular'] ) ) {
			return false;
		}

		$have = self::int_list( $ctx[ $ctx_key ] ?? array() );
		foreach ( $wanted as $id ) {
			if ( in_array( $id, $have, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse a schedule bound to Unix time; null if unparseable (ignored).
	 *
	 * @since 2.4.0
	 *
	 * @param string $raw Raw datetime string.
	 * @return int|null
	 */
	private static function parse_schedule_bound( string $raw ): ?int {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}

		if ( function_exists( 'wp_timezone' ) ) {
			try {
				$dt = date_create( $raw, wp_timezone() );
				if ( $dt instanceof DateTimeInterface ) {
					return $dt->getTimestamp();
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fall through to strtotime.
			}
		}

		$ts = strtotime( $raw );
		return false === $ts ? null : $ts;
	}

	/**
	 * Normalize a list of integers (drop zeros).
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $raw Raw list.
	 * @return int[]
	 */
	private static function int_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $v ) {
			$id = (int) $v;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}

		return array_values( array_unique( $out ) );
	}
}
