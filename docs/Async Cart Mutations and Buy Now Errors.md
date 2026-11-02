# E-KHMER Improvement #08 — Async Cart Mutations & Buy Now Errors

Act as a senior **Laravel + Vue.js + TypeScript Marketplace Engineer, Cart Engineer, Async State Engineer, UX Engineer, Error-Handling Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

Previous fixes:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
F03 — Discount Cap & Checkout Total Consistency
F04 — COD Collection & Paid-State Lifecycle
F05 — Guest Checkout Policy & Web Flow
F06 — Rating & Price Filters
F07 — Mobile Color Filters
```

Do NOT regress those fixes.

Now work ONLY on:

# F08 — Async Cart Mutations & Buy Now Errors

---

# 1. CURRENT DEFECT

The current audit reproduced this behavior:

```text
Customer opens Product Detail

Clicks:
Buy Now

Frontend sends:
POST /cart

Backend responds:
422 Validation Error

BUT frontend still:
→ navigates to checkout
→ throws an unhandled error
```

This is incorrect.

The application must never navigate to checkout if adding the requested product to the cart failed.

---

# 2. PRIMARY GOAL

Make cart actions transactional from the frontend user's perspective.

Correct flow:

```text
User clicks Buy Now
        ↓
Validate selection
        ↓
Start loading
        ↓
Await cart API request
        ↓
IF SUCCESS
        ↓
Update cart state
        ↓
Navigate to checkout

IF FAILURE
        ↓
Do NOT navigate
        ↓
Show actionable error
        ↓
Restore button state
```

The same principle must apply to:

```text
Add to Cart
Buy Now
Quantity Update
Remove Item
Clear Cart
Cart Drawer Mutations
Product Listing Add-to-Cart
```

where relevant.

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing code.

Focus especially on:

```text
frontend/src/views/ProductDetailView.vue
frontend/src/views/ShopView.vue
frontend/src/components/CartDrawer.vue
frontend/src/views/CartView.vue
frontend/src/stores/cart.ts
frontend/src/api/cart.ts
```

Also inspect:

```text
frontend router
toast/notification system
loading button components
error helpers
API client/interceptors

backend/app/Http/Controllers/Api/CartController.php
backend/app/Services/CartService.php
cart request validation
cart resources
```

Search for:

```text
addToCart
buyNow
updateQuantity
removeItem
clearCart
router.push
navigate
await
Promise
catch
finally
loading
pending
```

Trace actual behavior.

Do NOT assume the root cause.

---

# 4. IDENTIFY THE ROOT CAUSE

Determine exactly why navigation happens after failure.

Possible causes:

```text
await missing

cartStore.addItem() does not rethrow

router.push() runs outside success branch

Promise is started but not awaited

catch swallows failure

store returns void even when request fails

optimistic UI marks success too early

Buy Now shares logic with Add to Cart incorrectly
```

Document the confirmed root cause.

---

# 5. BUY NOW SUCCESS CONTRACT

Define a clear contract.

Example:

```ts
const success = await cartStore.addItem(...)
```

Then:

```text
success = true
→ navigate to checkout

