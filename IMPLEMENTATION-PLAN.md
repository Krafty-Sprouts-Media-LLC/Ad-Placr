# Ad Placr — Unification & Rebuild Master Plan

**Goal:** merge the useful features of the retired **Adsly** plugin into **Ad Placr**, turning Ad
Placr from a two-placement plugin into a **full WordPress ad manager** — without inheriting Adsly's
bugs. Guiding principle: **Adsly's feature set, rebuilt to Ad Placr's code quality.**

This plan is the phased source of truth for the rebuild. Agents: read `AGENTS.md` first, then this,
then the approved design at `docs/superpowers/specs/2026-07-22-ad-placr-ad-manager-design.md` (which
holds the confirmed architecture decisions). Feature inspiration (not a spec) drawn selectively from
**Ad Inserter**: <https://adinserter.pro/documentation/features>.

**Confirmed core model:** ads and placements are **separated** — an **Ad** is a reusable creative
(code, defined once); a **Placement** is a position + targeting rules that references a **weighted
list of Ads** (rotation built in, A-B-ready). Admin is native/zero-build. v1 targeting = content
location + device. See the design spec for the full decision record and the Ad Inserter-informed
backlog tiers.

---

## 1. Where we are

| | Ad Placr (keep the *quality*) | Adsly (keep the *ideas*, drop the code) |
|---|---|---|
| **Data model** | One option `ad_placr_settings` (array) | CPT `adsly_ads`, one post per ad + per-ad meta |
| **Code** | PHP 8.0+, typed, Settings API, filters, PHPCS-clean | PHP 7-era, untyped, custom-everything, buggy |
| **Placements** | Footer sticky, in-content paragraph slots | ~11 positions + widget + 3 shortcodes |
| **Targeting** | Post-type + mobile/universal | Devices, display rules, paragraph number |
| **Analytics** | None | Impressions/clicks (broken — see audit) |
| **Manual placement** | None | Widget + shortcodes (broken — key mismatch) |
| **Updates** | PUC → GitHub | none |

**Core decision:** the single-option model cannot scale to many ads with per-ad targeting,
analytics, and rotation. We adopt a **CPT-backed "ad unit" model** (`ad_placr_ad`) as the scalable
core, and fold the existing footer/in-content placements into it. The current option-based settings
migrate forward (Phase 1) so no user config is lost.

---

## 2. Target architecture

```
ad-placr.php                      bootstrap (unchanged shape)
includes/
  class-ad-placr-plugin.php       singleton; boots subsystems; settings accessor
  class-ad-placr-ad.php           NEW: Ad (creative) CPT — code + meta constants + is_active()
  class-ad-placr-placement.php    NEW: Placement CPT — position + rules + weighted ad list
  class-ad-placr-positions.php    NEW: FILTERABLE canonical taxonomy registry (key → hook + context)
  class-ad-placr-targeting.php    NEW: single should_display() — content location + device
  class-ad-placr-renderer.php     NEW: weighted ad pick + single render path (wrapper/mobile/disclosure)
  class-ad-placr-frontend.php     NEW: hooks every position → renderer
  class-ad-placr-shortcode.php    NEW: [ad_placr placement="…"|ad="…"] (same meta constants — no mismatch)
  class-ad-placr-widget.php       NEW: sidebar widget (sticky option)
  class-ad-placr-admin.php        NEW: meta boxes, list-table columns, jQuery repeater assets
  class-ad-placr-settings-page.php    KEEP → global settings (disclosure text, analytics toggle)
  class-ad-placr-analytics.php    NEW: external hooks (always) + opt-in storage + retention cron
  class-ad-placr-migration.php    NEW: v1 option → Ad/Placement CPTs (idempotent, versioned)
  class-ad-placr-rest.php         NEW: nonce-protected tracking endpoint (admin REST later)
  class-ad-placr-plugin-updater.php   KEEP
admin/  assets/  lib/  languages/
```

`class-ad-placr-footer-sticky.php` and `class-ad-placr-in-content.php` are **absorbed** — their
behavior becomes two entries in the position registry + the shared renderer, not standalone files.

**Meta keys are class constants** used for both read and write — the single fix that kills Adsly's
#1 bug (write `adsly_ad_code` / read `_adsly_ad_code`).

---

