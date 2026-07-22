<?php
/**
 * Admin settings UI and registration.
 *
 * Settings API option `ad_placr_settings` (single array). Capability gate is
 * `manage_options`. Ad code sanitization preserves raw HTML/JS for users with
 * `unfiltered_html`; everyone else gets `wp_kses_post`.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

/**
 * Registers the settings screen, sanitizers, and in-content repeater assets.
 *
 * @since 0.1.0
 */
final class Ad_Placr_Settings_Page {

	/**
	 * Option name stored in wp_options.
	 *
	 * @since 0.1.0
	 */
	public const OPTION_NAME = 'ad_placr_settings';

	/**
	 * Capability required to manage settings.
	 *
	 * @since 0.1.0
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Scripts and styles for the repeater UI (in-content slots).
	 *
	 * @since 1.1.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'settings_page_ad-placr' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'ad-placr-settings',
			AD_PLACR_PLUGIN_URL . 'admin/css/settings-slots.css',
			array(),
			AD_PLACR_VERSION
		);

		wp_enqueue_script(
			'ad-placr-in-content-slots',
			AD_PLACR_PLUGIN_URL . 'admin/js/in-content-slots.js',
			array( 'jquery' ),
			AD_PLACR_VERSION,
			true
		);

		wp_localize_script(
			'ad-placr-in-content-slots',
			'adPlacrIcSlots',
			array(
				'confirmRemove' => __( 'Remove this in-content placement?', 'ad-placr' ),
				'maxSlots'      => Ad_Placr_Plugin::MAX_IN_CONTENT_SLOTS,
			)
		);
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function add_menu(): void {
		add_options_page(
			__( 'Ad Placr', 'ad-placr' ),
			__( 'Ad Placr', 'ad-placr' ),
			self::CAPABILITY,
			'ad-placr',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register setting and sanitization.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			'ad_placr',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => Ad_Placr_Plugin::default_settings(),
			)
		);
	}

	/**
	 * Sanitize stored settings.
	 *
	 * Always returns a full defaults-shaped array. Unknown keys are dropped — the
	 * option is the allow-list, not a passthrough of $_POST.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value Raw option value.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $value ): array {
		$defaults = Ad_Placr_Plugin::default_settings();

		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$out = $defaults;

		if ( isset( $value['footer_sticky'] ) && is_array( $value['footer_sticky'] ) ) {
			$fs = $value['footer_sticky'];

			$out['footer_sticky']['enabled'] = ! empty( $fs['enabled'] );

			$out['footer_sticky']['code']        = self::sanitize_ad_code( isset( $fs['code'] ) ? $fs['code'] : '' );
			$out['footer_sticky']['mobile_code'] = self::sanitize_ad_code( isset( $fs['mobile_code'] ) ? $fs['mobile_code'] : '' );
		}

		$out['in_content_slots'] = self::sanitize_in_content_slots(
			isset( $value['in_content_slots'] ) && is_array( $value['in_content_slots'] ) ? $value['in_content_slots'] : array()
		);

		return $out;
	}

	/**
	 * Sanitize and normalize in-content slot rows.
	 *
	 * Caps at MAX_IN_CONTENT_SLOTS. Empty disabled rows (no code) are dropped so the
	 * stored option does not grow from leftover repeater placeholders.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, mixed> $rows Raw rows from POST.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_in_content_slots( array $rows ): array {
		$clean = array();
		$max   = Ad_Placr_Plugin::MAX_IN_CONTENT_SLOTS;

		foreach ( $rows as $row ) {
			if ( count( $clean ) >= $max ) {
				break;
			}

			if ( ! is_array( $row ) ) {
				continue;
			}

			$san = self::sanitize_ic_slot_row( $row );

			if ( null === $san ) {
				continue;
			}

			$clean[] = $san;
		}

		return $clean;
	}

	/**
	 * One slot row; returns null to drop an empty row.
	 *
	 * Stable `id` is kept across saves (drives `#ad-placr-ic-{id}` CSS scoping). New
	 * rows without an id get a random `ic_*` token. Paragraph index is clamped 1–100.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>|null
	 */
	private static function sanitize_ic_slot_row( array $row ): ?array {
		$title = isset( $row['title'] ) ? sanitize_text_field( (string) wp_unslash( $row['title'] ) ) : '';
		$code  = isset( $row['code'] ) ? self::sanitize_ad_code( (string) $row['code'] ) : '';
		$mob   = isset( $row['mobile_code'] ) ? self::sanitize_ad_code( (string) $row['mobile_code'] ) : '';

		$enabled = ! empty( $row['enabled'] );

		// Drop blank placeholders the repeater may POST when the user never filled a row.
		if ( ! $enabled && '' === trim( $code ) && '' === trim( $mob ) ) {
			return null;
		}

		$id = isset( $row['id'] ) ? strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) wp_unslash( $row['id'] ) ) ) : '';
		if ( '' === $id ) {
			$id = 'ic_' . strtolower( wp_generate_password( 10, false, false ) );
		}

		$pi = isset( $row['paragraph_index'] ) ? absint( $row['paragraph_index'] ) : 2;
		if ( $pi < 1 ) {
			$pi = 1;
		} elseif ( $pi > 100 ) {
			$pi = 100;
		}

		$pos = isset( $row['position'] ) ? sanitize_key( (string) $row['position'] ) : 'after';
		$pos = ( 'before' === $pos ) ? 'before' : 'after';

		// Only core types for now; expand when CPT targeting lands in the ad-manager rebuild.
		$allowed_types = array( 'post', 'page' );
		$pt_in         = isset( $row['post_types'] ) && is_array( $row['post_types'] ) ? $row['post_types'] : array();
		$pt_clean      = array_values( array_intersect( $allowed_types, array_map( 'sanitize_key', $pt_in ) ) );

		return array(
			'id'              => $id,
			'enabled'         => $enabled,
			'title'           => $title,
			'paragraph_index' => $pi,
			'position'        => $pos,
			'post_types'      => $pt_clean,
			'code'            => $code,
			'mobile_code'     => $mob,
		);
	}

	/**
	 * Sanitize ad markup / scripts from a privileged user.
	 *
	 * `unfiltered_html` (typically Administrators on single-site) keeps network tags
	 * and scripts intact. Other roles are limited to post-safe HTML via wp_kses_post.
	 *
	 * @since 0.1.0
	 *
	 * @param string $code Raw code from the textarea.
	 * @return string
	 */
	private static function sanitize_ad_code( string $code ): string {
		$code = wp_unslash( $code );

		if ( current_user_can( 'unfiltered_html' ) ) {
			return $code;
		}

		return wp_kses_post( $code );
	}

	/**
	 * Default empty row for the form (and JS template base).
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	private static function default_ic_slot_for_form(): array {
		return array(
			'id'              => '',
			'enabled'         => false,
			'title'           => '',
			'paragraph_index' => 2,
			'position'        => 'after',
			'post_types'      => array( 'post', 'page' ),
			'code'            => '',
			'mobile_code'     => '',
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = Ad_Placr_Plugin::get_settings();

		$fs = $settings['footer_sticky'];

		$slots = isset( $settings['in_content_slots'] ) && is_array( $settings['in_content_slots'] ) ? $settings['in_content_slots'] : array();
		if ( empty( $slots ) ) {
			$slots = array( self::default_ic_slot_for_form() );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php if ( ! current_user_can( 'unfiltered_html' ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Your account cannot save unfiltered HTML. Ad scripts may be stripped on save. Use an administrator account or unfiltered_html capability for full ad tags.', 'ad-placr' ); ?></p>
				</div>
			<?php endif; ?>
			<form action="options.php" method="post" id="ad-placr-settings-form">
				<?php settings_fields( 'ad_placr' ); ?>
				<h2 class="title"><?php esc_html_e( 'Footer sticky', 'ad-placr' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'One universal snippet is used on all viewports. If you add a mobile override, that snippet is used on small screens (up to 782px, the WordPress small-screen breakpoint); the universal snippet is used on larger screens.', 'ad-placr' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable footer sticky', 'ad-placr' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[footer_sticky][enabled]" value="1" <?php checked( ! empty( $fs['enabled'] ) ); ?> />
								<?php esc_html_e( 'Output the floating footer placement on the front end.', 'ad-placr' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ad-placr-footer-code"><?php esc_html_e( 'Universal ad code', 'ad-placr' ); ?></label>
						</th>
						<td>
							<textarea class="large-text code" rows="8" id="ad-placr-footer-code" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[footer_sticky][code]"><?php echo esc_textarea( $fs['code'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ad-placr-footer-mobile"><?php esc_html_e( 'Mobile override (optional)', 'ad-placr' ); ?></label>
						</th>
						<td>
							<textarea class="large-text code" rows="8" id="ad-placr-footer-mobile" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[footer_sticky][mobile_code]"><?php echo esc_textarea( $fs['mobile_code'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Leave empty to use the universal code on small screens too.', 'ad-placr' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'In-content placements', 'ad-placr' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Add multiple placements. Each slot targets a paragraph number in the rendered HTML (classic paragraph blocks). After mode inserts following that paragraph; before mode inserts immediately before it. Same mobile breakpoint behavior as the footer (782px default).', 'ad-placr' ); ?>
				</p>

				<p>
					<button type="button" class="button" id="ad-placr-add-ic-slot"><?php esc_html_e( 'Add in-content placement', 'ad-placr' ); ?></button>
				</p>

				<div id="ad-placr-ic-slots">
					<?php
					foreach ( $slots as $idx => $slot ) {
						self::render_ic_slot_card( (int) $idx, wp_parse_args( $slot, self::default_ic_slot_for_form() ) );
					}
					?>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Markup for one in-content slot card.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $index Zero-based index (form array key).
	 * @param array<string, mixed> $slot  Slot values.
	 * @return void
	 */
	private static function render_ic_slot_card( int $index, array $slot ): void {
		$base = self::OPTION_NAME . '[in_content_slots][' . (string) $index . ']';
		$pt   = isset( $slot['post_types'] ) && is_array( $slot['post_types'] ) ? $slot['post_types'] : array();

		$slot_id = isset( $slot['id'] ) ? (string) $slot['id'] : '';
		if ( '' === $slot_id ) {
			$slot_id = 'ic_' . strtolower( wp_generate_password( 8, false, false ) );
		}

		$pos = isset( $slot['position'] ) && 'before' === $slot['position'] ? 'before' : 'after';

		?>
		<div class="ad-placr-ic-slot">
			<div class="ad-placr-ic-slot__head">
				<strong><?php esc_html_e( 'In-content slot', 'ad-placr' ); ?></strong>
				<button type="button" class="button-link ad-placr-ic-slot-remove" aria-label="<?php esc_attr_e( 'Remove slot', 'ad-placr' ); ?>"><?php esc_html_e( 'Remove', 'ad-placr' ); ?></button>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $slot_id ); ?>" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="ad-placr-ic-title-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Label (optional)', 'ad-placr' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" id="ad-placr-ic-title-<?php echo esc_attr( (string) $index ); ?>" name="<?php echo esc_attr( $base ); ?>[title]" value="<?php echo esc_attr( isset( $slot['title'] ) ? (string) $slot['title'] : '' ); ?>" />
						<p class="description"><?php esc_html_e( 'For your reference only (not shown on the site).', 'ad-placr' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable', 'ad-placr' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( ! empty( $slot['enabled'] ) ); ?> />
							<?php esc_html_e( 'Active on the front end (when code is present and post type matches).', 'ad-placr' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post types', 'ad-placr' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[post_types][]" value="post" <?php checked( in_array( 'post', $pt, true ) ); ?> />
							<?php esc_html_e( 'Posts', 'ad-placr' ); ?>
						</label><br />
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[post_types][]" value="page" <?php checked( in_array( 'page', $pt, true ) ); ?> />
							<?php esc_html_e( 'Pages', 'ad-placr' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Paragraph number', 'ad-placr' ); ?></th>
					<td>
						<input type="number" class="small-text" min="1" max="100" step="1" name="<?php echo esc_attr( $base ); ?>[paragraph_index]" value="<?php echo esc_attr( (string) (int) $slot['paragraph_index'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Position', 'ad-placr' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="<?php echo esc_attr( $base ); ?>[position]" value="after" <?php checked( 'after', $pos ); ?> />
								<?php esc_html_e( 'After this paragraph', 'ad-placr' ); ?>
							</label><br />
							<label>
								<input type="radio" name="<?php echo esc_attr( $base ); ?>[position]" value="before" <?php checked( 'before', $pos ); ?> />
								<?php esc_html_e( 'Before this paragraph', 'ad-placr' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Universal ad code', 'ad-placr' ); ?></th>
					<td>
						<textarea class="large-text code" rows="6" name="<?php echo esc_attr( $base ); ?>[code]"><?php echo esc_textarea( isset( $slot['code'] ) ? (string) $slot['code'] : '' ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mobile override (optional)', 'ad-placr' ); ?></th>
					<td>
						<textarea class="large-text code" rows="6" name="<?php echo esc_attr( $base ); ?>[mobile_code]"><?php echo esc_textarea( isset( $slot['mobile_code'] ) ? (string) $slot['mobile_code'] : '' ); ?></textarea>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
