<?php
/**
 * Admin UI: Placement targeting meta box (minimal Phase 5).
 *
 * Saves into Ad_Placr_Placement::META_TARGETING only — merges with existing
 * keys (paragraph, slot_id) so migration data is not wiped.
 *
 * @package AdPlacr
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Placement admin meta boxes and save handlers.
 *
 * @since 2.4.0
 */
final class Ad_Placr_Admin {

	/**
	 * Nonce action for targeting form.
	 *
	 * @since 2.4.0
	 */
	private const NONCE_ACTION = 'ad_placr_save_targeting';

	/**
	 * Nonce field name.
	 *
	 * @since 2.4.0
	 */
	private const NONCE_FIELD = 'ad_placr_targeting_nonce';

	/**
	 * Hook meta boxes and save.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . Ad_Placr_Placement::POST_TYPE, array( __CLASS__, 'save_targeting' ), 10, 2 );
	}

	/**
	 * Register the targeting meta box on Placements.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	public static function register_meta_boxes(): void {
		add_meta_box(
			'ad_placr_targeting',
			__( 'Targeting', 'ad-placr' ),
			array( __CLASS__, 'render_targeting_meta_box' ),
			Ad_Placr_Placement::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render targeting fields.
	 *
	 * @since 2.4.0
	 *
	 * @param WP_Post $post Placement post.
	 * @return void
	 */
	public static function render_targeting_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$t          = Ad_Placr_Placement::get_targeting( (int) $post->ID );
		$contexts   = isset( $t['contexts'] ) && is_array( $t['contexts'] ) ? $t['contexts'] : array( 'all' );
		$post_types = isset( $t['post_types'] ) && is_array( $t['post_types'] ) ? $t['post_types'] : array();
		$user       = isset( $t['user'] ) ? (string) $t['user'] : 'any';
		$schedule   = isset( $t['schedule'] ) && is_array( $t['schedule'] ) ? $t['schedule'] : array();
		$start      = isset( $schedule['start'] ) ? (string) $schedule['start'] : '';
		$end        = isset( $schedule['end'] ) ? (string) $schedule['end'] : '';
		$url_lines  = isset( $t['url_contains'] ) && is_array( $t['url_contains'] )
			? implode( "\n", array_map( 'strval', $t['url_contains'] ) )
			: '';
		$cats       = isset( $t['include_categories'] ) && is_array( $t['include_categories'] )
			? implode( ', ', array_map( 'strval', $t['include_categories'] ) )
			: '';
		$tags       = isset( $t['include_tags'] ) && is_array( $t['include_tags'] )
			? implode( ', ', array_map( 'strval', $t['include_tags'] ) )
			: '';

		$context_choices = array(
			'all'        => __( 'All (fail-open default)', 'ad-placr' ),
			'singular'   => __( 'Singular', 'ad-placr' ),
			'front_page' => __( 'Front page', 'ad-placr' ),
			'blog_index' => __( 'Blog index', 'ad-placr' ),
			'archive'    => __( 'Archives', 'ad-placr' ),
			'search'     => __( 'Search', 'ad-placr' ),
		);

		$type_choices = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<p class="description">
			<?php esc_html_e( 'Empty rules fail open (show). Device targeting uses CSS mobile slots, not user-agent sniffing.', 'ad-placr' ); ?>
		</p>
		<p><strong><?php esc_html_e( 'Contexts', 'ad-placr' ); ?></strong></p>
		<?php foreach ( $context_choices as $key => $label ) : ?>
			<label style="display:block;margin:0.25em 0;">
				<input type="checkbox" name="ad_placr_contexts[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $contexts, true ) ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>

		<p style="margin-top:1em;"><strong><?php esc_html_e( 'Post types (singular)', 'ad-placr' ); ?></strong></p>
		<?php foreach ( $type_choices as $pto ) : ?>
			<label style="display:inline-block;margin:0.25em 1em 0.25em 0;">
				<input type="checkbox" name="ad_placr_post_types[]" value="<?php echo esc_attr( $pto->name ); ?>" <?php checked( in_array( $pto->name, $post_types, true ) ); ?> />
				<?php echo esc_html( $pto->labels->singular_name ); ?>
			</label>
		<?php endforeach; ?>

		<p style="margin-top:1em;">
			<label for="ad_placr_user"><strong><?php esc_html_e( 'Users', 'ad-placr' ); ?></strong></label><br />
			<select name="ad_placr_user" id="ad_placr_user">
				<option value="any" <?php selected( $user, 'any' ); ?>><?php esc_html_e( 'Everyone', 'ad-placr' ); ?></option>
				<option value="logged_in" <?php selected( $user, 'logged_in' ); ?>><?php esc_html_e( 'Logged-in only', 'ad-placr' ); ?></option>
				<option value="guest" <?php selected( $user, 'guest' ); ?>><?php esc_html_e( 'Guests only', 'ad-placr' ); ?></option>
			</select>
		</p>

