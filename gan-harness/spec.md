# Spec — YSLEP Service Ledger UI redesign & relayout (GAN design loop)

## The request (source of truth)

Redesign and relayout the frontend for:
1. **Better user experience** — clearer hierarchy, less scrolling, information where the eye lands.
2. **Fewer clicks per navigation** — every key journey has a click budget (below); no dead-end pages.
3. **A genuinely useful business-intelligence overview dashboard** — real aggregates and trends from
   real data, not decorative stat cards.
4. **Zero AI slop** (user's explicit words) — no generic patterns, no fake data, no boilerplate copy.

**Chosen visual world (user decision): deepen the paper-ledger world.** Keep the warm paper/ink
identity (cream paper, ink brown, rust accent, Fraunces serif + Manrope) and the ledger metaphor,
but execute it deeper and with far better information design: ruled ledger rows, stamped statuses,
marginalia-style charts, typographic hierarchy that means something. This is a *deepening*, not a
re-skin and not a generic analyst dashboard.

## Product truth (verified from code, not assumed)

- Laravel 13 (`^13.0`), PHP 8.4, Blade templates + Alpine.js 3 (`^3.16`) + Tailwind CSS v4
  (`^4.0`, `@tailwindcss/vite`). **No Livewire, no Filament, no JS framework.**
- Single-user local tool; routes have no auth (do not add any).
- Three live indexes of service entries, two-way synced with an Obsidian markdown vault:
  - `FormationEntry`: served_on, academic_year, cycle_code, module_code, title, time_start, time_end
  - `SocialApostolateEntry`: served_on, academic_year, about, role_in_activity, time_start, time_end
  - `ParishInvolvementEntry`: served_on, academic_year, role_in_activity, time_start, time_end
  - Duration is derived from time_start/time_end (`HasDurationAttributes`; `duration_label`,
    `total_minutes` aggregates already exist).
- `ReportGroup` (saved reports) lock entries; `AcademicYearSnapshot` archives reports per academic
  year. 13 reports, 4 snapshots exist.
- **Real dev data** (database `yslep`, PostgreSQL, `php artisan serve` on port 8000):
  13 formation / 36 social / 62 parish entries, spanning **2025-08-01 … 2026-08-29** (~13 months),
  13 saved reports, 4 snapshots. `academic_year` is largely unset on live entries.
- Pages: dashboard `/` (DashboardController), per-type ledger `/indexes/{type}` (IndexPageController,
  supports `?scope=` + `?report=`), reports list/detail `/reports[/{group}]`, snapshots
  `/academic-year-snapshots[/{snapshot}]`. Entry create/edit via `<dialog>` modals
  (`x-entry-modal`, dispatched `quick-add-open`), docked `x-quick-add-bar`, save-report builder
  partial `partials.save-group-builder`.
- Current design tokens live in `resources/css/app.css` (`--ledger-*` variables, `@theme` radii,
  Fraunces/Manrope). The "paper-panel / section-kicker / form-label / primary-button" vocabulary is
  the incumbent component language.

## Goals for the BI dashboard (`/`)

The dashboard must answer, at a glance and without clicking:
1. How much have we served in total, and **how is the trend moving** (this month vs last, busiest
   month, recent trajectory)?
2. **Hours per month over time, split by the three indexes** — a real stacked/grouped bar or area
   chart built from server-computed monthly aggregates (PostgreSQL `date_trunc('month', served_on)`).
   Every bar/point must expose its real value (tooltip on hover/focus AND an accessible data table).
3. **Coverage & gaps** — which months in the ledger period have zero entries (a real operational
   signal for a chronicler).
4. **Assignment funnel** — unassigned (ready to save) vs locked-in-report, per index and total, with
   the existing "Save a Report" flow reachable in one click.
5. **Academic-year lens** — totals grouped by academic_year when set, with an explicit, honest
   "not set" state. **Never infer or fabricate an academic year** (project rule).
6. Per-index share of total hours + average hours/entry + record counts, compactly.
7. **Drill-in within the click budget** (below). Chart elements and rows link to the filtered ledger.

Backend changes are allowed **only** as presentation support: extend `DashboardController` (or a
small dedicated query/aggregation class under `app/`) with read-only aggregate queries, and allow
an optional `month=YYYY-MM` filter on `indexes.show`. **No schema changes, no migrations, no
destructive SQL, no writes outside the app's own sync flow.**

## Click budgets (measured from the rendered DOM, not impressions)

| Journey | Budget |
|---|---|
| Dashboard → any index ledger | 1 click |
| Dashboard → a specific month's entries for one index | 1 click (chart drill-in) |
| Dashboard → open latest saved report | 1 click |
| Any page → add an entry of any type | ≤ 2 actions (quick-add bar) |
| Any page → switch to another top-level section | 1 click, always reachable (sticky/compact nav) |
| Ledger → see only unsaved (assignable) entries | 1 click (scope switcher) |
| Ledger row → edit that entry | ≤ 2 clicks |
| Report → its source/live entries or back to reports list | 1 click |
| Any report/archive mention anywhere → that report/archive | 1 click (chips/cards are links) |

Every page must have a clear "what do I do next" primary action; no dead ends.

## Hard constraints

1. **No new dependencies** — no chart libraries, no icon packages, no Alpine plugins beyond what
   `package.json` has (alpinejs + tailwind + vite + axios only). Charts are hand-built (inline SVG
   via Blade/Alpine, or CSS) — that is part of the craft. Existing Google Fonts stay.
2. **Preserve every route, form action, CSRF-protected endpoint, event name (`quick-add-open`,
   `entry-modal:*` etc.), and JS component contract** used across Blade/JS. Server-rendered Blade;
   keep `@extends('layouts.app')` structure and `@vite` wiring.
3. **`.ai/rules`** (binding):
   - `app.md`: academic year is optional everywhere; never infer an archive year, never create
     snapshots implicitly; snapshot creation stays explicit.
   - `views.md`: verify UI from the **DOM** (textContent, computed styles, console) in a real
     browser — screenshots alone are not evidence.
   - `css.md`: every component class defined **exactly once** in `app.css` (grep for duplicates
     before editing; the file historically had layered+unlayered twins that silently shadowed).
   - `tests.md`: `php artisan test` runs **serially**, only one process, shared `ysleep_testing` DB.
4. **Accessibility is not optional**: skip-to-content stays; visible focus rings everywhere;
   `prefers-reduced-motion` honored; charts get `role="img"`/`aria-label` + a visually-hidden data
   table; colour is never the only signal; touch targets ≥ 40px on mobile; the `<dialog>` modals
   keep working.
5. **Copy is domain-true** — YSLEP service ledger language (Formation, Social Apostolate, Parish
   Involvement, saved reports, academic year archives). Never invent features, counts, or claims.
6. Assets are built with `npm run build` (the layout loads the Vite manifest; `public/hot` does not
   exist). Verify the build succeeds after changes.
7. Windows/PowerShell environment: use `npm run build`, `php artisan test --compact`,
   `vendor/bin/pint --dirty --format agent`, `playwright-cli`. No Unix-only commands.
8. Keep `debug.php`, root `*.png` screenshots, and `.playwright-*` artifacts untouched.
9. Tests in `tests/Feature/*` assert on current markup in places — after relayout, **update those
   assertions to the new markup** (behavior must not regress; selectors may change). Run the suite
   serially and keep it green.

## Anti-slop bans (evaluator will fail the iteration on these)

- No purple/blue "AI gradients", glassmorphism blobs, floating 3D shapes, or neon-on-dark reskins.
- No "everything in identical rounded cards" layout; vary treatment by content weight.
- No generic hero ("Welcome back! Here's your overview at a glance"), no placeholder/lorem copy,
  no emoji icons, no icon-font, no "Get Started" language.
- No decorative charts with fake or hardcoded data — if a number isn't computed from the DB, it
  doesn't ship.
- No centred-hero-two-buttons template, no equal-weight three-column feature grids.
- No unnecessary abstraction: no new PHP interface/class where a method or closure suffices; no
  config for a value that never changes; fewest files possible for the change.
- Kill dead CSS while you're in there (unused classes from the relayout), keep the file coherent.

## Verification every iteration

1. `npm run build` — succeeds, no errors.
2. Live app at `http://127.0.0.1:8000/` (server already running; restart via
   `php artisan serve --port=8000` if it died) — dashboard, one index page, reports list, report
   detail, snapshots list — 200s, zero console errors.
3. DOM checks (playwright-cli): textContent/computed styles, keyboard focus walk, mobile 390px +
   desktop 1440px.
4. Chart values cross-checked against a DB query (no fabricated numbers).
5. Final gate after the loop passes: `php artisan test --compact` (serially!) + `vendor/bin/pint
   --dirty --format agent` + the mechanical detector:
   `node D:\Softwares\aiOpencode\config\skills\impeccable\scripts\detect.mjs --json <changed views/css/js>`.

## Out of scope

Authentication, i18n, dark mode, global search, new report/snapshot features, Obsidian sync
behavior changes, dependency upgrades, deployment.
