# Evaluator scorecard — iteration 03

**Mode:** `playwright` (playwright-cli; DOM textContent, computed styles, `getBoundingClientRect`,
`elementFromPoint` hit-testing, console log files). Screenshots used only to corroborate; every number
below comes from a DOM read, a real click, or a read-only tinker query.
**App:** http://127.0.0.1:8000 — already running, not restarted.
**Viewports:** 1440×900 and 390×844.

**Result: PASS — 8.25 / 10, no blockers.**
Score trajectory: 7.15 (it. 01) → 7.00 (it. 02) → **8.25 (it. 03)**.

Method note, stated plainly because it changed conclusions twice: two of my first-pass probes in this
session were wrong and would have produced false verdicts. A `getBoundingClientRect`-only band
measurement reported "all 27 segments under 40px" (it measured painted height, not hit height, and
probed columns outside the viewport); and a closure that cached one segment's rect reported three
segments sharing one band. Both were caught by cross-checking against a direct `elementFromPoint`
scan. Numbers below are re-measured, not carried over.

---

## Scores

| Axis | Score | Weight | Weighted |
|---|---|---|---|
| Design Quality | **8.0 / 10** | 0.35 | 2.80 |
| Originality | **8.5 / 10** | 0.30 | 2.55 |
| Craft | **8.0 / 10** | 0.25 | 2.00 |
| Functionality | **9.0 / 10** | 0.10 | 0.90 |
| **TOTAL** | | | **8.25 / 10** |

**Verdict: PASS.** Weighted 8.25 ≥ 7.5; every axis ≥ 6; **zero open blockers**.

---

## Blocker N1 — resolved, verified

### 1. Proportional truth of the painted bars

The iteration-2 floor is gone from both places it existed:

- `dashboard.blade.php:171` → `<div class="marg-stack" style="height: {{ round($monthFill, 2) }}%">`
  — the `$stackFloor` variable and the `max(..., rem)` clamp are gone (`anyMaxInInline: false`
  across all 13 columns).
- `.marg-seg` computed `min-height: auto` (no floor), `.marg-stack` computed `min-height: auto`.
- The only `max()` left anywhere is `.marg-seg::after { height: max(100%, 2.5rem) }` — an
  absolutely-positioned pseudo-element, which by definition cannot paint.

Fill percentages are the exact DB ratios: 540/1050 = **51.43%** (Aug 2025), 420/1050 = **40%**
(Sep), 840/1050 = **80%** (Nov), 1020/1050 = **97.14%** (Dec/Aug-26), 1050/1050 = **100%** (Mar).

Rendered heights vs. the `#trend-data` table's own values, cross-checked against read-only tinker:

| Month | true F / P / S (min, from DB) | painted px (border-adj) | scale px/min | stack px |
|---|---|---|---|---|
| August 2025 | 60 / 360 / 120 | 11.58 / 64.48 / 22.16 | **0.17630 / 0.17635 / 0.17630** | 98.22 |
| September 2025 | 60 / 240 / 120 | 11.48 / 42.94 / 21.97 | **0.17474 / 0.17474 / 0.17474** | 76.39 |
| March 2026 | 60 / 510 / 480 | 11.75 / 92.31 / 86.94 | **0.17917 / 0.17904 / 0.17904** | 191.00 |
| August 2026 | 60 / 600 / 360 | 11.73 / 108.38 / 65.42 | **0.17891 / 0.17896 / 0.17895** | 185.53 |

Within each column the three segments share one identical px-per-minute scale to four decimal places —
the bars are exactly proportional. **August 2025's true 60:360:120 split now paints 60:360:120**, not
three equal bars as in iteration 2. The residual ~1px offset is the `border-top: 1px` on each segment,
present since iteration 1 and equally applied to every bar.

Column ratio **98.22 / 76.39 = 1.2859** against the true **540/420 = 1.2857** — August and September
are once again distinguishable, which was the specific defect called last round.

