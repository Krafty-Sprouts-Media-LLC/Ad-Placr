# Ad Placr — Ad Manager Rebuild (Design Spec)

**Date:** 2026-07-22
**Status:** Approved design (pending final spec review)
**Related:** `../../../IMPLEMENTATION-PLAN.md` (phased roadmap), `AGENTS.md` (conventions)

## Purpose

Rebuild **Ad Placr** from a two-placement plugin (footer sticky + in-content slots) into a full
WordPress ad manager, absorbing the useful ideas of the retired **Adsly** plugin without inheriting
its bugs, and taking selective inspiration from **Ad Inserter**
(<https://adinserter.pro/documentation/features>). Principle: **best-in-class feature *ideas*,
rebuilt to Ad Placr's code quality.**

## Confirmed decisions

1. **Name:** keep **Ad Placr** (slug/text-domain/prefix/repo already established).
2. **Full rebuild, best architecture.** Existing option-based config **migrates** into the new model;
   no parallel legacy system.
3. **Core model — Ad ↔ Placement (separated):** an **Ad** is a reusable creative (code, defined
   once); a **Placement** is a position + targeting rules that references one or more Ads.
4. **Rotation:** a placement holds a **weighted list of ads**; one is chosen per request. This is the
   rotation engine and is A-B-test-ready (compare stats between ads in a placement).
5. **Admin:** **native WordPress, zero-build** — CPT screens + meta boxes + the existing jQuery
   repeater pattern. No webpack/Composer added.
6. **Targeting v1:** **content location** (post types, front page, blog index, archives, search,
   singular/list) + **device** (desktop/tablet/mobile). Everything else is deferred (see Backlog).
7. **Analytics: both** — external `do_action` hooks (always on) + opt-in first-party storage with a
   real retention cron.
8. **Positions registry is filterable** (extensibility baked in from v1).

## Architecture

### Data model (two CPTs)

- **`ad_placr_ad`** (creative): title, universal ad code, optional mobile-override code, default
  device visibility, status, notes. `show_ui` + `show_in_rest` true; capability `manage_options`.
- **`ad_placr_placement`** (where/when): title, one **canonical position key**, targeting rules
  (content-location + device), status, and a **weighted ad list** `[{ad_id, weight}, …]`.
- **All meta keys are class constants**, used for both read and write. (Adsly's #1 bug — writing
  `adsly_ad_code` but reading `_adsly_ad_code` — is structurally impossible here.)

### Components (`includes/`, all zero-build PHP)

| Class | Responsibility |
|---|---|
| `class-ad-placr-plugin.php` | Bootstrap / orchestrator; settings accessor (exists, extended) |
| `class-ad-placr-ad.php` | Ad CPT, meta constants, accessors, `is_active()` |
| `class-ad-placr-placement.php` | Placement CPT; position, rules, weighted-ad meta |
| `class-ad-placr-positions.php` | **Filterable** canonical position registry (key → hook + context predicate) |
| `class-ad-placr-targeting.php` | Single `should_display()` — content location + device |
| `class-ad-placr-renderer.php` | Weighted ad selection + wrapper / mobile-split / disclosure output |
| `class-ad-placr-frontend.php` | Binds each registered position to its hook |
| `class-ad-placr-shortcode.php` | `[ad_placr placement="…"]` / `[ad_placr ad="…"]` |
| `class-ad-placr-widget.php` | Sidebar widget (sticky option) |
| `class-ad-placr-admin.php` | Meta boxes, list-table columns, repeater assets |
| `class-ad-placr-settings-page.php` | Global defaults (disclosure text, analytics toggle) |
| `class-ad-placr-analytics.php` | External hooks + opt-in storage + retention cron |
| `class-ad-placr-migration.php` | v1 option → CPT, idempotent, versioned |
| `class-ad-placr-rest.php` | Nonce-protected tracking endpoint (admin REST later) |

The current `footer-sticky` and `in-content` classes are **absorbed**: their behavior becomes two
positions in the registry + the shared renderer, not standalone files.

### Front-end data flow

```
hook fires (e.g. wp_footer)
  → Frontend asks Positions which placements target this position
  → for each: Targeting::should_display() (content location + device + status)
  → Renderer picks ONE ad by weight
  → outputs wrapper (universal/mobile slots, scoped CSS, disclosure, data-ad/data-placement attrs)
  → front-end JS (vanilla, no jQuery) fires impression (IntersectionObserver) + click
  → REST endpoint (nonce) → Analytics: do_action('ad_placr_impression', …) always;
    first-party rows if enabled
```

### Position taxonomy (v1)

Canonical keys are the single source of truth (full Adsly→canonical mapping in
`IMPLEMENTATION-PLAN.md` §3): in-content before/after paragraph, before/after post content,
before/after header, before/after footer, sticky footer / left rail / right rail, front-page
top/bottom, blog-index top/bottom, archive top/bottom, sidebar widget, manual shortcode/block.

**Invariant (tested):** the set of position keys offered in the picker == the set the renderer can
output. This makes Adsly's orphaned/dead positions impossible.

**Extensibility:** positions are registered through a `ad_placr_register_positions` filter so custom
hook positions can be added without editing core. CSS-selector insertion ("before/inside/after any
element", à la Ad Inserter) is the planned successor to the regex-over-`<p>` paragraph method and
slots into the same registry.

### Migration

`footer_sticky` → 1 Ad (code + mobile) + 1 Placement (`sticky_footer`). Each `in_content_slot` →
1 Ad + 1 Placement (`in_content_*`, paragraph index, post types). Guarded by `ad_placr_db_version`;
the old option is retained (not deleted) until migration is verified; re-running is a no-op.

### Analytics

External `do_action('ad_placr_impression'|'ad_placr_click', $ad_id, $ctx)` always fire (zero-cost
integration point for GA/etc.). First-party storage is opt-in: one dedicated table (`dbDelta`), one
registered retention-cleanup cron, no PII in URLs or logs.

### Security

`manage_options` for all management; nonces on every write (Settings API + REST tracking); sanitize
on input, escape on output. Ad code is the single documented raw-echo exception (privileged users;
non-`unfiltered_html` input still passes `wp_kses_post`). `$wpdb->prepare()` for any dynamic SQL.

### Testing

PHPCS (WordPress) + PHPStan clean. Unit: weighted-selection distribution, targeting matrix, migration
idempotency, meta read==write, and the **picker==render** invariant. Integration: render each
position in a real request; shortcode/widget output; analytics toggle writes/doesn't.

## Backlog (Ad Inserter-informed, tiered — NOT v1)

Deferred deliberately (YAGNI). Ordered roughly by value/effort. Each becomes its own spec when picked.

**Tier 1 — near-term, low risk**
- **Debug/visualization overlay**: highlight inserted blocks and available positions on the front end
  for logged-in admins.
- **Import/export** of ads + placements (JSON) for staging.
- **`ads.txt`** management from admin.
- **Custom CSS class / anti-adblock class naming** option.
- **Gutenberg block** for manual placement (mirrors the shortcode).

**Tier 2 — targeting depth**
- Taxonomy / category / tag / specific-post-ID / URL-pattern black+white lists.
- Visitor rules: logged-in vs guest, user role.
- **Scheduling**: start/end date, time, days-of-week, with fallback ad.

**Tier 3 — performance & delivery**
- Lazy loading (load when visible) + interaction/scroll-delay.
- Full-page-cache-safe rotation (client-side pick).
- More positions: comments (before/after/between), blog-between-posts, 404, RSS, AJAX-loaded content.

**Tier 4 — advanced / product-grade**
- Consent / IAB TCF 2.0 gating (GDPR); insert on consent.
- Ad-block detection + fallback/message/replacement.
- GEO targeting (country/city; MaxMind/CloudFlare), cache-compatible.
- Advanced analytics: PDF/public reports, CTR-based rotation optimization, click-fraud/ reCAPTCHA.
- CSS-selector insertion ("before/inside/after any HTML element").
- Sticky animations (fade/slide/zoom), parallax, background/skin ads.
- Multisite network defaults; WPML compatibility.

## Definition of done (this rebuild / v1)

Ad↔Placement model live with weighted rotation; all v1 positions render and appear in the picker
(invariant tested); content-location + device targeting; migration converts existing config with no
loss; analytics hooks fire + opt-in storage works with real retention; PHPCS + PHPStan clean; each
placement verified in a real request; `/adsly` removed once parity confirmed.
