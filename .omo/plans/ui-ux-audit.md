# ui-ux-audit - Work Plan

## TL;DR (For humans)

**What you'll get:** A presentation-layer polish pass over the YSLEP Service Ledger — the paper-ledger identity stays (cream palette, Fraunces serif, Manrope body, ledger-grid texture). Three waves: (1) accessibility + data-trust, (2) workflow friction, (3) polish/dead-code. This file IS the requested `ui-ux-audit.md` deliverable: findings per view, design tokens, prioritized plan, component-by-component change list.

**Why this approach:** Every fix is either a Tailwind v4 `@theme` token, a CSS rule, or vanilla JS in `resources/js/app.js`. Zero new dependencies. No touch to Obsidian sync, YAML parsing, lock rules, or archive mechanics — three behavior-change ideas are flagged as proposals, not built.

**What it will NOT do:** No redesign, no new color identity, no collapse of the three index types, no change to scope filtering or record locking, no new dependencies, no controller/service logic changes.

**Effort:** ~14 focused edits across `resources/css/app.css`, 6 Blade views, `resources/js/app.js`; one file deleted (`ReportRecordManager.tsx`). Three commits (one per wave).

**Risk:** Low. Highest-risk item is the `text-stone-500` → `text-stone-600` sweep (visual only) and the accordion restructure on `reports/show` (markup wrap, no logic). `tests/Feature/DashboardSyncTest.php` asserts data attributes that must survive.

**Decisions:** D1-D10 (see Scope). Contrast targets measured: stone-500 = 4.1:1 on cream (fails AA), accent #c94d24 = 4.0:1 (fails), replacements #57534e = 6.6:1 and #9c3a1a = 6.0:1 (pass).

## Scope

### Findings (by view)

Severity: **blocker** = a11y regression or data-trust breach; **high** = AA contrast fail or meaningful workflow loss; **medium** = ambiguity/consistency; **polish** = refinement.

**Global (layout + app.css)**

