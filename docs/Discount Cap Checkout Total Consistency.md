# E-KHMER Improvement #03 — Discount Cap & Checkout Total Consistency

Act as a senior **Laravel + Vue.js + TypeScript Marketplace Engineer, Financial Business Logic Engineer, Checkout Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace called **E-KHMER**.

Previous issues already handled:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
```

Do NOT regress those fixes.

Now work ONLY on:

# F03 — Discount Cap & Displayed Checkout Total Consistency

---

# 1. CURRENT DEFECT

The audit reproduced a mismatch between frontend and backend totals.

Example:

```text
Subtotal:
$100

Coupon:
10%

Maximum discount:
$5

Tax:
10%
```

Incorrect frontend behavior produced something equivalent to:

```text
Discount:
$10

Displayed Total:
$99
```

while the backend correctly applied the discount cap:

```text
Raw discount:
$10

Max discount:
$5

Discount:
$5

Taxable amount:
$95

Tax:
$9.50

Server total:
$104.50
```

This creates a dangerous checkout inconsistency.

The customer must NEVER see a different payable amount from the amount used to create the order/payment.

---

# 2. PRIMARY GOAL

Establish one consistent calculation flow:

```text
Backend
  ↓
Validate Coupon
  ↓
Calculate Eligible Discount
  ↓
Apply Discount Cap
  ↓
Calculate Tax
  ↓
Calculate Shipping
  ↓
Calculate Grand Total
  ↓
Return Quote
  ↓
Vue Displays Quote
```

The frontend must not independently invent a different financial total.

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing code.

Focus on:

```text
backend/app/Services/CouponService.php
backend/app/Services/CheckoutService.php
backend/app/Http/Controllers/Api/CouponController.php
backend/app/Http/Controllers/Api/CheckoutController.php

frontend/src/stores/cart.ts
frontend/src/api/coupons.ts
frontend/src/views/CartView.vue
frontend/src/views/CheckoutView.vue
frontend/src/components/CartDrawer.vue

Coupon model
Order model
ShopOrder model
OrderItem model

existing coupon tests
checkout tests
browser tests
```

Also inspect the F01 implementation because shop-scoped eligible subtotal and discount allocation must remain correct.

---

# 4. IDENTIFY THE ROOT CAUSE

Determine exactly why displayed totals differ.

Possible causes may include:

```text
frontend receives coupon percentage
but not max_discount_amount

frontend recalculates discount independently

coupon response omits validated_discount

cart store stores raw coupon data only

checkout uses backend-calculated totals
but cart summary uses local calculations

tax calculation order differs

shipping calculation order differs

rounding differs between PHP and JavaScript

mixed-shop discount allocation is aggregated incorrectly
```

Do not guess.

Document the confirmed root cause.

---

# 5. BACKEND IS THE FINANCIAL SOURCE OF TRUTH

This rule is mandatory:

```text
Frontend
≠ source of truth for financial totals

Backend
= source of truth
```

Never trust frontend values for:

```text
subtotal
eligible subtotal
discount
coupon value
tax
shipping
grand total
payment amount
```

Vue may render the returned values.

Laravel must validate and calculate them.

---

# 6. CENTRALIZE CALCULATION RULES

Avoid having the same formula independently implemented in:

```text
Cart Store
Checkout View
Coupon API
Checkout Service
Order Creation
Payment Creation
```

Preferred architecture:

```text
CouponService
→ coupon eligibility
→ eligible subtotal
→ raw discount
→ discount cap
→ final discount

CheckoutService
→ subtotal
→ coupon result
→ shipping
→ tax
→ grand total
→ order/shop-order allocation
```

If a dedicated quote/calculator service already exists, reuse it.

Do NOT create duplicate financial services unnecessarily.

---

# 7. DISCOUNT FORMULA

For percentage coupon:

```text
raw_discount =
eligible_subtotal × percentage / 100
```

If:

```text
max_discount_amount != null
```

then:

```text
final_discount =
min(raw_discount, max_discount_amount)
```

Also:

```text
final_discount <= eligible_subtotal
```

---

# 8. FIXED DISCOUNT

For fixed discount:

```text
final_discount =
min(coupon_value, eligible_subtotal)
```

If a maximum discount field also applies according to current business rules:

```text
final_discount =
min(coupon_value, max_discount_amount, eligible_subtotal)
```

Do not invent new rules.

Follow actual coupon schema/business logic.

---

# 9. SHOP COUPON RULE FROM F01

Do not break F01.

Example:

```text
Shop A subtotal = $100
Shop B subtotal = $200
```

Shop A coupon:

```text
20%
max_discount = $15
```

Calculation:

```text
eligible subtotal = $100

