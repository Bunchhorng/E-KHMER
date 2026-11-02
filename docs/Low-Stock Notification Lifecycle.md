# E-KHMER Improvement #11 — Low-Stock Notification Lifecycle

Act as a senior **Laravel + Vue.js + MySQL Marketplace Engineer, Inventory Domain Engineer, Notification Engineer, Concurrency Engineer, Security Engineer, and QA Engineer**.

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
```

Do NOT regress those fixes.

Now work ONLY on:

# F11 — Low-Stock Notification Lifecycle

---

# 1. CURRENT DEFECT

The current audit reproduced this lifecycle:

```text
Stock = 4

Low-stock threshold = 5
        ↓
Low-stock notification sent
        ↓
low_stock_notified_at is set
```

Later stock recovers:

```text
Stock = 10
```

but:

```text
low_stock_notified_at
```

remains set.

Then stock drops again:

```text
10
→ 4
```

Expected:

```text
NEW low-stock notification
```

Current risk:

```text
No new notification
because low_stock_notified_at was never reset
```

This breaks the intended notification lifecycle.

---

# 2. PRIMARY GOAL

Implement a proper state lifecycle:

```text
NORMAL STOCK
     ↓
stock crosses low-stock threshold
     ↓
LOW STOCK
     ↓
send notification once
     ↓
mark notified
     ↓
stock recovers above threshold
     ↓
NORMAL STOCK
     ↓
reset low-stock notification marker
     ↓
stock becomes low again
     ↓
send a NEW notification
```

The system should alert once per low-stock episode.

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing code.

Focus especially on:

```text
backend/app/Services/InventoryService.php
backend/app/Notifications/LowStockNotification.php
```

Also inspect:

```text
backend/app/Models/Inventory.php
backend/app/Models/ProductVariant.php
backend/app/Models/Product.php
backend/app/Models/Shop.php

inventory migrations
inventory transaction migrations

checkout inventory reservation logic
reservation release logic
order cancellation logic
return/restock logic

seller inventory adjustments
admin inventory corrections

settings / low-stock threshold

notification preferences
admin notifications
seller notifications

frontend/src/views/seller/SellerInventoryView.vue
frontend seller dashboard
frontend admin inventory views

existing inventory tests
notification tests
browser tests
```

Search for:

```text
low_stock_notified_at
low stock
low_stock
threshold
available
reserved
quantity
release
reserve
adjust
restock
notify
```

Trace every inventory-changing path.

---

# 4. IDENTIFY THE REAL STOCK VALUE

Determine what E-KHMER considers low stock.

Possible definitions:

```text
quantity_on_hand
```

or:

```text
available = on_hand - reserved
```

The audit suggests the current system is reservation-aware.

Do NOT assume.

Document the exact source of truth.

---

# 5. RECOMMENDED LOW-STOCK BASIS

If checkout reserves stock, low-stock detection should normally use:

```text
available stock
```

rather than only physical on-hand stock.

Example:

```text
On hand:
10

Reserved:
7

Available:
3

Threshold:
5
```

This may be operationally low stock even though physical quantity is 10.

Use actual existing business behavior.

Do not redefine it unnecessarily in F11.

---

# 6. DEFINE STATES

Use a clear conceptual state.

Example:

```text
NORMAL
available > threshold

LOW
available <= threshold
```

or current project semantics.

Important boundary:

Determine whether:

```text
stock == threshold
```

means low.

Example threshold:

```text
5
```

Possible rule:

```text
available <= 5
→ low
```

Use current behavior consistently.

---

# 7. DEFINE ONE LOW-STOCK EPISODE

Example:

```text
20
→ 12
→ 6
→ 5
```

At:

```text
5
```

notification fires once.

Then:

```text
5
→ 4
→ 3
→ 2
```

Do NOT send repeated alerts for every decrement.

This is still the same low-stock episode.

---

# 8. RECOVERY

When stock returns to normal:

```text
2
→ 8
```

reset notification eligibility.

Concept:

```text
low_stock_notified_at = null
```

or equivalent existing state.

Then:

```text
8
→ 5
```

may trigger another low-stock alert.

---

# 9. CRITICAL AUDIT REPRODUCTION

Test exactly:

```text
Threshold = 5