The chart's caption is truthful: *"Every month from Aug 2025 — Aug 2026 on one shared scale, split by
index. The tallest column is March 2026 at 17 hr 30 min."* March 2026 is the 100% column and
`mar2026 total = 1050` min = 17 hr 30 min. ✅

### 2. Hit area separated from paint — no mis-targeting

`.marg-seg::after { content:''; position:absolute; inset:0 0 auto; height:max(100%,2.5rem) }`, with
`.marg-stack > .marg-seg:last-child::after { inset:auto 0 0 }`. Confirmed `position:absolute`,
`pointer-events:auto`, `background:rgba(0,0,0,0)` (transparent), and it occupies no layout box:
children of a stack sum to **98.22 = 98.22** (August) — the painted heights are untouched by the
overlay.

Measured contiguous hit band (`elementFromPoint` walk up and down through each bar's centre, at a
stable scroll offset):

| Column | painted | hit band |
|---|---|---|
| August 2025 | 11.6 / 64.5 / 22.2 px | **42 / 65 / 40 px** |
| January 2026 | 11.7 / 97.3 / 43.8 px | **42 / 97 / 43 px** |

The extension always grows into **empty air** — the bottom segment (first child, `column-reverse`)
downward past the ruled baseline, the top segment (`:last-child`, `inset:auto 0 0`) upward above the
column — so the inflated bands never overlap a neighbour's paint. No mid-column segment needed help:
the smallest middle segment in the whole chart is February 2026's Parish at ~43px.

**Mis-targeting probe:** 78 sample points (top edge +1, centre, bottom edge −1 of every segment, two
horizontal scroll passes) → **0 mismatches**; each point resolved to its own segment's anchor.
**Real click on an 11.6px painted bar** (`a.marg-seg[href$='indexes/formation?month=2025-08']`) →
`/indexes/formation?month=2025-08`, header `AUGUST 2025 · 1 hr served`. The tap target works without
the bar lying.

Tooltip: hover reveals `"Parish Involvement · Oct 2025 · 6 hr"` at `opacity:1`, `pointer-events:none`,
`position:absolute` — **no layout shift** (all 13 stack heights identical before and after).

### 3. Snapshot-detail overflow — resolved

`min-width: 0` on `.paper-panel` is live (`panelMinW: "0px"`). At 390×844:

```
scrollWidth - clientWidth = 0   and   scrollLeft stays 0
```

on **all eight pages** — the widest offender now measures `panelW: 358`, with the 668px
`table.ledger-table` inside a 314px `overflow-x:auto` scroller carrying **354px of internal scroll**,
so the full table is still reachable rather than merely clipped.

---

## Axis evidence

### Design Quality — 8.0
Hierarchy at 1440 reads as one system: `h1` **Fraunces 48px/60px w400**, section `h2` **Fraunces
24px/30px**, card `h2` Fraunces 20px/28px, `.marg-total` **Fraunces 12.8px w500**, micro-labels
Manrope 10.88px uppercase with tracking. Ink `rgb(32,22,18)` on paper `rgb(255,253,248)`; rust accent
`rgb(156,58,26)`.

The hero answers "what happened, what needs me" before any interaction: `ENTRIES 111 · SAVED REPORTS
13 · UNASSIGNED 0` as a ruled figure strip, then `TIME SERVED 167 hr`, then `LAST MONTH SERVED 17 hr /
August 2026 — nothing recorded in September 2026, October 2026.` — honest, non-inert, and DB-traceable.

Responsive integrity at 390px: zero overflow on all eight pages; chart, nav, index table and the
snapshot ledger table each keep their own `overflow-x:auto` scroller; masthead sticky; quick-add bar
`position:fixed` 210×40.

Anti-slop audit at `/`: no emoji in `body.innerText`; no "Welcome back / Get Started / at a glance"
phrasing; the only gradients are paper texture on `BODY`, `.primary-button`, `.page-grid` and the
ruled baseline on `.marg-stackwrap` — no purple/blue washes, no glassmorphism, no floating 3D.

