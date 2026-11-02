# E-KHMER Improvement #05 — Guest Checkout Policy & Web Flow

Act as a senior **Laravel + Vue.js + TypeScript Marketplace Engineer, Checkout Engineer, Authentication Engineer, UX Engineer, Security Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

Previous work already addressed:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
F03 — Discount Cap & Checkout Total Consistency
F04 — COD Collection & Paid-State Lifecycle
```

Do NOT regress those fixes.

Now work ONLY on:

# F05 — Guest Checkout Policy & Web Flow

---

# 1. CURRENT DEFECT

The backend already contains guest-order capability.

However, the current web checkout behaves like this:

```text
Guest Customer
      ↓
Adds Products to Cart
      ↓
Clicks Checkout
      ↓
Checkout loads authenticated addresses
      ↓
GET /addresses
      ↓
401 Unauthorized
      ↓
Redirect to Login
```

This creates an inconsistent product policy:

```text
Backend:
Guest checkout capability exists

Frontend:
Guest checkout effectively blocked
```

This must be resolved deliberately.

---

# 2. FIRST DECISION — DEFINE THE ACTUAL BUSINESS POLICY

Before coding, inspect the existing implementation and determine which policy E-KHMER should use.

There are two valid choices.

## Option A — Support Guest Checkout

Guest users can:

```text
Browse
Add to cart
Checkout
Enter contact details
Enter shipping address
Choose shipping
Choose supported payment
Place order
Receive order reference
Track/access order appropriately
```

## Option B — Require an Account

Guest users can:

```text
Browse
Add to cart
```

but checkout clearly requires:

```text
Login
or
Register
```

The current implementation must not remain half-guest / half-account-required.

---

# 3. RECOMMENDED POLICY

Because E-KHMER already has:

```text
guest carts
guest cart merge
guest order APIs
```

prefer implementing a proper **Guest Checkout** unless current business requirements explicitly require customer accounts.

Do NOT blindly follow this recommendation.

First inspect:

```text
backend guest order implementation
authentication requirements
payment requirements
returns
reviews
order tracking
notifications
support
addresses
```

Then document the chosen policy.

---

# 4. INSPECT THE REAL IMPLEMENTATION

Focus especially on:

```text
frontend/src/views/CheckoutView.vue
frontend/src/router/index.ts
frontend/src/stores/cart.ts
frontend/src/stores/auth.ts

frontend/src/api/
auth API
address API
checkout API
order API

backend/app/Http/Controllers/Api/CheckoutController.php
backend/app/Services/CheckoutService.php
backend/app/Services/CartService.php

backend/app/Http/Controllers/Api/AddressController.php

routes/api.php

Order model
ShopOrder model
Address model
User model
Cart model

checkout requests
checkout resources
checkout tests
guest cart tests
authentication tests
```

Trace:

```text
Guest Browser
→ Guest Cart
→ Checkout Page
→ Contact Details
→ Shipping Address
→ Delivery Selection
→ Coupon
→ Payment
→ Backend Checkout
→ Order
→ Confirmation
```

Do not assume the intended implementation.

---

# 5. DO NOT CREATE A SECOND CHECKOUT SYSTEM

Do NOT build:

```text
GuestCheckoutService
AuthenticatedCheckoutService
```

with duplicated business rules unless absolutely necessary.

Preferred architecture:

```text
CheckoutService

Authenticated Customer
        or
Guest Customer Context
        ↓
Same Product Validation
Same Coupon Validation
Same Shipping Validation
Same Financial Calculation
Same Inventory Reservation
Same Order Creation
```

Only identity/address handling should differ where needed.

---

# 6. GUEST CART

Preserve existing guest cart functionality.

Guest cart must support:

```text
add item
update quantity
remove item
clear cart
coupon
multi-shop cart
```

F02 stale-item recovery must continue to work.

Do NOT force login just because the user adds multiple shops.

---

# 7. CHECKOUT ENTRY

For guest customer:

```text
/cart
   ↓
[Proceed to Checkout]
   ↓
