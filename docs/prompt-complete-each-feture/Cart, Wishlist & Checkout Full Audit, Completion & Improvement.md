# E-KHMER — Cart, Wishlist & Checkout Full Audit, Completion & Improvement

Act as a senior **Laravel + Vue.js + MySQL E-Commerce Shopping Flow Engineer**.

I have an existing E-Commerce application called **E-KHMER**.

Tech stack:

- Laravel
- Vue.js
- MySQL
- REST API
- Docker
- Git/GitHub

Your responsibility is to fully inspect, test, fix, complete, integrate, and improve:

1. Cart
2. Cart Items
3. Wishlist
4. Wishlist Items
5. Checkout

These features must integrate correctly with:

- Authentication
- Users
- Addresses
- Products
- Product Variants
- Attributes
- Inventory
- Coupons
- Shipping
- Orders
- Payments

The goal is NOT only to make CRUD operations work.

The goal is to create a **smooth, secure, fast, reliable, and user-friendly shopping experience**.

Do NOT only analyze or give recommendations.

Actually:

**INSPECT → AUDIT → FIND BUGS → FIND MISSING FEATURES → FIX → COMPLETE → IMPROVE → TEST → VERIFY**

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

Vue Pages
Vue Components
State Management
Router
API Services
Axios Configuration

Cart UI
Wishlist UI
Checkout UI

Database Relationships
Authentication
Products
Variants
Inventory
Addresses
Coupons
Shipping
Orders
Payments
```

Do not create duplicate functionality.

Do not rewrite working modules unnecessarily.

---

# 2. FEATURE AUDIT

Audit:

```text
Cart
Cart Items
Wishlist
Wishlist Items
Checkout
```

For every feature classify:

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

Do NOT mark a feature COMPLETE without testing it.

---

# 3. DATABASE RELATIONSHIPS

Verify relationships similar to:

```text
User
 ├── Cart
 │    └── Cart Items
 │          ├── Product
 │          └── Product Variant
 │
 ├── Wishlist
 │    └── Wishlist Items
 │          └── Product
 │
 └── Addresses
```

Shopping flow:

```text
Cart
 ↓
Checkout
 ↓
Address
 ↓
Coupon
 ↓
Shipping
 ↓
Payment
 ↓
Order
 ↓
Order Items
```

Adapt this to the actual database.

Fix incorrect relationships safely.

---

# 4. CART

Customer should be able to:

```text
Add Product to Cart
Add Product Variant to Cart
View Cart
Update Quantity
Remove Item
Clear Cart
Continue Shopping
Proceed to Checkout
```

Verify all operations through both API and frontend.

---

# 5. ADD TO CART BUSINESS LOGIC

Before adding an item, validate:

```text
Product exists
Product is active
Product is purchasable
Variant exists if required
Variant belongs to product
Selected attributes are valid
Product/variant is in stock
Quantity > 0
Requested quantity <= available stock
```

Never trust:

```text
price
discount
subtotal
stock
product name
```

sent by the frontend.

Backend must calculate and verify important values.

---

# 6. PRODUCT VARIANTS

Variant products must work correctly.

Example:

```text
T-Shirt

Color: Black
Size: M
Price: $22
Stock: 5
```

Cart must preserve:

```text
Product
Variant
Selected Attributes
Price
Quantity
```

Display:

```text
Classic T-Shirt

Color: Black
Size: M

$22 × 2
```

Do not lose variant information.

---

# 7. INVALID VARIANT

Reject invalid combinations.

If available:

```text
Black / M
Black / L
White / S
```

Customer must not add:

```text
White / L
```

unless that variant actually exists.

Backend must validate this.

---

# 8. DUPLICATE CART ITEMS

Do not create unnecessary duplicate cart rows.

Example:

Customer adds:

```text
T-Shirt
Black / M
Quantity: 1
```

Then adds the exact same variant again.

Expected behavior:

```text
Black / M
Quantity: 2
```

not:

```text
Black / M — Qty 1
Black / M — Qty 1
```

However:

```text
Black / M
```

and:

```text
White / M
```

must remain separate cart items.

---

# 9. CART QUANTITY

Validate quantity carefully.

Reject:

```text
0
-1
-10
Non-numeric values
Quantity greater than stock
```

If stock = 5:

Customer must not set:

```text
Quantity = 10
```

unless backorders are intentionally supported.

---

# 10. STOCK CHANGES

Handle this scenario:

```text
Customer adds 5 items.

