# E-KHMER Improvement #04 — COD Collection & Paid-State Lifecycle

Implementation and validation: [F04 — COD Collection & Paid-State Lifecycle report](F04-COD-COLLECTION-IMPLEMENTATION.md).

Act as a senior **Laravel + Vue.js + MySQL Marketplace Engineer, Payment Engineer, Order Lifecycle Engineer, Financial Integrity Engineer, Security Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

Previous fixes:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
F03 — Discount Cap & Checkout Total Consistency
```

Do NOT regress these fixes.

Now work ONLY on:

# F04 — Cash on Delivery Collection & Paid-State Lifecycle

---

# 1. CURRENT DEFECT

The audit reproduced this lifecycle:

```text
Customer places COD order
        ↓
Order confirmed
        ↓
Seller processes order
        ↓
Seller ships order
        ↓
Order delivered
        ↓
Payment still:
pending / unpaid
```

This is incorrect for a COD order where cash has actually been collected.

Because payment remains unpaid:

```text
Revenue reports may exclude the sale

Financial analytics may exclude the sale

Customer return eligibility may fail

Payment history is inaccurate

Seller payment work queue remains incorrect
```

The current audit specifically found:

```text
Delivered COD order
        ↓
Payment remains pending/unpaid
        ↓
Return request rejected
```

---

# 2. PRIMARY GOAL

Implement a secure, explicit, auditable COD collection workflow.

Correct lifecycle:

```text
COD Order
    ↓
Pending Collection
    ↓
Delivered / Collection Performed
    ↓
Authorized User Records Cash Collection
    ↓
Payment Transaction Recorded
    ↓
Shop Payment = Paid
    ↓
Parent Payment Aggregated
    ↓
Order financial state updated
    ↓
Revenue / Analytics include sale
    ↓
Return eligibility works
```

Do NOT simply set:

```text
payment_status = paid
```

without recording a financial transaction.

---

# 3. FIRST INSPECT THE REAL IMPLEMENTATION

Before modifying code, inspect:

```text
backend/app/Services/CheckoutService.php
backend/app/Services/ReturnService.php
backend/app/Services/RefundService.php

backend/app/Http/Controllers/Api/Admin/AdminPaymentController.php
backend/app/Http/Controllers/Api/Seller/SellerPaymentController.php

Payment model
PaymentTransaction model
Order model
ShopOrder model
Shop model

payment migrations
payment transaction migrations

order status logic
shop-order fulfillment logic

frontend seller payment screens
frontend admin payment screens
frontend customer order details

financial analytics service
admin reporting service

existing payment/COD tests
```

Also inspect:

```text
backend/app/Services/ShopOrderFulfillmentService.php
backend/app/Services/Analytics/FinancialMetricService.php
backend/app/Http/Controllers/Api/Admin/AdminReportController.php
```

Trace the full flow:

```text
Checkout
→ COD Payment Creation
→ Shop Order
→ Fulfillment
→ Delivery
→ Collection
→ Payment Transaction
→ Payment Status
→ Parent Payment Status
→ Analytics
→ Return Eligibility
```

Do NOT assume the existing payment architecture.

---

# 4. CONFIRM CURRENT COD DATA MODEL

Determine how COD is currently represented.

Inspect whether:

```text
payments.method = cod
```

or equivalent.

Determine existing statuses such as:

```text
pending
unpaid
paid
failed
cancelled
refunded
partially_refunded
```

Use existing enums/constants/status classes if present.

Do not introduce duplicate status vocabulary.

---

# 5. DO NOT AUTO-MARK COD PAID AT CHECKOUT

COD is not paid when the order is placed.

Wrong:

```text
Checkout
→ COD
→ Paid
```

Correct:

```text
Checkout
→ COD
→ Pending Collection
```

Payment becomes paid only after cash collection is confirmed by an authorized workflow.

---

# 6. COLLECTION ACTION

Implement a dedicated action such as conceptually:

```text
Record COD Collection
```

Possible API pattern:

```text
POST /api/seller/shops/{shop}/payments/{payment}/collect-cod
```

or:

```text
POST /api/admin/payments/{payment}/collect-cod
```

Adapt to the actual existing routes and architecture.

Do NOT add duplicate endpoints if a suitable payment-state action already exists.

---

# 7. AUTHORIZATION

Only authorized actors may record collection.

Recommended business rule:

```text
Shop Owner
→ own shop COD payments

Shop Manager
→ own shop COD payments
  only if current permissions allow payment operations