Available = 4

Notification sent
low_stock_notified_at != null
```

Then:

```text
Available = 10
```

Expected:

```text
low_stock_notified_at = null
```

Then:

```text
Available = 4
```

Expected:

```text
new notification sent
new low_stock_notified_at
```

---

# 10. CENTRALIZE LOW-STOCK EVALUATION

Do NOT implement separate rules in:

```text
CheckoutService
InventoryService
ReturnService
AdminInventoryController
SellerProductController
```

Preferred:

```text
InventoryService
    ↓
evaluateLowStockState(...)
```

All inventory-changing workflows should pass through one consistent evaluation.

---

# 11. INVENTORY CHANGE PATHS

Inspect and verify low-stock evaluation after:

```text
manual stock increase

manual stock decrease

checkout reservation

reservation expiry

reservation release

order cancellation

order fulfillment deduction

return restock

refund restock if applicable

product edit stock change

admin inventory correction

seller inventory correction
```

Do not fix only one path.

---

# 12. RESERVATION CREATION

Example:

```text
Available:
8

Customer reserves:
4

New available:
4

Threshold:
5
```

Expected:

```text
low-stock episode starts
notification sent once
```

if reservation-aware low stock is the actual intended rule.

---

# 13. RESERVATION RELEASE

Critical current problem may occur here.

Example:

```text
Available:
4

low stock notification already sent
```

Reservation expires/releases:

```text
available becomes:
10
```

Expected:

```text
low-stock marker reset
```

This recovery path must be tested.

---

# 14. ORDER CANCELLATION

Example:

```text
reserved stock made available = 3
```

Cancellation restores stock.

If inventory moves:

```text
4
→ 7
```

reset low-stock notification state.

---

# 15. RETURN RESTOCK

Example:

```text
available = 3
threshold = 5
```

Customer return restocks:

```text
+5
→ available = 8
```

Expected:

```text
stock recovered
notification marker reset
```

Preserve current return/inventory accounting rules.

---

# 16. MANUAL STOCK ADJUSTMENT

Seller:

```text
4
→ 20
```

Expected:

```text
low-stock state clears
```

Then seller decreases:

```text
20
→ 5
```

Expected:

```text
new notification episode
```

---

# 17. DO NOT RESET WHILE STILL LOW

Example:

```text
Threshold = 5

4
→ 5
```

If rule is:

```text
<= threshold = LOW
```

then do NOT reset.

Only reset after crossing back into the normal state.

Example:

```text
5
→ 6
```

then reset.

---

# 18. HYSTERESIS — DO NOT ADD UNLESS NEEDED

A possible advanced design is:

```text
notify <= 5
reset >= 7
```

to avoid notification flapping.

Do NOT introduce this unless current requirements explicitly need it.

For F11, use the existing threshold semantics.

---

# 19. OUT-OF-STOCK

If:

```text
stock = 0
```

determine whether the system sends:

```text
Low Stock
```

or a separate:

```text
Out of Stock
```

notification.

Do not duplicate alerts unnecessarily.

Example undesirable behavior:

```text
5
→ low stock alert

0
→ another low stock alert
```

in the same episode.

Use existing notification architecture.

---

# 20. LOW STOCK VS OUT OF STOCK FILTERS

The audit separately notes admin low-stock/out-of-stock filters overlap.

That is not the primary F11 task.

Do not redesign those filters unless required to make notification semantics correct.

Document related behavior if discovered.

---

# 21. RECIPIENT POLICY

Current audit says automatic low-stock notifications currently target super admins.

Decide whether active shop users should also receive alerts.

Recommended marketplace behavior:

```text
Shop Owner
→ YES

Authorized Shop Manager / Inventory Manager
→ YES if role/permission supports inventory

Super Admin
→ optional / platform setting
```

Do NOT send Shop A inventory alerts to Shop B users.

---

# 22. SHOP ISOLATION

Critical rule:

```text
Inventory belongs to Shop A

