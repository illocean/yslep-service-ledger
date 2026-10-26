# Evaluator scorecard — iteration 02

**Mode:** `playwright` (playwright-cli; real DOM reads, computed styles, live clicks, console log files).
Screenshots used only as supporting evidence, never as the source of a number.
**App:** http://127.0.0.1:8000 — already running, not restarted.
**Viewports:** 1440×900 and 390×844.
**Prior card:** `01-evaluator-score.md` (7.15 / FAIL). B2 is withdrawn per instruction and is **not**
re-raised anywhere in this document.

---

## Scores

| Axis | Score | Weight | Weighted |
|---|---|---|---|
| Design Quality | **6.5 / 10** | 0.35 | 2.275 |
| Originality | **7.5 / 10** | 0.30 | 2.250 |
| Craft | **6.5 / 10** | 0.25 | 1.625 |
| Functionality | **8.5 / 10** | 0.10 | 0.850 |
| **TOTAL** | | | **7.00 / 10** |

**Verdict: FAIL** — needs ≥ 7.5. Axis rule satisfied (every axis ≥ 6); the total is 0.5 short and one
**new blocker** is open.

Net movement: **7.15 → 7.00**. Nine of eleven iteration-1 defects were genuinely fixed and verified.
That work was then partly cancelled out by a regression the generator introduced while fixing them.

---

## Re-verification of every iteration-1 must-fix (10 / 10 verified fixed)

| # | Iteration-1 issue | Measured now | Verdict |
|---|---|---|---|
| B1 | stale build; `/reports` unstyled | see below | ✅ fixed |
| 2 | mobile tap targets 55×11–22 / 36px | `a.marg-seg` 39 segs **58×40–91**; `.topbar-link` **40,40,40,40,40,40** | ✅ fixed |
| 3 | focus ring ≈1.13:1 | `solid 2px rgb(156,58,26)` offset 2px = **6.81:1** on cream | ✅ fixed |
| 4 | `sr-only` table → scrollWidth 813 | wrapper `w-px h-px overflow-hidden`, 1×1; `scrollWidth - clientWidth = 0` at 390 | ✅ fixed |
| 5 | inert "October 2026 · 0 hr" | `LAST MONTH SERVED / 17 hr / August 2026 — nothing recorded in September 2026, October 2026.` | ✅ fixed |
| 6 | copy pluralisation | `1 entry`, `0 entries`, `Unsaved only`, `Unsaved only entries`, `2 REPORTS \| 17 RECORDS`, `1 REPORT \| 9 RECORDS` | ✅ fixed |
| 7 | table spoke minutes, chart spoke hours | `August 2025 1 hr 6 hr 2 hr 9 9 hr` — matches the painted labels | ✅ fixed |
| 8 | focus lost to `<body>` on close | Escape → `document.activeElement` = `BUTTON "Edit"` (`.compact-pill`) | ✅ fixed |
| 9 | sub-10px micro-labels | `.recent-row__type` 10.88px, nav 10.88px, **0** text nodes under 10.88px in `main` | ✅ fixed |
| 10 | 7-col table squeezed to 316px | table 607px in a 316px `overflow-x:auto` wrapper, `th{white-space:nowrap}`, cols 45–131px, page does not scroll | ✅ fixed |

### 1 — B1 stale build: confirmed fixed
`public/build/assets/app-C352oiRM.css` mtime **13:09:18**, newest source `dashboard.blade.php`
**13:08:23**, `app.css` **13:01:27** — the build is genuinely last. **0 of 134** source component
classes are missing from the built stylesheet (was 8 missing). Live `/reports` computed styles:

```
.report-register      display:grid  width 1230
.register-row         display:grid  grid-template-columns:580.3px 67.7px 32.3px 333.6px 145.7px
                     border-bottom:1px solid rgba(32,22,18,0.09)   column-gap:16px   13 rows
.register-row__title Fraunces 17.6px weight 700  color rgb(32,22,18)
.register-row__tag    display:block  ui-monospace 12px
.register-row__stat--records  color rgb(105,84,73) + tabular-nums
```

The register is a ruled, five-column ledger with serif titles and mono tags. Screenshot confirms
hairline separators, aligned stat columns and index count chips.

