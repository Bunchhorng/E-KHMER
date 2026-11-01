# E-KHMER Improvement #01 — Shop Coupon Ownership & Multi-Shop Discount Safety

Act as a senior **Laravel + Vue.js + MySQL E-Commerce Engineer, Marketplace Business Logic Engineer, Security Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

The current audit identified this P1 defect:

# F01 — Shop Coupon Ownership

Observed behavior:

```text
A coupon owned by Shop A
was able to reduce the total
of an order containing only Shop B products.
```

This is incorrect marketplace behavior.

A shop-specific coupon must apply only to eligible products/order lines belonging to that shop.

---

# 1. IMPORTANT

Do NOT rebuild the coupon system.

Do NOT redesign checkout unnecessarily.

Do NOT create duplicate coupon logic.

First inspect the actual implementation.

Focus especially on:

```text
backend/app/Services/CheckoutService.php
backend/app/Services/CouponService.php

Coupon models
Coupon usage models
Cart logic
Checkout logic
ShopOrder logic
OrderItem logic
Coupon request validation
Existing coupon tests
```

Also inspect Vue coupon handling if frontend calculations depend on coupon information.

---

# 2. CURRENT BUG TO FIX

Example:

```text
Shop A Coupon:
TECH50

Discount:
$50

Eligible Shop:
Shop A
```

Customer cart:

```text
Shop B

Product B1
$100
```

Current broken behavior:

```text
TECH50 applied

Subtotal = $100
Discount = $50
Total = $50
```

Expected:

```text
Coupon invalid for this cart
```

or:

```text
Discount = $0
```

with a clear validation message.

---

# 3. CORRECT BUSINESS RULE

A shop coupon must apply only to:

```text
Order lines
where:

product.shop_id
=
coupon.shop_id
```

Example:

```text
Cart

Shop A
Product A1 = $100

Shop B
Product B1 = $200
```

Coupon:

```text
SHOPA10
10% off
shop_id = Shop A
```

Eligible subtotal:

```text
$100
```

NOT:

```text
$300
```

Discount:

```text
10% × $100
= $10
```

Final cart:

```text
Subtotal = $300
Discount = $10
```

---

# 4. PLATFORM COUPONS

Inspect whether E-KHMER supports platform/global coupons.

If the existing design uses:

```text
coupon.shop_id = null
```

for marketplace-wide coupons, preserve that behavior if it is already intentional.

Concept:

```text
shop_id = null
→ Platform coupon

shop_id = 12
→ Shop 12 coupon
```

Do NOT assume this rule if the code uses a different design.

Inspect first.

---

# 5. ELIGIBLE SUBTOTAL

Shop coupon calculations must use:

```text
eligible_subtotal
```

not:

```text
whole_cart_subtotal
```

Example:

```text
Shop A products = $80
Shop B products = $120

Cart total = $200
```

Shop A coupon minimum spend:

```text
$100
```

The coupon should fail because:

```text
Eligible subtotal = $80
```

Do NOT validate minimum spend using:

```text
$200
```

---

# 6. MINIMUM SPEND

Fix minimum-spend validation.

Wrong:

```text
Cart Total >= Minimum Spend
```

For shop coupon:

```text
Shop Eligible Subtotal >= Minimum Spend
```

Example:

```text
Shop A subtotal = $50
Shop B subtotal = $200

Coupon:
Shop A
Minimum = $100
```

Expected:

```text
Coupon rejected
```

because:

```text
Shop A eligible subtotal = $50
```

---

# 7. FIXED DISCOUNT

Example:

```text
Shop A subtotal = $30

Shop A coupon:
$50 OFF
```

Discount must never exceed eligible subtotal.

Expected:

```text
Discount = $30
```

not:

```text
$50
```

unless the existing business rules intentionally behave differently.

---

# 8. PERCENTAGE DISCOUNT

Example:

```text
Shop A subtotal = $100

Coupon:
20%
```

Expected:

```text
Discount = $20
```

---

# 9. MAXIMUM DISCOUNT CAP

If coupon has:

```text
percentage = 20%

max_discount_amount = $10
```

and:

```text
eligible subtotal = $100
```

then:

```text
20% = $20

but max = $10

final discount = $10
```

Make sure this agrees with checkout totals and frontend display.

Do not fix F03 completely in this task unless required to keep coupon calculation consistent.

Document any dependency on F03.

---

# 10. MIXED-SHOP CART

Test:

```text
Shop A
Product A = $100

Shop B
Product B = $200
```

Shop A coupon:

```text
10%
```

Expected:

```text
Shop A discount = $10

Shop B discount = $0
```

Total discount:

```text
$10
```

---

# 11. SHOP ORDER ALLOCATION

Because E-KHMER uses multi-shop checkout, discount allocation must remain consistent when creating:

```text
Parent Order
        ↓
Shop Order A
Shop Order B
```

Example:

```text
Cart subtotal = $300

Shop A:
$100

Shop B:
$200
```

Coupon:

```text
Shop A 10%
```

Result:

```text
Parent discount = $10

Shop Order A discount = $10

Shop Order B discount = $0
```

Do NOT accidentally spread Shop A's discount across Shop B.

---

# 12. CHECKOUT TOTAL

Verify all totals remain consistent:

```text
subtotal
discount
shipping
tax
grand_total
```

Parent total should equal the correctly aggregated shop totals according to existing E-KHMER rules.

Do not introduce rounding differences.

---

# 13. COUPON USAGE

Ensure coupon usage is recorded only after a successful valid checkout according to the existing design.

