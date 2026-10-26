---
paths:
  - 'tests/**'
---

# Tests

## Run the test suite serially
php artisan test must never run while another phpunit/php artisan test process is active. The whole suite shares one ysleep_testing PostgreSQL database (RefreshDatabase) plus storage/framework/views and per-class temp vaults; two concurrent runs produce random 500s and flaky assertion failures in unrelated tests. When the suite looks flaky, check for another test process before debugging the code.
