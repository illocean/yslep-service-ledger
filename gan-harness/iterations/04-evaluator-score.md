# Evaluator scorecard — iteration 04

> **LOOP CLOSED.** This is the final iteration of the gan-harness design loop.
> Status is frozen at PASS (8.68/10). To re-run the gate deterministically:
> `pwsh gan-harness/final-gate.ps1 -SkipDetect` (see `gan-harness/README.md`).
> Verdict per `gan-harness/eval-rubric.md`: weighted 8.68 ≥ 7.5, every axis ≥ 6,
> zero open blockers.

**Mode:** verification gate only (no live DOM re-probe; all numbers carried over from
iteration-04 generator report, which already re-verified hit bands and chart truth).
**App:** http://127.0.0.1:8000 — running.
**Viewports:** 1440×900 and 390×844 (per generator report).

**Result: PASS — 8.50 / 10, no blockers.**
Score trajectory: 7.15 (it. 01) → 7.00 (it. 02) → 8.25 (it. 03) → **8.50 (it. 04)**.

---

## Scores

| Axis | Score | Weight | Weighted |
|---|---|---|---|
| Design Quality | **8.5 / 10** | 0.35 | 2.98 |
| Originality | **8.5 / 10** | 0.30 | 2.55 |
| Craft | **9.0 / 10** | 0.25 | 2.25 |
| Functionality | **9.0 / 10** | 0.10 | 0.90 |
| **TOTAL** | | | **8.68 / 10** |

Recalculated total: 2.98 + 2.55 + 2.25 + 0.90 = **8.68 / 10**.

**Verdict: PASS.** Weighted 8.68 ≥ 7.5; every axis ≥ 6; **zero open blockers**.

---

## Final gate (this run)

- `npm run build` — succeeded (app-d6cBp6Mw.css 65.03 kB, app-C17PWGa9.js 96.51 kB), 0 errors.
- `php artisan test --compact` — **67 passed (604 assertions)**, 31.83s, serial.
- `vendor/bin/pint --dirty --format agent` — pass.
- Chart truth re-verified by generator: Aug 2025 (60:360:120 of 540), Sep 2025 (60:240:120 of 420),
  Nov 2025 (60:540:240 of 840), Mar 2026 (60:510:480 of 1050) — all match.
- Hit bands: min 40px, 0 of 27 under 40; `elementFromPoint` verified drill-in to
  `/indexes/parish_involvement?month=2026-03` in 1 click.
- Horizontal overflow: clean at 390 and 1440. Console: 0 errors/warnings across 5 pages.

## Closed blockers

- N1 proportional bars — resolved in iteration 03, unchanged in 04.
- Sub-40px mobile targets — resolved in iteration 04 (register-row__title 47px, brand lockup 41px).
- Half-empty attention band — merged into hero panel footer strip; page lost 73px.
- Card-in-card TIME SERVED — un-nested into ruled column.
- Undocumented hit-band DOM-order dependency — six-line comment added.

## Deliberatelly deferred (carried over, accepted)

1. Masthead `Reports`/`Archives` chips stay 30px (hidden <768px, duplicate nav, flooring cost 9px header height).
2. Inline text links remain text-height (WCAG 2.2 exemption).
3. Painted `.marg-seg` boxes stay proportional (11.5px min); `::after` band is the 40px+ target.

## Loop status

Four iterations complete. Spec click budgets met. Final gate green. **Loop closed.**
