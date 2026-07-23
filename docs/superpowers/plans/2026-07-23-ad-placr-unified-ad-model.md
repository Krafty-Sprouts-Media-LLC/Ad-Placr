# Ad Placr Unified Ad Model Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the unreleased Ad/Placement workflow with one complete Ad record that owns its display location, rules, status, weighted code versions, and statistics.

**Architecture:** Keep `ad_placr_ad` as the only management post type and move the useful Placement fields onto it. Convert the shared renderer and every automatic/manual entry point to accept an Ad ID, select one eligible weighted version, and attach the Ad/version identity used by analytics. A versioned, source-mapped migration converts either public v0.1.5 settings or local two-record data without deleting its sources.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, native WordPress admin, zero-build JavaScript, PHPUnit 9 + Brain Monkey, PHPCS with WordPress Coding Standards, PHPStan with phpstan-wordpress.

## Global Constraints

- Target release is **2.7.0**.
- WordPress **6.0+** and PHP **8.0+** remain the source-of-truth platform floors.
- There is one user-facing **Ads** menu, one **Add New Ad** action, and no Placements menu.
- Use ordinary language from the approved specification: “Display location,” “Ad code,” “Ad version,” “How often should this version appear?”, “Active,” and “Paused.”
- Do not expose CPT, meta, hook, renderer, predicate, taxonomy, creative, placement, variant, or rotation in normal UI copy.
- Use native WordPress admin and zero-build JavaScript; add no runtime dependency or bundled third-party UI library.
- Keep all meta keys as constants on `Ad_Placr_Ad`; storage keys are `_ad_placr_position`, `_ad_placr_targeting`, `_ad_placr_versions`, and `_ad_placr_notes`.
- WordPress post status is authoritative: `publish` is Active and every other non-trash status is Paused.
- Store ad-network code only for `manage_options` users; run it through `wp_kses_post()` when the current user lacks `unfiltered_html`.
- Every raw ad-code echo must use the project’s documented PHPCS exception comment.
- Every PHP file, class, member, method, property, constant, and new hook must follow `AGENTS.md` DocBlock and `@since` rules. Preserve every existing `@since`.
- Use tabs, `array()` syntax, Yoda conditions, spaced calls, and the existing `Ad_Placr_*` naming convention.
- Large or rarely read options must use autoload `false`.
- Preserve source settings, old Placement posts, old creative-only Ad posts, and old code meta during migration verification. The unreleased Placement-based analytics test rows may be discarded because the plugin has no production users.
- Do not extend `Ad_Placr_Placement`; isolate legacy reads inside `Ad_Placr_Migration`, then remove the Placement class from runtime and test bootstraps.
- Each task uses the test-driven cycle: add a focused failing test, run it and observe the expected failure, implement the minimum complete behavior, run the focused and full suites, then commit.

---

## File Structure

**Created:**

- `assets/js/admin.js` — add/remove/reorder Ad versions, calculate traffic shares, and reveal location-specific controls without a build step.
- `assets/css/admin.css` — small accessible layout for the unified editor and version rows.
- `tests/unit/UnifiedAdTest.php` — unified meta, status, version normalization, selection, and ordering behavior.
- `tests/unit/AdminTest.php` — pure validation, save normalization, list labels, and duplicate-copy behavior.

**Modified:**

- `includes/class-ad-placr-ad.php` — the complete Ad domain model, registered meta, status, versions, display location, rules, and deterministic queries.
- `includes/class-ad-placr-renderer.php` — version selection, responsive device/mobile CSS, and `data-ad-id`/`data-version-id`.
- `includes/class-ad-placr-targeting.php` — one Ad-based display-rule gate.
- `includes/class-ad-placr-frontend.php` — render all matching automatic Ads in deterministic order.
- `includes/class-ad-placr-footer-sticky.php` — use Ad IDs and render every matching sticky-footer Ad.
- `includes/class-ad-placr-in-content.php` — use Ad IDs while preserving the single-pass paragraph insertion algorithm.
- `includes/class-ad-placr-shortcode.php` — render one `manual_shortcode` Ad by ID.
- `includes/class-ad-placr-widget.php` — select and render one `sidebar_widget` Ad.
- `includes/class-ad-placr-admin.php` — one editor, save validation, plain-language list columns, duplication, status actions, and warnings.
- `includes/class-ad-placr-settings-page.php` — retain only the first-party statistics opt-in under the Ads menu while preserving legacy option data for migration audit.
- `includes/class-ad-placr-analytics.php` — store/query stable version IDs in a clean Ad/version event schema.
- `includes/class-ad-placr-rest.php` — accept `version_id` instead of `placement_id`.
- `assets/js/tracking.js` — send the selected version ID.
- `includes/class-ad-placr-migration.php` — source-mapped v0.1.5 and local two-record migrations.
- `includes/class-ad-placr-plugin.php` — stop registering Placements and register only unified subsystems.
- `ad-placr.php` — stop loading the Placement class, update product copy, and bump to 2.7.0.
- `tests/bootstrap.php` — replace the Placement dependency with the unified model.
- `tests/unit/RendererTest.php`, `TargetingTest.php`, `ShortcodeTest.php`, `AnalyticsTest.php`, `MigrationBuilderTest.php`, `ManualMetaKeysTest.php`, `FrontendContextTest.php`, `PositionsRegistryTest.php` — update the behavioral contract.
- `readme.txt`, `changelog.md`, `development.md`, `roadmap.md`, `uninstall.php` — release and architecture documentation plus migration-option cleanup.

**Deleted after migration reads are isolated:**

- `admin/css/settings-slots.css`
- `admin/js/in-content-slots.js`
- `includes/class-ad-placr-placement.php`
- `tests/unit/PlacementLogicTest.php`
- `tests/unit/PlacementTargetingTest.php`

---

### Task 1: Complete Unified Ad Domain Model

**Files:**

- Modify: `includes/class-ad-placr-ad.php`
- Create: `tests/unit/UnifiedAdTest.php`
- Modify: `tests/unit/ManualMetaKeysTest.php`
- Delete: `tests/unit/PlacementLogicTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**

- Consumes: `Ad_Placr_Positions::exists( string ): bool`.
- Produces:
  - `Ad_Placr_Ad::META_POSITION`, `META_TARGETING`, `META_VERSIONS`, `META_NOTES`.
  - `Ad_Placr_Ad::normalize_versions( mixed $raw ): array<int,array{version_id:string,name:string,code:string,mobile_code:string,weight:int,enabled:bool}>`.
  - `Ad_Placr_Ad::eligible_versions( array $versions ): array`.
  - `Ad_Placr_Ad::choose_weighted_version( array $versions, int $roll ): ?array`.
  - `Ad_Placr_Ad::get_versions( int $ad_id ): array`.
  - `Ad_Placr_Ad::get_position( int $ad_id ): string`.
  - `Ad_Placr_Ad::get_targeting( int $ad_id ): array<string,mixed>`.
  - `Ad_Placr_Ad::is_active( int $ad_id ): bool`.
  - `Ad_Placr_Ad::query_ids_for_position( string $position ): int[]`, ordered `menu_order ASC, ID ASC`.

- [ ] **Step 1: Replace the old two-record logic tests with the unified contract**

Create `tests/unit/UnifiedAdTest.php` with these complete cases:

```php
<?php

use PHPUnit\Framework\TestCase;

final class UnifiedAdTest extends TestCase {

	public function test_normalize_versions_preserves_stable_ids_and_order(): void {
		$raw = array(
			array(
				'version_id'  => '11111111-1111-4111-8111-111111111111',
				'name'        => 'Version B',
				'code'        => '<ins>b</ins>',
				'mobile_code' => '',
				'weight'      => 3,
				'enabled'     => true,
			),
			array(
				'version_id'  => '22222222-2222-4222-8222-222222222222',
				'name'        => 'Version A',
				'code'        => '<ins>a</ins>',
				'mobile_code' => '<ins>mobile</ins>',
				'weight'      => 1,
				'enabled'     => false,
			),
		);

		$normalized = Ad_Placr_Ad::normalize_versions( $raw );

		$this->assertSame( '11111111-1111-4111-8111-111111111111', $normalized[0]['version_id'] );
		$this->assertSame( '22222222-2222-4222-8222-222222222222', $normalized[1]['version_id'] );
		$this->assertSame( 3, $normalized[0]['weight'] );
		$this->assertFalse( $normalized[1]['enabled'] );
	}

