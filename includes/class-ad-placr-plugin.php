<?php
/**
 * Core plugin bootstrap.
 *
 * Owns activation, textdomain, default settings shape, and safe reads of
 * `ad_placr_settings`. Placement classes call get_settings() — never raw get_option().
 *
 * @package AdPlacr
 * @since 0.1.0
 */

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
	 * Maximum in-content slots stored in settings.
	 *
	 * @since 1.1.0
	 */
	public const MAX_IN_CONTENT_SLOTS = 30;

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
	 * or WordPress will miss it. Admin + front placements only attach their own hooks
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
		Ad_Placr_Placement::register();
		Ad_Placr_Migration::register();
		Ad_Placr_Plugin_Updater::register();
	}

	/**
	 * Activation callback — ensures default option shape exists.
	 *
	 * Merges existing values over defaults so reactivation never wipes saved ads.
	 * Autoload is forced off (`false`) — settings are not needed on every front request
	 * until a placement actually reads them.
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

		Ad_Placr_Analytics::install();
	}

	/**
	 * Default settings structure.
	 *
	 * Single option array (`ad_placr_settings`) holds both placements today.
	 * `in_content_slots` is a list of slot maps (see settings sanitizer); empty means
	 * no in-content injection.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'footer_sticky'     => array(
				'enabled'     => false,
				'code'        => '',
				'mobile_code' => '',
			),
			'in_content_slots'  => array(),
			'disclosure_text'   => '',
			'analytics_enabled' => false,
		);
	}

	/**
	 * Merged settings from the database with defaults.
	 *
	 * Top-level `wp_parse_args` is shallow — nested `footer_sticky` keys are merged
	 * again so a partial save (missing `mobile_code`) still gets a full shape.
	 * Callers can rely on both keys always existing as arrays.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$defaults = self::default_settings();
		$raw      = get_option( Ad_Placr_Settings_Page::OPTION_NAME, array() );

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$merged = wp_parse_args( $raw, $defaults );

		/*
		 * Nested merge: without this, an older/partial option row can omit keys and
		 * cause undefined-index notices in placement renderers.
		 */
		if ( isset( $merged['footer_sticky'] ) && is_array( $merged['footer_sticky'] ) ) {
			$merged['footer_sticky'] = wp_parse_args( $merged['footer_sticky'], $defaults['footer_sticky'] );
		} else {
			$merged['footer_sticky'] = $defaults['footer_sticky'];
		}

		if ( ! isset( $merged['in_content_slots'] ) || ! is_array( $merged['in_content_slots'] ) ) {
			$merged['in_content_slots'] = array();
		}

		return $merged;
	}
}
