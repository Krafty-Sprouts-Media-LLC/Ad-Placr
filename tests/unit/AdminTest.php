<?php
/**
 * Tests for the unified Ad administration helpers.
 *
 * @package AdPlacr
 * @since 2.7.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers pure validation and normalization used by the Ad editor.
 *
 * @since 2.7.0
 */
final class AdminTest extends TestCase {

	/**
	 * An Active Ad requires a display location and eligible code.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_activation_requires_location_and_eligible_code(): void {
		$this->assertSame(
			array(
				'Choose where this ad should appear before activating it.',
				'Add your ad code before activating this Ad.',
			),
			Ad_Placr_Admin::activation_errors( '', array() )
		);
	}

	/**
	 * A Paused Ad may retain an incomplete draft.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_paused_ad_may_keep_incomplete_values(): void {
		$this->assertSame( array(), Ad_Placr_Admin::save_errors( 'draft', '', array() ) );
	}

	/**
	 * Existing version IDs survive while missing IDs are generated.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_version_rows_keep_existing_ids_and_generate_missing_ids(): void {
		$rows = Ad_Placr_Admin::normalize_version_rows(
			array(
				array( 'version_id' => 'existing-id', 'name' => 'Version A', 'code' => '<ins>a</ins>', 'mobile_code' => '', 'weight' => '3', 'enabled' => '1' ),
				array( 'version_id' => '', 'name' => '', 'code' => '<ins>b</ins>', 'mobile_code' => '', 'weight' => '1', 'enabled' => '1' ),
			),
			true,
			static fn(): string => 'generated-id'
		);

		$this->assertSame( 'existing-id', $rows[0]['version_id'] );
		$this->assertSame( 'generated-id', $rows[1]['version_id'] );
		$this->assertSame( 'Version B', $rows[1]['name'] );
	}

	/**
	 * Version values are normalized before they reach post meta.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_version_rows_sanitize_names_filter_code_and_clamp_weights(): void {
		$rows = Ad_Placr_Admin::normalize_version_rows(
			array(
				array(
					'version_id'  => 'safe-id',
					'name'        => '<b>Network tag</b>',
					'code'        => '<script>alert(1)</script><ins>safe</ins>',
					'mobile_code' => '',
					'weight'      => '0',
					'enabled'     => '',
				),
			),
			false
		);

		$this->assertSame( 'Network tag', $rows[0]['name'] );
		$this->assertSame( 'alert(1)<ins>safe</ins>', $rows[0]['code'] );
		$this->assertSame( 1, $rows[0]['weight'] );
		$this->assertFalse( $rows[0]['enabled'] );
	}

	/**
	 * A complete Active Ad has no validation errors.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_active_ad_with_location_and_code_has_no_errors(): void {
		$versions = array(
			array(
				'version_id'  => 'version-a',
				'name'        => 'Version A',
				'code'        => '<ins>ready</ins>',
				'mobile_code' => '',
				'weight'      => 1,
				'enabled'     => true,
			),
		);

		$this->assertSame(
			array(),
			Ad_Placr_Admin::save_errors( 'publish', 'before_post_content', $versions )
		);
	}

	/**
	 * Native edit paths that bypass activation validation are unavailable.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_native_status_edit_actions_are_removed(): void {
		$row_actions = Ad_Placr_Admin::remove_unvalidated_status_actions(
			array(
				'edit'                  => 'Edit',
				'inline hide-if-no-js' => 'Quick Edit',
				'trash'                 => 'Trash',
			)
		);
		$bulk_actions = Ad_Placr_Admin::remove_bulk_edit_action(
			array(
				'edit'  => 'Edit',
				'trash' => 'Move to Trash',
			)
		);

		$this->assertSame(
			array(
				'edit'  => 'Edit',
				'trash' => 'Trash',
			),
			$row_actions
		);
		$this->assertSame( array( 'trash' => 'Move to Trash' ), $bulk_actions );
	}

	/**
	 * Saving statistics returns the complete clean settings shape.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function test_statistics_setting_discards_unreleased_legacy_values(): void {
		$updated = Ad_Placr_Settings_Page::sanitize_settings(
			array(
				'footer_sticky'     => array( 'enabled' => true ),
				'in_content_slots'  => array( array( 'id' => 'old-slot' ) ),
				'analytics_enabled' => true,
			)
		);

		$this->assertSame( array( 'analytics_enabled' => true ), $updated );
	}
}
