# Ad Placr UI/UX HTML Demos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create 3 interactive, standalone Light Mode HTML demos in `/demo/` for Ad Placr UI/UX.

**Architecture:** Standalone HTML5 pages with embedded CSS3 (flexbox, CSS grid, custom variables, smooth transitions) and vanilla JS for state management and interactive micro-UI.

**Tech Stack:** HTML5, Vanilla CSS3, Vanilla JS (ES6+), SVG icons.

## Global Constraints

- **Theme**: 100% Light Mode ONLY.
- **Directory**: All files in `/demo/` (`demo/01-dashboard.html`, `demo/02-ad-editor.html`, `demo/03-settings-analytics.html`).
- **Dependencies**: Self-contained single-file HTML (inline CSS and JS per demo) for instant zero-dependency preview in any web browser.
- **WordPress Admin Design System**: Use WP admin palette (`#2271b1`, `#f0f0f1`, `#ffffff`, `#1d2327`, `#00a32a`, `#dba617`) + Apple-inspired micro-animations.

---

### Task 1: Create `demo/01-dashboard.html` (Ads Management Dashboard Demo)

**Files:**
- Create: `demo/01-dashboard.html`

**Interfaces:**
- Interactive elements: Time Range Dropdown, Live Search Input, Placement Filter Buttons, Active/Paused Status Toggles, Quick Code Overlay Modal, Shortcode Copy Toast.

- [ ] **Step 1: Create directory `/demo` if it doesn't exist and scaffold `demo/01-dashboard.html` with basic structure, header, navigation, and summary cards**
- [ ] **Step 2: Add interactive Ad List Table with status toggles, position taxonomy tags, CTR color-coded badges, and action dropdowns**
- [ ] **Step 3: Implement JavaScript for live table search filtering, time-range metric updates, quick preview modal open/close, and toast notification on copy shortcode**
- [ ] **Step 4: Verify `demo/01-dashboard.html` by checking all interactions and visual rendering**

---

### Task 2: Create `demo/02-ad-editor.html` (Single Ad Unified Editor Demo)

**Files:**
- Create: `demo/02-ad-editor.html`

**Interfaces:**
- Interactive elements: Title & Status Header, Visual Placement Picker Grid & Wireframe Highlights, Multi-Version Tab Manager, Live Weight Sliders, Mobile Code Override Toggle, Targeting Accordion, Sandbox Ad Preview Box, Admin Notes.

- [ ] **Step 1: Scaffold `demo/02-ad-editor.html` with WP admin standard two-column editor layout (Main Column + Sidebar)**
- [ ] **Step 2: Implement Visual Placement Selector section with canonical keys and interactive wireframe highlighting**
- [ ] **Step 3: Implement A/B Weighted Code Versions card manager with live sliders, auto-percentage rebalancing, and mobile snippet toggle**
- [ ] **Step 4: Implement Display Rules / Targeting Accordion and Live Ad Sandbox Preview Box**
- [ ] **Step 5: Implement Sidebar widgets (Status box, mini analytics chart, admin private notes)**
- [ ] **Step 6: Add vanilla JS event listeners connecting visual placement clicks, slider movements, preview updates, and accordion expansion**
- [ ] **Step 7: Verify `demo/02-ad-editor.html` functionality in browser**

---

### Task 3: Create `demo/03-settings-analytics.html` (Plugin Settings & System Diagnostics Demo)

**Files:**
- Create: `demo/03-settings-analytics.html`

**Interfaces:**
- Interactive elements: Analytics Opt-in Toggle, Data Retention Slider, Mobile Breakpoint Slider, Interactive Diagnostics Runner with Progress Bar, System Logs Modal, Data Purge Modal with Confirmation Input.

- [ ] **Step 1: Scaffold `demo/03-settings-analytics.html` with tabbed settings interface (General & Analytics, System Diagnostics, Data Maintenance)**
- [ ] **Step 2: Build General & Analytics settings form with live switches, retention calculator, and breakpoint slider preview**
- [ ] **Step 3: Build System Diagnostics runner component with animated test step execution (PHP, WP, DB, PUC, Hooks)**
- [ ] **Step 4: Build Data Maintenance section with Purge Analytics confirmation modal**
- [ ] **Step 5: Add JS logic for diagnostic simulation, storage calculations, and purge modal validation**
- [ ] **Step 6: Verify `demo/03-settings-analytics.html` functionality**

---

### Task 4: Verify All Demos & Documentation

**Files:**
- Check: `demo/01-dashboard.html`, `demo/02-ad-editor.html`, `demo/03-settings-analytics.html`
- Update: `changelog.md`

- [ ] **Step 1: Perform full cross-browser and light mode UI audit of all 3 HTML files**
- [ ] **Step 2: Update `changelog.md` following project versioning and date rules**
