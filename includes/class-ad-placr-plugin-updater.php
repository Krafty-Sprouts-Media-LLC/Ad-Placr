<?php
/**
 * GitHub updates via Plugin Update Checker (Yahnis Elsts).
 *
 * @link https://github.com/YahnisElsts/plugin-update-checker
 *
 * @package AdPlacr
 * @since 0.1.3
 */
final class Ad_Placr_Plugin_Updater {

	/**
	 * GitHub repository URL (HTTPS, trailing slash).
	 *
	 * @since 0.1.3
	 */
	public const GITHUB_REPO_URL = 'https://github.com/Krafty-Sprouts-Media-LLC/Ad-Placr/';

	/**
	 * Default Git branch to read updates from.
	 *
	 * @since 0.1.3
	 */
	public const DEFAULT_BRANCH = 'main';

	/**
	 * Register the update checker with WordPress.
	 *
	 * @since 0.1.3
	 *
	 * @return void
	 */
	public static function register(): void {
		$puc_file = AD_PLACR_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

		if ( ! is_readable( $puc_file ) ) {
			return;
		}

		require_once $puc_file;

		/**
		 * Filter the Git branch used for update checks.
		 *
		 * @since 0.1.3
		 *
		 * @param string $branch Branch name (e.g. main, master).
		 */
		$branch = apply_filters( 'ad_placr_update_checker_branch', self::DEFAULT_BRANCH );

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			self::GITHUB_REPO_URL,
			AD_PLACR_PLUGIN_FILE,
			'ad-placr'
		);

		if ( is_string( $branch ) && '' !== $branch ) {
			$checker->setBranch( $branch );
		}
	}
}