/checkout
```

Do NOT immediately redirect to login if Guest Checkout is selected as the policy.

If Account Required is selected, do not silently fail with a 401.

Instead show an intentional UX:

```text
Sign in to continue checkout

[ Sign In ]
[ Create Account ]
```

and preserve the cart.

---

# 8. GUEST CONTACT INFORMATION

If supporting Guest Checkout, collect:

```text
Full Name
Email
Phone Number
```

Use the actual minimum required by existing order/shipping/payment flows.

Example:

```text
Contact Information

Full Name *
Email *
Phone *
```

Do not collect unnecessary personal information.

---

# 9. GUEST SHIPPING ADDRESS

Guest must be able to enter a shipping address directly in checkout.

Example fields:

```text
Recipient Name
Phone
Address Line
City / Province
Postal Code if used
Country
Delivery Notes optional
```

Adapt to the existing E-KHMER address model.

Do NOT require creation of a saved `addresses` row owned by a user if the customer has no user account.

---

# 10. ORDER ADDRESS SNAPSHOT

Guest shipping data must be persisted as an order-time snapshot.

Concept:

```text
Guest Input
     ↓
Validated Address
     ↓
Order Shipping Snapshot
```

Do NOT make guest historical orders depend on a user-address record.

Authenticated customers should also continue preserving historical snapshots according to existing order design.

---

# 11. AUTHENTICATED CHECKOUT

Do NOT degrade the existing customer experience.

Logged-in users should continue to have:

```text
Saved Addresses
Default Address
Add New Address
Edit Address
Select Address
```

Recommended branching:

```text
Authenticated
→ Saved address flow

Guest
→ Inline address flow
```

Both feed the same validated checkout data structure.

---

# 12. REMOVE AUTH-ONLY ADDRESS CALL FOR GUEST

Current defect includes checkout trying to fetch:

```text
/api/addresses
```

for guests.

Fix this.

Concept:

```text
if authenticated:
    load saved addresses
else:
    do not call authenticated address API
    show guest address form
```

Do not rely on catching a 401 after making an invalid request.

---

# 13. GUEST EMAIL VALIDATION

Validate email server-side.

Do not accept arbitrary malformed email.

Example:

```text
customer@example.com
```

If order confirmation email is part of the existing system, use the guest email for delivery.

Do not automatically create an account from the email.

---

# 14. EXISTING USER EMAIL

Scenario:

```text
Guest checkout email
=
email of existing E-KHMER account
```

Define behavior carefully.

Recommended:

```text
Allow guest checkout
```

unless the business explicitly requires login.

Do not expose:

```text
"This email already belongs to User X"
```

in a way that leaks account existence unnecessarily.

If the current auth policy intentionally blocks this, document and test it.

---

# 15. OPTIONAL ACCOUNT CREATION AFTER CHECKOUT

Do NOT force account creation during guest checkout.

Optional future UX:

```text
Order placed successfully

Create an account to manage this order more easily
```

This can be added later.

Do not include it in F05 unless existing architecture already supports safe linking.

---

# 16. GUEST ORDER OWNERSHIP

Guest orders cannot rely only on:

```text
user_id
```

Determine how current guest orders are represented.

Possibilities:

```text
user_id = null

guest_email

guest_phone

guest_token

order access token
```

Use the existing architecture.

Do NOT invent a new insecure ownership method without need.

---

# 17. GUEST ORDER CONFIRMATION

After successful checkout show:

```text
Order placed successfully

Order number:
EK-...

Email:
customer@example.com

Total:
$...

[ Continue Shopping ]
```

If secure guest tracking exists:

```text
[ Track Order ]
```

Do not expose internal database IDs.

---

# 18. GUEST ORDER ACCESS SECURITY

This is critical.

Never allow:

```text
/orders/123
```

to be accessible simply because someone knows order ID 123.

If guests can view order details, require a secure mechanism such as the existing:

```text
signed link
secure token
order number + verified secret
```

Use the actual implementation if one already exists.

Do NOT create predictable guest-access tokens.

---

# 19. CUSTOMER ACCOUNT ORDERS

Authenticated users continue to access:

```text
/account/orders
```

through normal ownership authorization.

Do not weaken authenticated order authorization to support guests.

---

# 20. GUEST CHECKOUT + MULTI-SHOP

Guest Checkout must work with the existing marketplace architecture.

Example:

```text
Guest Cart