| # | Issue | Why it matters | Severity |
|---|-------|----------------|----------|
| G1 | `text-stone-500` (#78716c) on cream paper = 4.1:1; accent kicker `--ledger-accent` #c94d24 on cream = 4.0:1. Both below 4.5:1 AA for the small label/kicker text that uses them. | Small muted labels and section kickers are the most-read microcopy; under AA on the primary background. | **high** |
| G2 | `details>summary` accordions and `.report-checkbox` have no custom `:focus-visible` ring (UA default only); `select.form-input` same. | Keyboard navigation exists (skip-link, focus rings elsewhere) but these controls render an inconsistent/weak ring on cream. | medium |
| G3 | `.clean-scroll` sets `scrollbar-width: none` and is used on horizontally-scrollable tables (`academic-year-snapshots/show` L121, `dashboard` L219). | Users cannot tell a dense ledger table scrolls sideways on narrow viewports — mobile users miss columns. | medium |
| G4 | Radius values ad hoc: `2rem / 1.75rem / 1.5rem / 1.4rem / 1.35rem / 1.25rem / 1.2rem / 1.1rem` across views and CSS. | Styling is not systematized (criterion 2); every new card picks a new number. | medium |
| G5 | `.form-input` uses `border-radius: 999px` (pill) for text/date/time inputs; pill clip can cut native date/time picker affordances. | Unusual for data-entry fields; inconsistent with card radii; picker icons clip. | polish |
| G6 | `.primary-button`, `.secondary-button`, `.danger-button` have no `:disabled` styling; `@disabled` used on the snapshot create button. | Disabled control looks active — users click a dead button. | polish |
| G7 | Dead code: `.quick-sidebar`, `.quick-sidebar__summary`, `.quick-sidebar__summary:hover`, `.quick-sidebar__badge`, `.report-row-locked` in app.css (no markup uses them); `resources/js/components/ReportRecordManager.tsx` never imported (server-rendered `reports/show` supersedes it). | Maintenance debt; two inline-edit implementations confuse future editors. | polish |
| G8 | `.page-enter` applies slide-in to main's direct children only; record accordions are nested inside one ledger `<section>` child — no per-record animation, no conflict with details toggling. Reduced-motion media query present and correct. | Verified non-issue (criterion 12). | — |

**Dashboard**

| # | Issue | Why it matters | Severity |
|---|-------|----------------|----------|
| D1 | `DashboardController::__invoke` calls `$syncService->syncAll()` on every load with zero UI acknowledgment (no "last synced", no in-progress state). | Silent sync on a ledger app is a trust problem (criterion 6); slow vault stalls the page with no feedback. | **high** (fix = flag F2; presentation has nothing to render until service exposes a stamp) |
| D2 | Save-report pick table (`data-save-group-card`) scrolls in `max-h-[30rem]` with hidden scrollbar (G3). | Same as G3. | medium |
| D3 | "Saved Reports" sidebar card: count + "Open Saved Reports" + Latest — supports, does not compete with, stat cards. Hierarchy sound. | Verified non-issue (criterion 1). | — |

**Indexes Show**

| # | Issue | Why it matters | Severity |
|---|-------|----------------|----------|
| I1 | Record accordion affordance: summary right side is a static circular **"+"** plus "Edit details" (L322-325). "+" reads as *add*, never changes when open, and contradicts the rotating-chevron pattern used by every other accordion (dashboard L177, add-record L152). | Ambiguous expand/collapse state (criterion 3); two visual vocabularies for the same interaction. | **high** |
| I2 | Scope switch (pills + auto-submit select) is a full reload; long ledger list jumps back to top. Browser scroll restoration is unreliable when DOM height changes. | Loses the user's place (criterion 4). | medium |
| I3 | Third stat card kicker says "Scope" but lists **other indexes'** totals (L93). | Mislabeled; reads as a scope control, is cross-index navigation. | medium |
| I4 | "Card Header" section label (L110) — internal jargon. | Not user-facing language. | polish |
| I5 | No protection against navigating away mid-edit (dirty state not tracked). | Typed edits are silently lost (criterion 5). | **high** |
| I6 | In Report scope, record chip says "Saved record"; in live scope, "Live record" — good. `old('editor_key')` reopens the right accordion on validation errors and repopulates values — solid. | Verified strengths, keep. | — |

**Reports Index**

| # | Issue | Why it matters | Severity |
|---|-------|----------------|----------|
| R1 | "Sync from Obsidian" button (L38-41): plain POST, no pending state; sync pulls from vault and can be slow. | No in-progress feedback (criterion 6). | **high** |
| R2 | Sync failure (vault unreachable) surfaces as a Laravel error page — no graceful message. | Trust problem (criterion 6). | **high** (fix = flag F1) |
| R3 | Empty state ("No reports yet" + Back to Overview) is designed and on-tone. | Verified non-issue. | — |

**Reports Show**

| # | Issue | Why it matters | Severity |
|---|-------|----------------|----------|
| S1 | Inline edit forms are **always expanded** under every record card (L219-267), while `indexes/show` hides edits behind accordions. Two editing patterns in one app. | Inconsistent mental model (criterion 5); long pages. | medium |
| S2 | Record chip is the type label only ("Formation") — no saved/locked status. `indexes/show` report scope says "Saved record"; dashboard says "Saved in X". Locked-vs-editable not communicated at rest (criterion 7). | User cannot tell a report record is a snapshot member vs a live entry. | medium |
| S3 | Sync panel duplicates R1/R2 (button, no pending, no failure path). | Same as R1. | **high** |

**Academic Year Snapshots**

| # | Issue | Why it matters | Severity |
|---|-------|----------------|----------|
| A1 | Snapshot show renders ledger tables visually identical to live ledgers; no persistent "you are in the archive" indicator beyond nav state. | Archive data can be mistaken for live data (criterion 8). | **high** |
| A2 | Formation table = 8 columns, horizontal scroll, scrollbar hidden (G3). | Worst mobile offender (criterion 10). | medium |
| A3 | Create button `@disabled` with no disabled styling (G6). | Dead-looking button. | polish |
| A4 | Builder/sidebar are well-separated; empty states on-tone. | Verified strengths. | — |

**Alerts partial**

| # | Issue | Why it matters | Severity |
|---|-------|----------------|----------|
| AL1 | Only two tones: emerald (status) + rose (errors). "No saved report note changes were detected" flashes green success — misleading tone for a zero-op. No neutral/info tone exists. | Tone misleads (criterion 6). Fix needs controller flash key → flag F1 (presentation partial is not wired without a behavior change). | medium (flag F1) |

### Design tokens (consolidated — target state)

Tailwind v4 is CSS-first: add to `@theme` in `resources/css/app.css` (below the existing `--font-*` lines). Keep existing `:root` `--ledger-*` vars; add `--ledger-accent-ink`.

```css
@theme {
    --color-paper-50: #fbf7ef;
    --color-paper-100: #f5ede0;
    --color-paper-200: #efe4d4;
    --color-ink-900: #201612;   /* matches --ledger-ink */
    --color-ink-700: #3d2e26;
    --color-ink-600: #57443a;   /* muted body text on cream, 6.6:1 */
    --color-accent-600: #c94d24; /* decorative strokes/chips only */
    --color-accent-700: #9c3a1a; /* accent TEXT on paper, 6.0:1 */
    --color-saved-700: #236654;  /* saved/locked ink, 5.8:1 */
    --color-danger-700: #991b1b;
    --radius-panel: 2rem;        /* hero panels */
    --radius-stat: 1.75rem;      /* stat cards */
    --radius-card: 1.5rem;       /* inner cards, sections */
    --radius-cell: 1.25rem;      /* profile cells, chips shells */
    --radius-control: 0.9rem;    /* form inputs */
    --radius-pill: 999px;        /* buttons, chips, nav pills */
}
```

`:root` additions:

```css
:root {
    /* existing vars unchanged */
    --ledger-accent-ink: #9c3a1a;
}
```

Mapping for the radius sweep (G4): `2rem`→panel, `1.75rem`→stat, `1.5rem`→card, `1.25rem`→cell; `1.4rem/1.35rem/1.2rem/1.1rem` (archive cards, chips, compact cells) → cell or card by context — worker picks the closest token per instance (list in todo 10). Type scale stays utility-based (Fraunces for headings, Manrope body — already in `--font-*`). Spacing keeps the Tailwind default scale.

### Flagged proposals (behavior changes — NOT implemented)

- **F1 — Graceful sync failure:** `ReportObsidianSyncController::store` should wrap `pullFromVault()` in try/catch and flash `warning` (new amber block in `partials/alerts.blade.php` would render it). Requires controller change; presentation is ready when the controller flashes `warning`.
- **F2 — "Last synced" stamp:** Dashboard and sync views should show when the vault was last pulled. Requires `ObsidianSyncService` to record/return a sync timestamp — service-layer change, out of scope.
- **F3 — Lock semantics clarity:** "Locked in reports" is currently presentation (chips). If a locked live entry should not be editable in a report scope, that is a domain rule change — out of scope.

### Must-NOT-Have

- No edits to: `app/Services/ObsidianSyncService.php`, `ReportGroupVaultSyncService`, YAML front-matter parsing, lock/assignment rules, `AcademicYearSnapshot` mechanics.
- No new npm/composer dependencies.
- No deletion of `data-save-group-card` / `data-available-archive-report` / `data-hidden-archive-report` attributes — asserted by `tests/Feature/DashboardSyncTest.php` L309-310, 375-377.
- No removal or regression of: skip-to-content link (`layouts/app.blade.php` L37), `:focus-visible` rules, `prefers-reduced-motion` block (app.css L49-56), staggered `.page-enter`.
- No change to scope filtering, report locking, or archive behavior — even where it looks like a UX win.

## Verification strategy

- **Contrast:** after wave 1, run a WCAG AA check on the three changed pairs: `#57534e` on `#f5ede0` (expect ≥4.5:1), `#9c3a1a` on `#f5ede0` (expect ≥4.5:1), white on the primary-button gradient (expect ≥4.5:1). Any contrast-checker tool (e.g. `npx` online check or a 5-line PHP/JS calc) — record the numbers in the commit message.
- **Tests:** `php artisan test` after every wave — full suite must stay green (`DashboardSyncTest` guards the data attributes and scope rendering).
- **Build:** `npm run build` (vite) after wave 3 (TSX deletion must not break the bundle).
- **Browser QA (agent-executed, Playwright):** keyboard-only Tab through accordions on `indexes/show` and dashboard save-group (every focus ring visible on cream); archive banner visible on `academic-year-snapshots/show` only; sync button shows "Syncing…" + disabled while the POST is in flight (throttle network in devtools); beforeunload fires after typing in an edit form and not after submit; viewport 375px — archive table scrolls horizontally with visible scrollbar; `prefers-reduced-motion` emulation — no page-enter movement.
- **Grep gates:** after wave 1, `text-stone-500` count in `resources/views` = 0; after wave 3, `rounded-\[` count in views + app.css = 0 and `quick-sidebar|report-row-locked|ReportRecordManager` = 0 (source files only, exclude `storage/`).

## Execution strategy

Three waves, one commit each. All JS is vanilla and lives in `resources/js/app.js`. All CSS changes live in `resources/css/app.css` unless a view needs a class swap.

**Wave 1 — accessibility + data-trust (G1, G2, A1, R1/S3, S2, I5):** contrast sweep, focus-visible for summary/checkbox/select, archive mode banner + muted table treatment on snapshot show, sync button pending state, saved-record chip on report records, beforeunload dirty guard.

**Wave 2 — workflow friction (I1, I2, I3, I4, S1):** accordion affordance standardized (chevron + open tint, edit forms collapsed behind accordion on `reports/show`), scope-switch scroll restore, relabels.

**Wave 3 — polish (G4, G5, G3, G6, G7):** radius token sweep, input radius, scrollbar affordance, disabled button styles, dead-code deletion.

## Todos

- [x] 1. `resources/css/app.css`: Fix label/kicker contrast — set `.section-kicker { color: var(--ledger-accent-ink) }` (add `--ledger-accent-ink: #9c3a1a` to `:root`), add `--ledger-accent-ink` near L20, leave `--ledger-accent: #c94d24` for decorative borders/chips only (e.g. dashboard L65 `border-[color:var(--ledger-accent)]`, reports/show L55). References: app.css L16-23, L118-125. Acceptance: `.section-kicker` resolves to #9c3a1a; grep `ledger-accent-ink` ≥1. QA happy: computed contrast ≥4.5:1 (record value). QA failure: `text-stone-500` still present in views (see todo 2) → contrast stays broken → fix before commit. Commit: `style: fix kicker contrast on cream palette`.
- [x] 2. All content views (`dashboard.blade.php`, `indexes/show.blade.php`, `reports/index.blade.php`, `reports/show.blade.php`, `academic-year-snapshots/index.blade.php`, `academic-year-snapshots/show.blade.php`): sweep `text-stone-500` → `text-stone-600` (#57534e, 6.6:1 on cream; also passes on white cards). Exact instances: dashboard L54, L58, L68, L112, L152, L213-214; indexes/show L100, L152, L213-214; reports/index L87; reports/show L15, L204, L218; snapshots/index L114, L160; snapshots/show — none, verify. Do NOT change `text-stone-600` occurrences (already compliant). Acceptance: grep `text-stone-500` across `resources/views` = 0. QA happy: computed contrast of #57534e on #f5ede0 ≥4.5:1. QA failure: any remaining `text-stone-500` → revert check. Commit: `style: bump muted text contrast to AA on cream`.
- [x] 3. `resources/css/app.css`: add focus-visible rings — `.report-checkbox:focus-visible { outline: 2px solid var(--ledger-accent-ink); outline-offset: 2px; }`, `select.form-input:focus-visible` (reuse existing form-input ring, add `select` selector to L152 rule), `details > summary:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(156,58,26,0.35); border-radius: inherit; }`. References: app.css L152-157, L447-455. Acceptance: `:focus-visible` selectors exist for checkbox, select, summary. QA happy (Playwright): Tab through `indexes/show` — every control shows a visible ring on cream. QA failure: summary focus invisible (UA ring suppressed) → ring must appear. Commit: `style: focus-visible rings for accordions, checkbox, select`.
- [x] 4. `resources/views/academic-year-snapshots/show.blade.php`: add persistent archive-mode banner directly under the hero section (after L44): a `role="status"` strip inside a `paper-panel`-style box — left: `section-kicker` text "Academic Year Archive" + a stone-colored chip (reuse `.header-status__chip--archive` visual language: `#57534e` dot), copy: "Read-only archive — edits happen in live records, saved reports, or a new snapshot. Records here cannot be changed in place." Also mute the table containers: L120 `bg-white/80` → `bg-stone-100/70`, header `bg-stone-950/[0.03]` → `bg-stone-900/[0.04]` (subtle, keeps paper tone). Acceptance: banner text + archive chip render only on this view; contrast of banner body ≥4.5:1. QA happy: snapshot show → banner visible, live views (dashboard, indexes/show, reports/*) → no banner. QA failure: banner appears on reports.show → scoping bug → fix selector. Commit: `style: persistent archive-mode indicator on snapshot view`.
- [x] 5. `resources/js/app.js` + `resources/views/reports/index.blade.php` (L38-41) + `resources/views/reports/show.blade.php` (L87-90): sync pending state. Add `class="sync-form"` to both sync `<form>`s; in app.js add a submit handler: on submit, `form.setAttribute('aria-busy','true')`, find `button[type=submit]`, `disabled = true`, swap text to "Syncing…" and prepend a `role="status"` spinner span (`<span aria-hidden="true" class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent align-middle" />`). Let the POST proceed natively (no preventDefault). Acceptance: both forms carry `sync-form`; handler registered in DOMContentLoaded. QA happy (Playwright, network throttled): click Sync → button disabled + "Syncing…" until navigation. QA failure: form submits anyway with JS disabled → native POST still works (handler must not preventDefault). Commit: `style: sync-from-obsidian pending state`.
- [x] 6. `resources/views/reports/show.blade.php`: saved-status chip on every record (L182-185). Change chip label from `{{ $type->label() }}` to `Saved record · {{ $type->label() }}` (keep `assignment-chip--saved` styling), matching the "Saved record" chip in `indexes/show` report scope (L304). Acceptance: grep "Saved record ·" ≥1 in reports/show; every report record shows the chip at rest. QA happy: report show → each record carries "Saved record · Formation/Social Apostolate/Parish Involvement". QA failure: live-scope records on indexes/show must NOT show "Saved record ·" (they show "Live record" — verify unchanged). Commit: `style: saved-status chip on report records`.
- [x] 7. `resources/js/app.js`: dirty-edit navigation guard. On `DOMContentLoaded`: attach `input`/`change` listeners (delegated on `document`) for `form.record-form-shell input, form.record-form-shell select` → set `form.dataset.dirty = 'true'`; attach `submit` listener that clears it; `window.addEventListener('beforeunload', e => { if (any form[data-dirty]) { e.preventDefault(); e.returnValue = ''; } })`. Applies to all three views using `record-form-shell` (indexes/show, reports/show) + dashboard save-group form. Acceptance: guard code in app.js; no change to server forms. QA happy (Playwright): type into an edit input → navigate → dialog appears; cancel, then submit → no dialog. QA failure: dialog fires after a clean submit → dirty flag not cleared → fix handler. Commit: `style: warn before losing unsaved inline edits`.
- [x] 8. `resources/views/indexes/show.blade.php` (L322-325) + `resources/views/reports/show.blade.php` (record cards): unify accordion affordance. (a) indexes/show: replace the `+` circle + "Edit details" block with the chevron pattern already used at L152-154 (`<svg … stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>` wrapped in `transition-transform group-open:rotate-180`); keep "Edit details" text next to it. (b) reports/show: wrap each record's delete form + edit form (L210-267) in `<details class="report-record-card group">` with a `<summary>` showing the headline row + chevron (same pattern); delete form stays inside the expanded body next to the edit form. Keep `group` class for `group-open:` to work. Acceptance: no `+` circle remains in indexes/show; reports/show record bodies are collapsed by default; both use the rotating chevron. QA happy (Playwright, keyboard): Tab to summary → Enter expands → chevron rotated 180°, edit fields reachable; collapse works. QA failure: `group-open` styling missing after wrap → chevron never rotates → add `group` class. Commit: `style: standardize record accordion affordance`.
- [x] 9. `resources/js/app.js` + `resources/views/indexes/show.blade.php`: preserve scroll position across scope switch. app.js: extend the existing `data-auto-submit` handler (L4-8) — before `form.submit()`, `sessionStorage.setItem('yslep-scroll', String(window.scrollY))`; on `DOMContentLoaded`, if key present, `const y = parseInt(...); sessionStorage.removeItem(...); window.scrollTo({ top: y, behavior: 'instant' })` inside `requestAnimationFrame`. Acceptance: handler stores/restores; no anchor needed. QA happy (Playwright): scroll down in All Time, switch to Unsaved → position roughly preserved (same element if present). QA failure: restore jumps to top → key cleared too early → move removal into scrollTo callback. Commit: `style: preserve scroll across scope switch`.
- [x] 10. `resources/views/indexes/show.blade.php`: relabels — L93 `section-kicker">Scope` → "Other Indexes" (card still lists other-index totals); L110 `section-kicker">Card Header` → "Profile". Acceptance: grep shows both new strings, zero old. QA happy: labels render. QA failure: n/a (pure copy). Commit: `style: relabel scope card and profile section`.
- [x] 11. `resources/css/app.css` + all views: radius token sweep. Add `--radius-*` tokens to `@theme` (Scope → Design tokens). Replace arbitrary radii: `2rem`→`rounded-panel`, `1.75rem`→`rounded-stat`, `1.5rem`→`rounded-card`, `1.25rem`→`rounded-cell`, `0.9rem`→`rounded-control` (form-input), `999px`→`rounded-pill`; collapse `1.4rem/1.35rem/1.2rem/1.1rem` (archive cards L663/L671/L627/L655, chips) to nearest token (cell or card) per instance. Explicit file sweep: `layouts/app.blade.php`, `dashboard.blade.php`, `indexes/show.blade.php`, `reports/index.blade.php`, `reports/show.blade.php`, `academic-year-snapshots/*`, `app.css` (component classes + media queries). Acceptance: grep `rounded-\[` in `resources/views` + `resources/css/app.css` = 0; `rounded-panel|rounded-stat|rounded-card|rounded-cell|rounded-control|rounded-pill` present. QA happy: build passes, layout renders (visual diff none beyond radius normalization). QA failure: a token class doesn't exist (typo) → purge check: `npx tailwindcss` output includes each token class. Commit: `style: systematize radius tokens`.
- [x] 12. `resources/css/app.css`: input radius — `.form-input` `border-radius: 999px` → `var(--radius-control)` (0.9rem). Keep hover/focus transitions. Acceptance: L137 uses the token. QA happy: date/time inputs no longer clip native picker icons (Playwright, 375px viewport). QA failure: pill appearance remains → verify token spelling. Commit: `style: form-input radius token`.
- [x] 13. `resources/css/app.css` + `academic-year-snapshots/show.blade.php` (L121) + `dashboard.blade.php` (L219): scrollbar affordance. Remove `clean-scroll` class from the two horizontally-scrollable table wrappers (keep it on the nav `nav-cluster__links` L61 and any vertical-only scroller); table wrappers get default scrollbars. Optionally add `overflow-x: auto` already present — default styling suffices. Acceptance: `.clean-scroll` removed from both table wrappers; still present on nav. QA happy (Playwright, 375px): archive table scrolls, horizontal scrollbar visible. QA failure: scrollbar hidden → class removal missed. Commit: `style: visible horizontal scroll on ledger tables`.
- [x] 14. `resources/css/app.css`: disabled button states — add `.primary-button:disabled, .secondary-button:disabled, .danger-button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }` (and suppress hover lift). Acceptance: three `:disabled` rules exist. QA happy (Playwright): snapshot create button on `academic-year-snapshots/index` with zero available reports renders at 0.5 opacity, not clickable. QA failure: button still lifts on hover → hover rules leak → scope `:hover:not(:disabled)`. Commit: `style: disabled button states`.
- [x] 15. Dead code deletion: (a) `resources/css/app.css` — remove `.quick-sidebar`, `.quick-sidebar__summary`, `.quick-sidebar__summary::-webkit-details-marker`, `.quick-sidebar__summary:hover`, `.quick-sidebar__badge`, `.report-row-locked` (L390-436, L462-464); (b) delete `resources/js/components/ReportRecordManager.tsx`. Do NOT touch `data-save-group-card` / `data-available-archive-report` / `data-hidden-archive-report` attributes (tests assert them). Acceptance: grep `quick-sidebar|report-row-locked|ReportRecordManager` over `resources/` (excluding `storage/`) = 0. QA happy: `npm run build` passes; `php artisan test` green. QA failure: test asserts a deleted attribute → attribute was wrongly removed → restore. Commit: `chore: remove dead CSS and unused report manager component`.

## Final verification wave

Runs in parallel after all todos; ALL must APPROVE. Each verifier gets this plan's path and the wave list above.

- [x] F1. Plan compliance audit — every todo's Acceptance is verifiable in the diff; no Must-NOT-Have violated (grep gates from Verification strategy rerun); flagged proposals F1-F3 appear only in the plan, never in code. Verdict: APPROVE / CHANGES.
- [x] F2. Code quality review — `resources/css/app.css`, edited views, `resources/js/app.js` reviewed for token discipline (no new arbitrary radii), no duplicated patterns, TSX deletion confirmed clean. Verdict: APPROVE / CHANGES.
- [x] F3. Real manual QA (Playwright, running app) — keyboard-only walkthrough: skip link, Tab order, accordions, inline edit save/cancel, scope switch scroll, sync pending state, archive banner, 375px archive table scroll, reduced-motion emulation. Verdict: APPROVE / CHANGES.
- [x] F4. Scope fidelity — diff touches only the 8 presentation files (6 views, app.css, app.js) + 1 deletion; `app/` (except zero files) and services untouched; tests green. Verdict: APPROVE / CHANGES.

## Commit strategy

One commit per wave, conventional style:
1. `style: a11y + data-trust — contrast, focus rings, archive indicator, sync feedback (todos 1-7)`
2. `style: workflow friction — accordion affordance, scroll restore, relabels (todos 8-10)`
3. `style: polish — radius tokens, scrollbar, disabled states, dead code (todos 11-15)`

Run `php artisan test` before each commit; `npm run build` before the third. Squash/merge strategy is the user's call at handoff.

## Success criteria

1. Every text element in the 6 audited views passes WCAG AA 4.5:1 on its actual background (measured, values in commits).
2. No accessibility regression: skip-to-content, focus-visible rings (now including accordions/checkbox/select), reduced-motion all verified working post-change.
3. Sync operations (index/report) show in-progress state on the button; failure path is flagged (F1) but documented, not silent.
4. Archive view is visually unmistakable (banner + muted tables); saved-record status visible at rest on every report record.
5. Accordion expand/collapse is unambiguous (rotating chevron everywhere), and scope switching preserves scroll.
6. Zero new dependencies; zero changes to Obsidian sync, YAML parsing, lock rules, or archive mechanics.
7. `php artisan test` green; `npm run build` green; paper-ledger identity (cream, Fraunces serif, Manrope, ledger-grid) intact.
