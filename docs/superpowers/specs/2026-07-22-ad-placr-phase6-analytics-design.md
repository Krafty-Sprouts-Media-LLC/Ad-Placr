# Ad Placr — Analytics & A/B-Ready Tracking (Design Spec)

**Date:** 2026-07-22  
**Status:** Approved  
**Version target:** **2.5.0**  
**Related:** `IMPLEMENTATION-PLAN.md` Phase 6, ad-manager design, `roadmap.md` backlog

## Purpose

Ship viewability-based impression + click tracking with **always-on external hooks** and
**opt-in first-party event storage** (dedicated table, 90-day retention cron). Weighted rotation
already exists; event counts make placement A/B comparison possible. Admin charts / GEO / CTR
auto-optimization / cache-safe client rotation stay on **`roadmap.md`** (not this release).

## Confirmed decisions

1. **Version:** **2.5.0**.
2. **Storage:** dedicated table via `dbDelta` (`{prefix}ad_placr_events`).
3. **Retention:** **90 days**; cron `ad_placr_analytics_cleanup` with a registered callback.
4. **Impressions:** client `IntersectionObserver` ≥50% visible, once per wrapper.
5. **Hooks always fire** from the track endpoint; table writes only when `analytics_enabled`.
6. **No PII** in table, URLs, or logs (no IP/UA/path stored).

## Architecture

```
Wrapper HTML (data-ad-id, data-placement-id)
  → assets/js/tracking.js (IO + click)
  → POST /wp-json/ad-placr/v1/track  (REST nonce)
  → Ad_Placr_Analytics::track()
       → do_action( 'ad_placr_impression'|'ad_placr_click', $ad_id, $ctx )  ALWAYS
       → INSERT row IFF analytics_enabled
```

### Table `{prefix}ad_placr_events`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `event_type` | VARCHAR(16) | `impression` \| `click` |
| `ad_id` | BIGINT UNSIGNED | |
| `placement_id` | BIGINT UNSIGNED | `0` when ad-only shortcode |
| `created_at` | DATETIME | UTC (`gmdate`) |

Indexes: `(created_at)`, `(ad_id, event_type)`, `(placement_id, event_type)`.

Created on activation + version bump guard (`ad_placr_db_version` or analytics schema version).

### Settings

- `analytics_enabled` (bool, default `false`) on Settings → Ad Placr.
- Fail-closed for storage: unset/false → no inserts.

### REST

- Namespace `ad-placr/v1`, route `track`, methods `POST`.
- Args: `event` (enum), `ad_id` (int), `placement_id` (int, optional default 0).
- `permission_callback`: `__return_true` + verify `X-WP-Nonce` / `_wpnonce` for
  `wp_rest` (same pattern as core front-end REST). No capability required (public).
- Validate IDs > 0 for `ad_id`; reject unknown `event`.

### Front-end JS

- Enqueue on `wp_enqueue_scripts` when not admin; localize `restUrl`, `nonce`.
- Observe `.ad-placr[data-ad-id]`; threshold 0.5; mark `data-ad-placr-impressed` after success.
- Click: capture on wrapper (once per navigation via `data-ad-placr-clicked`).

### Renderer

Extend wrapper with `data-ad-id` and `data-placement-id` (empty/0 omitted or `0`).

### Cron

- Schedule `daily` event `ad_placr_analytics_cleanup` on activation if not scheduled.
- Callback deletes `created_at < gmdate( …, time() - 90 days )`.
- Unschedule on deactivation optional; uninstall drops table when wiping plugin data.

### A/B

No experiment UI. Document: compare impression/click counts per `ad_id` within a placement’s
weighted list. Phase 7 list columns can surface totals.

## Backlog (explicitly deferred — see `roadmap.md`)

- Admin analytics charts / dashboards  
- GEO targeting  
- CTR-based rotation optimization  
- Full-page-cache-safe client-side rotation  
- Click-fraud / reCAPTCHA  

## Acceptance

1. Hooks fire even when storage is off; storage off → zero inserts (tested).
2. Storage on → one row per track call; cleanup removes rows older than 90 days (tested).
3. Cron hook has a registered callback.
4. No PII columns or logged request bodies with IP/UA.
5. PHPCS + PHPStan; version **2.5.0** + changelog.