Notifications:
Shop A authorized recipients only
```

Never:

```text
Shop B owner
→ receives Shop A low-stock alert
```

Test explicitly.

---

# 23. SUPER ADMIN RECIPIENT

Determine product decision.

Possible policy:

```text
Seller receives operational alert
Super Admin optionally receives platform alert
```

or:

```text
Seller only
```

Do not automatically remove existing super-admin alerts unless requirements support it.

Document final recipient policy.

---

# 24. NOTIFICATION PREFERENCES

Inspect current preferences.

Possible:

```text
low_stock_notifications
email_notifications
database_notifications
```

Respect user preferences where applicable.

Do not send disabled email notifications.

Critical operational in-app notifications may follow different existing rules.

Use current architecture.

---

# 25. DATABASE NOTIFICATION

If Laravel database notifications are used:

store safe useful context:

```text
shop_id
product_id
variant_id
product_name
sku
available_stock
threshold
```

Avoid unnecessary private financial/customer data.

---

# 26. EMAIL NOTIFICATION

If LowStockNotification supports email:

verify:

```text
recipient
subject
product
stock
shop
```

is correct.

Do not require real SMTP acceptance to finish F11 if O16 still tracks production email validation.

Automated rendering/queue tests are enough here.

---

# 27. DUPLICATE NOTIFICATION PREVENTION

Important.

Multiple operations could detect low stock nearly simultaneously.

Example:

```text
Request A:
available 6 → 5

Request B:
available 5 → 4
```

Both must not send duplicate alerts for same episode.

Use safe state/locking.

---

# 28. TRANSACTION SAFETY

Inventory mutation and low-stock-state decision should use existing transaction/locking patterns.

Concept:

```text
BEGIN

lock inventory

apply inventory change

calculate available

evaluate transition

update low-stock marker

COMMIT
```

Then dispatch notification safely according to existing architecture.

Do not introduce race-prone:

```text
read
→ notify
→ update marker
```

without protection.

---

# 29. NOTIFICATION DISPATCH TIMING

Avoid sending external notification before DB transaction successfully commits if failure could roll back inventory.

Preferred pattern:

```text
transaction commits
→ dispatch notification
```

or queue after commit.

Use Laravel's existing patterns if available.

---

# 30. QUEUED NOTIFICATIONS

If notification is queued:

ensure:

```text
marker update
```

does not depend on successful mail delivery.

The low-stock episode should not generate 50 duplicate jobs because one email provider is down.

Separate:

```text
business event occurred
```

from:

```text
delivery channel succeeded
```

---

# 31. DELIVERY FAILURE

If email fails but database notification succeeds:

do not necessarily reset:

```text
low_stock_notified_at
```

and resend indefinitely.

Use existing notification retry architecture.

Do not turn F11 into full incident management.

---

# 32. EXACTLY-ONCE VS AT-LEAST-ONCE

True exactly-once external delivery is difficult.

Primary guarantee should be:

```text
one low-stock business notification event per low-stock episode
```

with existing queue retry behavior for delivery.

---

# 33. MARKER MEANING

Document precisely what:

```text
low_stock_notified_at
```

means.

Recommended:

```text
timestamp when current low-stock episode was first detected/notified
```

Then:

```text
null
```

means:

```text
inventory currently eligible for next low-stock alert
```

Do not use it as permanent "ever notified" history.

---

# 34. OPTIONAL LOW-STOCK HISTORY

Do not add a separate history table unless required.

Laravel notifications may already provide event history.

F11 only requires lifecycle correctness.

---

# 35. THRESHOLD CHANGES

Important scenario.

Current:

```text
stock = 8
threshold = 5
normal
```

Admin changes threshold:

```text
threshold = 10
```

Now stock is low.

Determine whether this should trigger a notification.

Recommended:

```text
yes
```

if threshold configuration change invokes evaluation.

But inspect architecture.

If not supported, document limitation.

---

# 36. THRESHOLD LOWERED

Example:

```text
stock = 4
threshold = 5
low + already notified
```

Threshold changes:

```text
5 → 3
```

Now stock is normal.

Expected:

```text
reset low-stock marker
```

if threshold changes are evaluated immediately.

---

# 37. GLOBAL VS SHOP THRESHOLD

Inspect whether threshold is:

```text
platform global
shop specific
product specific
variant specific
```

Use actual configuration.

Do not invent multiple threshold layers.

---

# 38. VARIANT-LEVEL INVENTORY

Products can have variants.

Ensure alert identifies exact variant.

Example:

```text
Product:
T-Shirt

