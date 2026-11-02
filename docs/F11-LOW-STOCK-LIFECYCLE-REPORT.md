F11 — LOW-STOCK NOTIFICATION LIFECYCLE

Status: PASS

Root Cause: `InventoryService::release()` changed reserved stock without calling the existing low-stock evaluator. Checkout cancellation, expiration, and pending-order cancellation could therefore restore available stock while leaving `low_stock_notified_at` set. Manual adjustment and deducted-stock restocking already had a reset branch; the defect did not affect every recovery path. Direct evaluation also lacked its own lock, threshold settings bypassed evaluation, and the marker lacked the date cast used by `InventoryResource`.

Low-Stock Source: AVAILABLE — `max(quantity - reserved_quantity, 0)`, from `Inventory::available_quantity`.

Threshold Rule: LOW when available quantity is **less than or equal to** the inventory row's `low_stock_threshold`. NORMAL when available quantity is greater. The existing global `lowStockThreshold` setting defaults to 5, supplies new inventory rows, and synchronizes existing rows when saved through admin settings. No new threshold layers or hysteresis.

Old Lifecycle: Reserve → notify super admins synchronously → set marker. Release → recover available stock without evaluation → marker remains set → next reservation cannot start a new notification episode.

New Lifecycle: Mutate inventory under a row lock → centrally evaluate available quantity → record one low-stock business event per episode → queue channel delivery after the outer transaction commits. Every recovery evaluation clears the marker; a later low state can emit a new event.

Low-Stock Episode Definition: A continuous period of available quantity at or below the threshold. `5 → 4 → 0` is one episode at threshold 5. Initial evaluation of an unmarked low row also starts an episode.

Recovery Rule: Available quantity strictly greater than the threshold clears `low_stock_notified_at`. `4 → 5` remains low at threshold 5; `5 → 6` recovers.

Marker Used: Existing nullable `inventories.low_stock_notified_at`, now cast to a datetime. It records detection of the current episode, independently of mail delivery. Null means eligible for the next low-stock event. It is not a permanent “ever notified” flag or a delivery receipt. Notification rows remain event history.

Marker Reset Paths:

- Checkout reservation release and releaseMany, including expiration and cancellation.
- Pending-order cancellation through OrderService.
- Deducted-stock restock/restockMany, including confirmed-order cancellation and refunds.
- Received returns when the existing return workflow elects to restock.
- Seller/admin manual stock corrections.
- Product/variant stock edits, new variants, and initial simple-product stock evaluation.
- Platform threshold updates through admin settings, reevaluated under individual row locks in chunks of 100.
- Explicit `InventoryService::checkLowStock()` calls; fresh state is read under a lock even if the caller passes a stale model.

Reservation Behavior: Reserving 6 of 10 makes available stock 4 and starts an episode. Permanent deduction reduces on-hand and reserved quantities equally, preserving available stock and the existing episode. Release can end the episode. Reservation quantities and ledger accounting are unchanged.

Cancellation Behavior: Pending orders release reservations; confirmed orders restore deducted quantity and sold_count according to the existing order state machine. Both paths can clear the marker. Their next low-stock episode produces a new alert.

Return Restock Behavior: A received return with restocking enabled calls the existing inventory restock path. A recovery from 3 to 8 clears the marker. Return/refund amounts and inventory accounting are unchanged. A financial refund alone does not invent a stock movement.

Manual Adjustment Behavior: Seller/admin absolute stock corrections share the evaluator. `4 → 20` clears the marker; `20 → 5` starts a new episode. Product stock edits use the same adjustment ledger behavior, with variant attributes updated before notification context is captured. Creating initial stock retains the existing absence of an adjustment ledger entry.

Seller/admin inventory badges and dashboard counts still derive from current quantities and thresholds, independently of the notification marker. These stock queries do not cache the result. The existing seller workspace invalidates its dashboard after a stock correction. Legacy variants without inventory rows remain editable, including SKU swaps; notification evaluation skips their absent stock row.

Out-of-Stock Behavior: Zero is part of the same low-stock episode. No additional out-of-stock notification has been introduced. Admin low-stock and out-of-stock filters still overlap by design in the existing implementation; this separate filter issue was not redesigned.