	public function test_normalize_versions_drops_rows_without_a_stable_id(): void {
		$normalized = Ad_Placr_Ad::normalize_versions(
			array(
				array( 'name' => 'Missing ID', 'code' => '<ins>x</ins>', 'weight' => 1, 'enabled' => true ),
				'not-an-array',
			)
		);

		$this->assertSame( array(), $normalized );
	}

	public function test_eligible_versions_excludes_disabled_and_empty_rows(): void {
		$versions = Ad_Placr_Ad::normalize_versions(
			array(
				array( 'version_id' => 'a', 'name' => 'A', 'code' => '<ins>a</ins>', 'mobile_code' => '', 'weight' => 2, 'enabled' => true ),
				array( 'version_id' => 'b', 'name' => 'B', 'code' => '', 'mobile_code' => '', 'weight' => 5, 'enabled' => true ),
				array( 'version_id' => 'c', 'name' => 'C', 'code' => '<ins>c</ins>', 'mobile_code' => '', 'weight' => 3, 'enabled' => false ),
				array( 'version_id' => 'd', 'name' => 'D', 'code' => '', 'mobile_code' => '<ins>mobile</ins>', 'weight' => 1, 'enabled' => true ),
			)
		);

		$eligible = Ad_Placr_Ad::eligible_versions( $versions );

		$this->assertSame( array( 'a', 'd' ), array_column( $eligible, 'version_id' ) );
	}

	public function test_weighted_selection_uses_only_eligible_versions(): void {
		$versions = Ad_Placr_Ad::normalize_versions(
			array(
				array( 'version_id' => 'a', 'name' => 'A', 'code' => 'A', 'mobile_code' => '', 'weight' => 3, 'enabled' => true ),
				array( 'version_id' => 'b', 'name' => 'B', 'code' => 'B', 'mobile_code' => '', 'weight' => 99, 'enabled' => false ),
				array( 'version_id' => 'c', 'name' => 'C', 'code' => 'C', 'mobile_code' => '', 'weight' => 1, 'enabled' => true ),
			)
		);

		$this->assertSame( 'a', Ad_Placr_Ad::choose_weighted_version( $versions, 0 )['version_id'] );
		$this->assertSame( 'a', Ad_Placr_Ad::choose_weighted_version( $versions, 2 )['version_id'] );
		$this->assertSame( 'c', Ad_Placr_Ad::choose_weighted_version( $versions, 3 )['version_id'] );
		$this->assertNull( Ad_Placr_Ad::choose_weighted_version( array(), 0 ) );
	}
}
```

Update `tests/unit/ManualMetaKeysTest.php` to assert exactly:

```php
$this->assertSame( '_ad_placr_position', Ad_Placr_Ad::META_POSITION );
$this->assertSame( '_ad_placr_targeting', Ad_Placr_Ad::META_TARGETING );
$this->assertSame( '_ad_placr_versions', Ad_Placr_Ad::META_VERSIONS );
$this->assertSame( '_ad_placr_notes', Ad_Placr_Ad::META_NOTES );
```

Remove every `Ad_Placr_Placement` assertion and delete `tests/unit/PlacementLogicTest.php`. Keep the Placement require in `tests/bootstrap.php` and keep the old Placement runtime file temporarily so unchanged paths remain loadable until Tasks 3–8 finish removing their dependencies.

- [ ] **Step 2: Run the focused tests and confirm the contract fails**

Run:

```powershell
vendor\bin\phpunit --filter "UnifiedAdTest|ManualMetaKeysTest"
```

Expected: FAIL because the unified constants and version methods do not exist and the old constants still do.

- [ ] **Step 3: Replace the Ad constants and implement the pure version methods**

In `includes/class-ad-placr-ad.php`, replace the old code/mobile/devices/status constants with:

```php
/**
 * Display-location meta key.
 *
 * @since 2.7.0
 */
public const META_POSITION = '_ad_placr_position';

/**
 * Display-rule meta key.
 *
 * @since 2.7.0
 */
public const META_TARGETING = '_ad_placr_targeting';

/**
 * Ordered ad-version meta key.
 *
 * @since 2.7.0
 */
public const META_VERSIONS = '_ad_placr_versions';

/**
 * Private administrator notes meta key.
 *
 * @since 2.0.0
 */
public const META_NOTES = '_ad_placr_notes';
```

Add the following behavior, including full project-standard DocBlocks:

```php
public static function normalize_versions( $raw ): array {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$versions = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$version_id = isset( $row['version_id'] ) ? trim( (string) $row['version_id'] ) : '';
		$version_id = (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', $version_id );
		if ( '' === $version_id ) {
			continue;
		}

		$versions[] = array(
			'version_id'  => substr( $version_id, 0, 64 ),
			'name'        => isset( $row['name'] ) ? trim( (string) $row['name'] ) : '',
			'code'        => isset( $row['code'] ) ? (string) $row['code'] : '',
			'mobile_code' => isset( $row['mobile_code'] ) ? (string) $row['mobile_code'] : '',
			'weight'      => max( 1, isset( $row['weight'] ) ? (int) $row['weight'] : 1 ),
			'enabled'     => ! empty( $row['enabled'] ),
		);
	}

	return $versions;
}

public static function eligible_versions( array $versions ): array {
	$eligible = array();
	foreach ( self::normalize_versions( $versions ) as $version ) {
		if ( ! $version['enabled'] ) {
			continue;
		}
		if ( '' === trim( $version['code'] ) && '' === trim( $version['mobile_code'] ) ) {
			continue;
		}
		$eligible[] = $version;
	}

	return $eligible;
}

public static function choose_weighted_version( array $versions, int $roll ): ?array {
	$eligible = self::eligible_versions( $versions );
	$total    = array_sum( array_column( $eligible, 'weight' ) );
	if ( $total < 1 ) {
		return null;
	}

	$roll   = ( ( $roll % $total ) + $total ) % $total;
	$cursor = 0;
	foreach ( $eligible as $version ) {
		$cursor += $version['weight'];
		if ( $roll < $cursor ) {
			return $version;
		}
	}

	return null;
}
```

- [ ] **Step 4: Register unified meta and add WordPress accessors**

Register `META_POSITION` as a private string and `META_TARGETING`/`META_VERSIONS` as private arrays, all single-value and authorized with `manage_options`. Retain the existing notes registration. Replace old status/code accessors with:

```php
public static function is_active( int $ad_id ): bool {
	return self::POST_TYPE === get_post_type( $ad_id ) && 'publish' === get_post_status( $ad_id );
}

public static function get_position( int $ad_id ): string {
	$position = (string) get_post_meta( $ad_id, self::META_POSITION, true );

	return Ad_Placr_Positions::exists( $position ) ? $position : '';
}

public static function get_targeting( int $ad_id ): array {
	$targeting = get_post_meta( $ad_id, self::META_TARGETING, true );

	return is_array( $targeting ) ? $targeting : array();
}

public static function get_versions( int $ad_id ): array {
	return self::normalize_versions( get_post_meta( $ad_id, self::META_VERSIONS, true ) );
}

