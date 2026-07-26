=== Ad Placr ===
Contributors: kraftysprouts
Tags: ads, advertising, ad manager, shortcode, analytics
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete WordPress ad manager with automatic display locations, display rules, Ad versions, and statistics.

== Description ==

Ad Placr keeps everything about an advertisement in one **Ad**:

* choose where it appears;
* paste the main ad code and optional mobile code;
* add more code versions and control their traffic weights;
* choose the pages, visitors, and schedule it should match;
* switch it between Active and Paused;
* view impressions, clicks, and click-through rate.

Automatic display locations include sticky footer, in-content paragraphs, before or after post
content, header and footer, sticky side rails, and the top or bottom of front-page, blog, and archive
views.

For manual display, use `[ad_placr ad="123"]` or select an Ad in the Ad Placr sidebar widget.

Optional first-party statistics retain events for 90 days. Stored events contain only event type,
Ad ID, version ID, and timestamp—no IP address, user agent, or page URL.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/ad-placr/`.
2. Activate **Ad Placr** on the Plugins screen.
3. Open **Ads → Add New**.
4. Choose where the Ad should appear and add the ad code.
5. Save it as Paused while preparing it, or choose Active when it is ready.
6. Optionally open **Ads → Settings** to enable first-party statistics.

== Frequently Asked Questions ==

= Where do I put ad code? =

Open an Ad and use the **Ad code** section. The first version can contain the main code and an
optional mobile replacement. Add more versions only when you want weighted code choices.

= How do I place an ad automatically? =

Choose a display location in the Ad editor. Ad Placr handles the matching WordPress hook or content
insertion automatically.

= How do I place an ad manually? =

Choose **Shortcode** as the display location and use `[ad_placr ad="123"]`. For a sidebar, choose
**Sidebar widget** and select the Ad in the Ad Placr widget.

= Can I show more than one Ad in the same location? =

Yes. Matching Ads appear in their saved order. The editor warns when another Active Ad uses the same
automatic location so the choice is deliberate.

= Does statistics collection store personal data? =

No. The events table stores only event type, Ad ID, version ID, and timestamp.

= What happens to older Ad Placr settings? =

The 2.7.0 migration converts older enabled settings—or unreleased local Ad/Placement test data—into
complete Ads. Source data is retained for verification and hidden from the ordinary Ads list.

== Changelog ==

= 2.7.0 =
* One-screen Ads, display locations and rules, weighted code versions, mobile code, unified
  statistics, and safe migration. See changelog.md.

= 2.6.0 =
* Unreleased transitional Ad/Placement administration. See changelog.md.

= 2.5.0 =
* Opt-in statistics storage, REST tracking, viewability tracking, and 90-day retention. See
  changelog.md.

= 2.4.0 =
* Shared display-rule gate with fail-open defaults and no user-agent device detection. See
  changelog.md.

= 2.3.0 =
* Manual shortcode and sidebar widget foundations. See changelog.md.

= 2.2.0 =
* Automatic display-location dispatcher and side-rail styles. See changelog.md.

= 2.1.0 =
* Content display foundations and migration slot identity. See changelog.md.

= 2.0.0 =
* Ad-manager rebuild foundations and canonical display-location registry. See changelog.md.