raw discount = $20

final discount = $15
```

Expected:

```text
Shop A discount = $15
Shop B discount = $0
Parent discount = $15
```

---

# 10. GLOBAL COUPON

If platform/global coupons exist:

```text
shop_id = null
```

preserve existing intended behavior.

Example:

```text
Shop A = $100
Shop B = $200

Global coupon:
10%
max = $20
```

Expected:

```text
eligible subtotal = $300

raw discount = $30

final discount = $20
```

Allocation across shop orders must remain mathematically correct.

---

# 11. DISCOUNT ALLOCATION

If the parent order has:

```text
discount = $20
```

and multiple shop orders are eligible, allocation must satisfy:

```text
sum(shop_order_discounts)
=
parent_order_discount
```

No cents may be lost.

No extra cents may be created.

Use deterministic rounding.

---

# 12. ROUNDING

Inspect current money representation.

Determine whether the system uses:

```text
DECIMAL
integer cents
Money objects
formatted decimal strings
```

Do not use unsafe floating-point logic if the repository already has a safer pattern.

Example problem:

```text
19.99 × 3
```

should not become:

```text
59.969999999
```

in persisted financial data.

---

# 13. CALCULATION ORDER

Document the actual business calculation order.

For example:

```text
Subtotal
    ↓
Discount
    ↓
Discounted Subtotal
    ↓
Tax
    ↓
Shipping
    ↓
Grand Total
```

or:

```text
Subtotal
    ↓
Discount
    ↓
Shipping
    ↓
Tax
    ↓
Grand Total
```

Do NOT assume.

Inspect current checkout logic.

Then make frontend display match exactly.

---

# 14. TAX

The existing review notes that tax rules still require final operating-policy approval.

For this task:

```text
do NOT redesign tax policy
```

Use the existing backend tax behavior consistently.

The goal is:

```text
Frontend tax
=
Backend tax
```

not to decide the future tax policy.

---

# 15. SHIPPING

Do not let Vue independently calculate shipping if backend already returns valid per-shop delivery fees.

For multi-shop checkout:

```text
Shop A shipping
+
Shop B shipping
=
Parent shipping
```

Display the same values returned by backend.

---

# 16. QUOTE / PREVIEW RESPONSE

Prefer a backend response that gives the frontend everything required to display financial totals.

Example concept:

```json
{
  "subtotal": "100.00",
  "discount": "5.00",
  "tax": "9.50",
  "shipping": "0.00",
  "grand_total": "104.50",
  "coupon": {
    "code": "SAVE10",
    "raw_discount": "10.00",
    "discount": "5.00",
    "max_discount_amount": "5.00"
  }
}
```

Adapt to the actual API.

Do not expose unnecessary internal fields.

---

# 17. FRONTEND STORE

Inspect:

```text
frontend/src/stores/cart.ts
```

If it currently calculates:

```text
coupon.value
percentage
subtotal
```

independently, replace or constrain this behavior.

Preferred:

```text
Backend validates coupon
      ↓
Backend returns calculated discount
      ↓
Store saves calculated discount
      ↓
UI displays it
```

---

# 18. DO NOT TRUST STALE COUPON CALCULATIONS

Coupon discount must recalculate when:

```text
quantity changes
item removed
item added
product becomes unavailable
variant changes
shop changes
shipping method changes if relevant
coupon changes
```

Example:

```text
Original eligible subtotal:
$200

Coupon:
10%
Max:
$15

Discount:
$15
```

Customer removes product.

New eligible subtotal:

```text
$50
```

Expected:

```text
raw discount = $5
final discount = $5
```

Do not keep stale `$15`.

---

# 19. F02 INTERACTION

F02 introduced safe handling of unavailable cart items.

Unavailable items must not contribute to:

```text
eligible coupon subtotal
discount calculation
taxable subtotal
checkout total
```

Verify this explicitly.

---

# 20. CART SUMMARY

Cart page should show:

```text
Subtotal        $100.00
Discount        -$5.00
Tax              $9.50
Shipping         $0.00
------------------------
Total           $104.50
```

This must match checkout.

---

# 21. CHECKOUT REVIEW

Checkout review must show the same values.

Expected:

```text
Cart:
$104.50

Checkout review:
$104.50

Order:
$104.50

Payment:
$104.50

