F14 — ORDER NUMBER COLLISION HANDLING

Status:
PASS

Root Cause:
Order numbers came from the total order count plus a random offset of only 1–999. Checkout checked at most two candidates with `exists()`, then returned a third unchecked candidate. Even checked candidates could collide between SELECT and INSERT. The final order INSERT had no collision recovery.

Old Generator Strategy:
`SV-` + PHP calendar year + `(Order::withTrashed()->count() + random_int(1, 999))`, padded to at least six digits. Uniqueness depended on a pre-insert existence check and the database rejected any remaining collision.

New Generator Strategy:
Application calendar year plus a cryptographically secure, zero-padded 12-digit numeric suffix from `random_int(0, 999_999_999_999)`. No COUNT, MAX, or existence-check queries. The identifier space is 1,000,000,000,000 candidates per application year. The existing `SV-YYYY-…` style remains; the suffix expands from at least six to twelve digits for capacity. Laravel's application timezone remains UTC (`config/app.php`), independent of the container's OS timezone. Generation produces candidates; the actual database INSERT decides uniqueness.

Parent Order Number Format:
`SV-YYYY-NNNNNNNNNNNN`, for example `SV-2026-123456789012` (20 characters). Previously persisted identifiers remain unchanged and usable.

Parent Uniqueness Scope:
Global across `orders`, including soft-deleted rows. Independent checkouts cannot share a parent order number.

Shop Order Number Format:
Normally `<persisted parent number>-<1-based shop position>`, for example `SV-2026-123456789012-1`. Shops are processed in their existing sorted shop-ID order. If that child identifier is already occupied, retry with `<parent>-<position>-<12-digit secure random suffix>`. The parent and shop foreign keys remain authoritative; callers do not parse the identifier to reconstruct relationships.

Shop Order Uniqueness Scope:
Global across `shop_orders.shop_order_number`. The existing unique `(order_id, shop_id)` constraint separately permits only one allocation per parent/shop pair. A conflict with this composite constraint is not retried as a number collision.

Database Unique Constraints:
Live development MySQL schema inspection confirmed InnoDB, VARCHAR(255) columns, `orders_order_number_unique` and `shop_orders_shop_order_number_unique`. `shop_orders_order_id_shop_id_unique` remains intact. All generated formats fit the existing columns. No index migration or historical data repair was needed.

Retry Location:
`OrderNumberService::insert()`, called by `CheckoutService` through `createParent()` and by `ShopOrderService` through `createShop()`. This focused persistence service owns retries; `OrderNumberGenerator` only supplies candidates.

Maximum Retry Attempts:
5 INSERT attempts per parent/child identifier, including the first attempt (at most four retries). `OrderNumberService::MAX_ATTEMPTS` is the single bound.

Collision Detection Strategy:
Catch Laravel 13.37's `UniqueConstraintViolationException` and compare its structured `index` against the exact relevant number index. SQLite supplies structured `columns` instead; require precisely the one relevant number column when the index is null. No custom database-message parsing and no catch-all `QueryException` recovery. Each retry calls the candidate generator again. Info logs record `order_number_collision`; exhaustion produces one `order_number_retry_exhausted` error log with table, attempt count, strategy, a generated UUID reference shared by that insertion's attempts, and a SHA-256 idempotency reference when supplied. Requests without idempotency keys still have diagnostic correlation. Checkout payloads, addresses, tokens, and SQL are not logged by this service.

Unrelated DB Error Handling:
Propagate the original exception. Forced foreign-key, disconnected-database, deadlock, invalid-column, truncation, and unrelated-unique failures are not converted into number retries. The existing outer checkout/idempotency transaction deadlock handling remains separate and unchanged.

Transaction Strategy:
Keep the existing checkout transaction and owner/cart locks. Use a transaction/savepoint for each attempted INSERT, so a rejected insert is rolled back without re-running stock reservation or the rest of checkout. Parent success precedes order items, shop allocations, payment, shipments and coupon usage. A later child failure rolls back the complete outer checkout, including an earlier successfully inserted child. Exhaustion returns HTTP 503 with `ORDER_CREATION_UNAVAILABLE` and `We couldn't create your order. Please try again.` The cart remains available; no partial order, ledger, payment, coupon usage or idempotency row survives. The exact safe JSON response is also tested with production environment and debug enabled.

Idempotency Interaction:
Existing owner-scoped lookup is unchanged. A committed retry returns the same order, persisted number and reservation expiry without calling the generator again. Recovery is covered for authenticated customers and guests, including replay at the active-hold and attempt limits and replay after confirmation. Guest lookup continues to require the original session.

