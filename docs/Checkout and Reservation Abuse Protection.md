# E-KHMER Improvement #13 — Checkout & Reservation Abuse Protection

Act as a senior **Laravel + Vue.js + MySQL Marketplace Engineer, Checkout Security Engineer, Abuse-Prevention Engineer, Inventory Reservation Engineer, Concurrency Engineer, and QA Engineer**.

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
```

Do NOT regress those fixes.

Now work ONLY on:

# F13 — Checkout & Reservation Abuse Protection

---

# 1. CURRENT RISK

The current audit found that checkout and inventory reservation flows do not have sufficiently explicit abuse controls.

Potential abuse pattern:

```text
User / Bot
    ↓
Repeated checkout attempts
    ↓
Creates many active reservations
    ↓
Inventory becomes temporarily unavailable
    ↓
Real customers cannot purchase
```

Another possible pattern:

```text
Guest / Account
    ↓
Creates many pending orders
    ↓
Does not pay
    ↓
Reservations remain active until expiration
```

This can be used to create artificial stock exhaustion or excessive pending transactional data.

---

# 2. PRIMARY GOAL

Introduce layered protection without breaking legitimate customers.

Preferred protection model:

```text
Checkout Request
      ↓
Identity Context
      ↓
Throttle Check
      ↓
Active Reservation / Pending Order Limit
      ↓
Idempotency Check
      ↓
Inventory Validation
      ↓
Atomic Reservation
      ↓
Checkout Creation
```

Use multiple signals.

Do NOT rely only on:

```text
IP address
```

or only on:

```text
user_id
```

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing code.

Focus especially on:

```text
backend/app/Http/Controllers/Api/CheckoutController.php
backend/app/Services/CheckoutService.php
backend/app/Services/InventoryReservationService.php
backend/app/Services/CartService.php
```

Also inspect:

```text
routes/api.php
RouteServiceProvider
RateLimiter configuration

checkout request classes
checkout middleware
idempotency middleware/service

Order model
ShopOrder model
Payment model

InventoryReservation model
Cart model
CartItem model

reservation migrations
orders migrations

scheduler/cleanup commands
reservation expiry jobs

guest checkout implementation
authenticated checkout implementation

frontend/src/views/CheckoutView.vue
frontend/src/stores/cart.ts
frontend checkout API

existing checkout tests
reservation tests
browser tests
```

Search for:

```text
throttle
RateLimiter
reservation
expires_at
pending
idempotency
checkout
guest
session
fingerprint
ip
user_id
cart_id
```

Do NOT assume the current architecture.

---

# 4. MAP THE EXISTING CHECKOUT FLOW

Document the real flow.

Example:

```text
Cart
  ↓
Checkout Request
  ↓
Validate Products
  ↓
Validate Coupon
  ↓
Validate Delivery
  ↓
Calculate Totals
  ↓
Create Reservation
  ↓
Create Order
  ↓
Create Payment
```

or whatever the current implementation actually does.

Identify exactly when inventory becomes unavailable.

---

# 5. DEFINE THE ABUSE SURFACE

Identify which endpoints can create or hold inventory.

Potential:

```text
POST /checkout
POST /orders
POST /payments/initialize
POST /reservations
POST /cart/checkout
```

Do not throttle unrelated read-only endpoints unnecessarily.

---

# 6. IDENTITY MODEL

Abuse protection must understand who/what owns the checkout attempt.

Possible identity signals:

```text
Authenticated:
user_id

Guest:
guest session ID
cart owner token
session identifier

