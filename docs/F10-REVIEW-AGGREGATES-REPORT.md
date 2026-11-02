# F10 — REVIEW AGGREGATE MAINTENANCE

Status: Validation in progress.

Root Cause: `ReviewService::reject()` changed status without updating the product aggregate. `AdminReviewController::destroy()` deleted the model directly. Existing approval/customer update/customer deletion were not atomic with recalculation, and used potentially stale route-bound review state. ReviewSeeder maintained its own one-review arithmetic. Admin bulk handlers swallowed per-request failures and then reported every selection successful; published reviews had no Reject action in that UI.

Old Behavior: Rejecting the only approved five-star review could leave `rating_avg=5`, `rating_count=1`. Admin deletion could leave similarly stale scores, counts, filters and sorting.

New Aggregate Rule: Recompute the affected product from its current approved review rows after each supported review mutation. Use one shared formula, not incremental arithmetic.

Public Aggregate Includes:
- Rows for the product with `status=approved`, following the existing public review qualification. `verified` remains purchase information, not a newly introduced aggregate filter.

Public Aggregate Excludes:
- Pending and rejected rows.
- Hard-deleted rows. Reviews do not use SoftDeletes; review restore does not exist.
- Other products' reviews.

Average Rating Formula: `round(AVG(reviews.rating), 2)` over approved rows for that product; `COUNT(*)` for `rating_count`, calculated in the database.

Zero-Review Behavior: Persist `rating_avg=0.00`, `rating_count=0`. API values are numeric zero. Existing storefront shows empty stars, `0.0/5`, zero reviews and “No reviews yet” in the review tab; cards show empty stars and `(0)`.

Rounding Policy: Two decimal places in existing `DECIMAL(3,2)` storage and numeric API values. Examples: 5/4/4 → 4.33; 5/5/4 → 4.67. Catalog comparisons use stored precision. The existing one-decimal header/whole-star icon presentation does not alter the filter source.

Recalculation Location: `ReviewService::persistProductRating()` is the single formula; `recalculateProductRating()` is the locked public repair/seeder entry point. All supported customer/admin mutation methods use `withProductLock()`. The public list and aggregate share `Review::approved()`. No observer or double recalculation is added.

Transaction Strategy: Product lock → fresh review lock where applicable → mutation → SQL count/average → product write if changed → commit. Existing approval/rejection database notifications participate in the transaction. Failures roll back all writes, log IDs/action/actor/exception class without review content, and return a safe 503. Validation/not-found/authorization HTTP exceptions retain their existing status. Transactions allow three attempts for concurrency errors. Customer no-op payloads preserve status; editing any supported content on an approved review resets it to pending as before.

Concurrency Strategy: Writers serialize on the affected product row using `lockForUpdate()`, consistently before review locks. Aggregate reads also use `FOR UPDATE` so they see current rows even inside an older MySQL repeatable-read transaction. Different products can progress independently. This choice follows MySQL's distinction between snapshot and current locking reads; see [MySQL 8.0 transaction isolation](https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-isolation-levels.html) and [locking reads](https://docs.oracle.com/cd/E17952_01/mysql-8.0-en/innodb-locking-reads.html).

Cache Invalidation: Not applicable. Catalog/product/shop rating responses do not use a response cache. Category, brand, shipping and analytics caches are unrelated. Refreshed detail/list/shop responses are tested against persisted aggregates; the UI does not introduce real-time cross-tab subscriptions.

Existing Stale Data: **NO in the inspected development dataset** — read-only command scanned 22 products, zero incorrect aggregates, no writes. **UNKNOWN in production**, which was not accessed.

Repair Strategy: Explicit operator-run command, batch enumeration (200 IDs), per-product transaction/lock/current read, optional product ID, archived products included, changed-only updates, dry-run summary and idempotent second execution. No scheduled repair or write-mode run against development/production data. No reseeding, wiping or schema change. Direct future model/query-builder/SQL imports must use the service protocol or finish with this repair command; bypasses are not automatically observed.

Repair Command:

```sh
# Read only; inspect first.
docker compose exec -T app php artisan reviews:recalculate-ratings --dry-run
docker compose exec -T app php artisan reviews:recalculate-ratings --product=123 --dry-run
# Explicit correction of the selected product, or omit --product for all products.
docker compose exec -T app php artisan reviews:recalculate-ratings --product=123
```

Files Changed (21 implementation/test/documentation files; supplied task document unchanged):

| File | Purpose |
| --- | --- |
| `backend/app/Services/ReviewService.php` | One transactional lifecycle, current-row reload, central aggregate, safe errors/logging |
| `backend/app/Models/Review.php` | Shared approved scope |
| `backend/app/Http/Controllers/Api/Admin/AdminReviewController.php` | Route admin deletion through service |
| `backend/app/Http/Controllers/Api/ReviewController.php` | Use approved scope for public rows |
| `backend/app/Http/Resources/ReviewResource.php` | Null-safe archived product relation |
| `backend/app/Console/Commands/RecalculateReviewRatings.php` | Targeted/dry-run repair command |
| `backend/database/seeders/ReviewSeeder.php` | Reuse shared formula and product lock |
| `backend/tests/Feature/Api/ReviewAggregateTest.php` | Service/API qualification, transitions, rollback, filter, permissions and capacity cases |
| `backend/tests/Feature/ReviewRatingRepairTest.php` | Dry run, targeted repair, idempotency, archived/empty products and input errors |
| `backend/tests/Feature/Api/ReviewTest.php` | Assert zero aggregate in original rejection test |
| `backend/tests/Integration/ReviewAggregateConcurrencyTest.php` | Opt-in two-process MySQL concurrency/snapshot tests |
| `backend/tests/Support/review-aggregate-worker.php` | Isolated-process moderation worker with strict database guard |
| `frontend/src/views/admin/AdminReviewsView.vue` | Published-review rejection, rejected/reapproval status, accurate failure feedback and duplicate request guards |
| `frontend/src/api/admin.ts` | Nullable archived product typing |
| `frontend/src/api/reviews.ts` | Nullable archived product typing |
| `frontend/src/locales/en.json` | Missing safe review load/approve/reject error strings |
| `frontend/src/locales/km.json` | Matching Khmer error strings |
| `frontend/tests/e2e/review-aggregates.spec.ts` | Desktop/mobile moderation, public refresh, cards, filter and error tests |
| `docs/API-REFERENCE.md` | Preserved API fields and transactional/repair contract |
| `docs/COMMERCE-COMPLETION.md` | Operating policy and repair instructions |
| `docs/F10-REVIEW-AGGREGATES-REPORT.md` | Evidence and limits |

