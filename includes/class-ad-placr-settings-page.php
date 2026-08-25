<?php
/**
 * Plugin settings screen: statistics, retention, breakpoints, and status.
 *
 * @package AdPlacr
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Settings API screen under Ads.
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
	 * Allowed retention windows in days.
	 *
	 * @since 2.8.0
	 */
	public const RETENTION_CHOICES = array( 30, 60, 90, 180, 365 );

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
	 * Register the settings option with its sanitizer.
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
	 * Sanitize submitted settings into the complete stored shape.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value Submitted settings value.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $value ): array {
		$value = is_array( $value ) ? $value : array();

		return self::normalize_shape( $value );
	}

	/**
	 * Effective analytics retention window in days.
	 *
	 * Precedence: filter override, then stored setting, then 90.
	 *
	 * @since 2.8.0
	 *
	 * @return int Days between 7 and 3650.
	 */
	public static function retention_days(): int {
		$days = (int) apply_filters( 'ad_placr_analytics_retention_days', Ad_Placr_Plugin::get_settings()['retention_days'] );
		$days = $days > 0 ? $days : 90;

		return max( 7, min( 3650, $days ) );
	}

	/**
	 * Effective viewport width below which mobile ad code is served.
	 *
	 * Precedence: filter override, then stored setting, then 782. Filter
	 * values keep the full historical range; only the stored option is
	 * capped by the Settings UI at 1024.
	 *
	 * @since 2.8.0
	 *
	 * @return int Pixel width between 200 and 1200.
	 */
	public static function mobile_breakpoint(): int {
		$breakpoint = (int) apply_filters( 'ad_placr_mobile_breakpoint', Ad_Placr_Plugin::get_settings()['mobile_breakpoint'] );

		return max( 200, min( 1200, $breakpoint ) );
	}

	/**
	 * Effective viewport width below which tablet ad code is served.
	 *
	 * Precedence: filter override, then stored setting; always above the
	 * mobile breakpoint and capped at 1600.
	 *
	 * @since 2.8.0
	 *
	 * @return int Pixel width.
	 */
	public static function tablet_breakpoint(): int {
		$breakpoint = (int) apply_filters( 'ad_placr_tablet_breakpoint', Ad_Placr_Plugin::get_settings()['tablet_breakpoint'] );

		return max( self::mobile_breakpoint() + 1, min( 1600, $breakpoint ) );
	}

	/**
	 * Collapse any partial/raw settings array into the complete safe shape.
	 *
	 * Shared by the option reader and the request sanitizer so both paths
	 * produce identical keys and clamps.
	 *
	 * @since 2.8.0
	 *
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, mixed>
	 */
	public static function normalize_shape( array $raw ): array {
		$mobile = isset( $raw['mobile_breakpoint'] ) ? absint( $raw['mobile_breakpoint'] ) : 782;
		$mobile = max( 200, min( 1024, $mobile ) );

		$tablet = isset( $raw['tablet_breakpoint'] ) ? absint( $raw['tablet_breakpoint'] ) : 1024;
		$tablet = max( $mobile + 1, min( 1600, $tablet ) );

		$retention = isset( $raw['retention_days'] ) ? absint( $raw['retention_days'] ) : 90;
		if ( ! in_array( $retention, self::RETENTION_CHOICES, true ) ) {
			$retention = 90;
		}

		return array(
			'analytics_enabled' => ! empty( $raw['analytics_enabled'] ),
			'retention_days'    => $retention,
			'mobile_breakpoint' => $mobile,
			'tablet_breakpoint' => $tablet,
		);
	}

	/**
	 * Render the settings page with a card-based layout.
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
		$next_cleanup      = wp_next_scheduled( Ad_Placr_Analytics::CRON_HOOK );
		$total_events      = Ad_Placr_Analytics::count_events( 'impression' ) + Ad_Placr_Analytics::count_events( 'click' );
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
								<p><?php esc_html_e( 'Store impression and click totals for each ad. No personal information is collected.', 'ad-placr' ); ?></p>
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

						<div class="ad-placr-setting-row">
							<div class="ad-placr-setting-info">
								<label for="ad-placr-retention-days"><?php esc_html_e( 'Data retention', 'ad-placr' ); ?></label>
								<p><?php esc_html_e( 'How long to keep stored events before automatic cleanup removes them.', 'ad-placr' ); ?></p>
							</div>
							<div class="ad-placr-setting-control">
								<select id="ad-placr-retention-days" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[retention_days]">
									<?php foreach ( self::RETENTION_CHOICES as $days ) : ?>
										<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( (int) $settings['retention_days'], $days ); ?>>
											<?php
											printf(
												/* translators: %d: number of days. */
												esc_html( _n( '%s day', '%s days', $days, 'ad-placr' ) ),
												esc_html( number_format_i18n( $days ) )
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
				</div>

				<div class="ad-placr-settings-card">
					<div class="ad-placr-settings-card-header">
						<div class="ad-placr-settings-card-icon ad-placr-icon-display">
							<span class="dashicons dashicons-desktop"></span>
						</div>
						<div>
							<h2><?php esc_html_e( 'Ad Serving & Breakpoints', 'ad-placr' ); ?></h2>
							<p><?php esc_html_e( 'Viewport widths where mobile and tablet ad code take over.', 'ad-placr' ); ?></p>
						</div>
					</div>

					<div class="ad-placr-settings-card-body">
						<div class="ad-placr-setting-row">
							<div class="ad-placr-setting-info">
								<label for="ad-placr-mobile-breakpoint"><?php esc_html_e( 'Mobile breakpoint (px)', 'ad-placr' ); ?></label>
								<p><?php esc_html_e( 'Screens narrower than this receive mobile ad code. WordPress default: 782.', 'ad-placr' ); ?></p>
							</div>
							<div class="ad-placr-setting-control">
								<input
									type="number"
									id="ad-placr-mobile-breakpoint"
									min="200"
									max="1024"
									step="1"
									value="<?php echo esc_attr( (string) $settings['mobile_breakpoint'] ); ?>"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mobile_breakpoint]"
								/>
							</div>
						</div>

						<div class="ad-placr-setting-row">
							<div class="ad-placr-setting-info">
								<label for="ad-placr-tablet-breakpoint"><?php esc_html_e( 'Tablet breakpoint (px)', 'ad-placr' ); ?></label>
								<p><?php esc_html_e( 'Screens narrower than this (and wider than mobile) receive tablet code. Must stay above the mobile value.', 'ad-placr' ); ?></p>
							</div>
							<div class="ad-placr-setting-control">
								<input
									type="number"
									id="ad-placr-tablet-breakpoint"
									min="201"
									max="1600"
									step="1"
									value="<?php echo esc_attr( (string) $settings['tablet_breakpoint'] ); ?>"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[tablet_breakpoint]"
								/>
							</div>
						</div>
					</div>
				</div>

				<div class="ad-placr-settings-card">
					<div class="ad-placr-settings-card-header">
						<div class="ad-placr-settings-card-icon ad-placr-icon-status">
							<span class="dashicons dashicons-shield-alt"></span>
						</div>
						<div>
							<h2><?php esc_html_e( 'System Status', 'ad-placr' ); ?></h2>
							<p><?php esc_html_e( 'Live health of statistics storage.', 'ad-placr' ); ?></p>
						</div>
					</div>

					<div class="ad-placr-settings-card-body">
						<ul class="ad-placr-status-list">
							<li>
								<span><?php esc_html_e( 'Statistics storage', 'ad-placr' ); ?></span>
								<?php if ( $analytics_enabled ) : ?>
									<strong class="ad-placr-status-ok"><?php esc_html_e( 'Enabled', 'ad-placr' ); ?></strong>
								<?php else : ?>
									<strong class="ad-placr-status-warn"><?php esc_html_e( 'Disabled', 'ad-placr' ); ?></strong>
								<?php endif; ?>
							</li>
							<li>
								<span><?php esc_html_e( 'Stored events', 'ad-placr' ); ?></span>
								<strong><?php echo esc_html( number_format_i18n( $total_events ) ); ?></strong>
							</li>
							<li>
								<span><?php esc_html_e( 'Next automatic cleanup', 'ad-placr' ); ?></span>
								<strong>
									<?php
									echo esc_html(
										$next_cleanup
											? date_i18n( get_option( 'date_format' ) . ' H:i', $next_cleanup )
											: __( 'Not scheduled yet', 'ad-placr' )
									);
									?>
								</strong>
							</li>
						</ul>
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