Network:
IP address
```

Use the existing guest-cart/session architecture.

Do NOT invent fingerprinting that collects unnecessary user/device data.

---

# 7. AUTHENTICATED OWNER KEY

For authenticated customers:

primary limit key should normally include:

```text
user_id
```

Potential:

```text
checkout:user:{id}
```

Use framework conventions.

---

# 8. GUEST OWNER KEY

For guest checkout:

prefer stable application-controlled identity such as:

```text
guest cart owner
session ID
checkout session
```

not only IP.

Example:

```text
checkout:guest:{session}
```

This helps distinguish multiple legitimate users behind the same network.

---

# 9. IP AS SECONDARY SIGNAL

IP can be useful as a secondary abuse signal.

Example:

```text
checkout:ip:{ip}
```

But do NOT use a very strict IP-only rule.

Many legitimate users may share:

```text
school Wi-Fi
office network
mobile carrier NAT
cafe Wi-Fi
```

---

# 10. MULTI-LAYER RATE LIMITING

Recommended concept:

```text
Per account/session:
stricter logical control

Per IP:
broader burst protection
```

Example only:

```text
Customer:
5 checkout attempts / minute

IP:
30 checkout attempts / minute
```

Do NOT blindly use these numbers.

Inspect current traffic expectations and test behavior.

Choose reasonable defaults and document them.

---

# 11. DO NOT RATE-LIMIT NORMAL BROWSING

Do not throttle:

```text
product browsing
catalog filters
cart reads
product details
```

under the checkout limiter.

Scope controls to high-impact transactional actions.

---

# 12. ACTIVE RESERVATION LIMIT

Rate limiting alone is not enough.

Example attacker:

```text
1 request every 2 minutes
```

could still create many active reservations.

Add an active reservation limit.

Concept:

```text
Authenticated user:
max N active checkout reservations

Guest session:
max N active reservations
```

Use reasonable project-specific limits.

---

# 13. ACTIVE PENDING ORDER LIMIT

If pending orders hold inventory or payment sessions:

limit excessive pending orders.

Example concept:

```text
Customer may have at most:
3 unpaid active checkout orders
```

Do NOT implement this blindly.

Determine:

```text
which statuses count
how long they remain active
whether COD counts
whether Bakong pending counts
```

---

# 14. DEFINE "ACTIVE"

Explicitly define active reservation.

Example:

```text
reservation.status = active
AND expires_at > now()
```

Do not count:

```text
expired
released
consumed
cancelled
```

as active.

---

# 15. DEFINE ACTIVE PENDING ORDER

Possible statuses:

```text
pending_payment
awaiting_payment
payment_pending
```

Do NOT count:

```text
paid
cancelled
expired
failed
refunded
```

unless current business rules require it.

---

# 16. COD MUST BE HANDLED DIFFERENTLY

COD is not "unpaid abuse" in the same way as an abandoned online payment.

After F04:

```text
COD
→ pending collection
```

may remain unpaid until delivery.

Do NOT count every legitimate COD order as an abusive pending checkout.

Differentiate:

```text
pending checkout/payment authorization
```

from:

```text
valid COD order awaiting fulfillment/collection
```

---

# 17. ONLINE PAYMENT PENDING

For Bakong/KHQR:

an order/payment may be pending for a limited payment window.

Those active pending orders may count toward the anti-abuse quota.

Once:

```text
expired
failed
cancelled
paid
```

they should no longer consume the quota.

---

# 18. RESERVATION EXPIRY

Ensure every reservation has a defined expiry.

Example:

```text
expires_at
```

Do not create indefinite inventory holds.

Verify:

```text
checkout created
→ payment abandoned
→ reservation expires
→ stock released
```

---

# 19. CLEANUP JOB

Inspect current scheduler.

There should be a reliable cleanup path:

```text
expired reservations
→ released
```

Potential:

```text
scheduled command every minute
```

or equivalent.

Do not add duplicate cleanup jobs if one already exists.

---

# 20. EXPIRY MUST BE IDEMPOTENT

Cleanup may run twice.

Expected:

```text
release reservation once
```

Do NOT:

```text
release stock twice
```

Preserve existing inventory ledger correctness.

---

# 21. CHECKOUT IDEMPOTENCY

The audit previously confirmed retry-safe checkout/idempotency exists.

Do NOT replace it.

F13 should layer abuse protection around existing idempotency.

Important:

```text
same legitimate retry
```

must not be counted as multiple new orders/reservations if the same idempotency key is reused.

---

# 22. IDEMPOTENT RETRY

Scenario:

```text
Customer submits checkout
server commits
network drops
customer retries same idempotency key
```

Expected:

```text
existing result returned
```

Not:

```text
rate-limit error
new reservation
new order
```

The idempotency check ordering matters.

---

# 23. ORDER OF CONTROLS

Inspect carefully.

Possible safe ordering:

```text
validate identity
↓
detect exact idempotent retry
↓
rate-limit new attempts
↓
active quota
↓
checkout
```

But do not leak another user's checkout through idempotency keys.

Scope idempotency to owner.

---

# 24. OWNER-SCOPED IDEMPOTENCY

Existing idempotency should remain tied to:

```text
authenticated user
or
guest owner/session
```

Never allow:

```text
Guest A idempotency key
```

to return:

```text
Guest B order
```

---

# 25. RESERVATION QUOTA BEFORE STOCK HOLD

Do not create reservation and then check quota.

Correct:

```text
check quota
↓
then reserve
```

unless transaction/locking architecture requires equivalent atomic logic.

---

# 26. TRANSACTION SAFETY

Reservation count check and creation should be race-safe.

Bad:

```text
count active reservations = 2
limit = 3

