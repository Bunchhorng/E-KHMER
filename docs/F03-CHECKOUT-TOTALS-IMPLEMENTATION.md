# F03 — DISCOUNT CAP & CHECKOUT TOTAL CONSISTENCY

Implemented and verified on 2 November 2026.

## Status

**PASS.** The quote-to-order calculation is shared, Vue renders server financial amounts, changed quotes require review, and F01/F02 regressions pass. F04 was not started.

## Root Cause

The original audit identified Vue calculating a raw percentage against cart subtotal without respecting the cap. F01 already corrected that specific discount getter to use the backend's validated discount. At the start of F03, the confirmed remaining gap was a separate frontend `TAX_RATE`/rounding formula, separate shipping aggregation in `CheckoutView`, and no complete backend quote binding the displayed total to order creation. Cart and drawer excluded delivery fees while checkout added them later. A catalog or shipping-price change after display could therefore be silently reflected in the order's payable amount without the customer reviewing it.

Backend discount capping, ownership-aware shop allocation, payment amounts from persisted orders, and historical receipt values already existed. These behaviors were reused.

## Old Behavior

Coupon discount came from the server after F01, but Vue still derived tax and grand total and summed delivery fees. Totals were assembled differently across screens, and no reviewed-quote fingerprint accompanied checkout. There was no single complete amount response for the UI to display.

## New Calculation Flow

Persisted cart/catalog and F02 availability → `CouponService` validation and eligible discount → `CheckoutService` calculation → complete backend quote → shared Vue summary → current server recalculation and fingerprint check → order/shop-order/payment snapshots → persisted order confirmation, Bakong amount, and receipt.

The new `POST /checkout/quote` endpoint and order creation call the same private calculator in the existing `CheckoutService`. The legacy coupon-preview endpoint now includes this complete quote too. A preview creates no order, payment, reservation, or coupon usage. No new calculator service, schema, or migration was introduced.

Quote money fields are JSON numbers, consistent with existing order resources. They include subtotal, eligible spend, raw and final discount, tax, shipping, grand total, coupon metadata, selected per-shop delivery methods, readiness flags, currency, and fingerprint.

## Coupon Calculation

For percentage coupons, `raw_discount = round(eligible_subtotal × value / 100, 2)`. Final discount is limited to the percentage cap when present and never exceeds eligible subtotal. Fixed coupons retain existing policy: `min(value, eligible_subtotal)`; the percentage cap field is not applied to fixed coupons.

F01 shop coupons use only matching shop lines; global coupons use all purchasable lines. F02 unavailable lines cannot satisfy minimum spend or contribute to discounts. `CouponService::rawDiscountFor()` and `discountFor()` remain the single formula source. Coupon validation is repeated under the checkout coupon row lock; expiry, usage limits, and per-user limits are enforced again at submission.

Coupon intent is kept separately from the last valid response. Cart updates revalidate the intended coupon. A newly invalid coupon is removed, its specific business message is shown, and a fresh undiscounted quote is displayed.

## Tax Calculation

Existing backend policy is preserved: **10% of discounted goods**, with shipping excluded from the taxable amount. In cents: `tax_cents = round((subtotal_cents - discount_cents) / 10)`. Vue's tax constant and calculation were removed. Final operating-policy approval of tax rules remains separate.

## Shipping Calculation

Fees come from active server shipping methods, scoped to the correct shop or global methods. With no selection, the quote chooses the cheapest compatible method per purchasable shop, breaking equal-price ties by method ID. It returns each selected method and fee. Missing delivery options block checkout.

The cart now includes these selected delivery fees, so cart, drawer, and checkout share a complete total. Checkout changes request a new quote; Vue does not sum delivery fees. Removing or invalidating a selected method refreshes defaults and shows a fee-review notice. Unavailable goods do not add shipping fees.

The existing legacy single-method checkout API remains supported and charges its method once. Current Vue checkout uses per-shop selections from its reviewed quote.

## Rounding Strategy

Database money remains `DECIMAL` with two decimal places. Prices and existing coupon formulas are rounded at their established boundaries; the shared calculator converts amounts to integer cents before tax and grand-total addition. F01's deterministic cent allocation across shop orders remains unchanged.