Variant:
Black / XL

SKU:
TS-BLK-XL

Available:
3
```

Do not send vague:

```text
"T-Shirt is low stock"
```

if other variants have plenty of stock and variant details are available.

---

# 39. NON-VARIANT PRODUCTS

Also support simple products according to current inventory model.

Do not assume every inventory row has variant labels.

---

# 40. DELETED PRODUCT

If product/variant is deleted:

do not send new low-stock alerts for intentionally removed merchandise.

Use current active/inactive visibility rules where appropriate.

---

# 41. INACTIVE PRODUCT

Determine whether hidden/draft products should generate low-stock alerts.

Recommended:

```text
only inventory actively managed/sellable
```

but use existing business requirements.

Document final decision.

---

# 42. SUSPENDED SHOP

A suspended shop may not need operational low-stock alerts.

Inspect current marketplace behavior.

Do not send alerts to inactive memberships.

---

# 43. RECIPIENT DEDUPLICATION

If user is both:

```text
Shop Owner
+
Super Admin
```

do not send unnecessary duplicate identical notifications unless channels intentionally differ.

Use existing notification recipient collection conventions.

---

# 44. MESSAGE CONTENT

Recommended in-app message:

```text
Low stock: Aurora Wireless Headphones

SKU: DEMO-001-01
Available: 4
Threshold: 5
```

Include action:

```text
View inventory
```

if current notification UI supports links.

---

# 45. DO NOT EXPOSE PRIVATE COST

Low-stock notification does not need:

```text
purchase cost
profit
supplier secrets
customer information
```

unless specifically required.

---

# 46. SELLER DASHBOARD

Seller dashboard already shows low-stock counts.

After inventory changes:

```text
low-stock count
```

should reflect current available inventory.

Do not let notification marker control whether item appears in low-stock list.

These are different:

```text
Is low stock?
```

vs:

```text
Has current episode been notified?
```

---

# 47. IMPORTANT DISTINCTION

Do NOT implement:

```text
if low_stock_notified_at != null
    product is low stock
```

Wrong.

Low-stock status must come from inventory quantity/threshold.

Marker only controls alert lifecycle.

---

# 48. SELLER INVENTORY UI

If stock recovers:

```text
Low Stock
```

badge should disappear according to actual quantity.

If stock falls again:

badge returns.

Notification logic should not alter the visual stock calculation.

---

# 49. ADMIN INVENTORY UI

Admin should see actual quantity/status independently of alert state.

No stale marker should cause wrong filter classification.

---

# 50. CACHE INVALIDATION

If inventory/dashboard stats are cached:

inventory changes should update/invalidate relevant cache.

Do not send a correct alert while dashboard still shows stale stock for long periods.

Inspect existing caching first.

---

# 51. INVENTORY LEDGER

Do not change ledger semantics.

All stock changes should continue creating existing audit/ledger entries.

F11 adds/fixes notification evaluation, not accounting.

---

# 52. RESERVATION LEDGER

Preserve reservation accounting:

```text
reserved
released
deducted
restocked
```

Do not change quantities merely to fix notifications.

---

# 53. AUTOMATED UNIT TESTS

Add focused tests for low-stock evaluation.

## Test 1 — Normal stock

```text
threshold = 5
available = 10

