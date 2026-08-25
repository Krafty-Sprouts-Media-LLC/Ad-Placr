<?php
/**
 * Unified one-screen Ad editor and Ads list administration.
 *
 * The editor stores one canonical display location, one display-rule array,
 * and one or more independently identified code versions on each Ad.
 *
 * @package AdPlacr
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the complete Ad editor, validation, list columns, and row actions.
 *
 * @since 2.4.0
 */
final class Ad_Placr_Admin {

	/**
	 * Nonce action shared by the four editor sections.
	 *
	 * @since 2.4.0
	 */
	private const NONCE_ACTION = 'ad_placr_save_ad';

	/**
	 * Nonce field shared by the four editor sections.
	 *
	 * @since 2.4.0
	 */
	private const NONCE_FIELD = 'ad_placr_nonce';

	/**
	 * Prevent status corrections from re-entering the Ad save callback.
	 *
	 * @since 2.7.0
	 *
	 * @var bool
	 */
	private static bool $updating_status = false;

	/**
	 * Validation notice appended to the post-save redirect.
	 *
	 * @since 2.7.0
	 *
	 * @var string
	 */
	private static string $save_notice = '';

	/**
	 * Hook the editor, save path, list table, notices, and row actions.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . Ad_Placr_Ad::POST_TYPE, array( __CLASS__, 'save_ad' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notices' ) );
		add_filter( 'redirect_post_location', array( __CLASS__, 'append_save_notice' ) );
		add_filter( 'manage_' . Ad_Placr_Ad::POST_TYPE . '_posts_columns', array( __CLASS__, 'ad_columns' ) );
		add_action( 'manage_' . Ad_Placr_Ad::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_ad_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . Ad_Placr_Ad::POST_TYPE, array( __CLASS__, 'remove_bulk_edit_action' ) );

		add_action( 'admin_action_ad_placr_duplicate', array( __CLASS__, 'handle_duplicate_action' ) );
		add_action( 'admin_action_ad_placr_activate', array( __CLASS__, 'handle_activate_action' ) );
		add_action( 'admin_action_ad_placr_pause', array( __CLASS__, 'handle_pause_action' ) );
	}

	/**
	 * Register the four sections used by the unified Ad editor.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	public static function register_meta_boxes(): void {
		add_meta_box(
			'ad-placr-location',
			__( 'Where should this ad appear?', 'ad-placr' ),
			array( __CLASS__, 'render_location_meta_box' ),
			Ad_Placr_Ad::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'ad-placr-rules',
			__( 'Show this ad on…', 'ad-placr' ),
			array( __CLASS__, 'render_rules_meta_box' ),
			Ad_Placr_Ad::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'ad-placr-code',
			__( 'Ad code', 'ad-placr' ),
			array( __CLASS__, 'render_code_meta_box' ),
			Ad_Placr_Ad::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'ad-placr-stats',
			__( 'Statistics', 'ad-placr' ),
			array( __CLASS__, 'render_statistics_meta_box' ),
			Ad_Placr_Ad::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Enqueue zero-build assets only on unified Ad editor and list screens.
	 *
	 * @since 2.7.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Ad_Placr_Ad::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'ad-placr-admin',
			AD_PLACR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AD_PLACR_VERSION
		);

		wp_enqueue_script(
			'ad-placr-admin',
			AD_PLACR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wp-a11y' ),
			AD_PLACR_VERSION,
			true
		);

		wp_localize_script(
			'ad-placr-admin',
			'adPlacrAdmin',
			array(
				'versionAdded'   => __( 'Ad version added.', 'ad-placr' ),
				'versionLabel'   => __( 'Version', 'ad-placr' ),
				'versionRemoved' => __( 'Ad version removed.', 'ad-placr' ),
			)
		);
	}

	/**
	 * Render status, grouped display locations, and location-specific guidance.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Post $post Current Ad.
	 * @return void
	 */
	public static function render_location_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$raw_position = (string) get_post_meta( $post->ID, Ad_Placr_Ad::META_POSITION, true );
		$position     = Ad_Placr_Positions::exists( $raw_position ) ? $raw_position : '';
		$targeting    = wp_parse_args( Ad_Placr_Ad::get_targeting( (int) $post->ID ), self::default_targeting() );
		$paragraph    = max( 1, min( 100, absint( $targeting['paragraph'] ) ) );
		$slot_id      = isset( $targeting['slot_id'] ) ? sanitize_key( (string) $targeting['slot_id'] ) : '';
		$groups       = self::grouped_positions();
		?>
		<div class="ad-placr-editor-section">
			<div class="ad-placr-field">
				<label for="ad-placr-position"><strong><?php esc_html_e( 'Display location', 'ad-placr' ); ?></strong></label>
				<select name="ad_placr_position" id="ad-placr-position" class="widefat" data-ad-placr-location>
					<option value="" data-context=""><?php esc_html_e( 'Choose a display location', 'ad-placr' ); ?></option>
					<?php
					$all_positions = Ad_Placr_Positions::all();
					foreach ( $groups as $group_label => $options ) :
						?>
						<optgroup label="<?php echo esc_attr( $group_label ); ?>">
							<?php
							foreach ( $options as $key => $label ) :
								$context = isset( $all_positions[ $key ]['context'] ) ? (string) $all_positions[ $key ]['context'] : '';
								?>
								<option value="<?php echo esc_attr( $key ); ?>" data-context="<?php echo esc_attr( $context ); ?>" <?php selected( $position, $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</div>

			<?php if ( '' !== $raw_position && '' === $position ) : ?>
				<div class="notice notice-error inline">
					<p><?php esc_html_e( 'This Ad has an unrecognized display location. Choose a location before activating it.', 'ad-placr' ); ?></p>
				</div>
			<?php endif; ?>

			<div
				class="ad-placr-location-control"
				data-ad-placr-location-control="in_content_before_paragraph in_content_after_paragraph"
				hidden
			>
				<label for="ad-placr-paragraph"><strong><?php esc_html_e( 'Paragraph number', 'ad-placr' ); ?></strong></label>
				<input type="number" min="1" max="100" step="1" name="ad_placr_paragraph" id="ad-placr-paragraph" value="<?php echo esc_attr( (string) $paragraph ); ?>" />
				<p class="description"><?php esc_html_e( 'Count paragraphs from the beginning of the main post content.', 'ad-placr' ); ?></p>
			</div>

			<div
				class="ad-placr-location-control"
				data-ad-placr-location-control="manual_shortcode manual_block"
				hidden
			>
				<p>
					<?php esc_html_e( 'Place this Ad in content with:', 'ad-placr' ); ?>
					<code>[ad_placr ad="<?php echo esc_html( (string) $post->ID ); ?>"]</code>
				</p>
				<p class="description"><?php esc_html_e( 'You can also choose this Ad from the Ad Placr block when it is available in the editor.', 'ad-placr' ); ?></p>
			</div>

			<div
				class="ad-placr-location-control"
				data-ad-placr-location-control="sidebar_widget"
				hidden
			>
				<p><?php esc_html_e( 'Open Appearance > Widgets, add the Ad Placr widget, and choose this Ad.', 'ad-placr' ); ?></p>
			</div>

			<input type="hidden" name="ad_placr_slot_id" value="<?php echo esc_attr( $slot_id ); ?>" />

			<?php if ( self::has_duplicate_automatic_location( (int) $post->ID, $position ) ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Another Active Ad uses this display location. Both Ads may appear. To show only one result, put the code choices into one Ad as versions.', 'ad-placr' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the common single-code path and progressively disclosed versions.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Post $post Current Ad.
	 * @return void
	 */
	public static function render_code_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			return;
		}

		$versions = Ad_Placr_Ad::get_versions( (int) $post->ID );
		if ( empty( $versions ) ) {
			$versions = self::normalize_version_rows(
				array(
					array(
						'version_id'  => '',
						'name'        => '',
						'code'        => '',
						'mobile_code' => '',
						'weight'      => 1,
						'enabled'     => 1,
					),
				),
				true
			);
		}

		$multiple = count( $versions ) > 1;
		?>
		<div class="ad-placr-versions" data-ad-placr-versions data-multiple="<?php echo esc_attr( $multiple ? '1' : '0' ); ?>">
			<p class="description ad-placr-common-code-help">
				<?php esc_html_e( 'Paste the code provided by your ad network. Add mobile code only when the network gives you a separate mobile version.', 'ad-placr' ); ?>
			</p>
			<div class="ad-placr-version-list" data-ad-placr-version-list>
				<?php foreach ( $versions as $index => $version ) : ?>
					<?php self::render_version_row( (string) $index, $version ); ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button button-secondary" data-ad-placr-add-version>
					<?php esc_html_e( 'Add another ad version', 'ad-placr' ); ?>
				</button>
			</p>
			<template data-ad-placr-version-template>
				<?php
				self::render_version_row(
					'__INDEX__',
					array(
						'version_id'  => '',
						'name'        => '',
						'code'        => '',
						'mobile_code' => '',
						'weight'      => 1,
						'enabled'     => true,
					)
				);
				?>
			</template>
		</div>
		<?php
	}