success = false
→ stay on product page
```

Or use exceptions consistently.

Do NOT mix:

```text
boolean return
+
exceptions
+
silent catch
```

without a clear convention.

---

# 6. AWAIT EVERY REQUIRED MUTATION

Bad:

```ts
cartStore.addItem(payload)
router.push('/checkout')
```

Correct concept:

```ts
await cartStore.addItem(payload)
await router.push('/checkout')
```

only after successful cart mutation.

Do not navigate until backend confirms the item exists in cart.

---

# 7. DO NOT SWALLOW STORE ERRORS

Inspect:

```text
frontend/src/stores/cart.ts
```

If current pattern is:

```ts
try {
  await api.addItem(...)
} catch (error) {
  console.error(error)
}
```

then caller cannot know the mutation failed.

Prefer:

```text
catch
→ update store error state if needed
→ rethrow normalized error
```

or return an explicit result object.

Example concept:

```ts
{
  success: false,
  message: "...",
  code: "INSUFFICIENT_STOCK"
}
```

Use existing project conventions.

---

# 8. ONE ERROR-HANDLING CONVENTION

Choose one consistent pattern.

Recommended options:

## Pattern A — Throw normalized error

```text
API
→ store
→ normalized error
→ component catch
→ user message
```

or:

## Pattern B — Result object

```text
{
  ok: false,
  error: ...
}
```

Do not use both randomly.

---

# 9. BUY NOW BUTTON STATE

When clicked:

```text
Buy Now
```

immediately enter pending state:

```text
Processing...
```

or spinner.

Disable the button until the request finishes.

Prevent:

```text
click
click
click
```

creating duplicate requests.

---

# 10. ADD TO CART BUTTON STATE

Apply similar protection to:

```text
Add to Cart
```

Example:

```text
Idle
→ Adding...
→ Added
```

Failure:

```text
Adding...
→ Failed
→ button restored
```

Do not leave the button permanently disabled after failure.

---

# 11. PRODUCT SELECTION VALIDATION

Before API request, validate required selections.

Example:

```text
Color required
Size required
```

If missing:

```text
Please select a size.
```

Do NOT call the backend unnecessarily.

Backend validation must still remain.

---

# 12. VARIANT RESOLUTION

Example:

```text
Color = Black
Size = XL
```

If no valid variant resolves:

```text
Selected option is unavailable.
```

Do not call Buy Now with:

```text
variant_id = null
```

unless backend contract allows it.

Preserve F02 handling for stale variants.

---

# 13. STOCK VALIDATION

Backend remains source of truth.

Frontend may display:

```text
In stock
Only 2 left
```

but when backend returns:

```text
422
Only 2 items are available.
```

the UI must show that message.

Do not navigate.

---

# 14. OUT-OF-STOCK BUY NOW

Scenario:

```text
Product available when page loaded
↓
Another customer buys remaining stock
↓
Current customer clicks Buy Now
↓
backend rejects
```

Expected:

```text
Stay on product page

Show:
This product is now out of stock.

Refresh product/cart state if useful.
```

No checkout navigation.

---

# 15. INSUFFICIENT QUANTITY

Example:

```text
Requested:
5

Available:
2
```

Expected:

```text
Only 2 items are currently available.
```

Do not silently reduce quantity unless existing business rules explicitly do so.

---

# 16. INACTIVE PRODUCT

Scenario:

```text
Seller hides product
while customer has detail page open
```

Buy Now returns business error.

Expected:

```text
This product is no longer available.
```

No checkout navigation.

---

# 17. INACTIVE SHOP

Scenario:

```text
Shop suspended
```

Expected:

```text
Products from this shop are temporarily unavailable.
```

No cart success state.

No checkout navigation.

---

# 18. NETWORK ERROR

Example:

```text
API unavailable
timeout
offline
```

Expected:

```text
We couldn't add this item to your cart. Please try again.
```

Do NOT display:

```text
AxiosError
Network Error object
undefined
```

to customer.

---

# 19. SERVER ERROR

For unexpected 500:

```text
Something went wrong while updating your cart.
Please try again.
```

Log technical detail through existing error infrastructure.

Do not expose stack trace.

---

# 20. VALIDATION ERROR NORMALIZATION

Create/reuse a helper for API validation messages.

Laravel may return:

```json
{
  "message": "The selected variant is invalid.",
  "errors": {
    "variant_id": [
      "The selected variant is invalid."
    ]
  }
}
```

Frontend should extract a useful message.

Do not duplicate extraction logic in every component.

---

# 21. CART STORE LOADING STATE

Avoid one global loading boolean if it causes poor UX.

Possible states:

```text
addingItem
updatingItemId
removingItemId
clearingCart
```

Adapt to current architecture.

Goal:

```text
only relevant action disabled
```

where practical.

---

# 22. DUPLICATE ADD-TO-CART

Scenario:

```text
User double-clicks Add to Cart
```

Ensure behavior is intentional.

If existing CartService merges quantities:

```text
two rapid clicks should not accidentally create unexpected quantity
```

Disable pending action or rely on explicit safe logic.

Recommended:

```text
disable while request pending
```

---

# 23. DUPLICATE BUY NOW

This is critical.

Double-clicking:

```text
Buy Now
```

must not:

```text
send two POSTs
add quantity twice
navigate twice
```

Use local pending state.

---

# 24. NAVIGATION ORDER

Correct:

```text
await add to cart
↓
confirm success
↓
navigate
```

Incorrect:

```text
start add request
↓
navigate
↓
request later fails
```

---

# 25. ROUTER ERROR

If cart succeeds but router navigation fails unexpectedly:

Do not roll back the cart item.

The item was legitimately added.

Show a safe recovery:

```text
Item added to cart.
Unable to open checkout.
[Open Cart]
```

if needed.

This is rare; do not overengineer.

---

# 26. CART DRAWER MUTATIONS

Inspect:

```text
CartDrawer.vue
```

For:

```text
increase quantity
decrease quantity
remove item
```

Each mutation should:

```text
await request
show loading
handle failure
restore correct state
```

Do not silently fail.

---

# 27. QUANTITY UPDATE FAILURE

Example:

```text
Current quantity:
2

