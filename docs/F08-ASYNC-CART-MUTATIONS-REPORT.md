# F08 — ASYNC CART MUTATIONS & BUY NOW ERRORS

Verified: 2026-11-02. Scope stops after F08.

Status:
PASS

Root Cause:
The checked-out code already had F02's `if (!await cart.addItem(...)) return` guard on Buy Now and a store boolean result/global error banner. The audit's unconditional navigation after a 422 was therefore not present in this revision. Remaining defects were confirmed: product-detail Add to Cart dispatched without awaiting and opened the drawer immediately; Buy Now lacked a pending guard and did not await/catch router navigation; listing/rail/storefront handlers dispatched unawaited mutations through parent events; buttons could submit repeatedly. A browser concurrency test also exposed that a cart queue must use stable Pinia `$state` identity because action `this` can be wrapped by devtools.

Old Buy Now Flow:
Await the cart boolean and return on false, then start an unawaited router push. No per-action pending guard or navigation recovery. Add to Cart opened the drawer before backend confirmation. The supplied audit describes an earlier flow that navigated even after a failed add; the critical 422 browser test protects against that behavior.

New Buy Now Flow:
Validate the preselected/current variant and quantity → snapshot primitive payload → enter Preparing checkout → await the confirmed cart response and its quote refresh → apply server cart state → await checkout navigation. Failed adds remain on detail, display the existing normalized cart alert, and restore buttons. A blocked/failed navigation retains the added item and offers View Cart. Leaving detail during the request prevents late success from opening checkout or the drawer on another page.

Cart Store Error Strategy:
One `Promise<boolean>` convention across add, quantity, removal and clear: true after the cart response is applied; false after failure/cancellation. Failures use the existing global `mutationError` banner rather than mixing thrown request errors with boolean results. No automatic write retry. Full-cart reads and writes serialize per stable `$state` object; per-action pending keys are set immediately, including queued work, and released in `finally`. A duplicate pending key returns false without starting a second request. Quote response revision guards remain intact.

API Error Normalization:
Reuses `extractErrorMessage`: Laravel field-level errors take priority over its generic validation summary, and business messages remain visible. Unexpected 5xx responses use the translated action fallback without stack/SQL details. Network/timeout errors retain safe connection/retry instructions; 401/419 mutation failures show session-expiry recovery. Guest cart 401s without Authorization do not force login. Authenticated/protected request handling remains unchanged. Intentional Axios cancellation returns false without a false error alert.

Duplicate-Click Protection:
Local product-detail and card guards plus store pending keys. Detail Add to Cart and Buy Now disable together. Repeated cards for the same variant share pending state. Cart rows disable their own quantity/remove controls; distinct rows/cards may queue operations in order. Clear/checkout retain cart-wide guards.

Loading State:
Adding / Preparing checkout text, native disabled controls and aria-busy. Variant/quantity selection locks during a detail request. The current product page has one responsive purchase-control set; there is no separate sticky mobile button path. Listing success feedback appears only after confirmation. Account wishlist removal listens to the new success-only `added-to-cart` event.

Stock Error Handling:
Backend remains authoritative. Unavailable variant, hidden/deleted product, suspended/deleted shop and out-of-stock additions have actionable 422 messages. Excess requested quantity, including an existing line plus a new add, remains rejected without silently reducing it. The frontend stays on detail and restores controls. Existing first-available-variant listing behavior and detail preselection remain unchanged.

Network Error Handling:
Safe connection/timeout messages; no checkout navigation, phantom item or success feedback. Buttons recover for an explicit retry. No technical Axios error object is rendered. Intentionally cancelled Axios requests are silent.

Unhandled Promise Errors:
NONE — every F08 scenario asserts no page exception/unhandled rejection or uncaught application-event error. Expected browser HTTP/network resource logs are not treated as application exceptions.

