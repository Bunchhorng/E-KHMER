# E-KHMER — Orders, Payments & Coupons Full Audit, Completion & Improvement

Act as a senior **Laravel + Vue.js + MySQL E-Commerce Order & Payment Engineer**.

I have an existing E-Commerce application called **E-KHMER**.

Tech stack:

- Laravel
- Vue.js
- MySQL
- REST API
- Docker
- Git/GitHub

Your responsibility is to fully inspect, test, fix, complete, integrate, secure, and improve:

1. Orders
2. Order Items
3. Order Status
4. Order Status History
5. Payments
6. Payment Transactions
7. Coupons
8. Coupon Usages

These modules must integrate correctly with:

- Authentication
- Users
- Addresses
- Products
- Product Variants
- Inventory
- Cart
- Checkout
- Shipping
- Notifications

Do NOT treat these as separate CRUD modules.

The complete business flow is:

```text
Cart
 ↓
Checkout
 ↓
Validate Product / Variant
 ↓
Validate Inventory
 ↓
Validate Coupon
 ↓
Calculate Total
 ↓
Create Order
 ↓
Create Order Items
 ↓
Create Payment
 ↓
Process Payment
 ↓
Payment Transaction
 ↓
Update Order Status
 ↓
Update Inventory
 ↓
Shipping
 ↓
Notification
```

Your goal is:

**Correct Business Logic → Financial Accuracy → Data Integrity → Security → User Experience → Performance**

Do NOT only analyze.

Actually:

**INSPECT → AUDIT → FIX → COMPLETE → IMPROVE → TEST → VERIFY**

---

# 1. INSPECT EXISTING PROJECT FIRST

Before modifying code, inspect:

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
Jobs
Events
Listeners

Vue Pages
Vue Components
State Management
API Services

Database Relationships

Checkout
Cart
Inventory
Shipping
Notifications
```

Also inspect:

```text
Laravel Logs
Browser Console
Network Requests
Database Errors
Docker Logs
```

Do not create duplicate features.

Do not rewrite working modules unnecessarily.

---

# 2. FEATURE AUDIT

Audit:

```text
Orders
Order Items
Order Status
Order Status History
Payments
Payment Transactions
Coupons
Coupon Usages
```

Classify each:

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

Do not mark anything COMPLETE without testing it.

---

# 3. DATABASE RELATIONSHIPS

Verify relationships similar to:

```text
User
 ↓
Orders
 ↓
Order Items
 ↓
Product / Variant Snapshot
```

Payment:

```text
Order
 ↓
Payment
 ↓
Payment Transactions
```

Status:

```text
Order
 ↓
Order Status History
```

Coupon:

```text
Coupon
 ↓
Coupon Usages
 ↓
User / Order
```

Shipping:

```text
Order
 ↓
Shipment
```

Adapt these relationships to the existing database.

Do not duplicate existing relationships.

---

# 4. ORDER CREATION

Orders should normally be created through the checkout business flow.

Before creating an order verify:

```text
Authenticated customer if required
Cart exists
Cart is not empty
Products exist
Products are active
Variants are valid
Stock is available
Quantity is valid
Address is valid
Shipping method is valid
Coupon is valid
Prices are current
Totals are correct
```

Never trust financial information from the frontend.

---

# 5. SERVER-SIDE ORDER CALCULATION

Critical values must be calculated by the backend.

Do NOT trust frontend values such as:

```json
{
    "subtotal": 10,
    "discount": 500,
    "shipping": 0,
    "total": 1
}
```

Backend must calculate:

```text
Item Subtotals
Subtotal
Discount
Shipping
Tax if supported
Grand Total
```

Create one consistent calculation strategy.

Avoid duplicating calculation logic across multiple controllers.

---

# 6. ORDER NUMBER

Every order should have a unique human-friendly order number.

Example:

```text
EK-20261006-000123
```

or use the project's existing format.

Requirements:

```text
Unique
Non-conflicting
Easy for customer support
Easy for admin search
```

Do not expose sensitive sequential information if that creates a security problem.

---

# 7. ORDER ITEMS

Order items must preserve purchase-time information.

Store appropriate snapshots such as:

```text
product_id
variant_id

product_name
SKU
variant information
selected attributes

