# ui-ux-audit — Draft

- slug: `ui-ux-audit`
- intent: **clear**
- review_required: **false** (no high-accuracy modifier; CLEAR path offers review at delivery)
- classification: Standard (presentation pass across ~10 files: 8 Blade views + app.css + app.js; 1 dead TSX)

## Decisions ledger

| # | Decision | Source |
|---|----------|--------|
| D1 | Deliverable `ui-ux-audit.md` **is** the plan itself; written as `.omo/plans/ui-ux-audit.md` carrying the user's required 4 sections (Findings / Design tokens / Prioritized plan / Component-by-component change list). No separate doc file. | User deliverable format = plan format |
| D2 | Zero new dependencies. All fixes via Tailwind v4 `@theme` tokens + vanilla JS in `resources/js/app.js`. | Ladder rung 3/4; directive requires justification for any new dep |
| D3 | Behavior-change items (sync failure catch→flash, "last synced" timestamp, lock semantics) are **flagged proposals only**, never implemented. | Directive constraint |
| D4 | Keep `data-save-group-card` / `data-available-archive-report` / `data-hidden-archive-report` attributes — asserted by `tests/Feature/DashboardSyncTest.php` (lines 309-310, 375-377). | Grep evidence |
| D5 | Delete dead code: `.quick-sidebar*` + `.report-row-locked` CSS (unused in any view), `resources/js/components/ReportRecordManager.tsx` (never imported; server-rendered `reports/show` supersedes it). | Grep evidence |
| D6 | Scope-switch scroll preservation via vanilla JS (store scrollY, restore after load); no fetch/Turbo rearchitecture. | Ladder rung 3 |
| D7 | Record-accordion affordance standardized to chevron + `group-open:rotate-180` (pattern already in `dashboard.blade.php` L171-180) on `indexes/show` + `reports/show`; "+" circle removed. | Consistency; existing pattern |
| D8 | `form-input` radius pill→`0.9rem` for text/date/time inputs (pill kept for buttons/chips). | Criterion 2/3 |
| D9 | Archive mode indicator: persistent banner + muted sepia treatment on `academic-year-snapshots/show`, reusing header "Archives" chip color language (gray #57534e). | Criterion 8 |
| D10 | Contrast targets (WCAG AA 4.5:1 normal text on cream #f5ede0): `text-stone-500` → `stone-600`/ink-600 #57534e (6.6:1); kicker accent text → #9c3a1a (5.8:1); accent #c94d24 kept for decorative strokes/chips only. | Measured: stone-500 4.1:1 FAIL, accent 4.0:1 FAIL on cream |

## Exploration ledger (evidence)

- Views: `layouts/app`, `dashboard`, `indexes/show`, `reports/index`, `reports/show`, `academic-year-snapshots/{index,show}`, `partials/alerts` — all read.
- CSS: `resources/css/app.css` (Tailwind v4, `@theme` fonts only; arbitrary radius values `2rem/1.75rem/1.5rem/1.4rem/1.35rem/1.25rem/1.2rem/1.1rem` ad hoc).
- JS: `app.js` (auto-submit + confirm only); `ReportRecordManager.tsx` unimported.
- Controllers: `Dashboard` (silent `syncAll()` per load), `IndexPageController` (scope enum), `ObsidianSyncController` (flash status), `ReportObsidianSyncController` (flash status, no failure path — exceptions bubble to error page).
- Tests: `DashboardSyncTest` asserts data attributes (D4).

## Approach

Presentation-layer pass: consolidate tokens in `app.css` `@theme`; fix contrast + focus-visible + scrollbar affordance; add archive-mode indicator; sync-button pending state; dirty-edit navigation guard; scope scroll restore; standardize accordion affordance + inline-edit pattern; relabel jargon ("Card Header"→"Profile", "Scope" card→"Other Indexes"); alert tone variants; delete dead code. Three phases: (1) a11y + data-trust, (2) workflow friction, (3) polish/animation. Behavior changes flagged only.

## Pending action

Write `.omo/plans/ui-ux-audit.md` (the user's 4-section audit spec + decision-complete todos) on approval.

- status: **awaiting-approval**
