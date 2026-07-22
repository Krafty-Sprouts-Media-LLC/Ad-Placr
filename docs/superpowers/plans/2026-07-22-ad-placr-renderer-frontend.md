# Ad Placr Renderer & CPT Front-End Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship **2.1.0** — unified `Ad_Placr_Renderer` and refactor footer sticky + in-content so front-end output comes only from Ad/Placement CPTs (visual parity with today’s wrappers).

**Architecture:** Thin position handlers query active placements by canonical position key, apply v1 targeting (post types / singular / all), then call the renderer. The renderer owns weighted ad selection, wrapper HTML, mobile CSS strings, optional disclosure, and the single raw-echo site for ad network code.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, existing PHPUnit + Brain Monkey harness, WPCS. No new runtime Composer deps; no JS build.

## Global Constraints

- PHP floor **8.0**; WordPress floor **6.0**.
- Coding standards: WordPress PHP CS — tabs, `array()` long syntax, Yoda, spaced parentheses, one class per file.
- Filenames `class-ad-placr-*.php`; classes `Ad_Placr_*`; hooks/meta/options `ad_placr_`.
- Every function DocBlock: `@since`, `@param`, `@return`. New APIs `@since 2.1.0`. Never rewrite older `@since`.
- Meta keys: class constants only for read and write.
- Ad code raw echo only inside the renderer, with phpcs:ignore comment per AGENTS.md §1.4.
- Composer/`vendor` never required at runtime.
- Front end must **not** read `footer_sticky.code` / `in_content_slots[*].code` for output.
- Plugin Version / `AD_PLACR_VERSION` / readme Stable tag / changelog → **2.1.0**.
- Spec: `docs/superpowers/specs/2026-07-22-ad-placr-renderer-frontend-design.md`.

---

## File Structure

**Created:**
- `includes/class-ad-placr-renderer.php` — `Ad_Placr_Renderer`
- `tests/unit/RendererTest.php` — pure builder / disclosure / CSS tests
- `tests/unit/PlacementTargetingTest.php` — pure targeting match tests

**Modified:**
- `includes/class-ad-placr-ad.php` — `get_code()`, `get_mobile_code()`
- `includes/class-ad-placr-placement.php` — `is_active()`, `normalize_status()`, `query_ids_for_position()`, `get_position()`, `get_targeting()`, `targeting_matches_singular()`
- `includes/class-ad-placr-footer-sticky.php` — CPT-driven
- `includes/class-ad-placr-in-content.php` — CPT-driven
- `includes/class-ad-placr-settings-page.php` — disclosure + CPT notice
- `includes/class-ad-placr-plugin.php` — default `disclosure_text`; boot renderer if needed
- `ad-placr.php` — require renderer; version 2.1.0
- `tests/bootstrap.php` — require new class
- `readme.txt`, `changelog.md` — 2.1.0

---

### Task 1: Ad accessors + Placement status / targeting pure helpers

**Files:**
- Modify: `includes/class-ad-placr-ad.php`
- Modify: `includes/class-ad-placr-placement.php`
- Create: `tests/unit/PlacementTargetingTest.php`
- Modify: `tests/bootstrap.php` (if not already requiring placement/ad)

**Interfaces:**
- Consumes: existing meta constants on Ad / Placement.
- Produces:
  - `Ad_Placr_Ad::get_code( int $ad_id ): string`
  - `Ad_Placr_Ad::get_mobile_code( int $ad_id ): string`
  - `Ad_Placr_Placement::normalize_status( mixed $raw ): string` — `'active'|'inactive'` (same rules as Ad)
  - `Ad_Placr_Placement::targeting_matches_singular( array $targeting, string $post_type ): bool` — pure
  - DocBlocks `@since 2.1.0` on new methods only

- [ ] **Step 1: Write failing tests**

Create `tests/unit/PlacementTargetingTest.php`:

```php
<?php
/**
 * Pure targeting helpers for placements.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class PlacementTargetingTest extends TestCase {

	public function test_contexts_all_matches_any_post_type(): void {
		$t = array(
			'post_types' => array(),
			'contexts'   => array( 'all' ),
		);
		$this->assertTrue( Ad_Placr_Placement::targeting_matches_singular( $t, 'post' ) );
		$this->assertTrue( Ad_Placr_Placement::targeting_matches_singular( $t, 'page' ) );
	}

	public function test_singular_requires_post_type_allow_list(): void {
		$t = array(
			'post_types' => array( 'post' ),
			'contexts'   => array( 'singular' ),
		);
		$this->assertTrue( Ad_Placr_Placement::targeting_matches_singular( $t, 'post' ) );
		$this->assertFalse( Ad_Placr_Placement::targeting_matches_singular( $t, 'page' ) );
	}

	public function test_singular_empty_post_types_matches_nothing(): void {
		$t = array(
			'post_types' => array(),
			'contexts'   => array( 'singular' ),
		);
		$this->assertFalse( Ad_Placr_Placement::targeting_matches_singular( $t, 'post' ) );
	}

	public function test_placement_normalize_status(): void {
		$this->assertSame( 'inactive', Ad_Placr_Placement::normalize_status( '' ) );
		$this->assertSame( 'active', Ad_Placr_Placement::normalize_status( 'ACTIVE' ) );
	}
}
```

- [ ] **Step 2: Run tests — expect FAIL** (methods missing)

Run: `composer test -- --filter PlacementTargetingTest`  
Expected: FAIL — method does not exist / class error.

- [ ] **Step 3: Implement**

Add to `Ad_Placr_Ad` (after `is_active`):

```php
	/**
	 * Universal ad code for an ad.
	 *
	 * @since 2.1.0
	 *
	 * @param int $ad_id Ad post ID.
	 * @return string
	 */
	public static function get_code( int $ad_id ): string {
		return (string) get_post_meta( $ad_id, self::META_CODE, true );
	}

	/**
	 * Mobile override code for an ad.
	 *
	 * @since 2.1.0
	 *
	 * @param int $ad_id Ad post ID.
	 * @return string
	 */
	public static function get_mobile_code( int $ad_id ): string {
		return (string) get_post_meta( $ad_id, self::META_MOBILE_CODE, true );
	}
```

Add to `Ad_Placr_Placement`:

```php
	/**
	 * Normalize placement status. Pure.
	 *
	 * @since 2.1.0
	 *
	 * @param mixed $raw Raw status.
	 * @return string
	 */
	public static function normalize_status( $raw ): string {
		return 'active' === strtolower( (string) $raw ) ? 'active' : 'inactive';
	}

	/**
	 * Whether targeting allows this singular post type. Pure.
	 *
	 * `contexts` containing `all` always matches. `singular` requires `$post_type`
	 * to be listed in `post_types` (empty allow-list → no match).
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, mixed> $targeting Targeting blob.
	 * @param string               $post_type Current post type.
	 * @return bool
	 */
	public static function targeting_matches_singular( array $targeting, string $post_type ): bool {
		$contexts = isset( $targeting['contexts'] ) && is_array( $targeting['contexts'] )
			? $targeting['contexts']
			: array();

		if ( in_array( 'all', $contexts, true ) ) {
			return true;
		}

		if ( ! in_array( 'singular', $contexts, true ) ) {
			return false;
		}

		$types = isset( $targeting['post_types'] ) && is_array( $targeting['post_types'] )
			? $targeting['post_types']
			: array();

		return in_array( $post_type, $types, true );
	}
```

- [ ] **Step 4: Run tests — expect PASS**

Run: `composer test -- --filter PlacementTargetingTest`  
Expected: OK.

- [ ] **Step 5: Commit**

```bash
git add includes/class-ad-placr-ad.php includes/class-ad-placr-placement.php tests/unit/PlacementTargetingTest.php
git commit -m "feat: add ad code accessors and placement targeting helpers"
```

---

### Task 2: `Ad_Placr_Renderer` pure builders (TDD)

**Files:**
- Create: `includes/class-ad-placr-renderer.php`
- Create: `tests/unit/RendererTest.php`
- Modify: `tests/bootstrap.php` — `require` renderer
- Modify: `ad-placr.php` — `require_once` renderer (load only; register not required)

