# Ad Placr Phase 3 Positions & Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship **2.2.0** — extend the position registry with hook metadata and add `Ad_Placr_Frontend` so every automatic position renders (no orphans). Sticky footer + in-content stay specialized.

**Architecture:** Registry descriptors gain `hook`, `priority`, `render_mode`, `handler`. Frontend registers one callback per `handler=frontend` key, applies context predicates, queries placements, calls `Ad_Placr_Renderer::render_placement`.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, PHPUnit + Brain Monkey, WPCS. No new runtime deps.

## Global Constraints

- PHP **8.0+**, WP **6.0+**; WPCS tabs / `array()` / Yoda / `Ad_Placr_*` / `ad_placr_`.
- New APIs `@since 2.2.0`; never rewrite older `@since`.
- Meta keys via class constants only; ad code raw-echo only in Renderer.
- Composer never required at runtime.
- Version / Stable tag / changelog → **2.2.0**.
- Spec: `docs/superpowers/specs/2026-07-22-ad-placr-phase3-positions-design.md`.
- Manual positions (`sidebar_widget`, `manual_shortcode`, `manual_block`) must keep `handler=manual` and must **not** be registered by Frontend.

---

## File Structure

**Created:**
- `includes/class-ad-placr-frontend.php`
- `assets/css/rails.css`
- `tests/unit/PositionsRegistryTest.php` (extend or new — orphan invariant)
- `tests/unit/FrontendContextTest.php` — pure context predicate helpers

**Modified:**
- `includes/class-ad-placr-positions.php` — descriptor fields + helpers `automatic_keys()`, `frontend_keys()`, `special_keys()`
- `includes/class-ad-placr-plugin.php` — `Ad_Placr_Frontend::register()`
- `ad-placr.php` — require frontend; version 2.2.0
- `tests/bootstrap.php` — require frontend
- `tests/unit/PositionsTest.php` — update descriptor assertions for new required fields
- `readme.txt`, `changelog.md`

---

### Task 1: Extend position registry metadata (TDD)

**Files:**
- Modify: `includes/class-ad-placr-positions.php`
- Modify: `tests/unit/PositionsTest.php`
- Create: `tests/unit/PositionsRegistryTest.php` (orphan invariant helpers)

**Interfaces:**
- Each defaults() entry includes: `label`, `group`, `context`, `hook` (string|null), `priority` (int), `render_mode` (`echo`|`content`|`none`), `handler` (`frontend`|`special`|`manual`).
- `Ad_Placr_Positions::frontend_keys(): string[]` — keys where handler===frontend
- `Ad_Placr_Positions::special_keys(): string[]` — sticky_footer + in_content_*
- `Ad_Placr_Positions::manual_keys(): string[]`
- `Ad_Placr_Positions::renderable_keys(): string[]` — frontend ∪ special

Hook map per spec (exact):

| key | hook | priority | render_mode | handler |
|---|---|---|---|---|
| in_content_* | null | 0 | none | special |
| sticky_footer | wp_footer | 100 | echo | special |
| before_post_content | the_content | 11 | content | frontend |
| after_post_content | the_content | 13 | content | frontend |
| before_header | wp_body_open | 5 | echo | frontend |
| after_header | wp_body_open | 20 | echo | frontend |
| before_footer | get_footer | 5 | echo | frontend |
| after_footer | wp_footer | 20 | echo | frontend |
| sticky_left_rail | wp_footer | 99 | echo | frontend |
| sticky_right_rail | wp_footer | 99 | echo | frontend |
| front_page_top | loop_start | 5 | echo | frontend |
| front_page_bottom | wp_footer | 15 | echo | frontend |
| blog_index_top | loop_start | 5 | echo | frontend |
| blog_index_bottom | wp_footer | 15 | echo | frontend |
| archive_top | loop_start | 5 | echo | frontend |
| archive_bottom | wp_footer | 15 | echo | frontend |
| sidebar_widget / manual_* | null | 0 | none | manual |