Request A sees 2
Request B sees 2

both create
→ 4 active
```

Protect using:

```text
transaction
locking
atomic constraint
```

as appropriate.

---

# 27. CONCURRENT CHECKOUT

Test:

```text
same customer/session
two checkout requests
same time
```

Expected:

```text
idempotency / quota / locking
prevents duplicate inventory holds
```

---

# 28. STOCK HOARDING

Scenario:

```text
Product stock = 10

Attacker creates:
10 separate reservations
qty 1 each
```

Within active reservation limits, system should prevent unreasonable accumulation.

Possible owner-level constraint:

```text
max active reserved units
```

or:

```text
max active checkout attempts
```

Do not overengineer unless needed.

Inspect actual reservation model first.

---

# 29. QUANTITY LIMIT

If cart allows extreme quantity:

```text
999999
```

backend stock validation already prevents beyond inventory.

But consider whether per-order quantity caps already exist.

Do not add arbitrary product quantity limits in F13 unless abuse risk requires it.

---

# 30. ACCOUNT VS SESSION LIMITS

Example design:

```text
Authenticated user:
active checkout quota

Guest session:
active checkout quota

IP:
burst threshold
```

Each protects a different abuse pattern.

---

# 31. MULTI-SHOP CHECKOUT

One parent checkout may create:

```text
multiple shop orders
multiple reservations
```

Do NOT count each shop order as separate abusive checkout if they belong to one parent checkout.

Quota should usually count:

```text
checkout session / parent order
```

or equivalent high-level operation.

---

# 32. RESERVATION COUNTING

If one checkout reserves 5 cart lines:

do not necessarily count:

```text
5 active reservations
```

against a limit of 3 checkouts.

Define whether quota counts:

```text
reservation groups
parent checkout
orders
```

Use architecture appropriately.

---

# 33. RETURN ACTIONABLE 429

Rate limit response:

```text
HTTP 429
```

with a safe message.

Example:

```text
Too many checkout attempts. Please wait a moment and try again.
```

Do not expose internal thresholds unnecessarily.

---

# 34. RETURN ACTIONABLE QUOTA ERROR

If customer has too many active pending checkouts:

use a business error such as:

```text
You already have several checkout attempts awaiting completion.
Please complete or cancel an existing checkout before starting another.
```

Could be:

```text
409
422
429
```

Use existing API conventions deliberately.

Document status choice.

---

# 35. DO NOT SHOW SECURITY LANGUAGE TO CUSTOMER

Avoid:

```text
You were blocked for suspicious activity.
```

for normal quota errors.

Use neutral UX.

---

# 36. FRONTEND HANDLING

Checkout page must recognize:

```text
429
active-checkout-limit error
```

and display a clear message.

Do not show:

```text
AxiosError
Request failed with status code 429
```

---

# 37. DISABLE REPEATED SUBMISSION

Preserve F08 patterns.

When Place Order is pending:

```text
disable button
```

and show:

```text
Processing...
```

This prevents accidental duplicate clicks.

But frontend protection is not sufficient by itself.

---

# 38. PAGE REFRESH

If customer refreshes while checkout exists:

do not automatically create another reservation.

Use existing checkout/order state where appropriate.

---

# 39. BACK BUTTON

If user returns from payment page:

do not create a new reservation simply by reopening checkout unless current reservation expired or state requires it.

---

# 40. PAYMENT INITIALIZATION ABUSE

Inspect whether payment initialization can be called repeatedly for the same order.

Potential:

```text
POST /payments/{order}/initialize
```

Do not create unlimited payment sessions/QRs unnecessarily.

Reuse existing active payment intent if architecture supports it.

Do not redesign Bakong broadly.

---

# 41. COUPON ABUSE

Do not make F13 responsible for coupon usage rules.

F01/F03/F12 remain authoritative.

But repeated failed checkout attempts must not increment coupon usage.

---

# 42. RESERVATION CREATION FAILURE

If reservation creation fails midway:

```text
no partial stock holds
```

Transaction must roll back.

---

# 43. PAYMENT FAILURE

If payment initialization fails after reservation:

existing architecture should decide whether reservation remains temporarily active or releases.

Do not introduce inconsistent behavior.

Document current behavior.

---

# 44. CHECKOUT VALIDATION FAILURE

Requests failing:

```text
address validation
delivery validation
coupon validation
stock validation
```

should not create persistent reservations.

---

# 45. RATE LIMIT INVALID REQUESTS

Repeated malformed checkout requests may still need throttling.

Do not make limiter apply only after expensive checkout logic.

Place it early enough to reduce server/database load.

---

# 46. AUTHENTICATION BRUTE FORCE IS NOT F13

Do not modify login throttling.

This task is checkout/reservation abuse protection only.

---

# 47. DISTRIBUTED ENVIRONMENT

If production runs multiple application containers:

do not use:

```text
process-local memory
```

for rate limiting.

Use existing shared cache:

```text
Redis
```

if configured.

The project already uses Redis.

Prefer framework rate limiter backed by configured cache.

---

# 48. REDIS FAILURE

Do not design a complicated fallback unless current architecture requires it.

Document behavior if cache/rate limiter storage is unavailable.

---

# 49. CONFIGURATION

Do not hardcode every limit deep in service code.

Prefer config:

```text
checkout.max_attempts_per_minute
checkout.max_active_reservations
checkout.max_active_pending_orders
```

or appropriate existing configuration.

Use environment overrides only where needed.

---

# 50. SAFE DEFAULTS

Provide reasonable defaults in configuration.

Do not choose extremely strict settings that block normal users.

Document each default.

---

# 51. LOGGING

Log abuse-limit events with safe context.

Useful:

```text
user_id or guest owner hash
IP
endpoint
active reservation count
limit type
```

Avoid:

```text
full address
card/payment secrets
raw auth tokens
session cookies
```

---

# 52. AUDIT LOG

Do not create business audit-log records for every rate-limited bot request unless architecture intentionally does so.

Application/security logs are usually enough.

---

# 53. TELEGRAM ALERTS

Do NOT send a Telegram alert for every 429.

That would create alert spam.

Only aggregate/severe abuse detection could become future monitoring work under O13.

F13 should remain focused.

---

# 54. SECURITY RESPONSE

Avoid revealing exact internal stock/reservation architecture to abusive clients.

Messages should remain simple.

---

# 55. GUEST CHECKOUT

Preserve F05.

Guest checkout must remain usable.

Test:

```text
Guest A
→ normal checkout
→ succeeds
```

Do not accidentally require authenticated user_id for quota logic.

---

# 56. SHARED IP TEST

Simulate multiple distinct guest sessions under one IP.

Normal small number of legitimate checkouts should still work.

This helps ensure IP limiter is not too strict.

---

# 57. AUTHENTICATED TEST

Customer with normal behavior:

```text
one checkout
one reservation
payment
```

must remain unaffected.

---

# 58. ABUSE TEST — BURST

Simulate:

```text
20 checkout requests
within short period
same owner
```

Expected:

```text
initial allowed attempts according to config
later requests → 429
```

No excess orders/reservations.

---

# 59. ABUSE TEST — SLOW HOARDING

Simulate requests below rate-limit threshold but enough to exceed active reservation quota.

Expected:

```text
quota blocks additional active holds
```

This proves active quota is necessary beyond throttling.

---

# 60. RESERVATION EXPIRY TEST

Create active reservation.

Move time beyond:

```text
expires_at
```

Run cleanup.

Expected:

```text
reservation expired
stock released
quota slot available again
```

---

# 61. PENDING ORDER EXPIRY TEST

If pending online-payment orders expire:

verify after expiration:

```text
no longer counts against active checkout quota
```

---

# 62. CANCEL TEST

Customer cancels pending checkout/order.

Expected:

```text
reservation released
quota slot freed
```

according to current cancellation flow.

---

# 63. PAYMENT SUCCESS TEST

After payment succeeds:

```text
reservation consumed / finalized
```

and active pending quota no longer counts that checkout.

---

# 64. COD TEST

COD checkout:

```text
order legitimately created
```

must not remain counted as "abandoned pending checkout" until delivery.

Preserve F04.

---

# 65. IDEMPOTENCY TEST

Send same checkout twice using same valid idempotency key.

Expected:

```text
one reservation
one order
same response/result
```

and should not consume two active quota slots.

---

# 66. DIFFERENT IDEMPOTENCY KEYS

Same user sends multiple new checkout attempts with different keys.

Active quota should still apply.

---

# 67. CROSS-USER IDEMPOTENCY

User A and User B use the same idempotency string.

They must remain isolated.

Do not treat raw key globally without owner scope.

---

# 68. CART CHANGE AFTER RESERVATION

If customer modifies cart after a pending checkout exists:

determine current policy.

Do not automatically duplicate old reservation.

Possible:

```text
old checkout remains separate
new checkout subject to quota
```

Preserve existing order semantics.

---

# 69. STOCK CONSISTENCY

Abuse protection must not change inventory math.

After blocked request:

```text
available stock unchanged
reserved stock unchanged
```

---

# 70. LOW-STOCK INTERACTION

F11 uses reservation-aware inventory where applicable.

Blocked checkout requests must NOT create:

```text
fake reservation
→ fake low-stock notification
```

This is a critical regression.

---

# 71. FINANCIAL INTERACTION

F03 requires backend-authoritative totals.

Abuse middleware must not accept frontend total values.

No change to financial calculations.

---

# 72. COUPON INTERACTION

F01/F12 coupon rules remain unchanged.

Rate-limited/blocked checkout must not:

```text
consume coupon usage
```

or increment counters.

---

# 73. ORDER NUMBER

Blocked checkout must not consume or create unnecessary order numbers if possible.

Do not create placeholder orders before quota validation.

---

# 74. DATABASE GROWTH

Repeated blocked requests must not create:

```text
orders
payments
reservations
coupon usages
inventory ledger rows
```

unless an existing idempotent record is being returned.

---

# 75. CLEANUP PERFORMANCE

Expired reservation cleanup should be efficient.

Inspect indexes on:

```text
status
expires_at
owner/user fields
```

Do not add indexes without reviewing query behavior.

---

# 76. INDEX REVIEW

Potential relevant indexes:

```text
inventory_reservations.status
inventory_reservations.expires_at
inventory_reservations.user_id
inventory_reservations.cart_id
orders.user_id
orders.status
orders.payment_status
```

Only add what actual queries need.

---

# 77. MYSQL CONCURRENCY

This feature requires real concurrency confidence.

Where practical test using MySQL:

```text
parallel checkout attempts
reservation quota
stock locking
idempotency
```

If only SQLite tests run:

report:

```text
MySQL concurrency:
NOT VERIFIED
```

---

# 78. BACKEND TEST — NORMAL CHECKOUT

```text
normal customer
within limits
```

Expected:

```text
checkout succeeds
reservation created once
```

---

# 79. BACKEND TEST — OWNER RATE LIMIT

Exceed configured owner limit.

Expected:

```text
429
```

No extra reservation/order.

---

# 80. BACKEND TEST — IP RATE LIMIT

Use multiple attempts from same IP.

Expected according to configured threshold.

---

# 81. BACKEND TEST — DIFFERENT USERS SAME IP

Verify reasonable normal traffic for distinct authenticated users is not blocked too early.

---

# 82. BACKEND TEST — ACTIVE RESERVATION LIMIT

Setup:

```text
owner already has maximum active reservations/checkouts
```

New checkout:

```text
blocked
```

No stock mutation.

---

# 83. BACKEND TEST — EXPIRED RESERVATIONS

Expired rows should not count toward limit.

---

# 84. BACKEND TEST — RELEASED RESERVATIONS

Released/cancelled rows should not count.

---

# 85. BACKEND TEST — COD

Valid COD order should not incorrectly block future legitimate checkout under "pending online payment" quota.

---

# 86. BACKEND TEST — IDEMPOTENT RETRY

Critical:

```text
same request
same idempotency key
```

must return same checkout result rather than a rate-limit/quota error.

---

# 87. BACKEND TEST — CONCURRENT QUOTA

If limit:

```text
3
```

and owner currently has:

```text
2
```

two simultaneous requests must not both create valid new reservations.

Expected maximum:

```text
3
```

---

# 88. BACKEND TEST — STOCK HOARDING

Repeated attempts should not reserve all stock beyond allowed active quota.

---

# 89. BACKEND TEST — BLOCKED REQUEST SIDE EFFECTS

Verify blocked request creates:

```text
0 orders
0 payments
0 reservations
0 coupon usages
0 stock changes
```

---

# 90. FRONTEND TEST

When API returns:

```text
429
```

show:

```text
Too many checkout attempts. Please wait and try again.
```

Do not clear cart.

---

# 91. ACTIVE LIMIT FRONTEND TEST

When backend returns:

```text
ACTIVE_CHECKOUT_LIMIT
```

show actionable message.

Example:

```text
You already have checkout attempts awaiting completion.
Complete or cancel one before starting another.
```

---

# 92. BUTTON STATE

After error:

```text
Place Order
```

must re-enable.

Do not leave loading indefinitely.

---

# 93. BROWSER / E2E — BURST TEST

Where existing browser setup supports it:

trigger repeated checkout submissions.

Verify:

```text
eventually blocked
cart remains
no duplicate orders
```

---

# 94. BROWSER / E2E — NORMAL USER

Normal checkout must still succeed after F13.

This is important to avoid overly aggressive protection.

---

# 95. F01 REGRESSION

Shop coupon isolation unaffected.

---

# 96. F02 REGRESSION

Stale cart recovery unaffected.

---

# 97. F03 REGRESSION

Checkout totals remain consistent.

---

# 98. F04 REGRESSION

COD lifecycle unaffected.

---

# 99. F05 REGRESSION

Guest checkout/account-required policy unaffected.

---

# 100. F06 REGRESSION

Catalog rating/price filters unaffected.

---

# 101. F07 REGRESSION

Mobile color filters unaffected.

---

# 102. F08 REGRESSION

Async checkout/cart errors remain handled safely.

---

# 103. F09 REGRESSION

Safe descriptions unaffected.

---

# 104. F10 REGRESSION

Review aggregates unaffected.

---

# 105. F11 REGRESSION

Blocked checkout requests must not create false low-stock notifications.

---

# 106. F12 REGRESSION

Coupon validation unaffected.

---

# 107. CONFIG DOCUMENTATION

Document:

```text
checkout throttle rules
active reservation limit
active pending order limit
reservation expiry duration
guest identity strategy
authenticated identity strategy
```

Do not leave hidden magic numbers.

---

# 108. DOCKER ONLY

Use project Docker environment for:

```text
PHP
Composer
Redis
MySQL
Node
tests
build
```

Do not wipe development data.

Use isolated test data.

---

# 109. TYPE CHECK

Run frontend TypeScript type check.

No:

```text
any
@ts-ignore
```

used to bypass new error types.

---

# 110. PRODUCTION BUILD

Run frontend production build.

Must pass.

---

# 111. DO NOT START F14

Only solve F13.

Do not begin order-number collision handling.

That is:

```text
F14 — Order Number Collision Handling
```

---

# 112. DEFINITION OF DONE

Do not mark F13 complete until:

```text
[ ] Checkout rate limiter exists

