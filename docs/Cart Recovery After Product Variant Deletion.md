# E-KHMER Improvement #02 — Cart Recovery After Product / Variant Deletion

Act as a senior **Laravel + Vue.js + TypeScript E-Commerce Engineer, Cart Business Logic Engineer, UX Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

F01 Shop Coupon Ownership has already been completed and tested.

Do NOT modify or weaken the F01 fixes.

Now work ONLY on:

# F02 — Cart Recovery After Product / Variant Deletion

---

# 1. CURRENT DEFECT

The audit reproduced this situation:

```text
Cart contains:

Item A
→ Valid product
→ Valid variant

Item B
→ Product exists or historical cart row exists
→ Variant has been deleted / no longer resolves
→ variant = null
```

Current broken behavior:

```text
One stale cart item
        ↓
Frontend cart mapping fails
        ↓
Entire cart appears empty
```

This means a customer may still have valid purchasable items, but one invalid variant causes the whole cart UI to break.

This must be fixed.

---

# 2. EXPECTED BEHAVIOR

The cart must be resilient to stale/unavailable items.

Example:

```text
Cart

Product A
Valid
$50

Product B
Variant deleted
Unavailable

Product C
Valid
$30
```

Expected UI:

```text
Product A      $50     Available
Product B              Unavailable
Product C      $30     Available
```

or safely:

```text
Product A      $50
Product C      $30

1 unavailable item needs attention.
```

The valid items must remain visible and usable.

The customer should be able to remove the unavailable item.

---

# 3. FIRST INSPECT EXISTING IMPLEMENTATION

Inspect actual code before changing anything.

Focus especially on:

```text
frontend/src/stores/cart.ts
frontend/src/api/cart.ts
frontend/src/components/CartDrawer.vue
frontend/src/views/CartView.vue
frontend/src/views/CheckoutView.vue

backend/app/Http/Resources/CartItemResource.php
backend/app/Http/Controllers/Api/CartController.php
backend/app/Services/CartService.php

Cart model
CartItem model
Product model
ProductVariant model

existing cart tests
existing browser tests
```

Also inspect:

```text
soft deletes
product deletion behavior
variant deletion behavior
product visibility
shop suspension
stock availability
```

Do NOT assume the bug is frontend-only.

Trace:

```text
Database
→ Laravel Cart Service
→ API Resource
→ JSON response
→ TypeScript types
→ Pinia/store
→ Cart Drawer
→ Cart Page
→ Checkout
```

---

# 4. IDENTIFY ROOT CAUSE

Determine exactly why this happens.

Possible causes may include:

```text
frontend assumes variant is always non-null

API returns:
variant: null

mapping reads:
item.variant.id
item.variant.price
item.variant.attributes

one exception aborts entire transformation

TypeScript incorrectly declares variant as required

Cart drawer treats mapping failure as empty cart

backend resource doesn't expose availability state
```

Do not guess.

Confirm the actual root cause.

---

# 5. DO NOT SILENTLY DELETE USER CART DATA

Do NOT automatically remove stale cart rows without clear reasoning.

A deleted/unavailable product may need to be shown so the customer understands what happened.

Preferred behavior:

```text
Stale item
        ↓
Mark unavailable
        ↓
Customer sees reason
        ↓
Customer removes item
```

Automatic cleanup may be acceptable only if existing business rules intentionally require it.

If auto-cleanup already exists on the backend, make it explicit and test it.

---

# 6. CART ITEM AVAILABILITY MODEL

Create or reuse a clear concept such as:

```text
available
unavailable
```

Possible reasons:

```text
product_deleted
product_inactive
shop_inactive
variant_deleted
variant_inactive
out_of_stock
insufficient_stock
```

Do not expose internal exceptions to customers.

Example API:

```json
{
  "id": 25,
  "quantity": 2,
  "available": false,
  "unavailable_reason": "variant_deleted",
  "product": {
    "id": 10,
    "name": "T-Shirt"
  },
  "variant": null
}
```

Only add fields if they fit the existing API design.

Do not unnecessarily redesign the whole cart response.

---

# 7. TYPESCRIPT TYPES

