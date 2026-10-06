# E-KHMER — Inventory, Shipping, Shipments & Notifications Full Audit

Act as a senior **Laravel + Vue.js + MySQL E-Commerce Inventory & Logistics Engineer**.

I have an existing E-Commerce application called **E-KHMER**.

Tech stack:

- Laravel
- Vue.js
- MySQL
- REST API
- Docker
- Git/GitHub

Your responsibility is to fully inspect, test, fix, complete, integrate, secure, and improve:

1. Inventory
2. Inventory Transactions
3. Shipping / Shipping Methods
4. Shipments
5. Notifications

These features must integrate correctly with:

- Products
- Product Variants
- Orders
- Order Items
- Order Status
- Payments
- Checkout
- Users
- Addresses

Do NOT treat these modules as independent CRUD features.

The expected business flow is:

```text
Product / Variant
       ↓
   Inventory
       ↓
Customer Checkout
       ↓
     Order
       ↓
Payment / Confirmation
       ↓
Inventory Deduction
       ↓
Inventory Transaction
       ↓
    Shipment
       ↓
Shipment Status
       ↓
Customer Notification
       ↓
    Delivered
```

The goal is:

**Inventory Accuracy → Data Integrity → Reliable Shipping → Correct Notifications → Security → UX → Performance**

Do NOT only analyze or generate a report.

Actually:

**INSPECT → AUDIT → FIND BUGS → COMPLETE → FIX → IMPROVE → TEST → VERIFY**

---

# 1. INSPECT THE EXISTING PROJECT

Before changing code, inspect:

```text
Models
Migrations
Controllers
Services
Repositories
Policies
Middleware
Form Requests
API Resources
Routes
Events
Listeners
Jobs
Queues
Notifications

Vue Pages
Vue Components
State Management
API Services
Admin Pages
Customer Pages

Database Relationships
```

Also inspect:

```text
Laravel Logs
Browser Console
Network Requests
Database Errors
Docker Logs
Queue Worker
```

Do NOT create duplicate features.

Do NOT rewrite working modules without a reason.

---

# 2. FEATURE AUDIT

Audit:

```text
Inventory
Inventory Transactions
Shipping Methods
Shipments
Shipment Status
Notifications
```

Classify each feature:

```text
COMPLETE
PARTIAL
BROKEN
MISSING
NEEDS IMPROVEMENT
```

Priority:

```text
CRITICAL
HIGH
MEDIUM
LOW
```

Do not mark anything COMPLETE until it has actually been tested.

---

# 3. DATABASE RELATIONSHIPS

Inspect and verify relationships similar to:

```text
Product
   ↓
Variant
   ↓
Inventory
   ↓
Inventory Transactions
```

Orders:

```text
Order
   ↓
Order Items
   ↓
Product / Variant
   ↓
Inventory
```

Shipping:

```text
Order
   ↓
Shipment
   ↓
Shipping Method
```

Notifications:

```text
User
 ↓
Notifications
```

and events such as:

```text
Order
 ↓
Shipment Status Changed
 ↓
Notification
```

Adapt everything to the existing database.

Do NOT duplicate existing relationships.

---

# 4. INVENTORY — SINGLE SOURCE OF TRUTH

Identify where stock is currently stored.

Check whether stock exists in:

```text
products
product_variants
inventories
```

Avoid conflicting stock values.

Example of a bad design:

```text
products.stock = 20

product_variants.stock = 15

inventories.quantity = 18
```

Which one is correct?

Determine the correct **single source of truth** based on the existing architecture.

Fix inconsistencies safely.

---

# 5. SIMPLE PRODUCT INVENTORY

If simple products are supported:

```text
Product
   ↓
Inventory
```

Example:

```text
USB Cable

Available Stock: 100
```

Verify:

- stock retrieval
- stock update
- cart validation
- checkout validation
- order deduction
- cancellation restoration

---

# 6. VARIANT INVENTORY

Variant products must track inventory correctly.

Example:

```text
T-Shirt

Black / S → 10
Black / M → 5
Black / L → 2

White / S → 8
White / M → 4
White / L → 0
```

Do NOT use only total product stock when the customer purchases a specific variant.

Inventory must reference the correct variant.

---

# 7. INVENTORY MANAGEMENT

Admin should be able to:

```text
View Inventory
Search Inventory
Filter Inventory
View Product/Variant
View Current Stock
Adjust Stock
View Low Stock
View Out of Stock
View Transaction History
```

If business requirements support warehouses/shops, respect that architecture.

Do not introduce warehouses unnecessarily if the project does not use them.

---

# 8. STOCK ADJUSTMENT

Admin should not simply overwrite stock without traceability.

Bad:

```text
Stock = 20

Admin changes:

Stock = 50
```

with no record.

Better:

```text
Current: 20

Adjustment:
+30

New Stock:
50
```

Create an inventory transaction.

---

# 9. INVENTORY TRANSACTIONS

Every important stock movement should be traceable.

Possible transaction types:

```text
STOCK_IN
SALE
RETURN
ADJUSTMENT
CANCELLATION
RESTOCK
RESERVATION
RELEASE
```

Only use types appropriate to the project.

Possible transaction fields:

```text
id
inventory_id
product_id
variant_id
type
quantity
quantity_before
quantity_after
reference_type
reference_id
note
created_by
created_at
```

Adapt this to the current schema.

---

# 10. INVENTORY TRANSACTION RULE

Every stock-changing operation should answer:

```text
Why did stock change?
How much changed?
What was stock before?
What is stock after?
Who/what caused it?
When did it happen?
```

Example:

```text
Product: T-Shirt Black / M

Before: 10
Change: -2
After: 8

Reason: SALE
Order: #EK-00125
```

---

# 11. DO NOT EDIT TRANSACTION HISTORY CARELESSLY

Inventory transactions are audit records.

Do not normally allow users/admins to arbitrarily edit historical transactions.

If a mistake occurs, prefer a corrective transaction.

Example:

```text
Wrong Adjustment:
+20

Correction:
-20
```

instead of silently rewriting history.

---

# 12. INVENTORY CALCULATION

Ensure:

```text
Available Stock >= 0
```

unless negative inventory/backorders are intentionally supported.

Never allow accidental negative stock.

Reject invalid values such as:

```text
-100
abc
null
invalid decimal quantity
```

according to the product quantity rules.

---

# 13. CHECKOUT INVENTORY VALIDATION

Stock must be checked again immediately before order creation.

Example:

```text
Customer adds:

Product A × 5

Stock = 5
```

Later:

```text
Stock becomes 3.
```

Checkout must reject quantity 5.

Do not trust old cart stock information.

---

# 14. CONCURRENT CHECKOUT

Critical test:

```text
Stock = 1

Customer A → Checkout
Customer B → Checkout
```

Only one customer should successfully purchase the item.

Prevent overselling using an appropriate database concurrency strategy.

Consider:

```text
Database transaction
Row locking
Atomic update
```

based on the existing architecture.

Do not rely only on frontend validation.

---

# 15. STOCK DEDUCTION

Determine exactly when stock should be deducted.

Possible business rules:

```text
When order is created
```

or:

```text
When payment succeeds
```

or:

```text
Reserve at order creation
Deduct after confirmation
```

Inspect the existing business logic and choose one consistent strategy.

Do NOT deduct inventory from multiple places.

---

# 16. PREVENT DOUBLE STOCK DEDUCTION

Critical:

If the same payment callback or order event runs twice:

```text
Stock = 10
Order Quantity = 2
```

Correct:

```text
Stock = 8
```

Incorrect:

```text
10 → 8 → 6
```

Make stock-changing operations idempotent where necessary.

---

# 17. ORDER CANCELLATION

When an order is cancelled:

Determine whether inventory needs to be:

```text
Released
Restored
No action
```

depending on when inventory was reserved/deducted.

Example:

```text
Stock = 10

Order Qty = 2

Stock after purchase = 8

Order Cancelled

Stock = 10
```

