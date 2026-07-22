# Ad Placr Phase 6 Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship **2.5.0** — viewability tracking via REST + always-on hooks + opt-in `{prefix}ad_placr_events` table with 90-day retention cron.

**Architecture:** `Ad_Placr_Analytics::track()` always fires actions; inserts only when `analytics_enabled`. `Ad_Placr_Rest` registers `POST /ad-placr/v1/track`. Vanilla `assets/js/tracking.js` + Renderer `data-ad-id` / `data-placement-id`.

**Tech Stack:** PHP 8.0+, WP 6.0+, PHPUnit, WPCS. No new runtime deps.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-22-ad-placr-phase6-analytics-design.md`
- Backlog deferrals listed in `roadmap.md` (charts, GEO, CTR opt, cache-safe rotation)
- Version **2.5.0**; `@since 2.5.0` on new APIs; no PII

## Tasks

1. TDD Analytics pure helpers + track/insert/cleanup (Brain Monkey for `$wpdb` / options)
2. REST route + Renderer data attrs + tracking JS + enqueue
3. Settings toggle + activation dbDelta/cron + uninstall table drop
4. Version/changelog/verify/commit