### 3 — Focus ring contrast, computed
`#9c3a1a` on the cream paper `#fffdf8`: L 0.1017 vs 0.9830 → **(1.0330)/(0.1517) = 6.81:1**, far
above the 3:1 WCAG 2.2 focus-appearance floor. Every tab stop measured (skip link, both
quick-add buttons, brand, `Reports`, `Archives`, and all six nav links) now reports
`outline: solid 2px rgb(156,58,26)`, `outline-offset: 2px`. Only the skip link and the brand lockup
still fall back to the UA `auto 1px rgb(16,16,16)` — visible, but not the house ring.

### 5 — Trend panel, cross-checked
DOM: `TIME SERVED / 167 hr / LAST MONTH SERVED / 17 hr / August 2026 — nothing recorded in
September 2026, October 2026.`
Read-only tinker: `aug2026 F=60 S=360 P=600` → **1 020 min = 17 hr** ✅; `sept2026 count=0` ✅;
`oct2026 count=0` ✅. Honest, non-inert, and traceable.

### 4 / 7 — Accessible table
`table#trend-data` is now wrapped in `div.w-px.h-px.overflow-hidden` (wrapper 1×1, table 446×672
inside it). It survives in the accessibility tree — Playwright still reports
`table "Hours served per month, by index"` with its `<caption>`, `<th>` header row and all 13 body
rows in **hour** units. `documentElement.scrollWidth - clientWidth = 0` at 390px on `/`.

### 8 — Dialog focus return
```
click Edit        → dialog[open] "Edit Formation", focusInside: true, active INPUT/served_on
press Escape      → stillOpen: false, activeElement BUTTON "Edit" (class compact-pill)
```

---

## NEW BLOCKER

### N1 — the tap-target fix made the chart lie about the data

Iteration 2 floors both the column and its segments:
`style="height: max(51.43%, 7.5rem)"` on `.marg-stack` inside a **192px** `.marg-stackwrap`, and
`.marg-seg { min-height: 40px }`. Measured against the data table's own true minutes (max month
1 020 min → 191px, so the honest scale is `191 × min / 1020`):

| Month | true minutes | table says | **honest px** | **rendered px** | error | rendered segments |
|---|---|---|---|---|---|---|
| August 2025 | 540 | 9 hr | 101 | **120** | +19% | 40 / 40 / 40 |
| September 2025 | 420 | 7 hr | 79 | **120** | **+52%** | 40 / 40 / 40 |
| October 2025 | 540 | 9 hr | 101 | **120** | +19% | 40 / 40 / 40 |
| November 2025 | 840 | 14 hr | 157 | 152.8 | −3% | 40 / 72.8 / 40 |
| December 2025 | 1 020 | 17 hr | 191 | 185.5 | −3% | 40 / 77.5 / 68 |

Two consequences, both visible on screen:

1. **August 2025 (9 hr) and September 2025 (7 hr) draw at exactly the same height.** The left third
   of the chart is a flat plateau; the 23% decline the dashboard exists to reveal is invisible. The
   honest scale would put them 28px apart.
2. **The within-column split is destroyed.** August 2025 is Formation 60 / Parish 360 / Social 120
   minutes — a 1 : 6 : 2 split — and renders as three identical bars.

This is a regression **introduced in iteration 2**: in iteration 1 the same columns measured
55×11–22px, which matches the proportional scale (`192 × 60/1020 = 11.3px`). The chart was truthful
before it was tappable.

The generator's report discloses this as "deferred #3" and argues the exact value is carried by the
tooltip, the `aria-label`, the drawn month total and the data table. Those are all correct, and I
give that reasoning its due — but the rubric asks whether the dashboard "reads like an instrument a
chronicler trusts", and spec's anti-slop ban is "no decorative charts with fake data". A bar drawn at
120px for a month whose true height is 79px **is** a number that is not computed from the DB. A
per-user glance misleads; only a hover recovers the truth.

**This is fixable in a few lines without giving up the 40px targets** — the hit area and the painted
bar do not have to be the same box:

```blade
{{-- dashboard.blade.php:175-177 --}}
<div class="marg-stack" style="height: {{ round($monthFill, 2) }}%">   {{-- drop max(..., $stackFloor) --}}
```
```css
/* app.css — keep the bar honest, grow the target only */
.marg-stack { min-height: 0; }                 /* remove the 7.5rem stack floor */
.marg-seg   { min-height: 0; position: relative; }
.marg-seg::after {                              /* invisible 40px hit area */
    content: ''; position: absolute; inset: 0 0 auto; height: max(100%, 2.5rem);
}
.marg-seg:is(:hover, :focus-visible)::after { z-index: 5; }   /* overlap resolves to the topmost */
```
`.marg-col` is already `min-width: 3.4rem` (54.4px), so width is already adequate — only height is
the problem.

