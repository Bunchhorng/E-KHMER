# F01 Shop Coupon Ownership

Implementation date: 1 November 2026.

Status: **PASS**. All F01 acceptance checks and the complete Laravel regression suite pass.

## Root cause

`CouponService::validate()` accepted a scalar subtotal without inspecting cart ownership. Checkout passed the whole cart subtotal to both minimum-spend validation and discount calculation. `ShopOrderService` then spread every discount proportionally across sellers, including shop-owned coupons. The public preview endpoint trusted a client-supplied subtotal, its resource duplicated the discount formula, and Vue recalculated discounts from the entire cart without the percentage cap.

The existing nullable `coupons.shop_id` already distinguishes intentional platform coupons from shop coupons. Order items already snapshot `shop_id` and line amounts; coupon usages already support transactional redemption and idempotent retries. No migration was required.

## Business logic changed

- `CouponService` derives eligible subtotal from persisted cart variants/products; only matching `product.shop_id` lines qualify for shop coupons. Platform coupons retain the full-cart scope. Usage validation reads immutable order-item snapshots.
- Minimum spend, fixed discounts, percentage discounts, and maximum percentage discounts all use the eligible subtotal. A positive eligible subtotal is required. Wrong-shop-only carts receive HTTP 422 with `errors.code: ["This coupon does not apply to products in your cart."]`.
- Checkout reads cart items under its transaction and uses the same loaded prices and ownership for totals, coupon validation, and order-item snapshots. The coupon row stays locked through validation, allocation, and usage recording. The redundant controller prevalidation was removed.
- A shop coupon's entire discount belongs to its shop order. Platform discounts remain proportional. Tax follows each shop's discounted goods amount, explicit per-shop delivery fees are preserved, and integer-cent cumulative allocation prevents rounding drift. Legacy lines without shop ownership retain their share on the parent order.
- Preview resolves the authenticated customer's cart or the guest's `X-Session-Id` cart. It ignores client-supplied pricing and shop fields. The optional legacy `subtotal` input remains accepted but cannot influence the quote. Preview does not consume usage; successful checkout records usage inside the order transaction. Failed checkout rolls back usage, orders, and reservations.
- Vue displays the backend's `discount_amount`, revalidates applied coupons when the cart changes, shows server validation reasons, and ignores outdated quote responses. Checkout submission waits while a quote is being validated.

## Acceptance example

Shop A goods: $100; Shop B goods: $200. Shop A coupon: 20%, minimum $50, maximum discount $15. Existing tax rate: 10%. Delivery fees: A $2, B $5.

| Amount | Shop A | Shop B | Parent order |
| --- | ---: | ---: | ---: |
| Subtotal | $100.00 | $200.00 | $300.00 |
| Discount | $15.00 | $0.00 | $15.00 |
| Tax | $8.50 | $20.00 | $28.50 |
| Shipping | $2.00 | $5.00 | $7.00 |
| Total | $95.50 | $225.00 | $320.50 |

## Files changed

| Area | Files | Purpose |
| --- | --- | --- |
| Coupon rules | `backend/app/Services/CouponService.php` | Server-derived eligibility, eligible minimum spend, locked checkout validation, snapshot-based usage validation. |
| Checkout | `backend/app/Services/CheckoutService.php`, `backend/app/Services/CartService.php`, `backend/app/Http/Controllers/Api/CheckoutController.php` | Consistent cart snapshot and centralized coupon validation. |
| Seller allocation | `backend/app/Services/ShopOrderService.php` | Ownership-aware discounts, net-goods tax, exact-cent balancing. |
| Preview API | `backend/app/Http/Controllers/Api/CouponController.php`, `backend/app/Http/Requests/CouponValidateRequest.php`, `backend/app/Http/Resources/CouponResource.php` | Server-cart preview and one discount formula. |
| Frontend | `frontend/src/api/coupons.ts`, `frontend/src/types/index.ts`, `frontend/src/stores/cart.ts`, `frontend/src/views/CartView.vue`, `frontend/src/views/CheckoutView.vue` | Display authoritative discounts, invalidate/revalidate quotes, prevent stale responses. |
| Automated tests | `backend/tests/Feature/Api/ShopCouponOwnershipTest.php`, `backend/tests/Feature/Api/CouponTest.php`, `frontend/tests/e2e/shop-coupons.spec.ts` | Ownership, financial allocation, rollback, retry, and UI regression coverage. |
| Documentation | `docs/API-REFERENCE.md`, `docs/README.md`, this report | Updated preview contract and implementation evidence. |

## Tests added and updated

Eleven new backend tests cover same-shop acceptance, wrong-shop rejection, mixed-shop allocation and caps, eligible minimum spend, oversized fixed discounts, platform coupons, idempotent retries after catalog ownership changes, stock failure, rollback after a usage insertion failure, three-shop cent rounding, and guest-session isolation. The wrong-shop and rollback tests assert that no partial financial records or usage remain.

Existing coupon tests now create real server carts. They retain coverage for expiry, start date, active state, usage limits, per-user limits, minimum spend, percentage and fixed discounts, and administrative validation.

Three new browser scenarios run on desktop and mobile: capped backend quote through cart/checkout, automatic rejection after removing the eligible shop, and ignoring an old response after the cart changes.

## Validation results

All commands use the provided Docker containers. Laravel's configured test database is disposable SQLite in memory; no development database reset or seed command was used.

| Check | Result |
| --- | --- |
| Focused coupon and broader checkout regression | PASS: 64 tests, 494 assertions. |
| Complete Laravel suite | PASS: 460 tests, 3,285 assertions, 165.15 seconds. |
| New coupon browser checks | PASS: 6 tests across desktop/mobile. |
| Existing checkout browser regression | PASS: 4 tests across desktop/mobile, covering per-shop delivery, expired-attempt retry, and independent Bakong payment display. |
| Frontend type checking and production build | PASS: `npm run build` in the frontend container. |
| Changed PHP formatting | PASS: Laravel Pint. |
| Patch whitespace | PASS: `git diff --check`. |

Reproduction commands:

```sh
docker compose exec -T app php artisan test --compact --filter='CouponTest|ShopCouponOwnershipTest|CheckoutTest|CheckoutIdempotencyTest|MarketplaceFlowTest|ShopShippingTest|QaHotfixTest'
docker compose exec -T app php artisan test --compact
docker compose exec -T frontend npm run build
docker compose --profile test run --rm --no-deps browser-tests npx playwright test tests/e2e/shop-coupons.spec.ts
docker compose --profile test run --rm --no-deps browser-tests npx playwright test tests/e2e/commerce.spec.ts --grep checkout
```

Tests failed: **0 in the final checks.**

Regression result: **PASS**. The complete Laravel suite, focused coupon/checkout tests, existing checkout browser scenarios, new coupon browser scenarios, and frontend build pass.

## Remaining risks and related follow-up

- SQLite tests cover business outcomes and rollback but do not certify simultaneous MySQL lock contention. A production-like concurrent last-use coupon test remains a useful operational check.
- Browser checks use mocked API responses. Live bank transfers and the full unrelated browser suite were not rerun for this change.
- Old orders retain their stored historical discounts. No financial backfill was performed.
- **F03:** The frontend cap and shop-subtotal discrepancy needed for F01 is corrected by consuming the backend quote. A broader replacement of the existing frontend tax/shipping estimate with a complete backend checkout quote remains separate work. Checkout always recalculates the final total on the server.
- F02 and other audit findings remain outside this implementation.