unit_price
quantity
subtotal
```

Adapt fields to the current schema.

---

# 8. HISTORICAL ORDER INTEGRITY

Critical business rule:

Suppose customer buys:

```text
T-Shirt
Black / M
$20
```

Tomorrow admin changes it to:

```text
T-Shirt Premium
Black / M
$30
```

The old order must still show:

```text
T-Shirt
Black / M
$20
```

Do not make historical orders depend completely on live product data.

---

# 9. ORDER STATUS

Audit the current status system.

A reasonable flow might be:

```text
Pending
   ↓
Confirmed
   ↓
Processing
   ↓
Shipped
   ↓
Delivered
```

Alternative states:

```text
Cancelled
Refunded
```

Do not blindly add statuses if the project already defines them differently.

---

# 10. ORDER STATUS TRANSITIONS

Do not allow illogical transitions.

Bad:

```text
Delivered
 ↓
Pending
```

or:

```text
Cancelled
 ↓
Shipped
```

unless there is a specific business rule allowing it.

Create centralized transition rules.

Example:

```text
Pending
→ Confirmed
→ Processing
→ Shipped
→ Delivered
```

Cancellation might be:

```text
Pending → Cancelled
Confirmed → Cancelled
```

according to business requirements.

---

# 11. ORDER STATUS HISTORY

Every important status change should be traceable.

Store:

```text
Order
Old Status
New Status
Changed By
Timestamp
Optional Note
```

Example:

```text
Pending → Confirmed
Admin
10:30 AM
```

Do not silently change important order statuses without history.

---

# 12. CUSTOMER ORDER VIEW

Customer should be able to:

```text
View Orders
View Order Details
See Current Status
See Products
See Payment Status
See Shipping Status
See Total
```

Customer must only access their own orders.

Test ID manipulation.

Customer A must NOT access:

```text
Customer B's Order
Customer B's Payment
Customer B's Address
```

---

# 13. ADMIN ORDER MANAGEMENT

Admin should be able to:

```text
View Orders
Search Orders
Filter Orders
Sort Orders
View Details
Update Valid Status
View Payment
View Customer
View Shipping
View Status History
```

Useful filters:

```text
Order Number
Customer
Order Status
Payment Status
Date
Payment Method
```

Use server-side pagination.

---

# 14. ORDER CANCELLATION

Define cancellation rules clearly.

Check:

```text
Who can cancel?
Which statuses allow cancellation?
What happens to inventory?
What happens to payment?
What happens to coupon usage?
What happens to shipping?
```

Example:

```text
Pending Order
 ↓
Customer Cancels
 ↓
Order = Cancelled
 ↓
Restore/Release Inventory
 ↓
Handle Coupon Usage
 ↓
Handle Payment if necessary
 ↓
Record Status History
```

Do this atomically where appropriate.

---

# 15. INVENTORY INTEGRATION

Orders must integrate correctly with inventory.

Determine when stock is:

```text
Reserved
Deducted
Released
Restored
```

Do not accidentally deduct stock multiple times.

Do not restore stock multiple times.

---

# 16. PREVENT OVERSELLING

Critical scenario:

```text
Stock = 1

Customer A checks out.
Customer B checks out simultaneously.
```

Only one should successfully purchase if only one unit exists.

Use an appropriate database transaction / locking strategy.

Do not rely only on:

```text
if stock > 0
```

without considering concurrent requests.

---

# 17. PAYMENTS

Audit:

```text
Payment Creation
Payment Method
Payment Amount
Payment Status
Payment Reference
Payment Confirmation
Failed Payment
Cancelled Payment
Refund if supported
```

Typical statuses might include:

```text
Pending
Processing
Paid
Failed
Cancelled
Refunded
Partially Refunded
```

Only use statuses appropriate to the existing project.

---

# 18. ORDER STATUS VS PAYMENT STATUS

Do NOT mix these concepts.

Example:

```text
Order Status:
Processing

Payment Status:
Paid
```

They represent different things.

Keep:

```text
Order Status
Payment Status
Shipping Status
```

logically separate.

---

# 19. PAYMENT AMOUNT

Payment amount must come from the verified order total.

Do NOT accept:

```json
{
    "order_id": 100,
    "amount": 0.01
}
```

and blindly process it.

Backend should load the order and determine the expected amount.

---

# 20. PAYMENT TRANSACTIONS

Payment transactions should provide an audit trail.

Possible fields:

```text
payment_id
transaction_reference
provider
type
amount
status
provider_response/reference
created_at
```

Adapt to the existing schema.

Do not store sensitive payment information unnecessarily.

---

# 21. PAYMENT SECURITY

Never store:

```text
Full card number
CVV
Raw payment credentials
Secret API keys in database/frontend
```

Use payment provider tokens/references where applicable.

Never expose payment secrets to Vue.

---

# 22. PAYMENT SUCCESS

When payment succeeds:

```text
Verify Payment
 ↓
