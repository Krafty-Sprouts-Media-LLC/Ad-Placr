# Ad Placr Unified Ad Model

**Date:** 2026-07-23
**Status:** Approved design
**Target release:** 2.7.0
**Supersedes:** `2026-07-22-ad-placr-ad-manager-design.md`

## Purpose

Replace the unreleased two-record Ad/Placement model with one user-facing and
one storage-level Ad record. An Ad contains everything required to decide what
to display, where to display it, and who should see it.

The product rule is:

> One Ad, one screen, everything together.

This matches the ordinary workflow used by Adsly and Ad Inserter while keeping
Ad Placr's stronger renderer, position registry, targeting, analytics, security,
and automated tests.

## Platform constraints

- WordPress 6.0 or newer.
- PHP 8.0 or newer.
- Native WordPress admin and zero-build JavaScript.
- No new runtime dependency or bundled third-party UI library.
- One bootstrap with hook-driven loading.

## Why the design changed

The local 2.x rebuild separated:

- an **Ad**, which stored only reusable ad code; and
- a **Placement**, which stored the display location, targeting, status, and a
  weighted list of Ads.

That normalized model supports shared ad code, but it makes the common task
needlessly indirect. A user must create two records, connect them, and keep two
statuses valid before anything displays. It also creates empty Placements,
unassigned Ads, confusing terminology, and more complicated duplication,
migration, shortcodes, analytics, and deletion.

The public Git remote still points to version 0.1.5. The two-record model exists
only in the 24 local rebuild commits and has never been published or tagged.
The model can therefore be corrected without maintaining public compatibility
with the unreleased structure.

## Approaches considered

### 1. One complete Ad record -- selected

Store the display location, display rules, code versions, and status on one
`ad_placr_ad` post. Users create and manage one record.

This is the simplest workflow and the smallest reliable data model.

### 2. Keep two records but hide them behind one screen -- rejected

A unified screen would reduce visible friction but retain hidden synchronization,
orphaning, deletion, export, and migration complexity.

### 3. Add a reusable creative library -- deferred

A shared library could update the same ad code across many display locations.
That is useful for larger ad operations, but it is not required for the core
Ad Placr workflow. Users can duplicate an Ad when they want the same code in a
different location. A linked library may be designed later as an optional
advanced feature.

## Product vocabulary

The normal interface is written for site owners, not developers or ad-server
operators.

| Do not show | Show instead |
|---|---|
| Creative | Ad code |
| Placement | Where should this ad appear? |
| Position taxonomy | Display location |
| Targeting | Where should this ad be shown? |
| Rotation | Show different ad versions |
| Variant | Ad version |
| Weight | How often should this version appear? |
| Inactive | Paused |
| Context predicate | Never expose |
| Canonical position key | Never expose |

Technical terms such as CPT, meta, hook, renderer, predicate, and taxonomy stay
inside code and developer documentation.

Every unfamiliar control receives short inline help. A user must not need to
read separate documentation to create a basic Ad.

## User model

There is one **Ads** menu and one **Add New Ad** action. There is no Placements
menu.

Each Ad contains:

1. Name.
2. Status: Active or Paused.
3. One display location.
4. Location-specific settings, such as paragraph number.
5. Where the Ad should be shown: page, visitor, device, URL, taxonomy, and
   schedule rules.
6. One or more ad-code versions.
7. Statistics.

A basic Ad has one code version. Additional versions are optional and hidden
until the user selects **Add another ad version**.

Duplicating an Ad creates an independent copy. Editing the original never
changes the duplicate.

## WordPress data model

### One custom post type

The only management post type is:

```text
ad_placr_ad
```

`ad_placr_placement` is retired and is not registered after the migration
window.

### Authoritative status

WordPress post status is the single status source:

- `publish` means Active.
- `draft` means Paused.
- `trash` means deleted but recoverable.
- Any other post status is treated as Paused for front-end rendering.

The old `_ad_placr_status` meta value is migration input only and is not an
authoritative second status.

### Ad meta

All meta keys remain class constants on `Ad_Placr_Ad`.

```text
_ad_placr_position   One canonical display-location key
_ad_placr_targeting  Display-rule array
_ad_placr_versions   Ordered ad-version array
_ad_placr_notes      Optional administrator notes
```

The existing canonical position keys remain unchanged so the position registry,
CSS classes, filters, and migration mappings stay stable.

### Ad versions

`_ad_placr_versions` stores an ordered array:

```php
array(
	array(
		'version_id'  => 'stable-uuid',
		'name'        => 'Version A',
		'code'        => '<script>...</script>',
		'mobile_code' => '',
		'weight'      => 1,
		'enabled'     => true,
	),
)
```

Rules:

- `version_id` is generated once and survives edits and reordering so analytics
  remain attached to the correct version.
