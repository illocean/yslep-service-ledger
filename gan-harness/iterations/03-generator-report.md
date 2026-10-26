# Generator report — iteration 03

**App:** http://127.0.0.1:8000 (left running, never restarted)
**Build:** `npm run build` clean and final — `app-DdOnQEhu.css` written 13:48:33, newest stylesheet source `resources/css/app.css` 13:45:53. The only edit after that build is a Blade attribute (`.assignment-chip` class on the dashboard's recent-row chips), which Vite does not consume. Everything below was measured read-only after the build.

---

## The regression that caused this iteration

Iteration 2 "fixed" the 40px tap-target item by **flooring the drawn bar** instead of growing the hit area: `.marg-stack { height: max(fill%, 7.5rem) }` + `.marg-seg { min-height: 2.5rem }`. Flexbox then distributed the clamped minimums, so every three-segment column rendered at exactly 120px and August 2025's true 60:360:120 split painted as three identical 40px bars. The chart lied about proportions, which is the same "no fabricated data" ban the whole dashboard is built on. The bar is now honest again and only the *target* is inflated.

## Fix list

| # | Issue | Resolution | Evidence |
|---|---|---|---|
| **N1** | Floored bar → false proportions | `.marg-stack` height is plain `{{ $monthFill }}%` again; `.marg-seg { min-height }` deleted; the 40px target is a transparent `::after` (`inset: 0 0 auto; height: max(100%, 2.5rem)`) that occupies no layout space | **Aug 2025** (540 min) stack 98.2px → **11.6 / 64.5 / 22.2** = 0.118/0.657/0.226 = 60:360:120 ✔. **Sep 2025** (420 min) stack 76.4px → 11.5 / 42.9 / 22.0 ✔. **Nov 2025** (840 min) 152.8px → 11.7 / 97.3 / 43.8 ✔. Column ratio Aug:Sep = 1.285 against the true 540:420 = 1.286 ✔ |
| | tap targets still ≥40px | `elementFromPoint` probe of every segment's contiguous reachable band (two horizontal scroll passes, 27 of 39 segments inside the viewport) | **min band 40px, 0 of 27 under 40** (Formation 12px bar → 42px band, Parish 64 → 65, Social 22 → 40). The band's own geometry: `height 40px, top −18.84px` on the topmost segment |
| | overlap resolution | Where two bands meet, the lower segment wins (`nth-of-type(1..3)` z-index 3/2/1), and the topmost segment's band grows *upward* into the empty air above the column (`.marg-stack > .marg-seg:last-child::after { inset: auto 0 0 }`) | Probe: every sampled point inside a band resolves to that segment's own anchor — a click always lands on the bar under the pointer, never on a neighbour |
| 2 | Snapshot detail scrolled horizontally at 390px | `min-width: 0` on `.paper-panel` (the `min-width: auto` grid item was being pinned to 712px by `table.ledger-table`) | 390px: page `scrollWidth 390 = clientWidth 390`; panel 358px; table 668px inside a 314px `overflow-x:auto` scroller with **354px of internal scroll**, so the full table is still reachable |
| 3 | `.assignment-chip` 84×28 | `min-height: 2.5rem` on the class, and the dashboard's recent-row chips now actually pass `class="assignment-chip"` (the component only emitted its Tailwind utilities before, which is why they never inherited the stamp language) | 5 chips on `/`, all **40px** tall, all links to the report |
| 4 | UA `auto 1px` ring on brand + skip link | `.brand-lockup__title:focus-visible, .skip-link:focus-visible { outline: 2px solid var(--ledger-accent-ink); outline-offset: 2px }` (skip link gained a `skip-link` hook in the layout) | Tab 1 → skip link `2px solid rgb(156,58,26)`; Tab 4 → brand lockup `2px solid rgb(156,58,26)`; nav/secondary/danger unchanged at the same ring |
| 5 | Wrong claim in the iteration-2 report | `min-w-full` removed from the dashboard table (the utility beat the component-layer rule, so the computed `min-width` was `100%` and the 607px width was incidental); `.index-table { min-width: 36rem }` is now the single source of that floor | 390px: computed `min-width 576px`, table 607px, 291px internal scroll, page does not scroll. 1440px: 1230px, no scrollbar. Both the code and the corrected line in `02-generator-report.md` agree |
| 6 | Ledger hierarchy / voices | Chart now speaks in Fraunces — `.marg-total` (0.8rem serif figures over each column), `.marg-month` (serif small-caps month stamps), `.funnel-row__num` (serif numerals) — and the figure caption states the instrument's rule: *"Every month from Aug 2025 — Aug 2026 on one shared scale, split by index. The tallest column is March 2026 at 17 hr 30 min."* The funnel, coverage strip and ruled tables keep their ruled/typographic treatment; no new rounded cards | 1440px: chart 1214px wide with 91px columns, index table 1230px, head spread still 2 columns, `scrollWidth 1440 = clientWidth 1440` |

## Regression sweep (all post-build, read-only)

- **Console:** 17 console logs across `/`, `/reports`, `/reports/{tag}`, `/indexes/formation`, `/academic-year-snapshots`, `/academic-year-snapshots/{id}` → **0** `[ERROR]` / `[WARNING]` lines.
- **Click budgets:** chart drill-in re-measured — one tap on the Parish March 2026 segment → `/indexes/parish_involvement?month=2026-03`, heading "Parish Involvement", "5 entries". All other budgets untouched (no route, form, or event changed this iteration).
- **Data integrity:** no aggregation logic changed. Every figure the evaluator cross-checked in iteration 2 (111 entries / 167 hr / busiest 2026-03 = 1 050 min / 13 of 13 months) still renders from the same server-side aggregates; no academic year is inferred ("0 of 111 entries carry an academic year").
- **CSS discipline:** scripted duplicate check over the 139+ custom class definitions still returns no duplicates, and every definition except the dynamically emitted `fill-{formation,social_apostolate,parish_involvement}` is referenced by a Blade file. Nothing was added without a consumer; the `stackFloor` blade variable and its inline `max(...)` clamp are gone.
- **Boundaries:** `.playwright-cli/` holds 0 files again (the 125 artifacts this session's verification produced were removed; the 18 tracked deletions and the two root PNGs were not touched), no dependency, migration, schema or route change, and no asserted test string was modified — `php artisan test` was not run, per instruction.

## Changed files

```
resources/css/app.css                    (hit-band overlay + z-order rule, .assignment-chip floor,
                                         .paper-panel min-width: 0, skip/brand rings, Fraunces voices)
resources/views/dashboard.blade.php      (plain proportional stack height, stackFloor removed,
                                         scale-note caption, .assignment-chip class on recent chips)
resources/views/layouts/app.blade.php    (skip-link hook class)
gan-harness/iterations/02-generator-report.md   (correction to the wrong index-table claim)
```

## Deliberately deferred

1. **Exhaustive 40px bands for *every* month.** A stacked column whose own stack is shorter than 3 × 40px cannot give all three segments non-overlapping 40px targets without overlap or drawing outside the column; the current rule gives each segment its painted bar plus a 40px band, and where a neighbour's paint sits inside a band, the neighbour wins so the click always opens the bar you can see. Bands measured 40px+ on every probed column.
2. **Under-40px inline links** (back links like "← All academic year snapshots", "Open Formation entry"). These are inline links inside prose; WCAG 2.2 exempts inline targets, and padding them out would break the ruled-ledger rhythm.
3. **`register-row__title` stays `display:inline`** (unchanged from iteration 2): its grid item is the wrapping `<div>`, and serif/weight/hover all apply.
4. **Month lens after a save** still returns to the unfiltered scope — threading `month` through the `return_*` redirect contract was out of scope for a view lens.