Fix TypeScript definitions.

If backend can return:

```text
variant = null
```

then frontend types must allow:

```ts
variant: ProductVariant | null
```

Do NOT hide the problem with:

```ts
as any
```

or:

```ts
item.variant!
```

unless the code path has already validated the value.

---

# 8. SAFE CART NORMALIZATION

Cart normalization must tolerate incomplete/stale items.

Bad:

```text
map every item assuming:
product exists
variant exists
image exists
price exists
```

Better:

```text
For each cart item:

validate product
validate variant
determine availability
preserve item
return safe normalized object
```

One bad item must never crash processing of other items.

---

# 9. CART DRAWER

Update cart drawer behavior.

Expected:

```text
Valid item
→ normal cart row

Unavailable item
→ warning state
```

Example:

```text
Aurora Wireless Headphones
Variant no longer available

[ Remove ]
```

Use clear customer messaging such as:

```text
This option is no longer available.

This product is no longer available.

This shop is temporarily unavailable.

Only 2 are currently available.
```

Do not display:

```text
variant=null
undefined
TypeError
AxiosError
```

---

# 10. CART PAGE

The full cart page should clearly separate:

```text
Available items
Unavailable items
```

Example:

```text
3 items in cart

Available
-------------------
Product A
Product C

Needs attention
-------------------
Product B
This option is no longer available.
[Remove]
```

Do not make the customer think the whole cart disappeared.

---

# 11. CART TOTALS

Unavailable items must NOT contribute to:

```text
subtotal
discount
tax
shipping
grand total
```

unless current business rules say otherwise.

Example:

```text
Product A = $50 valid

Product B = $100 unavailable

Product C = $30 valid
```

Expected purchasable subtotal:

```text
$80
```

not:

```text
$180
```

Backend remains financial source of truth.

---

# 12. CHECKOUT BLOCKING

A customer must not checkout with unresolved invalid items.

Two acceptable UX patterns:

## Option A

Allow checkout button but backend rejects with actionable details.

Better UX:

## Option B

Disable checkout while invalid items remain:

```text
1 item needs attention before checkout.
```

Then show:

```text
[ Remove unavailable item ]
```

Use the pattern that best fits existing E-KHMER UX.

Backend validation must still exist even if frontend disables the button.

---

# 13. PRODUCT DELETED

Test:

```text
Product added to cart
        ↓
Seller deletes / archives product
        ↓
Customer reloads cart
```

Expected:

```text
Other valid items remain visible

Deleted product:
Unavailable
```

Customer can remove it.

---

# 14. VARIANT DELETED

Critical F02 case:

```text
Product exists

Variant:
Black / XL

added to cart

Seller deletes Black / XL
```

Expected:

```text
Cart remains functional

Item shows:
Selected option no longer available

[Remove]
```

Do NOT select another variant automatically.

That could change:

```text
price
color
size
SKU
stock
```

without customer consent.

---

# 15. PRODUCT INACTIVE / HIDDEN

Test:

```text
Seller hides product
```

Cart should reflect existing marketplace rules.

Normally:

```text
cannot purchase
```

but preserve the cart row long enough to explain the problem.

---

# 16. SHOP SUSPENDED / INACTIVE

Because E-KHMER is multi-vendor:

```text
Shop A active
Shop B suspended
```

Cart:

```text
Shop A valid products
Shop B product
```

Expected:

```text
Shop A items remain usable

Shop B item becomes unavailable
```

Do not break the whole cart.

---

# 17. OUT OF STOCK

Out-of-stock is different from deleted variant.

Example:

```text
Quantity requested = 3
Current stock = 0
```

Show:

```text
Out of stock
```

If:

```text
requested = 5
stock = 2
```

show:

```text
Only 2 available.
```

Do not silently reduce quantity unless existing E-KHMER rules intentionally do so.

---

# 18. MUTATION ERROR FEEDBACK

The audit also noted that mutation failures need visible feedback.

Inspect:

```text
add
update quantity
remove
clear
```

When an API mutation fails:

```text
show actionable message
rollback optimistic state if needed
```

Do not leave UI pretending success.

