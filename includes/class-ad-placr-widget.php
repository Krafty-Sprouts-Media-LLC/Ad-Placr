<?php
/**
 * Classic sidebar widget for manual Placement output.
 *
 * Picks a Placement by ID and renders via Ad_Placr_Renderer. Optional sticky
 * modifier uses assets/css/widget.css. No hard-coded meta keys.
 *
 * @package AdPlacr
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar widget: Placement picker + sticky option.
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
				'description' => __( 'Display an Ad Placr placement in a sidebar.', 'ad-placr' ),
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
		$placement_id = isset( $instance['placement_id'] ) ? absint( $instance['placement_id'] ) : 0;
		$sticky       = ! empty( $instance['sticky'] );

		if ( $placement_id < 1 ) {
			return;
		}

		$modifier = trim( 'ad-placr--sidebar-widget ' . self::sticky_modifier( $sticky ) );

		$html = Ad_Placr_Renderer::render_placement(
			$placement_id,
			array(
				'dom_id'         => 'ad-placr-widget-' . $this->number . '-' . $placement_id,
				'modifier_class' => $modifier,
				'breakpoint'     => Ad_Placr_Renderer::resolve_breakpoint(),
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
			'placement_id' => isset( $new_instance['placement_id'] ) ? absint( $new_instance['placement_id'] ) : 0,
			'sticky'       => ! empty( $new_instance['sticky'] ) ? 1 : 0,
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
		$placement_id = isset( $instance['placement_id'] ) ? absint( $instance['placement_id'] ) : 0;
		$sticky       = ! empty( $instance['sticky'] );
		$placements   = $this->published_placements();

		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'placement_id' ) ); ?>">
				<?php esc_html_e( 'Placement', 'ad-placr' ); ?>
			</label>
			<select
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'placement_id' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'placement_id' ) ); ?>"
			>
				<option value="0"><?php esc_html_e( '— Select —', 'ad-placr' ); ?></option>
				<?php foreach ( $placements as $post ) : ?>
					<option value="<?php echo esc_attr( (string) $post->ID ); ?>" <?php selected( $placement_id, (int) $post->ID ); ?>>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: placement title, 2: placement ID */
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
	 * Published placement posts for the admin select.
	 *
	 * @since 2.3.0
	 *
	 * @return WP_Post[]
	 */
	private function published_placements(): array {
		/** @var WP_Post[] $posts */
		$posts = get_posts(
			array(
				'post_type'              => Ad_Placr_Placement::POST_TYPE,
				'post_status'            => 'publish',
				// Admin select only — capped so the widget form stays usable.
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $posts;
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
