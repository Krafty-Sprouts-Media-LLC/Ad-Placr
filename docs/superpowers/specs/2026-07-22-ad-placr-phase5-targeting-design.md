# Ad Placr — Targeting & Rules (Design Spec)

**Date:** 2026-07-22  
**Status:** Approved  
**Version target:** **2.4.0**  
**Related:** `IMPLEMENTATION-PLAN.md` Phase 5, ad-manager design, Phase 4 manual placement

## Purpose

Introduce one Placement-scoped targeting gate — `Ad_Placr_Targeting::should_display()` —
used by every render path (Frontend, footer sticky, in-content, shortcode, widget). Expand the
existing targeting blob beyond `contexts` / `post_types` without inventing parallel meta keys.

## Confirmed decisions

1. **Version:** **2.4.0**.
2. **Rules live on Placement only** (not on Ad). Ads remain creatives; optional Ad `META_DEVICES`
   is unused by the server gate in this phase.
3. **Fail-open for empty/missing rules** — missing targeting blob or empty `contexts` → show
   (treat as `all`). Explicit deny shapes still hide (e.g. `singular` with empty `post_types`).
4. **No server-side device gate** — device presentation stays CSS dual-slot / breakpoint only
   (AGENTS.md: do not gate core logic on UA sniffing).
5. **Architecture:** new `Ad_Placr_Targeting` class; pure matchers + injected request context.
6. **Manual embeds respect targeting** — shortcode/widget call the same gate (no force-bypass in 2.4.0).

## Architecture

```
render path
  → build $ctx (view, post_type, user_state, url_path, term ids, now)
  → Ad_Placr_Targeting::should_display( $placement_id, $ctx )
       → is_active(placement)
       → match contexts / post_types / user / schedule / url / taxonomies
  → Renderer::render_placement | render_ad
```

### Request context `$ctx`

Built at each call site (helpers may wrap WP conditionals). Matchers receive only `$ctx` +
targeting blob — unit-testable without bootstrapping WordPress query state.

| Key | Type | Notes |
|---|---|---|
| `view` | string | `singular` \| `front_page` \| `blog_index` \| `archive` \| `search` \| `other` |
| `post_type` | string | Current singular type; empty when not singular |
| `is_singular` | bool | |
| `user_state` | string | `logged_in` \| `guest` |
| `url_path` | string | Path only (no query string), leading `/` |
| `category_ids` | int[] | Singular post categories; empty otherwise |
| `tag_ids` | int[] | Singular post tags; empty otherwise |
| `now` | int | Unix timestamp (site-local interpretation for schedule strings) |

### Targeting blob (`Ad_Placr_Placement::META_TARGETING`)

Extend the existing array. Do **not** rename existing keys (`contexts`, `post_types`, `paragraph`,
`slot_id`).

| Key | Meaning | Empty / missing |
|---|---|---|
| `contexts` | Allow-list: `all`, `singular`, `front_page`, `blog_index`, `archive`, `search` | Fail-open → treat as `all` |
| `post_types` | Allow-list when evaluating singular | If `singular` in contexts and list empty → **hide** (existing) |
| `user` | `any` \| `logged_in` \| `guest` | `any` |
| `schedule` | `{ start?: string, end?: string }` MySQL-style or `strtotime`-parseable, site TZ | No schedule limit |
| `url_contains` | string[] needles; OR match against `url_path` | No URL filter |
| `include_categories` | int[] term IDs; OR match on singular | No category filter |
| `include_tags` | int[] term IDs; OR match on singular | No tag filter |

**Combination:** AND across rule families; OR within a multi-value list.

`contexts` containing `all` short-circuits location matching to true (post_types / taxonomy /
URL still apply when those keys are non-empty — **except** when only `all` is set and other
families are empty → show). Clarification:

- Location family = `contexts` (+ `post_types` when the active view is singular).
- If `contexts` empty or contains `all` → location family passes.
- If `contexts` is a non-empty list without `all` → `view` must be in the list (map
  `singular` view to context `singular`, etc.).
- When location passes via `singular` (or `all` on a singular view), `post_types` allow-list
  applies only if non-empty; empty `post_types` with an explicit `singular`-only context
  (no `all`) → hide (preserve 2.1.0 behavior).

### `should_display()` contract

```php
Ad_Placr_Targeting::should_display( int $placement_id, array $ctx ): bool
```

Returns `false` when:

1. Placement is not active (`Ad_Placr_Placement::is_active`), or
2. Any rule family fails.

Returns `true` when active and every present rule family passes (fail-open for absent families).

### Call-site migration

Remove / thin private `placement_should_display` in:

- `Ad_Placr_Frontend`
- `Ad_Placr_Footer_Sticky`
- `Ad_Placr_In_Content`

Shortcode (placement attr) and Widget call `should_display` before `render_placement`.
Shortcode `ad` attr has no Placement — skip Placement targeting (Ad `is_active` only via Renderer).

### Admin (minimal)

Placement edit meta box: contexts checkboxes, post types, user select, schedule start/end,
URL contains (textarea, one needle per line), category/tag ID lists (comma-separated ints for v1 —
keep UI simple; Phase 7 can polish pickers).

Capability: `manage_options`. Nonce on save. Sanitize into the targeting blob; write only via
`META_TARGETING` constant.

### Filters

```php
apply_filters( 'ad_placr_targeting_should_display', $allowed, $placement_id, $ctx, $targeting );
```

Fired after core evaluation so integrators can force show/hide.

## Acceptance

1. Unit tests cover the rule matrix (contexts, post_types, user, schedule, url, taxonomies,
   AND/OR, fail-open defaults).
2. Every automatic + manual Placement render path calls `should_display` (grep/invariant test or
   shared helper).
3. Fail-open documented in this spec + changelog Notes.
4. No UA-based device gating in `should_display`.
5. PHPCS + PHPStan clean; version **2.4.0** + changelog.

## Out of scope

UA/soft device targeting; GEO; Ad-level rules; analytics; Gutenberg block; rich term pickers
(Phase 7 polish).

## Risks

- Page caches may serve logged-in vs guest HTML interchangeably if a full-page cache ignores
  cookies — document; do not invent cookie-vary in 2.4.0.
- Schedule TZ: parse with `wp_timezone()` when available; tests inject `now`.
- Category/tag ID raw inputs are easy to mistype — acceptable for v1 admin; validate ints only.