Examples:

```text
Could not update quantity. Please try again.

This product is no longer available.

Only 2 items remain in stock.
```

---

# 19. REMOVE UNAVAILABLE ITEM

Customer must be able to remove invalid cart items.

This should work even if:

```text
product = null
variant = null
```

The cart item itself still has its cart item ID.

Do not require a valid product/variant relationship just to delete the cart row.

---

# 20. OPTIONAL BULK CLEANUP

If useful and consistent with current UI, add:

```text
Remove unavailable items
```

but do not make this required for F02.

Primary requirement is:

```text
valid cart items survive
```

---

# 21. BACKEND RESOURCE SAFETY

Inspect:

```text
CartItemResource.php
```

Ensure serialization itself does not crash if relationships are missing.

Avoid patterns that blindly access:

```text
$this->variant->id
$this->product->name
```

if those relationships are legitimately nullable after deletion.

Return safe nullable data or snapshot values where available.

---

# 22. DATABASE FOREIGN KEYS / SOFT DELETE

Inspect whether:

```text
product_id
variant_id
```

use:

```text
CASCADE
SET NULL
RESTRICT
soft deletes
```

Understand why stale rows remain.

Do NOT change FK behavior just to hide the UI bug unless that change is correct for historical/cart business logic.

Document the current behavior.

---

# 23. PRODUCT SNAPSHOT IN CART

Do not introduce unnecessary snapshots into cart unless needed.

Orders need historical snapshots.

Cart normally does not.

If cart already stores:

```text
product name
variant text
image
price
```

reuse safely.

Otherwise avoid schema expansion unless required.

---

# 24. API CONTRACT

Ensure cart API behavior is explicit.

Example:

```json
{
  "items": [
    {
      "id": 1,
      "available": true,
      "product": {},
      "variant": {}
    },
    {
      "id": 2,
      "available": false,
      "unavailable_reason": "variant_deleted",
      "product": {},
      "variant": null
    }
  ]
}
```

Do not return HTTP 500 because one relation disappeared.

---

# 25. EMPTY CART LOGIC

Only display:

```text
Your cart is empty
```

when there are actually zero cart items after the chosen cleanup rules.

Do NOT display empty cart because normalization threw an exception.

---

# 26. CART COUNT

Decide whether the header/cart badge counts:

```text
all stored cart items
```

or:

```text
only available cart items
```

Use one consistent rule.

Recommended UX:

```text
badge = all items
```

while clearly marking invalid items.

But preserve existing application convention if different.

Document the decision.

---

# 27. COUPONS AFTER F02

F01 was already completed.

Do not regress coupon isolation.

When an item becomes unavailable:

```text
coupon eligible subtotal
```

must be recalculated using only purchasable eligible lines.

Example:

```text
Shop A coupon
Shop A product becomes unavailable
```

Coupon discount must not remain based on the old unavailable subtotal.

---

# 28. MULTI-SHOP CART

Test:

```text
Shop A
Valid item

Shop B
Deleted variant

Shop C
Valid item
```

Expected:

```text
Shop A visible
Shop B stale item visible as unavailable
Shop C visible
```

No complete cart failure.

---

# 29. GUEST CART

Test this behavior for:

```text
guest cart
authenticated cart
merged guest cart after login
```

Do not fix authenticated cart only.

If stale items appear during guest-cart merge, handle them safely.

---

# 30. LOGIN MERGE

Example:

```text
Guest cart:
Product A

Account cart:
Product B stale variant
```

Login merge must not produce:

```text
empty cart
500
broken store state
```

Valid items must survive.

---

# 31. LOADING / ERROR / EMPTY STATES

Differentiate:

```text
Loading cart

Cart loaded successfully but empty

Cart loaded with unavailable items

Cart API failed
```

These are not the same state.

Example API failure:

```text
We couldn't load your cart.
[Try again]
```

Do NOT show:

```text
Your cart is empty
```

for a network/server error.

---

# 32. AUTOMATED BACKEND TESTS

Add/update tests for:

### Test 1 — Valid cart

```text
valid product
valid variant

→ normal response
```

