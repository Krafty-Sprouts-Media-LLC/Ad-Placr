<?php
/**
 * Admin settings UI and registration.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

/**
 * Registers the settings screen and option sanitization.
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

		return $out;
	}

	/**
	 * Sanitize ad markup / scripts from a privileged user.
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

		$settings = get_option( self::OPTION_NAME, Ad_Placr_Plugin::default_settings() );
		$settings = wp_parse_args( $settings, Ad_Placr_Plugin::default_settings() );

		if ( isset( $settings['footer_sticky'] ) && is_array( $settings['footer_sticky'] ) ) {
			$settings['footer_sticky'] = wp_parse_args( $settings['footer_sticky'], Ad_Placr_Plugin::default_settings()['footer_sticky'] );
		} else {
			$settings['footer_sticky'] = Ad_Placr_Plugin::default_settings()['footer_sticky'];
		}

		$fs = $settings['footer_sticky'];

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php if ( ! current_user_can( 'unfiltered_html' ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Your account cannot save unfiltered HTML. Ad scripts may be stripped on save. Use an administrator account or unfiltered_html capability for full ad tags.', 'ad-placr' ); ?></p>
				</div>
			<?php endif; ?>
			<form action="options.php" method="post">
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
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
