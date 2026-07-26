# Ad Placr — roadmap

> **Current direction (2026-07-26).** Ad Placr uses one complete Ad record. Each Ad owns its display
> location, rules, Active/Paused status, weighted code versions, mobile code, and statistics. The
> approved design is `docs/superpowers/specs/2026-07-23-ad-placr-unified-ad-model-design.md`.

The 2.7.0 unified model is the foundation. The items below are deliberately deferred and each needs
its own design before implementation.

## Near-term, low risk

- Debug overlay that highlights inserted Ads and available display locations for administrators.
- Import/export of Ads as JSON for staging and backup.
- `ads.txt` management.
- Custom or anti-adblock wrapper-class setting.
- Gutenberg block for an Ad whose display location is **Manual block**.
- Statistics charts beyond the current aggregate and per-version list figures.

## Display-rule depth

- Specific post-ID allow and deny lists.
- User-role rules.
- Days-of-week and time-of-day schedules.
- Fallback Ad when a schedule or visitor rule does not match.

## Performance and delivery

- Lazy loading when an Ad becomes visible.
- Interaction or scroll delay.
- Full-page-cache-safe client-side version selection.
- Additional display locations: comments, between blog posts, 404 pages, RSS, and AJAX-loaded
  content.

## Advanced features

- Consent and IAB TCF gating.
- Ad-block detection with fallback messages or replacement Ads.
- Cache-compatible country/city rules.
- Public or PDF statistics reports, click-fraud protection, and carefully reviewed CTR optimization.
- CSS-selector insertion before, inside, or after a chosen HTML element.
- Optional sticky animations and background/skin Ads.
- Multisite defaults and multilingual compatibility.

## References

- Ad Inserter feature set, for inspiration only:
  <https://adinserter.pro/documentation/features>
- Keep `changelog.md` and all version declarations aligned with what actually ships.