if restoration is appropriate.

Create an inventory transaction for restoration.

---

# 18. ORDER RETURN / REFUND

If returns are supported, determine:

```text
Was item physically returned?
Is it resellable?
Should inventory increase?
```

Do not automatically increase stock simply because a refund occurred.

Payment refund and physical inventory return are different business events.

---

# 19. LOW STOCK

Support useful low-stock logic.

Example:

```text
Current Stock: 5
Low Stock Threshold: 10

Status:
LOW STOCK
```

Allow appropriate configuration if already supported.

Do not hard-code one threshold everywhere.

---

# 20. OUT OF STOCK

When:

```text
stock = 0
```

Customer-facing pages should display:

```text
Out of Stock
```

and prevent purchasing unless backorders are intentionally supported.

Variant stock must be respected.

---

# 21. INVENTORY STATUS

Useful derived states may include:

```text
IN STOCK
LOW STOCK
OUT OF STOCK
```

Prefer deriving these from quantity/threshold rather than storing duplicate state if possible.

Avoid conflicting data such as:

```text
quantity = 0
status = IN_STOCK
```

---

# 22. INVENTORY ADMIN UI

Admin inventory page should clearly display useful data such as:

```text
Product
Variant
SKU
Available Stock
Low Stock Threshold
Stock Status
Last Updated
Actions
```

Actions:

```text
View History
Adjust Stock
```

Avoid overcrowding the table.

---

# 23. INVENTORY SEARCH & FILTER

Support useful search/filtering:

```text
Product Name
SKU
Variant
In Stock
Low Stock
Out of Stock
```

Use server-side search/filter/pagination.

Do NOT load all inventory records into Vue for large datasets.

---

# 24. INVENTORY PERFORMANCE

Inspect:

```text
N+1 queries
Repeated stock queries
Unnecessary relationships
Duplicate API calls
Missing indexes
Large API payloads
```

Optimize actual bottlenecks.

---

# 25. SHIPPING METHODS

Audit shipping method management.

Admin should be able to:

```text
Create Shipping Method
View Shipping Methods
Edit Shipping Method
Activate/Deactivate
Delete/Archive Safely
```

Potential fields:

```text
name
description
cost
estimated_days
status
```

Adapt to the existing schema.

---

# 26. SHIPPING COST

Shipping cost must be calculated/validated on the backend.

Never trust:

```json
{
    "shipping_cost": 0.01
}
```

from the frontend.

Backend must load the selected shipping method and determine the valid cost.

---

# 27. SHIPPING METHOD VALIDATION

At checkout verify:

```text
Shipping method exists
Shipping method is active
Shipping method is available
Shipping cost is current
```

Do not allow customers to use inactive shipping methods through manually modified requests.

---

# 28. SHIPPING BUSINESS RULES

If supported, inspect:

```text
Free Shipping
Flat Rate
Location-based Shipping
Order-total-based Shipping
```

Do not introduce complicated shipping logic unless the project needs it.

Keep the rules centralized.

---

# 29. FREE SHIPPING

If free shipping exists, define the rule clearly.

Example:

```text
Order >= $100
→ Free Shipping
```

Do not calculate this differently between:

```text
Cart
Checkout
Order
Payment
```

Use one consistent business rule.

---

# 30. SHIPMENTS

Audit:

```text
Shipment Creation
Shipment Details
Shipping Method
Tracking Number
Carrier
Shipment Status
Shipped Date
Delivered Date
Notes
```

Use only fields supported by the project's requirements.

---

# 31. SHIPMENT CREATION

Shipment should normally be linked to a valid order.

Example:

```text
Order Confirmed/Paid
       ↓
Prepare Order
       ↓
Create Shipment
       ↓
Add Tracking
       ↓
Mark Shipped
```

Do not allow shipment creation for invalid/cancelled orders unless business requirements explicitly allow it.

---

# 32. SHIPMENT STATUS

Possible statuses:

```text
Pending
Preparing
Shipped
In Transit
Out for Delivery
Delivered
Failed Delivery
Returned
Cancelled
```