Receipt:
$104.50
```

No screen may calculate a different amount.

---

# 22. PAYMENT AMOUNT

Payment amount must come from persisted/verified order totals.

Never:

```text
Vue total
→ payment provider
```

Prefer:

```text
Backend order
→ payment amount
```

This is especially critical for Bakong.

---

# 23. ORDER CREATION

Verify:

```text
orders.subtotal
orders.discount
orders.tax
orders.shipping
orders.total
```

match the quote used immediately before order creation.

For multi-shop:

```text
shop_orders
```

must reconcile with parent order.

---

# 24. RECEIPTS

Verify receipt/PDF totals use persisted order values.

Do not recalculate old order discounts from the current coupon.

Historical receipt:

```text
must remain stable
```

even if coupon is later edited/deleted.

---

# 25. COUPON EXPIRY BETWEEN QUOTE AND CHECKOUT

Test:

```text
Customer validates coupon
        ↓
Coupon expires
        ↓
Customer places order
```

Backend must revalidate at order creation.

Expected:

```text
checkout rejected/requoted safely
```

Do not trust earlier frontend coupon state.

---

# 26. COUPON USAGE LIMIT BETWEEN QUOTE AND CHECKOUT

Same concept:

```text
coupon valid during cart view
usage limit consumed by another user
customer places order
```

Backend revalidates.

No invalid discount should be persisted.

---

# 27. PRICE CHANGE

Test:

```text
Product = $100
Coupon calculated
Seller updates price to $120
Customer checks out
```

Backend must use current valid pricing according to existing checkout rules.

Frontend should receive refreshed totals.

---

# 28. QUANTITY CHANGE

Example:

```text
Quantity 1:
subtotal = $100
discount = $5 cap

Quantity 2:
subtotal = $200
discount still = $5 cap
```

Verify correct update.

---

# 29. MIXED-SHOP EXAMPLE

Cart:

```text
Shop A:
Product = $100

Shop B:
Product = $50
```

Shop A coupon:

```text
20%
max = $10
```

Shipping:

```text
Shop A = $3
Shop B = $2
```

Example backend result:

```text
Subtotal:
$150

Eligible Shop A subtotal:
$100

Raw discount:
$20

Discount cap:
$10

Discount:
$10

Shipping:
$5
```

Then apply current tax rules.

Frontend must display exactly the backend result.

---

# 30. INVALID FRONTEND PAYLOAD

Test manual manipulation.

Example:

```json
{
  "subtotal": 100,
  "discount": 90,
  "total": 10
}
```

Backend must ignore/reject manipulated financial values.

Order should be calculated from server data.

---

# 31. API CONTRACT

Standardize money response types.

Avoid some APIs returning:

```text
5
```

others:

```text
"5.00"
```

others:

```text
"$5.00"
```

without a deliberate contract.

Prefer machine-safe numeric/decimal fields and format currency in UI.

Follow existing repository conventions.

---

# 32. LOADING STATE

When totals are recalculating:

```text
quantity change
coupon apply
coupon removal
shipping change
```

show:

```text
Updating totals...
```

or a subtle loading state.

Do not display old total as final while recalculation is pending.

---

# 33. DISABLE PLACE ORDER DURING RECALCULATION

Prevent:

```text
User changes shipping
↓
old total visible
↓
immediately clicks Place Order
```

Frontend should block submission while quote/totals are stale or updating.

Backend must still verify everything.

---

# 34. COUPON ERROR UX

Example messages:

```text
This coupon is no longer valid.

This coupon requires a minimum eligible spend of $50.

This coupon applies only to products from Tech Store.

This coupon has reached its usage limit.
```

Avoid generic:

```text
Something went wrong
```

for known business validation.

---

# 35. AUTOMATED BACKEND TESTS

Add/update tests for:

## Test 1 — Percentage under cap

```text
Subtotal = $100
Coupon = 5%
Cap = $20

Expected discount = $5
```

---

## Test 2 — Percentage over cap

```text
Subtotal = $100
Coupon = 10%
Cap = $5

Expected discount = $5
```

---

## Test 3 — Fixed discount

```text
Subtotal = $100
Fixed coupon = $10

Expected = $10
```

---

## Test 4 — Fixed > eligible subtotal

```text
Eligible subtotal = $5
Fixed discount = $10

Expected discount <= $5
```

---

## Test 5 — Shop coupon cap

```text
Shop A = $100
Shop B = $100

Shop A coupon:
20%
Cap = $10