Record Transaction
 ↓
Payment = Paid
 ↓
Update Order according to business rules
 ↓
Continue Fulfillment
 ↓
Notify Customer
```

Make this process idempotent.

Receiving the same success event twice must NOT:

```text
Deduct inventory twice
Create two payments
Create two orders
Send incorrect status changes
```

---

# 23. PAYMENT FAILURE

When payment fails:

```text
Payment = Failed
```

Then determine the correct order/inventory behavior.

Do NOT:

```text
Mark Order Paid
Clear important records incorrectly
Lose customer cart unnecessarily
Deduct stock permanently without business reason
```

Allow retry where appropriate.

---

# 24. PAYMENT RETRY

If supported:

```text
Failed Payment
 ↓
Retry
 ↓
New Transaction Attempt
 ↓
Success / Failure
```

Keep transaction history.

Do not overwrite the previous failed transaction.

---

# 25. DUPLICATE PAYMENT PROTECTION

Critical:

Prevent double-click or repeated API requests from creating duplicate charges.

Example:

```text
Pay
Pay
Pay
```

should not produce:

```text
Charge #1
Charge #2
Charge #3
```

Implement idempotency or equivalent safe business logic.

---

# 26. PAYMENT CALLBACK / WEBHOOK

If external payment providers are used, inspect:

```text
Callback
Webhook
Signature verification
Payment reference
Order reference
Amount verification
Currency verification
Duplicate webhook handling
```

Never trust webhook payloads without appropriate provider verification.

Do not mark an order paid just because the frontend says payment succeeded.

---

# 27. PAYMENT TRANSACTION LOGGING

Log enough information for debugging and audit.

Do NOT log:

```text
Passwords
Full payment credentials
API secrets
Tokens that should remain secret
```

Keep sensitive information protected.

---

# 28. COUPONS

Admin should be able to:

```text
Create Coupon
View Coupons
Edit Coupon
Activate/Deactivate
Archive/Delete Safely
Search
Filter
```

Coupon may include:

```text
Code
Discount Type
Discount Value
Start Date
End Date
Minimum Purchase
Maximum Discount
Usage Limit
Per User Limit
Status
```

Use only fields appropriate to existing requirements.

---

# 29. COUPON TYPES

If supported, validate:

```text
Percentage Discount
Fixed Amount Discount
```

Example:

```text
SAVE10
10%
```

or:

```text
SAVE5
$5
```

Do not allow invalid discount values.

For percentage:

```text
0 < percentage <= 100
```

---

# 30. COUPON VALIDATION

Before applying coupon check:

```text
Coupon exists
Coupon active
Current date >= start date
Current date <= expiry date
Usage limit not exceeded
Customer limit not exceeded
Minimum order reached
Applicable product/category rules
```

Backend must validate all rules.

---

# 31. COUPON USAGE

Record coupon usage correctly.

Example relationship:

```text
Coupon
 ↓
Coupon Usage
 ├── User
 └── Order
```

Prevent users from bypassing per-user usage limits with repeated API requests.

---

# 32. COUPON CONCURRENCY

Example:

```text
Coupon Usage Limit = 100

Current Uses = 99

Customer A applies coupon.
Customer B applies coupon simultaneously.
```

Prevent both from exceeding the usage limit if the business rule caps it at 100.

Use appropriate transaction/concurrency control.

---

# 33. COUPON + CANCELLED ORDER

Define business logic:

If an order using a coupon is cancelled:

Should the coupon usage:

```text
Remain Used
```

or:

```text
Be Released
```

Choose one rule based on project requirements and apply it consistently.

Document the decision.

---

# 34. COUPON + PAYMENT FAILURE

Do not permanently consume a coupon incorrectly when payment fails before a successful order/payment state, unless that is the intended business rule.

Inspect current behavior and fix inconsistencies.

---

# 35. ORDER TRANSACTION SAFETY

Critical operations should use database transactions where appropriate.

Conceptually:

```text
BEGIN TRANSACTION

Validate Checkout
Validate Stock
Validate Coupon
Calculate Total

Create Order
Create Order Items
Reserve/Deduct Inventory
Record Coupon Usage
Create Payment