	/**
	 * Render one complete version row.
	 *
	 * @since 2.7.0
	 *
	 * @param string               $index   Form row index or template placeholder.
	 * @param array<string, mixed> $version Normalized version values.
	 * @return void
	 */
	private static function render_version_row( string $index, array $version ): void {
		$base       = 'ad_placr_versions[' . $index . ']';
		$version_id = isset( $version['version_id'] ) ? (string) $version['version_id'] : '';
		$name       = isset( $version['name'] ) ? (string) $version['name'] : '';
		$code       = isset( $version['code'] ) ? (string) $version['code'] : '';
		$mobile     = isset( $version['mobile_code'] ) ? (string) $version['mobile_code'] : '';
		$weight     = max( 1, isset( $version['weight'] ) ? (int) $version['weight'] : 1 );
		$enabled    = ! isset( $version['enabled'] ) || ! empty( $version['enabled'] );
		$id_suffix  = sanitize_html_class( $index );
		?>
		<section class="ad-placr-version" data-ad-placr-version-row>
			<input type="hidden" name="<?php echo esc_attr( $base ); ?>[version_id]" value="<?php echo esc_attr( $version_id ); ?>" data-ad-placr-version-id />
			<div class="ad-placr-version-heading ad-placr-version-advanced">
				<strong data-ad-placr-version-heading><?php echo esc_html( '' !== $name ? $name : __( 'Ad version', 'ad-placr' ) ); ?></strong>
				<button type="button" class="button-link-delete" data-ad-placr-remove-version><?php esc_html_e( 'Remove', 'ad-placr' ); ?></button>
			</div>
			<div class="ad-placr-version-grid">
				<div class="ad-placr-field ad-placr-version-advanced">
					<label for="ad-placr-version-name-<?php echo esc_attr( $id_suffix ); ?>"><strong><?php esc_html_e( 'Version name', 'ad-placr' ); ?></strong></label>
					<input
						type="text"
						class="widefat"
						id="ad-placr-version-name-<?php echo esc_attr( $id_suffix ); ?>"
						name="<?php echo esc_attr( $base ); ?>[name]"
						value="<?php echo esc_attr( $name ); ?>"
						data-ad-placr-version-name
					/>
				</div>
				<div class="ad-placr-field ad-placr-version-advanced">
					<label for="ad-placr-version-weight-<?php echo esc_attr( $id_suffix ); ?>"><strong><?php esc_html_e( 'How often should this version appear?', 'ad-placr' ); ?></strong></label>
					<div class="ad-placr-weight-control">
						<input
							type="number"
							min="1"
							step="1"
							id="ad-placr-version-weight-<?php echo esc_attr( $id_suffix ); ?>"
							name="<?php echo esc_attr( $base ); ?>[weight]"
							value="<?php echo esc_attr( (string) $weight ); ?>"
							data-ad-placr-version-weight
						/>
						<output data-ad-placr-version-share>0%</output>
					</div>
				</div>
				<div class="ad-placr-field ad-placr-version-code">
					<label for="ad-placr-version-code-<?php echo esc_attr( $id_suffix ); ?>"><strong><?php esc_html_e( 'Ad code', 'ad-placr' ); ?></strong></label>
					<textarea
						class="large-text code"
						rows="8"
						id="ad-placr-version-code-<?php echo esc_attr( $id_suffix ); ?>"
						name="<?php echo esc_attr( $base ); ?>[code]"
						data-ad-placr-version-code
					><?php echo esc_textarea( $code ); ?></textarea>
				</div>
				<div class="ad-placr-field ad-placr-version-code">
					<label for="ad-placr-version-mobile-<?php echo esc_attr( $id_suffix ); ?>"><strong><?php esc_html_e( 'Mobile ad code (optional)', 'ad-placr' ); ?></strong></label>
					<textarea
						class="large-text code"
						rows="8"
						id="ad-placr-version-mobile-<?php echo esc_attr( $id_suffix ); ?>"
						name="<?php echo esc_attr( $base ); ?>[mobile_code]"
						data-ad-placr-version-mobile
					><?php echo esc_textarea( $mobile ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Leave empty to use the main ad code on phones.', 'ad-placr' ); ?></p>
				</div>
			</div>
			<p class="ad-placr-version-advanced">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> data-ad-placr-version-enabled />
					<?php esc_html_e( 'Use this version', 'ad-placr' ); ?>
				</label>
			</p>
		</section>
		<?php
	}

	/**
	 * Render progressively disclosed page, visitor, device, URL, and schedule rules.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Post $post Current Ad.
	 * @return void
	 */
	public static function render_rules_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			return;
		}