Expected:
Parent discount = $10
Shop A = $10
Shop B = $0
```

---

## Test 6 — Global coupon cap

Validate existing global behavior.

---

## Test 7 — Quantity change

Discount recalculates correctly.

---

## Test 8 — Item removal

Discount recalculates correctly.

---

## Test 9 — Unavailable item from F02

Unavailable item excluded from discount.

---

## Test 10 — Coupon expires before checkout

Order not created with invalid discount.

---

## Test 11 — Coupon usage limit reached before checkout

Safe failure.

---

## Test 12 — Financial payload manipulation

Frontend-provided fake total cannot alter order.

---

# 36. ORDER RECONCILIATION TEST

After checkout:

```text
Parent subtotal
=
sum eligible item subtotals according to business rules

Parent discount
=
sum shop-order discounts

Parent shipping
=
sum shop-order shipping

Parent total
=
expected formula
```

Add assertions.

---

# 37. PAYMENT RECONCILIATION TEST

Verify:

```text
payment.amount
=
order payable amount
```

according to current payment model.

For Bakong:

```text
QR/payment verification amount
=
persisted payable amount
```

---

# 38. RECEIPT TEST

Verify receipt uses:

```text
persisted discount
persisted tax
persisted shipping
persisted total
```

not recalculated coupon state.

---

# 39. FRONTEND E2E REGRESSION

Create browser test reproducing F03.

Mock or fixture:

```text
subtotal = $100
coupon = 10%
max discount = $5
tax = 10%
shipping = $0
```

Backend quote:

```text
discount = $5
tax = $9.50
total = $104.50
```

Verify browser displays:

```text
Discount -$5.00
Tax $9.50
Total $104.50
```

Then proceed to checkout and verify same value.

Run:

```text
desktop
mobile
```

if supported.

---

# 40. F01 REGRESSION

Confirm:

```text
wrong-shop coupon rejected

mixed-shop coupon allocation correct
```

---

# 41. F02 REGRESSION

Confirm:

```text
unavailable variant does not break cart

unavailable line does not contribute to totals
```

---

# 42. TYPE CHECK AND BUILD

Run:

```text
frontend type check
frontend production build
```

No unsafe `any` workaround.

---

# 43. DOCKER ONLY

Use Docker for:

```text
PHP
Composer
Node
tests
build
```

according to repository conventions.

Do not wipe development data.

Use isolated tests.

---

# 44. DO NOT START F04

Do not begin COD collection work in this task.

If COD affects total calculation, preserve current behavior only.

F04 will be handled separately.

---

# 45. DEFINITION OF DONE

Do not mark F03 complete until:

```text
[x] Percentage discount respects maximum cap

[x] Fixed discount respects eligible subtotal

[x] F01 shop eligibility remains correct

[x] F02 unavailable items excluded

[x] Cart total matches backend

[x] Checkout total matches backend

[x] Order persisted total matches quote

[x] Shop-order discount allocation reconciles

[x] Payment amount matches order

[x] Receipt total matches persisted order

[x] Quantity changes recalculate

[x] Item removal recalculates

[x] Coupon removal recalculates

[x] Shipping changes recalculate

[x] Stale coupon is revalidated at checkout

[x] Fake frontend financial values cannot alter order

[x] Backend tests pass

[x] Browser regression passes

[x] Type check passes

[x] Production build passes

[x] F01 regression passes

[x] F02 regression passes
```

---

# 46. FINAL REPORT

At completion report exactly:

```text
F03 — DISCOUNT CAP & CHECKOUT TOTAL CONSISTENCY

Status:
PASS / NEEDS WORK

Root Cause:

Old Behavior:

New Calculation Flow:

Coupon Calculation:

Tax Calculation:

Shipping Calculation:

Rounding Strategy:

Backend Source of Truth:
YES / NO

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

F02 Regression:
PASS / FAIL

Order / Payment Reconciliation:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F04 — COD Collection & Paid-State Lifecycle
```

Also show one reconciliation example:

```text
Subtotal:      $100.00
Raw discount:   $10.00
Discount cap:    $5.00
Final discount:  $5.00
Tax:             $9.50
Shipping:        $0.00
-----------------------
Total:          $104.50
```

Stop after F03.

Do NOT automatically start F04.

I will review the result first.

---

Implemented on 2 November 2026. **F03 status: PASS.** See the [F03 implementation report](./F03-CHECKOUT-TOTALS-IMPLEMENTATION.md) for the calculation flow, changed files, quote API, reconciliation example, Docker test results, compatibility limits, and separate F04 follow-up.