Failed Mutation State Recovery:
Cart quantities/counts/items update pessimistically from successful `CartResource` responses. A rejected quantity change retains the confirmed number; failed removal/clear retains the item/cart. Coupon/financial recomputation remains server-owned. Existing same-variant additions merge quantities, other products remain, and stale rows stay available for F02 recovery rather than being silently dropped. A quote failure does not roll back a legitimate successful cart write; checkout retains its existing quote-retry/disabled-payment rules.

Files Changed:

| File | Change |
| --- | --- |
| `frontend/src/stores/cart.ts` | Stable per-store read/write ordering, duplicate pending keys, boolean outcome, safe session/cancellation handling. |
| `frontend/src/views/ProductDetailView.vue` | Awaited guarded actions, selection validation/snapshot, pending UI, success-only drawer/navigation, navigation recovery and late-response guard. |
| `frontend/src/components/ProductCard.vue` | Own/await listing adds, shared variant pending state, confirmed success feedback and success-only event. |
| `frontend/src/components/ProductRail.vue` | Remove unawaited parent add dispatch; use the shared card. |
| `frontend/src/views/HomeView.vue` | Remove unawaited parent add dispatch; use the shared card. |
| `frontend/src/views/ShopView.vue` | Remove unawaited parent add dispatch; preserve F06/F07 filter behavior. |
| `frontend/src/views/ShopStorefrontView.vue` | Remove inline unawaited add dispatch; use the shared card. |
| `frontend/src/views/account/WishlistView.vue` | Remove saved product only after confirmed card add. |
| `frontend/src/views/account/DashboardView.vue` | Same confirmed-add wishlist behavior. |
| `frontend/src/components/CartLine.vue` | Await quantity/removal and expose per-row pending/aria-busy. Shared by cart and drawer. |
| `frontend/src/views/CartView.vue` | Await clear and expose its pending state. |
| `frontend/src/api/client.ts` | Keep unauthenticated guest cart 401 recovery in the current flow. |
| `frontend/src/locales/en.json` | Add action/loading/selection/session/navigation messages. |
| `frontend/src/locales/km.json` | Add corresponding Khmer messages. |
| `backend/app/Services/CartService.php` | Actionable unavailable option/product/shop/stock messages; preserve validation and merge semantics. |
| `backend/tests/Feature/Api/CartTest.php` | Update expected stock wording; Pint normalizes existing imports. |
| `backend/tests/Feature/Api/CartMutationTest.php` | New rejection/state-preservation, same-variant merging and quantity regression tests. |
| `frontend/tests/e2e/cart-mutations.spec.ts` | New desktop/mobile async mutation and error tests. |
| `docs/API-REFERENCE.md` | Document unchanged endpoints and the result/error behavior. |
| `docs/COMMERCE-COMPLETION.md` | Document F08 operation and boundaries. |
| `docs/F08-ASYNC-CART-MUTATIONS-REPORT.md` | This report and reproducible verification. |

The supplied `Async Cart Mutations and Buy Now Errors.md` specification was left unchanged. Existing `CartDrawer`, cart API response/resource shapes, global cart banner and error helper required no direct changes. No formatted-description rendering work is included.

Schema Changes:
None. No migration, data reset, new endpoint or cart/payment protocol.

Tests Added:
Seven backend cases: inactive variant/product/shop and out-of-stock rejection with existing cart preservation; missing variant without phantom rows; same-variant merge plus over-stock rejection; failed quantity update preservation. Twenty-two new browser scenarios run on desktop Chrome and Pixel 7 emulation (44 cases): eight error/retry variants, slow/double Buy Now, detail Add to Cart drawer ordering, same-item merge, stale-row recovery, marketplace/shop/home listing pending/error/success, drawer quantity rejection, distinct ordered writes, blocked navigation recovery, leaving during a request, intentional cancellation, unresolved variant and timeout. Every F08 case monitors unhandled browser application errors. Existing tests cover failed remove/clear and F01–F07 behavior. There is no existing standalone Pinia/component unit runner; these tests exercise the rendered components and real Pinia store in the browser.

