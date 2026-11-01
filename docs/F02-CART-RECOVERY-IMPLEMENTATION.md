# F02 — CART RECOVERY AFTER VARIANT DELETION

Implemented and verified on 2 November 2026.

## Status

**PASS**. F02 is implemented. F01 ownership, discount allocation, usage, and retry tests still pass. F03 was not started.

## Root Cause

Products and product variants use soft deletes. Soft-deleting a variant leaves its cart row, while Eloquent's normal `CartItem::variant()` relationship resolves to null. `CartItemResource` already safely returned `variant: null`; the reproduced failure was in Vue. `ApiCartItem` incorrectly required both variant and product objects, and `toDisplayItem()` read `variant.product`, `variant.id`, and other fields without a null guard. A single exception aborted the entire `map()`. `fetch()` swallowed the exception and marked the store loaded, so the initial empty item array looked like an empty cart.

The API had no row availability contract. Cart totals also included inactive products/variants, suspended shops, and insufficient-stock lines; coupon eligibility could therefore include unavailable goods. Mutation handlers commonly launched promises without visible failure feedback.

## Old Behavior

One null variant could make all valid cart items disappear from the UI. Loading, API failure, and successful empty-cart responses were indistinguishable. Unavailable goods with surviving variant relations could still inflate displayed subtotal and coupon minimum spend. Checkout already rejected several stale states, but the customer lacked a reliable recovery UI.

## New Behavior

- Every stored cart row survives reads. Valid rows remain available; stale rows show a clear reason and a removal button. No alternate variant is selected.
- The cart page groups **Available** and **Needs attention** rows. The drawer uses the same row renderer and recovery actions. English and Khmer messages cover deletion, inactivity, shop availability, stock shortages, and invalid quantity.
- Only purchasable lines contribute to subtotal, tax, total, and coupon eligible spend. A line with insufficient stock contributes zero until the customer explicitly reduces its quantity. Checkout delivery groups use available items only.
- The badge, drawer, and cart count sum **all stored quantities**, including unavailable rows. A cart containing only unavailable rows stays visible and does not show the empty-cart message.
- Checkout is blocked until unavailable items are removed or insufficient quantities resolved. Direct checkout navigation links back to cart recovery. Backend validation remains mandatory and returns row-specific errors before creating orders, coupon usage, or reservations; atomic stock reservation remains in place.
- Loading, failed load with retry, successful empty cart, and unavailable rows have separate UI states. Failed mutations preserve existing state and show feedback. Server-side exceptions from HTTP 5xx responses use a safe retry message.
- Applied coupons are revalidated after cart updates and checkout refresh. F01's backend quote, shop ownership, minimum-spend, cap, allocation, and usage rules are preserved.

## Backend Changes

`CartService::availability()` centralizes the live availability check shared by resources, totals, coupon preview, and checkout. It returns `available`, `unavailable_reason`, and `available_quantity`. Stock uses inventory quantity minus reserved quantity and never goes below zero. The full requested quantity must be available; there is no partial charge or automatic reduction.

`CartResource` now delegates totals to `CartService`, removing the conflicting resource calculation. Cart relations are eager-loaded consistently to avoid per-row product/shop/inventory lookups. `CartItemResource` keeps nullable variant/product data and returns the existing product-price fallback consistently with cart totals.

`CheckoutService` checks all row availability before pricing and order creation. Errors include `errors.cart` and `errors["cart_items.<id>"]` with a removal or quantity-reduction instruction. The existing stock-error response remains compatible. `CouponService` excludes unavailable cart rows while keeping order usage validation based on immutable snapshots.

## Frontend Changes

The Pinia store safely normalizes null variants and products, retains row IDs and quantities, and exposes available/unavailable groups and a checkout-readiness getter. `CartLine` and `CartLoadState` reuse rendering and recovery behavior across the cart, drawer, and checkout. Broken images have placeholders. Deleted or unavailable products do not produce broken product links or financial line totals.

Mutations return a boolean result, report errors globally, and keep controls disabled while requests are pending. Wishlist actions remove a saved product only after a successful cart add. Buy Now waits for a successful add before navigating. Checkout refreshes cart availability when opened and after a 422 response.

## Type Changes

`ApiCartItem.variant` and its nested `product` are nullable; price allows null defensively. Availability fields use a `CartUnavailableReason` union. Normalized `CartItem` includes availability, reason, and stock quantity. Missing relations are narrowed safely without `any` or non-null assertions.

## Files Changed

| Area | Files |
| --- | --- |
| Cart service/API | `backend/app/Services/CartService.php`, `backend/app/Http/Controllers/Api/CartController.php`, `backend/app/Http/Resources/CartResource.php`, `backend/app/Http/Resources/CartItemResource.php` |
| Checkout and F01 compatibility | `backend/app/Services/CheckoutService.php`, `backend/app/Services/CouponService.php` |
| Cart types/store | `frontend/src/api/cart.ts`, `frontend/src/types/index.ts`, `frontend/src/stores/cart.ts` |
| Recovery UI | `frontend/src/components/CartLine.vue`, `frontend/src/components/CartLoadState.vue`, `frontend/src/components/CartDrawer.vue`, `frontend/src/views/CartView.vue`, `frontend/src/views/CheckoutView.vue` |
| Mutation feedback/callers | `frontend/src/App.vue`, `frontend/src/views/ProductDetailView.vue`, `frontend/src/views/account/WishlistView.vue`, `frontend/src/views/account/DashboardView.vue` |
| Translations | `frontend/src/locales/en.json`, `frontend/src/locales/km.json` |
| Tests | `backend/tests/Feature/Api/CartRecoveryTest.php`, `frontend/tests/e2e/cart-recovery.spec.ts` |
| Documentation | `docs/API-REFERENCE.md`, `docs/FRONTEND.md`, `docs/README.md`, `docs/Cart Recovery After Product Variant Deletion.md`, this report |

