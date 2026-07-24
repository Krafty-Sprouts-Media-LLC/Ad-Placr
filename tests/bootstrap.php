<?php
/**
 * PHPUnit bootstrap for Ad Placr unit tests (no WordPress install required).
 *
 * @package AdPlacr
 */

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
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

if ( ! function_exists( '__' ) ) {
	/**
	 * Return translated source text unchanged in unit tests.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	function __( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Minimal plain-text sanitizer for unit tests.
	 *
	 * @param mixed $text Raw text.
	 * @return string
	 */
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Minimal safe-markup sanitizer for unit tests.
	 *
	 * @param mixed $text Raw markup.
	 * @return string
	 */
	function wp_kses_post( $text ) {
		return strip_tags( (string) $text, '<ins>' );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * Stable-shape UUID generator for tests that do not inject a seam.
	 *
	 * @return string
	 */
	function wp_generate_uuid4() {
		return '00000000-0000-4000-8000-000000000001';
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
require dirname( __DIR__ ) . '/includes/class-ad-placr-settings-page.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-admin.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-analytics.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-rest.php';