Do not add unnecessary statuses if the existing system uses a simpler model.

---

# 33. SHIPMENT STATUS TRANSITIONS

Prevent illogical transitions.

Bad:

```text
Delivered
 ↓
Preparing
```

or:

```text
Cancelled
 ↓
Out for Delivery
```

Centralize valid transition rules.

---

# 34. ORDER STATUS + SHIPMENT STATUS

Keep these concepts separate.

Example:

```text
Order Status:
Processing

Shipment Status:
Preparing
```

Later:

```text
Order Status:
Shipped

Shipment Status:
In Transit
```

Ensure synchronization where business rules require it.

Do not create circular update loops.

---

# 35. TRACKING NUMBER

If tracking is supported:

```text
Tracking Number
Carrier
Tracking URL if appropriate
```

should be available to the customer.

Tracking numbers should not be duplicated accidentally where uniqueness is expected.

---

# 36. CUSTOMER ORDER TRACKING

Customer should be able to see:

```text
Order Number
Order Status
Shipping Method
Shipment Status
Tracking Number
Shipped Date
Estimated Delivery
Delivered Date
```

Only display information actually available.

Do not create fake estimated delivery information.

---

# 37. SHIPPING ADDRESS SNAPSHOT

Critical:

The shipment/order should preserve the shipping address used when the order was placed.

Example:

Customer orders to:

```text
Address A
```

Later customer changes their saved profile address to:

```text
Address B
```

The old order/shipment must still show:

```text
Address A
```

Do not make historical shipment addresses depend completely on the customer's current saved address.

---

# 38. NOTIFICATIONS

Audit:

```text
Notification Creation
Notification Delivery
Notification List
Read/Unread
Mark as Read
Mark All as Read
Delete/Archive if supported
```

Check both:

```text
Customer Notifications
Admin Notifications
```

according to project requirements.

---

# 39. NOTIFICATION EVENTS

Potential customer events:

```text
Order Placed
Payment Successful
Payment Failed
Order Confirmed
Order Processing
Order Shipped
Shipment In Transit
Out for Delivery
Order Delivered
Order Cancelled
Refund Completed
```

Potential admin events:

```text
New Order
Payment Received
Payment Failed
Low Stock
Out of Stock
Order Cancelled
```

Only implement useful notifications.

Do not spam users with unnecessary messages.

---

# 40. LOW STOCK NOTIFICATION

Example:

```text
Product:
T-Shirt Black / M

Stock:
3

Threshold:
5

→ Low Stock Notification
```

Prevent duplicate notification spam.

Do not send:

```text
Low Stock
Low Stock
Low Stock
Low Stock
```

for every page request.

Notifications should come from meaningful stock-changing events.

---

# 41. OUT-OF-STOCK NOTIFICATION

When stock changes:

```text
1 → 0
```

consider triggering:

```text
Product is now out of stock.
```

for appropriate admin users.

Do not repeatedly create the same notification without reason.

---

# 42. SHIPPING NOTIFICATIONS

Example:

```text
Shipment Created
       ↓
Order Shipped
       ↓
Customer Notification
```

Message:

```text
Your order #EK-00125 has been shipped.
```

If tracking exists:

```text
Tracking Number: XXXXX
```

Never expose internal/private information unnecessarily.

---

# 43. NOTIFICATION ARCHITECTURE

Inspect whether the project uses:

```text
Laravel Notifications
Events / Listeners
Jobs / Queues
Database Notifications
Email
Telegram
```

Use the existing architecture where possible.

Do not create duplicate notification systems.

---

# 44. QUEUES

Long-running notification tasks should not unnecessarily delay requests.

Consider queues for:

```text
Email
Telegram
External APIs
Bulk Notifications
```

if queue infrastructure already exists or clearly benefits the project.

Do not introduce unnecessary complexity.

---

# 45. NOTIFICATION DUPLICATION

Critical:

Repeated events must not create incorrect duplicate notifications.

Example:

```text
Payment webhook called twice.
```

Should not cause:

```text
Order Shipped
Order Shipped
```

or duplicate inventory changes.

Implement idempotency where required.

---

# 46. NOTIFICATION OWNERSHIP

Customer A must not:

```text
Read Customer B's notifications
Mark Customer B's notification as read
Delete Customer B's notification
```

Backend ownership checks are mandatory.

---

# 47. READ / UNREAD

Notification UI should clearly support:

```text
Unread
Read
```

Potential API actions:

```text
Mark One Read
Mark All Read
```

Unread count should remain accurate.

Avoid repeatedly requesting the entire notification dataset just to get the unread count.

---

# 48. REAL-TIME NOTIFICATIONS

If Laravel Reverb/WebSockets already exist:

Inspect whether real-time notifications can use the existing infrastructure.

Do not add another real-time system unnecessarily.

Make sure fallback behavior still works if the real-time connection fails.

---

# 49. API SECURITY

Audit:

```text
Inventory adjustment authorization
Shipment management authorization
Shipping method authorization
Notification ownership
Mass assignment
IDOR
Input validation
Stock manipulation
Shipping-cost manipulation
Status manipulation
```

Normal customers must NOT be able to call an API and perform:

```json
{
    "stock": 999999
}
```

or:

```json
{
    "shipping_cost": 0
}
```

or:

```json
{
    "shipment_status": "delivered"
}
```

unless explicitly authorized.

---

# 50. ROLE PERMISSIONS

Customer:

```text
View own shipment
Track own order
View own notifications
Mark own notifications as read
```

Customer must NOT:

```text
Adjust inventory
Create shipping methods
Change shipping price
Create arbitrary shipments
Change shipment status
Access other customers' shipments
Access other customers' notifications
```

Admin permissions must be enforced by Laravel, not only Vue.

---

# 51. DATABASE TRANSACTIONS

Use transactions for operations that must succeed together.

Example:

```text
BEGIN TRANSACTION

Validate Order
Validate Inventory

Deduct Inventory
Create Inventory Transaction
Update Order
Create Shipment if appropriate

COMMIT
```

If failure:

```text
ROLLBACK
```

Never allow:

```text
Stock deducted
BUT
Inventory transaction missing
```

or:

```text
Shipment updated
BUT
Order remains incorrectly synchronized
```

---

# 52. IDEMPOTENCY

Audit operations that may be triggered multiple times:

```text
Payment callbacks
Order confirmation
Stock deduction
Stock restoration
Shipment update
Notification creation
```

Running the same event twice must not corrupt data.

---

# 53. CONCURRENCY

Test:

```text
Two customers buying final stock
Two admins adjusting same stock
Repeated payment callback
Repeated shipment status request
```

Protect critical stock operations from race conditions.

---

# 54. DATABASE INDEXES

Inspect frequently queried columns such as:

```text
product_id
variant_id
inventory_id
order_id
user_id
shipment_id
shipping_method_id
status
type
tracking_number
created_at
read_at
```

Add indexes only when justified.

Do not add unnecessary indexes everywhere.

---

# 55. API RESPONSE QUALITY

Inventory list should not return every inventory transaction.

Shipment list should not return unnecessary full order trees.

Notification list should not return unnecessary user data.

Use:

```text
API Resources
Pagination
Required relationships only
```

Keep API payloads efficient.

---

# 56. PAGINATION

Use server-side pagination for:

```text
Inventory
Inventory Transactions
Shipments
Notifications
```

Do not load thousands of records simultaneously.

---

# 57. SEARCH & FILTER

Inventory:

```text
Product
SKU
Variant
Stock Status
```

Transactions:

```text
Product
Transaction Type
Date
Reference
```

Shipments:

```text
Order Number
Tracking Number
Status
Customer
```

Notifications:

```text
Read
Unread
Type
Date
```

Perform large searches server-side.

---

# 58. ADMIN INVENTORY UX

Admin should quickly understand:

```text
What is in stock?
What is low?
What is out?
What changed recently?
Why did it change?
```