User changes:
3

Backend rejects:
stock = 2
```

Expected final UI:

```text
quantity remains/reverts to 2
```

and show:

```text
Only 2 are available.
```

Do not leave UI showing `3`.

---

# 28. OPTIMISTIC UPDATE

If current cart uses optimistic UI:

```text
update UI first
→ call API
```

then failure must:

```text
rollback UI
```

If rollback is complex, prefer pessimistic update for critical quantity/financial state.

Do not leave cart state inconsistent with backend.

---

# 29. REMOVE FAILURE

Scenario:

```text
Customer clicks remove
API fails
```

Expected:

```text
item remains in cart
```

or restored if optimistic removal was used.

Show:

```text
Could not remove this item. Please try again.
```

---

# 30. CLEAR CART FAILURE

If:

```text
Clear Cart
```

fails:

```text
do not show empty cart
```

unless backend actually cleared it.

Restore/current state should remain correct.

---

# 31. LISTING CARD ADD TO CART

The audit says apply consistent feedback to listing cards too.

Inspect:

```text
ShopView.vue
product card components
HomeView.vue product rails
shop storefront
```

If Add to Cart exists there:

```text
await it
handle errors
prevent duplicate click
```

Do not fix ProductDetail only.

---

# 32. VARIANT PRODUCTS ON LISTING

If a product requires option selection:

```text
Color
Size
```

listing card should NOT blindly add a random/default variant unless that is current intended behavior.

If options are required:

```text
Choose options
```

may navigate to product detail.

Do not redesign this unless current behavior is broken.

---

# 33. CART STATE AFTER SUCCESS

After successful add:

```text
cart items
cart count
subtotal
```

should update from server response or a reliable refresh.

Do not manually increment counts if server might merge quantities differently.

---

# 34. CART STATE AFTER FAILURE

After failed add:

```text
cart count unchanged
subtotal unchanged
drawer unchanged
```

No phantom items.

---

# 35. BUY NOW WITH EXISTING CART

Determine current intended behavior.

Example:

```text
Cart already has Product A

Customer Buy Now Product B
```

Likely:

```text
Product B added
→ checkout contains A + B
```

unless E-KHMER intentionally has dedicated Buy Now checkout.

Do not change existing business semantics.

F08 is about async correctness.

---

# 36. BUY NOW WITH SAME PRODUCT ALREADY IN CART

Test:

```text
Product A qty 1 in cart
Buy Now Product A qty 1
```

Expected behavior should follow current CartService rules.

Possibly:

```text
quantity becomes 2
```

Then navigate.

Do not create duplicate lines unexpectedly.

---

# 37. F02 INTERACTION

Stale cart items must not make Buy Now crash.

Example:

```text
Cart contains stale variant
Customer buys valid Product B
```

F02 should preserve valid operation while stale row remains handled safely.

---

# 38. F03 INTERACTION

After successful Buy Now:

```text
checkout totals
```

must be calculated from backend state.

Do not pass:

```text
price
discount
subtotal
```

through route state as financial authority.

---

# 39. F05 INTERACTION

If guest checkout is supported:

```text
Guest
→ Buy Now
→ successful cart mutation
→ checkout
```

must work without forced login according to F05 policy.

If account-required checkout was selected:

```text
Buy Now success
→ proper login flow
```

Preserve chosen policy.

---

# 40. ERROR UI

Use the existing toast/banner/form error component.

Do not create a new design system.

Recommended severity:

```text
Validation/business error:
warning/error toast