Super Admin
→ marketplace COD correction/collection
  if intentionally permitted

Customer
→ NEVER

Other Shop
→ NEVER
```

Laravel must enforce authorization.

Do NOT rely on Vue hiding the button.

---

# 8. SHOP ISOLATION

Critical marketplace rule:

```text
Shop A seller
    ✗
cannot collect payment for Shop B order
```

Test manual ID manipulation.

Example:

```text
POST /seller/shops/A/payments/SHOP_B_PAYMENT/collect-cod
```

Expected:

```text
403 Forbidden
```

or safe not-found behavior according to existing project conventions.

---

# 9. COLLECTION ELIGIBILITY

Do not allow COD collection for every payment.

Validate:

```text
payment method = COD?

payment belongs to authorized shop?

payment already paid?

order cancelled?

shop order cancelled?

payment refunded?

collection already recorded?

order sufficiently progressed?
```

Define the current business rule for when collection can occur.

Recommended:

```text
Delivered
or
approved handover/collection stage
```

But inspect the actual fulfillment architecture first.

---

# 10. DO NOT ALLOW ONLINE PAYMENT MANUAL PAID FLAG

This is critical.

Do NOT introduce:

```text
Mark Any Payment Paid
```

For:

```text
Bakong
KHQR
Online Provider
```

payment success must remain tied to verified provider evidence.

The COD action must explicitly require:

```text
method == COD
```

---

# 11. PAYMENT TRANSACTION RECORD

Recording COD collection must create an auditable transaction.

Concept:

```text
payment_transactions
--------------------------------
id
payment_id
type
amount
status
reference
provider
metadata
created_by
created_at
```

Adapt to the actual schema.

Example:

```text
Type:
collection

Provider:
cash_on_delivery

Amount:
$44.00

Status:
success

Actor:
Seller User ID 20
```

Do NOT store fake external gateway references.

Use a clear internal reference if the architecture requires one.

---

# 12. PAYMENT AMOUNT

Collection amount must come from backend financial records.

Never trust frontend:

```json
{
  "amount": 1
}
```

when actual COD due is:

```text
$44
```

Preferred:

```text
Payment record
      ↓
Outstanding COD amount
      ↓
Collection transaction
```

The frontend should not dictate the payable amount.

---

# 13. EXACT PAYMENT VS PARTIAL PAYMENT

Determine current product scope.

If E-KHMER COD Version 1 expects full collection:

```text
COD Collection
→ full outstanding amount
```

then do NOT add partial cash collection unnecessarily.

If partial COD payments already exist in the architecture, preserve them safely.

For this task, prefer the simplest valid existing business rule.

---

# 14. RETRY SAFETY / IDEMPOTENCY

This operation must be retry-safe.

Example:

```text
Seller clicks "Confirm collected"
twice
```

Expected:

```text
one financial collection
```

NOT:

```text
Payment transaction 1 = $44
Payment transaction 2 = $44

Total collected = $88
```

Use:

```text
database transaction
unique business constraint
state check
locking
idempotency key
```

as appropriate to the existing architecture.

---

# 15. CONCURRENT COLLECTION

Test:

```text
Seller request A
Seller request B

same COD payment
same time
```

Only one may create successful collection.

This must not depend solely on:

```text
if (!$payment->isPaid())
```

without transaction/locking protection.

---

# 16. DATABASE TRANSACTION

Collection should be atomic.

Concept:

```text
BEGIN

lock/reload payment

validate COD eligibility

create payment transaction

update shop payment state

update aggregate parent payment if required

record audit/event

COMMIT
```

If something fails:

```text
ROLLBACK
```

Do not allow:

```text
transaction recorded
but payment still pending
```

or:

```text
payment marked paid
but transaction missing
```

---

# 17. MULTI-SHOP ORDER

This is especially important for E-KHMER.

Example:

```text
Parent Order #100

Shop A
COD = $40

Shop B
COD = $60
```

Shop A delivers and collects first.

Expected:

```text
Shop A payment:
PAID

Shop B payment:
PENDING
```

Parent payment:

```text
PARTIALLY PAID
```

or the equivalent status already supported.

Later:

```text
Shop B collects $60
```

Then:

```text
Parent payment:
PAID
```

Do not mark the entire parent order paid after only one shop collects.

---

# 18. MIXED PAYMENT METHODS

If E-KHMER can contain:

```text
Shop A = Bakong
Shop B = COD
```

then aggregate correctly.

Example:

```text
Shop A Bakong:
verified paid