**Interfaces:**
- Consumes: nothing WP-specific in pure methods.
- Produces:
  - `Ad_Placr_Renderer::build_slots_inner_html( string $code, string $mobile_code ): string` — inner slot divs only (escaped structure; **code placeholders not inserted in pure test** — better: build with provided already-trusted strings and document that callers pass raw ad code)
  - Actually: pure method returns structure with `%1$s` / `%2$s` OR accepts codes and concatenates without escaping (ad exception). Unit tests assert class names and dual vs single branch.
  - `Ad_Placr_Renderer::build_wrapper_html( string $dom_id, string $modifier_class, int $breakpoint, string $code, string $mobile_code, string $disclosure ): string`
  - `Ad_Placr_Renderer::build_mobile_pair_css( string $dom_id_selector, int $breakpoint ): string` — scoped media queries; selector like `#ad-placr-footer-sticky` or `#ad-placr-ic-x`
  - `Ad_Placr_Renderer::resolve_breakpoint( int $default = 782 ): int` — apply filter `ad_placr_mobile_breakpoint`, clamp 320–1200
  - Default filter also keep legacy names as aliases? Spec says filter-overridable 782px. Use `ad_placr_mobile_breakpoint` as unified filter; handlers may also apply old filters for BC:
    - Footer still fires `ad_placr_footer_sticky_mobile_breakpoint` if present — implement resolve as: `$bp = apply_filters( 'ad_placr_mobile_breakpoint', 782 );` and document old filters call the new one OR keep calling old filter names from handlers when building. **Decision for implementer:** handlers pass breakpoint into renderer; handlers call existing `ad_placr_footer_sticky_mobile_breakpoint` / `ad_placr_in_content_mobile_breakpoint` for BC.

- [ ] **Step 1: Write failing `RendererTest`**

```php
<?php
/**
 * Renderer pure HTML/CSS builders.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase {

	public function test_wrapper_single_slot_when_no_mobile(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'ad-placr-footer-sticky',
			'ad-placr--footer-sticky',
			782,
			'<ins>desk</ins>',
			'',
			''
		);
		$this->assertStringContainsString( 'id="ad-placr-footer-sticky"', $html );
		$this->assertStringContainsString( 'ad-placr--footer-sticky', $html );
		$this->assertStringContainsString( 'ad-placr__slot--all', $html );
		$this->assertStringNotContainsString( 'ad-placr__slot--mobile', $html );
		$this->assertStringContainsString( '<ins>desk</ins>', $html );
	}

	public function test_wrapper_dual_slots_when_mobile_present(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'ad-placr-ic-1',
			'ad-placr--in-content',
			782,
			'U',
			'M',
			''
		);
		$this->assertStringContainsString( 'ad-placr__slot--universal', $html );
		$this->assertStringContainsString( 'ad-placr__slot--mobile', $html );
		$this->assertStringContainsString( '>U</div>', $html );
		$this->assertStringContainsString( '>M</div>', $html );
	}

	public function test_disclosure_rendered_when_non_empty(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'x',
			'ad-placr--footer-sticky',
			782,
			'A',
			'',
			'Advertisement'
		);
		$this->assertStringContainsString( 'ad-placr__disclosure', $html );
		$this->assertStringContainsString( 'Advertisement', $html );
	}

	public function test_mobile_css_contains_breakpoint_and_selector(): void {
		$css = Ad_Placr_Renderer::build_mobile_pair_css( '#ad-placr-footer-sticky', 782 );
		$this->assertStringContainsString( 'max-width: 782px', $css );
		$this->assertStringContainsString( 'min-width: 783px', $css );
		$this->assertStringContainsString( '#ad-placr-footer-sticky', $css );
		$this->assertStringContainsString( 'ad-placr__slot--universal', $css );
	}
}
```

- [ ] **Step 2: Run — expect FAIL**

Run: `composer test -- --filter RendererTest`

- [ ] **Step 3: Implement `Ad_Placr_Renderer`**

File header `@package AdPlacr`, `@since 2.1.0`. Class with:

- `SLOT_VISIBLE_INLINE` constant (same flex string as current in-content/footer).
- `build_wrapper_html` — escape `dom_id`, `modifier_class`, breakpoint attr with `esc_attr`; escape disclosure with `esc_html`; **do not** escape `$code` / `$mobile_code` when concatenating (document as privileged ad code). Prefer building via string concat in pure method (no `echo`) so unit tests work without WP — use a private note that WordPress escape helpers must exist in bootstrap OR implement minimal stubs.