[ ] Authenticated user key is defined

[ ] Guest session/owner key is defined

[ ] IP is used only as an appropriate secondary control

[ ] Active reservation/checkout quota exists

[ ] Active statuses are explicitly defined

[ ] Expired/released reservations do not count

[ ] COD is not incorrectly treated as abandoned online payment

[ ] Existing idempotency still works

[ ] Idempotent retry does not create extra reservations

[ ] Concurrent attempts cannot bypass quota

[ ] Reservation expiry is defined

[ ] Expired reservations are released reliably

[ ] Blocked request creates no stock side effects

[ ] Blocked request creates no order/payment side effects

[ ] Coupon usage is not consumed on blocked checkout

[ ] F11 low-stock notifications are not triggered by blocked requests

[ ] Guest checkout still works according to F05

[ ] Normal legitimate checkout still works

[ ] 429 UX is clear

[ ] Active-limit UX is clear

[ ] Security logs contain safe context only

[ ] Backend tests pass

[ ] Redis/rate-limit tests pass

[ ] MySQL concurrency is tested or explicitly reported as not verified

[ ] Frontend tests pass

[ ] Browser regression passes where applicable

[ ] Type check passes

[ ] Production build passes

[ ] F01–F12 regressions pass
```

---

# 113. FINAL REPORT

At completion report exactly:

```text
F13 — CHECKOUT & RESERVATION ABUSE PROTECTION