Shop B COD:
pending collection
```

Parent:

```text
partially paid
```

After COD collection:

```text
paid
```

Only implement this if the current checkout architecture supports mixed per-shop methods.

Otherwise document the boundary.

---

# 19. SHOP ORDER STATUS VS PAYMENT STATUS

Keep separate:

```text
Order Status
Payment Status
Shipment Status
```

Example:

```text
Order:
Delivered

Payment:
Pending COD Collection
```

This temporary state can be valid until collection is recorded.

Do not make:

```text
Delivered = Paid
```

an automatic generic rule.

Payment requires explicit collection evidence.

---

# 20. OPTIONAL DELIVERY + COLLECTION WORKFLOW

If operationally appropriate, seller fulfillment UI may offer:

```text
Mark Delivered
```

then:

```text
Record Cash Collected
```

or a combined confirmed action:

```text
Delivered & Cash Collected
```

only if the backend still performs two explicit domain operations safely.

Do not tightly couple generic delivery logic to COD payment unless it matches business requirements.

---

# 21. SELLER UI

Update seller payment/workflow UI.

For pending COD:

```text
Cash on Delivery

Amount due:
$44.00

Order:
SV-2026-000194-1

Status:
Awaiting collection

[ Record cash collected ]
```

After success:

```text
Collected
$44.00

Collected by:
Seller / Manager

Collected at:
...
```

Do not expose sensitive technical metadata.

---

# 22. CONFIRMATION MODAL

Because this changes financial state, require confirmation.

Example:

```text
Record COD payment?

Order:
SV-2026-000194-1

Amount:
$44.00

This confirms that cash was actually received.

[Cancel] [Confirm collection]
```

Prevent accidental double-click.

---

# 23. SUPER ADMIN UI

Admin payment view should show:

```text
Payment Method
COD

Collection Status
Collected / Awaiting collection

Amount

Shop

Order

Collected At

Collected By
```

If Super Admin may record collection, make the action clearly audited.

Do not create an unrestricted:

```text
Mark Paid
```

button.

Use:

```text
Record COD Collection
```

---

# 24. CUSTOMER UI

Customer order page may show:

Before collection:

```text
Payment:
Cash on Delivery

Status:
Pay on delivery
```

After collection:

```text
Payment:
Cash on Delivery

Status:
Paid
```

Avoid confusing the customer with internal states such as:

```text
pending_payment_transaction
```

---

# 25. RETURN ELIGIBILITY

This is a key F04 acceptance test.

Current broken flow:

```text
Delivered COD
+
payment unpaid
        ↓
Return rejected
```

After collection:

```text
Delivered COD
+
payment paid
        ↓
Eligible return can proceed
```

Do NOT weaken return rules to allow unpaid orders.

Fix payment lifecycle upstream.

---

# 26. REFUND BEHAVIOR

For a paid COD order that is later returned:

```text
COD collected
        ↓
Payment paid
        ↓
Return approved
        ↓
Refund workflow
```

Inspect existing refund rules.

Do NOT automatically treat cash refunds the same as Bakong refunds if current architecture differs.

Record refund state accurately.

---

# 27. REVENUE REPORTS

Once COD collection is confirmed:

```text
settled revenue
```

should include the transaction according to existing report definitions.

Before collection:

```text
pending COD
```

should not appear as settled cash revenue if current financial rules only count paid sales.

Verify F15-related report behavior without broadly implementing F15 yet.

---

# 28. BUSINESS ANALYTICS

Your Business Profit Prediction system depends on valid paid sales.

After COD collection:

```text
FinancialMetricService
```

should include the sale where its definition requires paid/settled revenue.

Before collection:

```text
exclude from settled revenue
```

if that is the existing intended definition.

Verify this explicitly.

---

# 29. PAYMENT HISTORY

Customer/admin/seller payment history should reflect:

```text
Pending COD
→ Collected
```

with correct timestamp.

Do not silently rewrite creation timestamp.

Use a separate collection timestamp/transaction timestamp.

---

# 30. AUDITABILITY

Record enough information to answer:

```text
Who recorded the cash?

For which order?

For which shop?

How much?

When?

Was it already collected?

