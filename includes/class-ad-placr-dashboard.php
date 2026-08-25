<?php
/**
 * Ads Dashboard: metric cards and performance summary table.
 *
 * Renders an overview page under the Ad Placr CPT menu showing aggregate
 * impressions, clicks, CTR, and a per-ad performance breakdown sourced from
 * the first-party analytics storage.
 *
 * @package AdPlacr
 * @since 2.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the Ads Dashboard submenu page.
 *
 * @since 2.7.0
 */
final class Ad_Placr_Dashboard {

	/**
	 * Register admin hooks for the dashboard page.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add the Dashboard submenu beneath Ads.
	 *
	 * Position 0 places it first in the CPT submenu list.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Ad_Placr_Ad::POST_TYPE,
			__( 'Dashboard', 'ad-placr' ),
			__( 'Dashboard', 'ad-placr' ),
			Ad_Placr_Settings_Page::CAPABILITY,
			'ad-placr-dashboard',
			array( __CLASS__, 'render_page' ),
			0
		);
	}

	/**
	 * Enqueue dashboard stylesheet on the dashboard page only.
	 *
	 * @since 2.7.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( Ad_Placr_Ad::POST_TYPE . '_page_ad-placr-dashboard' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'ad-placr-dashboard',
			AD_PLACR_PLUGIN_URL . 'assets/css/dashboard.css',
			array(),
			AD_PLACR_VERSION
		);
	}

	/**
	 * Render the full dashboard page.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( Ad_Placr_Settings_Page::CAPABILITY ) ) {
			return;
		}

		$storage_enabled = Ad_Placr_Analytics::is_storage_enabled();
		$impressions     = Ad_Placr_Analytics::count_events( 'impression' );
		$clicks          = Ad_Placr_Analytics::count_events( 'click' );
		$ctr             = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0;
		$counts          = self::get_ad_counts();
		$top_ads         = self::get_top_ads();
		?>
		<div class="wrap ad-placr-dashboard-wrap">
			<h1><?php esc_html_e( 'Ads Dashboard', 'ad-placr' ); ?></h1>
			<p class="ad-placr-dashboard-subtitle"><?php esc_html_e( 'Overview of your advertising performance.', 'ad-placr' ); ?></p>

			<?php if ( ! $storage_enabled ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: %s: URL to the settings page. */
							esc_html__( 'Analytics storage is currently disabled. Enable it in %s to start tracking impressions and clicks.', 'ad-placr' ),
							'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . Ad_Placr_Ad::POST_TYPE . '&page=ad-placr' ) ) . '">' . esc_html__( 'Settings', 'ad-placr' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Metric Cards -->
			<div class="ad-placr-metrics-grid">
				<div class="ad-placr-dashboard-card ad-placr-metric-card">
					<span class="ad-placr-metric-label"><?php esc_html_e( 'Total Impressions', 'ad-placr' ); ?></span>
					<span class="ad-placr-metric-value"><?php echo esc_html( number_format_i18n( $impressions ) ); ?></span>
				</div>

				<div class="ad-placr-dashboard-card ad-placr-metric-card">
					<span class="ad-placr-metric-label"><?php esc_html_e( 'Total Clicks', 'ad-placr' ); ?></span>
					<span class="ad-placr-metric-value"><?php echo esc_html( number_format_i18n( $clicks ) ); ?></span>
				</div>

				<div class="ad-placr-dashboard-card ad-placr-metric-card">
					<span class="ad-placr-metric-label"><?php esc_html_e( 'Overall CTR', 'ad-placr' ); ?></span>
					<span class="ad-placr-metric-value"><?php echo esc_html( number_format( $ctr, 2 ) . '%' ); ?></span>
				</div>

				<div class="ad-placr-dashboard-card ad-placr-metric-card">
					<span class="ad-placr-metric-label"><?php esc_html_e( 'Active / Paused', 'ad-placr' ); ?></span>
					<span class="ad-placr-metric-value">
						<?php
						printf(
							'%s / %s',
							esc_html( number_format_i18n( $counts['active'] ) ),
							esc_html( number_format_i18n( $counts['paused'] ) )
						);
						?>
					</span>
				</div>
			</div>

			<!-- Performance Table -->
			<div class="ad-placr-dashboard-card">
				<div class="ad-placr-dashboard-card-header">
					<h2><?php esc_html_e( 'Ad Performance', 'ad-placr' ); ?></h2>
				</div>

