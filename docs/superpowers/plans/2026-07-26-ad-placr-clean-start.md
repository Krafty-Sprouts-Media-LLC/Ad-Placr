# Ad Placr Clean-Start 2.7.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development
> (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use
> checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the unified one-Ad 2.7.0 build without unreleased legacy-data migration.

**Architecture:** Remove the isolated migration subsystem and every consumer of its map. Reduce the
settings option to the current statistics toggle while leaving normal analytics-table setup intact.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, PHPUnit, WordPress PHPCS, PHPStan.

## Global Constraints

- No LocalWP/database action is required.
- No production users or released two-record data exist.
- Preserve the unified Ad model and existing `@since` history.
- Use ordinary user-facing language.

---

### Task 1: Prove the runtime has no migration dependency

**Files:**

- Modify: `tests/unit/SanityTest.php`

- [x] Add source assertions that reject `Ad_Placr_Migration`, its class filename, its three option
  names, and `ad_placr_placement` in current runtime files.
- [x] Run `vendor/bin/phpunit --filter SanityTest` and confirm failure against the existing
  migration subsystem.

### Task 2: Remove migration and compatibility

**Files:**

- Delete: `includes/class-ad-placr-migration.php`
- Delete: `tests/unit/MigrationBuilderTest.php`
- Delete: `tests/unit/AdminQueryTest.php`
- Modify: `ad-placr.php`
- Modify: `includes/class-ad-placr-plugin.php`
- Modify: `includes/class-ad-placr-admin.php`
- Modify: `includes/class-ad-placr-settings-page.php`
- Modify: `tests/bootstrap.php`
- Modify: `tests/unit/AdminTest.php`
- Modify: `uninstall.php`

- [x] Remove migration bootstrap and source-hiding query behavior.
- [x] Reduce default/current settings to `analytics_enabled`.
- [x] Make statistics saving return the current clean settings shape rather than preserving unknown
  legacy values.
- [x] Remove migration/database-version uninstall options.
- [x] Run focused and full PHPUnit until green.

### Task 3: Correct release documentation

**Files:**

- Modify: `readme.txt`
- Modify: `readme.md`
- Modify: `changelog.md`
- Modify: `development.md`
- Modify: `roadmap.md`
- Delete: `docs/HANDOVER-2026-07-24-UNIFIED-AD-MODEL.md`

- [x] Remove current migration promises and backup instructions.
- [x] State that 2.7.0 is a clean-start unified model.
- [x] Keep older changelog sections unchanged as historical records.

### Task 4: Verify and integrate

- Add: `.gitattributes`

- [x] Run full PHPUnit.
- [x] Run PHPCS over `includes/` and `ad-placr.php`.
- [x] Run full PHPStan.
- [x] Run PHP and JavaScript syntax checks.
- [x] Run terminology, version, source, and `git diff --check` scans.
- [x] Keep PHP source on LF line endings so Windows checkouts pass WordPress PHPCS.
- [x] Commit, review, fast-forward merge into `main`, rerun tests from `main`, and confirm a clean
  checkout.