---

## New non-blocking issues

- **Mobile overflow on the snapshot detail page.** At 390px,
  `documentElement.scrollWidth = 728` vs `clientWidth = 390` (**+338**), caused by
  `ARTICLE.paper-panel` measuring **712px inside a `SECTION.grid` of 358px**. The ancestor chain is
  `SECTION.grid(358) → ARTICLE(712) → DIV.mt-6.overflow-hidden(670) → DIV.overflow-x-auto(668) →
  TABLE.ledger-table(668)`. Classic `min-width:auto` on a grid item: the table's `min-w-full` pins
  the panel open. The page does not scroll horizontally (`scrollLeft` stays 0 after
  `scrollingElement.scrollLeft = 300`), so content is cut rather than reachable — but the overflow
  wrapper is defeated by an intermediate `overflow-hidden`. Fix: `min-width: 0` on the grid item
  (`.paper-panel` / `SECTION.grid > *`), or drop `min-w-full` from `.ledger-table` at mobile.
- **Leftover 28px targets.** `.assignment-chip` on `/` measures **84×28px** — the report chips that
  serve the "any report mention → that report" journey. `.topbar-link` and `.marg-seg` got the 2.5rem
  floor; this class did not. On the snapshot detail page the chips are 28px too.
- **Claim mismatch (not user-visible).** The report states `.index-table { min-width: 36rem }`;
  computed is `min-width: 100%`. The outcome is correct (607px table, 291px of internal scroll), but
  the stated mechanism is not what ships.
- **Inline small links** (`← All academic year snapshots`, `Open Formation entry`, brand lockup 22px)
  remain under 40px. WCAG 2.2's inline-in-sentence exception covers these; not counted against.

---

## Regression + full-scope sweep

### Console and status codes
All 8 main pages return **200**. Eight fresh console logs, each containing only the Boost logger
`LOG` line — **zero `[ERROR]` / `[WARNING]`**. Capture was proven live this session:
`console.error('I2PROOF')` landed in the same log, so the absence is a measurement, not a gap.

| Page | Status | Console |
|---|---|---|
| `/` | 200 | clean |
| `/indexes/formation` | 200 | clean |
| `/indexes/parish_involvement` | 200 | clean |
| `/indexes/social_apostolate` | 200 | clean |
| `/reports` | 200 | clean |
| `/reports/RPT-20260924-181146-CS2L` | 200 | clean |
| `/academic-year-snapshots` | 200 | clean |
| `/academic-year-snapshots/AYS-20252026-20260418-164645-IZI6` | 200 | clean |

### All 9 click budgets, re-measured with real clicks

| Journey | Measured | Result |
|---|---|---|
| Dashboard → any index ledger | 1 click (`Open ledger`) → `/indexes/formation` | ✅ |
| Dashboard → one index, one month | **1 click** on `a[href$="/indexes/formation?month=2026-03"]` → `/indexes/formation?month=2026-03`, header `1 entry · 1 hr served`, clear chip `MARCH 2026` | ✅ |
| Dashboard → latest saved report | 1 click (`Open report`) → `/reports/RPT-20260924-181146-CS2L` | ✅ |
| Any page → add an entry | **2 actions** from `/academic-year-snapshots`: `.quick-add-bar button:has-text('Add entry')` → `.quick-add-options button:has-text('Parish Involvement')` → `dialog[open]` "Add Parish Involvement", focus on `input[served_on]` | ✅ |
| Any page → other top-level section | 1 click (nav) → `/academic-year-snapshots` | ✅ |
| Ledger → unsaved only | 1 click → `/indexes/parish_involvement?scope=unsaved` | ✅ |
| Ledger row → edit | **1 click** → `dialog[open]` "Edit Formation" | ✅ |
| Report → back to list | 1 click (`← All saved reports`) → `/reports` | ✅ |
| Report mention anywhere → that report | 1 click (recent-row chip) → `/reports/RPT-20260924-181146-CS2L` | ✅ |

*(My first pass on budget 4 reported a failure; that was my own selector colliding with the dialog's
own "Add entry" submit button in strict mode, not a product defect. Re-measured scoped, it passes.)*

