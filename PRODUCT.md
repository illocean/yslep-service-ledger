# PRODUCT — YSLEP Service Ledger

*Inferred from the codebase, docs/ and live data (no interview was available). Assumptions labeled.*

## What it is
A single-user local web tool for keeping a congregation's (YSLEP) service record: three live
indexes of service entries (Formation, Social Apostolate, Parish Involvement), two-way synced with
an Obsidian markdown vault that stays the master copy, plus saved reports that lock entries and
academic-year snapshots that archive reports for submission/review.

## Operator & scene (assumption: single operator, the formation/chronicler director, local machine)
The operator opens the dashboard to answer: *are we on track, what changed, what still needs to be
saved into a report?* Data entry usually happens in Obsidian; the web UI is for reviewing,
organizing into reports, and archiving by academic year. Use is periodic (weekly/monthly), not
real-time monitoring.

## Product truths (from code — never violate)
- Three indexes share the same entry lifecycle: `served_on`, optional `academic_year`, a
  time range (duration derived), plus type-specific fields (cycle/module/title; about;
  role_in_activity).
- Entries live "unassigned" until captured into a `ReportGroup`; captured entries are locked in the
  live view. `AcademicYearSnapshot` freezes selected reports into an explicit academic year.
- **Academic year is optional and must never be inferred** — explicitly requires the user's year
  and selected reports; existing assignments never move on their own.
- Obsidian sync is two-way with conflict detection; sync must not be hidden behind the redesign.
- No authentication exists or is planned (localhost tool).

## Success signals for this redesign
1. The dashboard gives a decision-ready overview in one screen (trend, coverage, assignment
   funnel) with every figure traceable to the DB.
2. Key journeys meet the click budgets in `gan-harness/spec.md`.
3. The UI reads unmistakably as *this* ledger — deepened paper-ledger craft, not a generic
   dashboard, not AI slop.

## Non-goals
Multi-user/auth, i18n, dark mode, global search, new domain features, leaving the paper world.
