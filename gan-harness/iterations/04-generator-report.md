# Generator report — iteration 04

**App:** http://127.0.0.1:8000 (left running)
**Scope:** craft polish only. Nothing was redesigned; the chart's proportional scale and its hit-band geometry are byte-identical to the iteration-3 state the evaluator passed.

---

## Fix list

| # | Issue | Resolution | Evidence (post-build, real DOM) |
|---|---|---|---|
| 1 | Sub-40px mobile targets: `a.register-row__title` 21px, brand lockup 22px, (plus the 35px card action) | `padding-block: 0.65rem; margin-block: -0.65rem` on `.register-row__title` and `0.6rem` on `.brand-lockup__title` — the box grows to the floor while the negative margin returns every pixel of layout, so the ruled rhythm is untouched | 390px: `.register-row__title` **47px** (width 175px), `.brand-lockup__title` **41px**. DOM experiment: zeroing the padding+margin on a register title changes the row height and page height by **0px** — the floor costs no layout. Snapshot cards were already fine (the whole 74px card is the target) |
| 2 | `Every entry is saved` was a half-empty full-width band at 1440 | Merged into the hero panel as a ruled footer: `.attention-strip` (hairline top, translucent ruled fill) inside the head-spread panel, copy on a `flex: 1 1 20rem` track and the action pushed to the strip's right edge. The standalone panel is gone; the page lost 73px and the band is now one content-weighted line | 1440px: strip 1278×82 with `border-top 1px`, action flush at the strip's right padding edge (1319 vs 1359). Dashboard body height 2703 → **2630**. At 390px the strip stacks (210px) with the 40px action below the copy. `data-open-save-group-builder` / `ready to save` / `View All Reports` all still present, so the 1-click save budget is intact |
| 3 | `TIME SERVED` was a card inside a card | `.total-cell` lost its border, radius, fill and padding; it is now a ruled column of the same sheet — hairline **left** rule + `padding-left: 1.75rem` at ≥1024px, hairline **top** rule on mobile (media variant, same pattern as the file's other breakpoint rules) | 1440px: `border-left 1px rgba(32,22,18,.12)`, `border-top 0px`, `border-radius 0px`, `background transparent`, `padding-left 28px`. 390px: `border-top 1px`, `border-left 0`, `padding-top 16px` |
| 4 | Undocumented DOM-order dependency in the chart hit bands | Six-line comment above `.marg-stack > .marg-seg:last-child::after` naming the dependency: `column-reverse` makes the **last** `.marg-seg` child the topmost band, it is the one that grows upward, and the `nth-of-type` z-index ladder is what makes the lower segment win overlaps so a click routes to the painted bar under the pointer. Ends with an explicit warning that reordering the segments or changing the direction silently reintroduces mis-targeting | Comment present in source and in the served stylesheet bundle |

## Chart truth re-verified (must not regress)

| Column | Total | Stack | Rendered segments | True share |
|---|---|---|---|---|
| Aug 2025 | 9 hr | 98.2px | **11.6 / 64.5 / 22.2** | 60 : 360 : 120 of 540 ✔ |
| Sep 2025 | 7 hr | 76.4px | **11.5 / 42.9 / 22.0** | 60 : 240 : 120 of 420 ✔ |
| Nov 2025 | 14 hr | 152.8px | **11.7 / 97.3 / 43.8** | 60 : 540 : 240 of 840 ✔ |
| Mar 2026 (busiest) | 17 hr 30 min | 191px | **11.8 / 92.3 / 86.9** | 60 : 510 : 480 of 1 050 ✔ |

Hit bands re-probed with `elementFromPoint` across two horizontal scroll passes: **min band 40px, 0 of 27 under 40**. One tap on the Parish March 2026 segment still lands on `/indexes/parish_involvement?month=2026-03` (1 click).

## Regression sweep

- **Horizontal overflow:** `scrollWidth == clientWidth == 390` on `/`, `/reports`, `/academic-year-snapshots`; 1440px clean on `/reports` and `/`.
- **Console:** 31 logs across five pages → **0** `[ERROR]` / `[WARNING]`.
- **CSS discipline:** 154 custom class names, scripted check finds only the dynamic `fill-{formation,social_apostolate,parish_involvement}` emitted from `fill-{{ $type->value }}`; no dead rules. The only multi-block classes (`total-cell`, `compact-summary-grid`, `nav-cluster__links`, `snapshot-builder-*`) are base + breakpoint variants, matching the file's existing convention; no class is defined twice at the same level.
- **Boundaries:** no dependency, schema, migration, route, form or event change; no asserted test string touched; `php artisan test` not run. `.playwright-cli/` back to 0 files (146 session artifacts removed; the tracked deletions and the two root PNGs untouched).

## Changed files

```
resources/css/app.css                    (.register-row__title + .brand-lockup__title floors with
                                         cancelling negative margins, .total-cell un-nested into a
                                         ruled column + its 1024px variant, .attention-strip family,
                                         .marg-seg hit-band DOM-order comment)
resources/views/dashboard.blade.php      (attention band merged into the hero panel as a footer strip;
                                         standalone panel removed)
```

## Deliberately deferred

1. **The masthead's `Reports` / `Archives` chips stay 30px.** They are `display: none` below 768px, so they are not mobile targets, and they duplicate nav destinations that are all ≥40px. Flooring them cost 9px of sticky-header height (98 → 107px) at 1440 because it pushed the nav onto a second row — I measured that and reverted it, keeping the compact header the evaluator called out as good.
2. **Inline text links** (`"Reports"` inside a sentence, `"← All academic year snapshots"`, `"Open Formation entries in the ledger"`) remain at their text height — WCAG 2.2 exempts inline targets and padding them would break the ruled rhythm. The evaluator's fix list scoped this out explicitly.
3. **The painted `.marg-seg` boxes stay proportional** (as low as 11.5px). That is the point of iteration 3: the box is the bar, the `::after` band is the target. Measured 40px+ bands with 0 mis-targets.