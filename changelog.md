# Changelog

All notable changes to **Ad Placr** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.4.0] - 22/07/2026

### Added

- **`Ad_Placr_Targeting`** — single `should_display()` / pure `matches()` gate for every Placement render path (Frontend, footer sticky, in-content, shortcode, widget).
- **Rule families** — contexts, post types, logged-in/guest, schedule start/end, URL path needles, category/tag ID allow-lists (AND across families, OR within lists).
- **Placement Targeting meta box** (`Ad_Placr_Admin`) — minimal native UI; merges into `META_TARGETING` without wiping `paragraph` / `slot_id`.
- **Filter** `ad_placr_targeting_should_display` — override after core evaluation.
- Targeting rule-matrix unit tests (`TargetingTest`).

### Changed

- Version bump to **2.4.0** (`ad-placr.php`, `readme.txt` Stable tag).
- `Ad_Placr_Placement::targeting_matches_singular()` delegates to `Ad_Placr_Targeting::matches()`.

### Notes

- **Fail-open:** empty/missing targeting (or empty `contexts`) shows the placement; explicit deny shapes still hide (e.g. `singular` + empty `post_types`).
- **No UA device gating** — device presentation remains CSS dual-slot / breakpoint only.

## [2.3.0] - 22/07/2026

### Added