COMMIT
```

If a critical operation fails:

```text
ROLLBACK
```

Avoid:

```text
Order created
but
Order items missing
```

or:

```text
Stock deducted
but
Order creation failed
```

---

# 36. FINANCIAL PRECISION

Audit money calculations.

Never use unsafe floating-point logic for financial calculations.

Check:

```text
Product Price
Subtotal
Discount
Shipping
Tax
Grand Total
Payment Amount
Refund Amount
```

Use appropriate decimal/money handling consistently.

Example:

```text
DECIMAL(12,2)
```

or the project's existing safe money representation.

---

# 37. CURRENCY

If the project supports one currency, enforce it consistently.

If multiple currencies exist, inspect:

```text
Currency Code
Conversion
Payment Currency
Order Currency
Formatting
```

Do not silently mix currencies.

---

# 38. ORDER SEARCH

Admin should be able to search efficiently by useful identifiers such as:

```text
Order Number
Customer Name
Customer Email
Payment Reference
```

Do not load every order and search client-side.

Use database queries and pagination.

---

# 39. FILTERING

Support useful filters where appropriate:

```text
Order Status
Payment Status
Date Range
Customer
Payment Method
Shipping Status
```

Filters should work together.

Example:

```text
Payment = Paid
Order = Processing
Date = This Month
```

---

# 40. ORDER PAGINATION

Do NOT load thousands of orders at once.

Use server-side pagination.

Example:

```text
20–50 records per page
```

depending on UI requirements.

---

# 41. API PERFORMANCE

Audit:

```text
Order List
Order Details
Payment List
Payment Details
Coupon List
Coupon Validation
Status History
```

Find:

```text
N+1 queries
Duplicate queries
Large responses
Unnecessary relationships
SELECT *
Missing indexes
```

Optimize actual bottlenecks.

---

# 42. DATABASE INDEXES

Inspect frequently queried fields such as:

```text
user_id
order_id
product_id
variant_id
coupon_id
payment_id
order_number
status
payment_status
transaction_reference
coupon_code
created_at
```

Add indexes only when justified by real query patterns.

---

# 43. API RESPONSE QUALITY

Avoid returning huge order objects unnecessarily.

Order list:

Return only data required by the table/card.

Order details:

Return richer information.

Do not return:

```text
Sensitive payment information
Internal secrets
Unnecessary user information
```

---

# 44. CUSTOMER UX

Customer order page should clearly display:

```text
Order Number
Date
Products
Variants
Quantity
Prices
Discount
Shipping
Grand Total
Payment Method
Payment Status
Order Status
Shipping Status
Address
```

Make status understandable.

Avoid raw database status codes.

---

# 45. ORDER TIMELINE

If supported, show:

```text
Order Placed
     ↓
Confirmed
     ↓
Processing
     ↓
Shipped
     ↓
Delivered
```

Use `order_status_histories` as the source where appropriate.

Do not fake timeline events that never occurred.

---

# 46. ADMIN UX

Admin order details should combine relevant information clearly:

```text
Order Information
Customer
Items
Payment
Shipping
Coupon
Totals
Status History
Actions
```

Admin should not need to open many unrelated pages to understand one order.

---

# 47. CONFIRMATIONS

Require confirmation for sensitive actions such as:

```text
Cancel Order
Refund Payment
Mark Delivered manually
Delete/Archive Coupon
```

Prevent accidental destructive actions.

---

# 48. NOTIFICATIONS

Check integration with notifications.

Potential events:

```text
Order Placed
Payment Successful
Payment Failed
Order Confirmed
Order Shipped
Order Delivered
Order Cancelled
Refund Completed
```

Do not send duplicate notifications from repeated events.

Use queues where appropriate.

---

# 49. SECURITY AUDIT

Test:

```text
Authentication
Authorization
Ownership
IDOR
Price Manipulation
Discount Manipulation
Payment Manipulation
Status Manipulation
Coupon Manipulation
Mass Assignment
Sensitive Data Exposure
```

Examples of malicious requests:

```json
{
    "order_id": 100,
    "total": 1
}
```

```json
{
    "payment_status": "paid"
}
```

```json
{
    "discount": 9999
}
```

```json
{
    "order_status": "delivered"
}
```

A normal customer must not be able to manipulate these values.

---

# 50. ROLE AUTHORIZATION

Customer can:

```text
View own orders
View own payments where appropriate
Cancel own eligible order
Retry eligible payment
```

Customer must NOT:

```text
Change payment status manually
Mark order delivered
View another customer's order
View another customer's payment
Change another customer's order
```

Admin actions must be protected by backend authorization.

---

# 51. EDGE CASES

Test:

```text
Empty cart
Product deleted
Variant deleted
Price changed
Insufficient stock
Concurrent checkout
Duplicate checkout
Payment failed
Payment timeout
Payment success callback repeated
Payment amount mismatch
Invalid coupon
Expired coupon
Coupon usage exceeded
Coupon concurrent usage
Order cancelled
Already cancelled order
Already delivered order
Invalid status transition
Inventory restoration
Duplicate payment
Network failure
Database failure
```

Application must fail safely.

---

# 52. AUTOMATED TESTS

Create or improve tests for:

```text
Order Creation
Order Items
Order Totals
Order Ownership
Order Status
Status History
Cancellation
Inventory Deduction
Inventory Restoration