Make important information clear.

Avoid unnecessary clicks.

---

# 59. ADMIN SHIPPING UX

Shipment management should clearly display:

```text
Order
Customer
Shipping Method
Tracking Number
Shipment Status
Shipped Date
Delivery Date
Actions
```

Provide only valid actions for the current shipment status.

---

# 60. CUSTOMER UX

Customer should easily answer:

```text
Has my order shipped?
Where is it?
What is the tracking number?
When was it shipped?
Was it delivered?
```

Keep tracking information simple and understandable.

---

# 61. LOADING & ERROR UX

Provide states:

```text
Loading inventory...
Updating stock...
Creating shipment...
Updating shipment...
Loading notifications...
```

Prevent double submissions.

Do not show customers technical errors such as:

```text
SQLSTATE
AxiosError
Stack Trace
```

Show useful messages.

---

# 62. COMPLETE INVENTORY FLOW TEST

Test:

```text
Admin Creates Product
       ↓
Create Variant
       ↓
Add Inventory
       ↓
Inventory Transaction = STOCK_IN
       ↓
Customer Adds to Cart
       ↓
Checkout
       ↓
Order
       ↓
Stock Deduction
       ↓
Inventory Transaction = SALE
```

Verify every quantity.

---

# 63. COMPLETE CANCELLATION FLOW

Test:

```text
Order
 ↓
Stock Deducted
 ↓
Inventory Transaction
 ↓
Order Cancelled
 ↓
Stock Restored if applicable
 ↓
Restoration Transaction
 ↓
Notification
```

Ensure stock is restored only once.

---

# 64. COMPLETE SHIPPING FLOW

Test:

```text
Order Confirmed
       ↓
Prepare Order
       ↓
Create Shipment
       ↓
Add Tracking Number
       ↓
Mark Shipped
       ↓
Customer Notification
       ↓
In Transit
       ↓
Out for Delivery
       ↓
Delivered
       ↓
Order Updated
       ↓
Customer Notification
```

Check every transition.

---

# 65. FAILURE TESTING

Test:

```text
Inventory update fails
Transaction creation fails
Shipment creation fails
Notification fails
Queue fails
Database connection fails
Network request fails
```

Critical business operations must not leave corrupted data.

A notification failure should generally not corrupt a successfully completed order/shipment transaction.

Design boundaries carefully.

---

# 66. EDGE CASES

Test:

```text
Stock = 0
Stock = 1
Negative stock attempt
Quantity > stock
Product deleted
Variant deleted
Concurrent checkout
Duplicate stock deduction
Duplicate stock restoration
Invalid shipping method
Inactive shipping method
Invalid shipping price
Cancelled order shipment
Duplicate tracking number
Invalid shipment transition
Repeated shipment request
Notification duplication
Unauthorized notification access
Unauthorized inventory update
Unauthorized shipment update
```

Application must fail safely.

---

# 67. AUTOMATED TESTS

Create or improve tests for:

```text
Inventory CRUD
Inventory Adjustment
Inventory Transactions
Simple Product Stock
Variant Stock
Low Stock
Out of Stock
Order Stock Deduction
Cancellation Restoration
Concurrent Stock Protection

Shipping Method CRUD
Shipping Validation
Shipping Cost Validation

Shipment Creation
Shipment Status
Tracking
Invalid Status Transition
Shipment Ownership

Notification Creation
Read/Unread
Mark Read
Mark All Read
Notification Ownership
Duplicate Prevention
```

Critical tests:

```text
Stock cannot become accidentally negative.

Two customers cannot buy the same final unit.

Repeated payment event does not deduct stock twice.

Cancelled order does not restore stock twice.

Customer cannot manipulate inventory.

Customer cannot manipulate shipping price.

Customer cannot change shipment status.

Customer A cannot access Customer B's shipment.

Customer A cannot access Customer B's notifications.
```

---

# 68. CODE REVIEW

Review for:

