# Eval rubric — YSLEP Service Ledger UI redesign (GAN design loop)

Score every axis 0–10 with **evidence from the real app** (DOM textContent, computed styles, console,
click counts, DB cross-checks). Impressions without evidence score ≤ 4. Overall score is the
weighted mean. **Pass: weighted ≥ 7.5, every axis ≥ 6, zero open blockers.**

### Design Quality (weight: 0.35)
The paper-ledger world, deepened. Typographic hierarchy that encodes importance (who can tell the
headline from the metadata at a glance?). Information design: the dashboard reads like an
instrument a chronicler trusts — ruled rows, honest empty states, deliberate rhythm and spacing,
contrast that holds (ink on cream at AA). Responsive integrity at 390px and 1440px: no overflow,
no orphaned elements, no 3-line-wrapping buttons. Does the screen answer "what happened, what needs
me" before any interaction?

### Originality (weight: 0.30)
Does this look like YSLEP's ledger and nothing else — or like every AI dashboard? Evidence of
distinctive, domain-derived craft: the chart drawn as ledger marginalia rather than a stock chart
card; status shown as a stamp, not a coloured pill farm; typography carrying personality (Fraunces
used with intent); layouts that follow the content (funnel, coverage strip, trend) rather than a
12-column template. Penalise: identical rounded cards grid, generic hero, three equal columns,
neon/dark reskin of a paper product, borrowed SaaS-dashboard look.

### Craft (weight: 0.25)
Pixel-level execution: alignment, consistent radius/shadow/spacing scale, exactly one definition
per CSS component class, no orphaned/duplicate rules, no `!important` battles, working focus rings,
reduced-motion honoured, `x-cloak` on Alpine surfaces, no layout shift on hover, sensible
`tabular-nums` on figures, correct `aria-*` and chart data tables, `sr-only` text where the visual
story needs a spoken one. Copy specifics over platitudes. Fewest-file, minimal-diff discipline:
no unneeded classes, no dead CSS left behind, no speculative abstractions in PHP/JS.

### Functionality (weight: 0.10)
Everything still works end-to-end: entry modals open/edit/save/delete, quick-add bar, scope
switcher (`?scope=`/`?report=`), save-group builder, report CRUD + Obsidian sync buttons, snapshot
builder, conflict flows untouched. Click budgets met (count real clicks in the browser).
`php artisan test --compact` green (serial), `npm run build` clean, zero console errors on the five
main pages, charts computed from the DB (cross-check one value per chart).

## Blockers (any one ⇒ iteration fails regardless of score)
- Build failure, test regression, console error, broken route/form/event contract.
- Fabricated or hardcoded chart value; inferred academic year.
- A11y regression: lost skip link, invisible focus, chart with no accessible data.
- Violation of a binding `.ai/rules` item or a hard constraint from `spec.md`.