Shop A
Product A

Shop B
Product B
```

Checkout must still support:

```text
one parent order
multiple shop orders
per-shop delivery
correct coupon scope
correct inventory reservation
correct payment totals
```

Guest identity must not change financial behavior.

---

# 21. COUPONS

Preserve F01 and F03.

Guest users must not bypass:

```text
shop coupon ownership
minimum spend
usage limits
discount caps
expiration
eligible items
```

If coupons have:

```text
per-user usage limits
```

determine how guest usage is handled.

Possible guest identifiers could include:

```text
guest email
order identity
session
```

Do NOT weaken coupon limits silently.

If per-user coupons intentionally require login, return a clear message.

---

# 22. DELIVERY METHODS

Guest checkout should still retrieve valid:

```text
per-shop delivery methods
fees
ETA
```

using public/checkout-safe endpoints.

Do not require authentication merely to view available shipping choices unless that is intentional.

Backend validates selected shipping methods again during checkout.

---

# 23. PAYMENT METHODS

Determine which payment methods guest checkout supports.

Potential:

```text
COD
Bakong / KHQR
```

Do not automatically assume every payment method supports guests.

For each existing production method, test:

```text
Guest COD
Guest Bakong
```

where applicable.

---

# 24. COD + GUEST

If guest COD is supported:

```text
Guest
→ COD Checkout
→ Order pending payment
→ Fulfillment
→ Collection
→ Paid
```

F04 lifecycle must remain correct.

Guest identity must not block COD collection.

---

# 25. BAKONG + GUEST

If guest Bakong is supported:

```text
Guest Checkout
→ Order
→ KHQR
→ Payment Verification
→ Confirmation
```

Do not require authentication to poll payment status if the guest has a secure checkout/payment token.

Do not weaken payment verification.

If current Bakong flow requires an authenticated account, document that boundary rather than hacking around it.

---

# 26. INVENTORY RESERVATION

Guest checkout must use the same reservation rules as authenticated checkout.

Preserve:

```text
atomic reservation
expiry
stock validation
retry safety
idempotency
```

Do not add a simplified guest path that bypasses stock protection.

---

# 27. CHECKOUT IDEMPOTENCY

Guest checkout must remain retry-safe.

Guest has no user ID, so inspect how idempotency ownership currently works.

Current audit says checkout uses owner-scoped idempotency.

Ensure guest ownership uses a stable safe context such as:

```text
guest session / cart owner
```

according to existing architecture.

Do not allow one guest session to retrieve another guest's checkout attempt.

---

# 28. SESSION EXPIRY

Test:

```text
Guest adds cart
session expires
opens checkout
```

Provide a safe state.

Do not create empty or anonymous orders without a valid cart context.

---

# 29. LOGIN DURING CHECKOUT

Scenario:

```text
Guest has cart
↓
opens checkout
↓
chooses Sign In
↓
logs in
```

Existing guest-cart merge should preserve valid items.

Then:

```text
checkout switches to authenticated address experience
```

Do not duplicate or lose cart lines.

---

# 30. REGISTER DURING CHECKOUT

If the user chooses:

```text
Create Account
```

ensure:

```text
guest cart
→ merged
→ checkout resumes
```

where the existing app supports this.

Do not make this mandatory for guest checkout.

---

# 31. VALIDATION ERRORS

Guest contact/address validation must be visible.

Current audit noted that inline address failures are swallowed even for registered checkout.

While focusing on F05, fix validation handling needed for the guest flow.

Example:

```text
Phone number is required.

Please enter a valid email address.

Address is required.

