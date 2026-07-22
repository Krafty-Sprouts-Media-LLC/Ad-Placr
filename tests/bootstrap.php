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

// Class files under test are required here as tasks add them.
require dirname( __DIR__ ) . '/includes/class-ad-placr-positions.php';
require dirname( __DIR__ ) . '/includes/class-ad-placr-ad.php';