		$targeting    = wp_parse_args( Ad_Placr_Ad::get_targeting( (int) $post->ID ), self::default_targeting() );
		$schedule     = isset( $targeting['schedule'] ) && is_array( $targeting['schedule'] )
			? wp_parse_args(
				$targeting['schedule'],
				array(
					'start' => '',
					'end'   => '',
				)
			)
			: array(
				'start' => '',
				'end'   => '',
			);
		$contexts     = isset( $targeting['contexts'] ) && is_array( $targeting['contexts'] ) ? $targeting['contexts'] : array();
		$post_types   = isset( $targeting['post_types'] ) && is_array( $targeting['post_types'] ) ? $targeting['post_types'] : array();
		$devices      = isset( $targeting['devices'] ) && is_array( $targeting['devices'] )
			? $targeting['devices']
			: array( 'desktop', 'tablet', 'mobile' );
		$user         = isset( $targeting['user'] ) ? (string) $targeting['user'] : 'any';
		$url_lines    = isset( $targeting['url_contains'] ) && is_array( $targeting['url_contains'] )
			? implode( "\n", array_map( 'strval', $targeting['url_contains'] ) )
			: '';
		$categories   = isset( $targeting['include_categories'] ) && is_array( $targeting['include_categories'] )
			? implode( ', ', array_map( 'strval', $targeting['include_categories'] ) )
			: '';
		$tags         = isset( $targeting['include_tags'] ) && is_array( $targeting['include_tags'] )
			? implode( ', ', array_map( 'strval', $targeting['include_tags'] ) )
			: '';
		$notes        = (string) get_post_meta( $post->ID, Ad_Placr_Ad::META_NOTES, true );
		$type_choices = get_post_types( array( 'public' => true ), 'objects' );
		$has_rules    = self::targeting_has_limits( $targeting );