Please choose a delivery method.
```

Do not show only:

```text
422
```

---

# 32. STEP PROGRESSION

Do not allow the checkout to move forward when required guest information is invalid.

Example:

```text
Contact
↓
Address
↓
Delivery
↓
Payment
↓
Review
```

If Address invalid:

```text
stay on Address step
show field errors
```

---

# 33. LOADING STATES

Add proper states for:

```text
loading shipping methods
validating coupon
creating order
loading payment
```

Disable duplicate Place Order clicks.

Preserve F03 financial recalculation behavior.

---

# 34. GUEST CHECKOUT PAGE UX

Recommended high-level layout:

```text
Checkout

1 Contact
2 Delivery
3 Payment
4 Review
```

For guest:

```text
Contact information

[Full name]
[Email]
[Phone]

Shipping address

[Address]
[City]
[Country]
```

Optional:

```text
Already have an account?
Sign in
```

Do not use an aggressive forced-login modal if guest checkout is supported.

---

# 35. ACCOUNT CHECKOUT PAGE UX

Authenticated:

```text
Deliver to

○ Default address
○ Saved address
○ Add new address
```

The rest of checkout remains shared.

---

# 36. GUEST ORDER EMAIL

If transactional email exists:

Send guest order confirmation to:

```text
checkout email
```

Verify that notifications can operate without a User model.

Do not access:

```text
$order->user->email
```

without safely handling guest orders.

---

# 37. GUEST SHIPPING NOTIFICATIONS

If order/shipping emails currently depend on the authenticated user, inspect:

```text
Order confirmation
Payment confirmation
Shipment notification
Delivered notification
```

Ensure guest recipient resolution is safe.

Do not expand F05 into a full mail redesign unnecessarily.

Fix only the necessary guest compatibility and document remaining live-email validation under the existing O16 item.

---

# 38. NOTIFICATIONS DATABASE

Guest customers generally cannot use authenticated in-app notification inboxes.

That is acceptable.

Use:

```text
email / order confirmation page
```

where the existing project supports them.

Do not create fake User records just to create database notifications.

---

# 39. REVIEWS

Guest order existence does NOT automatically mean guest reviews should be supported.

Current verified reviews depend on customer ownership/delivered purchase logic.

For Version 1, it is acceptable that:

```text
guest purchaser
→ cannot submit authenticated review
```

unless current requirements explicitly support guest reviews.

Document this boundary.

---

# 40. RETURNS

Determine whether guest returns are supported.

Do not invent an insecure return flow.

Possible policy:

```text
Guest orders:
support contact / secure order link required
```

Authenticated return workflow may remain account-only.

Document the decision.

Do not block F05 solely because full guest self-service returns do not exist unless guest returns are a stated requirement.

---

# 41. SUPPORT

Guest support already exists according to the current project audit.

Leverage that where appropriate for guest post-purchase help.

Do not require login for every support path if existing guest support intentionally allows anonymous contact.

---

# 42. DATA PRIVACY

Guest checkout creates personal data.

Protect:

```text
name
email
phone
shipping address
```

Do not expose it in:

```text
public API
shop product endpoints
logs
Telegram alerts
browser localStorage unnecessarily
```

Seller should receive only order-related information required for fulfillment according to existing authorization.

---

# 43. LOCAL STORAGE

Do not store full guest:

```text
address
phone
email
```

in persistent browser storage unless necessary and intentionally designed.

Prefer component/store state and backend order snapshot.

---

# 44. API DESIGN

If existing backend API already supports guest checkout, reuse it.

Do not create:

```text
/api/guest-checkout-v2
```

unless required.

Prefer one checkout endpoint that can accept:

```text
authenticated customer context
or
validated guest contact/address context
```

if existing architecture permits.

---

# 45. REQUEST STRUCTURE

Conceptual guest request:

```json
{
  "customer": {
    "name": "Demo Customer",
    "email": "customer@example.com",
    "phone": "+855..."
  },
  "shipping_address": {
    "address_line_1": "...",
    "city": "Phnom Penh",
    "country": "KH"
  },
  "delivery_methods": {},
  "payment_method": "cod"
}
```

Do not copy this blindly.

Use the actual existing request contract.

Never accept frontend:

```text
user_id
shop_id ownership
price
discount
tax
grand_total
stock
```

as financial authority.

---

# 46. GUEST ADDRESS MUST NOT BECOME ANOTHER USER'S ADDRESS

Security test:

```text
Guest submits:
address_id = 123
```

Backend must not allow access to authenticated User A's saved address.

For guest checkout:

```text
use submitted validated address data
```

not arbitrary saved-address IDs.

---

# 47. AUTHENTICATED ADDRESS OWNERSHIP

Preserve existing rule:

```text
Customer A
✗
Customer B saved address
```

F05 must not weaken this.

---

# 48. RATE LIMITING

Guest checkout is more exposed to abuse.

F13 will handle broader checkout abuse protection later.

For F05:

Do not introduce an unrestricted public endpoint accidentally.

Preserve existing throttles/idempotency protections.

Document that stronger checkout quotas are addressed under F13.

---

# 49. ORDER CREATION

Guest order must preserve:

```text
customer contact snapshot
shipping address snapshot
order items
price snapshots
shop orders
payment state
```

The order should remain understandable if the guest never creates an account.

---

# 50. OPTIONAL FUTURE ORDER CLAIMING

Future feature:

```text
Guest completes checkout
↓
later creates account with same email
↓
securely claims previous order
```

Do NOT implement automatically in F05.

Email matching alone is not sufficient proof of ownership.

---

# 51. BACKEND TESTS

Add/update tests for:

## Test 1 — Guest cart

```text
Guest
→ add product
→ cart persists
```

## Test 2 — Guest checkout page data/API support

```text
Guest
→ does not require /addresses
```

## Test 3 — Guest COD checkout

If supported:

```text
Guest
→ contact + address + delivery + COD
→ order created
```

## Test 4 — Guest Bakong

If supported:

```text
Guest
→ valid payment flow
```

Otherwise explicitly test that the method is unavailable with a clear reason.

## Test 5 — Invalid guest email

```text
→ 422
→ no order
```

## Test 6 — Missing address

```text
→ 422
→ no order
```

## Test 7 — Guest cannot use saved address belonging to a user

```text
→ rejected
```

## Test 8 — Multi-shop guest checkout

```text
Shop A + Shop B
→ parent order
→ correct shop orders
```

## Test 9 — Coupon rules

```text
guest checkout
→ F01/F03 coupon rules preserved
```

## Test 10 — Inventory

```text
guest checkout
→ reservation works
```

## Test 11 — Duplicate submission

```text
same guest checkout retried
→ no duplicate order
```

## Test 12 — Guest order ownership/access

Unauthorized guessed order access is rejected.

---

# 52. FRONTEND TESTS

Test:

```text
guest opens checkout
no /addresses request