		<p>
			<label for="ad_placr_schedule_start"><strong><?php esc_html_e( 'Schedule start', 'ad-placr' ); ?></strong></label><br />
			<input type="text" class="regular-text" name="ad_placr_schedule_start" id="ad_placr_schedule_start" value="<?php echo esc_attr( $start ); ?>" placeholder="YYYY-MM-DD HH:MM:SS" />
		</p>
		<p>
			<label for="ad_placr_schedule_end"><strong><?php esc_html_e( 'Schedule end', 'ad-placr' ); ?></strong></label><br />
			<input type="text" class="regular-text" name="ad_placr_schedule_end" id="ad_placr_schedule_end" value="<?php echo esc_attr( $end ); ?>" placeholder="YYYY-MM-DD HH:MM:SS" />
		</p>

		<p>
			<label for="ad_placr_url_contains"><strong><?php esc_html_e( 'URL path contains (one per line, OR)', 'ad-placr' ); ?></strong></label><br />
			<textarea class="large-text" rows="3" name="ad_placr_url_contains" id="ad_placr_url_contains"><?php echo esc_textarea( $url_lines ); ?></textarea>
		</p>

		<p>
			<label for="ad_placr_include_categories"><strong><?php esc_html_e( 'Category IDs (comma-separated)', 'ad-placr' ); ?></strong></label><br />
			<input type="text" class="regular-text" name="ad_placr_include_categories" id="ad_placr_include_categories" value="<?php echo esc_attr( $cats ); ?>" />
		</p>
		<p>
			<label for="ad_placr_include_tags"><strong><?php esc_html_e( 'Tag IDs (comma-separated)', 'ad-placr' ); ?></strong></label><br />
			<input type="text" class="regular-text" name="ad_placr_include_tags" id="ad_placr_include_tags" value="<?php echo esc_attr( $tags ); ?>" />
		</p>
		<?php
	}

	/**
	 * Persist targeting fields into META_TARGETING (merge, do not wipe extras).
	 *
	 * @since 2.4.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_targeting( int $post_id, WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$existing = Ad_Placr_Placement::get_targeting( $post_id );

		$allowed_contexts = array( 'all', 'singular', 'front_page', 'blog_index', 'archive', 'search' );
		$contexts_raw     = isset( $_POST['ad_placr_contexts'] ) ? wp_unslash( $_POST['ad_placr_contexts'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$contexts         = array();
		if ( is_array( $contexts_raw ) ) {
			foreach ( $contexts_raw as $c ) {
				$c = sanitize_key( (string) $c );
				if ( in_array( $c, $allowed_contexts, true ) ) {
					$contexts[] = $c;
				}
			}
		}
		$existing['contexts'] = array_values( array_unique( $contexts ) );

		$types_raw  = isset( $_POST['ad_placr_post_types'] ) ? wp_unslash( $_POST['ad_placr_post_types'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_types = array();
		if ( is_array( $types_raw ) ) {
			foreach ( $types_raw as $pt ) {
				$pt = sanitize_key( (string) $pt );
				if ( '' !== $pt && post_type_exists( $pt ) ) {
					$post_types[] = $pt;
				}
			}
		}
		$existing['post_types'] = array_values( array_unique( $post_types ) );

		$user = isset( $_POST['ad_placr_user'] ) ? sanitize_key( wp_unslash( (string) $_POST['ad_placr_user'] ) ) : 'any';
		if ( ! in_array( $user, array( 'any', 'logged_in', 'guest' ), true ) ) {
			$user = 'any';
		}
		$existing['user'] = $user;

		$start                = isset( $_POST['ad_placr_schedule_start'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['ad_placr_schedule_start'] ) ) : '';
		$end                  = isset( $_POST['ad_placr_schedule_end'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['ad_placr_schedule_end'] ) ) : '';
		$existing['schedule'] = array(
			'start' => $start,
			'end'   => $end,
		);

		$url_raw = isset( $_POST['ad_placr_url_contains'] ) ? wp_unslash( (string) $_POST['ad_placr_url_contains'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$needles = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $url_raw ) as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$needles[] = $line;
			}
		}
		$existing['url_contains'] = $needles;

		$existing['include_categories'] = self::parse_id_list(
			isset( $_POST['ad_placr_include_categories'] ) ? wp_unslash( (string) $_POST['ad_placr_include_categories'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);
		$existing['include_tags']       = self::parse_id_list(
			isset( $_POST['ad_placr_include_tags'] ) ? wp_unslash( (string) $_POST['ad_placr_include_tags'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);

		update_post_meta( $post_id, Ad_Placr_Placement::META_TARGETING, $existing );
	}

	/**
	 * Parse comma-separated positive ints.
	 *
	 * @since 2.4.0
	 *
	 * @param string $raw Raw input.
	 * @return int[]
	 */
	private static function parse_id_list( string $raw ): array {
		$out = array();
		foreach ( preg_split( '/[\s,]+/', $raw ) as $part ) {
			$id = absint( $part );
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}

		return array_values( array_unique( $out ) );
	}
}
