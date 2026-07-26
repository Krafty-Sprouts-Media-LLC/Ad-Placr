# Ad Placr — development

**Ad Placr** is a WordPress ad-management plugin by
[Krafty Sprouts Media LLC](https://kraftysprouts.com).

## Requirements

- PHP 8.0+
- WordPress 6.0+

The plugin header in `ad-placr.php` is the source of truth.

## Current architecture

One `ad_placr_ad` post owns the complete display record:

- WordPress post status is Active (`publish`) or Paused (`draft`);
- `_ad_placr_position` stores one canonical display location;
- `_ad_placr_targeting` stores display rules;
- `_ad_placr_versions` stores ordered, weighted code versions with stable IDs;
- `_ad_placr_notes` stores private administrator notes.

There is no Placement runtime or separate Placement editor.

## Layout

- `ad-placr.php` — minimal bootstrap, constants, and class loading.
- `includes/class-ad-placr-plugin.php` — subsystem registration, activation, and settings defaults.
- `includes/class-ad-placr-ad.php` — unified Ad post type and version model.
- `includes/class-ad-placr-admin.php` — one-screen Ad editor, list columns, validation, and actions.
- `includes/class-ad-placr-positions.php` — canonical, filterable display-location registry.
- `includes/class-ad-placr-targeting.php` — shared display-rule evaluation.
- `includes/class-ad-placr-renderer.php` — weighted version selection and responsive markup.
- `includes/class-ad-placr-frontend.php` — registry-driven automatic locations.
- `includes/class-ad-placr-footer-sticky.php` — sticky-footer output.
- `includes/class-ad-placr-in-content.php` — before/after paragraph insertion.
- `includes/class-ad-placr-shortcode.php` — `[ad_placr ad="ID"]`.
- `includes/class-ad-placr-widget.php` — sidebar widget that stores one Ad ID.
- `includes/class-ad-placr-analytics.php`, `includes/class-ad-placr-rest.php`, and
  `assets/js/tracking.js` — opt-in Ad/version statistics.
- `lib/plugin-update-checker/` — bundled Plugin Update Checker.

Admin behavior uses the native WordPress UI plus `assets/css/admin.css` and `assets/js/admin.js`; no
JavaScript build step or third-party dialog library is required.

## Settings

`ad_placr_settings` remains the settings option and should always be read through
`Ad_Placr_Plugin::get_settings()`. It contains only the site-wide statistics opt-in. Each Ad stores
its own display location, rules, code versions, status, and notes.

## Coding and verification

- Follow `AGENTS.md`, WordPress PHP coding standards, and existing `@since` history.
- Ad network code is the documented privileged-user raw-output exception; escape everything else.
- Composer is dev-local only and is never required for plugin activation.

Run:

```bash
vendor/bin/phpunit
composer exec phpcs -- --standard=WordPress includes/ ad-placr.php
composer exec phpstan -- analyse
```

Then verify admin save/reload, front-end output, statistics, and `WP_DEBUG_LOG` in real WordPress
requests.

## Releases and updates

Keep the plugin header version, `AD_PLACR_VERSION`, `readme.txt` Stable tag, and `changelog.md`
synchronized. Plugin Update Checker reads the GitHub repository
`Krafty-Sprouts-Media-LLC/Ad-Placr` from `main` by default.

## Uninstall

`uninstall.php` removes plugin options, the statistics table, and the cleanup schedule. It
deliberately does not delete Ad posts.

Deactivation also clears the statistics cleanup schedule; activation recreates it when needed.