- `name` is plain text and defaults to Version A, Version B, and so on.
- `code` and `mobile_code` contain privileged ad-network snippets.
- `weight` is a positive integer and defaults to `1`.
- `enabled` lets a user pause one version without pausing the whole Ad.
- Empty versions and disabled versions are excluded before selection.
- The old `_ad_placr_code` and `_ad_placr_mobile_code` values migrate into the
  first version and cease to be authoritative.

The interface displays the calculated traffic share beside each weight. Users
may enter `1` and `1` for an even split or `3` and `1` for a 75%/25% split
without manually maintaining a total of 100.

## Add/Edit Ad screen

The screen follows the order in which an ordinary user configures an Ad.

### 1. Ad name and status

- Name.
- Active or Paused.

### 2. Where should this ad appear?

- One display-location selector populated from `Ad_Placr_Positions`.
- Only controls relevant to the selected location are visible.
- Paragraph locations show the paragraph number.
- Manual locations show the shortcode or widget instructions.

### 3. Ad code

- Main ad code.
- Optional mobile ad code.
- **Add another ad version** reveals the version list and traffic-share
  controls.

Internally, even a single main code is stored as the first version. The user
does not need to understand that storage detail.

### 4. Where should this ad be shown?

- The default is Show everywhere.
- Optional controls cover post types, front page, blog index, archives, search,
  signed-in state, URL fragments, categories, tags, schedule, and device
  presentation supported by the renderer.
- Empty rule families fail open.

Device choices are Desktop, Tablet, and Mobile, with all three selected by
default. Visibility is enforced with responsive CSS rather than user-agent
sniffing so full-page caching remains safe. The main code serves desktop and
tablet plus mobile fallback; an optional mobile code replaces it on Mobile.
Filterable breakpoints define the three ranges, and an Ad is hidden in any
unselected range.

### 5. Statistics

- Impressions.
- Clicks.
- CTR.
- Aggregate totals are always shown.
- Per-version figures are shown when an Ad has more than one version.

### Ads list

The list columns are:

```text
Name | Display location | Status | Ad versions | Impressions | Clicks | CTR
```

Row actions include Edit, Duplicate, Activate/Pause, Trash, and Restore where
WordPress supports the action.

## Save validation

A Paused Ad may be saved while incomplete.

An Ad can become Active only when:

- it has a registered display location; and
- at least one enabled version contains universal or mobile ad code.

If activation validation fails, the Ad remains Paused and the interface shows a
specific correction:

- **Add your ad code before activating this Ad.**
- **Choose where this ad should appear before activating it.**

Unknown position keys fail closed on the front end and are visibly flagged in
the editor.

If another Active Ad uses the same automatic display location, the save
succeeds but a warning explains that both Ads may appear. Users who want only
one result should put the competing code snippets into one Ad as versions.

## Front-end data flow

For each automatic display location:

1. Query Active Ads assigned to that canonical position.
2. Process them in deterministic `menu_order ASC, ID ASC` order.
3. Check the Ad's display rules with the shared targeting gate.
4. Normalize versions and remove disabled or empty rows.
5. Render the only valid version, or choose one valid version by weight.
6. Use the mobile code at the configured CSS breakpoint when provided.
7. Output through the shared renderer.
8. Record the selected Ad ID and stable version ID for analytics.

If two Active Ads match the same automatic location, both render in the
deterministic order. The admin warning makes this consequence explicit.

Manual shortcode and widget paths use the same active, targeting, version
selection, rendering, and analytics pipeline. Paused, trashed, unknown,
incomplete, or non-matching Ads return an empty string.

## Failure behavior

- Invalid or incomplete Ads never break the page.
- If every version is empty or disabled, output is empty.
- Paused, expired, or non-matching Ads never display.
- Empty display rules mean Show everywhere.
- Invalid targeting data is normalized to safe defaults.
- Trash is used before permanent deletion.
- A shortcode for a missing or unavailable Ad returns an empty string.
- Analytics failure never prevents an Ad from rendering.

## Components

The implementation keeps focused classes while presenting one product concept.

| Component | Responsibility after unification |
|---|---|
| `Ad_Placr_Ad` | CPT, meta constants, normalization, status, versions, and position queries |
| `Ad_Placr_Positions` | Filterable canonical display-location registry |
| `Ad_Placr_Targeting` | One display-rule gate for every render path |
| `Ad_Placr_Renderer` | Select a valid version and build escaped wrapper/mobile output |
| `Ad_Placr_Frontend` | Bind automatic locations and request matching Ads |
| `Ad_Placr_Footer_Sticky` | Specialized sticky-footer presentation using Ad IDs |
| `Ad_Placr_In_Content` | Specialized paragraph insertion using Ad IDs |
| `Ad_Placr_Shortcode` | Render one Ad by ID through the shared pipeline |
| `Ad_Placr_Widget` | Select and render one Ad through the shared pipeline |
| `Ad_Placr_Admin` | One Ad editor, Ads list, validation, duplication, and warnings |
| `Ad_Placr_Analytics` | Aggregate Ad statistics and optional per-version statistics |
| `Ad_Placr_Migration` | Idempotent v0.1.5 and local two-record migration |
| `Ad_Placr_Placement` | Retired; removed from bootstrap after migration support is isolated |

