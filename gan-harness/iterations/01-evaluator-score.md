# Evaluator scorecard — iteration 01

**Mode:** `playwright` (playwright-cli, real DOM reads — textContent, computed styles, live clicks,
console log files). Screenshots used only as supporting evidence; every claim below is backed by a
DOM read, a click count, or a read-only DB query.
**App:** http://127.0.0.1:8000 — was already up, not restarted.
**Viewport passes:** 1440×900 and 390×844.

---

## Scores

| Axis | Score | Weight | Weighted |
|---|---|---|---|
| Design Quality | **7 / 10** | 0.35 | 2.45 |
| Originality | **8 / 10** | 0.30 | 2.40 |
| Craft | **6 / 10** | 0.25 | 1.50 |
| Functionality | **8 / 10** | 0.10 | 0.80 |
| **TOTAL** | | | **7.15 / 10** |

**Verdict: FAIL** (needs ≥ 7.5; also 2 open blockers).
Axis rule satisfied (every axis ≥ 6), weighted total is not.

---

## Blockers

### B1 — The build is stale; the `/reports` register ships unstyled
`resources/css/app.css` was modified at **07:37**; the only built stylesheet
`public/build/assets/app-BPEDy6JX.css` was produced at **07:28**. The build predates the last CSS
edit, and 8 component classes exist in source but **not in the served stylesheet**:

```
report-register, register-row, register-row__title, register-row__tag,
register-row__stat, register-row__stat--records, register-row__types, register-empty
```

DOM proof on the live `/reports` (the page the browser is actually serving):

```json
{"cls":"register-row__title","fs":"16px","ff":"Manrope","display":"inline",
 "border":"0px solid rgb(32, 22, 18)","grid":"none","sheetHas":false}
```

Source says it should be `font-family: var(--font-serif); font-size: 1.1rem; font-weight: 700`
(app.css:1276) inside a 5-column ruled ledger grid (`.register-row`, app.css:1263).
Net effect: on `/reports` the 13 saved reports render as a plain stacked list — no ruled
separators, no aligned columns, no serif titles — while every other page renders the new design.
This fails spec "Verification every iteration" step 1 (`npm run build` must back what ships) and
the rubric's "Build failure" blocker.

**Fix:** `npm run build`, then re-check `.register-row` computed styles on `/reports`.

### B2 — Hard constraint 8 violated: committed `.playwright-*` artifacts deleted
`git status` shows 18 tracked files deleted, e.g. `.playwright-cli/page-2026-09-20T08-42-20-342Z.yml`
(18 files, all ` D`). spec.md hard constraint 8: *"Keep `debug.php`, root `*.png` screenshots, and
`.playwright-*` artifacts untouched."* In addition two new root PNGs were added
(`before-dashboard.png`, `dashboard-before.png`) — root screenshots are supposed to stay untouched,
and "before" shots belong under `gan-harness/`, not the repo root. `debug.php` is unchanged ✓ and no
tracked root PNG was modified ✓.

**Fix:** `git checkout -- .playwright-cli/`; delete `before-dashboard.png` and
`dashboard-before.png` from the root (or move them into `gan-harness/`).

---

## Axis evidence

### Design Quality — 7/10
Verified from computed styles on `/`:
- Palette holds: body ink `rgb(32,22,18)` on paper `rgb(255,253,248)`; masthead
  `rgba(245,237,224,0.93)` + `backdrop-filter: blur(12px)`; accent rust `rgb(201,77,36)`. No AI
  gradients, no glass blobs, no neon — the only `background-image: gradient` values found are paper
  texture on `body`, `.primary-button`, `.page-grid`, `.marg-stackwrap`.
- Hierarchy encodes importance: `h1` 48px/60px **Fraunces** 400; section `h2` 24px Fraunces 400;
  kickers 10.88px 800 uppercase 1.3px tracking in rust; body **Manrope**. You can tell headline from
  metadata at a glance.
- Information design is real, not decorative: ruled funnel rows, per-column month totals above the
  bars, "Coverage — 13 of 13 months hold entries" under the chart, an honest academic-year block.
- Responsive: `documentElement.scrollWidth` stays scroll-locked at 390 (`window.scrollTo(400,0)`
  leaves `scrollLeft === 0`). The chart, the nav and the 7-column index table each live in their own
  `overflow-x:auto` container (`DIV.marg-scroll w=316`, `DIV.nav-cluster__links w=358`,
  `DIV.mt-5 overflow-x-auto w=316`). Masthead `position: sticky; top:0` — 92px desktop, 85px mobile.
  Quick-add bar `position: fixed` 210×40 at mobile ✓.
