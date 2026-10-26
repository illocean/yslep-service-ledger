# Generator report — iteration 02

**App:** http://127.0.0.1:8000 (server left running, never restarted)
**Build:** `npm run build` clean, and it is the last thing that wrote to disk — `app-C352oiRM.css` + `manifest.json` at 13:09:18 vs the newest source file (`resources/css/app.css`) at 13:01:27. Every verification below was read-only DOM/computed-style work after that build.

---

## Fix list from the iteration-01 scorecard

| # | Issue | Resolution | Evidence |
|---|---|---|---|
| B1 | Stale build — `/reports` register shipped unstyled | Built last; no source edit after it | `/reports` `.register-row` `display:grid`, `.register-row__title` **Fraunces 17.6px/700**, `.register-row__tag` `display:block`, 13 rows; all 8 missing classes + `archive-totals*`, `ay-lede`, `trend-coverage__figure` confirmed present in the served stylesheet |
| 2 | Mobile tap targets | `.marg-seg` floor `2.5rem`, `.topbar-link` `min-height 2.5rem`, `.compact-pill:is(button, a)` floor | 390px: 39 segments, **min 58×40px**; `.topbar-link` **40px**; bar proportions untouched (stack height still `max(fill%, 7.5rem)`, flex clamps only at the floor) |
| 3 | Invisible focus rings | `.topbar-link`, `.secondary-button`, `.danger-button` now `outline: 2px solid var(--ledger-accent-ink); outline-offset: 2px` (#9c3a1a on cream ≈ 6.4:1) | Keyboard `Tab` walk → `outline: 2px solid rgb(156,58,26)`, offset `2px` on `.topbar-link` |
| 4 | `sr-only` table inflated `scrollWidth` to 813 | Table dropped `sr-only`; wrapped in `w-px h-px overflow-hidden` (clipped, still in the a11y tree, caption + `<th scope>` intact) | 390px: `scrollWidth 390 = clientWidth 390`; wrapper `overflow:hidden` |
| 5 | Inert "October 2026 · 0 hr" trend | Now reports the **last month served** and the empty months since it, from the calendar, capped at 3 + `…` | "Last month served · **17 hr** · August 2026 — nothing recorded in September 2026, October 2026." (Aug 2026 = 1020 min in the DB) |
| 6 | Copy nits | `1 entry` / `Unsaved only` (`IndexScope::Unsaved` label), all `record(s)`/`report(s)` pluralized, snapshot card + builder stats unified; Alpine summary now uses `selectedReportLabel`/`selectedRecordLabel` getters | `/indexes/formation?month=2026-03` header reads `1 entry`; `Unsaved only entries` |
| 7 | Table spoke minutes, chart spoke hours | Table now uses the same hour labels the chart paints (`none` for an absent index) | Row: `1 hr · 6 hr · 2 hr · 9 hr` |
| 8 | Focus lost to `<body>` on dialog close | `entry-modal.js` records the trigger and restores it from the native `close` event, guarded against another dialog taking over | Click `Edit` → dialog opens on `input[served_on]`; `Escape` → `document.activeElement` = `BUTTON "Edit"` (class `compact-pill`), no longer `BODY` |
| 9 | Sub-10px micro-labels | Every custom label at or above `0.68rem` (was 0.5–0.66rem): `.recent-row__type`, `.index-table__file`, `.marg-base__label`, `.marg-month__year`, `.ay-row__stamp`, `.funnel-*`, `.header-status__chip`, `.brand-lockup__mark`, `.compact-pill`, chart legend/totals/tips | 0.68rem = 10.88px floor |
| 10 | 7-column table squeezed to 316px | Dropped `min-w-full` from the table — it beat the component-layer rule, so `.index-table` computed `min-width: 100%` and the 607px width I reported was incidental — and made `.index-table { min-width: 36rem }` the single source of that floor, inside its `overflow-x:auto` ledger wrapper | 390px: wrapper 316px, table 607px, **291px of internal scroll**, page itself does not scroll; 1440px: table 1230px, no scrollbar |
| 11 | Bonus — Fraunces hierarchy | `.ay-lede` (Fraunces 21.6px) and `.trend-coverage__figure` (Fraunces 16.8px) | "0 of 111 entries carry an academic year." (Fraunces 21.6px); "13 of 13 months hold entries" (Fraunces 16.8px) |

Also found and fixed while in the file: `.archive-totals*` (snapshot index-totals strip added in iteration 1) had **no CSS at all** — it would have shipped as a bare `<ul>`. Its six rules are now in the built stylesheet.

---

## Regression sweep (all post-build, read-only)

- **Console:** 0 `[ERROR]` / `[WARNING]` lines across `/`, `/reports`, `/reports/{tag}`, `/indexes/formation`, `/indexes/formation?month=2026-03`, `/indexes/parish_involvement?month=2026-03`, `/academic-year-snapshots`, `/academic-year-snapshots/{id}` (8 logs, only the Boost logger `LOG` line).
- **Click budgets:** chart drill-in re-measured at 390px — one tap on `a.marg-seg` (58×40) → `/indexes/formation?month=2026-03`, header `1 entry`, clear-filter chip present. Masthead section switch, `?scope=unsaved`, `Edit`, report chip → report all unchanged (same routes/handlers as iteration 1).
- **Data integrity:** no aggregation change to the figures that were cross-checked last round (entries 111 / hours 167 hr / busiest 2026-03 = 1 050 min / coverage 13 of 13). The only aggregation change is *added* reporting: last active month, months since, and the empty-month names. No academic year is ever inferred (`0 of 111 entries carry an academic year`).
- **CSS discipline:** 139 custom class definitions, 0 duplicates (scripted check, includes the layered/unlayered twin trap from `.ai/rules/css.md`). Dead-code sweep: every defined class is referenced by a blade file except `fill-formation` / `fill-social_apostolate` / `fill-parish_involvement`, which are emitted as `fill-{{ $type->value }}`. Removed in iteration 2's sweep: `.stat-panel`, `.record-form-shell`, `.record-form-grid` (media variants), `--radius-stat` (0 usages).
- **`vendor/bin/pint --dirty --format agent`:** `pass`.
- **Chart integrity with the new floors:** measured `.marg-stack` height vs summed child heights on the first four columns → `0px` overflow in every case, so the 40px floors never spill past the ruled baseline.

## Changed files

```
app/Enums/IndexScope.php                                    (label: "Unsaved only")
app/Http/Controllers/DashboardController.php                (lastActiveMonth, monthsSinceLast, emptyMonthsSince; dropped the inert current/last pair)
resources/js/components/entry-modal.js                      (opener capture + returnFocus)
resources/js/components/snapshot-builder.js                 (pluralised live summary getters)
resources/css/app.css                                       (tap targets, focus rings, 0.68rem label floor, table min-width, ay-lede/trend-coverage__figure, archive-totals, dead rules out)
resources/views/components/entry-modal.blade.php            (@close → returnFocus)
resources/views/dashboard.blade.php                         (trend panel, stack floor, clipped data table in hour units, academic-year lede)
resources/views/indexes/show.blade.php                      (plural counts)
resources/views/academic-year-snapshots/index.blade.php     (pluralised live summary)
```

Untouched by design: `.playwright-cli/` (18 tracked deletions left exactly as they were; the 74 verification artifacts this session were removed), root `*.png`, `debug.php`, `.env`, migrations, routes, forms, event names, `package.json`.

## Deliberately deferred

1. **B2 (restore `.playwright-cli/`, move the two root PNGs).** Not done — the iteration-2 boundary explicitly says to leave both exactly as they are. They predate the loop (present in `git status` before iteration 1).
2. **`register-row__title` stays `display:inline`.** The grid item is the wrapping `<div>`, and the anchor is the only child; serif, weight and hover are all applied, and long titles wrap correctly. Changing it would need another build cycle for no visual gain.
3. **`.marg-seg` floors exaggerated sub-2.5rem bars — superseded in iteration 3.** Flooring the painted bar (`.marg-stack { height: max(fill%, 7.5rem) }` + `.marg-seg { min-height: 2.5rem }`) made August 2025 (540 min) and September 2025 (420 min) both render at 120px, and August's true 60:360:120 split painted as three identical 40px bars. That is the iteration-2 N1 regression: the bar must stay proportional and only the hit band grows.
4. **Edit/delete on a month-filtered ledger** returns to the unfiltered scope (the `return_*` hidden inputs carry type/scope/report, not month). Preserving the month lens through save/redirect would mean threading a new field through two form requests and the conflict redirects; the filter is a view lens, not lost data.
5. **`php artisan test` was not run** (orchestrator runs it serially); assertions were kept compatible instead — `service ledger from your three Obsidian inputs`, `View All Reports`, `Locked in Reports`, `ready to save`, `data-open-save-group-builder`, `id="save-group-builder"`, `Unsaved only` heading, `Saved report: <name>` (entry modal untouched), and all `data-*` hooks on the snapshot builder are intact.