				<?php if ( empty( $top_ads ) ) : ?>
					<p class="ad-placr-dashboard-empty"><?php esc_html_e( 'No ads found. Create your first ad to see performance data here.', 'ad-placr' ); ?></p>
				<?php else : ?>
					<table class="ad-placr-dashboard-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Ad', 'ad-placr' ); ?></th>
								<th><?php esc_html_e( 'Position', 'ad-placr' ); ?></th>
								<th><?php esc_html_e( 'Impressions', 'ad-placr' ); ?></th>
								<th><?php esc_html_e( 'Clicks', 'ad-placr' ); ?></th>
								<th><?php esc_html_e( 'CTR', 'ad-placr' ); ?></th>
								<th><?php esc_html_e( 'Status', 'ad-placr' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $top_ads as $ad ) : ?>
								<?php
								$row_class = 'paused' === $ad['status'] ? 'ad-placr-row-paused' : '';
								$ctr_value = $ad['ctr'];

								/* Color-code CTR: green > 3%, amber 2–3%, gray < 2%. */
								if ( $ctr_value > 3 ) {
									$ctr_class = 'ad-placr-ctr-good';
								} elseif ( $ctr_value >= 2 ) {
									$ctr_class = 'ad-placr-ctr-ok';
								} else {
									$ctr_class = 'ad-placr-ctr-low';
								}
								?>
								<tr class="<?php echo esc_attr( $row_class ); ?>">
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $ad['id'], 'raw' ) ); ?>">
											<?php echo esc_html( $ad['title'] ); ?>
										</a>
									</td>
									<td>
										<code class="ad-placr-position-badge"><?php echo esc_html( $ad['position'] ); ?></code>
									</td>
									<td><?php echo esc_html( number_format_i18n( $ad['impressions'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $ad['clicks'] ) ); ?></td>
									<td>
										<span class="<?php echo esc_attr( $ctr_class ); ?>">
											<?php echo esc_html( number_format( $ad['ctr'], 2 ) . '%' ); ?>
										</span>
									</td>
									<td>
										<?php if ( 'active' === $ad['status'] ) : ?>
											<span class="ad-placr-status-badge ad-placr-status-active"><?php esc_html_e( 'Active', 'ad-placr' ); ?></span>
										<?php else : ?>
											<span class="ad-placr-status-badge ad-placr-status-paused"><?php esc_html_e( 'Paused', 'ad-placr' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Count active and paused ads.
	 *
	 * @since 2.7.0
	 *
	 * @return array{active:int, paused:int, total:int}
	 */
	private static function get_ad_counts(): array {
		$active = get_posts(
			array(
				'post_type'      => Ad_Placr_Ad::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$paused = get_posts(
			array(
				'post_type'      => Ad_Placr_Ad::POST_TYPE,
				'post_status'    => 'draft',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$active_count = count( $active );
		$paused_count = count( $paused );

		return array(
			'active' => $active_count,
			'paused' => $paused_count,
			'total'  => $active_count + $paused_count,
		);
	}

	/**
	 * Retrieve top ads with their performance metrics.
	 *
	 * Returns all published and draft ads with impressions, clicks, and CTR
	 * computed from the first-party analytics storage.
	 *
	 * @since 2.7.0
	 *
	 * @param int $limit Maximum number of ads to return.
	 * @return array<int, array{id:int, title:string, position:string, status:string, impressions:int, clicks:int, ctr:float}>
	 */
	private static function get_top_ads( int $limit = 10 ): array {
		$posts = get_posts(
			array(
				'post_type'      => Ad_Placr_Ad::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$ads = array();

		foreach ( $posts as $post ) {
			$ad_id        = (int) $post->ID;
			$position_key = (string) get_post_meta( $ad_id, Ad_Placr_Ad::META_POSITION, true );

			/* Resolve human-readable label; fall back to the raw key. */
			$position_label = Ad_Placr_Positions::label( $position_key );
			if ( '' === $position_label ) {
				$position_label = $position_key;
			}

			$impressions = Ad_Placr_Analytics::count_events( 'impression', $ad_id );
			$clicks      = Ad_Placr_Analytics::count_events( 'click', $ad_id );
			$ctr         = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0;

			$ads[] = array(
				'id'          => $ad_id,
				'title'       => get_the_title( $ad_id ),
				'position'    => $position_label,
				'status'      => 'publish' === get_post_status( $ad_id ) ? 'active' : 'paused',
				'impressions' => $impressions,
				'clicks'      => $clicks,
				'ctr'         => (float) $ctr,
			);
		}

		return $ads;
	}
}