public static function query_ids_for_position( string $position ): array {
	if ( ! Ad_Placr_Positions::exists( $position ) ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			'meta_key'       => self::META_POSITION,
			'meta_value'     => $position,
			'no_found_rows'  => true,
		)
	);
}
```

Keep the CPT private, visible in the native admin, title-only, `show_in_rest => false`, and labels based only on “Ad/Ads.” Assign every primitive and meta capability (`edit_post`, `read_post`, `delete_post`, `edit_posts`, `edit_others_posts`, `publish_posts`, `read_private_posts`, `delete_posts`, `delete_private_posts`, `delete_published_posts`, `delete_others_posts`, `edit_private_posts`, and `edit_published_posts`) to `manage_options`, with `map_meta_cap => false`.

- [ ] **Step 5: Run the focused and full suites**

Run:

```powershell
vendor\bin\phpunit --filter "UnifiedAdTest|ManualMetaKeysTest"
vendor\bin\phpunit
```

Expected: focused tests PASS. The full suite may still fail only in old Placement-dependent renderer/targeting/manual tests; record those failures as the red tests resolved by Tasks 2–4.

- [ ] **Step 6: Commit the domain model**

```powershell
git add includes/class-ad-placr-ad.php tests/bootstrap.php tests/unit/UnifiedAdTest.php tests/unit/ManualMetaKeysTest.php tests/unit/PlacementLogicTest.php
git commit -m "refactor: make each Ad a complete display record"
```

---

### Task 2: Select and Render Ad Versions Responsively

**Files:**

- Modify: `includes/class-ad-placr-renderer.php`
- Modify: `tests/unit/RendererTest.php`

**Interfaces:**

- Consumes: `Ad_Placr_Ad::is_active()`, `get_versions()`, and `choose_weighted_version()`.
- Produces:
  - `Ad_Placr_Renderer::render_ad( int $ad_id, array $args ): string`.
  - `Ad_Placr_Renderer::build_wrapper_html( string $dom_id, string $modifier, string $inner, int $ad_id, string $version_id ): string`.
  - `Ad_Placr_Renderer::resolve_mobile_breakpoint(): int`, filter `ad_placr_mobile_breakpoint`.
  - `Ad_Placr_Renderer::resolve_tablet_breakpoint(): int`, filter `ad_placr_tablet_breakpoint`.
  - `Ad_Placr_Renderer::build_responsive_css( string $selector, int $mobile_max, int $tablet_max, array $devices, bool $has_mobile_code ): string`.

- [ ] **Step 1: Update renderer tests to describe the unified output**

Replace Placement assertions in `tests/unit/RendererTest.php` with:

```php
public function test_wrapper_identifies_ad_and_stable_version(): void {
	$html = Ad_Placr_Renderer::build_wrapper_html(
		'ad-placr-test',
		'ad-placr--pos-sticky_footer',
		'<span>code</span>',
		42,
		'11111111-1111-4111-8111-111111111111'
	);

	$this->assertStringContainsString( 'data-ad-id="42"', $html );
	$this->assertStringContainsString( 'data-version-id="11111111-1111-4111-8111-111111111111"', $html );
	$this->assertStringNotContainsString( 'data-placement-id', $html );
}

public function test_responsive_css_swaps_mobile_code_and_hides_unselected_tablet(): void {
	$css = Ad_Placr_Renderer::build_responsive_css(
		'#ad-placr-test',
		782,
		1024,
		array( 'desktop', 'mobile' ),
		true
	);

	$this->assertStringContainsString( '@media (max-width:782px)', $css );
	$this->assertStringContainsString( '.ad-placr__universal{display:none!important}', $css );
	$this->assertStringContainsString( '@media (min-width:783px) and (max-width:1024px)', $css );
	$this->assertStringContainsString( '#ad-placr-test{display:none!important}', $css );
}
```

Retain tests proving the wrapper ID/class are escaped and ad code remains raw by design.

- [ ] **Step 2: Run the renderer tests and observe the expected failure**

Run:

```powershell
vendor\bin\phpunit --filter RendererTest
```

Expected: FAIL because the wrapper still exposes `data-placement-id` and responsive device CSS does not exist.

- [ ] **Step 3: Replace Placement rendering with version rendering**

Delete `render_placement()`. Change `build_wrapper_html()` to append:

```php
$html .= ' data-ad-id="' . esc_attr( (string) $ad_id ) . '"';
$html .= ' data-version-id="' . esc_attr( $version_id ) . '"';
```

Implement `render_ad()` with one random roll and one selected version:

```php
public static function render_ad( int $ad_id, array $args ): string {
	if ( ! Ad_Placr_Ad::is_active( $ad_id ) ) {
		return '';
	}

	$versions = Ad_Placr_Ad::eligible_versions( Ad_Placr_Ad::get_versions( $ad_id ) );
	if ( empty( $versions ) ) {
		return '';
	}

	$total   = array_sum( array_column( $versions, 'weight' ) );
	$roll    = 1 === $total ? 0 : wp_rand( 0, $total - 1 );
	$version = Ad_Placr_Ad::choose_weighted_version( $versions, $roll );
	if ( null === $version ) {
		return '';
	}

	$dom_id     = isset( $args['dom_id'] ) ? sanitize_html_class( (string) $args['dom_id'] ) : 'ad-placr-' . $ad_id;
	$modifier   = isset( $args['modifier_class'] ) ? (string) $args['modifier_class'] : '';
	$targeting  = Ad_Placr_Ad::get_targeting( $ad_id );
	$devices    = self::normalize_devices( $targeting['devices'] ?? array() );
	$mobile_max = self::resolve_mobile_breakpoint();
	$tablet_max = self::resolve_tablet_breakpoint();
	$inner      = self::build_slots_inner_html( $version['code'], $version['mobile_code'] );
	$css        = self::build_responsive_css( '#' . $dom_id, $mobile_max, $tablet_max, $devices, '' !== trim( $version['mobile_code'] ) );
	$html       = '' !== $css ? '<style id="' . esc_attr( $dom_id . '-responsive' ) . '">' . $css . '</style>' : '';
	$html      .= self::build_wrapper_html( $dom_id, $modifier, $inner, $ad_id, $version['version_id'] );

	if ( ! empty( $args['echo'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network code; stored by privileged users.
		echo $html;
	}

	return $html;
}
```

`normalize_devices()` returns the intersection of the input with `array( 'desktop', 'tablet', 'mobile' )`, defaulting to all three when the result is empty.

- [ ] **Step 4: Add filterable mobile/tablet ranges and scoped visibility CSS**

`resolve_mobile_breakpoint()` uses the existing `ad_placr_mobile_breakpoint` filter and clamps to 320–1023. `resolve_tablet_breakpoint()` applies:

```php
/**
 * Filter the largest viewport width treated as Tablet.
 *
 * @since 2.7.0
 *
 * @param int $breakpoint Tablet maximum width in pixels.
 */
$breakpoint = (int) apply_filters( 'ad_placr_tablet_breakpoint', 1024 );

return max( self::resolve_mobile_breakpoint() + 1, min( 1600, $breakpoint ) );
```

`build_responsive_css()` must:

- swap `.ad-placr__universal` and `.ad-placr__mobile` at `max-width:$mobile_max` only when mobile code exists;
- hide the whole scoped wrapper in the Mobile range if `mobile` is not selected;
- hide it from `$mobile_max + 1` through `$tablet_max` if `tablet` is not selected;
- hide it from `$tablet_max + 1` upward if `desktop` is not selected;
- return only generated selectors and integer breakpoints.

- [ ] **Step 5: Run tests and static checks**

Run:

```powershell
vendor\bin\phpunit --filter "UnifiedAdTest|RendererTest"
composer exec phpcs -- --standard=WordPress includes/class-ad-placr-ad.php includes/class-ad-placr-renderer.php
```

Expected: tests PASS and PHPCS reports no errors.

- [ ] **Step 6: Commit the shared renderer**

```powershell
git add includes/class-ad-placr-renderer.php tests/unit/RendererTest.php
git commit -m "refactor: render weighted Ad versions directly"
```

---

### Task 3: Convert Targeting and Automatic Display Paths

**Files:**

- Modify: `includes/class-ad-placr-targeting.php`
- Modify: `includes/class-ad-placr-frontend.php`
- Modify: `includes/class-ad-placr-footer-sticky.php`
- Modify: `includes/class-ad-placr-in-content.php`
- Modify: `tests/unit/TargetingTest.php`
- Delete: `tests/unit/PlacementTargetingTest.php`
- Modify: `tests/unit/FrontendContextTest.php`
- Modify: `tests/unit/PositionsRegistryTest.php`

**Interfaces:**

- Consumes: unified Ad accessors from Task 1 and `Renderer::render_ad()` from Task 2.
- Produces:
  - `Ad_Placr_Targeting::should_display( int $ad_id, array $context ): bool`.
  - Filter `ad_placr_targeting_should_display( bool $allowed, int $ad_id, array $context, array $rules )`.
  - `Ad_Placr_Frontend::render_all_for_position( string $key ): string`.
  - Every automatic registered location renders all matching Ads in `menu_order ASC, ID ASC`.

- [ ] **Step 1: Add tests for Ad-based gating and deterministic multi-Ad output**

Move the useful active/targeting assertions from `PlacementTargetingTest.php` into `TargetingTest.php`, mocking `Ad_Placr_Ad::is_active()` and `get_targeting()` call sites through WordPress function stubs. Add a pure aggregation seam to `FrontendContextTest.php`:

```php
public function test_join_rendered_ads_keeps_query_order_and_drops_empty_output(): void {
	$this->assertSame(
		'<div>first</div><div>second</div>',
		Ad_Placr_Frontend::join_rendered_ads(
			array( 9, 3, 12 ),
			static function ( int $ad_id ): string {
				return array(
					9  => '<div>first</div>',
					3  => '',
					12 => '<div>second</div>',
				)[ $ad_id ];
			}
		)
	);
}
```

Add a registry assertion that every descriptor with `handler` equal to `frontend`, `footer_sticky`, or `in_content` has a concrete runtime owner.

- [ ] **Step 2: Run the focused tests**

Run:

```powershell
vendor\bin\phpunit --filter "TargetingTest|FrontendContextTest|PositionsRegistryTest"
```

Expected: FAIL because targeting and frontend still require Placement IDs and `join_rendered_ads()` does not exist.

- [ ] **Step 3: Convert the shared targeting gate**

Rename parameters and documentation from `$placement_id` to `$ad_id`. The implementation becomes:

```php
public static function should_display( int $ad_id, array $ctx ): bool {
	if ( ! Ad_Placr_Ad::is_active( $ad_id ) ) {
		return false;
	}

	$targeting = Ad_Placr_Ad::get_targeting( $ad_id );
	$allowed   = self::matches( $targeting, $ctx );

	/**
	 * Filter whether an Ad should display after its saved rules are checked.
	 *
	 * @since 2.4.0
	 *
	 * @param bool                 $allowed   Core evaluation result.
	 * @param int                  $ad_id     Unified Ad post ID.
	 * @param array<string, mixed> $ctx       Normalized request context.
	 * @param array<string, mixed> $targeting Saved display rules.
	 */
	return (bool) apply_filters( 'ad_placr_targeting_should_display', $allowed, $ad_id, self::normalize_context( $ctx ), $targeting );
}
```

Keep `matches()` behavior unchanged: empty rule families fail open, malformed data normalizes safely, and schedules/URLs/terms remain supported.

- [ ] **Step 4: Convert the registry-driven frontend and render every match**

Rename `get_displayable_placement_ids()` to `get_displayable_ad_ids()` and query `Ad_Placr_Ad::query_ids_for_position()`. Rename `render_first_for_position()` to `render_all_for_position()`. Add:

```php
public static function join_rendered_ads( array $ad_ids, callable $render ): string {
	$html = '';
	foreach ( $ad_ids as $ad_id ) {
		$html .= (string) $render( (int) $ad_id );
	}

	return $html;
}
```

Within `render_all_for_position()`, call it with a closure that gives each Ad a unique DOM ID:

```php
return self::join_rendered_ads(
	self::get_displayable_ad_ids( $key ),
	static function ( int $ad_id ) use ( $safe_key, $modifier_class ): string {
		return Ad_Placr_Renderer::render_ad(
			$ad_id,
			array(
				'dom_id'         => 'ad-placr-pos-' . $safe_key . '-' . $ad_id,
				'modifier_class' => $modifier_class,
				'echo'           => false,
			)
		);
	}
);
```

Update echo/content methods and rail asset checks to use the new plural behavior.

- [ ] **Step 5: Convert sticky-footer and paragraph insertion**

In both specialized classes:

- replace every `placement_id` local, array key, DocBlock, and filter context with `ad_id`;
- query `Ad_Placr_Ad::query_ids_for_position()`;
- gate with `Ad_Placr_Targeting::should_display( $ad_id, $ctx )`;
- read paragraph, slot ID, and devices from `Ad_Placr_Ad::get_targeting( $ad_id )`;
- call `Ad_Placr_Renderer::render_ad()`;
- use DOM IDs ending in the Ad ID;
- remove the old per-creative mobile-meta scans because Task 2 emits scoped responsive CSS;
- do not return after the first sticky Ad—append and echo all matches in deterministic order;
- preserve the in-content single-pass paragraph split so several Ads can target the same paragraph without corrupting markup.

Rename filter context keys from `placement_ids` to `ad_ids` while preserving existing filter names and `@since` tags.

- [ ] **Step 6: Run all automatic-path tests**

Run:

```powershell
vendor\bin\phpunit --filter "TargetingTest|FrontendContextTest|PositionsRegistryTest|RendererTest"
vendor\bin\phpunit
```

Expected: focused tests PASS. Full-suite failures, if any, are now limited to shortcode/widget, analytics, migration, and admin expectations handled in later tasks.

- [ ] **Step 7: Commit automatic rendering**

```powershell
git add includes/class-ad-placr-targeting.php includes/class-ad-placr-frontend.php includes/class-ad-placr-footer-sticky.php includes/class-ad-placr-in-content.php tests/unit/TargetingTest.php tests/unit/PlacementTargetingTest.php tests/unit/FrontendContextTest.php tests/unit/PositionsRegistryTest.php
git commit -m "refactor: display unified Ads at automatic locations"
```

---

### Task 4: Convert Shortcode and Widget Paths

**Files:**

- Modify: `includes/class-ad-placr-shortcode.php`
- Modify: `includes/class-ad-placr-widget.php`
- Modify: `tests/unit/ShortcodeTest.php`
- Modify: `tests/unit/ManualMetaKeysTest.php`

**Interfaces:**

- Consumes: unified targeting and renderer.
- Produces:
  - `[ad_placr ad="42"]` for Ads whose location is `manual_shortcode`.
  - A widget instance key `ad_id` for Ads whose location is `sidebar_widget`.
  - Missing, paused, trashed, mismatched-location, or non-targeted Ads return no output.

- [ ] **Step 1: Replace Placement shortcode tests with one-Ad behavior**

Use these cases in `tests/unit/ShortcodeTest.php`:

```php
public function test_resolve_ad_id_accepts_positive_ad_attribute(): void {
	$this->assertSame( 42, Ad_Placr_Shortcode::resolve_ad_id( array( 'ad' => '42' ) ) );
}

public function test_resolve_ad_id_rejects_missing_or_invalid_values(): void {
	$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array() ) );
	$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array( 'ad' => '-1' ) ) );
	$this->assertSame( 0, Ad_Placr_Shortcode::resolve_ad_id( array( 'placement' => '9' ) ) );
}

