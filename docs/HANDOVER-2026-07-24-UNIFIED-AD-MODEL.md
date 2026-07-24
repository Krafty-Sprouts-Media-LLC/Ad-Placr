# Unified Ad Model Handover After Task 7

Date: 2026-07-24

This handover captures the approved unified Ad model checkpoint. Tasks 1–7 are complete and
reviewed. Tasks 8–10 have not been started.

## Important before opening the local site

The local Yenimi site loads the plugin from the main checkout. After this checkpoint is merged into
`main`, the next WordPress request can run database migration version 2.

**Make a recoverable database backup/export before loading wp-admin or the front end.** Do not use a
live request merely as a smoke test. The controlled first and second requests belong to Task 10.

There are no production users. We intentionally did not retain compatibility for the unreleased
Placement analytics or widget formats. Legacy Ad/Placement source posts and the old public settings
remain untouched migration inputs until the real database verification is complete.

## Approved product model

Ordinary users manage one complete **Ad** on one screen:

- Active or Paused;
- where it appears;
- display rules;
- one or more weighted code versions;
- optional mobile code;
- aggregate and per-version statistics.

There is no user-facing Placement concept. The retained Placement post type is temporarily hidden
storage for migration only and must be removed from runtime in Task 8.

## Completed work

Tasks 1–7 of
`docs/superpowers/plans/2026-07-23-ad-placr-unified-ad-model.md` are complete:

1. Unified Ad record, targeting, weighted versions, and deterministic queries.
2. Ad/version renderer with responsive main/mobile output.
3. Automatic locations render matching Ads directly.
4. Shortcode and widget use only Ad IDs.
5. Statistics store and track only Ad/version identity.
6. One plain-language Ads editor and list; no visible Placements screen.
7. Idempotent migration from either local two-record data or old public settings.

The Task 7 code ends at commit `6d9f67b`. Its independent review reported no Critical, Important,
or Minor findings.

Latest automated evidence:

- PHPUnit: 105 tests, 689 assertions.
- Focused migration PHPCS: clean.
- PHPStan: no errors.
- PHP syntax and `git diff --check`: clean.
- No live WordPress request or live database migration was run.

## Migration behavior to preserve

- Database migration version is `2`.
- Legacy keys are isolated inside `Ad_Placr_Migration`; it does not call
  `Ad_Placr_Placement`.
- If legacy Placement posts exist, they take precedence over old public settings.
- Every destination uses only the four unified Ad meta fields.
- Source posts/settings are never rewritten or deleted.
- The migration map is `ad_placr_unified_migration_map` and is non-autoloaded.
- The lock is `ad_placr_unified_migration_lock`, is non-autoloaded, and uses a conditional database
  insert plus exact-value compare-and-delete.
- Stable source slugs recover incomplete unmapped work without creating duplicate Ads.
- Meta and final status are persisted before a source is mapped.
- A mapped source is never written again; retries process only unmapped sources.
- Source Ad post status, old active state, and either main or mobile code determine version
  eligibility.

Do not simplify the lock back to `add_option()`: supported WordPress versions can update an existing
option row during a race, so it is not a reliable mutex.

## Remaining Task 8 — remove the Placement runtime

Follow Task 8 in the implementation plan with TDD:

- add the source scans first;
- delete `includes/class-ad-placr-placement.php` and its obsolete test;
- remove its bootstrap require and plugin `register()` call;
- remove remaining runtime `Ad_Placr_Placement` and `placement_id` references;
- keep literal legacy Placement keys only in migration/tests;
- on the unified Ads list only, exclude retained source Ad IDs returned by
  `Ad_Placr_Migration::source_ad_ids()`;
- do not hide those source records from exports, direct audit access, or front-end queries globally.

Temporary old Ad constants/accessors retained only to keep migration/admin code loadable should also
be removed when the Task 8 scans prove they are unused.

## Remaining Task 9 — prepare release 2.7.0

- Synchronize `Version:`, `AD_PLACR_VERSION`, and `readme.txt` Stable tag to `2.7.0`.
- Update the description, changelog, development notes, and roadmap for the one-Ad model.
- Update `uninstall.php` to delete both:
  - `ad_placr_unified_migration_map`;
  - `ad_placr_unified_migration_lock`.
- Do not delete retained source posts on uninstall in this release.
- Run terminology/version scans and the full PHPUnit, PHPCS, and PHPStan commands.

The repository-wide PHPCS run currently still reports pre-existing checkpoint cleanup:

- two auto-fixable parameter-alignment findings in
  `includes/class-ad-placr-frontend.php`;
- CRLF policy findings in `includes/class-ad-placr-plugin-updater.php`,
  `includes/class-ad-placr-plugin.php`, `includes/class-ad-placr-positions.php`,
  `includes/index.php`, and `ad-placr.php`.

Task 9's required full PHPCS result is zero errors, so resolve and verify these deliberately rather
than ignoring them.

## Remaining Task 10 — real WordPress verification

Use a database backup/export first. Then follow the plan exactly:

1. Record source Ad/Placement counts and relevant option values.
2. Load the first request and inspect migrated Ads plus the migration map.
3. Load a second request and prove counts/map entries do not increase.
4. Compare every location, rule, status, weight, main code, and mobile code with its source.
5. Confirm retained source posts still exist.
6. Exercise create, save, activate, pause, duplicate, reorder, trash, restore, and validation
   notices in wp-admin.
7. Verify every automatic location, shortcode, widget, responsive range, and multiple-Ad ordering.
8. Verify `data-ad-id` and `data-version-id`, with no `data-placement-id`.
9. Verify aggregate/per-version impressions, clicks, CTR, and retention.
10. Inspect `WP_DEBUG_LOG`, then rerun the complete automated checks.

If real verification finds a defect, return to its owning task, add a failing regression test, fix
it, and commit the fix separately.

## Local verification commands

Composer is not available on PATH in this environment. The working PHP runtime is:

```powershell
& 'C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe' `
  -d extension_dir=vendor\php-ext `
  -d extension=php_mbstring.dll `
  'vendor\phpunit\phpunit\phpunit'
```

Use the same PHP executable to invoke the vendored PHPCS and PHPStan tools. PHPStan may require
single-process analysis with a `1G` memory limit.

## Reference files

- Approved design:
  `docs/superpowers/specs/2026-07-23-ad-placr-unified-ad-model-design.md`
- Implementation plan:
  `docs/superpowers/plans/2026-07-23-ad-placr-unified-ad-model.md`
- Project rules: `AGENTS.md`
- Ignored development reports in the isolated worktree:
  `.superpowers/sdd/task-6-report.md` and `.superpowers/sdd/task-7-report.md`

The older 2026-07-22 design and `IMPLEMENTATION-PLAN.md` are superseded history. Adsly and
Ad Inserter remain reference-only; never load, copy, commit, or ship them.