The decimal-quantity case verifies `19.99 × 3 = 59.97`, raw/final 10% discount `6.00`, tax `5.40`, and total `59.37`. Persisted order and payment amounts match the quote.

## Backend Source of Truth

**YES.** Vue renders quote amounts through `QuoteSummary` in the cart, drawer, and checkout. The Place Order amount also comes directly from the quote. Updating/failed quotes hide financial rows and disable checkout instead of presenting an old amount as payable. Quote revisions prevent older coupon or shipping responses from restoring stale amounts.

Vue submits coupon code, delivery IDs, address/payment choice, and the reviewed `quote_fingerprint`; it submits no subtotal, discount, tax, shipping amount, total, or payment amount. Backend request handling ignores manipulated financial values.

The fingerprint is an HMAC over the server cart identity, priced lines/quantities/ownership, financial amounts, coupon result, and delivery selection. Order creation recalculates under its transaction and checks the fingerprint before creating financial records or reserving stock. A changed price/cap/shipping amount returns **409** with a review instruction. The UI refreshes cart/quote and requires another explicit submission. Expired/exhausted coupons return their **422** business validation message.

Existing idempotency is preserved: a retry returns the original order before repricing. Vue includes the fingerprint in its request signature so an intentionally revised quote starts a new request.

## Files Changed

| Area | Files | Purpose |
| --- | --- | --- |
| Shared rules | `backend/app/Services/CheckoutService.php`, `backend/app/Services/CouponService.php` | Shared quote/order calculation, raw discount, shipping, cent totals, reviewed-quote verification. |
| API | `backend/app/Http/Controllers/Api/CheckoutController.php`, `backend/app/Http/Controllers/Api/CouponController.php`, `backend/app/Http/Requests/CheckoutQuoteRequest.php`, `backend/app/Http/Requests/CheckoutRequest.php`, `backend/app/Http/Resources/CheckoutQuoteResource.php`, `backend/app/Http/Resources/CouponResource.php`, `backend/routes/api.php` | Quote route/contract and additive coupon-preview quote; checkout fingerprint validation. |
| Frontend contract/state | `frontend/src/api/checkout.ts`, `frontend/src/api/coupons.ts`, `frontend/src/stores/cart.ts` | Typed quote API, authoritative amounts, quote invalidation/retry and revision guards. |
| UI | `frontend/src/components/QuoteSummary.vue`, `frontend/src/components/CartDrawer.vue`, `frontend/src/views/CartView.vue`, `frontend/src/views/CheckoutView.vue` | Shared amounts, delivery selection, pending/error feedback, reviewed submission. |
| Language | `frontend/src/locales/en.json`, `frontend/src/locales/km.json` | Updating/retry and delivery-review messages. |
| Backend tests | `backend/tests/Feature/Api/CheckoutQuoteTest.php` | Quote, order, payment, receipt, security, retry, and stale-state reconciliation. |
| Browser tests | `frontend/tests/e2e/checkout-totals.spec.ts`, `frontend/tests/e2e/quote-fixture.ts`, `frontend/tests/e2e/shop-coupons.spec.ts`, `frontend/tests/e2e/cart-recovery.spec.ts`, `frontend/tests/e2e/commerce.spec.ts` | New F03 scenarios and existing F01/F02 fixtures updated for the complete quote contract. |
| Documentation | `docs/API-REFERENCE.md`, `docs/FRONTEND.md`, `docs/README.md`, `docs/Discount Cap Checkout Total Consistency.md`, this report | API behavior, operating rules, completion checklist, and evidence. |

## Tests Added

**18 backend cases** cover percentages below/above cap, fixed discounts and their existing cap policy, fixed discount exceeding eligible spend, decimal quantity rounding, shop/global caps, per-shop shipping, quantity and removal changes, unavailable lines, coupon expiry/usage exhaustion, changed catalog/shipping/cap, malicious financial fields, tampered fingerprints, guest session isolation, idempotent retries after price changes, stable receipts after coupon edit/deletion, and Bakong QR/payment reconciliation.