Recipient Policy: Preserve super-admin alerts and add active owner/manager memberships for the product's actual owning active shop. A single recipient query deduplicates a user who is both a super admin and owner/manager. Staff, suspended memberships, and other shops' managers are excluded. Platform products without a shop notify super admins only. Removed/hidden products, removed/inactive variants, and suspended/deleted shops do not start new operational alerts. Recovery still clears markers for inactive merchandise. Membership and mail preference are checked again during queued delivery.

Shop Owner: YES — active owning-shop membership.

Shop Manager: PERMISSION-BASED — active owner/manager role, matching the existing managed-shop inventory access rules. Staff do not have inventory management access.

Super Admin: YES — existing behavior retained for eligible merchandise.

Notification Preferences: PASS — existing global `emailLowStockAlerts` controls mail independently of in-app notifications. Disabled mail stays disabled, including when the setting changes after dispatch but before delivery. There is no existing per-seller email preference to add or reinterpret.

Shop Isolation: PASS — owning-shop recipient selection and delivery-time membership checks, including a Shop B owner and suspended manager exclusion test. Payloads contain shop/product/variant/SKU/available quantity/threshold and omit private costs, customer information, and financial data.

Duplicate Protection: A non-null marker suppresses further low-stock events until recovery. Zero stock and repeated stale-model evaluations do not duplicate the event. Notification delivery retries do not clear the marker.

Concurrency Strategy: The evaluator opens a transaction and reloads the inventory using `lockForUpdate()`. Mutation, marker decision, and marker update share the stock transaction; queued jobs are dispatched only after its outer commit. Snapshot scalar context prevents a later catalog/threshold change from altering an already queued alert. Database and email use separate notification channel jobs so a mail retry does not repeat successful database delivery.

MySQL Concurrency: PASS — 2 tests / 21 assertions against MySQL 8 on the disposable `restore-db` service, in `ecommerce_f11_test`. Two reservation processes compete for one locked inventory row while an unrelated inventory completes. Two direct evaluation processes also compete for an initially low row; one event is recorded, recovery clears it, and low stock afterward emits a second event. Test workers refuse any other environment/host/database. Application migrations ran only in the disposable database; no development data was reset.

Files Changed:

| File | Change |
| --- | --- |
| `backend/app/Services/InventoryService.php` | Release recovery evaluation; independently locked central evaluator; scoped recipients; threshold reevaluation |
| `backend/app/Models/Inventory.php` | Notification marker datetime cast |
| `backend/app/Notifications/LowStockNotification.php` | Queue after commit; immutable context; correct seller/admin actions; delivery-time access and email checks |
| `backend/app/Http/Controllers/Api/SettingsController.php` | Use inventory service for threshold synchronization |
| `backend/app/Http/Controllers/Api/Admin/AdminProductController.php` | Evaluate initial/new/existing variant stock; capture final attribute details |
| `backend/app/Http/Controllers/Api/AccountController.php` | Expose inventory notification type and action URL |
| `frontend/src/api/account.ts` | Typed optional notification URL |
| `frontend/src/views/account/NotificationsView.vue` | Inventory icon and internal inventory action, usable after marking read |
| `backend/tests/Feature/Api/LowStockLifecycleTest.php` | 25 lifecycle/API/queue/policy cases |
| `backend/tests/Integration/LowStockConcurrencyTest.php` | 2 guarded multi-process MySQL cases |
| `backend/tests/Support/low-stock-worker.php` | Guarded isolated-database test worker |
| `frontend/tests/e2e/low-stock-lifecycle.spec.ts` | 3 browser scenarios on desktop/mobile |
| `docs/F11-LOW-STOCK-LIFECYCLE-REPORT.md` | Implementation decisions and verification evidence |

Schema Changes: None. Existing marker, stock columns, notification storage, and ledger schema are retained. No dependency changes. The supplied task document is unchanged.

Tests Added: 25 normal backend cases, 2 opt-in MySQL concurrency cases, and 3 browser scenarios × desktop/mobile = 6 browser cases. Coverage includes exact recovery, threshold equality and zero threshold, reservation release that remains low, actual cancellation/expiry/return flows, ledger preservation, scoped membership, inactive merchandise, immutable queued context, rollback, delivery-time revocation, and email preference changes.

Backend Tests: Passed — final full suite **687 passed / 5,171 assertions**, 354.46 seconds, no failures or skips. Focused inventory/seller/account/return regression: 106 passed / 607 assertions. Final notification/lifecycle verification: 31 passed / 175 assertions (includes the 25 new cases). Legacy SKU operations plus lifecycle/notification verification after the compatibility fix: 60 passed / 363 assertions.

