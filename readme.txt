=== Ad Placr ===
Contributors: kraftysprouts
Tags: ads, advertising, footer, sticky
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Flexible ad placements for WordPress — starting with a floating footer sticky slot.

== Description ==

Ad Placr helps you place ad code in consistent, theme-agnostic locations. Version 0.1.0 includes a footer sticky placement with optional separate mobile ad code and a configurable breakpoint.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → Ad Placr** to configure the footer sticky placement.

== Changelog ==

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