		$context_choices = array(
			'singular'   => __( 'Individual posts and pages', 'ad-placr' ),
			'front_page' => __( 'Front page', 'ad-placr' ),
			'blog_index' => __( 'Blog page', 'ad-placr' ),
			'archive'    => __( 'Archive pages', 'ad-placr' ),
			'search'     => __( 'Search results', 'ad-placr' ),
		);
		?>
		<p class="description"><?php esc_html_e( 'By default, this Ad can appear everywhere its display location is available.', 'ad-placr' ); ?></p>
		<details class="ad-placr-rules" <?php echo $has_rules ? 'open' : ''; ?>>
			<summary><?php esc_html_e( 'Only show on specific pages, devices, or schedules', 'ad-placr' ); ?></summary>
			<div class="ad-placr-rules-grid">
				<fieldset class="ad-placr-field" data-ad-placr-contexts-fieldset>
					<legend><strong><?php esc_html_e( 'Types of pages', 'ad-placr' ); ?></strong></legend>
					<p class="description"><?php esc_html_e( 'Leave every option clear to allow all page types.', 'ad-placr' ); ?></p>
					<?php foreach ( $context_choices as $key => $label ) : ?>
						<label class="ad-placr-check">
							<input type="checkbox" name="ad_placr_contexts[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $contexts, true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<fieldset class="ad-placr-field">
					<legend><strong><?php esc_html_e( 'Content types', 'ad-placr' ); ?></strong></legend>
					<p class="description"><?php esc_html_e( 'Applies when individual posts and pages are selected.', 'ad-placr' ); ?></p>
					<?php foreach ( $type_choices as $type ) : ?>
						<label class="ad-placr-check">
							<input type="checkbox" name="ad_placr_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $post_types, true ) ); ?> />
							<?php echo esc_html( $type->labels->singular_name ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<div class="ad-placr-field">
					<label for="ad-placr-user"><strong><?php esc_html_e( 'Visitors', 'ad-placr' ); ?></strong></label>
					<select name="ad_placr_user" id="ad-placr-user">
						<option value="any" <?php selected( $user, 'any' ); ?>><?php esc_html_e( 'Everyone', 'ad-placr' ); ?></option>
						<option value="logged_in" <?php selected( $user, 'logged_in' ); ?>><?php esc_html_e( 'Logged-in visitors', 'ad-placr' ); ?></option>
						<option value="guest" <?php selected( $user, 'guest' ); ?>><?php esc_html_e( 'Logged-out visitors', 'ad-placr' ); ?></option>
					</select>
				</div>

				<fieldset class="ad-placr-field">
					<legend><strong><?php esc_html_e( 'Screen sizes', 'ad-placr' ); ?></strong></legend>
					<?php
					$device_choices = array(
						'desktop' => __( 'Desktop', 'ad-placr' ),
						'tablet'  => __( 'Tablet', 'ad-placr' ),
						'mobile'  => __( 'Mobile', 'ad-placr' ),
					);
					?>
					<?php foreach ( $device_choices as $key => $label ) : ?>
						<label class="ad-placr-check">
							<input type="checkbox" name="ad_placr_devices[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $devices, true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<div class="ad-placr-field">
					<label for="ad-placr-url-contains"><strong><?php esc_html_e( 'URL contains', 'ad-placr' ); ?></strong></label>
					<textarea class="large-text" rows="3" name="ad_placr_url_contains" id="ad-placr-url-contains"><?php echo esc_textarea( $url_lines ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter one path or phrase per line. Any matching line can show the Ad.', 'ad-placr' ); ?></p>
				</div>

				<div class="ad-placr-field">
					<label for="ad-placr-categories"><strong><?php esc_html_e( 'Only in category IDs', 'ad-placr' ); ?></strong></label>
					<input type="text" class="widefat" name="ad_placr_include_categories" id="ad-placr-categories" value="<?php echo esc_attr( $categories ); ?>" />
					<p class="description"><?php esc_html_e( 'Separate multiple numbers with commas.', 'ad-placr' ); ?></p>
				</div>

				<div class="ad-placr-field">
					<label for="ad-placr-tags"><strong><?php esc_html_e( 'Only with tag IDs', 'ad-placr' ); ?></strong></label>
					<input type="text" class="widefat" name="ad_placr_include_tags" id="ad-placr-tags" value="<?php echo esc_attr( $tags ); ?>" />
					<p class="description"><?php esc_html_e( 'Separate multiple numbers with commas.', 'ad-placr' ); ?></p>
				</div>

				<div class="ad-placr-field">
					<label for="ad-placr-schedule-start"><strong><?php esc_html_e( 'Start showing', 'ad-placr' ); ?></strong></label>
					<input type="text" class="widefat" name="ad_placr_schedule_start" id="ad-placr-schedule-start" value="<?php echo esc_attr( (string) $schedule['start'] ); ?>" placeholder="YYYY-MM-DD HH:MM:SS" />
				</div>

				<div class="ad-placr-field">
					<label for="ad-placr-schedule-end"><strong><?php esc_html_e( 'Stop showing', 'ad-placr' ); ?></strong></label>
					<input type="text" class="widefat" name="ad_placr_schedule_end" id="ad-placr-schedule-end" value="<?php echo esc_attr( (string) $schedule['end'] ); ?>" placeholder="YYYY-MM-DD HH:MM:SS" />
				</div>
			</div>
		</details>

		<div class="ad-placr-field ad-placr-notes">
			<label for="ad-placr-notes"><strong><?php esc_html_e( 'Private notes (optional)', 'ad-placr' ); ?></strong></label>
			<textarea class="large-text" rows="3" name="ad_placr_notes" id="ad-placr-notes"><?php echo esc_textarea( $notes ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Visible only to people who can manage Ads.', 'ad-placr' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render aggregate statistics and optional per-version figures.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Post $post Current Ad.
	 * @return void
	 */
	public static function render_statistics_meta_box( WP_Post $post ): void {
		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			return;
		}

		$storage     = Ad_Placr_Analytics::is_storage_enabled();
		$impressions = Ad_Placr_Analytics::count_events( 'impression', (int) $post->ID );
		$clicks      = Ad_Placr_Analytics::count_events( 'click', (int) $post->ID );
		$versions    = Ad_Placr_Ad::get_versions( (int) $post->ID );
		?>
		<div class="ad-placr-stat-summary">
			<div>
				<span><?php esc_html_e( 'Impressions', 'ad-placr' ); ?></span>
				<strong><?php echo esc_html( Ad_Placr_Analytics::format_stat_cell( $impressions, $storage ) ); ?></strong>
			</div>
			<div>
				<span><?php esc_html_e( 'Clicks', 'ad-placr' ); ?></span>
				<strong><?php echo esc_html( Ad_Placr_Analytics::format_stat_cell( $clicks, $storage ) ); ?></strong>
			</div>
			<div>
				<span><?php esc_html_e( 'CTR', 'ad-placr' ); ?></span>
				<strong><?php echo esc_html( self::format_ctr( $impressions, $clicks, $storage ) ); ?></strong>
			</div>
		</div>
		<?php if ( ! $storage ) : ?>
			<p class="description">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Settings page URL. */
						__( 'Statistics storage is off. <a href="%s">Turn it on in Settings</a> to collect totals.', 'ad-placr' ),
						esc_url( admin_url( 'edit.php?post_type=' . Ad_Placr_Ad::POST_TYPE . '&page=ad-placr' ) )
					),
					array(
						'a' => array( 'href' => array() ),
					)
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( count( $versions ) > 1 ) : ?>
			<table class="widefat striped ad-placr-version-stats">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Ad version', 'ad-placr' ); ?></th>
						<th><?php esc_html_e( 'Impressions', 'ad-placr' ); ?></th>
						<th><?php esc_html_e( 'Clicks', 'ad-placr' ); ?></th>
						<th><?php esc_html_e( 'CTR', 'ad-placr' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $versions as $index => $version ) : ?>
						<?php
						$version_impressions = Ad_Placr_Analytics::count_events( 'impression', (int) $post->ID, $version['version_id'] );
						$version_clicks      = Ad_Placr_Analytics::count_events( 'click', (int) $post->ID, $version['version_id'] );
						$version_name        = '' !== $version['name']
							? $version['name']
							: self::default_version_name( (int) $index );
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $version_name ); ?></th>
							<td><?php echo esc_html( Ad_Placr_Analytics::format_stat_cell( $version_impressions, $storage ) ); ?></td>
							<td><?php echo esc_html( Ad_Placr_Analytics::format_stat_cell( $version_clicks, $storage ) ); ?></td>
							<td><?php echo esc_html( self::format_ctr( $version_impressions, $version_clicks, $storage ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Normalize submitted version rows into safe, stable storage.
	 *
	 * Privileged users retain ad-network markup verbatim. Other users are
	 * restricted to post-safe HTML. Existing valid IDs survive; only absent,
	 * invalid, or duplicate IDs are replaced.
	 *
	 * @since 2.7.0
	 *
	 * @param array<int|string, mixed> $posted              Submitted version rows after wp_unslash().
	 * @param bool                     $can_unfiltered_html  Whether ad-network scripts may be stored.
	 * @param callable|null            $id_generator         Optional ID generator seam for tests.
	 * @return array<int, array{version_id:string, name:string, code:string, mobile_code:string, weight:int, enabled:bool}>
	 */
	public static function normalize_version_rows( array $posted, bool $can_unfiltered_html, ?callable $id_generator = null ): array {
		$id_generator = $id_generator ?? static fn(): string => wp_generate_uuid4();
		$versions     = array();
		$used_ids     = array();

		foreach ( $posted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$version_id = isset( $row['version_id'] ) ? self::normalize_version_id( (string) $row['version_id'] ) : '';
			if ( '' === $version_id || isset( $used_ids[ $version_id ] ) ) {
				$version_id = self::generate_unique_version_id( $id_generator, $used_ids );
			}
			$used_ids[ $version_id ] = true;

			$name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			if ( '' === $name ) {
				$name = self::default_version_name( count( $versions ) );
			}

			$code   = isset( $row['code'] ) ? (string) $row['code'] : '';
			$mobile = isset( $row['mobile_code'] ) ? (string) $row['mobile_code'] : '';
			if ( ! $can_unfiltered_html ) {
				$code   = wp_kses_post( $code );
				$mobile = wp_kses_post( $mobile );
			}

			$versions[] = array(
				'version_id'  => $version_id,
				'name'        => $name,
				'code'        => $code,
				'mobile_code' => $mobile,
				'weight'      => max( 1, isset( $row['weight'] ) ? (int) $row['weight'] : 1 ),
				'enabled'     => ! empty( $row['enabled'] ),
			);
		}

		return $versions;
	}

	/**
	 * Return corrections required before an Ad can become Active.
	 *
	 * @since 2.7.0
	 *
	 * @param string                           $position Canonical display-location key, or empty.
	 * @param array<int, array<string, mixed>> $versions Submitted or stored versions.
	 * @return string[]
	 */
	public static function activation_errors( string $position, array $versions ): array {
		$errors = array();

		if ( '' === $position ) {
			$errors[] = __( 'Choose where this ad should appear before activating it.', 'ad-placr' );
		}

		if ( empty( Ad_Placr_Ad::eligible_versions( $versions ) ) ) {
			$errors[] = __( 'Add your ad code before activating this Ad.', 'ad-placr' );
		}

		return $errors;
	}

	/**
	 * Apply activation validation only when Active status is requested.
	 *
	 * @since 2.7.0
	 *
	 * @param string                           $requested_status Requested WordPress post status.
	 * @param string                           $position         Canonical display-location key, or empty.
	 * @param array<int, array<string, mixed>> $versions         Submitted or stored versions.
	 * @return string[]
	 */
	public static function save_errors( string $requested_status, string $position, array $versions ): array {
		if ( 'publish' !== $requested_status ) {
			return array();
		}

		return self::activation_errors( $position, $versions );
	}

	/**
	 * Resolve the requested Active/Paused state for a save.
	 *
	 * Prefers the native post_status field so the standard publish box drives
	 * status; falls back to the legacy custom field during transitions.
	 *
	 * @since 2.8.0
	 *
	 * @param string|null $post_status Native WordPress post status, when present.
	 * @param string|null $legacy      Legacy ad_placr_status value, when present.
	 * @return string Either publish or draft.
	 */
	public static function normalize_requested_status( ?string $post_status, ?string $legacy ): string {
		$candidate = ( null !== $post_status && '' !== $post_status ) ? $post_status : (string) $legacy;

		return 'publish' === sanitize_key( $candidate ) ? 'publish' : 'draft';
	}

	/**
	 * Save all unified Ad fields through one guarded callback.
	 *
	 * @since 2.7.0
	 *
	 * @param int     $post_id Current Ad ID.
	 * @param WP_Post $post    Current Ad object.
	 * @return void
	 */
	public static function save_ad( int $post_id, WP_Post $post ): void {
		if ( self::$updating_status ) {
			return;
		}

		if ( ! isset( $_POST['ad_placr_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['ad_placr_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'ad_placr_save_ad' ) ) {
			return;
		}

		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( Ad_Placr_Ad::POST_TYPE !== $post->post_type ) {
			return;
		}

		$position = isset( $_POST['ad_placr_position'] )
			? sanitize_key( wp_unslash( (string) $_POST['ad_placr_position'] ) )
			: '';
		if ( ! Ad_Placr_Positions::exists( $position ) ) {
			$position = '';
		}

		$posted_versions = isset( $_POST['ad_placr_versions'] )
			? wp_unslash( $_POST['ad_placr_versions'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each row is normalized below.
			: array();
		$posted_versions = is_array( $posted_versions ) ? $posted_versions : array();
		$versions        = self::normalize_version_rows( $posted_versions, current_user_can( 'unfiltered_html' ) );
		$targeting       = self::normalize_targeting_request();

		/*
		 * Clear page-type contexts when the position already implies a specific
		 * context. Only global positions (header, footer, sticky) benefit from
		 * page-type filtering — all other positions are scoped by design, and
		 * stale checkbox values from a previous selection would be misleading.
		 */
		if ( '' !== $position ) {
			$all_positions    = Ad_Placr_Positions::all();
			$position_context = isset( $all_positions[ $position ]['context'] )
				? (string) $all_positions[ $position ]['context']
				: '';

			if ( 'global' !== $position_context ) {
				$targeting['contexts'] = array();
			}
		}
		$notes            = isset( $_POST['ad_placr_notes'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['ad_placr_notes'] ) )
			: '';
		$legacy_status    = isset( $_POST['ad_placr_status'] )
			? sanitize_key( wp_unslash( (string) $_POST['ad_placr_status'] ) )
			: '';
		$native_status    = isset( $_POST['post_status'] )
			? sanitize_key( wp_unslash( (string) $_POST['post_status'] ) )
			: '';
		$requested_status = self::normalize_requested_status(
			'' !== $native_status ? $native_status : null,
			'' !== $legacy_status ? $legacy_status : null
		);

		update_post_meta( $post_id, Ad_Placr_Ad::META_POSITION, $position );
		update_post_meta( $post_id, Ad_Placr_Ad::META_VERSIONS, $versions );
		update_post_meta( $post_id, Ad_Placr_Ad::META_TARGETING, $targeting );
		update_post_meta( $post_id, Ad_Placr_Ad::META_NOTES, $notes );

		$errors       = self::save_errors( $requested_status, $position, $versions );
		$final_status = empty( $errors ) ? $requested_status : 'draft';
		if ( ! empty( $errors ) ) {
			self::$save_notice = in_array(
				__( 'Choose where this ad should appear before activating it.', 'ad-placr' ),
				$errors,
				true
			) ? 'missing_location' : 'missing_code';
		}

		if ( get_post_status( $post_id ) !== $final_status ) {
			self::update_post_status( $post_id, $final_status );
		}
	}

	/**
	 * Append an activation correction to the normal editor redirect.
	 *
	 * @since 2.7.0
	 *
	 * @param string $location Original post-save redirect.
	 * @return string
	 */
	public static function append_save_notice( string $location ): string {
		if ( '' === self::$save_notice ) {
			return $location;
		}

		$location          = add_query_arg( 'ad_placr_notice', self::$save_notice, $location );
		self::$save_notice = '';

		return $location;
	}

	/**
	 * Add independent Duplicate and status row actions to Ads.
	 *
	 * @since 2.7.0
	 *
	 * @param array<string, string> $actions Existing native row actions.
	 * @param WP_Post               $post    Row post.
	 * @return array<string, string>
	 */
	public static function row_actions( array $actions, WP_Post $post ): array {
		if ( Ad_Placr_Ad::POST_TYPE !== $post->post_type || ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			return $actions;
		}

		$actions = self::remove_unvalidated_status_actions( $actions );

		if ( 'trash' === $post->post_status ) {
			return $actions;
		}

		$duplicate_url                 = wp_nonce_url(
			admin_url( 'admin.php?action=ad_placr_duplicate&ad=' . (int) $post->ID ),
			'ad_placr_duplicate_' . (int) $post->ID
		);
		$actions['ad_placr_duplicate'] = '<a href="' . esc_url( $duplicate_url ) . '">' . esc_html__( 'Duplicate', 'ad-placr' ) . '</a>';

		if ( 'publish' === $post->post_status ) {
			$pause_url                  = wp_nonce_url(
				admin_url( 'admin.php?action=ad_placr_pause&ad=' . (int) $post->ID ),
				'ad_placr_pause_' . (int) $post->ID
			);
			$actions['ad_placr_status'] = '<a href="' . esc_url( $pause_url ) . '">' . esc_html__( 'Pause', 'ad-placr' ) . '</a>';
		} else {
			$activate_url               = wp_nonce_url(
				admin_url( 'admin.php?action=ad_placr_activate&ad=' . (int) $post->ID ),
				'ad_placr_activate_' . (int) $post->ID
			);
			$actions['ad_placr_status'] = '<a href="' . esc_url( $activate_url ) . '">' . esc_html__( 'Activate', 'ad-placr' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Remove Quick Edit because it cannot run editor validation.
	 *
	 * Quick Edit does not submit the unified editor nonce or complete fields.
	 * The ordinary Edit link remains alongside the validated Activate row action.
	 *
	 * @since 2.7.0
	 *
	 * @param array<string, string> $actions Native row or bulk actions.
	 * @return array<string, string>
	 */
	public static function remove_unvalidated_status_actions( array $actions ): array {
		unset( $actions['inline hide-if-no-js'] );

		return $actions;
	}

	/**
	 * Remove Bulk Edit because it can publish without complete editor fields.
	 *
	 * @since 2.7.0
	 *
	 * @param array<string, string> $actions Native bulk actions.
	 * @return array<string, string>
	 */
	public static function remove_bulk_edit_action( array $actions ): array {
		unset( $actions['edit'] );

		return $actions;
	}

	/**
	 * Create an independent Paused copy of one Ad.
	 *
	 * @since 2.7.0
	 *
	 * @param int $source_id Source Ad ID.
	 * @return int|WP_Error New Ad ID or an insertion/validation error.
	 */
	public static function duplicate_ad( int $source_id ): int|WP_Error {
		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			return new WP_Error( 'ad_placr_forbidden', __( 'You are not allowed to duplicate Ads.', 'ad-placr' ) );
		}

		$source = get_post( $source_id );
		if ( ! $source instanceof WP_Post || Ad_Placr_Ad::POST_TYPE !== $source->post_type ) {
			return new WP_Error( 'ad_placr_invalid_ad', __( 'The Ad could not be found.', 'ad-placr' ) );
		}

		$source_title = '' !== trim( $source->post_title ) ? $source->post_title : __( 'Ad', 'ad-placr' );
		$new_id       = wp_insert_post(
			array(
				'post_type'   => Ad_Placr_Ad::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => wp_slash(
					sprintf(
						/* translators: %s: source Ad name. */
						__( '%s — Copy', 'ad-placr' ),
						$source_title
					)
				),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$versions = Ad_Placr_Ad::get_versions( $source_id );
		foreach ( $versions as &$version ) {
			$version['version_id'] = wp_generate_uuid4();
		}
		unset( $version );

		update_post_meta( $new_id, Ad_Placr_Ad::META_POSITION, Ad_Placr_Ad::get_position( $source_id ) );
		update_post_meta( $new_id, Ad_Placr_Ad::META_TARGETING, Ad_Placr_Ad::get_targeting( $source_id ) );
		update_post_meta( $new_id, Ad_Placr_Ad::META_NOTES, (string) get_post_meta( $source_id, Ad_Placr_Ad::META_NOTES, true ) );
		update_post_meta( $new_id, Ad_Placr_Ad::META_VERSIONS, $versions );

		return (int) $new_id;
	}

	/**
	 * Process the nonce-protected Duplicate row action.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function handle_duplicate_action(): void {
		$ad_id  = self::validate_row_action( 'duplicate' );
		$new_id = self::duplicate_ad( $ad_id );
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		$url = add_query_arg(
			'ad_placr_notice',
			'duplicated',
			get_edit_post_link( $new_id, 'url' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Process the nonce-protected Activate row action through shared validation.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function handle_activate_action(): void {
		$ad_id  = self::validate_row_action( 'activate' );
		$errors = self::activation_errors( Ad_Placr_Ad::get_position( $ad_id ), Ad_Placr_Ad::get_versions( $ad_id ) );
		$notice = 'activated';

		if ( ! empty( $errors ) ) {
			self::update_post_status( $ad_id, 'draft' );
			$notice = in_array(
				__( 'Choose where this ad should appear before activating it.', 'ad-placr' ),
				$errors,
				true
			) ? 'missing_location' : 'missing_code';
		} else {
			self::update_post_status( $ad_id, 'publish' );
		}

		$url = add_query_arg( 'ad_placr_notice', $notice, get_edit_post_link( $ad_id, 'url' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Process the nonce-protected Pause row action.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function handle_pause_action(): void {
		$ad_id = self::validate_row_action( 'pause' );
		self::update_post_status( $ad_id, 'draft' );

		$url = add_query_arg( 'ad_placr_notice', 'paused', get_edit_post_link( $ad_id, 'url' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render correction and completion notices from save and row actions.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function render_admin_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only completion notice selected from a fixed allow-list.
		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) || ! isset( $_GET['ad_placr_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( (string) $_GET['ad_placr_notice'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'missing_location' => array( 'error', __( 'Choose where this ad should appear before activating it.', 'ad-placr' ) ),
			'missing_code'     => array( 'error', __( 'Add your ad code before activating this Ad.', 'ad-placr' ) ),
			'duplicated'       => array( 'success', __( 'The Ad was duplicated as an independent Paused copy.', 'ad-placr' ) ),
			'activated'        => array( 'success', __( 'The Ad is now Active.', 'ad-placr' ) ),
			'paused'           => array( 'success', __( 'The Ad is now Paused.', 'ad-placr' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		$type    = (string) $messages[ $notice ][0];
		$message = (string) $messages[ $notice ][1];
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Return deterministic Ads list columns.
	 *
	 * @since 2.6.0
	 *
	 * @param array<string, string> $columns Native columns.
	 * @return array<string, string>
	 */
	public static function ad_columns( array $columns ): array {
		return array(
			'cb'          => $columns['cb'],
			'title'       => __( 'Name', 'ad-placr' ),
			'location'    => __( 'Display location', 'ad-placr' ),
			'status'      => __( 'Status', 'ad-placr' ),
			'versions'    => __( 'Ad versions', 'ad-placr' ),
			'impressions' => __( 'Impressions', 'ad-placr' ),
			'clicks'      => __( 'Clicks', 'ad-placr' ),
			'ctr'         => __( 'CTR', 'ad-placr' ),
			'date'        => $columns['date'],
		);
	}

	/**
	 * Render one deterministic Ads list cell.
	 *
	 * @since 2.6.0
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Ad ID.
	 * @return void
	 */
	public static function render_ad_column( string $column, int $post_id ): void {
		if ( 'location' === $column ) {
			$position = Ad_Placr_Ad::get_position( $post_id );
			echo esc_html( '' !== $position ? Ad_Placr_Positions::label( $position ) : '—' );
			return;
		}

		if ( 'status' === $column ) {
			echo esc_html( 'publish' === get_post_status( $post_id ) ? __( 'Active', 'ad-placr' ) : __( 'Paused', 'ad-placr' ) );
			return;
		}

		if ( 'versions' === $column ) {
			echo esc_html( (string) count( Ad_Placr_Ad::eligible_versions( Ad_Placr_Ad::get_versions( $post_id ) ) ) );
			return;
		}

		if ( ! in_array( $column, array( 'impressions', 'clicks', 'ctr' ), true ) ) {
			return;
		}

		$storage     = Ad_Placr_Analytics::is_storage_enabled();
		$impressions = Ad_Placr_Analytics::count_events( 'impression', $post_id );
		$clicks      = Ad_Placr_Analytics::count_events( 'click', $post_id );

		if ( 'impressions' === $column ) {
			echo esc_html( Ad_Placr_Analytics::format_stat_cell( $impressions, $storage ) );
		} elseif ( 'clicks' === $column ) {
			echo esc_html( Ad_Placr_Analytics::format_stat_cell( $clicks, $storage ) );
		} else {
			echo esc_html( self::format_ctr( $impressions, $clicks, $storage ) );
		}
	}

	/**
	 * Group the filtered position registry for an understandable selector.
	 *
	 * @since 2.7.0
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function grouped_positions(): array {
		$group_labels = array(
			'in_content' => __( 'Inside post content', 'ad-placr' ),
			'content'    => __( 'Around post content', 'ad-placr' ),
			'structure'  => __( 'Site layout', 'ad-placr' ),
			'sticky'     => __( 'Sticky Ads', 'ad-placr' ),
			'listing'    => __( 'Listing pages', 'ad-placr' ),
			'manual'     => __( 'Place it yourself', 'ad-placr' ),
		);
		$groups       = array();

		foreach ( Ad_Placr_Positions::all() as $key => $descriptor ) {
			if ( ! is_array( $descriptor ) || ! Ad_Placr_Positions::exists( (string) $key ) ) {
				continue;
			}

			$group       = sanitize_key( (string) $descriptor['group'] );
			$group_label = $group_labels[ $group ] ?? __( 'Other locations', 'ad-placr' );
			$label       = Ad_Placr_Positions::label( (string) $key );
			if ( '' === $label ) {
				continue;
			}
			$groups[ $group_label ][ (string) $key ] = $label;
		}

		return $groups;
	}

	/**
	 * Return the complete safe default shape for display rules.
	 *
	 * @since 2.7.0
	 *
	 * @return array<string, mixed>
	 */
	private static function default_targeting(): array {
		return array(
			'contexts'           => array(),
			'post_types'         => array(),
			'user'               => 'any',
			'url_contains'       => array(),
			'include_categories' => array(),
			'include_tags'       => array(),
			'schedule'           => array(
				'start' => '',
				'end'   => '',
			),
			'devices'            => array( 'desktop', 'tablet', 'mobile' ),
			'paragraph'          => 1,
			'slot_id'            => '',
		);
	}

	/**
	 * Normalize every explicit display-rule POST key.
	 *
	 * @since 2.7.0
	 *
	 * @return array<string, mixed>
	 */
	private static function normalize_targeting_request(): array {
		$targeting = self::default_targeting();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- save_ad() verifies the shared editor nonce before calling this request normalizer.
		$contexts_raw          = isset( $_POST['ad_placr_contexts'] )
			? wp_unslash( $_POST['ad_placr_contexts'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- allow-list below.
			: array();
		$targeting['contexts'] = self::sanitize_key_list(
			is_array( $contexts_raw ) ? $contexts_raw : array(),
			array( 'singular', 'front_page', 'blog_index', 'archive', 'search' )
		);

		$post_types_raw = isset( $_POST['ad_placr_post_types'] )
			? wp_unslash( $_POST['ad_placr_post_types'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- existence check below.
			: array();
		$post_types     = array();
		if ( is_array( $post_types_raw ) ) {
			foreach ( $post_types_raw as $post_type ) {
				$post_type = sanitize_key( (string) $post_type );
				if ( '' !== $post_type && post_type_exists( $post_type ) ) {
					$post_types[] = $post_type;
				}
			}
		}
		$targeting['post_types'] = array_values( array_unique( $post_types ) );

		$user              = isset( $_POST['ad_placr_user'] )
			? sanitize_key( wp_unslash( (string) $_POST['ad_placr_user'] ) )
			: 'any';
		$targeting['user'] = in_array( $user, array( 'any', 'logged_in', 'guest' ), true ) ? $user : 'any';

		$url_raw   = isset( $_POST['ad_placr_url_contains'] )
			? wp_unslash( (string) $_POST['ad_placr_url_contains'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each line is sanitized below.
			: '';
		$url_lines = preg_split( '/\r\n|\r|\n/', $url_raw );
		$needles   = array();
		foreach ( is_array( $url_lines ) ? $url_lines : array() as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$needles[] = $line;
			}
		}
		$targeting['url_contains'] = array_values( array_unique( $needles ) );

		$targeting['include_categories'] = self::parse_id_list(
			isset( $_POST['ad_placr_include_categories'] )
				? wp_unslash( (string) $_POST['ad_placr_include_categories'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- integer parser below.
				: ''
		);
		$targeting['include_tags']       = self::parse_id_list(
			isset( $_POST['ad_placr_include_tags'] )
				? wp_unslash( (string) $_POST['ad_placr_include_tags'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- integer parser below.
				: ''
		);

		$targeting['schedule'] = array(
			'start' => isset( $_POST['ad_placr_schedule_start'] )
				? sanitize_text_field( wp_unslash( (string) $_POST['ad_placr_schedule_start'] ) )
				: '',
			'end'   => isset( $_POST['ad_placr_schedule_end'] )
				? sanitize_text_field( wp_unslash( (string) $_POST['ad_placr_schedule_end'] ) )
				: '',
		);

		$devices_raw          = isset( $_POST['ad_placr_devices'] )
			? wp_unslash( $_POST['ad_placr_devices'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- allow-list below.
			: array();
		$targeting['devices'] = self::sanitize_key_list(
			is_array( $devices_raw ) ? $devices_raw : array(),
			array( 'desktop', 'tablet', 'mobile' )
		);

		$paragraph              = isset( $_POST['ad_placr_paragraph'] )
			? absint( wp_unslash( $_POST['ad_placr_paragraph'] ) )
			: 1;
		$targeting['paragraph'] = max( 1, min( 100, $paragraph ) );
		$targeting['slot_id']   = isset( $_POST['ad_placr_slot_id'] )
			? sanitize_key( wp_unslash( (string) $_POST['ad_placr_slot_id'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $targeting;
	}

	/**
	 * Sanitize an allow-listed array of keys.
	 *
	 * @since 2.7.0
	 *
	 * @param array<int|string, mixed> $raw     Raw values.
	 * @param string[]                 $allowed Allowed keys.
	 * @return string[]
	 */
	private static function sanitize_key_list( array $raw, array $allowed ): array {
		$clean = array();
		foreach ( $raw as $value ) {
			$value = sanitize_key( (string) $value );
			if ( in_array( $value, $allowed, true ) ) {
				$clean[] = $value;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Parse comma- or whitespace-separated positive IDs.
	 *
	 * @since 2.4.0
	 *
	 * @param string $raw Raw input.
	 * @return int[]
	 */
	private static function parse_id_list( string $raw ): array {
		$parts = preg_split( '/[\s,]+/', $raw );
		$out   = array();
		foreach ( is_array( $parts ) ? $parts : array() as $part ) {
			$id = absint( $part );
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Detect whether saved rules differ from the show-everywhere defaults.
	 *
	 * @since 2.7.0
	 *
	 * @param array<string, mixed> $targeting Saved display rules.
	 * @return bool
	 */
	private static function targeting_has_limits( array $targeting ): bool {
		$defaults = self::default_targeting();

		foreach ( array( 'contexts', 'post_types', 'url_contains', 'include_categories', 'include_tags' ) as $key ) {
			if ( ! empty( $targeting[ $key ] ) ) {
				return true;
			}
		}

		if ( isset( $targeting['user'] ) && 'any' !== $targeting['user'] ) {
			return true;
		}

		if ( isset( $targeting['schedule'] ) && is_array( $targeting['schedule'] ) ) {
			if ( ! empty( $targeting['schedule']['start'] ) || ! empty( $targeting['schedule']['end'] ) ) {
				return true;
			}
		}

		$devices = isset( $targeting['devices'] ) && is_array( $targeting['devices'] )
			? array_values( $targeting['devices'] )
			: $defaults['devices'];
		sort( $devices );
		$default_devices = $defaults['devices'];
		sort( $default_devices );

		return $devices !== $default_devices;
	}

	/**
	 * Normalize a stable version ID to its storage alphabet.
	 *
	 * @since 2.7.0
	 *
	 * @param string $raw Raw version ID.
	 * @return string
	 */
	private static function normalize_version_id( string $raw ): string {
		$normalized = (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', trim( $raw ) );

		return substr( $normalized, 0, 64 );
	}

	/**
	 * Generate a unique valid version ID with a deterministic final fallback.
	 *
	 * @since 2.7.0
	 *
	 * @param callable            $id_generator Version ID generator.
	 * @param array<string, bool> $used_ids     IDs already used in this request.
	 * @return string
	 */
	private static function generate_unique_version_id( callable $id_generator, array $used_ids ): string {
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$candidate = self::normalize_version_id( (string) $id_generator() );
			if ( '' !== $candidate && ! isset( $used_ids[ $candidate ] ) ) {
				return $candidate;
			}
		}

		/*
		 * A broken injected generator must not collapse analytics identities.
		 * The runtime UUID generator should never reach this fallback.
		 */
		do {
			$candidate = self::normalize_version_id( wp_generate_uuid4() . '-' . ( count( $used_ids ) + 1 ) );
		} while ( isset( $used_ids[ $candidate ] ) );

		return $candidate;
	}

	/**
	 * Return the alphabetic default name for a zero-based row.
	 *
	 * @since 2.7.0
	 *
	 * @param int $index Zero-based row index.
	 * @return string
	 */
	private static function default_version_name( int $index ): string {
		$number = max( 0, $index ) + 1;
		$suffix = '';
		while ( $number > 0 ) {
			--$number;
			$suffix = chr( 65 + ( $number % 26 ) ) . $suffix;
			$number = intdiv( $number, 26 );
		}

		return sprintf(
			/* translators: %s: alphabetic version label such as A or B. */
			__( 'Version %s', 'ad-placr' ),
			$suffix
		);
	}

	/**
	 * Format click-through rate consistently for editor and list cells.
	 *
	 * @since 2.7.0
	 *
	 * @param int  $impressions    Impression total.
	 * @param int  $clicks         Click total.
	 * @param bool $storage_enabled Whether first-party statistics are stored.
	 * @return string
	 */
	private static function format_ctr( int $impressions, int $clicks, bool $storage_enabled ): string {
		if ( ! $storage_enabled ) {
			return '—';
		}

		$rate = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0;

		return number_format_i18n( $rate, 2 ) . '%';
	}

	/**
	 * Whether another Active automatic Ad uses the same display location.
	 *
	 * @since 2.7.0
	 *
	 * @param int    $ad_id    Current Ad ID.
	 * @param string $position Canonical display location.
	 * @return bool
	 */
	private static function has_duplicate_automatic_location( int $ad_id, string $position ): bool {
		if ( ! Ad_Placr_Ad::is_active( $ad_id ) || '' === $position || ! in_array( $position, Ad_Placr_Positions::renderable_keys(), true ) ) {
			return false;
		}

		foreach ( Ad_Placr_Ad::query_ids_for_position( $position ) as $other_id ) {
			if ( $ad_id !== (int) $other_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Update WordPress status without recursively processing the editor save.
	 *
	 * @since 2.7.0
	 *
	 * @param int    $post_id Ad ID.
	 * @param string $status  publish or draft.
	 * @return void
	 */
	private static function update_post_status( int $post_id, string $status ): void {
		self::$updating_status = true;
		try {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish' === $status ? 'publish' : 'draft',
				)
			);
		} finally {
			self::$updating_status = false;
		}
	}

	/**
	 * Verify capability, nonce, post type, and Ad ID for a row action.
	 *
	 * @since 2.7.0
	 *
	 * @param string $action duplicate, activate, or pause.
	 * @return int Valid Ad ID.
	 */
	private static function validate_row_action( string $action ): int {
		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Ads.', 'ad-placr' ) );
		}

		$ad_id = isset( $_GET['ad'] ) ? absint( wp_unslash( $_GET['ad'] ) ) : 0;
		check_admin_referer( 'ad_placr_' . $action . '_' . $ad_id );

		if ( $ad_id < 1 || Ad_Placr_Ad::POST_TYPE !== get_post_type( $ad_id ) ) {
			wp_die( esc_html__( 'The Ad could not be found.', 'ad-placr' ) );
		}

		return $ad_id;
	}
}