Status:
PASS / NEEDS WORK

Root Risk:

Checkout Flow Before:

Checkout Flow After:

Authenticated Identity Key:

Guest Identity Key:

IP Strategy:

Rate Limit Configuration:

Active Reservation Limit:

Active Pending Order Limit:

Active Status Definitions:

Reservation Expiry:

Cleanup Strategy:

COD Handling:

Bakong/KHQR Pending Handling:

Idempotency Interaction:

Concurrent Request Protection:

Redis Usage:

Blocked Request Side Effects:
NONE / LIST

Inventory Protection:

Coupon Usage Protection:

Low-Stock Notification Interaction:

Frontend 429 UX:

Frontend Active-Limit UX:

Files Changed:

Schema Changes:
None / List

Indexes Added:
None / List

Config Added:
List

Tests Added:

Backend Tests:
Passed / Failed

Redis / Rate Limiter Tests:
Passed / Failed

Frontend Tests:
Passed / Failed

Browser Tests:
Passed / Failed / N/A

MySQL Concurrency:
PASS / NOT VERIFIED / FAIL

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

Remaining Risks:

Related Follow-Up:
F14 — Order Number Collision Handling
```

Also provide this before/after example:

```text
BEFORE

Attacker:
POST /checkout
POST /checkout
POST /checkout
POST /checkout
...

Each request:
creates/holds inventory

Result:
Real customers may see false out-of-stock


AFTER

Checkout request
→ owner/session/IP controls
→ idempotency
→ active reservation quota
→ transactional stock reservation

Normal customer:
allowed

Duplicate retry:
same checkout returned

Excessive new attempts:
429 / active-limit error

Blocked attempt:
no order
no payment
no coupon usage
no stock reservation
```

Stop after F13.

Do NOT automatically begin F14.

I will review the result first.