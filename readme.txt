=== Ad Placr ===
Contributors: kraftysprouts
Tags: ads, advertising, footer, sticky, content
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Flexible ad placements for WordPress: footer sticky and multiple in-content paragraph slots.

== Description ==

Ad Placr helps you place ad code in consistent locations: a floating footer sticky and **multiple** in-content placements (before or after numbered paragraphs on posts/pages), each with optional mobile-specific code. Settings support add/remove slots from one screen.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → Ad Placr** to configure placements.

== Changelog ==

= 2.1.0 =
* CPT-driven front end (renderer, footer/in-content), disclosure setting, settings notice, migration preserves in-content slot_id. See changelog.md.

= 2.0.0 =
* Major rebuild foundations: position registry, Ad/Placement CPTs, legacy settings migration, in-content package files. See changelog.md.

= 1.1.1 =
* Removed legacy `in_content` auto-migration and a mistakenly nested unrelated plugin folder. Product-neutral docs only. See changelog.md.

= 1.1.0 =
* Multiple in-content slots (repeater UI), paragraph block walk, per-slot IDs and filters. See changelog.md for filter changes.

= 0.1.6 =
* In-content placement: paragraph number, before/after, posts/pages, optional mobile override.

= 0.1.5 =
* Footer sticky: flexbox centering for display ad blocks and iframes.

= 0.1.4 =
* Plugin Update Checker relocated to `lib/plugin-update-checker/`.

= 0.1.3 =
* Plugin Update Checker: GitHub updates from https://github.com/Krafty-Sprouts-Media-LLC/Ad-Placr (branch `main` by default).

= 0.1.2 =
* Automatic 782px breakpoint for mobile override (setting removed); filter `ad_placr_footer_sticky_mobile_breakpoint` for custom widths.

= 0.1.1 =
* Load classes via WordPress-style `includes/class-*.php` files; no Composer autoloader required.

= 0.1.0 =
* Initial release: footer sticky placement, universal + optional mobile override, settings screen.
