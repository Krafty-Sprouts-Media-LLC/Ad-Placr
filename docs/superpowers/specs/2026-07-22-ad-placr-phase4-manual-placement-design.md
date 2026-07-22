# Ad Placr — Manual Placement: Shortcode + Widget (Design Spec)

**Date:** 2026-07-22  
**Status:** Approved  
**Version target:** **2.3.0**  
**Related:** `IMPLEMENTATION-PLAN.md` Phase 4, ad-manager design, Phase 3 positions

## Purpose

Ship explicit, on-demand ad output via shortcode and classic sidebar widget — the
`handler=manual` positions — using the **same** Ad/Placement meta constants as CPT
admin writes (Adsly bug #1 cannot recur). Gutenberg block stays backlog.

## Confirmed decisions

1. **Version:** **2.3.0**.
2. **Shortcode attrs (option 1):** `[ad_placr placement="123"]` and `[ad_placr ad="456"]`.
   - If both set, **`placement` wins**.
   - Invalid / zero / missing → empty string (fail closed for output).
3. **Widget:** classic `WP_Widget` — pick a Placement by ID; optional sticky CSS class.
4. **Render path:** `Ad_Placr_Renderer::render_placement()` / new `render_ad()`; no parallel
   meta keys in Shortcode/Widget.
5. **`manual_block`:** registered in taxonomy only; no block UI in 2.3.0.

## Architecture

```
[ad_placr placement|ad]
  → Shortcode::resolve_request(atts)
  → Renderer::render_placement | render_ad
  → Ad_Placr_Ad::get_code / get_mobile_code  (META_* constants)

WP_Widget (Ad Placr)
  → instance.placement_id + sticky flag
  → Renderer::render_placement
  → optional .ad-placr--widget-sticky
```

### Shortcode

| Attr | Meaning |
|---|---|
| `placement` | Placement post ID (preferred when both present) |
| `ad` | Ad post ID |

Wrapper: `dom_id` = `ad-placr-sc-{id}`, modifier `ad-placr--manual-shortcode` (placement)
or `ad-placr--manual-ad` (ad).

Placement shortcode does **not** require `position=manual_shortcode` — an explicit ID is an
intentional embed anywhere content allows shortcodes.

### Widget

- Base ID: `ad_placr`.
- Fields: `placement_id` (int), `sticky` (bool).
- Form: select of published placements (title + ID); checkbox for sticky.
- Output: `before_widget` + renderer HTML + `after_widget`.
- Sticky: modifier `ad-placr--widget-sticky` + enqueue `assets/css/widget.css`
  (`position: sticky; top: …` — minimal).

Placements with position `sidebar_widget` are the intended match, but the picker lists **all**
published placements so editors can reuse a creative placement without duplicating posts.

### Meta invariant (tested)

Shortcode and Widget never invent meta key strings. They call Ad/Placement accessors /
Renderer only. Unit test asserts Ad `META_*` constants equal the documented underscore keys
and that Shortcode/Widget source does not contain hard-coded `_ad_placr_` meta strings.

## Acceptance

1. Shortcode with valid `placement` / `ad` returns real wrapper HTML (active + non-empty code).
2. Widget outputs the same renderer path for its placement ID.
3. Automated test: read keys == write keys (constants + no hard-coded meta in manual handlers).
4. PHPCS + PHPStan clean; version **2.3.0** + changelog.

## Out of scope

Gutenberg block, Phase 5 targeting depth, analytics, REST.