Network/server error:
error toast
```

Keep messages actionable.

---

# 41. ACCESSIBILITY

Pending buttons should expose:

```text
disabled
aria-busy
```

where applicable.

Error messages should be perceivable.

Do not rely only on button color.

---

# 42. MOBILE UX

On mobile:

```text
sticky Add to Cart / Buy Now buttons
```

must also disable while pending.

Do not allow the bottom bar to fire duplicate requests.

---

# 43. API CLIENT

Inspect whether API client/interceptor currently:

```text
throws automatically
returns response.data
converts 422
redirects on 401
```

Understand that behavior before changing store logic.

Do not add duplicate error parsing if global helpers already exist.

---

# 44. 401 BEHAVIOR

If cart mutation requires auth in some path:

Follow F05 policy.

Guest cart operations should not accidentally trigger global login redirects if guest carts are supported.

Do not weaken legitimate authenticated endpoints.

---

# 45. 419 / SESSION EXPIRY

If applicable:

```text
guest/session expired
```

show safe recovery.

Example:

```text
Your session expired. Please refresh and try again.
```

Do not navigate to checkout with stale cart state.

---

# 46. CANCELLED REQUEST

If request is cancelled because user navigates away:

Do not show a false error toast unnecessarily.

Differentiate intentional abort from real failure if existing API layer supports it.

---

# 47. REQUEST RACE

Example:

```text
User changes selected variant
while previous add request is still pending
```

The pending request should use the payload captured at click time.

Do not mutate payload references after request starts.

---

# 48. BUTTON FEEDBACK

Possible success UX:

```text
Add to Cart
→ Adding...
→ Added ✓
→ Add to Cart
```

Buy Now:

```text
Buy Now
→ Preparing checkout...
→ navigate
```

Do not display success before API confirms.

---

# 49. BACKEND RESPONSE

Inspect whether CartController returns enough data.

Prefer successful mutation returning:

```text
updated cart
or
updated cart item
```

Avoid requiring several unnecessary follow-up requests.

But do not redesign backend if existing response is sufficient.

---

# 50. AUTOMATED STORE TESTS

If Pinia/store tests exist, add:

## Test 1

```text
add success
→ store updates
→ resolves successfully
```

## Test 2

```text
add 422
→ store does not fake success
→ caller receives failure
```

## Test 3

```text
network failure
→ caller receives normalized failure
```

## Test 4

```text
quantity update failure
→ state remains/reverts correctly
```

## Test 5

```text
remove failure
→ item remains
```

---

# 51. COMPONENT TESTS

Product detail:

```text
Buy Now success
→ navigate
```

Product detail:

```text
Buy Now failure
→ no navigation
→ error visible
```

Add to Cart:

```text
failure
→ no fake success
```

Duplicate click:

```text
one request only
```

---

# 52. BROWSER / E2E TEST — CRITICAL F08 REPRODUCTION

Reproduce exact defect.

Mock/intercept:

```text
POST /cart
→ 422
```

Then:

```text
click Buy Now
```

Verify:

```text
URL remains product detail page

checkout not opened

error message visible

no unhandled page error

button re-enabled
```

This is the main acceptance test.

---

# 53. BROWSER SUCCESS TEST

Mock/intercept:

```text
POST /cart
→ 200/201
```

Then:

```text
click Buy Now
```

Verify:

```text
request completes
then checkout navigation occurs
```

Not before.

---

# 54. SLOW NETWORK TEST

Delay response:

```text
2 seconds
```

Verify:

```text
button disabled

second click does not create second request

no navigation until response succeeds
```

---

# 55. OUT-OF-STOCK TEST

Return:

```text
422
Only 0 available
```

Verify:

```text
no checkout navigation