**Brain Monkey / stubs:** `tests/bootstrap.php` already may not define `esc_attr` / `esc_html`. Add thin stubs if missing:

```php
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
```

Implement `build_mobile_pair_css` mirroring existing footer sprintf media queries, scoped to `$selector` (e.g. `#id`).

- [ ] **Step 4: Run — expect PASS** + full `composer test`

- [ ] **Step 5: Commit**

```bash
git add includes/class-ad-placr-renderer.php tests/unit/RendererTest.php tests/bootstrap.php ad-placr.php
git commit -m "feat: add Ad_Placr_Renderer wrapper and mobile CSS builders"
```

---

### Task 3: Placement runtime query + `is_active` + renderer `render_placement`

**Files:**
- Modify: `includes/class-ad-placr-placement.php`
- Modify: `includes/class-ad-placr-renderer.php`
- Modify: `includes/class-ad-placr-plugin.php` — `default_settings()` add `'disclosure_text' => ''`

**Interfaces:**
- Produces:
  - `Ad_Placr_Placement::is_active( int $placement_id ): bool` — publish + status active
  - `Ad_Placr_Placement::get_position( int $id ): string`
  - `Ad_Placr_Placement::get_targeting( int $id ): array`
  - `Ad_Placr_Placement::query_ids_for_position( string $position_key ): int[]` — `WP_Query` / `get_posts` meta_query on `META_POSITION`, `post_status=publish`, `posts_per_page=-1`, fields ids
  - `Ad_Placr_Renderer::render_placement( int $placement_id, array $args ): string` where `$args` includes `dom_id`, `modifier_class`, `breakpoint` (int), optional `echo` bool default false for testability — returns HTML string; when codes empty or ad inactive returns `''`
  - Weighted pick: `wp_rand( 0, PHP_INT_MAX )` or `random_int` passed to `choose_weighted`
  - Disclosure: `Ad_Placr_Plugin::get_settings()['disclosure_text']` trimmed

- [ ] **Step 1: Implement query + is_active + get_* ** (no unit test for WP_Query; keep methods thin)

- [ ] **Step 2: Implement `render_placement`** using `build_wrapper_html`

Logic:

```
if ( ! Placement::is_active ) return '';
$ads = Placement::get_ads; if empty return '';
$ad_id = choose_weighted( $ads, random );
if ( null === $ad_id || ! Ad::is_active( $ad_id ) ) return '';
$code = Ad::get_code; $mobile = Ad::get_mobile_code;
if both trim empty return '';
disclosure from settings;
return build_wrapper_html( ... );
```

- [ ] **Step 3: `composer lint` on touched files; `composer test`**

- [ ] **Step 4: Commit**

```bash
git add includes/class-ad-placr-placement.php includes/class-ad-placr-renderer.php includes/class-ad-placr-plugin.php
git commit -m "feat: render placements from CPT weighted ads"
```

---

### Task 4: Refactor footer sticky to CPT handler

**Files:**
- Modify: `includes/class-ad-placr-footer-sticky.php`

**Interfaces:**
- Consumes: `query_ids_for_position( STICKY_FOOTER )`, `get_targeting`, `targeting_matches_singular` (for `all` contexts footer still shows on all front requests — `contexts:all` matches any post type; on non-singular use: if `all` in contexts, allow; else skip). **Footer rule:** output when any active sticky_footer placement has `contexts` containing `all` OR (singular + targeting match). Helper:

```php
private static function placement_should_display( int $placement_id ): bool {
	if ( ! Ad_Placr_Placement::is_active( $placement_id ) ) {
		return false;
	}
	$t = Ad_Placr_Placement::get_targeting( $placement_id );
	$contexts = ...;
	if ( in_array( 'all', $contexts, true ) ) {
		return true;
	}
	if ( ! is_singular() ) {
		return false;
	}
	$pt = get_post_type();
	return is_string( $pt ) && Ad_Placr_Placement::targeting_matches_singular( $t, $pt );
}
```

- Enqueue: if at least one placement would display and render_placement would be non-empty — practical approach: enqueue when `query_ids_for_position` returns non-empty active candidates; build inline CSS if any selected ad has mobile override (may enqueue CSS for all candidates with mobile code).
- `render()`: foreach placement ids, if should_display, echo `render_placement` with `dom_id` = `ad-placr-footer-sticky` — **only first matching placement** to preserve single footer region (spec: one sticky footer band). If multiple CPT placements exist, render the first that returns non-empty HTML.
- Breakpoint: existing filter `ad_placr_footer_sticky_mobile_breakpoint`.
- Keep filter `ad_placr_footer_sticky_should_display` wrapping the whole render.
- **Remove** `get_config()` option reads for code.