Was it later refunded?
```

Do not place sensitive personal information unnecessarily in metadata.

---

# 31. NOTIFICATIONS

Inspect existing notification architecture.

If appropriate, on successful COD collection:

Customer:

```text
Payment received for order ...
```

Seller:

```text
optional confirmation
```

Admin:

```text
normally no notification required
```

Do not introduce notification spam.

This is optional unless existing payment flows already notify on payment success.

---

# 32. TELEGRAM ERROR MONITORING

If the Telegram error-alert feature already exists:

Unexpected COD collection failure can be logged/reported as a financial error.

Example category:

```text
PAYMENT
```

Do NOT send:

```text
customer phone
customer address
full payment payload
secrets
```

to Telegram.

This is secondary to correct application behavior.

---

# 33. ERROR HANDLING

Known business error:

```text
This COD payment has already been collected.
```

Unauthorized:

```text
You do not have permission to manage this payment.
```

Invalid method:

```text
Only cash-on-delivery payments can use this action.
```

Cancelled:

```text
Payment cannot be collected for a cancelled order.
```

Do not show:

```text
SQLSTATE
UniqueConstraintViolation
stack trace
```

to users.

---

# 34. PAYMENT STATE MACHINE

Inspect existing statuses and formalize valid transitions.

Concept:

```text
COD

PENDING
   ↓
PAID
   ↓
PARTIALLY_REFUNDED
   ↓
REFUNDED
```

Possible cancellation:

```text
PENDING
   ↓
CANCELLED
```

Forbidden:

```text
REFUNDED → PAID

CANCELLED → PAID

PAID → PAID
```

Adapt to actual status vocabulary.

---

# 35. CANCEL BEFORE COLLECTION

Scenario:

```text
COD order
payment pending
order cancelled
```

Expected:

```text
payment cancelled
```

or existing equivalent.

Collection must no longer be possible.

---

# 36. CANCEL AFTER COLLECTION

If cash is already collected:

```text
payment = paid
```

do not simply cancel payment and discard money history.

Follow refund/return/cancellation business logic.

Financial history must remain auditable.

---

# 37. FAILED REQUEST RETRY

Scenario:

```text
Client submits collection
Backend commits
Network response is lost
Client retries
```

Expected:

```text
same final paid state
one collection transaction
```

Return useful current state.

This is an important idempotency test.

---

# 38. BACKEND TESTS

Add/update tests for at least:

## Test 1 — Create COD order

```text
checkout COD

Expected:
payment pending/unpaid
```

---

## Test 2 — Valid collection

```text
pending COD
+
authorized seller
+
eligible order

→ collection succeeds
→ payment paid
→ transaction created once
```

---

## Test 3 — Wrong payment method

```text
Bakong payment

→ collect-cod rejected
```

---

## Test 4 — Wrong shop

```text
Shop A seller
Shop B COD payment

→ forbidden
```

---

## Test 5 — Customer

```text
customer attempts collection

→ forbidden
```

---

## Test 6 — Duplicate collection

```text
collect twice

→ one financial transaction
→ second request safe
```

---

## Test 7 — Concurrent collection

Simulate/verify as far as existing test architecture permits.

Only one successful collection record.

---

## Test 8 — Cancelled order

```text
cancelled COD
→ cannot collect
```

---

## Test 9 — Return eligibility

```text
delivered
COD pending

→ return not eligible if paid sale required

collect COD

→ eligible return succeeds
```

---

## Test 10 — Revenue/reporting

```text
pending COD
→ not settled revenue

collected COD
→ settled revenue
```

according to current metric definition.

---

## Test 11 — Financial analytics

Verify collected COD enters the correct analytics period.

---

## Test 12 — Parent aggregation

Multi-shop:

```text
Shop A paid
Shop B pending
→ parent partial

Shop B paid
→ parent paid
```

if parent status exists.

---

## Test 13 — Retry after lost response

Repeated same collection cannot double-record.

---

# 39. MULTI-SHOP PAYMENT TEST

Example:

```text
Parent Order = $100

Shop A COD = $40
Shop B COD = $60
```

Initial:

```text
A pending
B pending
Parent pending
```

Collect A:

```text
A paid
B pending
Parent partially paid
```

Collect A again:

```text
no duplicate
```

Collect B:

```text
A paid
B paid
Parent paid
```

Verify transaction totals:

```text
$40 + $60 = $100
```

---

# 40. F01 REGRESSION

Verify:

```text
shop coupon ownership
mixed-shop discount allocation
```

still passes.

---

# 41. F02 REGRESSION

Verify:

```text
stale cart items do not break cart
```

still passes.

---

# 42. F03 REGRESSION

Verify:

```text
quote total
order total
payment amount
receipt amount
```

remain consistent.

COD collection must use the persisted correct amount from F03.

---

# 43. FRONTEND TESTS

Test seller payment UI:

```text
pending COD shows action