```text
Correct Business Logic
Data Integrity
Transactions
Concurrency
Idempotency
Security
Authorization
Validation
Performance
Laravel Conventions
Vue Conventions
Maintainability
```

Look for:

```text
Huge controllers
Duplicate stock logic
Hard-coded shipping costs
Hard-coded statuses
Business logic inside Vue
Missing transactions
Repeated queries
Missing authorization
Missing validation
Duplicate notifications
```

Centralize important logic where appropriate.

Potential concepts:

```text
InventoryService
ShippingService
ShipmentService
NotificationService
```

Only introduce them if they improve the current architecture.

Do not overengineer.

---

# 69. DEFINITION OF DONE — INVENTORY

```text
[ ] Simple product inventory works
[ ] Variant inventory works
[ ] Stock adjustment works
[ ] Stock deduction works
[ ] Stock restoration works
[ ] Negative stock prevented
[ ] Concurrent purchase protected
[ ] Low stock works
[ ] Out-of-stock works
[ ] Search/filter works
[ ] Pagination works
```

---

# 70. DEFINITION OF DONE — INVENTORY TRANSACTIONS

```text
[ ] Stock-in recorded
[ ] Sale recorded
[ ] Adjustment recorded
[ ] Cancellation/restoration recorded
[ ] Before/after quantity correct
[ ] Reference works
[ ] History cannot be casually corrupted
[ ] Pagination works
```

---

# 71. DEFINITION OF DONE — SHIPPING

```text
[ ] Shipping method CRUD works
[ ] Active/inactive works
[ ] Shipping price validated server-side
[ ] Checkout integration works
[ ] Order integration works
```

---

# 72. DEFINITION OF DONE — SHIPMENTS

```text
[ ] Shipment creation works
[ ] Order relationship works
[ ] Tracking works
[ ] Valid status transitions work
[ ] Invalid transitions blocked
[ ] Customer tracking works
[ ] Historical shipping address remains correct
```

---

# 73. DEFINITION OF DONE — NOTIFICATIONS

```text
[ ] Notification creation works
[ ] Customer notifications work
[ ] Admin notifications work where required
[ ] Read/unread works
[ ] Mark one read works
[ ] Mark all read works
[ ] Unread count works
[ ] Ownership works
[ ] Duplicate spam prevented
[ ] Queue/realtime integration works if used
```

---

# 74. INTEGRATION CHECKLIST

Verify:

```text
[ ] Product → Inventory
[ ] Variant → Inventory
[ ] Order → Inventory Deduction
[ ] Cancellation → Inventory Restoration
[ ] Inventory → Transactions

[ ] Checkout → Shipping Method
[ ] Order → Shipping
[ ] Order → Shipment
[ ] Shipment → Tracking
[ ] Shipment → Order Status

[ ] Order → Notification
[ ] Payment → Notification
[ ] Shipment → Notification
[ ] Inventory → Admin Notification
```

---

# 75. QUALITY CHECKLIST

```text
[ ] Business logic correct
[ ] Stock quantities correct
[ ] Transactions correct
[ ] Concurrency tested
[ ] Idempotency tested
[ ] Authorization works
[ ] Ownership works
[ ] Validation works
[ ] API tested
[ ] Performance checked
[ ] Mobile checked
[ ] Automated tests pass
[ ] Browser console clean
[ ] Laravel logs checked
[ ] Existing features still work
```

---

# 76. FINAL AUDIT DOCUMENT

Create:

```text
docs/INVENTORY_SHIPPING_AUDIT.md
```

Include:

```markdown
# E-KHMER Inventory & Shipping Audit

## Executive Summary

## Inventory
### Problems Found
### Missing Features
### Changes Made

## Inventory Transactions

## Shipping Methods

## Shipments

## Shipment Status

## Notifications

## Product & Variant Integration

## Order Integration

## Payment Integration

## Inventory Business Logic

## Shipping Business Logic

## Notification Logic

## Data Integrity Improvements

## Security Improvements

## Admin UX Improvements

## Customer UX Improvements

## Performance Improvements

## Database Improvements

## Bugs Fixed

## Automated Tests

## Remaining Issues

## Future Improvements

## Final Status
```