Available stock = 5.

Later admin changes stock to 3.

Customer opens cart.
```

The system must detect the change.

Display something useful such as:

```text
Only 3 items are currently available.
```

Do not allow checkout with invalid quantity.

---

# 11. PRICE CHANGES

Handle:

```text
Customer adds product at $20.

Admin changes price to $25.

Customer returns later.
```

Define and enforce the business rule.

Normally, cart price should be recalculated using current valid pricing before checkout.

The backend must remain the source of truth.

Never trust old frontend price data.

---

# 12. CART TOTALS

Correctly calculate:

```text
Unit Price
Quantity
Line Subtotal
Cart Subtotal
Discount
Shipping
Tax if supported
Grand Total
```

Example:

```text
Product A
$20 × 2 = $40

Product B
$15 × 1 = $15

Subtotal = $55
Discount = $5
Shipping = $2

Grand Total = $52
```

Use one consistent calculation strategy.

Avoid calculating different totals separately in multiple controllers/components.

---

# 13. SERVER-SIDE TOTAL CALCULATION

Critical financial values must be calculated on the backend.

Do NOT trust frontend values such as:

```json
{
  "price": 1,
  "subtotal": 1,
  "discount": 999,
  "total": 1
}
```

The backend must retrieve valid prices and calculate totals.

---

# 14. CART OWNERSHIP

Customer A must not access Customer B's cart.

Test manipulated requests such as:

```text
/api/carts/1
/api/carts/2
/api/cart-items/10
```

Enforce ownership on the backend.

Do not rely only on frontend route protection.

---

# 15. EMPTY CART

Provide a useful empty state.

Example:

```text
Your cart is empty.

Browse products and add something you like.

[ Continue Shopping ]
```

Do not show a broken/empty table.

---

# 16. CART PERFORMANCE

Check for:

```text
Duplicate API requests
N+1 queries
Repeated totals calculation
Large API payloads
Unnecessary frontend re-renders
```

Load only required relationships.

Example:

```text
Cart
 ↓
Items
 ↓
Product
 ↓
Required Variant Data
```

Do not load every product relationship.

---

# 17. GUEST CART

Inspect whether guest cart exists.

If the project supports it:

```text
Guest
 ↓
Add Products
 ↓
Cart stored safely
 ↓
Login/Register
 ↓
Merge Guest Cart
 ↓
Customer Cart
```

Do not lose cart items after login.

If guest cart is NOT part of project requirements, do not add a complicated implementation unnecessarily.

Document the decision.

---

# 18. CART MERGE

If guest cart merging exists, prevent:

```text
Duplicate items
Invalid variants
Out-of-stock items
Excess quantity
Incorrect prices
```

Example:

Guest cart:

```text
Product A × 2
```

Customer existing cart:

```text
Product A × 1
```

After login:

```text
Product A × 3
```

provided stock allows it.

---

# 19. WISHLIST

Customer should be able to:

```text
Add to Wishlist
Remove from Wishlist
View Wishlist
Move Wishlist Item to Cart
```

Verify frontend and API behavior.

---

# 20. WISHLIST DUPLICATES

Prevent duplicate entries.

Bad:

```text
iPhone
iPhone
iPhone
```

Expected:

```text
iPhone
```

Use appropriate database constraints/business logic where possible.

---

# 21. WISHLIST OWNERSHIP

Customer must only access their own wishlist.

Test ID manipulation.

Customer A must not:

```text
View Customer B Wishlist
Delete Customer B Wishlist Item
Modify Customer B Wishlist
```

Backend authorization is mandatory.

---

# 22. WISHLIST PRODUCT STATUS

Handle:

```text
Product deleted/archived
Product inactive
Product out of stock
Price changed
```

Wishlist should not crash.

Display useful state such as:

```text
Currently unavailable
```

where appropriate.

---

# 23. MOVE WISHLIST TO CART

When moving an item:

```text
Wishlist
 ↓