**Nine browser scenarios, desktop and mobile (18 checks)** cover identical capped totals from cart through order/payment; quantity/coupon removal; item removal below a cap; pending shipping updates and out-of-order responses; failed quote retry; changed-price review; coupon expiry at submission; rendering server-provided tax cents without a local formula; and removed delivery methods. The capped-total journey captures screenshots that were visually reviewed.

Existing F01/F02 browser tests were adapted to the quote endpoint while keeping ownership, stale-row, removal, stock, and error assertions. Expected cart totals now include the configured delivery fees.

## Backend Tests

**Passed: 491. Failed: 0.** Complete Laravel suite: **3,769 assertions**, 178.53 seconds. The focused F03/F01/F02 run passed 62 tests with 730 assertions. Final quote-specific verification passed all 18 cases with 267 assertions, including the three-unit decimal case.

All runtime/test commands used Docker. Laravel tests used the configured disposable SQLite memory database. Development data was not reset or seeded.

## Browser Tests

**Passed: 56 distinct focused checks. Failed: 0 in final verification.** These comprise 38 existing F01/F02/checkout/wishlist/layout checks and 18 new F03 desktop/mobile checks. The capped journey was rerun successfully after correcting its drawer-transition selector. Browser tests use mocked API responses; production browser code contains no fixture calculations. The unrelated full browser suite was not rerun.

## Type Check

**PASS**, through `vue-tsc -b` in the frontend Docker production-build command. Quote and nullable-cart types remain strict; no unsafe `any` workaround was introduced.

## Production Build

**PASS**, using `docker compose exec -T frontend npm run build`. Changed PHP files pass Laravel Pint; the patch passes `git diff --check`.

## F01 Regression

**PASS.** Wrong-shop coupons reject, shop discount/cap stays isolated, global allocation remains balanced, and usage/rollback/retry rules pass. All six ownership browser checks pass.

## F02 Regression

**PASS.** Null variants/products remain safely rendered and removable. Unavailable goods do not contribute to quote subtotal, discount, tax, shipping, or total. Checkout remains blocked until the cart is resolved. Existing guest, merge, count, error, and stock-recovery behavior passes.

## Order / Payment Reconciliation

**PASS.** Quote and persisted order fields agree. Parent and seller amounts reconcile in cents for shop-owned orders. Parent payment amount equals order total; Bakong seller payment and QR amounts equal persisted shop-order totals, and their sum matches the parent quote in the multi-shop test. Receipt HTML and PDF use persisted order values and remain stable after coupon edits/deletion. Legacy unassigned goods retain their existing parent-only allocation behavior.

```text
Subtotal:       $100.00
Raw discount:    $10.00
Discount cap:     $5.00
Final discount:   $5.00
Tax:              $9.50
Shipping:         $0.00
------------------------
Total:           $104.50
```

For the same example, cart, drawer, checkout review, persisted order, payment, and receipt all use **$104.50**.

## Remaining Risks

- Legacy API clients may still omit the optional fingerprint; they receive server-calculated amounts but should adopt quote/fingerprint submission to detect changes from their own earlier displays. The Vue client always sends it.
- SQLite checks establish business outcomes but do not certify MySQL lock contention. Bakong QR generation and persisted amounts are tested; no live bank transfer was performed.
- The preserved tax policy still needs final operating-policy approval. No tax-policy redesign or historical financial backfill was performed.
- A quote does not reserve inventory. Checkout revalidates availability and performs the existing atomic reservation.

## Related Follow-Up

**F04 — COD Collection & Paid-State Lifecycle** remains separate and was not started. Existing COD confirmation/payment behavior is preserved.

Reproduction commands:

```sh
docker compose exec -T app php artisan test --compact --filter=CheckoutQuoteTest
docker compose exec -T app php artisan test --compact
docker compose exec -T frontend npm run build
docker compose --profile test run --rm --no-deps browser-tests npx playwright test tests/e2e/checkout-totals.spec.ts
docker compose --profile test run --rm --no-deps browser-tests npx playwright test tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/commerce.spec.ts tests/e2e/account-ui.spec.ts --grep 'cart|coupon|checkout|store pages'
```