---

# 77. FINAL STATUS REPORT

Report:

```text
Inventory: PASS / NEEDS WORK
Inventory Transactions: PASS / NEEDS WORK

Shipping Methods: PASS / NEEDS WORK
Shipments: PASS / NEEDS WORK
Shipment Status: PASS / NEEDS WORK

Notifications: PASS / NEEDS WORK

Product Integration: PASS / NEEDS WORK
Variant Integration: PASS / NEEDS WORK
Checkout Integration: PASS / NEEDS WORK
Order Integration: PASS / NEEDS WORK
Payment Integration: PASS / NEEDS WORK

Stock Accuracy: PASS / NEEDS WORK
Transaction Safety: PASS / NEEDS WORK
Concurrency: PASS / NEEDS WORK
Security: PASS / NEEDS WORK
Admin UX: PASS / NEEDS WORK
Customer UX: PASS / NEEDS WORK
Performance: PASS / NEEDS WORK
Automated Tests: PASS / NEEDS WORK
```

Also report:

```text
Missing features completed:
Bugs fixed:
Inventory problems fixed:
Shipping problems fixed:
Notification problems fixed:
Business logic improved:
Security issues fixed:
UX improvements:
Performance improvements:
Database changes:
Tests added:
Remaining issues:
```

---

# 78. EXECUTION ORDER

Follow this order:

```text
INSPECT EXISTING PROJECT
        ↓
UNDERSTAND DATABASE
        ↓
AUDIT INVENTORY
        ↓
AUDIT INVENTORY TRANSACTIONS
        ↓
CHECK PRODUCT & VARIANT INTEGRATION
        ↓
CHECK ORDER INTEGRATION
        ↓
CHECK STOCK DEDUCTION
        ↓
CHECK STOCK RESTORATION
        ↓
CHECK CONCURRENCY
        ↓
AUDIT SHIPPING METHODS
        ↓
AUDIT SHIPMENTS
        ↓
AUDIT SHIPMENT STATUS
        ↓
AUDIT NOTIFICATIONS
        ↓
CHECK SECURITY
        ↓
IDENTIFY MISSING FEATURES
        ↓
FIX CRITICAL BUGS
        ↓
COMPLETE MISSING FEATURES
        ↓
IMPROVE BUSINESS LOGIC
        ↓
IMPROVE ADMIN UX
        ↓
IMPROVE CUSTOMER UX
        ↓
OPTIMIZE PERFORMANCE
        ↓
ADD AUTOMATED TESTS
        ↓
TEST EDGE CASES
        ↓
TEST CONCURRENT OPERATIONS
        ↓
TEST COMPLETE INVENTORY FLOW
        ↓
TEST COMPLETE SHIPPING FLOW
        ↓
REGRESSION TEST
        ↓
GENERATE FINAL AUDIT
```

# MOST IMPORTANT REQUIREMENT

Do NOT only inspect the project and write documentation.

Actually modify and fix the existing code.

Do NOT mark features COMPLETE until they have been tested.

Never trust the frontend for:

```text
Stock Quantity
Inventory Adjustment
Shipping Cost
Shipment Status
Order Status
Notification Ownership
```

Laravel/backend must remain the source of truth.

Most importantly, make sure stock changes are always traceable:

```text
Product / Variant
        ↓
     Inventory
        ↓
Stock Changes
        ↓
Inventory Transaction
        ↓
      Order
        ↓
     Shipment
        ↓
Shipment Status
        ↓
  Notification
```

The final system must never accidentally:

```text
Deduct stock twice
Restore stock twice
Allow negative stock
Oversell final inventory
Lose inventory history
Allow fake shipping costs
Allow invalid shipment status changes
Expose another customer's shipment
Expose another customer's notifications
Spam duplicate notifications
```

Priority:

**Stock Accuracy → Data Integrity → Transaction Safety → Concurrency → Shipping Reliability → Security → Notifications → User Experience → Performance → Maintainable Code.**