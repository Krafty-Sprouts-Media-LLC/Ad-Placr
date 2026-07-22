# Ad Placr — Admin Polish & Docs (Design Spec)

**Date:** 2026-07-22  
**Status:** Approved  
**Version target:** **2.6.0**  
**Related:** `IMPLEMENTATION-PLAN.md` Phase 7

## Purpose

Make Ads/Placements fully editable in wp-admin, surface status/position/analytics columns in
list tables, and rewrite product docs (`readme.txt`, **`readme.md`**, `development.md`) for the
Ad ↔ Placement model. No new admin REST or Abilities API.

## Confirmed decisions

1. **Version:** **2.6.0**.
2. **Scope:** CPT edit meta boxes + list columns + docs (option 1).
3. **Include missing edit UI** for Ad code/status and Placement position/status/weighted ads.
4. **Docs:** update `readme.txt`, create/update **`readme.md`**, refresh `development.md`.
5. **Out of scope:** admin REST, Abilities API, charts, Gutenberg block, retiring local `/adsly`
   (already gitignored / not shipped).

## Admin UI

### Ad meta box (`Ad creative`)
- Fields: universal code, mobile code, status (`active`|`inactive`)
- Meta constants only; sanitize like Settings (`unfiltered_html` → raw, else `wp_kses_post`)

### Placement meta boxes
- **Details:** position select from `Ad_Placr_Positions::all()` labels; status
- **Ads:** repeater `{ad_id, weight}` — select published Ads + weight ≥ 1
- **Targeting:** existing box unchanged

### List tables
- Ads: Status, Impressions, Clicks
- Placements: Position, Status, Ads (#), Impressions, Clicks  
- Counts from `Ad_Placr_Analytics::count_events(…)` when table exists; show `—` when storage
  disabled or zero schema

## Docs

- `readme.txt` — Description/Installation for CPT workflow + shortcode/widget
- `readme.md` — GitHub-oriented overview (features, requirements, links to plan/AGENTS)
- `development.md` — admin meta boxes + list columns note

## Acceptance

1. New Ad + Placement can be configured entirely in wp-admin and render.
2. List columns show correct meta / counts.
3. Docs describe Ad↔Placement model; version **2.6.0**.
4. PHPCS + PHPStan + tests green.
