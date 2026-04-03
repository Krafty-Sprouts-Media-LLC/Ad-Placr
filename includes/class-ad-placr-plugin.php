<?php
/**
 * Core plugin bootstrap.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

/**
 * Main plugin singleton.
 *
 * @since 0.1.0
 */
final class Ad_Placr_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Ad_Placr_Plugin|null
	 */
	private static ?Ad_Placr_Plugin $instance = null;

	/**
	 * Get the plugin instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Ad_Placr_Plugin
	 */
	public static function instance(): Ad_Placr_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Load hooks and subsystems.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		register_activation_hook(
			AD_PLACR_PLUGIN_FILE,
			array( $this, 'activate' )
		);

		add_action(
			'init',
			static function () {
				load_plugin_textdomain(
					'ad-placr',
					false,
					dirname( plugin_basename( AD_PLACR_PLUGIN_FILE ) ) . '/languages'
				);
			}
		);

		Ad_Placr_Settings_Page::register();
		Ad_Placr_Footer_Sticky::register();
		Ad_Placr_Plugin_Updater::register();
	}

	/**
	 * Activation callback — ensures default option shape exists.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function activate(): void {
		$defaults = self::default_settings();
		$existing = get_option( 'ad_placr_settings', array() );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$merged = wp_parse_args( $existing, $defaults );

		update_option( 'ad_placr_settings', $merged, false );
	}

	/**
	 * Default settings structure.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'footer_sticky' => array(
				'enabled'     => false,
				'code'        => '',
				'mobile_code' => '',
			),
		);
	}
}