paid COD hides action

wrong method has no COD action

loading state prevents duplicate click

success updates state

business error displays clearly
```

---

# 44. BROWSER / E2E TEST

Add an end-to-end regression scenario.

Example:

```text
Create/use COD order
→ seller opens payment/order
→ records collection
→ status becomes Paid
→ reload page
→ still Paid
→ action no longer available
```

Then customer:

```text
opens delivered order
→ return eligibility now works
```

Use mocked/provider-independent COD flow.

Run desktop/mobile where applicable.

---

# 45. MYSQL CONCURRENCY

The audit still lists MySQL concurrency validation as a broader open validation area.

For F04:

If practical, test COD collection concurrency with the actual MySQL test environment.

If not completed, report clearly:

```text
MySQL concurrency:
NOT VERIFIED
```

Do NOT claim it passed based only on SQLite.

---

# 46. DOCKER ONLY

Use project Docker containers for:

```text
PHP
Composer
Node
backend tests
frontend tests
build
```

Do NOT wipe or reseed important development data.

Use isolated testing.

---

# 47. DATABASE MIGRATION

Do not add schema changes automatically.

First determine whether existing:

```text
payment_transactions
payment status
metadata
timestamps
actor/audit data
```

are sufficient.

Only add a migration if the existing schema cannot safely represent collection.

If adding:

```text
collected_at
collected_by
```

explain why transaction history alone is insufficient.

---

# 48. CODE QUALITY

Do not put the entire workflow inside:

```text
AdminPaymentController
```

Prefer a service/domain operation such as existing:

```text
PaymentService
```

or add a focused service if none exists.

Potential concept:

```text
CodCollectionService
```

only if it reduces duplicated seller/admin logic.

Both seller/admin endpoints should use the same core collection behavior.

---

# 49. DO NOT START F05

This task is only F04.

Do not implement guest checkout yet.

Do not redesign Bakong.

Do not redesign refunds broadly.

Document dependencies, but keep scope controlled.

---

# 50. DEFINITION OF DONE

Do not mark F04 complete until:

```text
[x] COD begins pending/unpaid

[x] Authorized seller can record actual collection

[x] Super Admin behavior follows policy

[x] Customer cannot record collection

[x] Shop A cannot collect Shop B payment

[x] Online payment cannot use COD collection action

[x] Collection creates payment transaction

[x] Backend determines amount

[x] Payment becomes paid

[x] Duplicate collection is safe

[x] Concurrent collection is protected

[x] Parent payment aggregation is correct

[x] Mixed-shop payment state is correct where supported

[x] Cancelled order cannot collect

[x] Paid history remains auditable

[x] Collected COD enters settled revenue

[x] Collected COD enters financial analytics correctly

[x] Delivered + collected COD becomes return-eligible when other rules pass

[x] Seller UI works

[x] Admin UI works

[x] Customer payment status is understandable

[x] F01 regression passes

[x] F02 regression passes

[x] F03 reconciliation passes

[x] Backend tests pass

[x] Frontend type check passes

[x] Production build passes

[x] Browser regression passes
```

---

# 51. FINAL REPORT

At completion report exactly:

```text
F04 — COD COLLECTION & PAID-STATE LIFECYCLE

Status:
PASS / NEEDS WORK

Root Cause:

Old Lifecycle:

New Lifecycle:

Collection Authorization:

Collection Amount Source:

Transaction Safety:

Idempotency Strategy:

Concurrency Strategy:

Multi-Shop Aggregation:

Return Eligibility Result:

Revenue Result:

Analytics Result:

Files Changed:

Schema Changes:
None / List

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

F03 Regression:
PASS / FAIL

MySQL Concurrency:
PASS / NOT VERIFIED / FAIL

Remaining Risks:

Related Follow-Up:
F05 — Guest Checkout Policy & Web Flow
```

Also include a concrete before/after example:

```text
BEFORE

Order:
Delivered

Payment Method:
COD

Payment:
Pending

Return:
Rejected


AFTER

Order:
Delivered

Payment Method:
COD

Collection:
$44 received

Payment:
Paid

Return:
Eligible according to normal return rules
```

Stop after F04.

Do NOT automatically begin F05.

I will review the result first.
