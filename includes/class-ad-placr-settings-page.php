<?php
/**
 * Statistics storage settings for the unified Ad manager.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the statistics-only Settings API screen under Ads.
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
	 * Register the menu, Settings API hooks, and page assets.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_settings_assets' ) );
	}

	/**
	 * Enqueue settings page stylesheet.
	 *
	 * @since 2.7.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_settings_assets( string $hook_suffix ): void {
		if ( Ad_Placr_Ad::POST_TYPE . '_page_ad-placr' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'ad-placr-settings',
			AD_PLACR_PLUGIN_URL . 'assets/css/settings.css',
			array(),
			AD_PLACR_VERSION
		);
	}

	/**
	 * Add the Settings submenu beneath Ads.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Ad_Placr_Ad::POST_TYPE,
			__( 'Ad Placr Settings', 'ad-placr' ),
			__( 'Settings', 'ad-placr' ),
			self::CAPABILITY,
			'ad-placr',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the statistics option with its sanitizer.
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
	 * Sanitize the statistics checkbox into the complete settings shape.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value Submitted settings value.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $value ): array {
		$value   = is_array( $value ) ? $value : array();
		$enabled = ! empty( $value['analytics_enabled'] );

		return array(
			'analytics_enabled' => $enabled,
		);
	}

	/**
	 * Render the settings page with a clean card-based layout.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings          = Ad_Placr_Plugin::get_settings();
		$analytics_enabled = ! empty( $settings['analytics_enabled'] );
		?>
		<div class="wrap ad-placr-settings-wrap">
			<h1 class="ad-placr-settings-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="ad-placr-settings-subtitle"><?php esc_html_e( 'Configure how Ad Placr behaves across your site.', 'ad-placr' ); ?></p>

			<form action="options.php" method="post">
				<?php settings_fields( 'ad_placr' ); ?>

				<div class="ad-placr-settings-card">
					<div class="ad-placr-settings-card-header">
						<div class="ad-placr-settings-card-icon ad-placr-icon-stats">
							<span class="dashicons dashicons-chart-bar"></span>
						</div>
						<div>
							<h2><?php esc_html_e( 'Statistics & Analytics', 'ad-placr' ); ?></h2>
							<p><?php esc_html_e( 'Control how Ad Placr tracks and stores performance data.', 'ad-placr' ); ?></p>
						</div>
					</div>

					<div class="ad-placr-settings-card-body">
						<div class="ad-placr-setting-row">
							<div class="ad-placr-setting-info">
								<label for="ad-placr-analytics-enabled"><?php esc_html_e( 'Enable statistics storage', 'ad-placr' ); ?></label>
								<p><?php esc_html_e( 'Store impression and click totals for each ad. Data is kept for 90 days and contains no personal information.', 'ad-placr' ); ?></p>
							</div>
							<div class="ad-placr-setting-control">
								<label class="ad-placr-toggle" for="ad-placr-analytics-enabled">
									<input
										type="checkbox"
										id="ad-placr-analytics-enabled"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[analytics_enabled]"
										value="1"
										<?php checked( $analytics_enabled ); ?>
									/>
									<span class="ad-placr-toggle-slider"></span>
									<span class="ad-placr-toggle-label">
										<?php echo $analytics_enabled ? esc_html__( 'On', 'ad-placr' ) : esc_html__( 'Off', 'ad-placr' ); ?>
									</span>
								</label>
							</div>
						</div>
					</div>
				</div>

				<div class="ad-placr-settings-save">
					<?php submit_button( __( 'Save Settings', 'ad-placr' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}
}
