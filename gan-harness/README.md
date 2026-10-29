# gan-harness — YSLEP Service Ledger UI design loop

A GAN-style agentic design loop: a **Generator** (the coding agent) produces a
candidate UI build, and an **Evaluator** (the verification agent) scores it
against a fixed rubric, then feeds the failing issues back as the next prompt.
The loop is **closed** as of iteration 04 (PASS, 8.68/10).

## Status

| Iteration | Generator report | Evaluator score | Verdict |
|-----------|------------------|-----------------|---------|
| 01 | `iterations/01-generator-report.md` | `iterations/01-evaluator-score.md` | FAIL (2 blockers) |
| 02 | `iterations/02-generator-report.md` | `iterations/02-evaluator-score.md` | PASS 7.00 |
| 03 | `iterations/03-generator-report.md` | `iterations/03-evaluator-score.md` | PASS 8.25 |
| 04 | `iterations/04-generator-report.md` | `iterations/04-evaluator-score.md` | **PASS 8.68 (loop closed)** |

## The protocol (re-runnable)

1. **Spec** — `spec.md` is the single source of truth for scope, product truths,
   hard constraints, and the per-iteration verification contract. Never edit the
   constraints to make a build pass.
2. **Generate** — the Generator reads the latest evaluator scorecard, fixes the
   listed issues, rebuilds (`npm run build`), and writes
   `iterations/NN-generator-report.md` with a row-per-fix evidence table (all
   values read live from the DOM at `http://127.0.0.1:8000`).
3. **Evaluate** — the Evaluator runs `iterations/NN-evaluator-score.md` scoring
   each axis 0–10, weighted per the rubric (`eval-rubric.md`). **Pass gate:**
   weighted total ≥ 7.5 AND every axis ≥ 6 AND zero open blockers.
4. **Gate** — before a verdict is accepted, run the final gate:
   `pwsh gan-harness/final-gate.ps1 -SkipDetect`
   which runs build → tests → pint → (optional) anti-slop detect.

## Acceptance criteria (from `spec.md` + `eval-rubric.md`)

- Score trajectory is monotonic non-decreasing, or regressions are explicitly justified.
- `npm run build` succeeds with 0 errors.
- `php artisan test --compact` is green (run **serially** — see
  `.ai/rules/tests.md`; the final-gate script enforces a concurrent-PHP guard).
- `vendor/bin/pint --dirty --format agent` passes.
- No new blocker-level issues introduced between iterations.

## Reproducing an iteration

```ps1
# 1. start the app (in one shell)
composer run dev

# 2. make Generator changes, rebuild, then verify
pwsh gan-harness/final-gate.ps1 -SkipDetect
```

## Key artifacts

- `spec.md` — constraints, product truths, verification contract.
- `eval-rubric.md` — scoring axes, weights, pass gate, blocker definitions.
- `final-gate.ps1` — the runnable end-of-iteration verification gate.
- `iterations/` — per-iteration generator reports and evaluator scorecards.

## Notes

- The loop targets a **single-user localhost tool** with no auth (spec.md hard
  constraint 2). Do not add auth or change routes/form constraints.
- Frontend is Blade + Alpine 3 + Tailwind 4 (no Livewire, no Filament). See
  `spec.md` hard constraints 1 and 4.
- Verification is always from the **real DOM**, never screenshots (`.ai/rules/views.md`).
