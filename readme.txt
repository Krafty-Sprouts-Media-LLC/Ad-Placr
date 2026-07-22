=== Ad Placr ===
Contributors: kraftysprouts
Tags: ads, advertising, placements, shortcode, analytics
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full WordPress ad manager: reusable Ads, positioned Placements with targeting and rotation, shortcode/widget, opt-in analytics.

== Description ==

Ad Placr manages ads as two linked ideas:

* **Ads** — reusable creatives (HTML/JS ad code, optional mobile override).
* **Placements** — a canonical position, targeting rules, and a weighted list of Ads (rotation / A-B-ready).

Automatic positions include sticky footer, in-content paragraphs, before/after post content, header/footer, sticky rails, and front page / blog index / archive tops and bottoms. Manual embeds use `[ad_placr placement="ID"]` or `[ad_placr ad="ID"]`, plus a sidebar widget.

Optional first-party analytics stores impressions (viewability) and clicks for 90 days. External hooks always fire for Google Analytics and other listeners.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/ad-placr/`.
2. Activate **Ad Placr** on the Plugins screen.
3. Create **Ads**, then **Placements** (position + weighted ads + targeting).
4. Optionally open **Settings → Ad Placr** for disclosure text and analytics storage.

== Frequently Asked Questions ==

= Where do I put ad code? =

Create an **Ad**, paste network code there, then attach that Ad to a **Placement** with a weight.

= How do I place an ad manually? =

Use `[ad_placr placement="123"]` or `[ad_placr ad="456"]`, or the **Ad Placr** sidebar widget.

= Does analytics collect personal data? =

No. The events table stores only event type, ad ID, placement ID, and timestamp — no IP, user agent, or URL.

== Changelog ==

= 2.6.0 =
* CPT edit meta boxes (Ad creative, Placement details/ads), list-table columns, readme.md + docs refresh. See changelog.md.

= 2.5.0 =
* Opt-in analytics storage + always-on impression/click hooks, REST track endpoint, viewability JS, 90-day retention. See changelog.md.

= 2.4.0 =
* Unified Placement targeting gate; fail-open defaults; no UA device sniffing. See changelog.md.

= 2.3.0 =
* Shortcode [ad_placr placement|ad], sidebar widget with optional sticky. See changelog.md.

= 2.2.0 =
* Frontend dispatcher for automatic positions, rails CSS. See changelog.md.

= 2.1.0 =
* CPT-driven front end, disclosure setting, migration slot_id. See changelog.md.

= 2.0.0 =
* Major rebuild foundations: position registry, Ad/Placement CPTs, migration. See changelog.md.
