<?php
/**
 * Repository-level contract tests for the Ad Placr runtime.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Verifies the runtime bootstrap and unified-model source boundaries.
 *
 * @since 0.1.0
 */
final class SanityTest extends TestCase {

	/**
	 * The test bootstrap exposes the plugin version constant.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_constants_are_defined(): void {
		$this->assertTrue( defined( 'AD_PLACR_VERSION' ) );
		$this->assertSame( 'test', AD_PLACR_VERSION );
	}

	/**
	 * Production runtime files have no transitional Placement dependency.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_runtime_has_no_placement_dependency(): void {
		$root          = dirname( __DIR__, 2 );
		$include_files = glob( $root . '/includes/*.php' );
		$include_files = is_array( $include_files ) ? $include_files : array();
		$runtime       = array_merge( array( $root . '/ad-placr.php' ), $include_files );

		foreach ( $runtime as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
			$source = (string) file_get_contents( $file );

			$this->assertStringNotContainsString( 'Ad_Placr_Placement', $source, $file );
			$this->assertStringNotContainsString( 'ad_placr_placement', $source, $file );
			$this->assertStringNotContainsString( 'placement_id', $source, $file );
			$this->assertStringNotContainsString( 'data-placement-id', $source, $file );
		}
	}

	/**
	 * The clean-start build has no legacy-data migration subsystem.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_runtime_has_no_legacy_data_migration(): void {
		$root          = dirname( __DIR__, 2 );
		$include_files = glob( $root . '/includes/*.php' );
		$include_files = is_array( $include_files ) ? $include_files : array();
		$runtime       = array_merge(
			array(
				$root . '/ad-placr.php',
				$root . '/uninstall.php',
			),
			$include_files
		);

		foreach ( $runtime as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
			$source = (string) file_get_contents( $file );

			$this->assertStringNotContainsString( 'Ad_Placr_Migration', $source, $file );
			$this->assertStringNotContainsString( 'class-ad-placr-migration.php', $source, $file );
			$this->assertStringNotContainsString( 'ad_placr_db_version', $source, $file );
			$this->assertStringNotContainsString( 'ad_placr_unified_migration_map', $source, $file );
			$this->assertStringNotContainsString( 'ad_placr_unified_migration_lock', $source, $file );
		}
	}

	/**
	 * Legacy single-code Ad APIs do not survive the unified version model.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_runtime_has_no_transitional_ad_code_api(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/includes/class-ad-placr-ad.php'
		);

		$this->assertStringNotContainsString( 'META_CODE', $source );
		$this->assertStringNotContainsString( 'META_MOBILE_CODE', $source );
		$this->assertStringNotContainsString( 'META_STATUS', $source );
		$this->assertStringNotContainsString( 'normalize_status', $source );
		$this->assertStringNotContainsString( 'get_code', $source );
		$this->assertStringNotContainsString( 'get_mobile_code', $source );
	}

	/**
	 * The renderer contains no completed-task compatibility helpers.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_renderer_has_no_transitional_responsive_helpers(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/includes/class-ad-placr-renderer.php'
		);

		$this->assertStringNotContainsString( 'build_mobile_pair_css', $source );
		$this->assertStringNotContainsString( 'resolve_breakpoint', $source );
		$this->assertStringNotContainsString( 'Tasks 3', $source );
	}

	/**
	 * Current settings contain only the statistics opt-in.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_default_settings_have_no_unreleased_legacy_sources(): void {
		$this->assertSame(
			array( 'analytics_enabled' => false ),
			Ad_Placr_Plugin::default_settings()
		);
	}

	/**
	 * All public release-version declarations remain synchronized.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_release_version_declarations_are_2_7_0(): void {
		$root = dirname( __DIR__, 2 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
		$plugin = (string) file_get_contents( $root . '/ad-placr.php' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
		$readme = (string) file_get_contents( $root . '/readme.txt' );

		$this->assertStringContainsString( 'Version:           2.7.0', $plugin );
		$this->assertStringContainsString( "define( 'AD_PLACR_VERSION', '2.7.0' );", $plugin );
		$this->assertStringContainsString( 'Stable tag: 2.7.0', $readme );
	}

	/**
	 * Uninstall has no legacy-data migration cleanup.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_uninstall_has_no_legacy_migration_options_or_post_deletion(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );

		$this->assertStringNotContainsString( 'ad_placr_db_version', $source );
		$this->assertStringNotContainsString( 'ad_placr_unified_migration_map', $source );
		$this->assertStringNotContainsString( 'ad_placr_unified_migration_lock', $source );
		$this->assertStringNotContainsString( 'wp_delete_post', $source );
		$this->assertStringNotContainsString( 'delete_posts', $source );
	}

	/**
	 * Lifecycle files guard direct access and clear recurring cleanup events.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_lifecycle_source_clears_analytics_cron_safely(): void {
		$root = dirname( __DIR__, 2 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
		$plugin = (string) file_get_contents( $root . '/includes/class-ad-placr-plugin.php' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source scan.
		$uninstall = (string) file_get_contents( $root . '/uninstall.php' );

		$this->assertStringContainsString( "if ( ! defined( 'ABSPATH' ) )", $plugin );
		$this->assertStringContainsString( 'register_deactivation_hook', $plugin );
		$this->assertStringContainsString( 'wp_clear_scheduled_hook', $plugin );
		$this->assertStringContainsString( 'wp_clear_scheduled_hook', $uninstall );
		$this->assertStringNotContainsString( 'wp_unschedule_event', $uninstall );
	}
}