- **Shortcode** (`[ad_placr placement="ID"]` / `[ad_placr ad="ID"]`) — manual embeds via shared `Ad_Placr_Renderer`; `placement` wins when both attrs are set.
- **Sidebar widget** (`Ad_Placr_Widget`) — pick a Placement, optional sticky CSS (`assets/css/widget.css`).
- **`Ad_Placr_Renderer::render_ad()`** — single-ad render path used by the shortcode `ad` attribute and by placement weighted pick.
- **Meta-key invariant tests** — manual handlers must not hard-code `_ad_placr_*` meta strings (Adsly bug #1 guard).

### Changed

- Version bump to **2.3.0** (`ad-placr.php`, `readme.txt` Stable tag).

## [2.2.0] - 22/07/2026

### Added

- **Frontend dispatcher** (`Ad_Placr_Frontend`) — registers every `handler=frontend` position to its WordPress hook and renders via `Ad_Placr_Renderer`.
- **Automatic positions wired** — before/after post content, header/footer, sticky left/right rails, front page, blog index, and archive tops/bottoms all render (no orphan keys).
- **Rails CSS** (`assets/css/rails.css`) — fixed left/right sticky rails on wide viewports; hidden below 960px.
- **Registry hook metadata** — `Ad_Placr_Positions::defaults()` carries `hook`, `priority`, `render_mode`, and `handler`; partition helpers (`partition_from`, `frontend_keys`, etc.) plus orphan-invariant unit tests.

### Changed

- Version bump to **2.2.0** (`ad-placr.php`, `readme.txt` Stable tag).

### Notes

- **Theme caveat:** `before_header` and `after_header` hook `wp_body_open`. Themes that omit `wp_body_open()` will not show those placements.

## [2.1.0] - 22/07/2026

### Added

- **Disclosure text** setting (`disclosure_text`) — optional front-end label on each ad wrapper; empty omits the disclosure node.
- **Settings notice** — admin blurb that live ads come from **Ads** and **Placements** CPTs after migration.
- **CPT front-end cutover** — sticky footer and in-content output driven by Placement/Ad posts via `Ad_Placr_Renderer` (legacy option fields retained for reference / re-migration).

### Fixed

- **Migration `slot_id`** — in-content placements now store `targeting['slot_id']` so DOM ids keep `#ad-placr-ic-{legacy_slot_id}` parity after migrate.

### Changed

- Version bump to **2.1.0** (`ad-placr.php`, `readme.txt` Stable tag).

## [2.0.0] - 22/07/2026

### Added

- **Major rebuild foundations** — Ad ↔ Placement CPT model begins here (Adsly feature intent, Ad Placr quality).
- **Dev tooling** — Composer scripts for PHPCS, PHPStan, and PHPUnit (`composer test` / lint / analyse).
- **Canonical position registry** (`Ad_Placr_Positions`) — filterable taxonomy keys for all placement positions.
- **Ad CPT** (`ad_placr_ad`) — reusable creative posts with code / mobile code / status meta (`@since 2.0.0`).
- **Placement CPT** (`ad_placr_placement`) — position + targeting + weighted ad list (rotation-ready).
- **One-time migration** (`Ad_Placr_Migration`) — converts legacy `ad_placr_settings` (footer sticky + in-content slots) into Ad + Placement posts; DB version bumps only when migration succeeds (or when there is nothing to migrate).
- **In-content placement files** shipped with the package (`class-ad-placr-in-content.php`, front-end CSS, settings repeater assets) so clean checkouts load and the 1.1+ settings UI works.

### Changed

- Version bump to **2.0.0** (`ad-placr.php`, `readme.txt` Stable tag). Legacy option-based footer/in-content renderers remain active during the phased cutover.

## [1.1.2] - 22/07/2026

### Added

- **AGENTS.md §4.1–4.2** — WordPress file headers, DocBlock tags (`@package AdPlacr`, `@since`, `@param`, `@return`, `@var`), hook docs, and inline-comment rules that explain non-obvious “why”.
- **Explanatory comments** across bootstrap, settings sanitization, footer sticky dual-slot output, and in-content paragraph injection so the control flow is readable without reverse-engineering.

### Changed

- Version bump to **1.1.2** (`ad-placr.php`, `readme.txt` Stable tag).

## [1.1.1] - 03/04/2026

### Removed

- **Legacy `in_content` migration** in `Ad_Placr_Plugin::get_settings()` — the plugin is still in active development; settings use `in_content_slots` only.
- **Nested foreign plugin directory** — unrelated product tree that had been committed inside this folder by mistake.

### Changed

- **Copy and docs:** neutral wording only (no third-party product names in Ad Placr readme, settings blurbs, or dev notes).

## [1.1.0] - 03/04/2026

### Added

- **Multiple in-content placements:** repeatable slots (up to 30) with per-slot label, enable, post types, paragraph number, before/after, universal + optional mobile code, stable `id` for wrapper/CSS scoping (paragraph-index targeting on rendered content).
- **Admin repeater:** Add / Remove slot UI with `admin/js/in-content-slots.js` and `admin/css/settings-slots.css`.
- **`Ad_Placr_Plugin::get_settings()`** — merged options plus automatic migration from the legacy single `in_content` object (pre-1.1.0) into `in_content_slots` until settings are saved.
- **`roadmap.md`** — future ideas (shortcodes, CPTs, conditions, header/AMP, etc.).

### Changed

- In-content injection uses **`<p>…</p>` block splitting** in one pass so several slots can target the same or different paragraphs.
- Per-slot responsive CSS is scoped to **`#ad-placr-ic-{id}`** when both universal and mobile code exist.

### Removed

- **Breaking:** the filter `ad_placr_in_content_should_display` (single config) is replaced by **`ad_placr_in_content_should_inject`** (global) and **`ad_placr_in_content_slot_should_display`** (per slot, receives `$post_id`).

## [0.1.6] - 03/04/2026

### Added

- **In-content** placement: insert universal (and optional mobile) ad code **before** or **after** a numbered HTML paragraph on singular posts/pages (settings: paragraph index 1–100, post type checkboxes). Runs on `the_content` at priority 12. Filters: `ad_placr_in_content_should_display`, `ad_placr_in_content_mobile_breakpoint`.

## [0.1.5] - 03/04/2026

### Fixed

- Footer sticky: center block-level ad units (e.g. GPT containers, iframes) using flexbox on the bar and slots; responsive mobile override inline CSS no longer forces `display:block`, which broke horizontal centering.

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

[2.0.0]: https://github.com/kraftysprouts/ad-placr/compare/1.1.2...2.0.0
[1.1.2]: https://github.com/kraftysprouts/ad-placr/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/kraftysprouts/ad-placr/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/kraftysprouts/ad-placr/compare/0.1.6...1.1.0
[0.1.6]: https://github.com/kraftysprouts/ad-placr/compare/0.1.5...0.1.6
[0.1.5]: https://github.com/kraftysprouts/ad-placr/compare/0.1.4...0.1.5
[0.1.4]: https://github.com/kraftysprouts/ad-placr/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/kraftysprouts/ad-placr/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/kraftysprouts/ad-placr/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/kraftysprouts/ad-placr/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/kraftysprouts/ad-placr/compare/0.0.0...0.1.0