Expected:
no notification
marker null
```

---

## Test 2 — Cross into low stock

```text
10 → 5
```

Expected:

```text
one notification
marker set
```

---

## Test 3 — Decrease further while low

```text
5 → 4 → 3
```

Expected:

```text
no additional low-stock episode notification
```

---

## Test 4 — Recovery

```text
3 → 10
```

Expected:

```text
marker reset
```

---

## Test 5 — Low again

```text
10 → 4
```

Expected:

```text
second notification
new marker
```

This is the critical F11 test.

---

# 54. RESERVATION TEST

Example:

```text
available 10
reserve 6
→ available 4
```

Expected:

```text
low-stock alert
```

if low stock is reservation-aware.

---

# 55. RESERVATION RELEASE TEST

```text
available 4
release reservation
→ available 10
```

Expected:

```text
marker reset
```

This directly covers the reproduced audit issue.

---

# 56. CANCELLATION TEST

```text
low stock due to reservation
order cancelled
stock released above threshold
```

Expected:

```text
notification state rearmed
```

---

# 57. RETURN RESTOCK TEST

```text
available 3
return +5
→ available 8
```

Expected:

```text
marker reset
```

---

# 58. MANUAL ADJUSTMENT TEST

```text
4 → 20
```

Expected:

```text
marker reset
```

Then:

```text
20 → 5
```

Expected:

```text
new alert
```

---

# 59. THRESHOLD BOUNDARY TEST

If rule is:

```text
<= threshold
```

test:

```text
6 → 5
```

triggers.

And:

```text
5 → 6
```

resets.

Document actual rule.

---

# 60. OUT-OF-STOCK TEST

```text
5 → 0
```

Ensure no duplicate low-stock alert in same episode unless separate out-of-stock event is explicitly designed.

---

# 61. WRONG SHOP RECIPIENT TEST

Setup:

```text
Shop A inventory low
Shop A owner
Shop B owner
```

Expected:

```text
Shop A owner:
receives alert if policy says seller receives

Shop B owner:
does NOT receive
```

---

# 62. INACTIVE MEMBERSHIP TEST

Former/suspended shop manager should not receive operational alert if membership is inactive.

Use existing membership rules.

---

# 63. SUPER ADMIN TEST

Verify final chosen policy:

```text
super admin receives / does not receive
```

according to documented decision.

---

# 64. PREFERENCE TEST

If seller disables low-stock email:

```text
database notification:
according to policy

email:
not sent
```

Adapt to actual preference system.

---

# 65. DUPLICATE EVENT TEST

Call low-stock evaluation twice without stock recovery.

Expected:

```text
one notification only
```

---

# 66. CONCURRENCY TEST

Simulate where practical:

```text
Request A reduces stock to 5
Request B reduces stock to 4
```

Expected:

```text
one low-stock episode notification
```

Use actual MySQL locking if possible.

If only SQLite tests were run:

report:

```text
MySQL concurrency:
NOT VERIFIED
```

Do not overclaim.

---

# 67. QUEUE TEST

If notification is queued:

verify job dispatch occurs once.

Do not require live email provider.

---

# 68. NOTIFICATION PAYLOAD TEST

Verify notification contains correct:

```text
shop
product
variant/SKU
available quantity
threshold
```

and no inappropriate unrelated private data.

---

# 69. FRONTEND NOTIFICATION TEST

If seller notification inbox displays the event:

verify:

```text
title
message
link
read/unread state
```

works.

Do not redesign entire notification center.

---

# 70. SELLER DASHBOARD E2E

Fixture:

```text
Variant stock = 4
threshold = 5
```

Seller opens dashboard/inventory.

Expected:

```text
Low Stock count includes item
```

After restock:

```text
4 → 10
```

Expected:

```text
low-stock count no longer includes item
```

---

# 71. E2E LOW AGAIN

After recovery:

```text
10 → 4
```

verify new notification appears.

This is the main full-lifecycle acceptance flow.

---

# 72. F01 REGRESSION

Coupon isolation unaffected.

---

# 73. F02 REGRESSION

Cart recovery unaffected.

---

# 74. F03 REGRESSION

Financial totals unaffected.

---

# 75. F04 REGRESSION

COD lifecycle unaffected.

---

# 76. F05 REGRESSION

Guest/account checkout behavior unaffected.

---

# 77. F06 REGRESSION

Rating/price filters unaffected.

---

# 78. F07 REGRESSION

Mobile color filters unaffected.

---

# 79. F08 REGRESSION

Async cart mutation handling unaffected.

---

# 80. F09 REGRESSION

Safe formatted descriptions unaffected.

---

# 81. F10 REGRESSION

Review aggregate maintenance unaffected.

---

# 82. BACKEND FULL TESTS

Run focused:

```text
InventoryService tests
reservation tests
notification tests
order cancellation tests
return/restock tests
```

Then run broader relevant backend regression.

Do not mark F11 complete if inventory accounting tests fail.

---

# 83. FRONTEND TYPE CHECK

Run existing frontend type check.

Do not add:

```text
any
@ts-ignore
```

just to bypass notification/inventory API typing.

---

# 84. PRODUCTION BUILD

Run frontend production build.

Must pass.

---

# 85. DOCKER ONLY

Use project Docker containers for:

```text
PHP
Composer
Node
tests
build
```

according to existing repository conventions.

Do not wipe development data.

---

# 86. DO NOT START F12

Only solve F11.

Do not begin duplicate coupon validation.

That is:

```text
F12 — Duplicate Coupon Validation
```

---

# 87. DEFINITION OF DONE

Do not mark F11 complete until:

```text
[ ] Low-stock threshold rule is explicitly defined