- [ ] **Step 1: Update PositionsTest** — every defaults() entry has the new keys; special/manual/frontend partitions cover all keys with no overlap.

```php
public function test_handlers_partition_all_keys(): void {
	$all = array_keys( Ad_Placr_Positions::defaults() );
	$parts = array_merge(
		Ad_Placr_Positions::frontend_keys(),
		Ad_Placr_Positions::special_keys(),
		Ad_Placr_Positions::manual_keys()
	);
	sort( $all );
	sort( $parts );
	$this->assertSame( $all, $parts );
}

public function test_frontend_keys_have_hooks(): void {
	foreach ( Ad_Placr_Positions::frontend_keys() as $key ) {
		$d = Ad_Placr_Positions::defaults()[ $key ];
		$this->assertNotSame( '', (string) $d['hook'] );
		$this->assertContains( $d['render_mode'], array( 'echo', 'content' ) );
	}
}
```

Note: `frontend_keys()` etc. should read **defaults()** for unit purity OR `all()` — prefer methods that accept optional registry array, or compute from `defaults()` for tests and from `all()` at runtime. **Decision:** implement:

```php
public static function keys_by_handler( string $handler, ?array $registry = null ): array
```

and wrappers that call `defaults()` in tests via `keys_by_handler( 'frontend', self::defaults() )` while runtime Frontend uses `keys_by_handler( 'frontend', self::all() )`.

Simpler: `frontend_keys()` uses `all()` but unit tests call against defaults by not filtering. For pure tests without apply_filters, Brain Monkey stubs `apply_filters` to return first arg — already may exist. Prefer computing from `defaults()` in the helper used by the orphan test:

```php
public static function partition_from( array $registry ): array {
  // returns [ 'frontend' => keys, 'special' => ..., 'manual' => ... ]
}
```

- [ ] **Step 2: Run — FAIL** then implement registry fields + helpers — PASS

- [ ] **Step 3: Commit**

```bash
git add includes/class-ad-placr-positions.php tests/unit/PositionsTest.php tests/unit/PositionsRegistryTest.php
git commit -m "feat: add hook metadata and handler partitions to positions registry"
```

---

### Task 2: Context predicate helpers (TDD)

**Files:**
- Create: `includes/class-ad-placr-frontend.php` (stubs + pure `context_matches`)
- Create: `tests/unit/FrontendContextTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- `Ad_Placr_Frontend::context_matches( string $context ): bool` — pure if we inject booleans; better:

```php
/**
 * @param string               $context Registry context key.
 * @param array<string,bool>   $flags   Keys: singular, front_page, blog_index, archive, main_query.
 */