Payment Creation
Payment Success
Payment Failure
Payment Retry
Duplicate Payment
Payment Amount Validation

Coupon Creation
Coupon Validation
Coupon Expiration
Minimum Purchase
Usage Limit
Per-user Limit
Coupon Usage
```

Critical tests:

```text
Customer A cannot access Customer B's order.

Customer cannot manually mark payment as paid.

Frontend cannot manipulate order total.

Frontend cannot manipulate payment amount.

Frontend cannot manipulate discount.

Out-of-stock items cannot create an order.

Invalid status transitions are rejected.

Failed order creation rolls back inventory.

Duplicate payment requests do not create duplicate charges.

Duplicate callbacks do not process payment twice.

Historical order items remain unchanged after product edits.
```

---

# 53. CODE QUALITY

Review for:

```text
Huge Controllers
Duplicate Business Logic
Duplicate Total Calculations
Hard-coded Status Values
Hard-coded Payment Logic
Repeated Queries
Missing Transactions
Missing Authorization
Missing Validation
Business Logic Inside Vue
```

Centralize important business logic where appropriate.

Potential concepts:

```text
OrderService
PaymentService
CouponService
OrderStatusService
```

Only introduce these if they improve the existing architecture.

Do NOT overengineer.

---

# 54. DEFINITION OF DONE — ORDERS

```text
[ ] Order creation works
[ ] Order items work
[ ] Order number works
[ ] Totals are correct
[ ] Historical snapshots work
[ ] Ownership works
[ ] Search works
[ ] Filters work
[ ] Pagination works
[ ] Cancellation works
```

---

# 55. DEFINITION OF DONE — STATUS

```text
[ ] Valid transitions work
[ ] Invalid transitions blocked
[ ] Status history works
[ ] Changed-by tracking works
[ ] Customer timeline works if supported
```

---

# 56. DEFINITION OF DONE — PAYMENTS

```text
[ ] Payment creation works
[ ] Amount verification works
[ ] Success works
[ ] Failure works
[ ] Retry works if supported
[ ] Duplicate protection works
[ ] Transaction history works
[ ] Payment status is separate from order status
[ ] Sensitive data is protected
```

---

# 57. DEFINITION OF DONE — COUPONS

```text
[ ] CRUD works
[ ] Activation works
[ ] Date validation works
[ ] Percentage works
[ ] Fixed discount works
[ ] Minimum order works
[ ] Maximum discount works if supported
[ ] Usage limit works
[ ] Per-user limit works
[ ] Coupon usage history works
[ ] Concurrent usage is safe
```

---

# 58. INTEGRATION CHECKLIST

Verify:

```text
[ ] Checkout → Order
[ ] Order → Order Items
[ ] Order → Inventory
[ ] Order → Coupon
[ ] Order → Payment
[ ] Payment → Transactions
[ ] Order → Status History
[ ] Order → Shipping
[ ] Order → Notification
[ ] Order → Customer History
```

---

# 59. QUALITY CHECKLIST

```text
[ ] Business logic correct
[ ] Financial calculations correct
[ ] Database integrity correct
[ ] Transactions implemented where required
[ ] Authentication works
[ ] Authorization works
[ ] Ownership works
[ ] API tested
[ ] Security tested
[ ] Performance checked
[ ] Mobile UX checked
[ ] Automated tests pass
[ ] Browser console clean
[ ] Laravel logs checked
[ ] Existing features still work
```

---

# 60. COMPLETE FLOW TEST

Test:

```text
Customer
   ↓
Cart
   ↓
Checkout
   ↓
Coupon
   ↓
Shipping
   ↓
Order Calculation
   ↓
Inventory Validation
   ↓
Order
   ↓
Order Items
   ↓
Payment
   ↓
Payment Transaction
   ↓
Payment Success
   ↓
