# F05 — GUEST CHECKOUT POLICY & WEB FLOW

Status:
PASS

Chosen Policy:
GUEST CHECKOUT

Reason for Policy:
The existing backend already supports guest carts, login/registration cart merge, inline address snapshots, session-protected guest order lookup, COD and Bakong. Reviews, returns and saved addresses may remain account-only without requiring an account for checkout. The shared CheckoutService continues to handle both identities.

Root Cause:
CheckoutView unconditionally loaded authenticated saved addresses and submitted only address_id. The API client's 401 interceptor redirected guests to login. Guest contact requirements were incomplete, saved-address IDs could bypass inline guest validation, per-customer coupon limits were skipped for guests, and order notifications only resolved User recipients.

Old Behavior:
Guest cart → Checkout → GET /addresses → 401 → Login. There was no usable guest contact/address form; inline saved-address errors were swallowed. Guest confirmation showed an account-only receipt action and used account contact details.

New Behavior:
Guest checkout collects and validates contact/shipping details directly, never requests the address API, and shares delivery, quotes, coupons, payments, inventory and order creation with account checkout. Account saved/default addresses, add/edit and ownership checks remain available. Optional login/registration returns to checkout after cart merge. Address, delivery and checkout errors remain visible and retryable.

Guest Cart Behavior:
Existing add/update/remove/clear, stale-item recovery and multi-shop behavior remain intact. Empty/expired sessions cannot create orders. Login/registration merge valid lines without duplication and preserve stock limits. Guests may use explicitly unrestricted coupons; coupons with per_user_limit require sign-in with a clear message.

Address Strategy:
Guests submit validated inline recipient, delivery phone, street, city, state/province, postal code and country; email is required separately. Orders and shipments keep snapshots without creating user/address records. Saved address IDs are prohibited for guests. Account address IDs must belong to the current customer; editing a saved address cannot change historical snapshots.

Order Ownership Strategy:
Guest orders retain user_id = null and the existing guest-session hash in payment provider metadata. A cryptographically random browser X-Session-Id secret authorizes guest order lookup and checkout/payment actions. Public order numbers or numeric IDs alone grant no access. Same-email account creation/login never claims an existing guest order. Checkout attempts remain owner-scoped and payload-bound.

Order Confirmation Strategy:
The confirmation page displays the public reference, snapshotted customer/email, authoritative total and payment state, plus Continue Shopping and same-browser Track Order. Guests receive no account-only receipt action. Anonymous mail notifications resolve the guest email, respect the existing email setting, and omit the database channel. Mail tracking links explain that the original browser session is required.

COD Support:
YES — confirmation and delivery remain unpaid; authorized collection after delivery records payment through the existing F04 lifecycle.

Bakong Support:
YES — session-protected QR and verification endpoints use the existing bank checks. Configuration is required for the server and each shop; unavailable Bakong returns a clear COD fallback. Automated transfers are mocked; no live money was sent.

Multi-Shop Guest Checkout:
PASS — one parent order, per-shop delivery/fees, allocations, scoped capped discounts, reservations and payment totals.

Security Controls:
Server contact/address validation; guest saved-address prohibition; unchanged account/address ownership; session-secret guest access; account email non-enumeration and no automatic claiming; shared authoritative calculations and inventory locks; owner-scoped idempotency; duplicate submission prevention; explicit account-only per-customer coupon limits; bounded checkout/quote/lookup throttles; guest contact/address details excluded from persistent browser storage and idempotency signatures stored as digests.

Files Changed:

- backend/app/Http/Controllers/Api/CheckoutController.php
- backend/app/Http/Controllers/Api/OrderController.php
- backend/app/Http/Requests/CheckoutRequest.php
- backend/app/Http/Resources/CouponResource.php
- backend/app/Models/Order.php
- backend/app/Notifications/OrderPlacedNotification.php
- backend/app/Notifications/OrderStatusNotification.php
- backend/app/Services/CheckoutService.php
- backend/app/Services/CouponService.php
- backend/app/Services/OrderService.php
- backend/app/Services/ShopOrderFulfillmentService.php
- backend/routes/api.php
- backend/database/migrations/2026_11_02_000002_allow_coupons_without_per_customer_limits.php
- backend/tests/Feature/Api/GuestCheckoutTest.php
- backend/tests/Feature/Api/CheckoutTest.php
- backend/tests/Feature/Api/ShopCouponOwnershipTest.php
- frontend/src/api/client.ts
- frontend/src/views/CheckoutView.vue
- frontend/src/views/OrderSuccessView.vue
- frontend/src/views/BakongPaymentView.vue
- frontend/src/views/account/AddressFormView.vue
- frontend/src/views/admin/CouponFormView.vue
- frontend/src/views/auth/LoginView.vue
- frontend/src/views/auth/RegisterView.vue
- frontend/tests/e2e/guest-checkout.spec.ts
- docs/COMMERCE-COMPLETION.md
- docs/API-REFERENCE.md
- docs/F05-GUEST-CHECKOUT-REPORT.md