## Database and Login Merge Behavior

No migration, snapshots, or FK changes were needed. `products` and `product_variants` have `deleted_at`. Normal soft deletion preserves cart rows. `cart_items.product_variant_id` has an existing cascading FK for permanent deletion; permanent product deletion also cascades through variants. The application still uses its existing soft-delete behavior for normal catalog removal.

Cart reads do not silently clean up rows. Existing login merge behavior intentionally skips unavailable **guest** rows, caps merged quantities at live stock, then clears the guest cart. This policy was preserved and explicitly tested. Already stored unavailable **account** rows remain visible after login, and valid guest rows still merge successfully. Login merge therefore has different cleanup rules from ordinary cart reads.

Deleted relation names are not recoverable from a cart snapshot because no such snapshot exists. These rows use a generic **Unavailable item** title plus a specific reason and removal action. Order history snapshots are unchanged.

## Tests Added

**13 backend cases** cover a three-shop valid/deleted/valid cart; product deletion; inactive product and variant; suspended and deleted shops; zero stock; reserved-stock shortage; server totals and badge quantities; checkout rejection and zero financial side effects; removal and successful checkout recovery; explicit stock reduction; guest isolation/removal; login merge with stale account and guest rows; and coupon eligible spend excluding unavailable goods.

**12 browser scenarios**, each run on desktop and mobile, cover the mixed-cart regression through checkout, guest drawer removal, null product and unavailable-only carts, direct checkout blocking, explicit quantity recovery, load error/retry, loading state, coupon invalidation after a checkout refresh, failed update/remove/clear mutations, and safe HTTP 5xx feedback. Existing wishlist coverage verifies failed add feedback without losing the saved product. The mixed-cart browser test also checks for uncaught page errors and captures screenshots.

The project has Playwright browser testing and no existing frontend unit/component test runner; no additional framework was introduced.

## Backend Tests

**Passed: 473. Failed: 0.** Complete Laravel suite: 3,502 assertions, 190.99 seconds. The focused cart/checkout/coupon run also passed 57 tests with 461 assertions before the final additional rollback assertions.

Laravel's configured test database is disposable SQLite in memory. All PHP, Composer, Node, tests, and builds ran through the provided Docker environment. No development database reset or seed command was used.

## Browser Tests

**Passed: 38 distinct desktop/mobile checks. Failed: 0 in final verification.** The main focused run passed 36 checks. A final mutation and visual run passed 10 checks, including two new server-error checks and eight repeated cases after the final mutation handling change. These totals overlap and should not be added as 46 distinct cases.

The checks include all new F02 scenarios, six F01 coupon checks, four existing checkout checks, two wishlist failed-add checks, and two storefront layout checks. Desktop and mobile recovery screenshots were visually inspected. The unrelated full browser suite was not rerun.

## Type Check

**PASS**, through `vue-tsc -b` in `npm run build`.

## Production Build

**PASS**, through the frontend Docker container. Changed PHP files also pass Laravel Pint; the patch passes `git diff --check`.

## F01 Regression

**PASS.** Full backend regression includes coupon ownership, mixed-shop financial allocation, capped discounts, usage limits, rollback, and idempotent retry. All six F01 browser cases pass. The new F02 coupon tests additionally show that unavailable goods cannot satisfy shop minimum spend or retain an old discount.

Reproduction commands:

```sh
docker compose exec -T app php artisan test --compact --filter='CartRecoveryTest|CartTest|ShopCouponOwnershipTest|CheckoutTest|CouponTest'
docker compose exec -T app php artisan test --compact
docker compose exec -T frontend npm run build
docker compose --profile test run --rm --no-deps browser-tests npx playwright test tests/e2e/cart-recovery.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/commerce.spec.ts tests/e2e/account-ui.spec.ts --grep 'cart|coupon|checkout|store pages'
```

## Remaining Risks

- Availability is a point-in-time view. Stock and publication can change after a cart response; checkout validation and atomic reservation remain the final protection.
- SQLite tests verify business results but do not certify simultaneous MySQL lock contention. Browser tests mock API responses; they do not exercise live bank transfers.
- Existing permanent-delete cascades and guest-merge cleanup remain intentional differences from read-time recovery, as documented above.
- A deleted relation's original product name is not shown when no current relation is available; no cart snapshot schema was added.

## Related Follow-Up

**F03 — Discount Cap & Checkout Total Consistency** remains separate. F02 continues to display F01's backend coupon quote and excludes unavailable lines. The existing frontend tax/shipping estimate was not broadly redesigned.

## Example

Before:

```text
Valid Item
Deleted Variant
→ Entire cart appeared empty
```

After:

```text
Valid Item        Available
Deleted Variant   Unavailable / Remove
→ Cart remains usable
```

For $50 and $30 available goods plus a $100 unavailable line, purchasable subtotal is **$80**, tax is **$8**, and cart total is **$88** before shipping. The unavailable row remains visible until removed.