clear stock message shown
```

---

# 56. QUANTITY TEST

Cart drawer:

```text
qty 2
→ attempt qty 10
→ backend 422
```

Expected:

```text
UI stays at/reverts to 2
```

---

# 57. REMOVE FAILURE TEST

Mock:

```text
DELETE /cart/items/{id}
→ 500
```

Expected:

```text
item remains
error shown
```

---

# 58. MOBILE E2E

Run critical Buy Now failure case in mobile viewport too.

Verify sticky buttons behave safely.

---

# 59. UNHANDLED PROMISES

The browser test should fail if there are:

```text
unhandledrejection
uncaught exception
console error caused by application failure
```

where current testing infrastructure supports this.

Fix the actual Promise chain.

Do not suppress console errors just to make tests pass.

---

# 60. BACKEND TESTS

Backend changes may be minimal.

Still run existing cart validation tests covering:

```text
invalid variant
inactive product
inactive shop
out of stock
quantity above stock
```

Ensure API returns correct business errors.

---

# 61. F01 REGRESSION

Coupon isolation still passes.

---

# 62. F02 REGRESSION

Stale cart recovery still passes.

Particularly:

```text
valid item + stale item
→ valid item usable
```

---

# 63. F03 REGRESSION

Cart/checkout financial reconciliation still passes.

---

# 64. F04 REGRESSION

COD flow unaffected.

---

# 65. F05 REGRESSION

Guest/account checkout policy unaffected.

---

# 66. F06 REGRESSION

Rating/price filters unaffected.

---

# 67. F07 REGRESSION

Mobile color filtering unaffected.

---

# 68. TYPE CHECK

Run frontend TypeScript type check.

No:

```text
any
@ts-ignore
```

used merely to bypass proper result/error typing.

---

# 69. PRODUCTION BUILD

Run frontend production build.

Must pass.

---

# 70. DOCKER ONLY

Use project Docker containers for:

```text
PHP
Composer
Node
tests
build
```

according to existing project workflow.

Do not wipe important development data.

---

# 71. DO NOT START F09

Only solve F08.

Do not begin product-description rendering/sanitization work.

That is:

```text
F09 — Formatted Product Descriptions
```

---

# 72. DEFINITION OF DONE

Do not mark F08 complete until:

```text
[ ] Buy Now awaits cart API

[ ] Failed Buy Now never navigates to checkout

[ ] Successful Buy Now navigates only after success

[ ] Add to Cart awaits API

[ ] Duplicate clicks are blocked

[ ] Loading states work

[ ] Validation messages are visible

[ ] Stock errors are visible

[ ] Network errors are visible

[ ] Unexpected server errors are safe

[ ] No unhandled Promise rejection

[ ] Cart store does not swallow critical errors

[ ] Failed cart mutation does not fake success

[ ] Quantity failure restores correct state

[ ] Remove failure preserves item

[ ] Clear failure preserves cart state

[ ] Product listing Add-to-Cart follows same safety rules

[ ] Mobile buttons follow same safety rules

[ ] Guest flow follows F05 policy

[ ] F02 stale-item handling still works

[ ] F03 totals remain backend-authoritative

[ ] Backend cart tests pass

[ ] Frontend tests pass

[ ] Desktop browser tests pass

[ ] Mobile browser tests pass

[ ] Type check passes

[ ] Production build passes

[ ] F01–F07 regressions pass
```

---

# 73. FINAL REPORT

At completion report exactly:

```text
F08 — ASYNC CART MUTATIONS & BUY NOW ERRORS

Status:
PASS / NEEDS WORK

Root Cause:

Old Buy Now Flow:

New Buy Now Flow:

Cart Store Error Strategy:

API Error Normalization:

Duplicate-Click Protection:

Loading State:

Stock Error Handling:

Network Error Handling:

Unhandled Promise Errors:
NONE / LIST

Failed Mutation State Recovery:

Files Changed:

Schema Changes:
None / List

Tests Added:

Backend Tests:
Passed / Failed

Frontend Tests:
Passed / Failed

Desktop Browser Tests:
Passed / Failed

Mobile Browser Tests:
Passed / Failed

Type Check:
PASS / FAIL

Production Build:
PASS / FAIL

F01 Regression:
PASS / FAIL

F02 Regression:
PASS / FAIL

F03 Regression:
PASS / FAIL

F04 Regression:
PASS / FAIL

F05 Regression:
PASS / FAIL

F06 Regression:
PASS / FAIL

F07 Regression:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F09 — Formatted Product Descriptions
```

Also show a before/after example:

```text
BEFORE

Click Buy Now
→ POST /cart
→ 422
→ frontend still navigates
→ checkout opens incorrectly
→ unhandled error


AFTER

Click Buy Now
→ button becomes "Preparing..."
→ await POST /cart

If success:
→ cart updates
→ navigate to checkout

If 422:
→ stay on product page
→ show actionable error
→ button becomes available again
→ no unhandled error
```

Stop after F08.

Do NOT automatically begin F09.

I will review the result first.