guest contact form visible

field validation visible

shipping form works

delivery methods work

coupon works

payment selection works

order review works

place order works

loading state works

API errors visible
```

Authenticated checkout must still work.

---

# 53. BROWSER / E2E TEST

Critical regression:

```text
Guest
→ Browse
→ Add product
→ Cart
→ Checkout
```

Expected:

```text
No forced login
No /addresses 401
```

if Guest Checkout is chosen.

Then:

```text
Enter contact
Enter shipping address
Choose delivery
Choose payment
Review
Place order
Confirmation
```

Also test:

```text
Shop A + Shop B
```

if fixtures support multi-shop checkout.

---

# 54. ACCOUNT-REQUIRED ALTERNATIVE

If after inspection the team deliberately chooses:

```text
Account Required Checkout
```

then do NOT implement guest-order UI.

Instead:

```text
Remove misleading guest-checkout path
Guard checkout intentionally
Show account requirement before checkout
Preserve guest cart
Return to checkout after login/register
Document backend guest-order APIs as unused/deprecated or future
```

Tests must verify:

```text
guest cannot enter checkout
cart preserved
clear sign-in/register UX
login returns customer to checkout
```

Do not leave backend/frontend policies contradictory.

---

# 55. POLICY DOCUMENTATION

Document the chosen decision in:

```text
docs/COMMERCE-COMPLETION.md
```

or appropriate current documentation.

Write explicitly:

```text
Guest checkout:
SUPPORTED
```

or:

```text
Guest checkout:
ACCOUNT REQUIRED
```

Do not leave this ambiguous.

---

# 56. F01 REGRESSION

Verify:

```text
shop coupon isolation
mixed-shop coupon
```

still passes.

---

# 57. F02 REGRESSION

Verify:

```text
unavailable cart items
guest cart recovery
```

still passes.

---

# 58. F03 REGRESSION

Verify:

```text
cart totals
checkout totals
payment totals
```

remain consistent.

---

# 59. F04 REGRESSION

For guest COD, if supported:

```text
pending COD
→ delivery
→ collection
→ paid
```

must still work.

Guest order must not break the COD collection lifecycle.

---

# 60. TYPE CHECK & BUILD

Run:

```text
frontend TypeScript check
frontend production build
```

No unsafe `any` fixes.

---

# 61. DOCKER ONLY

Use project Docker containers for:

```text
PHP
Composer
Node
tests
build
```

Do not wipe development data.

Use isolated test environments.

---

# 62. DO NOT START F06

Only solve F05.

Do not begin:

```text
rating filter fix
price filter fix
```

yet.

That is F06.

---

# 63. DEFINITION OF DONE — GUEST CHECKOUT OPTION

If Guest Checkout is selected, F05 is complete only when:

```text
[ ] Guest can enter checkout without login

