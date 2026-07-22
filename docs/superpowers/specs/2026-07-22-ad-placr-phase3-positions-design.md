# Ad Placr — New Automatic Positions & Frontend Dispatcher (Design Spec)

**Date:** 2026-07-22  
**Status:** Approved  
**Version target:** **2.2.0**  
**Related:** `IMPLEMENTATION-PLAN.md` Phase 3, renderer design (2.1.0), foundations (2.0.0)

## Purpose

Wire every **automatic** position in the canonical registry to a real render path so Adsly’s
orphan bug cannot recur: if a key appears in the picker, it can render. Shortcode / widget /
block stay Phase 4.

## Confirmed decisions

1. **Version:** **2.2.0**.
2. **Scope:** all automatic registry positions not already live (not `sticky_footer`, not
   `in_content_*`, not `sidebar_widget` / `manual_shortcode` / `manual_block`).
3. **Architecture:** registry-driven `Ad_Placr_Frontend` dispatcher (approach 1).
4. **Specialized handlers remain:** `Ad_Placr_Footer_Sticky`, `Ad_Placr_In_Content`.
5. **Invariant (tested):** automatic renderable keys == Frontend-registered keys ∪ specialized keys.

## Architecture

```
Ad_Placr_Positions::defaults()
  descriptor += hook, priority, render_mode ('echo'|'the_content'), handler ('frontend'|'special')

Ad_Placr_Frontend::register()
  foreach position where handler === 'frontend':
    add_action / add_filter( hook, priority )
      → context_matches( descriptor.context )
      → query placements for key
      → placement active + targeting (reuse 2.1.0 helpers)
      → Renderer::render_placement (first non-empty HTML)
      → echo or prepend/append to content
```

### Registry descriptor (extended)

| Field | Meaning |
|---|---|
| `label`, `group`, `context` | Existing (2.0.0) |
| `hook` | WordPress hook name (e.g. `wp_body_open`, `the_content`, `wp_footer`) |
| `priority` | Hook priority (int) |
| `render_mode` | `echo` (action) or `content` (filter string in/out) |
| `handler` | `frontend` \| `special` \| `manual` |

`ad_placr_positions` filter may add/override these fields; Frontend ignores unknown hooks safely.

### Position → hook map (2.2.0)

| Key | Hook | Priority | Mode | Context check |
|---|---|---|---|---|
| `before_post_content` | `the_content` | 11 | content prepend | singular + main query loop |
| `after_post_content` | `the_content` | 13 | content append | same |
| `before_header` | `wp_body_open` | 5 | echo | always (global) |
| `after_header` | `wp_body_open` | 20 | echo | global *(theme caveat: true “after header” needs theme support; documented)* |
| `before_footer` | `get_footer` | 5 | echo | global |
| `after_footer` | `wp_footer` | 20 | echo | global *(not 100 — sticky footer owns 100)* |
| `sticky_left_rail` | `wp_footer` | 99 | echo | global + rail CSS |
| `sticky_right_rail` | `wp_footer` | 99 | echo | global + rail CSS |
| `front_page_top` | `loop_start` | 5 | echo | `is_front_page()` + main query |
| `front_page_bottom` | `wp_footer` | 15 | echo | `is_front_page()` |
| `blog_index_top` | `loop_start` | 5 | echo | `is_home() && ! is_front_page()` + main query |
| `blog_index_bottom` | `wp_footer` | 15 | echo | blog index |
| `archive_top` | `loop_start` | 5 | echo | `is_archive()` + main query |
| `archive_bottom` | `wp_footer` | 15 | echo | `is_archive()` |

**Special (unchanged):** `sticky_footer` → Footer_Sticky; `in_content_*` → In_Content.  
**Manual (Phase 4):** `sidebar_widget`, `manual_shortcode`, `manual_block`.

### Targeting / output

Reuse 2.1.0: `Placement::is_active`, `get_targeting`, `targeting_matches_singular`, contexts `all` /
`singular`. For listing contexts (`front_page`, `blog_index`, `archive`), Frontend applies the
registry context predicate first; placement targeting `all` still allowed; `singular` placements
skip on listing views.

Wrapper: `dom_id` = `ad-placr-pos-{key}` (or `{key}-{placement_id}` if multiple — **2.2.0 ships first
non-empty only** per position per request, same as sticky footer).

Modifier class: `ad-placr--pos-{key}` plus sticky rail modifiers for left/right.

### Assets

- New `assets/css/rails.css` for left/right sticky rails (minimal fixed positioning).
- Enqueue when a rail placement would display.

### Acceptance

1. Every `handler=frontend` key registers exactly one hook callback.
2. Unit test: `automatic_keys()` sorted == specialized ∪ frontend keys.
3. Creating a Placement with `before_post_content` injects HTML around post content on singular.
4. Front-page / blog / archive positions respect their context predicates (no bleed).
5. Version **2.2.0** + changelog.

## Out of scope

Phase 4 manual placements; Phase 5 full targeting; analytics; theme-specific header injection
beyond `wp_body_open` documentation.

## Risks

- Themes without `wp_body_open` → `before_header` / `after_header` silent no-op (document).
- `loop_start` may fire for secondary loops — guard with `is_main_query()`.
- Multiple placements per position: first-wins (document; multi later).