## 3. Canonical position taxonomy (Adsly → Ad Placr)

`class-ad-placr-positions.php` is the one place positions are defined. Each entry: canonical key,
label, render hook, and context predicate. **Nothing renders a position that isn't registered here.**

| Canonical key | Label | Hook / mechanism | Adsly source key |
|---|---|---|---|
| `in_content_before_paragraph` | Before paragraph N | `the_content` (12) | `content_before_paragraph` ✅ have |
| `in_content_after_paragraph` | After paragraph N | `the_content` (12) | `content_after_paragraph` ✅ have |
| `before_post_content` | Before post content | `the_content` (11) | `before_post_content` *(picker-only in Adsly)* |
| `after_post_content` | After post content | `the_content` (13) | `after_post_content` *(picker-only in Adsly)* |
| `before_header` | Before header | `wp_body_open` / `get_header` | `before_header` |
| `after_header` | After header | `wp_head`→buffer / `get_header` | `after_header` |
| `before_footer` | Before footer | `get_footer` | `before_footer` |
| `after_footer` | After footer | `wp_footer` | `after_footer` |
| `sticky_footer` | Sticky footer banner | `wp_footer` (100) | `sticky_footer_banner` ✅ have |
| `sticky_left_rail` | Sticky left rail | `wp_footer` | `sticky_left_sidebar` |
| `sticky_right_rail` | Sticky right rail | `wp_footer` | `sticky_right_sidebar` |
| `front_page_top` / `front_page_bottom` | Static front page top/bottom | `wp_head`/`wp_footer` + `is_front_page()` | `front_page_top/bottom` *(orphaned in Adsly)* |
| `blog_index_top` / `blog_index_bottom` | Blog index top/bottom | + `is_home()` | `blog_homepage_top/bottom` *(orphaned)* |
| `archive_top` / `archive_bottom` | Archive top/bottom | + `is_archive()` | `archive_top/bottom` *(orphaned)* |
| `sidebar_widget` | Sidebar widget | `WP_Widget` / block | Adsly widget |
| `manual_shortcode` | Shortcode | `[ad_placr id="…"]` | Adsly shortcodes |
| `manual_block` | Block | Gutenberg block | *(new; roadmap)* |

Header/footer "before/after" hooks vary by theme; `wp_body_open` and `get_header`/`get_footer` are
the portable anchors. Document theme caveats in the position registry.

---

## 4. Phased delivery

Each phase ships independently, stays PHPCS/PHPStan-clean, and keeps the plugin usable. Do **not**
start a phase's code before its brainstorm/plan/TDD skills are invoked (see `AGENTS.md` §3).

### Phase 0 — Foundations (no user-facing change)
- Add dev tooling: `phpcs.xml.dist` (WordPress standard), `phpstan.neon.dist` (via `wp-phpstan`).
- Add `class-ad-placr-positions.php` with the taxonomy in §3 (data only, nothing renders yet).
- **Acceptance:** tooling runs clean on current code; taxonomy unit-covered.

### Phase 1 — Ad + Placement models (CPTs) + migration
- `class-ad-placr-ad.php`: register CPT `ad_placr_ad` (the reusable creative — `show_ui` +
  `show_in_rest` true, `manage_options`), meta as **constants**, `is_active()` gate.
- `class-ad-placr-placement.php`: register CPT `ad_placr_placement` — one canonical position key,
  targeting meta, and a **weighted ad list** `[{ad_id, weight}, …]` (rotation baked in).
- `class-ad-placr-migration.php`: one-time convert `footer_sticky` + each `in_content_slot` from
  `ad_placr_settings` into an Ad + a Placement. Idempotent, versioned (`ad_placr_db_version`); old
  option retained until verified.
- **Acceptance:** existing placements still render after migration; no config lost; re-running is a
  no-op; every meta key has one read site and one write site referencing the same constant.

### Phase 2 — Unified renderer + refactor existing placements
- `class-ad-placr-renderer.php`: single output path — wrapper markup, universal/mobile split, scoped
  responsive CSS, optional "Advertisement" disclosure. Carry over Ad Placr's existing flex-centering
  and 782px breakpoint logic (filter-overridable).
- Refactor footer-sticky and in-content to *position handlers* that call the renderer.
- **Acceptance:** identical front-end output to today (visual diff), fewer code paths, one escaping
  exception site.

