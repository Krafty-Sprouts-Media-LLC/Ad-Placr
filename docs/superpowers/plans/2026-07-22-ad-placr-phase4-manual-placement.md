# Ad Placr Phase 4 Manual Placement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship **2.3.0** — `[ad_placr placement|ad]` shortcode + classic sidebar widget, both via shared Renderer and Ad/Placement meta constants.

**Architecture:** Pure `resolve_request()` on Shortcode; `Renderer::render_ad()` for ad embeds; `WP_Widget` picks placement ID + sticky flag. No new meta keys.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, PHPUnit + Brain Monkey, WPCS. No new runtime deps.

## Global Constraints

- PHP **8.0+**, WP **6.0+**; WPCS tabs / `array()` / Yoda / `Ad_Placr_*` / `ad_placr_`.
- New APIs `@since 2.3.0`; never rewrite older `@since`.
- Meta keys via class constants only; ad code raw-echo only in Renderer.
- Composer never required at runtime.
- Version / Stable tag / changelog → **2.3.0**.
- Spec: `docs/superpowers/specs/2026-07-22-ad-placr-phase4-manual-placement-design.md`.

---

## File Structure

**Created:**
- `includes/class-ad-placr-shortcode.php`
- `includes/class-ad-placr-widget.php`
- `assets/css/widget.css`
- `tests/unit/ShortcodeTest.php`
- `tests/unit/ManualMetaKeysTest.php`

**Modified:**
- `includes/class-ad-placr-renderer.php` — `render_ad()`
- `includes/class-ad-placr-plugin.php` — register Shortcode + Widget
- `ad-placr.php` — require + version 2.3.0
- `tests/bootstrap.php` — require new classes; stub `WP_Widget` if missing
- `readme.txt`, `changelog.md`, `development.md`

---

### Task 1: Shortcode resolve + meta invariant (TDD)

**Files:** Create `tests/unit/ShortcodeTest.php`, `tests/unit/ManualMetaKeysTest.php`; create `includes/class-ad-placr-shortcode.php`

- [ ] **Step 1:** Write failing tests for `resolve_request()` (placement, ad, placement-wins, empty).
- [ ] **Step 2:** Write failing meta-key invariant test (Ad META_* values; Shortcode/Widget sources lack hard-coded `_ad_placr_` meta strings).
- [ ] **Step 3:** Implement `Ad_Placr_Shortcode::resolve_request()` + `register()` / `render()` skeleton.
- [ ] **Step 4:** Run tests — resolve + meta green.

### Task 2: Renderer::render_ad + shortcode render path

**Files:** Modify Renderer; finish Shortcode::render

- [ ] **Step 1:** Add `Ad_Placr_Renderer::render_ad()` mirroring `render_placement` without weighted pick.
- [ ] **Step 2:** Wire Shortcode::render to Renderer with correct dom_id / modifiers.
- [ ] **Step 3:** PHPUnit green.

### Task 3: Widget + CSS

**Files:** Create widget class + CSS; wire boot

- [ ] **Step 1:** Implement `Ad_Placr_Widget` (form/update/widget); sticky modifier helper.
- [ ] **Step 2:** Enqueue `assets/css/widget.css` when sticky instance renders.
- [ ] **Step 3:** Register via `widgets_init` from Shortcode/Widget::register or Plugin boot.
- [ ] **Step 4:** Require in `ad-placr.php` + `tests/bootstrap.php` (WP_Widget stub).

### Task 4: Version, changelog, verify, commit

- [ ] **Step 1:** Bump to **2.3.0** in `ad-placr.php`, `readme.txt`, `changelog.md`.
- [ ] **Step 2:** `composer test`, `composer lint` / phpcs, `composer analyze`.
- [ ] **Step 3:** Commit Phase 4.
