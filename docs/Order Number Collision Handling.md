# E-KHMER Improvement #14 — Order Number Collision Handling

Act as a senior **Laravel + MySQL Marketplace Engineer, Distributed Systems Engineer, Transaction Engineer, Database Integrity Engineer, Checkout Engineer, and QA Engineer**.

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
F08 — Async Cart Mutations & Buy Now Errors
F09 — Formatted Product Descriptions
F10 — Review Aggregate Maintenance
F11 — Low-Stock Notification Lifecycle
F12 — Duplicate Coupon Validation
F13 — Checkout & Reservation Abuse Protection
```

Do NOT regress those fixes.

Now work ONLY on:

# F14 — Order Number Collision Handling

---

# 1. CURRENT DEFECT

The current source review found:

```text
OrderNumberGenerator
→ generates candidate
→ final candidate may not be robustly checked

CheckoutService
→ attempts order insert

If another request already used same number:
→ database unique constraint failure
→ no bounded retry
→ possible 500
```

A forced order-number collision must never create:

```text
duplicate order numbers
```

and should not normally produce:

```text
500 Internal Server Error
```

---

# 2. PRIMARY GOAL

Implement robust order-number generation with database-backed uniqueness.

Correct flow:

```text
Checkout begins
      ↓
Generate candidate order number
      ↓
Attempt INSERT
      ↓
Success
      ↓
Continue checkout
```

If database reports order-number conflict:

```text
Unique conflict
      ↓
Generate NEW candidate
      ↓
Retry insert
      ↓
Bounded number of attempts
```

If all attempts fail:

```text
Rollback checkout safely
→ return controlled server/business error
→ no partial order
→ no leaked stock/payment state
```

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before modifying code.

Focus especially on:

```text
backend/app/Services/OrderNumberGenerator.php
backend/app/Services/CheckoutService.php
```

Also inspect:

```text
backend/app/Models/Order.php
backend/app/Models/ShopOrder.php

orders migration
shop_orders migration

database unique indexes

checkout transaction logic
idempotency logic
inventory reservations
payment creation

order resources
receipt generation
tracking routes