- Deductions: `/reports` is visibly unstyled (B1). Type runs too small in places — nav labels
  `10.88px`, `.recent-row__type` `0.56rem` (~9px). At 390px the index table squeezes 7 columns into
  316px (headers measured `Hours:40`, `Avg per entr:37`, `Entries:48` px wide — truncated on screen).
  The "Time Served" comparison panel reads `October 2026 · 0 hr · against 0 hr the month before` —
  honest, but dead: it answers no trajectory question for a ledger whose last entry is Aug 2026.

### Originality — 8/10
- The monthly chart is genuinely drawn as ledger marginalia, not a chart card: `DIV.marg-plot w=755`
  holding 13 ruled month columns, each a `flex` stack of `a.marg-seg` bars with the total
  (`7 hr 30 min`), the entry count and the month/year label set beneath a hairline baseline. Caption:
  *"Every month from Aug 2025 — Aug 2026, split by index. Busiest month: March 2026 with 17 hr 30 min."*
- Status is not a pill farm: one `status "Live data from Obsidian"` chip, stamped-looking
  `compact-pill`s, and the save-report builder is a native `<details>/<summary>` disclosure rather
  than a modal or a widget.
- Layout follows content: funnel is a ruled list with 100% stacked bars, the index table is a
  7-column ruled ledger, recent entries are ruled rows — no 12-col template, no equal-weight
  three-column grid, no centred hero with two buttons.
- Copy is domain-true throughout: "Formation, Social Apostolate, and Parish Involvement service
  records, saved reports, and academic year archives."; "Nothing is waiting: every live entry
  already belongs to a saved report."; "Years are never inferred."
- Held back only by the fact that one of five pages currently renders outside this system (B1).

### Craft — 6/10
Good:
- Zero console errors on 10 pages visited (`/`, `/indexes/formation?month=2026-03`,
  `/indexes/formation?scope=unsaved`, `/indexes/parish_involvement`, `/indexes/social_apostolate`,
  `/reports`, `/reports/RPT-20260924-181146-CS2L`, `/academic-year-snapshots`,
  `/academic-year-snapshots/AYS-20252026-20260418-164645-IZI6`). Capture mechanism proven live:
  `console.error('EVALTEST-ERR')` was recorded in the same log, so the absence is real.
- Focus rings present everywhere measured: `.marg-seg`, `.primary-button`, `details > summary`,
  form inputs all get `outline: 2px solid var(--ledger-accent-ink)`; nav/secondary get a 4px ring.
  Tab walk from the top: skip link first (`rect [16,16,136,36]`, becomes visible on focus), then
  quick-add, masthead, then **39 chart segment links**, all focusable and operable with
  `outline: solid 2px`.
- `prefers-reduced-motion` honoured twice (app.css:56 global, app.css:1550 for
  `.quick-add-bar`/`.entry-modal`); `[x-cloak] { display:none !important }` present.
- `.ai/rules/css.md` respected: 147 base component rules, and every apparent duplicate
  (`.compact-summary-grid` ×4, `.snapshot-builder-toolbar` ×3, `.nav-cluster__links` ×2,
  `.index-table` ×4 …) is a legitimate `@media (min-width: 768/1024/1280px)` variant or a
  `.page-enter > *:nth-child()` child rule — **no layered/unlayered twins**. Only 6 `!important`,
  all in reduced-motion and `x-cloak`.
- `tabular-nums` on figures confirmed: `dd`/`strong` values report
  `fontVariantNumeric: "tabular-nums"`.
- Chart a11y is above spec: container `role="group" aria-label="Monthly hours stacked by index;
  scroll horizontally if needed"`, each of the 39 segments is an `<a href>` with a full spoken
  label (`aria-label="Formation, August 2025: 1 hr. Open the Formation ledger for this month."`),
  hover/focus tooltip works (`"Social Apostolate · Sep 2025 · 2 hr"`, `opacity:0→1`,
  `pointer-events:none` so no layout shift), plus a `<caption>`-bearing data table.
- `<dialog>` modals: `showModal` opens with focus inside on `input[name=served_on]`; Escape closes;
  2 actions from anywhere to a ready form.