The supplied docs/Guest Checkout Policy Web Flow.md remains unchanged.

Schema Changes:
coupons.per_user_limit becomes nullable through 2026_11_02_000002_allow_coupons_without_per_customer_limits.php. Explicit null removes the account-based limit and permits guests; existing numeric limits and default 1 are preserved. Migration applied successfully in development; automated migration coverage used isolated SQLite. No order/address schema changes or data reset.

Tests Added:
17 backend guest tests covering snapshots, existing-account email, required contact/address validation, saved-address rejection/ownership, coupons and delivery scope, idempotency, cross-session access, empty/expired sessions, multi-shop COD/Bakong, expiry, registration merge, admin coupon policy and anonymous notifications/settings. 10 browser scenarios run on desktop and mobile (20 guest cases), including the full browse → two-shop cart → coupon → order → tracking flow, validation, retry/loading, Bakong, login/register return and authenticated address errors. Existing guest fixtures now provide required email and explicitly remove account-based coupon limits where guest coupon use is intended.

Backend Tests:
Passed — complete suite: 525 tests, 4,236 assertions. Following the final added coverage, the guest suite passed separately: 17 tests, 221 assertions. This focused run overlaps the earlier complete run and is not an additional 17 unique tests on top of 525. All tests used isolated in-memory SQLite, array mail/cache and mocked bank requests.

Frontend Tests:
Passed — Playwright guest scenarios and F01–F04 regressions.

Browser Tests:
Passed — 84 tests across desktop Chrome and Pixel 7 emulation, with retries disabled. Includes 20 guest cases and 64 existing F01–F04 cases. Browser APIs are mocked; backend behavior is verified separately by Laravel tests.

Type Check:
PASS — vue-tsc -b through the Docker frontend container.

Production Build:
PASS — npm run build (vue-tsc -b && vite build) through Docker.

F01 Regression:
PASS — backend shop coupon ownership/eligibility and desktop/mobile shop coupon browser tests.

F02 Regression:
PASS — backend cart recovery/guest merge and desktop/mobile cart recovery browser tests.

F03 Regression:
PASS — backend authoritative checkout quote/payment totals and desktop/mobile checkout totals browser tests.

F04 Regression:
PASS — backend COD collection suite, explicit guest delivery/collection lifecycle and desktop/mobile COD browser tests.

Remaining Risks:
Real SMTP delivery/deliverability remains O16; real-account Bakong acceptance remains the existing payment launch gate. Guest order access requires the checkout browser/session; cross-device links and automatic order claiming are not implemented. Lost-session support must verify ownership. Reviews, account receipts and self-service returns remain account-only. Broader checkout abuse quotas remain F13. Production deployment must apply the included migration.

Related Follow-Up:
F06 — Rating & Price Filters (not started).

Final Customer Flow:
Guest → Product → Cart → Checkout → Contact → Address → Delivery → Payment → Review → Order → Confirmation.

Validation commands (Docker only):

```sh
docker compose exec -T app php artisan test --compact
docker compose exec -T app php artisan test --compact --filter GuestCheckoutTest
docker compose run --rm browser-tests npx playwright test tests/e2e/guest-checkout.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts --reporter=line --retries=0
docker compose exec -T frontend npm run build
docker compose exec -T frontend npx vue-tsc -b
docker compose exec -T app php artisan migrate --no-interaction
```

Changed PHP files passed Laravel Pint. `git diff --check` passed. No sensitive environment file is tracked. Development PHP and queue processes were gracefully reloaded; services remain running at http://localhost:5174. No commit or production deployment was performed.