Validate Product
 ↓
Validate Variant if required
 ↓
Validate Stock
 ↓
Add to Cart
 ↓
Optionally Remove from Wishlist
```

If product requires variant selection, do not blindly add an invalid default variant.

Ask/select variant first.

---

# 24. CHECKOUT

Audit the complete checkout flow.

Recommended:

```text
Cart
 ↓
Login/Register if required
 ↓
Shipping Address
 ↓
Shipping Method
 ↓
Coupon
 ↓
Payment Method
 ↓
Order Review
 ↓
Place Order
 ↓
Order Confirmation
```

Keep checkout simple.

Avoid unnecessary steps.

---

# 25. CHECKOUT PRE-VALIDATION

Before checkout begins, verify:

```text
Cart is not empty
Products still exist
Products are active
Variants are valid
Prices are current
Stock is available
Quantities are valid
```

Do not allow invalid carts into order creation.

---

# 26. CHECKOUT ADDRESS

Customer should be able to:

```text
Select Saved Address
Add New Address
Edit Address
Set Default Address
```

Do not force repeat customers to re-enter the same address.

Verify address ownership.

Customer must never use another customer's private saved address by changing an ID.

---

# 27. SHIPPING METHOD

Checkout should show only valid shipping methods.

Display:

```text
Shipping Method
Estimated Cost
Estimated Delivery Time if supported
```

Backend must validate the selected shipping method.

Do not trust shipping cost sent by frontend.

---

# 28. COUPON INTEGRATION

Audit coupon usage during cart/checkout.

Validate:

```text
Coupon exists
Coupon active
Start date
Expiration date
Minimum purchase
Maximum discount
Usage limit
Per-user usage limit
Applicable products/categories
```

Do not trust discount amounts from frontend.

---

# 29. COUPON EDGE CASES

Test:

```text
Invalid coupon
Expired coupon
Inactive coupon
Coupon usage exceeded
Minimum order not reached
Customer already used coupon
Cart changed after coupon applied
Product removed after coupon applied
```

Recalculate discount when cart changes.

---

# 30. TOTAL RECALCULATION

Totals should be recalculated whenever important checkout data changes.

Examples:

```text
Quantity changed
Product removed
Coupon added
Coupon removed
Shipping method changed
Product price changed
```

Correct calculation order must be clearly defined.

For example:

```text
Subtotal
   ↓
Discount
   ↓
Shipping
   ↓
Tax if supported
   ↓
Grand Total
```

Use project business rules as the final authority.

---

# 31. INVENTORY CHECK BEFORE ORDER

Critical:

Recheck inventory immediately before creating the order.

Do NOT assume stock is still available because it was available when the item entered the cart.

Example:

```text
Customer A sees stock = 1
Customer B sees stock = 1

Both checkout.
```

Prevent overselling.

Use database transactions/locking or another appropriate concurrency strategy.

---

# 32. CHECKOUT TRANSACTION

Order creation should be atomic.

Conceptually:

```text
BEGIN TRANSACTION

Validate Cart
Validate Products
Validate Variants
Validate Inventory
Validate Coupon
Calculate Totals
Create Order
Create Order Items
Create Payment record if appropriate
Update/Reserve Inventory
Record Coupon Usage
Clear Cart

