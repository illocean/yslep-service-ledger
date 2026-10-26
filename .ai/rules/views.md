---
paths:
  - 'resources/views/**'
---

# Views

## Verify rendered UI from the DOM, not from screenshots
When checking UI behaviour, read textContent/computed styles through a real browser and assert on those. Reading values out of screenshots produced wrong numbers here more than once. Console errors and the server access log are the other two trustworthy signals.