Deductions: the `Every entry is saved` band at 1440 is a full-width strip carrying two lines and a lot
of air; the `TIME SERVED` card sits as a card inside the hero card, slightly redundant nesting.

### Originality — 8.5
The monthly figure is drawn as ledger marginalia, not a chart widget: ruled `.marg-col` columns over a
hairline `.marg-stackwrap` baseline with a repeating rule pattern, hatched `.marg-col--gap` columns for
missing months, the month total set in Fraunces above each column and the entry count beneath. No stock
chart card, no equal-weight card grid, no generic hero.

Domain-derived choices hold up: the save-report builder is a native `<details>`/`<summary>`, not a
modal; per-index share uses a small filled rail rather than a percentage pill; recent-entry report
mentions are **stamp** chips (`.assignment-chip`, 40px, carrying a dot + label); the academic-year
block refuses to infer and says so in the interface copy ("Years are never inferred", "0 of 111 entries
carry an academic year"). Fraunces is used with intent across headings, figure totals and numerals.

### Craft — 8.0
- **Focus:** every tab stop measured now returns the house ring — skip link, both quick-add buttons,
  brand lockup, `Reports`, `Archives`, all six nav links, and `Open report`:
  `outline: solid 2px rgb(156,58,26)`, `outline-offset: 2px`. Chart segments match on focus
  (`.marg-seg:focus-visible` → same ring + `z-index:5`). `#9c3a1a` on cream `#fffdf8` = **6.81:1**,
  comfortably past the 3:1 WCAG 2.2 floor. The UA `auto 1px` fallback from iterations 1–2 is gone.
- **CSS discipline (`.ai/rules/css.md`):** 155 base rules; the apparent repeats
  (`.compact-summary-grid` ×4, `.index-table` ×4, `.page-enter` ×6, `.marg-stack` ×2 …) are all
  `@media (min-width: …)` variants, `:nth-child()` delay rules or a descendant selector — **no
  layered/unlayered twins**, independently confirmed by scanning column-0 selectors against the layered
  set (no overlap). `!important` down to 6, all in reduced-motion / `[x-cloak]`.
- **A11y:** chart container `role="group"` + `aria-label`; all 39 segments carry full spoken labels
  (`"Formation, August 2025: 1 hr. Open the Formation ledger for this month."`); the clipped
  `w-px h-px overflow-hidden` data table stays in the accessibility tree with `<caption>` and `<th>`
  headers, in the same hour units the chart paints. `prefers-reduced-motion` and `[x-cloak]` rules
  present.
- **Dialogs:** `Edit` opens `dialog[open]` "Edit Formation" with focus inside on `input[served_on]`;
  Escape closes and `document.activeElement` returns to `BUTTON [Edit]`, not `BODY`.
- `tabular-nums` on figures throughout.

Deductions: `a.register-row__title` is **21px** tall and the row's Manage button **35px** at 390px,
and `.brand-lockup__title` is **22px** — all below the 40px target the rest of the site now meets.
These are arguably inline links in a ruled ledger row, but they are standalone anchors, so WCAG's
inline-in-sentence exemption does not clearly cover them. (Cross-check: the iteration-2
`.index-table { min-width: 36rem }` correction is now real — computed `576px`, table 607px, 291px
internal scroll at 390px, 1230px with 0 scroll at 1440px.)

### Functionality — 9.0
All eight pages **200**. Ten console logs, only the Boost logger `LOG` line — **zero `[ERROR]`/
`[WARNING]`**. Capture proven live this session: `console.error('I3PROOF')` was recorded.

**All 9 click budgets, re-measured with real clicks:**

| Journey | Measured | |
|---|---|---|
| Dashboard → any index ledger | 1 click → `/indexes/formation` | ✅ |
| Dashboard → one index, one month | **1 click** → `/indexes/parish_involvement?month=2026-03` | ✅ |
| Dashboard → latest saved report | 1 click → `/reports/RPT-20260924-181146-CS2L` | ✅ |
| Any page → add an entry | **2 actions** → `dialog[open]` "Add Formation", focus `served_on` | ✅ |
| Any page → other top-level section | 1 click → `/indexes/social_apostolate` | ✅ |
| Ledger → unsaved only | 1 click → `?scope=unsaved` | ✅ |
| Ledger row → edit | **1 click** → "Edit Formation" | ✅ |
| Report → back to list | 1 click → `/reports` | ✅ |
| Report mention → that report | 1 click → `/reports/RPT-20260924-181146-CS2L` | ✅ |

**DB cross-checks (read-only tinker; no writes, no migrations, `php artisan test` not run):**

| UI figure | DB | |
|---|---|---|
| Chart row `August 2025 · 1 hr / 6 hr / 2 hr · 9 entries` | `F=60 P=360 S=120`, `aug2025_count=9` | ✅ |
| `September 2025 · 1 / 4 / 2 · 7` | `F=60 P=240 S=120` | ✅ |
| `March 2026 · 1 hr / 8 hr 30 min / 8 hr · 9` | `F=60 P=510 S=480 = 1050`, `mar2026_count=9` | ✅ |
| `August 2026 · 1 / 10 / 6 · 9` (and "last month served 17 hr") | `F=60 P=600 S=360 = 1020` | ✅ |
| `November 2025`, `December 2025`, `February 2026`, `May 2026`, `July 2026` rows | `840 / 1020 / 450 / 900 / 780 min` | ✅ |
| `Entries 111` (13 / 62 / 36) | `counts F=13 P=62 S=36 TOT=111` | ✅ |
| `Time Served 167 hr` | `total_min=10020` | ✅ |
| Funnel `111 of 111 locked / 0 ready`, 13 / 62 / 36 | matches live counts | ✅ |
| `/reports` `120 TOTAL RECORDS · 188 TOTAL HOURS` | `reportitems=120 hours=188` | ✅ |
| `0 of 111 entries carry an academic year` | `ay_set=0` — **no inference** | ✅ |
| Undated rows excluded from the chart | `null_served=0` | ✅ |

No aggregation logic changed this iteration; no academic year inferred anywhere.

---

## Prioritized must-fix (none blocking)

1. **Raise the three remaining sub-40px link targets.** `a.register-row__title` is **21px** and the
   row's Manage button **35px** at 390px on `/reports`; `.brand-lockup__title` is **22px** everywhere.
   The site now holds 40px on `.marg-seg`, `.topbar-link` and `.assignment-chip` — apply the same
   `min-height: 2.5rem` (or padding) to these. *Where:* `resources/css/app.css`,
   `.register-row__title` / `.brand-lockup__title`. *Why:* consistency in an accessibility standard the
   rest of the loop now meets; a thumb tapping a report title currently gets a 21px target.
2. **Tighten the `Every entry is saved` band.** At 1440 it is a full-width strip with two lines of copy
   and a large empty right half. *Where:* `resources/views/dashboard.blade.php`. *Why:* the one place
   on the dashboard where the layout does not follow the content weight — it reads as unfinished space
   next to the dense ruled panels below it.
3. **Un-nest the `TIME SERVED` card.** It is a rounded card sitting inside the hero card; a ruled
   figure block on the hero's own paper (as in the iteration-1 hero) would read as one instrument.
   *Where:* `dashboard.blade.php` hero markup. *Why:* the redundant border is the one spot where the
   paper world briefly looks like a card grid.
4. **Make the band rule self-documenting.** `.marg-stack > .marg-seg:last-child::after` depends on DOM
   order matching visual order under `column-reverse`; it is correct today but silent if the column
   markup is ever reordered. A one-line comment naming that dependency costs nothing.
   *Where:* `resources/css/app.css` near line 897. *Why:* protects the hit-area geometry from a future
   edit that would silently re-introduce mis-targeting.

**Not re-raised:** B2 (withdrawn, pre-existing working-tree state).
**Acknowledged as sound:** the deferred items — under-40px *inline prose* links (WCAG-exempt), and
`register-row__title` staying `display:inline` (its grid item is the wrapping `<div>`; serif, weight
and hover all verified applied).
