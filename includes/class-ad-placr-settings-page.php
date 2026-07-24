<?php
/**
 * Statistics storage settings for the unified Ad manager.
 *
 * Legacy placement settings remain in the option as read-only migration input.
 * Saving this screen changes only the statistics opt-in value.
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
	 * Register the menu and Settings API hooks.
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
	 * Register the retained option with a statistics-only sanitizer.
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
	 * Change the statistics toggle without discarding migration source data.
	 *
	 * @since 2.7.0
	 *
	 * @param array<string, mixed> $current Existing complete settings option.
	 * @param bool                 $enabled Whether first-party statistics storage is enabled.
	 * @return array<string, mixed>
	 */
	public static function merge_analytics_setting( array $current, bool $enabled ): array {
		$current['analytics_enabled'] = $enabled;

		return $current;
	}

	/**
	 * Sanitize the statistics checkbox while preserving all existing option keys.
	 *
	 * The old footer and in-content values are retained strictly as migration
	 * evidence. They are never accepted back from this form.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value Submitted settings value.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $value ): array {
		$current = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$value   = is_array( $value ) ? $value : array();
		$enabled = ! empty( $value['analytics_enabled'] );

		return self::merge_analytics_setting( $current, $enabled );
	}

	/**
	 * Render the statistics storage setting.
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
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'ad_placr' ); ?>
				<h2 class="title"><?php esc_html_e( 'Statistics storage', 'ad-placr' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Store impression and click totals for 90 days. No personal information is collected.', 'ad-placr' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Statistics storage', 'ad-placr' ); ?></th>
						<td>
							<label for="ad-placr-analytics-enabled">
								<input
									type="checkbox"
									id="ad-placr-analytics-enabled"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[analytics_enabled]"
									value="1"
									<?php checked( $analytics_enabled ); ?>
								/>
								<?php esc_html_e( 'Store statistics in this WordPress site', 'ad-placr' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
