# F04 — COD COLLECTION & PAID-STATE LIFECYCLE

Status:
PASS.

Root Cause:
COD checkout correctly left `payments.status=pending` and `orders.payment_status=unpaid`. Fulfillment changed delivery state without any operation to record cash receipt. Seller payments listed only `bakong_payments`. Returns and financial metrics depended on the parent paid flag, which also prevented a collected shop from being handled independently of another unpaid shop.

Old Lifecycle:
COD checkout → confirmation and stock deduction → delivery → payment stays pending → settled revenue excludes the sale and a paid-sale return is rejected.

New Lifecycle:
COD checkout remains pending/unpaid → confirmation deducts reserved stock → each shop fulfills independently → authorized actor explicitly confirms cash receipt after that shop's delivery → one successful collection transaction is recorded → shop becomes paid → parent remains `partial` until every allocation is collected → parent payment becomes `completed` and order payment state becomes `paid`.

Collection Authorization:
Active owners and managers of an active shop may collect that shop's COD allocation, matching existing payment-management permissions. Super admins may collect through the admin endpoint, with their role and identity recorded. Sanctum and existing route middleware protect the endpoints; the domain service also checks authorization. Customers, staff without management permission, suspended memberships, and other shops are denied. Manipulated shop/allocation/payment combinations receive safe 403/404 responses.

Collection Amount Source:
Persisted `shop_orders.total`, reconciled against the parent's saved `payments.amount`, using integer cents. Request amount, actor, reference, and timestamp values are ignored. Catalog/coupon edits cannot reprice collection. Collection is full per shop; partial cash tender is outside the existing product scope. A fully discounted zero-balance allocation can be explicitly settled once without recording positive cash.

Transaction Safety:
The service locks and reloads the parent order, payment, and target allocation in one database transaction. Delivery, method, financial state, authorization, and remaining balance are validated before inserting the collection. An injected failure after insertion rolls back both the transaction record and financial state. Cancellation before collection closes the pending payment using existing `failed` status. Collected cash cannot be discarded by generic cancellation/refund transitions.

Idempotency Strategy:
The successful collection for a shop allocation is the business identity. Retrying returns its current state without creating another transaction, including after a subsequent refund. Internal references use `COD-{payment_id}-{shop_order_id}`; these are not gateway references. A database unique constraint on `(shop_order_id, type)` prevents duplicate collection records even outside the service.

Concurrency Strategy:
Parent-first row locks serialize duplicate requests and different shops on the same payment. The unique business constraint adds database enforcement. Laravel retries deadlocked transactions up to three times. Real simultaneous PHP processes were tested against MySQL 8/InnoDB in a randomly named disposable database, including duplicate collection, retry racing another shop, and simultaneous sibling collection.

Multi-Shop Aggregation:
Shop status derives from its own successful collection and recorded cash return refunds. One collected shop never marks an uncollected sibling paid. Parent `payment_status=partial` and payment `status=pending` are retained until all shop allocations have collection evidence and their gross amounts match the saved payment amount. Parent `paid_at` records completion; individual transaction timestamps preserve earlier shop collections. Refund aggregation retains partial, partially refunded, or refunded states correctly. Checkout currently selects one payment method for the entire order; mixed Bakong/COD methods within one checkout are not supported or introduced here.

Return Eligibility Result:
A delivered, uncollected COD allocation is rejected. Once its cash is recorded, a normal eligible return succeeds even while another shop remains unpaid. Ownership, delivery timestamp, return window, quantity, and refund rules remain enforced. Cash refunds still require a unique cash receipt reference in the existing `payment_refunds` ledger; they do not impersonate verified Bakong transfers. Refunds cannot exceed cash actually collected, and a later collection does not erase refund history.

Revenue Result:
The admin summary excludes uncollected COD and includes collected allocations independently, without counting the parent again. Recorded cash refunds reduce this COD amount. For a $40 item plus $4 tax, settled report revenue changes from $0 to $44 after collection. Existing item/order-volume report definitions and unrelated report work remain outside F04.

Analytics Result:
`FinancialMetricService` includes only collected COD shops, even with a partially paid parent. Its existing net-goods definition excludes tax and delivery; the $44 collection example contributes $40 before returns. Sales remain attributed to the original placement month and are restated for recorded refunds according to the existing definition. Cost coverage and missing-cost behavior are unchanged.

Files Changed:

- `backend/app/Services/CodCollectionService.php` — shared authorized collection and parent aggregation.
- `backend/app/Models/ShopOrder.php`, `PaymentTransaction.php` — collection relationship, eligibility, shop financial state, transaction linkage.
- `backend/app/Http/Resources/CodPaymentResource.php`, `PaymentResource.php`, `ShopOrderResource.php` — safe shop collection details and independent paid status.
- `backend/app/Http/Controllers/Api/Seller/SellerPaymentController.php`, `Admin/AdminPaymentController.php`, `backend/routes/api.php` — protected seller/admin operations and COD list support.
- `backend/app/Services/CheckoutService.php`, `OrderService.php`, `ReturnService.php`, `RefundService.php` — unpaid cancellation, paid-cash preservation, settled-shop returns, receipt-based refund aggregation.
- `backend/app/Services/Analytics/FinancialMetricService.php`, `backend/app/Http/Controllers/Api/Admin/AdminReportController.php` — collected-shop revenue recognition.
- `backend/database/migrations/2026_11_02_000001_link_payment_transactions_to_shop_orders.php` — nullable allocation link and unique business constraint.
- `frontend/src/api/cod.ts`, `api/admin.ts` — typed collection data.
- `frontend/src/components/CodCollectionPanel.vue`, `CustomerPaymentStatus.vue` — confirmation, duplicate prevention, safe errors, audit details, customer language.
- `frontend/src/views/seller/SellerPaymentsView.vue`, `admin/AdminPaymentsView.vue`, `OrderTrackingView.vue`, `OrderSuccessView.vue` — seller, admin, and customer integration.
- `frontend/src/locales/en.json`, `km.json` — English and Khmer collection/status messages.
- `backend/tests/Feature/Api/CodCollectionTest.php`, `backend/tests/Concurrency/cod-collection.php`, `frontend/tests/e2e/cod-collection.spec.ts` — regressions.
- This report and the F04 task checklist.