F13 Rate-Limit Interaction:
Internal INSERT retries do not invoke the HTTP limiter or re-enter active-capacity checks. They consume one customer request and one eventual parent hold. Existing MySQL/Redis counter, quota and concurrent idempotency tests pass.

Inventory Side Effects:
PASS — one reservation/ledger entry per line on recovery; all reservations and ledger entries roll back on exhaustion. Stock quantities are unchanged until normal confirmation.

Coupon Usage Side Effects:
PASS — one redemption and used-count increment on success; none on failure. Shop ownership and discount allocation remain unchanged.

Payment Side Effects:
PASS — one pending payment on success, no duplicate capture or KHQR rows, and no payment artifacts on failure. Payment and Bakong lifecycle regressions pass.

Order Item Side Effects:
PASS — one snapshot per cart line, correct shop foreign keys, balanced three-shop totals, and no surviving items after parent or later-child exhaustion.

Notification Duplication:
NONE — failed candidates do not dispatch order confirmations. The existing confirmation flow sends one `OrderPlacedNotification` with the final persisted number. Existing notification jobs retain their after-commit behavior; saved-model cache invalidation is also registered after commit.

Forced Collision Test:
PASS — first collision, multiple collisions, success on the fifth attempt, soft-deleted conflicts, authenticated/guest replay, three-shop allocation and child-number collision recovery.

Retry Exhaustion Test:
PASS — authenticated, guest and production-mode parent exhaustion; later-child exhaustion after an earlier child already succeeded; exact safe response and complete rollback.

Concurrent Creation Test:
PASS — 2 overlapping authenticated HTTP checkouts with the same forced first candidate; 20 concurrent number-persistence processes forced to generate the same candidate before any INSERT proceeds. Every successful order has a distinct persisted number; all expected checkout relationships and stock holds reconcile.

MySQL Concurrency:
PASS — MySQL 8, disposable `ecommerce_f14_test` on the existing tmpfs `restore-db` service: 3 tests / 156 assertions, 14.90 seconds, including actual child-number recovery through MySQL's named index. The two HTTP requests synchronize before owner locking so normal transactional locks can proceed; the 20 insert-only workers synchronize at candidate allocation. The 20-process case is a persistence contention test, not 20 complete HTTP checkouts. Existing F13 MySQL/Redis concurrency: 11 tests / 190 assertions, 75.35 seconds, isolated `ecommerce_f13_test` and `checkout-test-redis`.

Files Changed:

- `backend/app/Services/OrderNumberGenerator.php`
- `backend/app/Services/OrderNumberService.php` (new)
- `backend/app/Services/CheckoutService.php`
- `backend/app/Services/ShopOrderService.php`
- `backend/tests/Feature/OrderNumberGeneratorTest.php` (new)
- `backend/tests/Feature/Api/OrderNumberCollisionTest.php` (new)
- `backend/tests/Integration/OrderNumberConcurrencyTest.php` (new, opt-in)
- `backend/tests/Support/order-number-race-worker.php` (new)
- `docs/F14-ORDER-NUMBER-COLLISION-HANDLING-REPORT.md` (new)

Schema Changes:
None. Existing migrations ran only in disposable test databases. Development orders and stock were not reset.

Indexes Added/Changed:
None.

Config Added:
None. Retry policy uses one constant; no new environment variables or dependencies are required.

Tests Added:
22 generator/feature cases and 3 opt-in MySQL cases. Coverage includes format/year boundary, secure numeric capacity, server-generated-only identifiers, actual unique-index collisions, bounded retries, soft deletes, safe production response, keyed/unkeyed diagnostic correlation, rollback, unrelated database failures, composite child constraints, idempotency, F13 limits, guest ownership, receipts, confirmation, notifications, multiple shops and concurrent persistence. Generator tests use the application test harness because they depend on Laravel's clock.

Backend Tests:
Passed — full unit/feature run: 758 tests / 5,841 assertions, 510.98 seconds, no failures or skips. The final focused run passed all 22 F14 cases / 374 assertions, 9.32 seconds; it includes the guest replay, production-mode exhaustion and unkeyed logging cases added after full-suite discovery, the strengthened later-child rollback case, and the final diagnostic-context change. The MySQL suites above run separately. Pint passed on all eight changed PHP files; `git diff --check` passed.