Not good:
- **B1** — shipping CSS that does not match the source is the single worst craft failure available
  in this loop; it also means nothing below the CSS line can be trusted until the build is re-run.
- `.topbar-link:focus-visible` and `.secondary-button:focus-visible` are `outline: none` + a 4px
  `rgba(201,77,36,0.12)` ring. Blended over the cream pill that is ≈ `rgb(248,237,227)` against
  `rgb(255,253,248)` — **≈1.13:1**. Effectively invisible. (Pre-existing — `git show HEAD:app.css`
  has the identical rule — so it is not a regression, but the redesign owned the focus system and
  left this.)
- `table.sr-only` (the chart data table) computes `width: 776.94px; height: 360px;
  clip-path: inset(50%)`. It is visually hidden correctly, but it keeps a full layout box, which
  inflates `documentElement.scrollWidth` to **813px at a 390px viewport**. No scrollbar appears
  today, but the page is one CSS change away from a real horizontal scroll.
- Screen-reader numbers disagree with the visual: the table reads `60 minutes / 360 minutes / 120
  minutes` where the chart above reads `1 hr / 6 hr / 2 hr`.
- Closing a modal returns focus to `BODY`, not to the trigger (`Add entry` / `Edit`).
- Copy nits: `1 entries` and `Unsaved Only entries` on `/indexes/formation`; snapshots list mixes
  `1 report(s)` in a title with `2 REPORT(S) | 17 RECORD(S)` in the stat line.

### Functionality — 8/10
**Click budgets — every one met, counted with real clicks:**

| Journey | Measured | Result |
|---|---|---|
| Dashboard → any index ledger | 1 click (`a[href$="/indexes/formation"]` "Open ledger") | ✅ |
| Dashboard → one index, one month | **1 click** — clicked `a[href$="/indexes/formation?month=2026-03"]`, landed on `/indexes/formation?month=2026-03`, header shows `1 entries · 1 hr served` + clear-chip `March 2026 × — clear month filter` | ✅ |
| Dashboard → latest saved report | 1 click ("Open report" → `/reports/RPT-20260924-181146-CS2L`) | ✅ |
| Any page → add an entry | **2 actions**: `Add entry` → type chooser (`Formation`/`Social Apostolate`/`Parish Involvement`) → `<dialog open>`, focus on `input[name=served_on]` | ✅ |
| Any page → other top-level section | 1 click, masthead is `position: sticky; top:0` on every page tested | ✅ |
| Ledger → unsaved only | 1 click (`?scope=unsaved`; header switches to `UNSAVED ONLY`) | ✅ |
| Ledger row → edit | **1 click** (`button:has-text('Edit')` → `dialog[open]` "Edit Formation") | ✅ |
| Report → back to list | 1 click (`← All saved reports`, plus masthead `Reports`) | ✅ |
| Any report/archive mention → it | 1 click (recent-entry report chips, snapshot `Open` links, register rows) | ✅ |

Other smoke: quick-add bar opens and cancels without submitting; save-report builder expands via
`<summary>` and shows an honest empty state (`"All categories are saved or locked. Create new live
entries in Obsidian first."`); `Sync from Obsidian` + `Save a Report` buttons present on `/reports`;
scope switcher, month filter and report `<select>` all GET-form driven; no dead ends found.

**DB cross-checks (read-only tinker, no writes):**

| UI figure | DB value | Match |
|---|---|---|
| `Entries 111` (Formation 13 / Parish 62 / Social 36) | `counts F=13 S=36 P=62` | ✅ |
| `Time Served 167 hr` (F 14 hr / P 99 hr / S 54 hr) | `total_min F=840 S=3240 P=5940` → 10 020 min = 167 hr | ✅ |
| Busiest month `March 2026 · 17 hr 30 min`, table row `60 / 510 / 480 min` | `2026-03 F=60 S=480 P=510` = 1 050 min | ✅ |
| Chart row `August 2025 · 9 hr`, `60 / 360 / 120 min`, `9 entries` | `2025-08 F=60 S=120 P=360`, parish count 6 | ✅ |
| Chart row `August 2026 · 17 hr`, `60 / 600 / 360 min` | `2026-08 F=60 S=360 P=600` | ✅ |
| `October 2026 · 0 hr` / `September 2026` absent | `2026-09` count = 0 across all three indexes; data range `2025-08-09 … 2026-08-29` | ✅ honest |
| `Not set · 111 entries · 167 hr` (academic-year lens) | `whereNotNull('academic_year')` = 0 on all three tables | ✅ — **no inferred year** |
| Undated entries (not in the chart) | `whereNull('served_on')` = 0 on all three | ✅ |
| Funnel `Formation 13 / Parish 62 / Social 36 locked, 0 ready` | equals the live entry counts; `ReportGroupItem::count() = 120` across 13 groups (entries can sit in >1 report) | ✅ |
| `/reports` `120 TOTAL RECORDS`, `188 TOTAL HOURS` | `ReportGroupItem` count 120, `sum(duration_minutes) = 11 280` = **188 hr** | ✅ exact |

