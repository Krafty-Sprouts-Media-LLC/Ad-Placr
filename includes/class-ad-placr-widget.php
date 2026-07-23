<?php
/**
 * Classic sidebar widget for manual Ad output.
 *
 * Picks one sidebar-widget Ad ID and renders through the shared targeting and
 * renderer services. The optional sticky modifier uses assets/css/widget.css.
 *
 * @package AdPlacr
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar widget: Ad picker + sticky option.
 *
 * @since 2.3.0
 */
final class Ad_Placr_Widget extends WP_Widget {

	/**
	 * Register the widget class on widgets_init.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'widgets_init',
			static function (): void {
				register_widget( __CLASS__ );
			}
		);
	}

	/**
	 * Sticky modifier class when the sticky checkbox is on.
	 *
	 * @since 2.3.0
	 *
	 * @param bool $sticky Whether sticky is enabled.
	 * @return string Modifier class fragment (may be empty).
	 */
	public static function sticky_modifier( bool $sticky ): string {
		return $sticky ? 'ad-placr--widget-sticky' : '';
	}

	/**
	 * Widget constructor.
	 *
	 * @since 2.3.0
	 */
	public function __construct() {
		parent::__construct(
			'ad_placr',
			__( 'Ad Placr', 'ad-placr' ),
			array(
				'description' => __( 'Display an Ad Placr Ad in a sidebar.', 'ad-placr' ),
				'classname'   => 'ad-placr-widget',
			)
		);
	}

	/**
	 * Front-end output.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string, mixed> $args     Widget display args.
	 * @param array<string, mixed> $instance Saved settings.
	 * @return void
	 */
	public function widget( $args, $instance ): void {
		$ad_id  = isset( $instance['ad_id'] ) ? absint( $instance['ad_id'] ) : 0;
		$sticky = ! empty( $instance['sticky'] );

		if ( $ad_id < 1 ) {
			return;
		}

		if ( Ad_Placr_Positions::SIDEBAR_WIDGET !== Ad_Placr_Ad::get_position( $ad_id ) ) {
			return;
		}

		$ctx = Ad_Placr_Targeting::build_request_context();
		if ( ! Ad_Placr_Targeting::should_display( $ad_id, $ctx ) ) {
			return;
		}
		$modifier = trim( 'ad-placr--sidebar-widget ' . self::sticky_modifier( $sticky ) );

		$html = Ad_Placr_Renderer::render_ad(
			$ad_id,
			array(
				'dom_id'         => 'ad-placr-widget-' . $this->number . '-' . $ad_id,
				'modifier_class' => $modifier,
			)
		);
		if ( '' === $html ) {
			return;
		}

		if ( $sticky ) {
			$this->enqueue_sticky_css();
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- before/after_widget from core; ad HTML from Renderer.
		echo $args['before_widget'];
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network code; stored by privileged users.
		echo $html;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- before/after_widget from core.
		echo $args['after_widget'];
	}

	/**
	 * Sanitize widget settings on save.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string, mixed> $new_instance New settings.
	 * @param array<string, mixed> $old_instance Previous settings.
	 * @return array<string, mixed>
	 */
	public function update( $new_instance, $old_instance ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return array(
			'ad_id'  => isset( $new_instance['ad_id'] ) ? absint( $new_instance['ad_id'] ) : 0,
			'sticky' => ! empty( $new_instance['sticky'] ) ? 1 : 0,
		);
	}

	/**
	 * Admin form.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string, mixed> $instance Current settings.
	 * @return void
	 */
	public function form( $instance ): void {
		$ad_id  = isset( $instance['ad_id'] ) ? absint( $instance['ad_id'] ) : 0;
		$sticky = ! empty( $instance['sticky'] );
		$ads    = $this->published_sidebar_ads();

		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>">
				<?php esc_html_e( 'Choose an Ad', 'ad-placr' ); ?>
			</label>
			<select
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'ad_id' ) ); ?>"
			>
				<option value="0"><?php esc_html_e( '— Select —', 'ad-placr' ); ?></option>
				<?php foreach ( $ads as $post ) : ?>
					<option value="<?php echo esc_attr( (string) $post->ID ); ?>" <?php selected( $ad_id, (int) $post->ID ); ?>>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: Ad title, 2: Ad ID */
								__( '%1$s (#%2$d)', 'ad-placr' ),
								$post->post_title ? $post->post_title : __( '(no title)', 'ad-placr' ),
								(int) $post->ID
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'Create an Ad with “Sidebar widget” as its display location, then select it here.', 'ad-placr' ); ?>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'sticky' ) ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $this->get_field_id( 'sticky' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'sticky' ) ); ?>"
					value="1"
					<?php checked( $sticky ); ?>
				/>
				<?php esc_html_e( 'Sticky within sidebar', 'ad-placr' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Published sidebar-widget Ads for the admin select.
	 *
	 * @since 2.3.0
	 *
	 * @return WP_Post[]
	 */
	private function published_sidebar_ads(): array {
		$ad_ids = Ad_Placr_Ad::query_ids_for_position( Ad_Placr_Positions::SIDEBAR_WIDGET );
		if ( empty( $ad_ids ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'              => Ad_Placr_Ad::POST_TYPE,
				'post_status'            => 'publish',
				// Admin select only — capped so the widget form stays usable.
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'post__in'               => $ad_ids,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$out[] = $post;
			}
		}

		return $out;
	}

	/**
	 * Enqueue sticky widget CSS once per request.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	private function enqueue_sticky_css(): void {
		$handle = 'ad-placr-widget';
		if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'done' ) ) {
			return;
		}

		wp_enqueue_style(
			$handle,
			AD_PLACR_PLUGIN_URL . 'assets/css/widget.css',
			array(),
			AD_PLACR_VERSION
		);
	}
}
