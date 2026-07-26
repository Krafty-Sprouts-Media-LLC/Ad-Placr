<?php
/**
 * Core plugin bootstrap.
 *
 * Owns activation, textdomain, the statistics setting, and subsystem registration.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin singleton — wires subsystems and centralizes settings access.
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
	 * Activation must be registered here (top-level boot path), not inside other hooks,
	 * or WordPress will miss it. Admin and front-end services attach their own hooks
	 * via ::register() — keeps this file free of render logic.
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
		register_deactivation_hook(
			AD_PLACR_PLUGIN_FILE,
			array( $this, 'deactivate' )
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
		Ad_Placr_In_Content::register();
		Ad_Placr_Frontend::register();
		Ad_Placr_Shortcode::register();
		Ad_Placr_Widget::register();
		Ad_Placr_Admin::register();
		Ad_Placr_Analytics::register();
		Ad_Placr_Rest::register();
		Ad_Placr_Ad::register();
		Ad_Placr_Plugin_Updater::register();
	}

	/**
	 * Activation callback — ensures the clean settings shape and analytics schema exist.
	 *
	 * The single setting is not needed on every request, so autoload remains disabled.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function activate(): void {
		update_option( Ad_Placr_Settings_Page::OPTION_NAME, self::get_settings(), false );

		Ad_Placr_Analytics::install();
	}

	/**
	 * Stop scheduled analytics cleanup while the plugin is inactive.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function deactivate(): void {
		wp_clear_scheduled_hook( Ad_Placr_Analytics::CRON_HOOK );
	}

	/**
	 * Default settings structure.
	 *
	 * Ads store their own display data. The settings option contains only the
	 * site-wide statistics opt-in.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'analytics_enabled' => false,
		);
	}

	/**
	 * Read the site-wide statistics setting in its stable shape.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$raw = get_option( Ad_Placr_Settings_Page::OPTION_NAME, array() );

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return array(
			'analytics_enabled' => ! empty( $raw['analytics_enabled'] ),
		);
	}
}