COMMIT
```

If a critical step fails:

```text
ROLLBACK
```

Do not leave partial orders or corrupted inventory.

---

# 33. PAYMENT FAILURE

Handle:

```text
Payment Successful
Payment Pending
Payment Failed
Payment Cancelled
```

Do not:

```text
Clear cart too early
Mark unpaid order as paid
Deduct stock incorrectly
Create duplicate orders
```

Adapt behavior to the project's payment flow.

---

# 34. DOUBLE CHECKOUT

Prevent:

```text
Click "Place Order"
Click again
Click again
```

from creating multiple orders.

Frontend:

```text
Disable button
Show "Processing..."
```

Backend:

Implement appropriate duplicate/idempotency protection where necessary.

Do not rely only on button disabling.

---

# 35. ORDER SNAPSHOT

When checkout succeeds, order items should preserve important purchase-time information.

Examples:

```text
Product Name
SKU
Variant
Selected Attributes
Unit Price
Quantity
Subtotal
```

Old orders must remain accurate even if admin later changes the product.

---

# 36. SUCCESSFUL CHECKOUT UX

After success:

```text
Order placed successfully.

Order Number: #EK12345
Total: $52.00
Payment: Paid/Pending
```

Provide actions such as:

```text
View Order
Continue Shopping
```

Do not leave the customer wondering whether the order was created.

---

# 37. FAILED CHECKOUT UX

If checkout fails:

Do NOT show:

```text
SQLSTATE
AxiosError
Stack Trace
```

Display meaningful messages:

```text
One of your items is no longer available.

Please review your cart.
```

or:

```text
Payment could not be completed. Please try again.
```

Preserve cart data when safe.

---

# 38. LOADING UX

Provide states for:

```text
Adding to cart...
Updating...
Removing...
Applying coupon...
Loading shipping methods...
Processing payment...
Placing order...
```

Prevent duplicate actions.

Do not use loading animations to hide slow APIs.

Fix actual performance issues too.

---

# 39. MOBILE UX

Test:

```text
Mobile
Tablet
Desktop
```

Especially:

```text
Cart Items
Quantity Controls
Wishlist Cards
Checkout Form
Address Selection
Shipping Selection
Order Summary
Place Order Button
```

Checkout must be easy to use on mobile.

---

# 40. CART UI IMPROVEMENT

Cart should clearly display:

```text
Product Image
Product Name
Variant
Attributes
Unit Price
Quantity
Subtotal
Remove
```

Also display:

```text
Subtotal
Discount
Shipping if known
Estimated Total
```

Avoid clutter.

---

# 41. CHECKOUT UI IMPROVEMENT

Organize checkout into clear sections:

```text
1. Contact / Customer
2. Shipping Address
3. Shipping Method
4. Payment
5. Coupon
6. Order Summary
```

Use the existing design system.

Do not redesign the entire application unnecessarily.

---

# 42. ORDER SUMMARY

Before placing order, customer must clearly see:

```text
Products
Variants
Quantity
Unit Price
Subtotal
Discount
Shipping
Tax if applicable
Grand Total
Shipping Address
Payment Method
```

No hidden unexpected charges.

---

# 43. API SECURITY

Audit:

```text
Authentication
Authorization
Ownership
Validation
Mass assignment
IDOR
Price manipulation
Quantity manipulation
Coupon manipulation
Shipping price manipulation
```

Test malicious requests.

Example:

```json
{
  "product_id": 5,
  "price": 0.01,
  "quantity": 1000
}
```

Backend must reject or ignore manipulated financial values.

---

# 44. API QUALITY

Review endpoints for:

```text
Cart
Wishlist
Checkout
Coupons
Shipping
```

Check:

```text
HTTP Methods
Status Codes
Validation
Authorization
Error Responses
API Resources
Payload Size
```

Use consistent API response structure.

---

# 45. PERFORMANCE

Measure:

```text
Get Cart
Add to Cart
Update Quantity
Remove Item
Wishlist
Checkout Initialization
Coupon Validation
Shipping Methods
Place Order
```

Look for:

```text
N+1 queries
Duplicate requests
Large API payloads
Repeated calculations
Unnecessary frontend rendering
Slow database queries
```

Optimize actual bottlenecks.

---

# 46. DATABASE CONSTRAINTS

Review appropriate constraints for:

```text
carts
cart_items
wishlists
wishlist_items
```

Consider uniqueness such as:

```text
User + Product + Variant
```

where compatible with existing architecture.

Do not add constraints that break legitimate business cases.

---

# 47. EMPTY / SPECIAL STATES

Handle:

```text
Empty Cart
Empty Wishlist
No Address
No Shipping Method
No Payment Method
Invalid Coupon
Out of Stock
Product Removed
Variant Removed
Network Failure
```

Every state should have a useful next action.

---

# 48. COMPLETE CUSTOMER FLOW TEST

Test from beginning to end:

```text
Open Website
      ↓