### Responsive integrity at 390×844
Seven of eight pages: `scrollWidth - clientWidth = 0` **and** `scrollLeft === 0` after
`scrollingElement.scrollLeft = 300`. The only exception is the snapshot detail page (+338, above).
Masthead `position: sticky`; quick-add bar `position: fixed` 210×40.

### Hierarchy at 1440×900
`h1` Fraunces 48px/60px w400 · section `h2` Fraunces 24px w400 · card `h2` Fraunces 20px ·
`/reports` `h1` Fraunces 36px w400 · body Manrope · `tabular-nums` on figures · zero text nodes
below 10.88px. The new Fraunces ledes ("0 of 111 entries carry an academic year.", "13 of 13 months
hold entries") sit correctly below their kickers and above their body copy.

### DB cross-checks (read-only tinker; no writes, no migrations, no `php artisan test`)

| UI figure | DB | Match |
|---|---|---|
| `Entries 111` (13 / 62 / 36) | `counts F=13 S=36 P=62` | ✅ |
| `Time Served 167 hr` | `total_min=10020` | ✅ |
| `LAST MONTH SERVED 17 hr · August 2026` | `aug2026 F=60 S=360 P=600` = 1 020 min | ✅ |
| "nothing recorded in September 2026, October 2026" | `sept2026 count=0`, `oct2026 count=0` | ✅ |
| Busiest `March 2026 · 17 hr 30 min` | `mar2026 total=1050` | ✅ |
| Funnel `111 of 111 locked / 0 ready`; 13 / 62 / 36 | matches the live entry counts | ✅ |
| `0 of 111 entries carry an academic year` | `ay_set=0` | ✅ no inference |
| `/reports` `120 TOTAL RECORDS / 188 TOTAL HOURS` | `reportitems=120`, `hours=188` | ✅ exact |
| Undated entries excluded from the chart | `undated=0` | ✅ |

No academic year is inferred anywhere; `.ai/rules/app.md` respected.

### CSS discipline (`.ai/rules/css.md`)
134 base component rules; every apparent duplicate is a legitimate `@media (min-width: 768/1024/
1280px)` variant or a `.page-enter > *:nth-child()` child rule — **no layered/unlayered twins**.
Confirmed independently of the generator's claim. `x-cloak`, `prefers-reduced-motion` and
`tabular-nums` all present.

---

## Prioritized must-fix

1. **Make the chart encode the data again (N1, blocker).** Drop the `max({{ $monthFill }}%,
   {{ $stackFloor }}rem)` stack floor in `resources/views/dashboard.blade.php:176` and the
   `min-height` on `.marg-seg` (`resources/css/app.css`), then grow the *hit area* with a
   transparent `.marg-seg::after { height: max(100%, 2.5rem) }` overlay so the 40px tap target
   survives. Verify: August 2025 (540 min) and September 2025 (420 min) must render at different
   heights in the 1.5:1 proportion 540:420, and August's segments must render ≈40/75/40, not
   40/40/40.
2. **Fix the snapshot detail overflow at 390px (+338px).** `ARTICLE.paper-panel` is 712px inside a
   358px grid column because `table.ledger-table { min-w-full }` pins the grid item's
   `min-width: auto`. *Where:* `resources/css/app.css` (`.paper-panel`, `.ledger-table`) or
   `resources/views/academic-year-snapshots/show.blade.php`. *Why:* a main page loses content off the
   right edge on the most common viewport.
3. **Bring `.assignment-chip` up to the same 40px floor** as `.topbar-link` / `.marg-seg` (now 84×28
   on `/`, 28px on snapshot detail). *Where:* `resources/css/app.css`. *Why:* these are the report
   chips that carry the "any mention → that report" click budget; the fix was applied to two of three
   target classes and missed this one.
4. **Give the brand lockup and skip link the house ring.** Both still fall back to the UA
   `auto 1px rgb(16,16,16)` while every other focusable uses
   `outline: 2px solid var(--ledger-accent-ink); outline-offset: 2px`. *Where:* `app.css`
   (`.brand-lockup__title`, `.sr-only:focus-visible`). *Why:* consistency in a focus system the
   redesign otherwise rewrote correctly.
5. **Correct the report's `min-width: 36rem` claim.** Computed is `min-width: 100%`. The outcome is
   right, but a scorecard-driven loop that trusts its own report will catch this on a page where it
   is wrong. *Where:* `02-generator-report.md` #10.

**Deliberately not re-raising:** B2 — withdrawn as pre-existing working-tree state.