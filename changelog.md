# Changelog

All notable changes to **Ad Placr** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.4] - 03/04/2026

### Changed

- Moved bundled Plugin Update Checker to **`lib/plugin-update-checker/`** for a cleaner plugin root. Loader path updated in `includes/class-ad-placr-plugin-updater.php`.
- `.gitignore`: ignore `*.zip` artifacts in the plugin directory.

## [0.1.3] - 03/04/2026

### Added

- Bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT) and wired it to the public GitHub repository `Krafty-Sprouts-Media-LLC/Ad-Placr` (default branch `main`). Filter: `ad_placr_update_checker_branch` to use another branch (e.g. `master`).

## [0.1.2] - 03/04/2026

### Changed

- Mobile vs desktop switching when a mobile override exists now uses a fixed **782px** breakpoint automatically (WordPress small-screen width). Removed the “Mobile breakpoint (px)” setting. Themes or custom code can still override via the `ad_placr_footer_sticky_mobile_breakpoint` filter.

## [0.1.1] - 03/04/2026

### Changed

- Replaced Composer PSR-4 loading with WordPress-style `includes/class-*.php` files and explicit `require_once` calls in the main plugin file.
- Renamed classes to prefixed `Ad_Placr_*` (e.g. `Ad_Placr_Settings_Page`) to match common WordPress plugin naming.

## [0.1.0] - 03/04/2026

### Added

- Initial plugin bootstrap with Composer PSR-4 autoloading (`KraftySprouts\AdPlacr\`).
- Settings page under **Settings → Ad Placr**: footer sticky enable, universal ad code, optional mobile override, configurable mobile breakpoint (default 782px).
- Front-end floating footer sticky placement with scoped CSS and responsive slot switching when a mobile override is present.
- `development.md` with local setup notes.
- Uninstall handler to remove stored options.

[0.1.4]: https://github.com/kraftysprouts/ad-placr/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/kraftysprouts/ad-placr/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/kraftysprouts/ad-placr/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/kraftysprouts/ad-placr/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/kraftysprouts/ad-placr/compare/0.0.0...0.1.0