[ ] Low stock uses correct available/on-hand source

[ ] First transition into low stock sends one alert

[ ] Remaining low does not spam alerts

[ ] Recovery above threshold resets alert eligibility

[ ] Low stock after recovery sends a new alert

[ ] Reservation release can reset marker

[ ] Cancellation stock restoration can reset marker

[ ] Return restock can reset marker

[ ] Manual stock increase can reset marker

[ ] Manual decrease can trigger a new episode

[ ] Out-of-stock behavior is defined

[ ] Seller recipient policy is defined

[ ] Shop isolation is enforced

[ ] Inactive memberships do not receive alerts

[ ] Super-admin behavior is defined

[ ] Notification preferences are respected

[ ] Duplicate/concurrent event protection exists

[ ] Inventory ledger behavior is unchanged

[ ] Seller dashboard low-stock status remains quantity-based

[ ] Backend tests pass

[ ] Notification tests pass

[ ] Browser/E2E regression passes where applicable

[ ] Type check passes

[ ] Production build passes

[ ] F01–F10 regressions pass
```

---

# 88. FINAL REPORT

At completion report exactly:

```text
F11 — LOW-STOCK NOTIFICATION LIFECYCLE

Status:
PASS / NEEDS WORK

Root Cause:

Low-Stock Source:
ON-HAND / AVAILABLE / OTHER

Threshold Rule:

Old Lifecycle:

New Lifecycle:

Low-Stock Episode Definition:

Recovery Rule:

Marker Used:

Marker Reset Paths:
List

Reservation Behavior:

Cancellation Behavior:

Return Restock Behavior:

Manual Adjustment Behavior:

Out-of-Stock Behavior:

Recipient Policy:

Shop Owner:
YES / NO

Shop Manager:
YES / NO / PERMISSION-BASED

Super Admin:
YES / NO / CONFIGURABLE

Notification Preferences:
PASS / FAIL / N/A

Shop Isolation:
PASS / FAIL

Duplicate Protection:

Concurrency Strategy:

MySQL Concurrency:
PASS / NOT VERIFIED / FAIL

Files Changed:

Schema Changes:
None / List

Tests Added:

Backend Tests:
Passed / Failed

Notification Tests:
Passed / Failed

Browser/E2E Tests:
Passed / Failed / N/A

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

Remaining Risks:

Related Follow-Up:
F12 — Duplicate Coupon Validation
```

Also provide this before/after example:

```text
BEFORE

Threshold:
5

Stock:
4

Low-stock alert sent
low_stock_notified_at = set

Stock recovers:
4 → 10

low_stock_notified_at:
still set

Stock drops:
10 → 4

Result:
No new alert


AFTER

Threshold:
5

Stock:
10 → 4

Alert #1 sent

Stock:
4 → 10

Low-stock episode ends
notification marker resets

Stock:
10 → 4

Alert #2 sent

Result:
One alert for each genuine low-stock episode
```

Stop after F11.

Do NOT automatically begin F12.

I will review the result first.