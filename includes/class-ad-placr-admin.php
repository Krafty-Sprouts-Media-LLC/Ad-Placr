<?php
/**
 * Admin UI: CPT meta boxes, list-table columns, targeting save.
 *
 * All meta reads/writes use Ad/Placement class constants. Capability:
 * manage_options. No SweetAlert or custom SPA admin.
 *
 * @package AdPlacr
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Placement/Ad admin UI and list columns.
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
	 * Nonce field name (targeting).
	 *
	 * @since 2.4.0
	 */
	private const NONCE_FIELD = 'ad_placr_targeting_nonce';

	/**
	 * Nonce action for Ad / Placement detail forms.
	 *
	 * @since 2.6.0
	 */
	private const NONCE_ACTION_DETAILS = 'ad_placr_save_details';

	/**
	 * Nonce field for Ad / Placement detail forms.
	 *
	 * @since 2.6.0
	 */
	private const NONCE_FIELD_DETAILS = 'ad_placr_details_nonce';

	/**
	 * Hook meta boxes, saves, and list columns.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . Ad_Placr_Placement::POST_TYPE, array( __CLASS__, 'save_targeting' ), 10, 2 );
		// After targeting so paragraph merge keeps contexts/user/etc. from that save.
		add_action( 'save_post_' . Ad_Placr_Placement::POST_TYPE, array( __CLASS__, 'save_placement_details' ), 11, 2 );
		add_action( 'save_post_' . Ad_Placr_Ad::POST_TYPE, array( __CLASS__, 'save_ad_details' ), 10, 2 );

		add_filter( 'manage_' . Ad_Placr_Ad::POST_TYPE . '_posts_columns', array( __CLASS__, 'ad_columns' ) );
		add_action( 'manage_' . Ad_Placr_Ad::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_ad_column' ), 10, 2 );

		add_filter( 'manage_' . Ad_Placr_Placement::POST_TYPE . '_posts_columns', array( __CLASS__, 'placement_columns' ) );
		add_action( 'manage_' . Ad_Placr_Placement::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_placement_column' ), 10, 2 );
	}

	/**
	 * Register meta boxes on Ads and Placements.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	public static function register_meta_boxes(): void {
		add_meta_box(
			'ad_placr_ad_details',
			__( 'Ad creative', 'ad-placr' ),
			array( __CLASS__, 'render_ad_meta_box' ),
			Ad_Placr_Ad::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'ad_placr_placement_details',
			__( 'Placement details', 'ad-placr' ),
			array( __CLASS__, 'render_placement_details_meta_box' ),
			Ad_Placr_Placement::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'ad_placr_placement_ads',
			__( 'Weighted ads', 'ad-placr' ),
			array( __CLASS__, 'render_placement_ads_meta_box' ),
			Ad_Placr_Placement::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'ad_placr_targeting',
			__( 'Targeting', 'ad-placr' ),
			array( __CLASS__, 'render_targeting_meta_box' ),
			Ad_Placr_Placement::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Ad creative fields.
	 *
	 * @since 2.6.0
	 *
	 * @param WP_Post $post Ad post.
	 * @return void
	 */
	public static function render_ad_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION_DETAILS, self::NONCE_FIELD_DETAILS );

		$code   = Ad_Placr_Ad::get_code( (int) $post->ID );
		$mobile = Ad_Placr_Ad::get_mobile_code( (int) $post->ID );
		$status = Ad_Placr_Ad::normalize_status( get_post_meta( $post->ID, Ad_Placr_Ad::META_STATUS, true ) );
		?>
		<p>
			<label for="ad_placr_code"><strong><?php esc_html_e( 'Universal ad code', 'ad-placr' ); ?></strong></label><br />
			<textarea class="large-text code" rows="8" name="ad_placr_code" id="ad_placr_code"><?php echo esc_textarea( $code ); ?></textarea>
		</p>
		<p>
			<label for="ad_placr_mobile_code"><strong><?php esc_html_e( 'Mobile override code', 'ad-placr' ); ?></strong></label><br />
			<textarea class="large-text code" rows="6" name="ad_placr_mobile_code" id="ad_placr_mobile_code"><?php echo esc_textarea( $mobile ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Optional. When set, CSS swaps to this snippet below the mobile breakpoint.', 'ad-placr' ); ?></span>
		</p>
		<p>
			<label for="ad_placr_status"><strong><?php esc_html_e( 'Status', 'ad-placr' ); ?></strong></label><br />
			<select name="ad_placr_status" id="ad_placr_status">
				<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'ad-placr' ); ?></option>
				<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ad-placr' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Placement position / status / paragraph.
	 *
	 * @since 2.6.0
	 *
	 * @param WP_Post $post Placement post.
	 * @return void
	 */
	public static function render_placement_details_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION_DETAILS, self::NONCE_FIELD_DETAILS );

		$position  = Ad_Placr_Placement::get_position( (int) $post->ID );
		$status    = Ad_Placr_Placement::normalize_status( get_post_meta( $post->ID, Ad_Placr_Placement::META_STATUS, true ) );
		$targeting = Ad_Placr_Placement::get_targeting( (int) $post->ID );
		$paragraph = isset( $targeting['paragraph'] ) ? absint( $targeting['paragraph'] ) : 1;
		if ( $paragraph < 1 ) {
			$paragraph = 1;
		}

		$registry = Ad_Placr_Positions::all();
		?>
		<p>
			<label for="ad_placr_position"><strong><?php esc_html_e( 'Position', 'ad-placr' ); ?></strong></label><br />
			<select name="ad_placr_position" id="ad_placr_position" class="widefat">
				<option value=""><?php esc_html_e( '— Select —', 'ad-placr' ); ?></option>
				<?php foreach ( $registry as $key => $descriptor ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $position, $key ); ?>>
						<?php echo esc_html( (string) $descriptor['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="ad_placr_status"><strong><?php esc_html_e( 'Status', 'ad-placr' ); ?></strong></label><br />
			<select name="ad_placr_status" id="ad_placr_status">
				<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'ad-placr' ); ?></option>
				<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ad-placr' ); ?></option>
			</select>
		</p>
		<p>
			<label for="ad_placr_paragraph"><strong><?php esc_html_e( 'Paragraph index (in-content)', 'ad-placr' ); ?></strong></label><br />
			<input type="number" min="1" max="100" name="ad_placr_paragraph" id="ad_placr_paragraph" value="<?php echo esc_attr( (string) $paragraph ); ?>" />
			<span class="description"><?php esc_html_e( 'Used when position is before/after paragraph.', 'ad-placr' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Weighted ad list repeater.
	 *
	 * @since 2.6.0
	 *
	 * @param WP_Post $post Placement post.
	 * @return void
	 */
	public static function render_placement_ads_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ads_list = Ad_Placr_Placement::get_ads( (int) $post->ID );
		if ( empty( $ads_list ) ) {
			$ads_list = array(
				array(
					'ad_id'  => 0,
					'weight' => 1,
				),
			);
		}

		$published_ads = get_posts(
			array(
				'post_type'      => Ad_Placr_Ad::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p class="description"><?php esc_html_e( 'One ad is chosen per request by weight. Leave a row’s ad blank to ignore it.', 'ad-placr' ); ?></p>
		<table class="widefat striped" style="max-width:640px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ad', 'ad-placr' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Weight', 'ad-placr' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ads_list as $i => $row ) : ?>
					<tr>
						<td>
							<select name="ad_placr_ads[<?php echo esc_attr( (string) $i ); ?>][ad_id]" class="widefat">
								<option value="0"><?php esc_html_e( '— Select —', 'ad-placr' ); ?></option>
								<?php foreach ( $published_ads as $ad_post ) : ?>
									<option value="<?php echo esc_attr( (string) $ad_post->ID ); ?>" <?php selected( (int) $row['ad_id'], (int) $ad_post->ID ); ?>>
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: ad title, 2: ad ID */
												__( '%1$s (#%2$d)', 'ad-placr' ),
												$ad_post->post_title ? $ad_post->post_title : __( '(no title)', 'ad-placr' ),
												(int) $ad_post->ID
											)
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<input type="number" min="1" name="ad_placr_ads[<?php echo esc_attr( (string) $i ); ?>][weight]" value="<?php echo esc_attr( (string) max( 1, (int) $row['weight'] ) ); ?>" style="width:100%;" />
						</td>
					</tr>
				<?php endforeach; ?>
				<?php
				// Extra empty row for adding another ad without JS.
				$next = count( $ads_list );
				?>
				<tr>
					<td>
						<select name="ad_placr_ads[<?php echo esc_attr( (string) $next ); ?>][ad_id]" class="widefat">
							<option value="0"><?php esc_html_e( '— Add ad —', 'ad-placr' ); ?></option>
							<?php foreach ( $published_ads as $ad_post ) : ?>
								<option value="<?php echo esc_attr( (string) $ad_post->ID ); ?>">
									<?php echo esc_html( $ad_post->post_title ? $ad_post->post_title : __( '(no title)', 'ad-placr' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<input type="number" min="1" name="ad_placr_ads[<?php echo esc_attr( (string) $next ); ?>][weight]" value="1" style="width:100%;" />
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save Ad meta.
	 *
	 * @since 2.6.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_ad_details( int $post_id, WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Literal $_POST keys required so WPCS nonce sniff can see verification.
		if ( ! isset( $_POST['ad_placr_details_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['ad_placr_details_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'ad_placr_save_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		$code = isset( $_POST['ad_placr_code'] ) ? Ad_Placr_Settings_Page::sanitize_ad_code( (string) $_POST['ad_placr_code'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitize_ad_code unslashes.
		$mob  = isset( $_POST['ad_placr_mobile_code'] ) ? Ad_Placr_Settings_Page::sanitize_ad_code( (string) $_POST['ad_placr_mobile_code'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$st   = isset( $_POST['ad_placr_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['ad_placr_status'] ) ) : 'inactive';

		update_post_meta( $post_id, Ad_Placr_Ad::META_CODE, $code );
		update_post_meta( $post_id, Ad_Placr_Ad::META_MOBILE_CODE, $mob );
		update_post_meta( $post_id, Ad_Placr_Ad::META_STATUS, Ad_Placr_Ad::normalize_status( $st ) );
	}

	/**
	 * Save Placement position, status, paragraph, weighted ads.
	 *
	 * @since 2.6.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_placement_details( int $post_id, WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Literal $_POST keys required so WPCS nonce sniff can see verification.
		if ( ! isset( $_POST['ad_placr_details_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['ad_placr_details_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'ad_placr_save_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		$position = isset( $_POST['ad_placr_position'] ) ? sanitize_key( wp_unslash( (string) $_POST['ad_placr_position'] ) ) : '';
		$keys     = array_keys( Ad_Placr_Positions::all() );
		if ( ! in_array( $position, $keys, true ) ) {
			$position = '';
		}
		update_post_meta( $post_id, Ad_Placr_Placement::META_POSITION, $position );

		$st = isset( $_POST['ad_placr_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['ad_placr_status'] ) ) : 'inactive';
		update_post_meta( $post_id, Ad_Placr_Placement::META_STATUS, Ad_Placr_Placement::normalize_status( $st ) );

		$targeting = Ad_Placr_Placement::get_targeting( $post_id );
		$paragraph = isset( $_POST['ad_placr_paragraph'] ) ? absint( wp_unslash( $_POST['ad_placr_paragraph'] ) ) : 1;
		if ( $paragraph < 1 ) {
			$paragraph = 1;
		} elseif ( $paragraph > 100 ) {
			$paragraph = 100;
		}
		$targeting['paragraph'] = $paragraph;
		update_post_meta( $post_id, Ad_Placr_Placement::META_TARGETING, $targeting );

		$raw_ads = isset( $_POST['ad_placr_ads'] ) ? wp_unslash( $_POST['ad_placr_ads'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ads     = array();
		if ( is_array( $raw_ads ) ) {
			foreach ( $raw_ads as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$ad_id  = isset( $row['ad_id'] ) ? absint( $row['ad_id'] ) : 0;
				$weight = isset( $row['weight'] ) ? absint( $row['weight'] ) : 1;
				if ( $ad_id < 1 ) {
					continue;
				}
				if ( Ad_Placr_Ad::POST_TYPE !== get_post_type( $ad_id ) ) {
					continue;
				}
				$ads[] = array(
					'ad_id'  => $ad_id,
					'weight' => max( 1, $weight ),
				);
			}
		}
		update_post_meta( $post_id, Ad_Placr_Placement::META_ADS, Ad_Placr_Placement::normalize_ads( $ads ) );
	}

	/**
	 * Ad list columns.
	 *
	 * @since 2.6.0
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function ad_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['ad_placr_status']      = __( 'Status', 'ad-placr' );
				$new['ad_placr_impressions'] = __( 'Impressions', 'ad-placr' );
				$new['ad_placr_clicks']      = __( 'Clicks', 'ad-placr' );
			}
		}

		return $new;
	}

	/**
	 * Render Ad list column.
	 *
	 * @since 2.6.0
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_ad_column( string $column, int $post_id ): void {
		$storage = Ad_Placr_Analytics::is_storage_enabled();

		if ( 'ad_placr_status' === $column ) {
			$status = Ad_Placr_Ad::normalize_status( get_post_meta( $post_id, Ad_Placr_Ad::META_STATUS, true ) );
			echo esc_html( 'active' === $status ? __( 'Active', 'ad-placr' ) : __( 'Inactive', 'ad-placr' ) );
			return;
		}

		if ( 'ad_placr_impressions' === $column ) {
			$count = Ad_Placr_Analytics::count_events( 'impression', $post_id );
			echo esc_html( Ad_Placr_Analytics::format_stat_cell( $count, $storage ) );
			return;
		}

		if ( 'ad_placr_clicks' === $column ) {
			$count = Ad_Placr_Analytics::count_events( 'click', $post_id );
			echo esc_html( Ad_Placr_Analytics::format_stat_cell( $count, $storage ) );
		}
	}

	/**
	 * Placement list columns.
	 *
	 * @since 2.6.0
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function placement_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['ad_placr_position'] = __( 'Position', 'ad-placr' );
				$new['ad_placr_status']   = __( 'Status', 'ad-placr' );
				$new['ad_placr_ads']      = __( 'Ads', 'ad-placr' );
			}
		}

		return $new;
	}

	/**
	 * Render Placement list column.
	 *
	 * @since 2.6.0
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_placement_column( string $column, int $post_id ): void {
		if ( 'ad_placr_position' === $column ) {
			$key   = Ad_Placr_Placement::get_position( $post_id );
			$all   = Ad_Placr_Positions::all();
			$label = ( '' !== $key && isset( $all[ $key ]['label'] ) ) ? (string) $all[ $key ]['label'] : $key;
			echo esc_html( '' !== $label ? $label : '—' );
			return;
		}

		if ( 'ad_placr_status' === $column ) {
			$status = Ad_Placr_Placement::normalize_status( get_post_meta( $post_id, Ad_Placr_Placement::META_STATUS, true ) );
			echo esc_html( 'active' === $status ? __( 'Active', 'ad-placr' ) : __( 'Inactive', 'ad-placr' ) );
			return;
		}

		if ( 'ad_placr_ads' === $column ) {
			echo esc_html( (string) count( Ad_Placr_Placement::get_ads( $post_id ) ) );
		}
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
		// Literal $_POST keys required so WPCS nonce sniff can see verification.
		if ( ! isset( $_POST['ad_placr_targeting_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['ad_placr_targeting_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'ad_placr_save_targeting' ) ) {
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
