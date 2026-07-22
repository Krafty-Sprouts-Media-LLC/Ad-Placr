# Ad Placr Phase 5 Targeting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship **2.4.0** — single Placement targeting gate (`Ad_Placr_Targeting`) used by every render path, with fail-open defaults and no UA device gating.

**Architecture:** Pure `matches( $targeting, $ctx )` + `should_display( $placement_id, $ctx )` that loads meta/active state. Call sites build `$ctx` via `build_request_context()`. Minimal Placement targeting meta box in `Ad_Placr_Admin`.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, PHPUnit + Brain Monkey, WPCS. No new runtime deps.

## Global Constraints

- PHP **8.0+**, WP **6.0+**; WPCS tabs / `array()` / Yoda / `Ad_Placr_*` / `ad_placr_`.
- New APIs `@since 2.4.0`; never rewrite older `@since`.
- Meta keys via class constants only.
- Spec: `docs/superpowers/specs/2026-07-22-ad-placr-phase5-targeting-design.md`.
- Version / Stable tag / changelog → **2.4.0**.

---

## File Structure

**Created:**
- `includes/class-ad-placr-targeting.php`
- `includes/class-ad-placr-admin.php` — Placement targeting meta box
- `tests/unit/TargetingTest.php`

**Modified:**
- `includes/class-ad-placr-frontend.php` — use Targeting
- `includes/class-ad-placr-footer-sticky.php` — use Targeting
- `includes/class-ad-placr-in-content.php` — use Targeting
- `includes/class-ad-placr-shortcode.php` — gate placement embeds
- `includes/class-ad-placr-widget.php` — gate widget output
- `includes/class-ad-placr-plugin.php` — register Targeting/Admin
- `ad-placr.php` — require + version 2.4.0
- `tests/bootstrap.php`
- `readme.txt`, `changelog.md`, `development.md`

---

### Task 1: Pure matchers (TDD)

**Files:** Create `tests/unit/TargetingTest.php`, `includes/class-ad-placr-targeting.php`

**Produces:**
- `Ad_Placr_Targeting::matches( array $targeting, array $ctx ): bool`
- Context keys per spec

- [ ] Write failing matrix tests (contexts/all/fail-open, singular post_types, user, schedule, url_contains, categories/tags, AND across families)
- [ ] Implement `matches()` (+ private family helpers)
- [ ] PHPUnit green for TargetingTest

### Task 2: should_display + request context + wire paths

**Produces:**
- `should_display( int $placement_id, array $ctx ): bool`
- `build_request_context(): array`
- Filter `ad_placr_targeting_should_display`

- [ ] Implement WP wrappers (Brain Monkey for should_display tests optional; pure matches are primary)
- [ ] Replace Frontend / Footer / In_Content private gates
- [ ] Shortcode placement + Widget call `should_display`
- [ ] Full suite green

### Task 3: Admin meta box + version

- [ ] `Ad_Placr_Admin` — meta box on `ad_placr_placement`, save nonce + `manage_options`, sanitize into `META_TARGETING`
- [ ] Bump **2.4.0**, changelog Notes on fail-open + no UA device gate
- [ ] PHPCS + PHPStan + commit