- [ ] **Step 1: Refactor class** (preserve `@since 0.1.0` on class; new methods `@since 2.1.0`)

- [ ] **Step 2: Lint file; full `composer test`**

- [ ] **Step 3: Commit**

```bash
git add includes/class-ad-placr-footer-sticky.php
git commit -m "feat: drive footer sticky output from placement CPTs"
```

---

### Task 5: Refactor in-content to CPT handler

**Files:**
- Modify: `includes/class-ad-placr-in-content.php`

**Interfaces:**
- Query both `IN_CONTENT_BEFORE_PARAGRAPH` and `IN_CONTENT_AFTER_PARAGRAPH`.
- For each active placement matching singular targeting, read `targeting['paragraph']` (int, clamp 1–100), build HTML via renderer with `dom_id` = `ad-placr-ic-{slug}` where slug from targeting `slot_id` if set, else `(string) $placement_id`.
- Bucket before/after maps; reuse existing `inject_by_paragraph_blocks`.
- Keep filters `ad_placr_in_content_should_inject` and `ad_placr_in_content_slot_should_display` (pass targeting array or placement id — **keep signature** `$show, $slot, $post_id` by building a `$slot`-shaped array for BC:

```php
$slot = array(
	'id' => ...,
	'paragraph_index' => ...,
	'position' => 'before'|'after',
	'post_types' => ...,
);
```

- Enqueue `in-content.css` + per-id mobile CSS via `Renderer::build_mobile_pair_css`.
- **Remove** option slot reads.

- [ ] **Step 1: Refactor**

- [ ] **Step 2: Lint + `composer test`**

- [ ] **Step 3: Commit**

```bash
git add includes/class-ad-placr-in-content.php
git commit -m "feat: drive in-content output from placement CPTs"
```

---

### Task 6: Settings notice + disclosure field + version 2.1.0

**Files:**
- Modify: `includes/class-ad-placr-settings-page.php` — sanitize `disclosure_text` as `sanitize_text_field`; admin notice/blurb that front-end ads come from **Ads** and **Placements** after migration; textarea for disclosure
- Modify: `includes/class-ad-placr-plugin.php` — ensure default key exists in `get_settings` merge
- Modify: `ad-placr.php` — Version **2.1.0**, `AD_PLACR_VERSION`
- Modify: `readme.txt` — Stable tag 2.1.0 + changelog bullet
- Modify: `changelog.md` — new `[2.1.0] - 22/07/2026` section (use today’s date if different: run `Get-Date -Format 'dd/MM/yyyy'`)

- [ ] **Step 1: Implement settings + version bumps**

- [ ] **Step 2: Full `composer test`; `composer lint` on `includes/` `ad-placr.php`**

- [ ] **Step 3: Commit**

```bash
git add includes/class-ad-placr-settings-page.php includes/class-ad-placr-plugin.php ad-placr.php readme.txt changelog.md
git commit -m "feat: 2.1.0 disclosure setting and CPT front-end cutover notes"
```

---

## Manual verification (after all tasks)

1. On a site with migrated footer + in-content placements: view a singular post — sticky footer and paragraph ads appear with same wrappers.
2. Trash/inactivate Placement → ads disappear.
3. Fresh options with `db_version` unset: load admin once (migration), then front end shows CPT ads.
4. Empty disclosure → no disclosure node; set “Advertisement” → node appears.

---

## Spec coverage checklist

| Spec item | Task |
|---|---|
| Renderer wrapper + mobile CSS + disclosure | 2, 3 |
| CPT-only front end | 4, 5 |
| Thin footer / in-content handlers | 4, 5 |
| Ad accessors / placement targeting | 1, 3 |
| Settings notice + disclosure | 6 |
| Version 2.1.0 | 6 |
| Single raw-echo site | 2–3 (`build_wrapper_html` / render) |
| Markup parity | 2, 4, 5 |
| Out of scope Phase 3+ | — not tasked |