Backend is real aggregation, not decoration: `DashboardController::monthlySeries()` walks the
already-loaded live entries, buckets by `served_on->format('Y-m')`, keeps missing months explicit as
gaps, and never fabricates an academic year (`academicYearTotals()` buckets only what is stored).
`IndexPageController` gained a validated `^\d{4}-\d{2}$` month lens. No schema changes.

---

## Prioritized must-fix

1. **Rebuild the assets.** `npm run build` (app.css mtime 07:37 > build 07:28; `register-row`,
   `report-register`, `register-row__*`, `register-empty` are absent from
   `public/build/assets/app-BPEDy6JX.css`). *Where:* build, then verify `.register-row` on
   `/reports`. *Why:* one of five main pages ships unstyled — a blocker.
2. **Restore the deleted artifacts.** `git checkout -- .playwright-cli/` (18 tracked `D` files) and
   remove/relocate `before-dashboard.png`, `dashboard-before.png`. *Where:* repo root /
   `.playwright-cli/`. *Why:* hard constraint 8 — an explicit fail condition in the rubric.
3. **Raise mobile tap targets to ≥40px.** `a.marg-seg` measures **55×11–22px** and `.topbar-link`
   **36px** at 390×844. *Where:* `resources/css/app.css` (`.marg-seg`, `.topbar-link`) — add
   `min-height: 2.5rem` and vertical padding on the segment, or give the bar a taller padded hit
   area via `::after { inset: -Xpx }`. *Why:* spec hard constraint 4; the chart drill-in is the
   dashboard's headline interaction.
4. **Make the nav/secondary focus ring visible.** `.topbar-link:focus-visible` /
   `.secondary-button:focus-visible` use `outline: none` + `box-shadow 0 0 0 4px
   rgba(201,77,36,0.12)` ≈ **1.13:1** against the cream pill. *Where:* app.css:209 and app.css:279 —
   copy the `.marg-seg:focus-visible` treatment (`outline: 2px solid var(--ledger-accent-ink);
   outline-offset: 2px`). *Why:* the primary navigation is the least visible focus target on the
   site.
5. **Contain the sr-only table's layout box.** `table#trend-data.sr-only` computes
   `776.94×360px` (`clip-path: inset(50%)` hides it visually but not its box), pushing
   `documentElement.scrollWidth` to **813 at a 390px viewport**. *Where:*
   `resources/views/dashboard.blade.php:201` — add `w-px h-px` (Tailwind's `sr-only` `width:1px` is
   losing to the table's auto table-layout). *Why:* currently invisible, one edit from a real
   horizontal scroll on every mobile page.

## Next-iteration suggestions
- Make the trend panel answer "what needs me": it currently reads `October 2026 · 0 hr · against 0
  hr the month before`. Report the last month *with* entries (`August 2026 · 17 hr · no entries
  since`) instead of an inert current/previous pair.
- Fix copy: `1 entries` → `1 entry` (header summary on `/indexes/{type}?month=`), `Unsaved Only
  entries` → `Unsaved only`, and align the snapshot `1 report(s)` / `2 REPORT(S)` casing.
- Give the sr-only data table the same human units the chart shows (`1 hr` rather than `60 minutes`).
- Restore focus to the trigger on `<dialog>` close instead of `BODY`.
- Reconsider sub-10px micro-labels (`.recent-row__type` `0.56rem`, nav `0.68rem`); 0.68rem+ reads
  better at arm's length and costs nothing in the design.
- Add a `min-width` floor per column in the 7-column index table, or drop "Locked in Reports" into
  the Index cell at 390px — `Avg per entry` currently measures 37px and truncates.
