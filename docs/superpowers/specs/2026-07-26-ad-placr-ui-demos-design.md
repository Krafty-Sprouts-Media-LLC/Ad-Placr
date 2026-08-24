# Ad Placr UI/UX Interactive HTML Demos Design Spec

**Date**: 2026-07-26  
**Status**: Approved  
**Author**: Krafty Sprouts Media LLC / Antigravity  
**Target Output**: 3 standalone interactive HTML files in `/demo/` directory  

---

## 1. Overview & Objectives

Ad Placr is being rebuilt as a modern, unified WordPress ad management plugin. To validate and refine the new user interface and experience before frontend integration, we are creating three high-fidelity, standalone, fully interactive HTML demos in the `/demo` folder.

### Key Constraints & Requirements
- **Light Mode Only**: 100% crisp light theme using WordPress admin colors (`#2271b1`, `#f0f0f1`, `#ffffff`, `#1d2327`) with modern micro-animations and Apple-inspired translucency/materials where appropriate.
- **No External Build Tools**: Pure HTML5, CSS3 (vanilla CSS, CSS variables, grid, flexbox, custom scrollbars, transitions), and clean vanilla JavaScript.
- **Maximum Interactivity**: Functional UI controls, live calculations, state changes, tab switches, visual pickers, modals, search/filtering, and toast notifications.
- **File Structure**:
  - `demo/01-dashboard.html`
  - `demo/02-ad-editor.html`
  - `demo/03-settings-analytics.html`

---

## 2. Interactive HTML Demos Details

### Demo 1: `demo/01-dashboard.html` — Ads Management Dashboard
- **Header & Metrics**:
  - Time range dropdown (7 Days, 30 Days, 90 Days) that dynamically updates metric card values (Impressions, Clicks, Avg CTR, Revenue/RPM estimate).
  - Search bar with instant live-filtering of ad rows.
  - Placement filter pills (All, In-Content, Sticky Footer, Header/Footer, Sidebar).
- **Interactive List Table**:
  - Row status toggle switches (Active / Paused) with live status pill updates.
  - Action buttons: "Edit", "Quick Code Preview" (opens overlay modal), "Copy Shortcode" (copies `[ad_placr id="..."]` with a floating toast message).
  - Metric badges: CTR badges color-coded by performance (green > 2%, amber 1-2%, gray < 1%).
- **Interactive Quick Preview Overlay**:
  - Modal lightbox showing the rendered ad HTML/JS code and mobile fallback preview.

### Demo 2: `demo/02-ad-editor.html` — Single Ad Unified Editor
- **Header & Title Bar**:
  - Live status toggle (Active/Paused), Title input, and "Save Changes" floating indicator.
- **Visual Position Selector**:
  - Interactive placement grid alongside a mini website wireframe mockup.
  - Clicking a placement key (`in_content_after_paragraph`, `sticky_footer`, `before_header`, `sidebar_widget`, etc.) highlights the position visually on the website wireframe and updates canonical position settings live.
- **Code Versions & A/B Testing**:
  - Multi-version tab manager (Version A, Version B, + Add Version button).
  - Interactive weight sliders (`0% - 100%`) with automatic weight balancing and percentage calculation.
  - "Mobile Snippet Override" checkbox toggle revealing/hiding mobile-specific ad code textareas.
- **Targeting & Display Rules Accordion**:
  - Collapsible cards for Content Rules (Post types, Specific categories), User Rules (Logged in / Logged out), and Device Rules (Desktop / Mobile / Tablet).
  - Dynamic summary badge counting active targeting conditions (e.g. "3 Active Rules").
- **Live Ad Preview Sandbox**:
  - Real-time preview iframe/box rendering the currently selected code version with Desktop/Mobile view toggle.
- **Sidebar**:
  - Analytics Widget: Mini CTR chart and 7-day trend.
  - Private Admin Notes: Textarea with auto-saving timestamp indicator.

### Demo 3: `demo/03-settings-analytics.html` — Settings & System Diagnostic
- **Global Settings Panel**:
  - Opt-in Analytics toggle switch with live DB status card.
  - Data Retention Dropdown (30, 60, 90, 180, 365 Days) with estimated DB storage calculator.
  - Mobile Breakpoint input slider (default `782px`) with responsive preview diagram.
  - Default rotation weight strategy radio options.
- **Interactive System Diagnostics Sweep**:
  - "Run Diagnostics" button triggering an animated progress bar checking:
    - WordPress & PHP version compatibility
    - Database table schema & analytics indexes
    - Update checker status (`main` branch)
    - Active hooks & placement filters
  - Interactive log inspector for detailed diagnostic logs.
- **Data Maintenance & Purge Action**:
  - "Purge Analytics Data" button opening a confirmation modal requiring typed confirmation before resetting statistics.

---

## 3. Verification & Compliance Checklist

- [x] No dark mode used; strict light theme.
- [x] All 3 `.html` files located in `/demo/`.
- [x] Self-contained files (styles & scripts included for instant browser viewing).
- [x] PHPCS / WordPress coding standards aligned.
- [x] Tested across resolution breakpoints (mobile, tablet, desktop).