### Test 2 — Deleted variant

```text
valid product
deleted variant

→ API does not crash
→ item is safely represented
```

### Test 3 — Mixed valid/stale

```text
Item A valid
Item B stale
Item C valid

→ all rows safely returned
```

### Test 4 — Delete stale item

```text
variant no longer exists
→ DELETE cart item still succeeds
```

### Test 5 — Checkout stale item

```text
stale item exists
→ checkout rejects safely
→ no order created
→ no reservation created
```

### Test 6 — Multi-shop stale item

```text
Shop A valid
Shop B stale

→ valid shop data unaffected
```

---

# 33. FRONTEND UNIT / COMPONENT TESTS

If the repository has frontend unit/component testing, add tests for:

```text
variant:null

product:null if possible

valid + invalid mixed cart

remove unavailable

totals exclude unavailable

checkout disabled/blocked

API load failure != empty cart
```

Do not introduce a new testing framework only for F02.

---

# 34. BROWSER / E2E TEST

Add a regression browser test matching the reproduced bug.

Scenario:

```text
Open cart with:

valid item
+
item whose variant is null
```

Verify:

```text
valid product still visible

stale product gets unavailable UI

empty-cart message absent

remove action works

checkout does not include invalid line
```

Run both:

```text
desktop
mobile
```

if current E2E setup supports them.

---

# 35. TYPE CHECK

Run frontend:

```text
type check
production build
```

Make sure nullable relationships are correctly typed.

Do not suppress errors using `any`.

---

# 36. DOCKER ONLY

Use the project's Docker environment for:

```text
PHP
Composer
Node
tests
build
```

Follow existing repository conventions.

Do NOT reseed or wipe the development database unless explicitly required.

Prefer isolated test databases.

---

# 37. REGRESSION TEST F01

Because coupon logic was just changed, verify at least:

```text
shop coupon isolation still passes
mixed-shop discount still passes
coupon usage still passes
```

F02 must not regress F01.

---

# 38. DO NOT START F03

Only work on F02.

Do not broadly redesign checkout total calculation yet.

F03 will be handled separately.

If an F03 issue is discovered while implementing F02:

```text
document it
do not expand scope
```

unless it blocks F02 correctness.

---

# 39. DEFINITION OF DONE

Do not mark F02 complete until:

```text
[x] One deleted variant cannot break entire cart

[x] Valid cart items remain visible

[x] Stale item has clear unavailable state

[x] Stale item can be removed

[x] variant:null is safely typed

[x] Backend serialization does not crash

[x] Empty cart state is accurate

[x] API failure is not shown as empty cart

[x] Unavailable items excluded from purchasable totals

[x] Checkout cannot include invalid item

[x] Multi-shop cart still works

[x] Guest cart still works

[x] Login cart merge still works

[x] F01 coupon isolation still passes

[x] Backend tests pass

[x] Frontend type check passes

[x] Frontend production build passes

[x] Desktop browser regression passes

[x] Mobile browser regression passes
```

---

# 40. FINAL REPORT

At completion report exactly:

```text
F02 — CART RECOVERY AFTER VARIANT DELETION

Status:
PASS / NEEDS WORK

Root Cause:

Old Behavior:

New Behavior:

Backend Changes:

Frontend Changes:

Type Changes:

Files Changed:

Tests Added:

Backend Tests:
Passed / Failed

Browser Tests:
Passed / Failed

Type Check:
PASS / FAIL

Production Build:
PASS / FAIL

F01 Regression:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F03 — Discount Cap & Checkout Total Consistency
```

Also provide a short example:

```text
Before:

Valid Item
Deleted Variant
→ Entire cart appeared empty


After:

Valid Item       Available
Deleted Variant  Unavailable / Remove
→ Cart remains usable
```

Stop after F02.

Do NOT automatically start F03.

I will review the result before continuing.

---

Implemented on 2 November 2026. **F02 status: PASS.** See the [F02 implementation report](./F02-CART-RECOVERY-IMPLEMENTATION.md) for root cause, changed files, availability/count/merge rules, Docker regression results, remaining risks, and the separate F03 follow-up.
