# Ad Placr — roadmap

> **Direction changed (2026-07-22).** Ad Placr is being rebuilt into a full ad manager on an
> **Ad ↔ Placement** model (reusable creatives + positioned, targeted, weighted placements). The
> authoritative plan is **`IMPLEMENTATION-PLAN.md`**; the approved design is
> **`docs/superpowers/specs/2026-07-22-ad-placr-ad-manager-design.md`**. This file is now just the
> longer-horizon backlog that sits *beyond* the v1 rebuild.

## In the v1 rebuild (see IMPLEMENTATION-PLAN.md)

Ad + Placement CPTs · canonical position registry (filterable) · weighted rotation (A-B-ready) ·
content-location + device targeting · native zero-build admin · migration of current footer/in-content
config · analytics (external hooks + opt-in first-party storage) · shortcode + widget.

## Backlog beyond v1 (Ad Inserter-informed, tiered)

Deferred deliberately (YAGNI). Each becomes its own spec when picked up.

### Tier 1 — near-term, low risk
- **Debug/visualization overlay** — highlight inserted blocks and available positions for admins.
- **Import / export** of ads + placements (JSON) for staging.
- **`ads.txt`** management from admin.
- **Custom / anti-adblock CSS class** naming option.
- **Gutenberg block** for manual placement (mirrors the shortcode).

### Tier 2 — targeting depth
- Taxonomy / category / tag / specific post ID / URL-pattern black + white lists.
- Visitor rules: logged-in vs guest, user role.
- Scheduling: start/end date, time, days-of-week, with fallback ad.

### Tier 3 — performance & delivery
- Lazy loading (load when visible) + interaction/scroll delay.
- Full-page-cache-safe rotation (client-side pick).
- More positions: comments (before/after/between), between blog posts, 404, RSS, AJAX-loaded content.

### Tier 4 — advanced / product-grade
- Consent / IAB TCF 2.0 gating (GDPR); insert on consent grant.
- Ad-block detection + fallback / message / replacement.
- GEO targeting (country/city; MaxMind / CloudFlare), cache-compatible.
- Advanced analytics: PDF / public reports, CTR-based rotation optimization, click-fraud / reCAPTCHA.
- CSS-selector insertion ("before/inside/after any HTML element") — successor to the paragraph regex.
- Sticky animations (fade/slide/zoom), parallax, background/skin ads.
- Multisite network defaults; WPML compatibility.

## References

- **Ad Inserter** feature set (inspiration only): <https://adinserter.pro/documentation/features>
- Keep `changelog.md` and version headers aligned with what actually ships.
