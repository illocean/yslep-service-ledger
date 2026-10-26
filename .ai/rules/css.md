---
paths:
  - resources/css/app.css
---

# Css

## Duplicated component definitions; the unlayered copy wins
Several components were declared twice - once inside @layer components and again unlayered further down the file. Unlayered CSS beats layered CSS in the cascade, so edits to the layered copy are silently inert (this hid a fixed focus ring). Before editing any component class here, grep for a second definition; keep exactly one.
