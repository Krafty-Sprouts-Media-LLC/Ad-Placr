# Task 1: Complete Unified Ad Domain Model

## Implementation

- Replaced the primary Ad metadata contract with `META_POSITION`, `META_TARGETING`, `META_VERSIONS`, and `META_NOTES`.
- Added version normalization, eligibility filtering, deterministic weighted selection, and unified Ad accessors for activity, position, targeting, versions, and position queries.
- Registered the unified private meta fields and configured the private, title-only Ad CPT with native admin UI, REST disabled, and every required capability mapped to `manage_options`.
- Added transitional `META_CODE`, `META_MOBILE_CODE`, `META_STATUS`, `normalize_status()`, `get_code()`, and `get_mobile_code()` APIs. The unified fields remain authoritative; these documented shims keep existing admin, migration, and legacy renderer paths loadable until Task 8 converts and removes them.
- Replaced the old two-record contract tests with unified version-contract tests and removed `PlacementLogicTest.php`. `tests/bootstrap.php` already retained the temporary Placement require required by the brief, so it was intentionally not changed.

## Files

- Modified: `includes/class-ad-placr-ad.php`
- Added: `tests/unit/UnifiedAdTest.php`
- Modified: `tests/unit/ManualMetaKeysTest.php`
- Deleted: `tests/unit/PlacementLogicTest.php`

## RED evidence

Command:

```powershell
& 'C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe' -d extension_dir=vendor\php-ext -d extension=php_mbstring.dll 'vendor\phpunit\phpunit\phpunit' --filter "UnifiedAdTest|ManualMetaKeysTest"
```

Result: exit 1, 5 expected errors. `ManualMetaKeysTest` reported undefined `Ad_Placr_Ad::META_POSITION`; all four `UnifiedAdTest` cases reported undefined `Ad_Placr_Ad::normalize_versions()`.

## GREEN evidence

Focused command:

```powershell
& 'C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe' -d extension_dir=vendor\php-ext -d extension=php_mbstring.dll 'vendor\phpunit\phpunit\phpunit' --filter "UnifiedAdTest|ManualMetaKeysTest"
```

Result: exit 0, `OK (6 tests, 20 assertions)`.

Full command:

```powershell
& 'C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe' -d extension_dir=vendor\php-ext -d extension=php_mbstring.dll 'vendor\phpunit\phpunit\phpunit'
```

Result: exit 0, `OK (70 tests, 515 assertions)`.

Changed-file PHPCS command:

```powershell
& 'C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe' -d extension_dir=vendor\php-ext -d extension=php_mbstring.dll 'vendor\squizlabs\php_codesniffer\bin\phpcs' --standard=WordPress includes\class-ad-placr-ad.php tests\unit\UnifiedAdTest.php tests\unit\ManualMetaKeysTest.php
```

Result: exit 0.

PHPStan command:

```powershell
& 'C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe' -d memory_limit=-1 -d extension_dir=vendor\php-ext -d extension=php_mbstring.dll 'vendor\phpstan\phpstan\phpstan' analyse --memory-limit=-1
```

Result: exit 0, `[OK] No errors`.

## Self-review

- Verified every requested unified public API and exact meta-key values.
- Verified weighted selection first filters disabled or code-empty versions and normalizes signed rolls.
- Verified position reads and position queries use the canonical `Ad_Placr_Positions::exists()` guard.
- Verified the CPT remains private, native-admin visible, title-only, non-REST, and uses the required `manage_options` capability map.
- A separate read-only review found no P0/P1 Task 1 issues. Its class-responsibility DocBlock finding was fixed before final verification.
- `git diff --check` exits 0.

## Concerns

- The repository-wide PHPCS command (`... phpcs --standard=WordPress includes\ ad-placr.php`) exits 1 solely because 18 untouched baseline files use CRLF while this PHPCS configuration expects LF. The changed files pass PHPCS cleanly.
- PHPStan's configured 128M and an explicit 512M limit both crashed; the unlimited-memory command above completes cleanly.
- A real front-end smoke request could not run because `yenimi.local` does not resolve in this environment, including after an approved unsandboxed retry (`curl: (6) Could not resolve host`).