The bootstrap remains single and hook-driven. Admin-only behavior remains
behind admin hooks.

## Analytics

Analytics identifies:

- the unified Ad post ID; and
- the stable version ID selected for the impression or click.

The existing aggregate Ad totals remain available. When several versions are
enabled, the admin also shows per-version impressions, clicks, and CTR.

External analytics actions remain available, but their documented context uses
`ad_id` and `version_id`; the obsolete Placement is not a required dimension.
First-party storage remains opt-in, bounded by retention, and must not collect
PII.

## Migration

Migration is versioned, idempotent, and non-destructive until verification is
complete.

### Public v0.1.5 settings

- Footer sticky settings create one complete Ad at `sticky_footer`.
- Every in-content slot creates one complete Ad at its before/after paragraph
  location.
- Universal and mobile code become Version A.
- Existing post-type and paragraph rules move onto the same Ad.

### Local unreleased two-record data

For each `ad_placr_placement` post:

1. Create one unified Ad.
2. Copy its title, position, targeting, and effective Active/Paused status.
3. Convert every referenced old Ad into a version.
4. Preserve the old weight.
5. Assign a stable version ID derived from the source Ad ID for idempotency.

If one old Ad is referenced by several Placements, its code is copied into each
resulting unified Ad. The new records are independent.

Old Placement posts, old creative-only Ads, old code meta, and old analytics
rows are retained during verification. A source-to-result migration map
prevents duplicates and supports audit. Cleanup is a separate explicit release
step after migration has been verified; migration itself does not permanently
delete source data.

## Security

- Management remains restricted to `manage_options`.
- Every write requires a nonce and capability check.
- Input is sanitized and output is escaped.
- Ad-network code remains the documented privileged raw-output exception.
- Users without `unfiltered_html` have code filtered through `wp_kses_post`.
- Dynamic SQL uses `$wpdb->prepare()`; core APIs remain preferred.
- Large or rarely read options are not autoloaded.

## Testing and verification

Automated coverage must prove:

- one Ad contains location, targeting, status, and versions;
- WordPress post status is the only active/paused authority;
- version normalization ignores disabled and empty rows;
- weighted selection uses only eligible versions;
- stable version IDs survive reordering;
- every registered automatic position has a render path;
- duplicate-position ordering is deterministic;
- empty display rules fail open;
- unknown positions and malformed active Ads fail closed;
- shortcode and widget paths use the same renderer;
- analytics attributes identify Ad and version;
- v0.1.5 migration is lossless and idempotent;
- local two-record migration preserves positions, rules, code, mobile code, and
  weights without duplicates.

Release verification must include:

```text
vendor/bin/phpunit
composer exec phpcs -- --standard=WordPress includes/ ad-placr.php
composer exec phpstan -- analyse
```

Manual verification must include:

- create, edit, duplicate, activate, pause, trash, restore, and permanently
  delete an Ad;
- save and reload every field;
- render every automatic display location in a real request;
- render shortcode and widget Ads;
- verify mobile switching;
- verify one-version and multi-version Ads;
- verify impressions, clicks, and CTR;
- inspect `WP_DEBUG_LOG` for notices and warnings.

## Preserved work

The following 2.x work remains valuable and is adapted rather than discarded:

- canonical position registry;
- automatic front-end dispatcher;
- specialized sticky and paragraph insertion;
- shared renderer and mobile wrapper behavior;
- targeting rules;
- shortcode and widget entry points;
- analytics storage, external hooks, tracking, and retention;
- security controls and automated tests.

The replacement focuses on the data model and user workflow. It does not
rewrite unrelated working subsystems.

## Non-goals

This redesign does not add:

- a reusable linked creative library;
- campaigns or advertiser accounts;
- cross-site creative synchronization;
- a drag-and-drop visual ad builder;
- new display locations unrelated to model unification;
- new targeting families unrelated to model unification.

Those ideas require separate designs after the unified model ships.

## Definition of done

Ad Placr exposes one Ads menu and one complete Ad record. No normal workflow,
shortcode, widget, renderer, analytics event, or migration requires a Placement.
One-version Ads remain simple; multi-version rotation remains available inside
the same editor. Existing public settings and local rebuild data migrate without
loss or duplication. Automated checks pass, admin values round-trip, every
display location is exercised in a real request, and the user-facing language
contains no unexplained developer or ad-server terminology.