Browse Products
      ↓
Select Product
      ↓
Select Variant
      ↓
Add to Cart
      ↓
Update Quantity
      ↓
Add Another Product
      ↓
Move Wishlist Item to Cart
      ↓
Proceed to Checkout
      ↓
Login/Register if needed
      ↓
Select Address
      ↓
Apply Coupon
      ↓
Select Shipping
      ↓
Select Payment
      ↓
Review Order
      ↓
Place Order
      ↓
Payment
      ↓
Order Confirmation
      ↓
View Order
```

Do not test each module only in isolation.

---

# 49. EDGE CASE TESTING

Test:

```text
Empty cart checkout
Quantity = 0
Negative quantity
Quantity > stock
Product deleted after adding
Product inactive after adding
Variant deleted after adding
Variant out of stock
Price changed
Coupon expired
Coupon invalid
Shipping unavailable
Address deleted
Payment failed
Payment timeout
Double-click checkout
Concurrent checkout
Duplicate cart item
Unauthorized cart access
Unauthorized wishlist access
API failure
Database failure
```

Application must fail gracefully.

---

# 50. AUTOMATED TESTS

Create or improve tests for:

```text
Add to Cart
Update Cart
Remove Cart Item
Clear Cart
Duplicate Cart Item
Variant Cart Item
Stock Validation
Cart Ownership

Add Wishlist
Remove Wishlist
Duplicate Wishlist
Wishlist Ownership
Move Wishlist to Cart

Checkout
Address Validation
Coupon Validation
Shipping Validation
Total Calculation
Inventory Validation
Order Creation
Transaction Rollback
Double Checkout Prevention
```

Critical tests:

```text
Customer A cannot access Customer B cart.

Customer A cannot access Customer B wishlist.

Frontend cannot manipulate product price.

Frontend cannot manipulate discount.

Frontend cannot manipulate shipping price.

Out-of-stock products cannot checkout.

Invalid variants cannot checkout.

Order is not partially created after failure.

Double-click does not create duplicate orders.
```

---

# 51. CODE REVIEW

Review for:

```text
Correct business logic
Security
Performance
Laravel conventions
Vue conventions
Database integrity
Transactions
Validation
Authorization
Error handling
Maintainability
```

Look for:

```text
Huge controllers
Duplicate total calculations
Business logic inside Vue
Hard-coded prices
Hard-coded shipping
Missing transactions
Repeated queries
Missing ownership checks
Unused code
```

Move important shopping business logic to appropriate backend services/classes where useful.

Do not overengineer.

---

# 52. DEFINITION OF DONE

## Cart

```text
[ ] Add works
[ ] Variant selection works
[ ] Duplicate handling works
[ ] Update quantity works
[ ] Remove works
[ ] Clear works
[ ] Stock validation works
[ ] Price validation works
[ ] Totals work
[ ] Ownership works
[ ] UI works
```

## Wishlist

```text
[ ] Add works
[ ] Remove works
[ ] Duplicate prevention works
[ ] Ownership works
[ ] Move to cart works
[ ] Unavailable products handled
```

## Checkout

```text
[ ] Cart validation works
[ ] Address works
[ ] Shipping works
[ ] Coupon works
[ ] Payment integration works
[ ] Totals are correct
[ ] Stock recheck works
[ ] Order creation works
[ ] Transactions work
[ ] Double checkout prevented
[ ] Success UX works
[ ] Failure UX works
```

## Quality

```text
[ ] Backend validation works
[ ] Authorization works
[ ] Security tested
[ ] API tested
[ ] Performance checked
[ ] Mobile checked
[ ] Automated tests pass
[ ] No browser console errors
[ ] No unexpected Laravel errors
[ ] Existing features still work
```

---

# 53. FINAL AUDIT DOCUMENT

Create:

```text
docs/SHOPPING_FLOW_AUDIT.md
```

Include:

```markdown
# E-KHMER Shopping Flow Audit