Backend Tests:
Passed — 326 relevant cart/catalog/checkout/payment/auth and F01–F08 tests, 2,395 assertions, using isolated SQLite and mocked external services. Includes seven new F08 cases (39 assertions).

Frontend Tests:
Passed — final combined run: 183 passed and one intentional desktop skip for a mobile-only account-menu test; retries disabled. Includes all 44 new F08 cases, all 116 F01–F07 commerce cases, and 23 account UI cases. Initial implementation/test development exposed and corrected the Pinia queue identity issue and fixture selectors/module imports; these counts refer to the final complete run.

Desktop Browser Tests:
Passed — 91 cases, plus one intentional mobile-only skip. All 22 desktop F08 cases passed.

Mobile Browser Tests:
Passed — 92 cases using Pixel 7 emulation. All 22 mobile F08 cases passed.

Type Check:
PASS — Docker vue-tsc -b, including the final production build after the stable queue correction.

Production Build:
PASS — Docker npm run build (vue-tsc -b && vite build). PHP formatting also passed for all three changed PHP files.

F01 Regression:
PASS — coupon ownership/isolation backend tests and shop-coupons browser suite.

F02 Regression:
PASS — cart recovery/merge backend tests, cart-recovery browser suite and valid Buy Now with a stale row.

F03 Regression:
PASS — checkout/quote/payment totals backend tests and checkout-totals browser suite; no client financial authority introduced.

F04 Regression:
PASS — COD lifecycle/collection backend tests and cod-collection browser suite.

F05 Regression:
PASS — guest/auth/address/payment backend coverage and guest-checkout browser suite. New Buy Now scenarios also use guest carts with session headers.

F06 Regression:
PASS — CatalogFiltersTest and desktop/mobile catalog-filters browser suite.

F07 Regression:
PASS — CatalogColorsTest and desktop/mobile catalog-colors browser suite.

Remaining Risks:
Browser tests use mocked APIs and mobile emulation; integrated staging/physical-device acceptance remains separate. A lost network response cannot prove whether a write committed; there is no automatic retry or new cart-write idempotency protocol. Pending guards and ordering apply within the current browser store, not across separate tabs/devices. Backend stock/checkout validation remains authoritative.

Related Follow-Up:
F09 — Formatted Product Descriptions. Not started; stop after F08 for review.

Before/after example:

```text
BEFORE (supplied audit)
Click Buy Now → POST /cart → 422 → incorrect checkout navigation/unhandled error
Checked-out revision already blocked navigation after a false cart result,
but still lacked duplicate protection and awaited router error recovery.
Add to Cart still opened the drawer immediately, even before a rejected add.

AFTER
Click Buy Now → Preparing checkout… → await POST /cart
Success → apply server cart → await navigation to checkout
422 → remain on product → actionable existing alert → buttons restored
Repeated pending clicks → one add request
Router failure → item remains added → View Cart recovery
Add to Cart → drawer opens only after successful response
```

Verification commands (all application runtimes inside Docker):

```sh
docker compose exec -T app php artisan test --compact --filter 'CatalogTest|CatalogFiltersTest|CatalogColorsTest|ShopTest|CategoryTest|ShopCouponOwnershipTest|CartRecoveryTest|CartMutationTest|CartTest|Checkout|CouponTest|CodCollectionTest|BakongPaymentTest|AuthTest|NotificationTest|MarketplaceFlowTest'
docker compose run --rm browser-tests npx playwright test tests/e2e/cart-mutations.spec.ts tests/e2e/catalog-colors.spec.ts tests/e2e/catalog-filters.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts tests/e2e/guest-checkout.spec.ts tests/e2e/account-ui.spec.ts --output=/tmp/f08-final-results --reporter=line --retries=0
docker compose exec -T frontend npm run build
docker compose exec -T app vendor/bin/pint --test app/Services/CartService.php tests/Feature/Api/CartTest.php tests/Feature/Api/CartMutationTest.php
```

PHP formatting and git diff --check passed. API workers were reloaded with the tested cart messages. No development MySQL data reset, production deployment, credentials change or commit is included. Stop after F08.
