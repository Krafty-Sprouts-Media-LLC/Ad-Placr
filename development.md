# Ad Placr — development

**Ad Placr** is a WordPress plugin by [Krafty Sprouts Media LLC](https://kraftysprouts.com). This document describes how to work on the plugin in a local environment.

## Requirements

- PHP **8.0+** (see the plugin header).
- WordPress **6.0+**.

## Layout

- **Main file:** `ad-placr.php` defines constants and loads class files from `includes/` with `require_once`.
- **Third-party:** [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) lives under **`lib/plugin-update-checker/`** (bundled; commit when you publish).
- **Admin assets:** `admin/js/in-content-slots.js`, `admin/css/settings-slots.css` — repeater UI for in-content slots (Settings → Ad Placr).
- **Classes:** WordPress-style `class-ad-placr-*.php` filenames; classes are prefixed with `Ad_Placr_` to limit global namespace collisions.
- **Settings:** `includes/class-ad-placr-settings-page.php` — option `ad_placr_settings` (array). Keys: `footer_sticky`, `in_content_slots` (list of slot arrays). Use **`Ad_Placr_Plugin::get_settings()`** for merged values with defaults.
- **Footer sticky:** `includes/class-ad-placr-footer-sticky.php` — `wp_footer`, `assets/css/footer-sticky.css`. Mobile breakpoint filter: `ad_placr_footer_sticky_mobile_breakpoint` (default **782px**, clamped 320–1200).
- **In-content:** `includes/class-ad-placr-in-content.php` — `the_content` priority **12**. Multiple slots; each slot has `id`, `enabled`, optional `title`, `paragraph_index`, `position` (`before`/`after`), `post_types`, `code`, `mobile_code`. Injection walks `<p>…</p>` chunks in one pass. Per-slot wrapper id: `ad-placr-ic-{slot_id}` for scoped responsive CSS. Filters: `ad_placr_in_content_should_inject`, `ad_placr_in_content_slot_should_display`, `ad_placr_in_content_mobile_breakpoint`.

## Roadmap

See **`roadmap.md`** for features under consideration (CPT picker, shortcodes, header placements, consent, etc.).

Composer is **not** required for this plugin: there is no `vendor/` autoloader. If you later add Composer-only tooling (e.g. PHPCS), keep it dev-local and do not make activation depend on `vendor/autoload.php`.

## Coding standards

- Follow the [WordPress PHP coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) for this codebase (tabs, Yoda conditions where appropriate, `array()` syntax, etc.).
- Do not change existing `@since` tags when editing; they mark when code was first introduced.

## Changelog and versioning

After functional changes, update `changelog.md` and bump the plugin version in:

- `ad-placr.php` (header + `AD_PLACR_VERSION`)
- `readme.txt` (`Stable tag` when applicable)
- `changelog.md` (new section)

## GitHub releases and updates

The plugin ships with [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) in **`lib/plugin-update-checker/`** (commit that directory when you push). Updates are checked against **`https://github.com/Krafty-Sprouts-Media-LLC/Ad-Placr`** on branch **`main`** by default.

- **Bump the version** in `ad-placr.php` (`Version` header and `AD_PLACR_VERSION`) before tagging.
- **Push** your changes to GitHub, then create a **tag** or ensure the branch `HEAD` reflects the new version (PUC reads the version from `ad-placr.php` in the repo).
- If your default branch is **`master`** instead of `main`, add a small mu-plugin or theme snippet, or use:  
  `add_filter( 'ad_placr_update_checker_branch', fn () => 'master' );`

If the repository is **private**, you must supply a GitHub token with read access — see the Plugin Update Checker README (`setAuthentication`).

## Uninstall

`uninstall.php` removes `ad_placr_settings` when the plugin is deleted from WordPress.