public function test_modifier_uses_plain_manual_location_language(): void {
	$this->assertSame( 'ad-placr--shortcode', Ad_Placr_Shortcode::modifier_class() );
}
```

Add a source assertion to `ManualMetaKeysTest.php` proving neither shortcode nor widget contains `META_` literals or `Ad_Placr_Placement`.

- [ ] **Step 2: Run the focused tests**

Run:

```powershell
vendor\bin\phpunit --filter "ShortcodeTest|ManualMetaKeysTest"
```

Expected: FAIL because the shortcode still resolves Placement requests and the widget stores `placement_id`.

- [ ] **Step 3: Simplify the shortcode to a single Ad ID**

Replace `resolve_request()` with:

```php
public static function resolve_ad_id( array $atts ): int {
	if ( ! isset( $atts['ad'] ) ) {
		return 0;
	}

	return max( 0, (int) $atts['ad'] );
}
```

`render()` must:

1. resolve the ID;
2. require `Ad_Placr_Ad::get_position( $ad_id ) === Ad_Placr_Positions::MANUAL_SHORTCODE`;
3. require `Ad_Placr_Targeting::should_display( $ad_id, build_request_context() )`;
4. return `Renderer::render_ad()` with DOM ID `ad-placr-shortcode-$ad_id`;
5. otherwise return `''`.

Keep the shortcode tag `ad_placr`; remove the `placement` attribute and all Placement branches.

- [ ] **Step 4: Convert the widget instance and picker**

Change saved instance key from `placement_id` to `ad_id`. Query only published Ads with `META_POSITION = sidebar_widget`, ordered by title. Render through the same targeting and renderer path with DOM ID `ad-placr-widget-{widget-number}-{ad-id}`.

The form copy is:

```php
__( 'Choose an Ad', 'ad-placr' )
__( 'Create an Ad with “Sidebar widget” as its display location, then select it here.', 'ad-placr' )
```

Do not read the unreleased `placement_id` widget key. The plugin has no production users, so the widget stores and reads only `ad_id`.

- [ ] **Step 5: Run tests and commit**

Run:

```powershell
vendor\bin\phpunit --filter "ShortcodeTest|ManualMetaKeysTest"
composer exec phpcs -- --standard=WordPress includes/class-ad-placr-shortcode.php includes/class-ad-placr-widget.php
```

Expected: tests PASS and PHPCS reports no errors.

```powershell
git add includes/class-ad-placr-shortcode.php includes/class-ad-placr-widget.php tests/unit/ShortcodeTest.php tests/unit/ManualMetaKeysTest.php
git commit -m "refactor: render manual Ads without Placements"
```

---

### Task 5: Track Ad and Version Statistics

**Files:**

- Modify: `includes/class-ad-placr-analytics.php`
- Modify: `includes/class-ad-placr-rest.php`
- Modify: `assets/js/tracking.js`
- Modify: `tests/unit/AnalyticsTest.php`

**Interfaces:**

- Consumes: wrapper attributes from Task 2.
- Produces:
  - Analytics schema version 2 with new `version_id varchar(64) NOT NULL DEFAULT ''`.
  - A clean event schema containing `event_type`, `ad_id`, `version_id`, and `created_at`; no Placement column.
  - `Ad_Placr_Analytics::normalize_version_id( string ): string`.
  - `Ad_Placr_Analytics::normalize_tracking_context( array $context ): array{event:string,ad_id:int,version_id:string}`.
  - `count_events( ?string $event_type = null, int $ad_id = 0, string $version_id = '' ): int`.
  - `track( string $event_type, int $ad_id, string $version_id = '' ): bool`.
  - REST payload `{event, ad_id, version_id}`.

- [ ] **Step 1: Add analytics normalization and payload tests**

Add to `tests/unit/AnalyticsTest.php`:

```php
public function test_version_id_normalization_is_bounded_and_safe(): void {
	$this->assertSame(
		'11111111-1111-4111-8111-111111111111',
		Ad_Placr_Analytics::normalize_version_id( '11111111-1111-4111-8111-111111111111***' )
	);
	$this->assertSame( '', Ad_Placr_Analytics::normalize_version_id( '***' ) );
}

