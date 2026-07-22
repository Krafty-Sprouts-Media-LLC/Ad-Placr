# Ad Placr

WordPress ad manager by [Krafty Sprouts Media LLC](https://kraftysprouts.com).

Reusable **Ads** (creatives) and **Placements** (position + targeting + weighted ad list) with automatic positions, shortcode/widget embeds, targeting rules, and opt-in analytics.

## Requirements

- WordPress **6.0+**
- PHP **8.0+**

## Features

- **Ads** CPT — universal + optional mobile ad code, active/inactive status
- **Placements** CPT — canonical position, weighted rotation, targeting (contexts, post types, users, schedule, URL, categories/tags)
- Automatic positions — sticky footer, in-content paragraphs, header/footer, rails, front/blog/archive tops & bottoms
- Manual — `[ad_placr placement="ID"]` / `[ad_placr ad="ID"]`, sidebar widget
- Analytics — always-on `ad_placr_impression` / `ad_placr_click` hooks; optional first-party event storage (90-day retention)
- GitHub updates via bundled Plugin Update Checker

## Admin

1. **Ads** — create creatives (Ad Placr → Ads)
2. **Placements** — pick position, attach weighted ads, set targeting
3. **Settings → Ad Placr** — disclosure text, analytics storage toggle, legacy option fields (reference / re-migration)

## Docs in this repo

| File | Purpose |
|---|---|
| [`AGENTS.md`](AGENTS.md) | Conventions for contributors / AI agents |
| [`IMPLEMENTATION-PLAN.md`](IMPLEMENTATION-PLAN.md) | Phased rebuild roadmap |
| [`development.md`](development.md) | Local layout & coding notes |
| [`roadmap.md`](roadmap.md) | Backlog beyond v1 |
| [`changelog.md`](changelog.md) | Release history |
| [`readme.txt`](readme.txt) | WordPress.org-style readme |

## Development

```bash
composer install
composer test
composer exec phpcs -- --standard=WordPress includes/ ad-placr.php
composer exec phpstan -- analyse --memory-limit=2G
```

Composer is **dev-only** — activation never depends on `vendor/`.

## License

GPL-2.0-or-later
