# Ad Placr Clean-Start 2.7.0 Design

**Status:** Approved by the product owner on 2026-07-26.

## Decision

Ad Placr 2.7.0 starts with the unified one-Ad model only. The plugin has no production users, so
there is no user data or released two-record workflow to preserve.

The unreleased local Ad/Placement records and old settings are disposable development data. Ad Placr
will not convert, retain, hide, or audit them.

## Runtime

- Delete `Ad_Placr_Migration` and its bootstrap registration.
- Delete the migration map, lock, database-version option, and source-hiding query.
- Remove legacy footer/in-content settings from the current settings shape.
- Keep only the statistics opt-in in `ad_placr_settings`.
- Keep the unified `ad_placr_ad` post type and its four current meta fields.
- Keep statistics-table creation and retention because those are normal clean-install plugin setup,
  not conversion of legacy Ad/Placement data.

## Administration

Users see one Ads workflow. There is no Placement post type, converter, source record, migration
notice, or migration-specific behavior.

## Documentation

Current documentation describes a clean 2.7.0 installation. Historical changelog entries remain
historical, but the 2.7.0 release notes must not promise data conversion.

The 2026-07-23 design and plan are implementation history. This decision supersedes only their
legacy-data migration requirements; the unified Ad model remains approved.

## Verification

- Source scans prove no runtime or current test references `Ad_Placr_Migration`,
  `ad_placr_unified_migration_*`, `ad_placr_db_version`, or `ad_placr_placement`.
- PHPUnit, WordPress PHPCS, PHPStan, PHP syntax, JavaScript syntax, and release-version scans pass.
- LocalWP/database verification is unnecessary for deleting an unreleased compatibility path and is
  not part of this clean-start completion.
