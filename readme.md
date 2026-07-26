# Ad Placr

WordPress ad manager by [Krafty Sprouts Media LLC](https://kraftysprouts.com).

Each **Ad** contains everything needed to display it: location, status, display rules, one or more
weighted code versions, optional mobile code, and statistics.

## Requirements

- WordPress **6.0+**
- PHP **8.0+**

## Features

- One-screen Ad editor with clear Active and Paused status
- Main and optional mobile ad code, plus weighted code versions
- Display rules for page context, content type, visitors, schedules, URL paths, categories, and tags
- Automatic locations including sticky footer, in-content paragraphs, header/footer, side rails, and
  front-page, blog, and archive positions
- Manual display with `[ad_placr ad="123"]` or the Ad Placr sidebar widget
- Optional first-party impression and click statistics with 90-day retention
- GitHub updates via bundled Plugin Update Checker

## Admin

1. Open **Ads → Add New**.
2. Choose where the Ad should appear.
3. Paste the ad code and choose any display rules.
4. Save it as Paused while preparing it, or Active when it is ready.
5. Optionally open **Ads → Settings** to enable first-party statistics.

## Docs in this repo

| File | Purpose |
|---|---|
| [`AGENTS.md`](AGENTS.md) | Conventions for contributors / AI agents |
| [`docs/superpowers/specs/2026-07-23-ad-placr-unified-ad-model-design.md`](docs/superpowers/specs/2026-07-23-ad-placr-unified-ad-model-design.md) | Approved unified Ad design |
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