tests for checkout
tests for order numbers
```

Search for:

```text
order_number
order_no
number
generateOrderNumber
generate
unique
random
sequence
MAX(
count(
retry
duplicate
IntegrityConstraintViolation
QueryException
```

Do NOT assume the generator implementation.

---

# 4. CONFIRM ORDER NUMBER TYPES

Determine whether E-KHMER has:

```text
Parent Order Number
```

and:

```text
Shop Order Number
```

Example:

```text
Parent:
EK-20261102-000123

Shop:
EK-20261102-000123-1
EK-20261102-000123-2
```

or a different format.

Document all externally visible identifiers.

---

# 5. DEFINE UNIQUENESS SCOPE

Determine whether:

```text
orders.order_number
```

must be globally unique.

Recommended:

```text
YES
```

for parent order numbers.

Then determine shop order-number uniqueness.

Possible:

```text
globally unique
```

or:

```text
unique within parent order
```

Use current schema/business rules.

---

# 6. DATABASE UNIQUE CONSTRAINT

Application checks are not sufficient.

Ensure the database has the final integrity guarantee.

Example:

```text
UNIQUE(order_number)
```

for the intended scope.

Do NOT remove the unique index to avoid collisions.

The database must remain the final authority.

---

# 7. DO NOT USE COUNT()+1

If current generator uses:

```text
Order::count() + 1
```

this is unsafe.

Example:

```text
Request A:
count = 100
candidate = 101

Request B:
count = 100
candidate = 101
```

Both generate the same identifier.

Avoid this pattern.

---

# 8. DO NOT RELY ONLY ON MAX()+1

If current generator uses:

```text
MAX(sequence) + 1
```

without locking/sequence protection, concurrent requests can still collide.

Example:

```text
A reads MAX = 100
B reads MAX = 100

A → 101
B → 101
```

Do not assume `MAX()+1` is concurrency-safe.

---

# 9. DO NOT RELY ONLY ON EXISTS()

This is also insufficient:

```text
generate candidate
↓
SELECT exists?
↓
not found
↓
INSERT
```

Two concurrent requests can both pass the existence check.

This is a TOCTOU race.

Database unique constraint + retry is still required.

---

# 10. ROBUST GENERATION STRATEGY

Use the existing format if possible.

Possible safe strategies:

```text
random/secure suffix
UUID-derived identifier
ULID-derived identifier
database sequence/counter
date + sufficiently unique random component
```

Do NOT change customer-facing number format unnecessarily.

---

# 11. KEEP HUMAN-READABLE FORMAT IF POSSIBLE

If current E-KHMER format is readable:

```text
EK-20261102-001245
```

preserve that style where practical.

Do not suddenly replace it with:

```text
550e8400-e29b-41d4-a716-446655440000
```

unless architecture intentionally requires that.

Internal UUID and external order number can be separate concepts.

---

# 12. BOUNDED RETRY

Collision retry must be bounded.

Concept:

```text
MAX_ORDER_NUMBER_ATTEMPTS = 5
```

Example flow:

```text
attempt 1
→ collision

attempt 2
→ collision

attempt 3
→ success
```

Do not use:

```text
while (true)
```

without a hard limit.

---

# 13. RETRY ONLY THE CORRECT ERROR

Critical.

Do NOT retry every database exception.

Only retry when the error is specifically:

```text
unique constraint violation
```

for:

```text
orders.order_number
```

or the relevant order-number index.

Examples that must NOT be hidden by retries:

```text
database disconnected
foreign key failure
invalid column
deadlock requiring different handling
data truncation
payment constraint error
```

---

# 14. DUPLICATE CONFLICT DETECTION

Use Laravel/database-driver-safe duplicate detection.

Do not rely only on fragile error-message string parsing if a better repository helper exists.

Inspect current project exception-handling patterns.

---

# 15. KEEP RETRY CLOSE TO INSERT

A generator method such as:

```text
generateUniqueOrderNumber()
```

that checks before insert is still race-prone.

Preferred safety:

```text
generate candidate
↓
attempt actual order INSERT
↓
if order_number unique conflict:
    retry
```

Database insert is the authoritative uniqueness test.

---

# 16. TRANSACTION BOUNDARY

Inspect current CheckoutService transaction.

Order-number retry must fit safely inside it.

Concept:

```text
BEGIN

validate checkout
reserve/lock inventory as designed

attempt create parent order
    collision?
    retry candidate

create shop orders
create order items
create payment
create reservation relationships

COMMIT
```

Do not accidentally commit partial checkout state between retries.

---

# 17. DO NOT DUPLICATE INVENTORY RESERVATION

If order-number collision occurs:

```text
retrying order creation
```

must NOT cause:

```text
inventory reserved twice
```

This is critical.

Keep retry scope as narrow as possible.

---

# 18. DO NOT DUPLICATE COUPON USAGE

Collision retry must not:

```text
increment coupon usage twice
```

Preserve F01/F03/F12 behavior.

---

# 19. DO NOT DUPLICATE PAYMENT CREATION

Example:

```text
order number collision
```

should occur before creating multiple:

```text
payments
KHQR intents
COD payment rows
```

Structure flow so retrying the order-number insert does not duplicate financial records.

---

# 20. IDEMPOTENCY INTERACTION

F13 and existing checkout flow already support owner-scoped idempotency.

Do NOT replace that.

Different concepts:

```text
Idempotency:
same customer request should not create two orders

Order-number uniqueness:
different legitimate orders must not share one number
```

Both protections are required.

---

# 21. SAME IDEMPOTENCY KEY

Scenario:

```text
checkout request succeeds
network response lost

client retries same idempotency key
```

Expected:

```text
same existing order returned
same order number returned
```

Do NOT generate a new order number for the idempotent retry.

---

# 22. DIFFERENT CHECKOUTS

Two legitimate distinct checkouts:

```text
Request A
Request B
```

must receive:

```text
different order numbers
```

even when created simultaneously.

---

# 23. SHOP ORDER NUMBER GENERATION

Inspect shop-order generation.

Example:

```text
Parent:
EK-20261102-00100

Shop A:
EK-20261102-00100-01

Shop B:
EK-20261102-00100-02
```

If child numbers are derived deterministically from parent + sequence, ensure uniqueness rules are safe.

---

# 24. CHILD ORDER COLLISION

If shop-order numbers have their own unique index:

handle collisions correctly.

Do not solve parent order numbers while leaving child order-number generation vulnerable.

---

# 25. MULTI-SHOP CHECKOUT

Test:

```text
Customer cart:
Shop A
Shop B
Shop C
```

Expected:

```text
1 unique parent number
3 correctly unique shop-order numbers
```

No duplicates.

---

# 26. ORDER NUMBER IMMUTABILITY

Once generated/persisted:

```text
order_number
```

should not change during normal order lifecycle.

Do not regenerate on:

```text
payment success
shipment
delivery
refund
return
```

---

# 27. RECEIPTS

Receipts must continue using the persisted order number.

Do not regenerate it during PDF generation.

---

# 28. ORDER TRACKING

Tracking routes/pages must use the persisted unique value correctly.

No regression to:

```text
customer tracking
admin lookup
seller order lookup
```

---

# 29. SEARCH

Admin/seller order search by order number must remain functional.

If formatting changes:

avoid doing so unless necessary.

---

# 30. DATE COMPONENT

If format contains date:

```text
EK-20261102-...
```

use one consistent timezone.

Determine whether existing system uses:

```text
application timezone
UTC
Asia/Phnom_Penh
```

Do not create different date prefixes depending on server/container timezone.

---

# 31. CLOCK BOUNDARY

Test creation around:

```text
23:59:59
00:00:00
```

if date is part of the identifier.

Do not assume collision behavior only occurs during daytime.

---

# 32. RANDOMNESS

If random suffix is used:

use an appropriate generator.

Do NOT use weak patterns like:

```php
rand(1000, 9999)
```

for a high-volume uniqueness guarantee unless collision retry and capacity make it acceptable.

Prefer Laravel/PHP secure randomness utilities where suitable.

---

# 33. FORMAT CAPACITY

Calculate identifier space.

Example:

```text
4 numeric digits
= 10,000 candidates/day
```

If marketplace can exceed this, format is insufficient.

Document the capacity.

---

# 34. DATABASE COLUMN LENGTH

Verify column length can hold generated format.

Example:

```text
VARCHAR(50)
```

Do not generate a longer identifier that gets truncated.

Truncation can create unexpected collisions.

---

# 35. NORMALIZATION

Order number should have canonical formatting.

Example:

```text
uppercase
no leading/trailing spaces
```

Do not allow manually supplied customer order numbers.

---

# 36. SERVER-GENERATED ONLY

Frontend must never decide:

```text
order_number
```

Ignore/reject any client-supplied order number.

Server is source of truth.

---

# 37. FORCED COLLISION TESTABILITY

Make generator testable.

Possible architecture:

```text
OrderNumberGenerator
```

can be mocked/faked so tests force:

```text
candidate 1 = existing
candidate 2 = existing
candidate 3 = new
```

This allows deterministic collision testing.

---

# 38. CRITICAL TEST — FIRST COLLISION

Setup:

```text
Existing:
EK-TEST-001
```

Generator returns:

```text
Attempt 1:
EK-TEST-001

Attempt 2:
EK-TEST-002
```

Expected:

```text
checkout succeeds
order number = EK-TEST-002
```

No 500.

---

# 39. CRITICAL TEST — MULTIPLE COLLISIONS

Generator:

```text
1 → duplicate
2 → duplicate
3 → unique
```

Expected:

```text
third candidate succeeds
```

Only one order is created.

---

# 40. RETRY LIMIT TEST

Generator always returns existing numbers.

After configured max attempts:

Expected:

```text
checkout fails safely
```

No infinite loop.

No duplicate order.

No partial checkout artifacts.

---

# 41. SAFE FAILURE MESSAGE

Customer should receive a generic safe message such as:

```text
We couldn't create your order. Please try again.
```

Do not show:

```text
Duplicate entry 'EK-...'
SQLSTATE[23000]
unique_orders_order_number
```

---

# 42. LOGGING

Internal logs should contain useful diagnostic context.

Example:

```text
event:
order_number_collision

attempt:
2

candidate:
EK-...
```

Be careful not to log sensitive checkout payloads.

---

# 43. ALERTING

A single recovered collision is not necessarily an incident.

Do not send Telegram/system incident alerts for every successful collision retry.

Repeated exhaustion of retry limit may be worth an error log.

Leave broader incident policy to O13.

---

# 44. DATABASE UNIQUE INDEX TEST

Verify actual MySQL schema has intended unique constraint.

Do not trust only Laravel model validation.

---

# 45. EXISTING DUPLICATES

Before adding a unique index, if missing:

inspect existing rows.

If duplicates already exist:

```text
do NOT automatically delete/rename production orders
```

Report them for migration strategy.

---

# 46. MIGRATION SAFETY

Only add/change index if necessary.

Do not run:

```text
migrate:fresh
```

to solve order-number problems.

Preserve existing orders.

---

# 47. DEADLOCKS ARE DIFFERENT

MySQL deadlock:

```text
1213
```

is not the same as:

```text
duplicate order number
```

Do not accidentally treat every `QueryException` as a number collision.

---

# 48. DATABASE CONNECTION ERROR

If database connection fails:

do not retry order-number generation five times as if candidates collided.

Fail according to existing infrastructure error handling.

---

# 49. OTHER UNIQUE CONSTRAINTS

Order creation may have other unique fields, such as:

```text
idempotency_key
payment reference
external reference
```

If one of those conflicts:

do NOT assume it is an order-number collision.

Detect the relevant index/field.

---

# 50. IDENTITY OF COLLISION

Retry only when:

```text
violated constraint == order-number uniqueness
```

or a reliable equivalent.

This prevents hiding unrelated bugs.

---

# 51. RETRY LOCATION

Preferred concept:

```text
CheckoutService
→ createParentOrderWithUniqueNumber()
```

or a focused domain service.

Do not clutter controller with retry loops.

---

# 52. GENERATOR RESPONSIBILITY

Recommended separation:

```text
OrderNumberGenerator
→ generates next candidate

CheckoutService / creation helper
→ attempts persistence
→ handles bounded conflict retry
```

Do not make generator responsible for the whole checkout transaction.

---

# 53. SHOP ORDER GENERATOR

If separate:

```text
ShopOrderNumberGenerator
```

inspect and harden similarly.

Do not create one giant generic generator if existing architecture separates concerns cleanly.

---

# 54. CONCURRENCY

Test simultaneous order creation.

Example:

```text
Request A
Request B

generator collision occurs
```

Expected:

```text
A succeeds
B retries and succeeds with different number
```

No duplicate.

---

# 55. MYSQL TESTING

This feature depends strongly on database behavior.

Prefer actual MySQL concurrency tests where practical.

If only SQLite tests run:

report:

```text
MySQL concurrency:
NOT VERIFIED
```

Do not claim production-grade collision safety solely from SQLite.

---

# 56. HIGH-CONTENTION TEST

If practical:

create many concurrent checkout/order-number requests.

Example:

```text
20 parallel order creations
```

Verify:

```text
20 unique numbers
0 duplicate rows
0 unexpected 500s
```

Use isolated test data.

---

# 57. UNIQUE COUNT ASSERTION

After concurrency test:

```text
COUNT(orders)
=
COUNT(DISTINCT order_number)
```

for created fixtures.

---

# 58. PARENT/CHILD RECONCILIATION

For multi-shop order:

verify:

```text
shop_orders.order_id
```

points to correct parent regardless of generated child number.

Order-number retry must not break relationships.

---

# 59. INVENTORY SIDE-EFFECT TEST

Force first order-number collision.

After checkout succeeds:

verify stock changed exactly once.

Example:

```text
initial stock = 10
order qty = 2

final stock/reservation state
= equivalent to one order only
```

Not two.

---

# 60. COUPON SIDE-EFFECT TEST

Force collision with coupon.

Verify:

```text
coupon usage increment = 1
```

not:

```text
2
```

---

# 61. PAYMENT SIDE-EFFECT TEST

Force collision.

Verify:

```text
payment rows created = 1
```

for final order.

---

# 62. ORDER ITEM SIDE-EFFECT TEST

Force collision.

Verify:

```text
order items created once
```

No orphan rows from failed candidate attempts.

---

# 63. SHOP ORDER SIDE-EFFECT TEST

Force parent collision.

Verify:

```text
shop orders only belong to successful parent order
```

No child rows from failed parent-number attempt.

---

# 64. IDEMPOTENCY SIDE-EFFECT TEST

Forced collision + request retry with same idempotency key.

Expected:

```text
one checkout
one order
one final order number
```

---

# 65. F13 INTERACTION

F13 protects repeated checkout creation.

A legitimate order-number collision retry inside one checkout must NOT consume:

```text
another rate-limit attempt
another active-reservation slot
```

This retry is internal server behavior, not a new customer checkout.

---

# 66. ORDER NUMBER RETRY MUST BE INTERNAL

Do not tell frontend:

```text
Please submit checkout again because order number collided.
```

Normal collision recovery should happen inside backend.

---

# 67. API RESPONSE

Final successful response returns only the successfully persisted order number.

Never expose failed candidates.

---

# 68. ORDER EVENTS

If checkout emits:

```text
OrderCreated
```

event:

emit it only for the successfully created order.

Do not dispatch events for failed insert attempts.

---

# 69. NOTIFICATIONS

Customer order confirmation should be sent once.

Seller notification should be sent once.

Collision retries must not duplicate notifications.

---

# 70. EMAIL

Receipt/order email should contain final persisted order number.

No duplicate emails from retries.

---

# 71. QUEUES

If jobs dispatch during checkout:

prefer dispatch after successful transaction commit.

Do not queue jobs for failed order-number candidates.

---

# 72. AUDIT LOG

Audit/order-created entry should exist once.

Internal collision retries may go to application logs, not repeated business audit rows.

---

# 73. RECEIPT FILE NAMES

If receipt filename uses order number:

ensure it uses final persisted number.

Do not create artifacts for failed candidate numbers.

---

# 74. SECURITY

Order number uniqueness is not authorization.

Even if numbers become difficult to guess:

continue enforcing:

```text
customer ownership
seller shop isolation
admin permissions
guest secure access
```

Do not use unpredictability as the only access control.

---

# 75. GUESSABILITY

Do not redesign order-number secrecy in F14.

Human-readable order numbers can remain.

Authorization remains the real protection.

---

# 76. BACKEND UNIT TESTS — GENERATOR

Add tests for:

```text
format valid
length valid
prefix correct
date component correct if used
candidate changes appropriately
```

according to existing generator design.

---

# 77. BACKEND FEATURE TEST — NORMAL CREATE

Normal checkout:

```text
no collision
```

Expected:

```text
one order
one valid unique order number
```

---

# 78. BACKEND FEATURE TEST — COLLISION THEN SUCCESS

Force:

```text
candidate 1 duplicate
candidate 2 unique
```

Expected:

```text
checkout succeeds
```

---

# 79. BACKEND FEATURE TEST — RETRY EXHAUSTION

Force all candidates duplicate.

Expected:

```text
controlled failure
```

No partial records.

---

# 80. BACKEND FEATURE TEST — WRONG DB ERROR

Force unrelated DB exception.

Expected:

```text
do not treat as order-number collision
```

Fail normally.

---

# 81. BACKEND FEATURE TEST — IDEMPOTENT RETRY

Existing successful checkout + same key.

Expected:

```text
same order
same order number
```

---

# 82. MULTI-SHOP TEST

Cart:

```text
Shop A
Shop B
```

Force parent-number collision.

Expected:

```text
one successful parent
two correct shop orders
unique numbers
```

---

# 83. CONCURRENCY TEST

Where practical:

```text
multiple concurrent order creations
```

Verify uniqueness.

---

# 84. FRONTEND CHANGES

Ideally F14 requires little or no frontend change.

If backend ultimately fails after retry exhaustion:

reuse F08 normalized checkout error handling.

Do not create a special order-number-collision UI.

---

# 85. CUSTOMER UX

Normal recovered collision:

```text
customer notices nothing
```

Checkout succeeds normally.

This is the desired behavior.

---

# 86. FINAL FAILURE UX

Only if all internal retries fail:

```text
We couldn't create your order. Please try again.
```

Cart should remain available.

Do not falsely show successful checkout.

---

# 87. DO NOT CLEAR CART ON FAILED ORDER CREATION

If transaction fails before order success:

customer cart must not be incorrectly cleared.

Use existing checkout behavior.

---

# 88. DO NOT CONSUME RESERVATION INCORRECTLY

If final order creation fails:

reservation state must follow existing rollback/release behavior.

No orphan inventory hold caused solely by order-number failure.

---

# 89. PERFORMANCE

Do not add a query loop like:

```text
generate
exists
generate
exists
generate
exists
```

for every normal order if unnecessary.

Normal case should remain efficient.

---

# 90. CONFIGURATION

If retry count is configurable:

use a clear setting such as:

```text
orders.number_generation_max_attempts
```

Do not scatter magic values.

If configuration is unnecessary, a well-defined constant is acceptable.

---

# 91. LOG RETRY EXHAUSTION

If maximum attempts are exhausted:

log at appropriate severity.

Include:

```text
attempt_count
generator strategy
checkout correlation/idempotency reference
```

Avoid sensitive customer information.

---

# 92. METRICS

Do not build a monitoring platform in F14.

If current metrics infrastructure exists, optionally increment:

```text
order_number_collision
```

but this is not required.

---

# 93. F01 REGRESSION

Coupon ownership unaffected.

---

# 94. F02 REGRESSION

Cart recovery unaffected.

---

# 95. F03 REGRESSION

Checkout totals unaffected.

---

# 96. F04 REGRESSION

COD lifecycle unaffected.

---

# 97. F05 REGRESSION

Guest checkout/account-required policy unaffected.

---

# 98. F06 REGRESSION

Catalog filters unaffected.

---

# 99. F07 REGRESSION

Mobile color filter unaffected.

---

# 100. F08 REGRESSION

Checkout error handling remains safe.

---

# 101. F09 REGRESSION

Rich descriptions unaffected.

---

# 102. F10 REGRESSION

Review aggregates unaffected.

---

# 103. F11 REGRESSION

Inventory notification lifecycle unaffected.

---

# 104. F12 REGRESSION

Coupon validation unaffected.

---

# 105. F13 REGRESSION

Critical:

```text
checkout rate limiting
active reservation quota
idempotent retries
```

must still work.

Internal number retries must not be treated as new client attempts.

---

# 106. SCHEMA REVIEW

Confirm:

```text
orders.order_number
```

has correct unique index.

Also inspect:

```text
shop_orders.order_number
```

if applicable.

---

# 107. INDEX MIGRATION

If unique index is missing:

add a safe migration only after checking existing duplicates.

Do not silently repair historical data.

---

# 108. DOCKER ONLY

Use project Docker environment for:

```text
PHP
Composer
MySQL
Redis if checkout needs it
Node if frontend verification is needed
tests
build
```

Do not wipe development data.

---

# 109. TYPE CHECK

If frontend changes occur:

run TypeScript type check.

If no frontend files change:

still follow project standard regression verification as practical.

---

# 110. PRODUCTION BUILD

Run frontend production build if current project acceptance process requires it.

---

# 111. DO NOT START F15

Only solve F14.

Do not begin report metric consistency work.

That is:

```text
F15 — Report Metric Consistency
```

---

# 112. DEFINITION OF DONE

Do not mark F14 complete until:

```text
[ ] Parent order-number uniqueness scope is defined

[ ] Shop-order number scope is defined where applicable

[ ] Database unique constraint exists

[ ] Generator no longer relies on unsafe count/max assumptions without protection

[ ] Actual INSERT is protected by database uniqueness

[ ] Order-number conflict triggers bounded retry

[ ] Retry generates a new candidate

[ ] Retry handles only the relevant unique conflict

[ ] Unrelated DB errors are not hidden

[ ] Retry count is bounded

[ ] Retry exhaustion fails safely

[ ] Forced collision does not produce duplicate order

[ ] Forced collision normally does not produce 500

[ ] Collision retry does not duplicate inventory reservation

[ ] Collision retry does not duplicate coupon usage

[ ] Collision retry does not duplicate payment

[ ] Collision retry does not duplicate order items

[ ] Multi-shop child orders remain correct

[ ] Customer notifications are not duplicated

[ ] Idempotency still returns same existing order

[ ] F13 limits are not consumed by internal retry

[ ] Existing order search/tracking/receipt behavior works

[ ] Backend tests pass

[ ] Forced collision tests pass

[ ] MySQL concurrency is tested or explicitly reported as not verified

[ ] F01–F13 regressions pass
```

---

# 113. FINAL REPORT

At completion report exactly:

```text
F14 — ORDER NUMBER COLLISION HANDLING

Status:
PASS / NEEDS WORK

Root Cause:

Old Generator Strategy:

New Generator Strategy:

Parent Order Number Format:

Parent Uniqueness Scope:

Shop Order Number Format:

Shop Order Uniqueness Scope:

Database Unique Constraints:

Retry Location:

Maximum Retry Attempts:

Collision Detection Strategy:

Unrelated DB Error Handling:

Transaction Strategy:

Idempotency Interaction:

F13 Rate-Limit Interaction:

Inventory Side Effects:
PASS / FAIL

Coupon Usage Side Effects:
PASS / FAIL

Payment Side Effects:
PASS / FAIL

Order Item Side Effects:
PASS / FAIL

Notification Duplication:
NONE / LIST

Forced Collision Test:
PASS / FAIL

Retry Exhaustion Test:
PASS / FAIL

Concurrent Creation Test:
PASS / NOT VERIFIED / FAIL

MySQL Concurrency:
PASS / NOT VERIFIED / FAIL

Files Changed:

Schema Changes:
None / List

Indexes Added/Changed:
None / List

Config Added:
None / List

Tests Added:

Backend Tests:
Passed / Failed

Frontend Tests:
Passed / Failed / N/A

Type Check:
PASS / FAIL / N/A

Production Build:
PASS / FAIL / N/A

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

F08 Regression:
PASS / FAIL

F09 Regression:
PASS / FAIL

F10 Regression:
PASS / FAIL

F11 Regression:
PASS / FAIL

F12 Regression:
PASS / FAIL

F13 Regression:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F15 — Report Metric Consistency
```

Also provide this before/after example:

```text
BEFORE

Request A generates:
EK-20261102-00100

Request B generates:
EK-20261102-00100

A inserts successfully.

B inserts:
unique constraint violation

Result:
possible 500


AFTER

Request A:
candidate EK-20261102-00100
→ insert success

Request B:
candidate EK-20261102-00100
→ database reports order-number collision
→ backend generates new candidate
→ EK-20261102-00101
→ insert success

Result:

A:
EK-20261102-00100

B:
EK-20261102-00101

No duplicate order
No customer-visible collision error
```

Stop after F14.

Do NOT automatically begin F15.

I will review the result first.