public function test_tracking_context_uses_version_not_placement(): void {
	$context = Ad_Placr_Analytics::normalize_tracking_context(
		array(
			'event'      => 'click',
			'ad_id'      => 42,
			'version_id' => 'version-a',
		)
	);

	$this->assertSame( 'version-a', $context['version_id'] );
	$this->assertArrayNotHasKey( 'placement_id', $context );
}
```

- [ ] **Step 2: Run the analytics tests**

Run:

```powershell
vendor\bin\phpunit --filter AnalyticsTest
```

Expected: FAIL because stable version IDs are not part of the analytics contract.

- [ ] **Step 3: Upgrade storage and PHP APIs**

Bump the analytics schema version to `2`. When upgrading from schema version 1, drop the unreleased local events table before calling `dbDelta()`; the plugin has no production users and those test rows cannot identify stable Ad versions. The replacement SQL contains:

```sql
version_id varchar(64) NOT NULL DEFAULT '',
KEY version_event (version_id, event_type)
```

Implement:

```php
public static function normalize_version_id( string $version_id ): string {
	$version_id = (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', $version_id );

	return substr( $version_id, 0, 64 );
}
```

It does not contain `placement_id`. Change every event context, action DocBlock, SQL insert, and count filter from Placement ID to version ID. Keep retention and no-PII behavior unchanged. The external action context must contain `event`, `ad_id`, and `version_id`.

Add the pure normalization seam used by the test and external-action path:

```php
public static function normalize_tracking_context( array $context ): array {
	$event = self::normalize_event_type( (string) ( $context['event'] ?? '' ) );

	return array(
		'event'      => null === $event ? '' : $event,
		'ad_id'      => max( 0, (int) ( $context['ad_id'] ?? 0 ) ),
		'version_id' => self::normalize_version_id( (string) ( $context['version_id'] ?? '' ) ),
	);
}
```

- [ ] **Step 4: Update REST and browser tracking**

In `Ad_Placr_Rest::register_routes()`, replace the Placement argument with:

```php
'version_id' => array(
	'required'          => true,
	'type'              => 'string',
	'sanitize_callback' => array( Ad_Placr_Analytics::class, 'normalize_version_id' ),
),
```

`handle_track()` rejects an empty normalized version ID and calls `track( $event, $ad_id, $version_id )`.

In `assets/js/tracking.js`, read `data-version-id`, require it alongside `data-ad-id`, and send:

```js
var body = {
	event: event,
	ad_id: adId,
	version_id: versionId
};
```

Do not change the viewability threshold or error-swallowing behavior.

- [ ] **Step 5: Run tests and commit**

Run:

```powershell
vendor\bin\phpunit --filter "AnalyticsTest|RendererTest"
composer exec phpcs -- --standard=WordPress includes/class-ad-placr-analytics.php includes/class-ad-placr-rest.php
```

Expected: tests PASS and PHPCS reports no errors.

```powershell
git add includes/class-ad-placr-analytics.php includes/class-ad-placr-rest.php assets/js/tracking.js tests/unit/AnalyticsTest.php
git commit -m "refactor: track statistics by Ad version"
```

---

### Task 6: Build the One-Screen Ad Editor

**Files:**

- Modify: `includes/class-ad-placr-admin.php`
- Modify: `includes/class-ad-placr-settings-page.php`
- Create: `assets/js/admin.js`
- Create: `assets/css/admin.css`
- Create: `tests/unit/AdminTest.php`
- Modify: `tests/bootstrap.php`
- Delete: `admin/css/settings-slots.css`
- Delete: `admin/js/in-content-slots.js`

**Interfaces:**

- Consumes: Ad/position/analytics APIs from Tasks 1 and 5.
- Produces:
  - One Ad editor with sections: Name/status, display location, Ad code, display rules, statistics.
  - `Ad_Placr_Admin::normalize_version_rows( array $posted, bool $can_unfiltered_html, ?callable $id_generator = null ): array`.
  - `Ad_Placr_Admin::activation_errors( string $position, array $versions ): string[]`.
  - `Ad_Placr_Admin::save_errors( string $requested_status, string $position, array $versions ): string[]`.
  - `Ad_Placr_Admin::duplicate_ad( int $source_id ): int|WP_Error`.
  - `Ad_Placr_Settings_Page::merge_analytics_setting( array $current, bool $enabled ): array`.
  - List columns: Name, Display location, Status, Ad versions, Impressions, Clicks, CTR.

- [ ] **Step 1: Write pure admin validation tests**

Create `tests/unit/AdminTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase {

	public function test_activation_requires_location_and_eligible_code(): void {
		$this->assertSame(
			array(
				'Choose where this ad should appear before activating it.',
				'Add your ad code before activating this Ad.',
			),
			Ad_Placr_Admin::activation_errors( '', array() )
		);
	}

	public function test_paused_ad_may_keep_incomplete_values(): void {
		$this->assertSame( array(), Ad_Placr_Admin::save_errors( 'draft', '', array() ) );
	}

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

	public function test_statistics_setting_preserves_legacy_migration_source(): void {
		$current = array(
			'footer_sticky'     => array( 'enabled' => true, 'code' => '<ins>source</ins>' ),
			'in_content_slots'  => array( array( 'id' => 'source-slot' ) ),
			'analytics_enabled' => false,
		);

		$updated = Ad_Placr_Settings_Page::merge_analytics_setting( $current, true );

		$this->assertTrue( $updated['analytics_enabled'] );
		$this->assertSame( $current['footer_sticky'], $updated['footer_sticky'] );
		$this->assertSame( $current['in_content_slots'], $updated['in_content_slots'] );
	}
}
```

The third optional callable argument is the ID generator seam; runtime passes no argument and defaults to `wp_generate_uuid4()`. Add `includes/class-ad-placr-settings-page.php` to `tests/bootstrap.php` before `class-ad-placr-admin.php`.

- [ ] **Step 2: Run the admin tests**

Run:

```powershell
vendor\bin\phpunit --filter AdminTest
```

Expected: FAIL because the unified admin helpers do not exist.

- [ ] **Step 3: Replace separate Ad/Placement meta boxes with unified sections**

Keep `Ad_Placr_Admin` behind admin hooks and `manage_options`. Register only Ad post-type meta boxes:

1. `ad-placr-location` — heading “Where should this ad appear?”
2. `ad-placr-code` — heading “Ad code”
3. `ad-placr-rules` — heading “Where should this ad be shown?”
4. `ad-placr-stats` — heading “Statistics”

The location selector iterates `Ad_Placr_Positions::all()`, groups options by each descriptor’s `group` value, and uses `Ad_Placr_Positions::label()` for labels. It stores only the canonical key. Add `data-ad-placr-location-control` blocks for paragraph number, shortcode instructions, and widget instructions.

The status control uses values `publish` and `draft` with labels “Active” and “Paused.” It is the only custom status field; do not write `_ad_placr_status`.

- [ ] **Step 4: Reduce the legacy Settings screen to the statistics opt-in**

Keep `Ad_Placr_Settings_Page::OPTION_NAME`, `CAPABILITY`, Settings API nonce handling, and the `analytics_enabled` checkbox. Move its submenu under `edit.php?post_type=ad_placr_ad` with menu label “Settings.” Remove the Footer sticky and In-content fields, their repeater enqueue, and the two obsolete files `admin/css/settings-slots.css` and `admin/js/in-content-slots.js`.

Use the following pure merge helper so saving the checkbox does not destroy the v0.1.5 source retained for migration audit:

```php
public static function merge_analytics_setting( array $current, bool $enabled ): array {
	$current['analytics_enabled'] = $enabled;

	return $current;
}
```

The Settings API sanitizer reads the current option, normalizes it to an array, and returns `merge_analytics_setting( $current, ! empty( $value['analytics_enabled'] ) )`. The page text is:

```php
esc_html_e( 'Statistics storage', 'ad-placr' );
esc_html_e( 'Store impression and click totals for 90 days. No personal information is collected.', 'ad-placr' );
esc_html_e( 'Store statistics in this WordPress site', 'ad-placr' );
```

- [ ] **Step 5: Render and normalize version rows**

Each row posts:

```text
ad_placr_versions[index][version_id]
ad_placr_versions[index][name]
ad_placr_versions[index][code]
ad_placr_versions[index][mobile_code]
ad_placr_versions[index][weight]
ad_placr_versions[index][enabled]
```

Render one row for a new Ad with a generated ID and name “Version A.” Hide the multi-version controls until a second row exists or the user selects “Add another ad version.” Label the weight field “How often should this version appear?” and place a calculated percentage beside it.

`normalize_version_rows()` must preserve valid IDs, generate only missing IDs, default names by alphabetic sequence, clamp weights to at least 1, sanitize names, preserve privileged code, and use `wp_kses_post()` when `$can_unfiltered_html` is false.

- [ ] **Step 6: Save with nonce, capability, autosave, and activation validation**

Use one nonce `ad_placr_save_ad` and one save callback. Merge all targeting fields into `META_TARGETING`, including:

```php
array(
	'contexts'          => array(),
	'post_types'        => array(),
	'user'              => 'any',
	'url_contains'      => array(),
	'include_categories'=> array(),
	'include_tags'      => array(),
	'schedule'          => array( 'start' => '', 'end' => '' ),
	'devices'           => array( 'desktop', 'tablet', 'mobile' ),
	'paragraph'         => 1,
	'slot_id'           => '',
)
```

When requested status is `publish`, call `activation_errors()`. If errors exist, keep/set the post to `draft`, preserve the submitted fields, and append `ad_placr_notice=missing_location` or `missing_code` to the redirect. Use a guarded `wp_update_post()` call so the save hook cannot recurse. Paused Ads may save incomplete data.

Implement `save_errors()` as the single status-aware seam:

```php
public static function save_errors( string $requested_status, string $position, array $versions ): array {
	if ( 'publish' !== $requested_status ) {
		return array();
	}

	return self::activation_errors( $position, $versions );
}
```

Unknown position values store as an empty string and produce the location error on activation.

- [ ] **Step 7: Add duplicate-location warning and independent duplication**

When a published automatic Ad shares its location with another published Ad, show:

```php
__( 'Another Active Ad uses this display location. Both Ads may appear. To show only one result, put the code choices into one Ad as versions.', 'ad-placr' )
```

Add a nonce-protected “Duplicate” row action. `duplicate_ad()` inserts a draft title suffixed “— Copy,” copies position/rules/notes, and copies every version with a newly generated `version_id`; it never links the records.

Add nonce-protected “Activate” and “Pause” row actions. Activation uses the same validator and never bypasses it. Trash/Restore remain native WordPress actions.

- [ ] **Step 8: Replace list columns and statistics**

Return these columns in this order:

```php
array(
	'cb'          => $columns['cb'],
	'title'       => __( 'Name', 'ad-placr' ),
	'location'    => __( 'Display location', 'ad-placr' ),
	'status'      => __( 'Status', 'ad-placr' ),
	'versions'    => __( 'Ad versions', 'ad-placr' ),
	'impressions' => __( 'Impressions', 'ad-placr' ),
	'clicks'      => __( 'Clicks', 'ad-placr' ),
	'ctr'         => __( 'CTR', 'ad-placr' ),
	'date'        => $columns['date'],
);
```

Use position labels, `publish`/other status, eligible version count, and aggregate analytics counts. In the Statistics meta box, always show totals and show a per-version table only when more than one normalized version exists.

- [ ] **Step 9: Add zero-build editor behavior**

Enqueue `assets/js/admin.js` and `assets/css/admin.css` only on `post.php`, `post-new.php`, and the unified Ads list. The script must:

- clone a `<template>` row and replace `__INDEX__`;
- generate an ID with `crypto.randomUUID()` when available, with a timestamp/random fallback;
- remove rows while retaining at least one;
- update Version A/B/C names only when a name is empty;
- compute each enabled/nonempty row’s `weight / enabled_weight_total * 100`;
- reveal paragraph/manual instruction controls based on the selected display location;
- use `wp.a11y.speak()` after add/remove actions.

The CSS should use WordPress variables/colors, a simple two-column layout above 782px, one column below, visible focus outlines, and no decorative framework.

- [ ] **Step 10: Run admin tests and source-language audit**

Run:

```powershell
vendor\bin\phpunit --filter AdminTest
rg -n "Placement|Creative|Variant|Rotation|Targeting|taxonomy|predicate|CPT|meta" includes/class-ad-placr-admin.php assets/js/admin.js
composer exec phpcs -- --standard=WordPress includes/class-ad-placr-admin.php includes/class-ad-placr-settings-page.php
```

Expected: Admin tests PASS. Review every `rg` hit and confirm none is inside a translated/user-facing UI string; technical migration or code comments may use internal terms. PHPCS reports no errors.

- [ ] **Step 11: Commit the unified admin**

```powershell
git add includes/class-ad-placr-admin.php includes/class-ad-placr-settings-page.php assets/js/admin.js assets/css/admin.css admin/css/settings-slots.css admin/js/in-content-slots.js tests/bootstrap.php tests/unit/AdminTest.php
git commit -m "feat: add one-screen Ad management"
```

---

### Task 7: Migrate Public Settings and Local Two-Record Data

**Files:**

- Modify: `includes/class-ad-placr-migration.php`
- Modify: `tests/unit/MigrationBuilderTest.php`

**Interfaces:**

- Consumes: only legacy literal post type/meta constants declared inside `Ad_Placr_Migration`; it must not call `Ad_Placr_Placement`.
- Produces:
  - `DB_VERSION = 2`.
  - `OPTION_MIGRATION_MAP = 'ad_placr_unified_migration_map'`, autoload false.
  - `build_settings_ads( array $settings ): array`.
  - `build_legacy_placement_ad( array $placement, array $source_ads ): array`.
  - `source_version_id( string $source_key ): string`, deterministic UUID-shaped ID.
  - `source_ad_ids(): int[]`, normalized from the migration map for the admin exclusion.
  - Source map `{settings: array<string,int>, placements: array<string,int>, source_ad_ids: int[]}`.

- [ ] **Step 1: Replace migration builder tests with complete unified definitions**

Update `tests/unit/MigrationBuilderTest.php` to assert:

```php
public function test_footer_settings_become_one_complete_ad(): void {
	$ads = Ad_Placr_Migration::build_settings_ads(
		array(
			'footer_sticky' => array(
				'enabled'     => true,
				'code'        => '<ins>desktop</ins>',
				'mobile_code' => '<ins>mobile</ins>',
			),
		)
	);

	$this->assertCount( 1, $ads );
	$this->assertSame( 'sticky_footer', $ads[0]['position'] );
	$this->assertSame( '<ins>desktop</ins>', $ads[0]['versions'][0]['code'] );
	$this->assertSame( '<ins>mobile</ins>', $ads[0]['versions'][0]['mobile_code'] );
	$this->assertSame( array( 'desktop', 'tablet', 'mobile' ), $ads[0]['targeting']['devices'] );
}

public function test_in_content_settings_preserve_paragraph_and_post_types(): void {
	$ads = Ad_Placr_Migration::build_settings_ads(
		array(
			'in_content_slots' => array(
				array(
					'id'              => 'slot-a',
					'enabled'         => true,
					'title'           => 'Article Ad',
					'paragraph_index' => 4,
					'position'        => 'before',
					'post_types'      => array( 'post' ),
					'code'            => '<ins>a</ins>',
					'mobile_code'     => '',
				),
			),
		)
	);

	$this->assertSame( 'in_content_before_paragraph', $ads[0]['position'] );
	$this->assertSame( 4, $ads[0]['targeting']['paragraph'] );
	$this->assertSame( array( 'post' ), $ads[0]['targeting']['post_types'] );
}

public function test_two_record_data_becomes_one_ad_with_weighted_versions(): void {
	$definition = Ad_Placr_Migration::build_legacy_placement_ad(
		array(
			'id'          => 90,
			'title'       => 'Header choices',
			'post_status' => 'publish',
			'position'    => 'after_header',
			'status'      => 'active',
			'targeting'   => array( 'contexts' => array( 'all' ) ),
			'ads'         => array(
				array( 'ad_id' => 10, 'weight' => 3 ),
				array( 'ad_id' => 11, 'weight' => 1 ),
			),
		),
		array(
			10 => array( 'title' => 'Blue', 'code' => '<ins>blue</ins>', 'mobile_code' => '', 'status' => 'active' ),
			11 => array( 'title' => 'Red', 'code' => '<ins>red</ins>', 'mobile_code' => '<ins>red-mobile</ins>', 'status' => 'active' ),
		)
	);

	$this->assertSame( 'after_header', $definition['position'] );
	$this->assertSame( array( 3, 1 ), array_column( $definition['versions'], 'weight' ) );
	$this->assertSame( '<ins>red-mobile</ins>', $definition['versions'][1]['mobile_code'] );
	$this->assertSame(
		Ad_Placr_Migration::source_version_id( 'ad:10' ),
		$definition['versions'][0]['version_id']
	);
}
```

Also assert disabled/empty public settings are skipped, paused Placement state produces `draft`, inactive source Ads produce disabled versions, missing source Ads are ignored, and identical source keys always return the same version ID.

- [ ] **Step 2: Run migration tests**

Run:

```powershell
vendor\bin\phpunit --filter MigrationBuilderTest
```

Expected: FAIL because the current builder returns separate `ads` and `placements`.

- [ ] **Step 3: Isolate all legacy constants in Migration**

Declare private/public documented constants inside `Ad_Placr_Migration` for:

```php
private const LEGACY_PLACEMENT_POST_TYPE = 'ad_placr_placement';
private const LEGACY_META_POSITION       = '_ad_placr_position';
private const LEGACY_META_STATUS         = '_ad_placr_status';
private const LEGACY_META_TARGETING      = '_ad_placr_targeting';
private const LEGACY_META_ADS            = '_ad_placr_ads';
private const LEGACY_META_CODE           = '_ad_placr_code';
private const LEGACY_META_MOBILE_CODE    = '_ad_placr_mobile_code';
```

Do not reference `Ad_Placr_Placement` anywhere in this class.

- [ ] **Step 4: Implement the two pure builders and deterministic IDs**

`build_settings_ads()` returns only complete unified definitions with `source_key`, `title`, `post_status`, `position`, `targeting`, `versions`, and `notes`.

`build_legacy_placement_ad()`:

- copies title, registered position, targeting, and notes;
- sets `post_status` to `publish` only when the source Placement post is published, its legacy status is active, and at least one resulting version is eligible;
- processes linked Ads in saved order;
- preserves each positive weight;
- copies code/mobile code;
- sets version enabled only when the source Ad status is active and either code field is nonempty;
- derives `version_id` from `source_version_id( 'ad:' . $source_ad_id )`.

Implement deterministic UUID shape:

```php
public static function source_version_id( string $source_key ): string {
	$hex = md5( 'ad-placr-version:' . $source_key );

	return substr( $hex, 0, 8 ) . '-' .
		substr( $hex, 8, 4 ) . '-4' .
		substr( $hex, 13, 3 ) . '-8' .
		substr( $hex, 17, 3 ) . '-' .
		substr( $hex, 20, 12 );
}
```

- [ ] **Step 5: Implement idempotent runtime migration**

Set `DB_VERSION = 2`. `run()` performs:

1. load/normalize the non-autoloaded migration map;
2. query all legacy Placement posts, including draft/trash/private;
3. if any exist, convert each unmapped Placement to one new unified Ad and record the mapping immediately after each successful insert;
4. collect every linked old Ad ID in `source_ad_ids`;
5. if no legacy Placements exist, convert public settings definitions instead;
6. save each `settings[source_key]` or `placements[(string) source_id]` mapping immediately after successful insertion;
7. update the DB version only after all required definitions are mapped;
8. never delete or rewrite source records/settings.

Use a private `insert_unified_ad( array $definition )` that inserts the post and writes only `Ad_Placr_Ad` unified meta constants. If meta persistence fails, return `WP_Error`, leave DB version behind, and retry only the unmapped source later.

When the map option does not exist, create it with:

```php
add_option( self::OPTION_MIGRATION_MAP, $map, '', 'no' );
```

Subsequent writes use `update_option( ..., false )`.

- [ ] **Step 6: Run tests and inspect idempotency**

Run:

```powershell
vendor\bin\phpunit --filter MigrationBuilderTest
rg -n "Ad_Placr_Placement|META_CODE|META_MOBILE_CODE" includes/class-ad-placr-migration.php
```

Expected: tests PASS. `rg` finds no `Ad_Placr_Placement`; old code keys appear only as the migration class’s legacy constants and their read sites.

- [ ] **Step 7: Commit migration**

```powershell
git add includes/class-ad-placr-migration.php tests/unit/MigrationBuilderTest.php
git commit -m "feat: migrate legacy data into complete Ads"
```

---

### Task 8: Retire Placement Runtime and Hide Retained Sources

**Files:**

- Delete: `includes/class-ad-placr-placement.php`
- Delete: `tests/unit/PlacementTargetingTest.php`
- Modify: `ad-placr.php`
- Modify: `includes/class-ad-placr-plugin.php`
- Modify: `includes/class-ad-placr-admin.php`
- Modify: `tests/bootstrap.php`
- Modify: `tests/unit/SanityTest.php`

**Interfaces:**

- Consumes: Migration map from Task 7.
- Produces: no loaded/registered `ad_placr_placement`; retained source Ad IDs hidden from the normal unified Ads list; all public paths depend only on Ad IDs.

- [ ] **Step 1: Add source scans that fail while Placement remains**

Add to `tests/unit/SanityTest.php`:

```php
public function test_runtime_has_no_placement_dependency(): void {
	$root    = dirname( __DIR__, 2 );
	$runtime = array_merge(
		array( $root . '/ad-placr.php' ),
		glob( $root . '/includes/*.php' ) ?: array()
	);

	foreach ( $runtime as $file ) {
		if ( str_ends_with( $file, 'class-ad-placr-migration.php' ) ) {
			continue;
		}
		$source = (string) file_get_contents( $file );
		$this->assertStringNotContainsString( 'Ad_Placr_Placement', $source, $file );
		$this->assertStringNotContainsString( 'placement_id', $source, $file );
	}
}
```

- [ ] **Step 2: Run the scan**

Run:

```powershell
vendor\bin\phpunit --filter SanityTest
```

Expected: FAIL and name the remaining runtime Placement dependencies.

- [ ] **Step 3: Remove Placement from bootstrap and plugin boot**

Delete its `require_once` from `ad-placr.php`, its `register()` call from `Ad_Placr_Plugin::boot()`, its test-bootstrap require, its class file, and obsolete Placement test file. Confirm migration still uses literal legacy constants only.

In admin `pre_get_posts`, read Migration’s `source_ad_ids()` and exclude those IDs only on the main `edit.php?post_type=ad_placr_ad` query. Do not alter front-end queries, exports, or direct source URLs. This keeps retained source records auditable while preventing them from confusing normal users.

- [ ] **Step 4: Run the full suite and source scans**

Run:

```powershell
vendor\bin\phpunit
rg -n "Ad_Placr_Placement|ad_placr_placement|placement_id|data-placement-id" ad-placr.php includes assets tests
```

Expected: PHPUnit PASS. `rg` finds `ad_placr_placement` only inside Migration legacy constants/tests and historical comments explicitly describing migration; it finds no runtime class or `placement_id`.

- [ ] **Step 5: Commit runtime retirement**

```powershell
git add ad-placr.php includes/class-ad-placr-plugin.php includes/class-ad-placr-admin.php includes/class-ad-placr-placement.php tests/bootstrap.php tests/unit/PlacementTargetingTest.php tests/unit/SanityTest.php
git commit -m "refactor: retire the Placement runtime"
```

---

### Task 9: Release Copy, Cleanup, and Automated Verification

**Files:**

- Modify: `ad-placr.php`
- Modify: `readme.txt`
- Modify: `changelog.md`
- Modify: `development.md`
- Modify: `roadmap.md`
- Modify: `uninstall.php`

**Interfaces:**

- Consumes: completed unified runtime.
- Produces: synchronized 2.7.0 release metadata and user documentation that describes one-Ad management.

- [ ] **Step 1: Update release metadata and user-facing documentation**

Set all three version sources to `2.7.0`:

```text
ad-placr.php Version: 2.7.0
AD_PLACR_VERSION = '2.7.0'
readme.txt Stable tag: 2.7.0
```

Change the plugin description to:

```text
A complete WordPress ad manager with automatic display locations, display rules, Ad versions, and statistics.
```

Add a 2.7.0 changelog section covering:

- one Ads screen and no Placements workflow;
- location/rules/status/code versions on each Ad;
- weighted Ad versions and mobile code;
- aggregate/per-version statistics;
- public and local migration behavior;
- source retention during verification.

Update `development.md` and `roadmap.md` so current architecture always says one complete Ad record. Historical design/plan files stay marked superseded and are not rewritten.

- [ ] **Step 2: Update uninstall cleanup**

Retain the current settings/DB-version/analytics cleanup and additionally delete the migration map by its option name (uninstall runs without the normal class bootstrap):

```php
delete_option( 'ad_placr_unified_migration_map' );
```

Do not add implicit source-post deletion to uninstall in this release; source cleanup is explicitly deferred by the approved migration design.

- [ ] **Step 3: Run language and version consistency checks**

Run:

```powershell
rg -n "Version:|AD_PLACR_VERSION|Stable tag" ad-placr.php readme.txt
rg -n "Placements menu|create a Placement|Ad/Placement|Creative|Variant|Rotation" readme.txt development.md roadmap.md includes assets
```

Expected: all version values are 2.7.0. No current user-facing documentation instructs users to create or manage Placements; hits are limited to migration/history explanations.

- [ ] **Step 4: Run the mandatory automated verification**

Run:

```powershell
vendor\bin\phpunit
composer exec phpcs -- --standard=WordPress includes/ ad-placr.php
composer exec phpstan -- analyse
```

Expected:

- PHPUnit: all tests pass with no warnings/risky tests.
- PHPCS: zero errors; warnings individually reviewed.
- PHPStan: `[OK] No errors`.

- [ ] **Step 5: Commit release documentation**

```powershell
git add ad-placr.php readme.txt changelog.md development.md roadmap.md uninstall.php
git commit -m "docs: prepare unified Ad model release"
```

---

### Task 10: Real WordPress Round-Trip and Display Verification

**Files:**

- No planned source changes. If a defect is found, return to the owning task, add a failing regression test, fix it, rerun that task’s checks, and commit the fix separately.

**Interfaces:**

- Consumes: the complete 2.7.0 build.
- Produces: evidence that admin values, migration, automatic/manual rendering, responsive behavior, and analytics work in real WordPress requests.

- [ ] **Step 1: Verify migration on a recoverable local database copy**

Before exercising migration, use Local’s database backup/export facility. On the local Yenimi site:

1. record counts for `ad_placr_ad` and `ad_placr_placement`;
2. load one request so migration version 2 runs;
3. record the unified Ad count and migration-map option;
4. load a second request and confirm neither count nor map entries increase;
5. compare every migrated location, rule, main/mobile code, weight, and status with its source;
6. confirm old source posts still exist and the analytics table now uses only Ad/version identity.

Expected: one result per source Placement when local two-record data exists, otherwise one result per enabled v0.1.5 configuration; second run creates nothing.

- [ ] **Step 2: Verify every editor lifecycle action**

Using a `manage_options` account:

1. create a Paused incomplete Ad and reload it;
2. add a location and one code version, activate, and reload;
3. add/reorder/disable versions and verify IDs remain stable in saved meta;
4. duplicate and verify every duplicate version gets a different ID;
5. pause, activate, trash, restore, and permanently delete the duplicate;
6. attempt activation without code and without location and verify the exact correction notice;
7. activate two Ads at one automatic location and verify the warning.

Expected: values round-trip, post status alone controls Active/Paused, and no workflow mentions Placements.

- [ ] **Step 3: Verify automatic and manual output in real requests**

Create minimal test Ads for every automatic registry location and verify:

- each appears in the expected hook/request context;
- two Ads at one location both appear in `menu_order ASC, ID ASC`;
- before/after paragraph Ads preserve content markup;
- shortcode and widget Ads use only their matching manual display location;
- paused, trashed, unknown-position, empty-version, expired, and nonmatching Ads output nothing.

Inspect page source for:

```text
data-ad-id="<numeric ID>"
data-version-id="<stable ID>"
```

Expected: no `data-placement-id`, PHP notice, warning, or fatal error.

- [ ] **Step 4: Verify responsive code and device visibility**

At viewport widths below the Mobile maximum, within the Tablet range, and above the Tablet maximum:

- confirm main code serves Desktop/Tablet and Mobile fallback;
- confirm optional mobile code replaces main code on Mobile;
- confirm an unselected device range hides the whole Ad;
- change each breakpoint through its filter and confirm the generated scoped media queries follow it;
- confirm cached HTML contains CSS-based behavior and no user-agent-dependent branch.

- [ ] **Step 5: Verify aggregate and per-version analytics**

Trigger visible impressions and one click for each version. Confirm:

- tracking requests contain `event`, `ad_id`, and `version_id`;
- failed tracking requests never remove/break the Ad;
- the Ads list shows aggregate impressions, clicks, and CTR;
- a multi-version Ad shows per-version figures;
- retention still removes only expired rows;
- no PII field is introduced.

- [ ] **Step 6: Inspect the debug log and rerun final checks**

Inspect the site’s `WP_DEBUG_LOG` after all admin/front-end requests. Expected: no Ad Placr notice, warning, deprecation, or fatal.

Then run:

```powershell
vendor\bin\phpunit
composer exec phpcs -- --standard=WordPress includes/ ad-placr.php
composer exec phpstan -- analyse
git status --short
```

Expected: all checks pass and `git status --short` is clean.

---

## Self-Review

### 1. Specification Coverage

- One Ad CPT, no Placements menu, one-screen workflow: Tasks 1, 6, and 8.
- Post status as sole Active/Paused authority: Tasks 1 and 6.
- Unified location/rules/versions/notes meta: Task 1.
- Stable version IDs, normalization, disabled/empty exclusion, weighted selection: Tasks 1 and 6.
- Main/mobile code and responsive Desktop/Tablet/Mobile visibility: Task 2.
- Deterministic multi-Ad automatic rendering and shared targeting: Task 3.
- Shared shortcode/widget pipeline: Task 4.
- Clean Ad/version analytics with no unreleased Placement compatibility: Task 5.
- Validation, duplicate-location warning, independent duplication, list columns, statistics, plain language: Task 6.
- Lossless/idempotent public settings and local two-record migration with source map and no deletion: Task 7.
- Runtime Placement retirement while retained sources remain auditable: Task 8.
- 2.7.0 metadata, current documentation, uninstall option cleanup: Task 9.
- Required automated checks and every manual lifecycle/display/analytics verification: Tasks 9 and 10.
- Non-goals remain absent: no creative library, campaigns, advertiser accounts, visual builder, new locations, or new targeting families.

### 2. Placeholder Scan

The plan contains none of the prohibited placeholder markers, unspecified error-handling requests, or unnamed test requests. Every task names exact files, interfaces, focused tests, commands, expected outcomes, and commits. References to later tasks always point to explicitly numbered work.

### 3. Type and Name Consistency

- Every runtime entry point uses `int $ad_id`; `placement_id` remains only inside the one-time Ad-data migration reader and migration tests.
- `version_id` is a sanitized string up to 64 characters in Ad versions, wrappers, REST, JavaScript, analytics storage, and statistics queries.
- The only current meta constants are `META_POSITION`, `META_TARGETING`, `META_VERSIONS`, and `META_NOTES`.
- `render_ad()` is the single rendering entry point across automatic, sticky, paragraph, shortcode, and widget paths.
- `query_ids_for_position()` supplies the required `menu_order ASC, ID ASC` order, and every consumer preserves it.
- `publish`/`draft` are the only Active/Paused values; legacy `_ad_placr_status` is read only inside migration.
- Migration map keys and output shape are stable across Tasks 7 and 8.