### Phase 3 — New positions
- Header/footer/post-content/front-page/blog/archive positions from §3, each wired to the renderer
  and guarded by its context predicate.
- **Acceptance:** every registered position both appears in the picker and renders; no orphans
  (Adsly bug #2 cannot recur — enforce with a test that asserts picker keys == render keys).

### Phase 4 — Manual placement
- `class-ad-placr-shortcode.php`: `[ad_placr id="…"]` and `[ad_placr position="…"]`, reading the
  **same** meta constants (kills Adsly bug #1).
- Sidebar `sidebar_widget` (widget and/or block via `wp-block-development`).
- **Acceptance:** shortcode/widget output a real ad; automated test proves read keys == write keys.

### Phase 5 — Targeting & rules
- Per-ad: devices, display rules (post types, singular/archive/front/search, logged-in vs guest,
  URL/category/tag patterns), schedule (start/end). One `should_display()` used by all paths.
- **Acceptance:** rule matrix covered by tests; default behavior documented (fail-open vs fail-closed
  chosen deliberately, unlike Adsly's accidental fail-closed).

### Phase 6 — Analytics (opt-in) & rotation/A-B
- **Two layers, decided:**
  1. **External hooks (always available):** fire `do_action( 'ad_placr_impression', $ad_id, $ctx )`
     and `ad_placr_click` so GA/other trackers can subscribe with zero storage cost.
  2. **First-party storage (opt-in):** when enabled, record impressions/clicks through **one**
     storage path (a dedicated table via `dbDelta`, or a bounded meta/rollup — decide in the phase's
     brainstorm) with a **real** registered retention-cleanup cron callback.
- Rotation / A-B per position (use the `ab-testing` skill).
- **Acceptance:** external hooks fire regardless of the storage toggle; disabling first-party
  analytics writes nothing to our storage; every scheduled hook has a registered callback; no PII in
  URLs or logs; retention actually prunes.

### Phase 7 — Admin polish & docs (**shipped 2.6.0**)
- CPT edit meta boxes (Ad code/status; Placement position/status/ads/targeting) + list-table
  columns (position, status, ads count, impressions/clicks).
- Docs: `readme.md`, refreshed `readme.txt` / `development.md` / `changelog.md`.
- **Deferred (roadmap):** admin REST/abilities for AI clients; retiring `/adsly` from the local
  tree once parity is confirmed.

---

## 5. Audit — issues to fix or avoid (carried from the review)

**Adsly (do not reproduce):** meta write/read key mismatch (shortcodes/zones fully broken); orphaned
positions vs picker-only positions; inconsistent `status` gating; analytics tables created but unused
+ retention cron with no handler; bundled SweetAlert2; `show_ui`/`show_in_rest` both false; UA-sniff
device detection; unguarded `$_SERVER['HTTP_USER_AGENT']`.

**Ad Placr (address during rebuild):** single-option model doesn't scale (Phase 1 resolves);
paragraph injection is regex over `<p>` blocks — brittle for block markup, keep but document limits;
no automated tests today (add per phase); no capability path below `manage_options` (fine for now,
revisit if editor-role management is wanted).

---

## 6. Decisions (confirmed) & remaining risks

**Confirmed:**
- **Full rebuild, best architecture.** Option-based placements **migrate into** the CPT model
  (Phase 1); no parallel legacy system is kept. Migration is one-time, idempotent, and versioned so
  no existing config is lost.
- **Analytics = both.** External impression/click *hooks* (always on) **and** opt-in first-party
  storage (Phase 6, §4).

**Remaining risks:**
- **Header/footer hook portability** depends on the active theme; some positions may need a
  documented "requires `wp_body_open`" note and a graceful fallback.
- **First-party storage shape** (custom table vs rollup) is deferred to the Phase 6 brainstorm —
  chosen for scale + privacy, not copied from Adsly's unused tables.
- **Backward compatibility window:** keep reading the old `ad_placr_settings` shape until the
  migration has run on a site (guard on `ad_placr_db_version`).

---

## 7. Definition of done (whole project)

Feature parity with Adsly's *intended* behavior, zero Adsly bugs reproduced, PHPCS + PHPStan clean,
each placement verified in a real request, `AGENTS.md` conventions honored throughout, `/adsly`
removed from the tree.