Order Confirmed
   ↓
Shipping
   ↓
Delivered
```

Also test failure:

```text
Checkout
   ↓
Payment
   ↓
FAILED
   ↓
Correct Order State
   ↓
Correct Inventory State
   ↓
Correct Coupon State
   ↓
Retry if allowed
```

---

# 61. FINAL AUDIT DOCUMENT

Create:

```text
docs/ORDER_PAYMENT_AUDIT.md
```

Include:

```markdown
# E-KHMER Order & Payment Audit

## Executive Summary

## Orders
### Problems Found
### Missing Features
### Changes Made

## Order Items

## Order Status

## Order Status History

## Payments

## Payment Transactions

## Coupons

## Coupon Usage

## Checkout Integration

## Inventory Integration

## Shipping Integration

## Notification Integration

## Financial Logic Improvements

## Business Logic Improvements

## Security Improvements

## Customer UX Improvements

## Admin UX Improvements

## Performance Improvements

## Database Improvements

## Bugs Fixed

## Automated Tests

## Remaining Issues

## Future Improvements

## Final Status
```

---

# 62. FINAL STATUS REPORT

Report:

```text
Orders: PASS / NEEDS WORK
Order Items: PASS / NEEDS WORK
Order Status: PASS / NEEDS WORK
Status History: PASS / NEEDS WORK

Payments: PASS / NEEDS WORK
Payment Transactions: PASS / NEEDS WORK

Coupons: PASS / NEEDS WORK
Coupon Usage: PASS / NEEDS WORK

Checkout Integration: PASS / NEEDS WORK
Inventory Integration: PASS / NEEDS WORK
Shipping Integration: PASS / NEEDS WORK
Notification Integration: PASS / NEEDS WORK

Financial Accuracy: PASS / NEEDS WORK
Data Integrity: PASS / NEEDS WORK
Security: PASS / NEEDS WORK
Customer UX: PASS / NEEDS WORK
Admin UX: PASS / NEEDS WORK
Performance: PASS / NEEDS WORK
Automated Tests: PASS / NEEDS WORK
```

Also report:

```text
Missing features completed:
Bugs fixed:
Financial issues fixed:
Business logic improved:
Security issues fixed:
UX improvements:
Performance improvements:
Database changes:
Tests added:
Remaining issues:
```

---

# 63. EXECUTION ORDER

Follow this order:

```text
INSPECT EXISTING PROJECT
        ↓
UNDERSTAND DATABASE
        ↓
AUDIT ORDERS
        ↓
AUDIT ORDER ITEMS
        ↓
AUDIT ORDER STATUS
        ↓
AUDIT STATUS HISTORY
        ↓
AUDIT PAYMENTS
        ↓
AUDIT PAYMENT TRANSACTIONS
        ↓
AUDIT COUPONS
        ↓
CHECK CHECKOUT INTEGRATION
        ↓
CHECK INVENTORY
        ↓
CHECK SHIPPING
        ↓
CHECK NOTIFICATIONS
        ↓
CHECK FINANCIAL CALCULATIONS
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
IMPROVE CUSTOMER UX
        ↓
IMPROVE ADMIN UX
        ↓
OPTIMIZE PERFORMANCE
        ↓
ADD AUTOMATED TESTS
        ↓
TEST FAILURE SCENARIOS
        ↓
TEST CONCURRENCY
        ↓
TEST COMPLETE ORDER FLOW
        ↓
REGRESSION TEST
        ↓
GENERATE FINAL AUDIT
```

# MOST IMPORTANT REQUIREMENT

Do NOT only analyze the project or generate documentation.

Actually inspect and modify the existing code.

Do NOT mark a feature COMPLETE without testing it.

Never trust the frontend for:

```text
Price
Subtotal
Discount
Shipping Cost
Grand Total
Payment Amount
Payment Status
Order Status
Inventory Quantity
Coupon Validity
```

The backend must remain the source of truth.

Treat the entire system as one connected transaction:

```text
Checkout
   ↓
Order
   ├── Order Items
   ├── Status History
   ├── Coupon Usage
   ├── Inventory
   ├── Payment
   │      ↓
   │   Transactions
   │
   ├── Shipping
   └── Notifications
```

The final result must ensure that an order can **never become financially incorrect, partially created, paid twice, oversold, or corrupted because of failed/repeated requests**.

Priority:

**Financial Accuracy → Data Integrity → Transaction Safety → Security → Correct Business Logic → Customer Experience → Admin Experience → Performance → Maintainable Code.**