## Executive Summary

## Cart
### Problems Found
### Missing Features
### Changes Made

## Wishlist
### Problems Found
### Missing Features
### Changes Made

## Checkout
### Problems Found
### Missing Features
### Changes Made

## Business Logic Improvements

## Cart Calculation

## Checkout Calculation

## Inventory Integration

## Coupon Integration

## Shipping Integration

## Payment Integration

## Order Integration

## Security Improvements

## User Experience Improvements

## Performance Improvements

## Bugs Fixed

## Automated Tests

## Remaining Issues

## Future Improvements

## Final Status
```

---

# 54. FINAL STATUS REPORT

Report:

```text
Cart: PASS / NEEDS WORK
Wishlist: PASS / NEEDS WORK
Checkout: PASS / NEEDS WORK

Product Integration: PASS / NEEDS WORK
Variant Integration: PASS / NEEDS WORK
Inventory Integration: PASS / NEEDS WORK
Address Integration: PASS / NEEDS WORK
Coupon Integration: PASS / NEEDS WORK
Shipping Integration: PASS / NEEDS WORK
Payment Integration: PASS / NEEDS WORK
Order Integration: PASS / NEEDS WORK

Business Logic: PASS / NEEDS WORK
Security: PASS / NEEDS WORK
Customer UX: PASS / NEEDS WORK
Mobile UX: PASS / NEEDS WORK
Performance: PASS / NEEDS WORK
Automated Tests: PASS / NEEDS WORK
```

Also report:

```text
Missing features completed:
Bugs fixed:
Business logic improved:
Security issues fixed:
UX improvements:
Performance improvements:
Tests added:
Database changes:
Remaining issues:
```

---

# 55. EXECUTION ORDER

Follow this order:

```text
INSPECT EXISTING PROJECT
        ↓
UNDERSTAND DATABASE
        ↓
AUDIT CART
        ↓
AUDIT WISHLIST
        ↓
AUDIT CHECKOUT
        ↓
CHECK PRODUCT & VARIANT INTEGRATION
        ↓
CHECK INVENTORY
        ↓
CHECK AUTHENTICATION
        ↓
CHECK ADDRESS
        ↓
CHECK COUPON
        ↓
CHECK SHIPPING
        ↓
CHECK PAYMENT
        ↓
CHECK ORDER CREATION
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
IMPROVE PERFORMANCE
        ↓
ADD AUTOMATED TESTS
        ↓
TEST EDGE CASES
        ↓
TEST COMPLETE CUSTOMER FLOW
        ↓
REGRESSION TEST
        ↓
GENERATE FINAL AUDIT
```

# MOST IMPORTANT REQUIREMENT

Do NOT only inspect and generate a report.

Actually modify and improve the existing application.

Do NOT mark a feature COMPLETE until it has been tested.

Treat the shopping system as one connected flow:

```text
Product
   ↓
Variant
   ↓
Cart
   ↔
Wishlist
   ↓
Checkout
   ↓
Address
   ↓
Coupon
   ↓
Shipping
   ↓
Payment
   ↓
Order
   ↓
Inventory Update
```

The final result should allow a customer to move from **Product → Cart → Checkout → Order Confirmation** smoothly, securely, and without confusing or unnecessary steps.

Priority:

**Correct Business Logic → Financial Accuracy → Inventory Integrity → Security → Customer Experience → Performance → Maintainable Code.**