[ ] Checkout does not call auth-only address API for guest

[ ] Guest contact form works

[ ] Guest shipping address works

[ ] Server-side validation works

[ ] Multi-shop delivery works

[ ] Coupons work correctly

[ ] Totals remain backend-authoritative

[ ] Inventory reservation works

[ ] Guest order is created safely

[ ] Guest address snapshot persists

[ ] Secure guest order confirmation/access exists as required

[ ] Authenticated checkout still works

[ ] Guest cart still works

[ ] Login/register cart merge still works

[ ] COD works if enabled for guests

[ ] Bakong behavior is explicitly supported or restricted

[ ] No cross-user address leakage

[ ] No insecure order-ID access

[ ] F01 regression passes

[ ] F02 regression passes

[ ] F03 regression passes

[ ] F04 regression passes

[ ] Backend tests pass

[ ] Frontend tests pass

[ ] Type check passes

[ ] Production build passes

[ ] Browser regression passes
```

---

# 64. DEFINITION OF DONE — ACCOUNT REQUIRED OPTION

If the project intentionally chooses account-required checkout:

```text
[ ] Guest policy explicitly documented

[ ] Checkout route intentionally protected

[ ] No accidental /addresses 401 flow

[ ] Guest sees clear sign-in/register requirement

[ ] Guest cart preserved

[ ] Login/register returns user to checkout

[ ] Backend guest-order capability reviewed/documented

[ ] Existing authenticated checkout passes

[ ] Regression suite passes
```

---

# 65. FINAL REPORT

At completion report exactly:

```text
F05 — GUEST CHECKOUT POLICY & WEB FLOW

Status:
PASS / NEEDS WORK

Chosen Policy:
GUEST CHECKOUT / ACCOUNT REQUIRED

Reason for Policy:

Root Cause:

Old Behavior:

New Behavior:

Guest Cart Behavior:

Address Strategy:

Order Ownership Strategy:

Order Confirmation Strategy:

COD Support:
YES / NO / N/A

Bakong Support:
YES / NO / N/A

Multi-Shop Guest Checkout:
PASS / FAIL / N/A

Security Controls:

Files Changed:

Schema Changes:
None / List

Tests Added:

Backend Tests:
Passed / Failed

Frontend Tests:
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

F04 Regression:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F06 — Rating & Price Filters
```

Also include the final customer flow.

If Guest Checkout:

```text
Guest
→ Product
→ Cart
→ Checkout
→ Contact
→ Address
→ Delivery
→ Payment
→ Review
→ Order
→ Confirmation
```

If Account Required:

```text
Guest
→ Product
→ Cart
→ Checkout
→ Sign In / Register
→ Cart Preserved
→ Checkout
→ Order
```

Stop after F05.

Do NOT automatically start F06.

I will review the result first.