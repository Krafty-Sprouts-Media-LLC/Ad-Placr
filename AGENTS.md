# AGENTS.md — Ad Placr

Operating guide for AI agents (and humans) working on **Ad Placr**, a WordPress ad-management
plugin by [Krafty Sprouts Media LLC](https://kraftysprouts.com). Read this **before** touching code.

> **Mission.** Ad Placr is being rebuilt from a two-placement plugin (footer sticky + in-content
> paragraph slots) into a **full ad manager** by absorbing the useful features of the retired
> **Adsly** plugin (`/adsly`, kept in-tree for reference only — never load or ship it).
> The north star: **Adsly's feature set, rebuilt to Ad Placr's code quality.**

---

## 1. Golden rules

1. **Invoke the relevant skill first.** Skills live in `.agents/skills/`. Task → skill map in §3.
   If a skill applies, use it — this is not optional.
2. **Never port Adsly code verbatim.** `/adsly` is a *specification of intent*, not a source of
   truth. It has real bugs (see §9). Re-implement cleanly; do not copy.
3. **Preserve `@since` tags.** They record when code first shipped. Add new ones; never rewrite old.
4. **Ad code is the one escaping exception.** Ad snippets (HTML/JS from ad networks) are stored by
   privileged users and echoed raw *by design*. Every raw echo must carry a
   `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ad network code; stored by privileged users.`
   comment. **Everything else is escaped on output** (`esc_html`, `esc_attr`, `esc_url`).
5. **Verify before claiming done.** Run PHPCS + PHPStan (§7) and load the change in a real request.
   No "should work" — show the evidence.
6. **One bootstrap, load on hooks.** No heavy work at file-load time. Admin-only code stays behind
   `is_admin()` or admin hooks.

---

## 2. What Ad Placr is (current architecture)

- **Entry:** `ad-placr.php` — headers, constants (`AD_PLACR_VERSION`, `AD_PLACR_PLUGIN_*`),
  `require_once` of `includes/`, then `Ad_Placr_Plugin::instance()->boot()`.
- **Core singleton:** `includes/class-ad-placr-plugin.php` — registers subsystems, activation,
  textdomain, and owns `default_settings()` / `get_settings()`.
- **Settings:** `includes/class-ad-placr-settings-page.php` — Settings API, option
  `ad_placr_settings` (single array). Always read via `Ad_Placr_Plugin::get_settings()`.
- **Placements today:**
  - `class-ad-placr-footer-sticky.php` — `wp_footer` (pri 100), floating footer, universal +
    mobile-override code, scoped responsive CSS.
  - `class-ad-placr-in-content.php` — `the_content` (pri 12), N slots targeting paragraph numbers
    (`before`/`after`), per-slot mobile override + scoped CSS.
- **Updates:** `lib/plugin-update-checker/` (PUC, bundled) → GitHub `Krafty-Sprouts-Media-LLC/Ad-Placr`.
- **Uninstall:** `uninstall.php` removes `ad_placr_settings`.

**Requirements:** PHP **8.0+**, WordPress **6.0+** (see plugin header — the source of truth).

Where this is going: a **separated Ad ↔ Placement model** — an **Ad** (`ad_placr_ad`) is a reusable
creative (code); a **Placement** (`ad_placr_placement`) is a position + targeting rules referencing a
**weighted list of Ads** (rotation built in, A-B-ready). Current option-based placements migrate in.
See `docs/superpowers/specs/2026-07-22-ad-placr-ad-manager-design.md` for the approved design,
`IMPLEMENTATION-PLAN.md` for the phased roadmap, and the canonical **position taxonomy** (§6 here is
the short version).

---

## 3. Skill map — invoke before working

Skills are in `.agents/skills/`. Match the task, invoke the skill, follow it.

| Task | Skill |
|---|---|
| Any plugin structure / hooks / activation / Settings API / security / packaging | `wp-plugin-development` |
| "What kind of repo / tooling is this?" before changes | `wp-project-triage` |
| GPL / naming / trademark / wp.org submission compliance | `wp-plugin-directory-guidelines` |
| REST routes, `show_in_rest`, meta exposure, permission callbacks | `wp-rest-api` |
| A Gutenberg block for manual ad placement | `wp-block-development` |
| Performance: autoloaded options, queries, caching, cron | `wp-performance` |
| Static analysis setup / fixing type errors | `wp-phpstan` |
| WP-CLI commands / ops | `wp-wpcli-and-ops` |
| Exposing capabilities to AI/MCP clients | `wp-abilities-api` |
| A/B testing / rotation logic | `ab-testing` |
| Admin UI polish / design | `wpds`, `apple-design`, `refactoring-ui` |
| Before any feature/build work | `brainstorming`, then `writing-plans` |
| Before writing implementation code | `test-driven-development` |
| Any bug / test failure | `systematic-debugging` |
| Before claiming complete / before a PR | `verification-before-completion`, `requesting-code-review` |

If a skill's guidance conflicts with this file, **this file wins for project conventions**; the
skill wins for WordPress correctness. When in doubt, ask.

---

## 4. Coding standards (WordPress + this repo)

- **WordPress PHP Coding Standards**: tabs (not spaces), `array()` long syntax, Yoda conditions,
  spaced parentheses `foo( $bar )`, one class per file.
- **Filenames:** `class-ad-placr-*.php` (kebab-case). **Classes:** `Ad_Placr_*` prefix (no
  namespace) to avoid global collisions. **Functions/hooks/meta/options:** `ad_placr_` prefix.
- **PHP 8.0+ features are welcome** — typed properties, return types, `?type`, `match`, constructor
  promotion — the codebase already uses them.
- **Filters over hardcoding.** Follow the existing pattern
  (`ad_placr_footer_sticky_should_display`, `ad_placr_in_content_mobile_breakpoint`, etc.).
- **Composer is not required** and activation must never depend on `vendor/autoload.php`. Dev tooling
  (PHPCS/PHPStan) stays dev-local.
- **No new bundled third-party libs** without a GPL-compatibility check (`wp-plugin-directory-guidelines`).
  Adsly bundled SweetAlert2 — do **not** carry that over; use core admin UI / `wp.a11y` patterns.

### 4.1 File headers & docblocks (required)

Every PHP file follows WordPress file-comment + DocBlock conventions. Match existing files; do not invent
a different `@package` spelling.

**Main plugin file (`ad-placr.php`)** — WordPress plugin header **plus** DocBlock tags:

```php
<?php
/**
 * Plugin Name:       Ad Placr
 * Plugin URI:        https://kraftysprouts.com
 * Description:       …
 * Version:           x.y.z
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Krafty Sprouts Media LLC
 * Author URI:        https://kraftysprouts.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ad-placr
 *
 * @package AdPlacr
 * @since 0.1.0
 */
```

Keep `Version:` in sync with `AD_PLACR_VERSION` and `readme.txt` `Stable tag` on every release (§8).

**Every other PHP file** — file-level DocBlock immediately after `<?php`:

```php
<?php
/**
 * One-line summary of what this file owns.
 *
 * Optional second paragraph when the “why” is non-obvious (injection strategy, dual-slot CSS, etc.).
 *
 * @package AdPlacr
 * @since x.y.z
 */
```

Then a **separate** class DocBlock (do not merge file + class into one block):

```php
/**
 * What this class is responsible for (one or two sentences).
 *
 * @since x.y.z
 */
final class Ad_Placr_Example {
```

**Members & callables** — every public/protected/private method, property, and class constant gets a
DocBlock:

| Tag | When |
|---|---|
| Summary line | Always — say *what* and *why it exists*, not just the method name restated |
| `@since` | Always — version the member was **first** introduced; never rewrite later |
| `@param` | Every parameter (type + meaning) |
| `@return` | Every method, including `: void` |
| `@var` | Properties when the PHP type alone is insufficient (e.g. `array<string, mixed>`) |

**Hooks you introduce** — document with a DocBlock on the `apply_filters` / `do_action` call (summary,
`@since`, `@param` for each argument passed to callbacks), matching the existing filter docs in
`Ad_Placr_Footer_Sticky` / `Ad_Placr_In_Content`.

**Guards after the file header** — bootstrap files use `if ( ! defined( 'ABSPATH' ) ) { exit; }`;
`uninstall.php` uses `WP_UNINSTALL_PLUGIN`.

**Package name:** always `@package AdPlacr` (PascalCase, no underscore).

### 4.2 Inline comments — explain the “why”

DocBlocks describe the API surface. **Inline comments** explain non-obvious control flow so a human
can follow the code without reverse-engineering it.

**Do comment when:**
- A branch exists for a subtle reason (e.g. `in_the_loop()` + `is_main_query()` to avoid injecting into
  widgets / secondary loops).
- Algorithm or ordering matters (e.g. single-pass `<p>` split so multiple slots can target the same
  paragraph).
- CSS / markup pairing is intentional (dual universal/mobile slots + breakpoint media queries).
- Security exceptions are deliberate (raw ad-code echo — already required in §1.4).
- Defaults, clamps, or magic numbers need a one-line rationale (e.g. 782px WP admin breakpoint).

**Write comments that teach:** prefer 2–4 lines above a block that state the intent, the constraint,
and the consequence of changing it. Use `/* … */` multi-line blocks for algorithms; `//` for a short
local note.

**Do not comment:** restating the next line in English (`// Get settings` above `get_settings()`),
TODO noise without an issue, or large banners. Delete commented-out code — never ship it.

**Bar for “done” on a non-trivial change:** a reader unfamiliar with the feature should understand the
happy path and the important guards from the DocBlocks + inline comments alone, without reading Adsly
or the design doc first.

---

## 5. Security & data checklist (every change)

- **Capabilities:** gate admin actions on `manage_options` (constant
  `Ad_Placr_Settings_Page::CAPABILITY`). CPT ad editing = `manage_options`.
- **Nonces:** every form (Settings API handles its own), every AJAX/REST write. Verify before use.
- **Sanitize on input, escape on output.** Ad code is the documented exception (§1.4); a
  non-`unfiltered_html` user's input still passes through `wp_kses_post`.
- **SQL:** `$wpdb->prepare()` for every dynamic query; never interpolate. Prefer core APIs
  (`WP_Query`, `get_post_meta`) over raw SQL.
- **No autoload bloat:** large/rarely-read options use `add_option( ..., '', 'no' )`. The main
  settings option is already saved with autoload `false` in activation — keep it that way.
- **REST:** always set an explicit `permission_callback`; `show_in_rest` only with a schema.
- **User-agent / superglobals:** guard with `isset()` and sanitize; do not gate core logic on UA
  sniffing (Adsly's device detection is unreliable — prefer CSS breakpoints, as Ad Placr already does).

---

## 6. Canonical position taxonomy

All placements resolve to one of these **canonical keys** (full mapping from Adsly's keys is in
`IMPLEMENTATION-PLAN.md`). Use these exact strings for meta values, CSS classes
(`ad-placr-pos-{key}`), and filters.

```
in_content_before_paragraph   in_content_after_paragraph
before_post_content           after_post_content
before_header                 after_header
before_footer                 after_footer
sticky_footer                 sticky_left_rail        sticky_right_rail
front_page_top                front_page_bottom
blog_index_top                blog_index_bottom
archive_top                   archive_bottom
sidebar_widget                manual_shortcode        manual_block
```

Never invent a variant spelling (Adsly had `sticky_footer_banner` **and** `sticky_footer` in the same
file — that inconsistency is a bug we are eliminating).

---

## 7. Verification (run these; paste output in PRs)

```bash
# From plugin root. Dev-local tooling only.
composer exec phpcs -- --standard=WordPress includes/ ad-placr.php
composer exec phpstan -- analyse
```

- No PHPCS errors (warnings triaged). See `wp-phpstan` for baseline/config.
- Load a real front-end request with the placement active; confirm output and no PHP notices
  (`WP_DEBUG` + `WP_DEBUG_LOG` on).
- For admin changes: save the settings/CPT screen and confirm values round-trip.

Never assert "done/fixed/passing" without command output backing it (`verification-before-completion`).

---

## 8. Release process

1. Bump version in **`ad-placr.php`** (header `Version:` **and** `AD_PLACR_VERSION`) and
   `readme.txt` (`Stable tag`).
2. Add a section to **`changelog.md`**.
3. Commit `lib/plugin-update-checker/` when present (PUC reads version from `ad-placr.php` on GitHub).
4. Push to `main`, tag the release. Default update branch is `main`
   (override via `ad_placr_update_checker_branch`).

---

## 9. Adsly's mistakes — never repeat these

Concrete bugs found in `/adsly` during the audit. When re-implementing a feature, confirm it does
**not** reproduce the matching failure:

1. **Meta write/read key mismatch** — admin writes `adsly_ad_code`; shortcodes read `_adsly_ad_code`.
   → Define each meta key **once** as a constant; write and read the same constant everywhere.
2. **Orphaned positions** — `front_page_*`, `blog_homepage_*`, `archive_*` were rendered by hooks but
   absent from the picker; `before/after_post_content` were in the picker with no render code.
   → A position is only "real" when picker, storage, and render all reference the same taxonomy key.
3. **Status gating inconsistent** — some paths required `status === 'active'`, others ignored status.
   → One `is_ad_active()` gate used by every render path.
4. **Dead analytics** — custom `wp_adsly_impressions`/`_clicks` tables were created, but tracking
   incremented post-meta counters instead, and the `adsly_cleanup_analytics` cron had no handler.
   → If you build analytics, one storage path, and every scheduled hook has a registered callback.
5. **Bundled SweetAlert2** — heavy third-party JS for confirm dialogs. → Use core admin UI.
6. **Custom-everything admin** with `show_ui => false` and `show_in_rest => false`. → Prefer leaning
   on core (list tables / block editor / REST) before hand-rolling.

---

## 10. Reference docs in this repo

- `docs/superpowers/specs/2026-07-22-ad-placr-ad-manager-design.md` — **approved design** (confirmed
  architecture decisions + Ad Inserter-informed backlog tiers). Start here for the "what/why".
- `IMPLEMENTATION-PLAN.md` — the phased roadmap (phases, taxonomy mapping, acceptance criteria).
- `development.md` — local setup, layout, PUC/update details.
- `roadmap.md` — longer-horizon backlog beyond v1.
- `changelog.md` — shipped history.
- `/adsly/**` and `/ad-inserter/**` — **reference only** (gitignored). Third-party/legacy source kept
  locally to mine ideas. Never enqueue, load, commit, or ship. `ad-inserter/` is another vendor's GPL
  plugin — read for patterns, never copy code.