Schema Changes:
Added nullable `payment_transactions.shop_order_id`, referencing `shop_orders` with restricted deletion, and unique `(shop_order_id, type)`. Existing transactions remain valid with a null allocation. The existing JSON metadata and timestamps hold provider, actor ID/name/role, shop ID, and collection time; no duplicate `collected_at`/`collected_by` columns are needed. The allocation column is necessary for a relational shop payment history and database uniqueness, which the previous unindexed JSON metadata could not enforce. The additive migration is applied to the development database without reseeding. Forward migration and rollback are checked on isolated MySQL.

Tests Added:
21 backend cases cover pending checkout/delivery, saved amounts and actor audit, retry/lost response, multi-shop aggregation, three rejected online methods, membership/isolation, manager/admin policy, cancellation/expiry, return/refund integration with an unpaid sibling, settled reporting and placement-month analytics, rollback, uniqueness, generic refund rejection, capped totals after catalog/coupon changes, four terminal financial states, and zero balances. Eight browser scenarios run on both desktop and mobile, including seller collection/reload, customer return, modal cancellation/loading, absent online/paid actions, delivery eligibility, business errors, safe server errors, and admin audit/history.

Backend Tests:
Passed: final full Laravel suite **512 tests, 4,064 assertions**, in 313.16 seconds. Focused COD suite: **21 tests, 294 assertions**. All tests use the project Docker container and isolated SQLite memory database; the separate concurrency script uses actual MySQL. Pint passes for all 18 changed PHP files; final rollback/script edits also pass. `git diff --check` passes.

Browser Tests:
Passed: 64 distinct desktop/mobile scenarios across F01–F04. The combined run had 63 first-attempt passes and one mobile tracking check that passed on retry while the page was still loading. That new test now waits explicitly for the order response; all 16 F04 browser checks subsequently passed with `--retries=0` in 1.1 minutes. The remaining 48 F01–F03 checks passed in the combined run. Desktop/mobile collection screenshots were visually reviewed. Browser tests use deterministic API mocks; backend tests cover the real domain/database behavior.

Type Check:
PASS (`vue-tsc -b` in the final `npm run build`).

Production Build:
PASS (2,364 modules; final Vite production build completed in 16.74 seconds).

F01 Regression:
PASS in the full backend suite and all six desktop/mobile shop-coupon browser checks.

F02 Regression:
PASS in the full backend suite and all 24 desktop/mobile cart-recovery browser checks.

F03 Regression:
PASS in the full backend suite, including quote/order/payment/receipt reconciliation, and all 18 desktop/mobile checkout-total browser checks. COD also adds a saved capped-total collection regression.

MySQL Concurrency:
PASS for duplicate collection, retry with sibling collection, and concurrent sibling aggregation on actual MySQL 8/InnoDB. Forward migration and rollback also pass. The random test database was removed and the disposable service stopped. This does not claim completion of broader marketplace concurrency validation.

Remaining Risks:

- A staff confirmation is the evidence of physical cash receipt; the application cannot independently verify banknotes. Permission checks, confirmation, immutable transactions, and actor/time audit make that responsibility explicit.
- Historical orders with missing/unallocated shop records require reviewed data repair before collection through this workflow. Historical unpaid orders are never automatically marked paid or backfilled with invented receipts.
- Partial cash tender and mixed per-shop payment methods are not supported by current checkout. F04 preserves that boundary.
- No new payment notification stream or Telegram payload was introduced. Existing application exception logging remains active; successful collection does not generate notification spam.

Related Follow-Up:
F05 — Guest Checkout Policy & Web Flow. Not started.

## Before/after example

| Field | Before | After authorized collection |
|---|---|---|
| Order | Delivered | Delivered |
| Payment method | COD | COD |
| Collection evidence | Missing | $44 received; one transaction, actor, time, internal receipt |
| Shop payment | Unpaid | Paid |
| Parent payment, single shop | Pending | Completed / paid |
| Settled report revenue | $0 | $44 |
| Analytics net-goods revenue | $0 | $40 |
| Return | Rejected as unsettled | Eligible under normal rules |

## API and operational checks

```text
GET  /api/seller/shops/{shop}/payments?method=cod
POST /api/seller/shops/{shop}/payments/{shopOrder}/collect-cod
POST /api/admin/payments/{payment}/shop-orders/{shopOrder}/collect-cod
```

The seller action identifies the shop allocation returned by the COD list, not a Bakong payment ID. Existing Bakong listing/refund endpoints are retained. Collection POST bodies contain no financial values.

```powershell
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test --compact
docker compose exec -T frontend npm run build
docker compose --profile test run --rm --no-deps browser-tests npx playwright test tests/e2e/cod-collection.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts
docker compose --profile maintenance up -d restore-db
```

For the isolated MySQL check, supply `COD_MYSQL_HOST=restore-db` and `COD_MYSQL_PASSWORD` from the disposable service's local Docker Compose configuration to `docker compose exec -T app php tests/Concurrency/cod-collection.php` (using `exec -e` environment arguments). The script refuses other hosts, creates a random `cod_test_<16 hex>` database, migrates it, runs independent PHP worker processes, verifies rollback, then removes only that database. It does not connect to the development MySQL host or reseed development data. Stop the disposable service after testing.