Notification Tests: Passed — 31 focused tests / 175 assertions, including existing notification tests. Real database queue jobs are popped and executed in the isolated test process; no live SMTP provider is required.

Browser/E2E Tests: Passed — combined F01–F11/account regression: **257 passed, 1 existing skip**, 10.7 minutes, no retries. All 6 new F11 desktop/mobile cases passed. The skip is the existing desktop run of the mobile-only account navigation test. Tests use stateful mocked API responses for rendered UI behavior; actual service, API, queue, and concurrency correctness is verified separately with backend and MySQL tests. No browser fixture is written to the development database.

Type Check: PASS — Docker `npx vue-tsc -b` and the build's type check.

Production Build: PASS — Docker `npm run build`, Vite production bundle built successfully.

F01 Regression: PASS — coupon ownership/isolation, backend and browser.

F02 Regression: PASS — deleted-variant cart recovery, backend and browser.

F03 Regression: PASS — discount caps and checkout total consistency, backend and browser.

F04 Regression: PASS — COD money collection and paid-state lifecycle, backend and browser.

F05 Regression: PASS — guest/account checkout policy, backend and browser.

F06 Regression: PASS — rating/price filters, backend and browser.

F07 Regression: PASS — mobile color filters, backend and browser.

F08 Regression: PASS — asynchronous cart mutation and Buy Now errors, backend and browser.

F09 Regression: PASS — safe formatted descriptions, backend and browser.

F10 Regression: PASS — review aggregate maintenance, backend and browser.

Remaining Risks: External delivery retains the queue's normal retry guarantees. A process/queue outage between database commit and after-commit enqueue can still lose delivery; this change does not add a transactional outbox or claim exactly-once SMTP delivery. Existing normal rows with stale historical markers recover on their next evaluation; no bulk development-data rewrite was performed. Direct future stock/setting writes must call the shared evaluator. Reactivating hidden merchandise without any stock/threshold evaluation does not independently create a notification. Full-catalog threshold saves do synchronous per-row reevaluation and should be measured for large catalogs. Browser mobile coverage is emulated; live SMTP acceptance is outside this task.

Related Follow-Up: F12 — Duplicate Coupon Validation. Not started.

Before/after example:

```text
BEFORE

Threshold: 5
Available stock: 4
Low-stock alert sent; low_stock_notified_at = set
Stock recovers by reservation release: 4 → 10
low_stock_notified_at: still set
Stock drops: 10 → 4
Result: No new alert

AFTER

Threshold: 5
Available stock: 10 → 4
Alert #1 sent
Stock: 4 → 10
Low-stock episode ends; notification marker resets
Stock: 10 → 4
Alert #2 sent
Result: One alert for each genuine low-stock episode
```

Verification commands (all runtimes run inside Docker):

```sh
docker compose exec -T app php artisan test --compact --filter='LowStockLifecycleTest|NotificationTest|AdminInventoryTest|SellerWorkspaceTest|ReturnsAndSupportTest|ShopOwnershipTest|AccountAndSettingsTest|SellerProductPhotosTest|SellerCompletionTest'
docker compose exec -T app php artisan test --compact
docker compose exec -T frontend npx vue-tsc -b
docker compose exec -T frontend npm run build
docker compose run --rm browser-tests npx playwright test tests/e2e/low-stock-lifecycle.spec.ts tests/e2e/account-ui.spec.ts tests/e2e/review-aggregates.spec.ts tests/e2e/product-descriptions.spec.ts tests/e2e/seller-product-form.spec.ts tests/e2e/cart-mutations.spec.ts tests/e2e/catalog-colors.spec.ts tests/e2e/catalog-filters.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts tests/e2e/guest-checkout.spec.ts --output=/tmp/f11-regression-results --reporter=line --retries=0
```

For opt-in concurrency verification, start `restore-db`, create `ecommerce_f11_test` there, and pass `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_HOST=restore-db`, `DB_DATABASE=ecommerce_f11_test`, its disposable credentials, blank `DB_URL`, array cache/mail, and sync queue to the app container. Run the existing migrations, then `php artisan test --compact tests/Integration/LowStockConcurrencyTest.php`. The test is excluded from the default SQLite suite. Stop the disposable service afterward. Pint was run on the explicit changed PHP paths because the app container does not mount `.git`, so its `--dirty` mode is unavailable.