Do not consume coupon usage when:

```text
validation fails
checkout rolls back
order creation fails
```

Inspect the current implementation before changing this.

---

# 14. SECURITY

Never trust frontend:

```text
shop_id
discount
eligible subtotal
coupon amount
```

Laravel must calculate coupon eligibility and discount.

The frontend may display results but must not become the financial source of truth.

---

# 15. ERROR RESPONSE

If customer applies an invalid shop coupon, return a clear validation/business message.

Example:

```text
This coupon does not apply to products in your cart.
```

or:

```text
This coupon is only valid for products from Tech Store.
```

Avoid exposing internal database information.

---

# 16. DO NOT BREAK

Regression test:

```text
Valid global coupons

Valid shop coupon

Fixed discounts

Percentage discounts

Minimum spend

Maximum discount

Usage limits

Per-user limits

Expiry

Start date

Coupon usage records

Checkout retry/idempotency

Multi-shop checkout
```

---

# 17. AUTOMATED TESTS

Add/update backend tests for at least:

### Test 1

```text
Shop A coupon
+
Shop A product

→ valid
```

### Test 2

```text
Shop A coupon
+
Shop B-only cart

→ rejected
```

### Test 3

```text
Shop A coupon
+
Shop A + Shop B cart

→ discount only Shop A
```

### Test 4

```text
Shop A eligible subtotal below minimum
but whole cart above minimum

→ rejected
```

### Test 5

```text
Eligible subtotal above minimum

→ accepted
```

### Test 6

```text
Fixed discount greater than eligible subtotal

→ discount never exceeds eligible subtotal
```

### Test 7

```text
Percentage coupon with maximum discount

→ maximum respected
```

### Test 8

```text
Global coupon

→ existing global behavior remains correct
```

### Test 9

```text
Checkout creates:

Parent Order
Shop Order A
Shop Order B

Discount allocation is correct
```

### Test 10

```text
Invalid coupon checkout

→ no coupon usage consumed
→ no partial order created
```

---

# 18. FRONTEND

Inspect:

```text
frontend/src/stores/cart.ts
frontend/src/api/coupons.ts
frontend/src/views/CheckoutView.vue
```

if coupon results are displayed there.

The frontend should display backend-calculated discount.

Do not duplicate financial business logic in Vue unnecessarily.

If frontend currently independently calculates coupon discounts, reduce duplication or make it consistent with backend source-of-truth behavior.

---

# 19. TEST USING DOCKER

Use the existing Docker environment.

Do not run PHP/Composer directly from the host if the project standard requires containers.

Run the relevant existing test commands inside the Laravel container.

Start with focused coupon/checkout tests.

Then run broader checkout regression tests.

Do NOT run destructive seed/reset commands against important development data.

---

# 20. CHECK DATABASE

Inspect:

```text
coupons
coupon_usages
orders
shop_orders
order_items
```

and actual relationships.

Do not add a migration unless required.

Prefer fixing existing business logic if schema already supports shop ownership.

---

# 21. CODE QUALITY

Avoid creating:

```text
CouponServiceV2
NewCouponService
CouponHelper2
```

if an existing service can be corrected.

Keep coupon calculation centralized.

Recommended ownership:

```text
CouponService
→ eligibility
→ eligible subtotal
→ discount calculation

CheckoutService
→ applies validated result
→ creates financial records
```

Adapt to existing architecture.

---

# 22. ACCEPTANCE EXAMPLE

Given:

```text
Shop A

Product A
$100


Shop B

Product B
$200
```

Coupon:

```text
Code:
SHOPA20

Shop:
A

Type:
percentage

Value:
20%

Minimum:
$50

Max Discount:
$15
```

Expected:

```text
Eligible subtotal:
$100

Raw discount:
$20

Maximum:
$15

Final discount:
$15
```

Shop totals:

```text
Shop A
Subtotal: $100
Discount: $15

Shop B
Subtotal: $200
Discount: $0
```

Parent:

```text
Subtotal:
$300

Discount:
$15
```

excluding other existing tax/shipping calculations.

---

# 23. DEFINITION OF DONE

Do not mark F01 complete until:

```text
[x] Shop coupon applies only to its shop

[x] Wrong-shop-only cart rejects coupon

[x] Mixed-shop cart discounts only eligible lines

[x] Minimum spend checks eligible shop subtotal

[x] Maximum discount works

[x] Fixed discount cannot exceed eligible subtotal

[x] Parent order discount is correct

[x] Shop order discount allocation is correct

[x] Frontend shows backend-calculated result

[x] Coupon usage is not consumed on failed checkout

[x] Existing global coupon behavior still works

[x] Automated tests pass

[x] Checkout regression tests pass
```

---

# 24. FINAL REPORT

After implementation, report:

```text
F01 SHOP COUPON OWNERSHIP

Status:
PASS / NEEDS WORK

Root Cause:

Files Changed:

Business Logic Changed:

Tests Added:

Tests Passed:

Tests Failed:

Regression Result:

Remaining Risks:

Related Follow-Up:
F03 discount-cap/frontend total consistency
```

Do NOT automatically start F02.

Stop after F01 is fully implemented and tested.

I will review the result before moving to the next issue.

---

Implementation completed on 1 November 2026. **F01 status: PASS.** See [the implementation report](./F01-SHOP-COUPON-IMPLEMENTATION.md) for the root cause, changed files, acceptance totals, Docker test results, remaining risks, and F03 follow-up. F02 was not started.