public static function context_matches( string $context, array $flags ): bool
```

Rules:
- `global` → true
- `singular` → flags['singular'] && flags['main_query'] (for content filters; Frontend may pass main_query true when in_the_loop)
- `front_page` → front_page
- `blog_index` → blog_index
- `archive` → archive
- `widget` / `manual` → false (Frontend never called)
- unknown → false

Runtime wrapper `current_request_flags(): array` uses `is_singular()`, `is_front_page()`, `is_home()`, `is_archive()`, `is_main_query()` — not unit-tested.

- [ ] **Step 1: Failing tests for context_matches matrix**

- [ ] **Step 2: Implement pure method — PASS**

- [ ] **Step 3: Commit**

```bash
git add includes/class-ad-placr-frontend.php tests/unit/FrontendContextTest.php tests/bootstrap.php
git commit -m "feat: add Frontend context predicate helpers"
```

---

### Task 3: Frontend register + render callbacks

**Files:**
- Modify: `includes/class-ad-placr-frontend.php`
- Modify: `includes/class-ad-placr-plugin.php`
- Modify: `ad-placr.php` — require_once frontend

**Interfaces:**
- `Ad_Placr_Frontend::register(): void` — loops `keys_by_handler('frontend', all())`, registers hook
- For `render_mode=echo`: `add_action( $hook, function() use ($key) { self::echo_position( $key ); }, $priority )`
- For `render_mode=content`: `add_filter( 'the_content', function( $content ) use ($key) { return self::filter_content_position( $key, $content ); }, $priority )`
- Deduplicate: multiple keys share `the_content` / `wp_footer` / `loop_start` — **register one callback per (hook,priority) group OR one per key** (one per key is simpler and fine).
- `echo_position( $key )`: flags = current_request_flags(); if !context_matches(descriptor.context, flags) return; foreach query_ids_for_position; if !is_active continue; targeting: if singular context use targeting_matches_singular when is_singular; if listing and placement contexts only singular skip; render first non-empty HTML with args:
  - `dom_id` => `ad-placr-pos-` . sanitize_key($key)
  - `modifier_class` => `ad-placr--pos-` . sanitize_key($key) (rails add `ad-placr--rail-left` / `ad-placr--rail-right`)
  - `breakpoint` => Renderer::resolve_breakpoint() or 782 with existing filters
- `filter_content_position`: same guards as in-content (`in_the_loop`, `is_main_query`, not admin/feed); prepend or append HTML.

Enqueue rails.css when left/right rail positions have candidates.

- [ ] **Step 1: Implement register + echo/filter**

- [ ] **Step 2: `composer lint` on new/changed PHP; `composer test`**

- [ ] **Step 3: Commit**

```bash
git add includes/class-ad-placr-frontend.php includes/class-ad-placr-plugin.php ad-placr.php assets/css/rails.css
git commit -m "feat: register Frontend dispatcher for automatic positions"
```

Create minimal `assets/css/rails.css`:

```css
.ad-placr--rail-left {
	position: fixed;
	left: 0;
	top: 50%;
	transform: translateY(-50%);
	z-index: 9998;
	max-width: 160px;
}
.ad-placr--rail-right {
	position: fixed;
	right: 0;
	top: 50%;
	transform: translateY(-50%);
	z-index: 9998;
	max-width: 160px;
}
@media (max-width: 960px) {
	.ad-placr--rail-left,
	.ad-placr--rail-right {
		display: none !important;
	}
}
```

---

### Task 4: Orphan invariant test + version 2.2.0

**Files:**
- Modify/create tests asserting `partition_from(defaults)` frontend∪special∪manual == all keys AND every frontend key has non-empty hook
- `ad-placr.php`, `readme.txt`, `changelog.md` → **2.2.0** (date via `Get-Date -Format 'dd/MM/yyyy'`)

Changelog bullets: Frontend dispatcher; automatic positions wired; rails CSS; registry hook metadata; theme caveat for `wp_body_open`.

- [ ] **Step 1: Tests green**

- [ ] **Step 2: Version bump**

- [ ] **Step 3: Full `composer test`**

- [ ] **Step 4: Commit**

```bash
git add tests/ ad-placr.php readme.txt changelog.md includes/class-ad-placr-positions.php
git commit -m "feat: 2.2.0 automatic positions via Frontend dispatcher"
```

---

## Manual verification

1. Create Placement `before_post_content` + Ad → view singular post → ad before content.
2. Placement `front_page_top` → appears on static front only, not on a regular post.
3. Placement `sticky_left_rail` → fixed left on wide viewports; hidden &lt;960px.
4. Confirm shortcode/widget keys still do not print via Frontend.

## Spec coverage

| Spec item | Task |
|---|---|
| Registry hook metadata | 1 |
| Context predicates | 2 |
| Frontend dispatcher | 3 |
| Rails CSS | 3 |
| Orphan invariant test | 1, 4 |
| Version 2.2.0 | 4 |
| Special handlers untouched | 3 (no edits required) |
| Manual excluded | 1, 3 |
