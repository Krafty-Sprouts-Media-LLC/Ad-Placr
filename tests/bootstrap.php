<?php
/**
 * PHPUnit bootstrap for Ad Placr unit tests (no WordPress install required).
 *
 * @package AdPlacr
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'AD_PLACR_VERSION', 'test' );
define( 'AD_PLACR_PLUGIN_FILE', dirname( __DIR__ ) . '/ad-placr.php' );
define( 'AD_PLACR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'AD_PLACR_PLUGIN_URL', 'http://example.test/wp-content/plugins/ad-placr/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Thin esc_attr stub for unit tests without WordPress.
	 *
	 * @param mixed $text Text to escape.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Thin esc_html stub for unit tests without WordPress.
	 *
	 * @param mixed $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Thin absint stub for unit tests without WordPress.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! class_exists( 'WP_Widget' ) ) {
	/**
	 * Minimal WP_Widget stub so the widget class can load in unit tests.
	 */
	class WP_Widget {
		/**
		 * Widget instance number.
		 *
		 * @var int
		 */
		public $number = 1;

		/**
		 * @param string               $id_base         Base ID.
		 * @param string               $name            Name.
		 * @param array<string, mixed> $widget_options  Options.
		 * @param array<string, mixed> $control_options Control options.
		 */
		public function __construct( $id_base = '', $name = '', $widget_options = array(), $control_options = array() ) {
		}

		/**
		 * @param string $field Field name.
		 * @return string
		 */
		public function get_field_id( $field ) {
			return (string) $field;
		}

		/**
		 * @param string $field Field name.
		 * @return string
		 */
		public function get_field_name( $field ) {
			return (string) $field;
		}
	}
}

// Class files under test are required here as tasks add them.
require dirname( __DIR__ ) . '/includes/class-ad-placr-positions.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-ad.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-placement.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-migration.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-renderer.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-frontend.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-targeting.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-shortcode.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-widget.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-admin.php';
