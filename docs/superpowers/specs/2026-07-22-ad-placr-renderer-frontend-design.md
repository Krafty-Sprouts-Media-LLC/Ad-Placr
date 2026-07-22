# Ad Placr — Unified Renderer & CPT Front-End (Design Spec)

**Date:** 2026-07-22  
**Status:** Approved design (pending final spec review)  
**Version target:** **2.1.0**  
**Related:** `IMPLEMENTATION-PLAN.md` Phase 2, foundations plan (2.0.0),  
`docs/superpowers/specs/2026-07-22-ad-placr-ad-manager-design.md`

## Purpose

Cut the front end over to the **Ad ↔ Placement** CPT model: one shared renderer builds
markup; footer sticky and in-content become thin position handlers. Live output no longer
reads `ad_placr_settings` for ad code.

## Confirmed decisions

1. **Source of truth for front-end output:** CPT placements only (choice **A**).
2. **Architecture:** shared `Ad_Placr_Renderer` + thin handlers (approach **1**). No mega
   `Frontend` class in 2.1.0 (that waits for Phase 3 new positions).
3. **Markup parity:** same DOM ids/classes, flex centering, 782px CSS breakpoint
   (filter-overridable), dual universal/mobile slots when both codes exist.
4. **Disclosure:** optional global string from settings; empty default → omit (parity with today).
5. **Legacy options:** retained in DB / settings UI for reference; front end ignores them for
   output. Settings screen shows a notice pointing editors to Ads / Placements.
6. **`@since` / release:** new APIs tagged `@since 2.1.0`; plugin Version **2.1.0**.

## Architecture

```
wp_footer (100) / the_content (12)
  → Ad_Placr_Footer_Sticky | Ad_Placr_In_Content  (thin handlers)
      → query published Placements for position key(s)
      → placement active + targeting match (post types / singular / “all”)
      → Ad_Placr_Renderer::render_placement( $placement_id, $wrapper_args )
            → load META_ADS → choose_weighted
            → Ad::is_active + code / mobile_code meta
            → build wrapper HTML (pure builder) + enqueue scoped CSS when needed
            → echo wrapper; raw-echo ad code once (documented phpcs:ignore)
```

### Components

| Class | Change |
|---|---|
| `class-ad-placr-renderer.php` | **NEW** — weighted pick + wrapper + mobile CSS string + disclosure |
| `class-ad-placr-footer-sticky.php` | **Refactor** — CPT `sticky_footer` → renderer; drop option code reads |
| `class-ad-placr-in-content.php` | **Refactor** — CPT `in_content_*` → renderer HTML into existing `<p>` walk |
| `class-ad-placr-placement.php` | **Extend** — `is_active()`, `query_ids_for_position()`, targeting helpers |
| `class-ad-placr-ad.php` | **Extend** — `get_code()`, `get_mobile_code()` accessors (meta constants only) |
| `class-ad-placr-settings-page.php` | Notice: live ads from CPTs; optional disclosure field |
| `class-ad-placr-plugin.php` | Defaults for `disclosure_text`; require + boot renderer |

### Wrapper contract (parity)

Footer:

```html
<div id="ad-placr-footer-sticky" class="ad-placr ad-placr--footer-sticky" data-mobile-max="{bp}">
  <!-- dual or single slot as today -->
</div>
```

In-content:

```html
<div id="ad-placr-ic-{stable}" class="ad-placr ad-placr--in-content" data-mobile-max="{bp}">
  …
</div>
```

Stable in-content id: from targeting/migration slot id when present; else `placement-{id}`.

### Targeting v1 (handler-local, not full Phase 5 engine)

Reuse migration shape:

- `contexts` contains `all` → always (for that handler’s hook context)
- `contexts` contains `singular` → `is_singular()` + `post_types` allow-list (empty list → no match)
- Placement `META_STATUS` must normalize to `active`; post must be `publish`
- Device rules remain CSS-only (no UA sniffing)

### Escape exception

Exactly **one** call site family inside `Ad_Placr_Renderer` echoes ad network code raw, with the
standard phpcs:ignore comment. Handlers never echo ad code themselves.

## Out of scope (2.1.0)

- New positions (Phase 3)
- Shortcode / widget (Phase 4)
- Full targeting engine / schedule (Phase 5)
- Analytics / A-B UI (Phase 6)
- Admin meta-box UI for weighted ads (may remain raw CPT edit until Phase 7)
- Deleting `ad_placr_settings` keys

## Acceptance

1. After migration, sticky footer and in-content slots render from CPT data with **visual parity**
   to pre-2.1.0 option-based output (same wrappers/classes/breakpoint behavior).
2. Disabling/removing a Placement or marking Ad inactive stops output.
3. Sites with `ad_placr_db_version` set but zero matching placements output nothing (no PHP notices).
4. Unit tests cover pure renderer builders + targeting match helpers; PHPCS clean on touched files.
5. Version headers + changelog show **2.1.0**.

## Risks

- **Unmigrated sites** (`db_version` 0): front end shows no ads until migration runs on `init`.
  Acceptable under choice A; document in changelog.
- **Paragraph injection** still regex-`<p>`; documented limitation unchanged.
- **Capability_type `post`** on CPTs remains a known follow-up from 2.0.0 review.