Frontend Tests:
Passed — all 242 existing F01–F13 browser executions across desktop and mobile, 11.7 minutes, no failures, retries or skips. No frontend source changes were needed. Browser fixtures mock APIs; actual persistence, authorization and transaction races are checked separately in backend/MySQL tests.

Type Check:
PASS — Docker production build runs `vue-tsc -b` successfully.

Production Build:
PASS — Docker `npm run build` completed successfully; Vite built 2,375 modules.

F01 Regression:
PASS — shop coupon ownership and eligibility; full backend suite.

F02 Regression:
PASS — deleted-variant cart recovery; full backend suite.

F03 Regression:
PASS — discount caps, authoritative totals and multi-shop allocation; full backend suite and forced-collision assertions.

F04 Regression:
PASS — COD collection and unpaid-to-paid lifecycle; full backend suite.

F05 Regression:
PASS — guest policy, address/contact validation and ownership; full backend suite and collision/replay tests.

F06 Regression:
PASS — rating/price catalog filtering; full backend suite.

F07 Regression:
PASS — existing mobile and desktop color-filter browser regressions.

F08 Regression:
PASS — cart mutations and normalized checkout error handling; backend regressions and exact safe failure response.

F09 Regression:
PASS — formatted descriptions and sanitization; full backend suite.

F10 Regression:
PASS — review aggregate maintenance and moderation; full backend suite.

F11 Regression:
PASS — reservation-aware low-stock lifecycle and rollback; full backend suite.

F12 Regression:
PASS — duplicate coupon validation and ownership; full backend suite.

F13 Regression:
PASS — full protection feature suite, forced-collision replay at the limits, and all 11 real MySQL/Redis concurrency cases.

Remaining Risks:
Random candidates can still collide; database uniqueness and bounded INSERT recovery are the guarantee. Repeated exhaustion deliberately returns a recoverable 503. The 12-digit suffix is wider than historical identifiers; project routes, resources, searches and receipt filenames use persisted strings without six-digit parsing. Historical identifiers are preserved. Concurrency tests are deterministic isolated cases, not a production throughput benchmark. External bank transfers and physical-device acceptance were outside F14. Test services were already running before this task and their lifecycle was left unchanged.

Related Follow-Up:
F15 — Report Metric Consistency. Not started; stop after F14 for review.

Before/after example using the project's actual prefix and format:

```text
BEFORE

Request A generates SV-2026-000100.
Request B generates SV-2026-000100.

A inserts successfully.
B inserts: unique constraint violation.

Result: possible 500.

AFTER

Request A: candidate SV-2026-000000000100 -> INSERT succeeds.
Request B: candidate SV-2026-000000000100 -> database reports collision.
           Backend generates a fresh candidate internally.
           SV-2026-000000000101 -> INSERT succeeds.

A: SV-2026-000000000100
B: SV-2026-000000000101

No duplicate order.
No customer-visible collision error.
The example's next candidate is illustrative; generation is random, not sequential.
```

Reproduction commands (all PHP/Node/MySQL execution occurs in Docker):

```text
docker compose exec -T app php artisan db:table orders --no-interaction
docker compose exec -T app php artisan db:table shop_orders --no-interaction
docker compose exec -T app php artisan test --compact
docker compose exec -T app php artisan test --compact --filter='OrderNumberCollisionTest|OrderNumberGeneratorTest'
docker compose exec -T frontend npm run build
```

The opt-in F14 suite refuses any environment except testing/MySQL, database `ecommerce_f14_test`, host `restore-db`, and array default cache. Create that named database only on the disposable service, run existing migrations there, and execute `php artisan test --compact tests/Integration/OrderNumberConcurrencyTest.php` through the app container with explicit test database credentials, blank `DB_URL`, array cache/checkout limiter, array mail/session and sync queue. The support worker repeats the isolation guard and receives its ephemeral test token through its process environment. The F13 suite additionally requires the disposable Redis host and blank Redis URL before flushing it. No development database migration, reset or cache flush was used.

Browser command:

```text
docker compose run --rm browser-tests npx playwright test tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts tests/e2e/guest-checkout.spec.ts tests/e2e/catalog-filters.spec.ts tests/e2e/catalog-colors.spec.ts tests/e2e/cart-mutations.spec.ts tests/e2e/product-descriptions.spec.ts tests/e2e/review-aggregates.spec.ts tests/e2e/low-stock-lifecycle.spec.ts tests/e2e/duplicate-coupons.spec.ts --output=/app/test-results/f14-regression --reporter=line --retries=0 --timeout=120000
```