Schema Changes: **None**. Existing `products.rating_avg DECIMAL(3,2)`, `rating_count UNSIGNED INTEGER`, review uniqueness on user/product and product/status index are retained. No dependency changes.

Tests Added: **31 normal backend cases** (28 service/API + 3 repair), **2 opt-in MySQL integration cases**, **9 browser scenarios × desktop/mobile = 18 cases**. Existing rejection test strengthened.

Backend Tests: Focused **68 passed / 1,021 assertions**. Full suite **662 passed / 5,025 assertions** (316.35 seconds). Final debug-mode failure-response verification pending.

Frontend Tests: The existing frontend test framework is Playwright; focused **18/18 passed** and final combined regression **228/228 passed**.

Browser Tests: Focused desktop/mobile **18 passed**; final combined suite **228 passed** (114 desktop + 114 mobile, 6.6 minutes), zero retries/skips. Stateful mocked API fixtures verify user-visible behavior; backend correctness is covered separately by real service/API tests. No review fixtures were written to development data.

Type Check: **PASS**, `docker compose exec -T frontend npx vue-tsc -b`.

Production Build: **PASS**, `docker compose exec -T frontend npm run build` (`vue-tsc -b` and Vite; build stage 25.44 seconds).

F01 Regression: **PASS**, shop coupon isolation (backend and browser).

F02 Regression: **PASS**, cart recovery (backend and browser).

F03 Regression: **PASS**, discount caps and checkout totals (backend and browser).

F04 Regression: **PASS**, COD paid-state lifecycle (backend and browser).

F05 Regression: **PASS**, guest/account checkout (backend and browser).

F06 Regression: **PASS**, rating/price filters plus moderation-driven threshold and rating-sort changes (backend and browser).

F07 Regression: **PASS**, canonical desktop/mobile color filters (backend and browser).

F08 Regression: **PASS**, awaited cart mutations and Buy Now errors (backend and browser).

F09 Regression: **PASS**, safe rich descriptions and seller/admin editor (backend and browser).

MySQL Concurrency: **PASS — 2 tests, 20 assertions**, MySQL 8 on the provided `restore-db` service, a disposable database named `ecommerce_f10_test`. Existing application migrations were applied only in that isolated database. Workers refuse any other host/database/environment. The tests verify simultaneous approve/reject on one product while another product completes, final approved-row average/count, and a repair inside an older repeatable-read snapshot. Review/product/user fixtures were cleaned and the disposable database service was stopped afterward. No application DB was reset.

Remaining Risks: Production stale-data status is unknown; use the dry run there before repair. Browser mobile coverage uses emulation; physical/staging acceptance remains a release check. Future direct review writes outside the service can bypass the protocol and must opt into it. Database notifications are currently transactional; external delivery channels added later should use after-commit delivery. The repair command uses short product/review locks, so plan full-catalog write repairs for a suitable operational window. Existing admin list pagination behavior and unrelated dependencies are outside F10.

Related Follow-Up: **F11 — Low-Stock Notification Lifecycle**. Not started.

## Verification commands

```sh
docker compose exec -T app php artisan test --compact --filter 'ReviewAggregateTest|ReviewRatingRepairTest|ReviewTest|AccountAndSettingsTest|NotificationTest|ShopOwnershipTest|DatabaseSeederTest'
docker compose exec -T app php artisan test --compact
docker compose exec -T frontend npx vue-tsc -b
docker compose exec -T frontend npm run build
docker compose run --rm browser-tests npx playwright test tests/e2e/review-aggregates.spec.ts tests/e2e/product-descriptions.spec.ts tests/e2e/seller-product-form.spec.ts tests/e2e/cart-mutations.spec.ts tests/e2e/catalog-colors.spec.ts tests/e2e/catalog-filters.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts tests/e2e/guest-checkout.spec.ts --output=/tmp/f10-final-results --reporter=line --retries=0
```

The opt-in MySQL test requires `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_HOST=restore-db`, `DB_DATABASE=ecommerce_f10_test`, the restore service's disposable credentials, blank `DB_URL`, array cache and sync queue passed to the app container. Run existing migrations in that isolated database, then `php artisan test --compact tests/Integration/ReviewAggregateConcurrencyTest.php` with the same environment. It is deliberately excluded from the default SQLite suite; SQLite alone is not treated as proof of locking behavior.

## Before / after

```text
BEFORE
Product: 5.0 stars, 1 review.
Admin rejects its only approved review.
Database still stores rating_avg=5, rating_count=1.
Customer still sees 5.0 stars and the product can pass a 4.5+ filter.

AFTER
Product: 5.0 stars, 1 approved review.
Admin rejects it through the transactional service.
Approved reviews=0; rating_avg=0.00; rating_count=0.
Refreshed product: empty stars, 0 reviews, “No reviews yet”.
It no longer qualifies for a 4.5+ rating filter.
```
