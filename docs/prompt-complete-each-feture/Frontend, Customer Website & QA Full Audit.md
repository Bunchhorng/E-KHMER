# E-KHMER — Frontend, Customer Website & QA Full Audit

Act as a senior **Vue.js Frontend Engineer, Laravel API Integration Engineer, UI/UX Engineer, and QA Engineer**.

I have an existing E-Commerce application called **E-KHMER**.

Tech stack:

- Laravel Backend
- Vue.js Frontend
- MySQL
- REST API
- Docker
- Git/GitHub

My responsibilities are:

1. Customer Website
2. Reviews
3. Review Images
4. API Integration
5. Testing
6. Quality Assurance (QA)

Your job is to fully inspect, test, fix, complete, integrate, optimize, and improve these areas.

Do NOT only review the code or generate a report.

Actually:

**INSPECT → AUDIT → FIND BUGS → FIX → COMPLETE → INTEGRATE → IMPROVE UX → OPTIMIZE → TEST → REGRESSION TEST → VERIFY**

---

# 1. INSPECT THE EXISTING PROJECT FIRST

Before making changes, inspect the existing frontend and related backend APIs.

Inspect:

```text
Vue application structure
Pages
Components
Layouts
Router
Route guards
State management
Axios/API services
Environment variables
Reusable components
Forms
Tables
Modals
Loading components
Error components
Empty states

Laravel API routes
Controllers
Resources
Validation
Authentication
Authorization

Docker
Vite configuration
Browser console
Network requests
Laravel logs
```

Do NOT rebuild working features unnecessarily.

Reuse the project's existing design system and architecture.

---

# 2. FULL FRONTEND AUDIT

Find every customer-facing page.

Classify each as:

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

For every page check:

```text
UI
Responsive Design
API Integration
Loading State
Error State
Empty State
Validation
Navigation
Business Logic
Performance
Accessibility
```

Do NOT mark anything COMPLETE without testing it.

---

# 3. CUSTOMER WEBSITE

Audit and complete the entire customer website.

Expected flow:

```text
Home
 ↓
Products
 ↓
Product Details
 ↓
Cart
 ↓
Checkout
 ↓
Payment
 ↓
Order Confirmation
 ↓
Orders
 ↓
Order Details
 ↓
Tracking
 ↓
Review
```

Also inspect:

```text
Login
Register
Profile
Addresses
Wishlist
Notifications
Search
Categories
Brands
```

The entire customer journey must feel like one application.

---

# 4. HOME PAGE

Audit:

```text
Header
Navigation
Search
Hero/Banner if used
Categories
Featured Products
New Products
Popular Products if supported
Promotions if supported
Footer
```

Check that all displayed data comes from correct APIs where appropriate.

Do not use fake/static product data when real APIs exist.

---

# 5. HEADER / NAVIGATION

Verify:

```text
Logo
Home
Products
Categories
Search
Wishlist
Cart
Login/Register
Profile
Orders
Notifications
Logout
```

Display navigation based on authentication state.

Guest:

```text
Login
Register
```

Authenticated customer:

```text
Profile
Orders
Notifications
Logout
```

Avoid unnecessary full-page reloads.

Use Vue Router correctly.

---

# 6. PRODUCT LIST

Product listing should support appropriate features such as:

```text
Product Image
Product Name
Price
Sale Price
Stock Status
Category
Brand
Rating
Add to Cart
Wishlist
```

Also test:

```text
Search
Filters
Sorting
Pagination
```

Do not load every product at once.

Use backend pagination.

---

# 7. PRODUCT SEARCH

Search should be:

- fast
- responsive
- clear
- debounced where appropriate

Do NOT make API requests for every single keystroke unnecessarily.

Handle:

```text
Loading
No Results
API Error
Empty Search
```

---

# 8. FILTERS

Check combinations such as:

```text
Category
+
Brand
+
Price
+
Availability
+
Sort
```

Changing one filter should not unexpectedly reset all other filters.

Pagination should preserve filters where appropriate.

---

# 9. PRODUCT DETAILS

Product detail page should correctly display:

```text
Product Name
Images
Price
Sale Price
Category
Brand
Description
Variants
Attributes
Stock
Quantity
Wishlist
Add to Cart
Reviews
Average Rating
```

Do not expose internal IDs unnecessarily.

---

# 10. PRODUCT IMAGE GALLERY

Check:

```text
Primary Image
Thumbnail Images
Image Selection
Fallback Image
Broken Image Handling
Responsive Images
Lazy Loading
```

Do not download unnecessarily huge images on listing pages.

---

# 11. PRODUCT VARIANT UX

Example:

```text
Color:
[ Black ] [ White ]

Size:
[ S ] [ M ] [ L ]
```

When customer selects:

```text
Black + M
```

update appropriate:

```text
Price
Stock
SKU if displayed
Image if applicable
Availability
```

Invalid combinations should be disabled.

---

# 12. ADD TO CART UX

Before adding:

```text
Product active?
Variant selected?
Variant valid?
Quantity valid?
Stock available?
```

Display useful feedback:

```text
Added to cart successfully.
```

Do not require a page refresh.

Prevent double submissions.

---

# 13. CART UI

Check:

```text
Product Image
Product Name
Variant
Attributes
Price
Quantity
Subtotal
Remove
```

Order summary:

```text
Subtotal
Discount
Shipping if known
Total
```

Cart UI must integrate correctly with backend calculations.

Never treat frontend totals as the financial source of truth.

---

# 14. WISHLIST UI

Check:

```text
Add to Wishlist
Remove
View Wishlist
Move to Cart
Product Availability
```

Prevent duplicate wishlist items.

Handle unavailable products gracefully.

---

# 15. CHECKOUT UI

Checkout should be simple.

Recommended flow:

```text
Cart
 ↓
Address
 ↓
Shipping
 ↓
Coupon
 ↓
Payment
 ↓
Order Review
 ↓
Place Order
```

Do not add unnecessary steps.

---

# 16. CHECKOUT REVIEW

Before placing the order clearly show:

```text
Products
Variants
Quantity
Price
Subtotal
Discount
Shipping
Tax if applicable
Grand Total
Shipping Address
Shipping Method
Payment Method
```

Do not hide important charges.

---

# 17. ORDER SUCCESS PAGE

After successful checkout show:

```text
Order placed successfully.

Order Number
Order Total
Payment Status
Order Status
```

Provide actions:

```text
View Order
Continue Shopping
```

Do not leave customers unsure whether checkout succeeded.

---

# 18. CUSTOMER ORDERS

Customer should be able to:

```text
View Orders
View Order Details
Filter if useful
Track Order
Cancel eligible order
Review delivered products
```

Never expose another customer's order.

---

# 19. ORDER DETAILS

Display:

```text
Order Number
Date
Items
Variants
Quantity
Prices
Discount
Shipping
Grand Total
Payment Status
Order Status
Shipping Status
Address
Tracking
```

Make statuses easy to understand.

---

# 20. PROFILE

Audit:

```text
Profile Information
Edit Profile
Change Password
Addresses
Orders
Wishlist
Reviews
Notifications
Logout
```

Check all API integrations.

---

# 21. REVIEWS

Fully audit the Review feature.

Customer should be able to:

```text
View Reviews
Create Review
Edit Own Review
Delete Own Review
Upload Review Images
```

Admin functionality may include moderation if supported.

---

# 22. REVIEW BUSINESS LOGIC

Determine and enforce the project's review policy.

Recommended E-Commerce rule:

Only customers who purchased the product should be allowed to create a verified review.

Possible flow:

```text
Customer
 ↓
Delivered Order
 ↓
Purchased Product
 ↓
Write Review
```

If the project intentionally allows all authenticated users to review, preserve that requirement instead.

---

# 23. REVIEW VALIDATION

Validate:

```text
Rating
Comment
Product
Images
```

Rating should normally be:

```text
1–5
```

Reject:

```text
0
6
-1
Invalid text
```

Backend validation remains mandatory.

---

# 24. DUPLICATE REVIEWS

Define the business rule.

A reasonable rule:

```text
One Customer
+
One Product
=
One Review
```

unless the project intentionally supports multiple reviews from repeat purchases.

Enforce the chosen rule consistently.

---

# 25. REVIEW OWNERSHIP

Customer A must NOT:

```text
Edit Customer B's Review
Delete Customer B's Review
Modify Customer B's Review Images
```

Do not rely only on hidden frontend buttons.

Laravel authorization must enforce ownership.

---

# 26. REVIEW DISPLAY

Display:

```text
Customer Name
Rating
Comment
Date
Images
Verified Purchase if supported
```

Do not expose:

```text
Email
Phone
Address
Private User Data
```

---

# 27. AVERAGE RATING

Calculate rating correctly.

Example:

```text
5★ → 10
4★ → 5
3★ → 2
2★ → 1
1★ → 0

Average Rating
Total Reviews
```

Do not download all reviews into Vue just to calculate the average if the backend can efficiently aggregate it.

---

# 28. REVIEW FILTERING

If useful support:

```text
Newest
Highest Rating
Lowest Rating
5 Stars
4 Stars
3 Stars
2 Stars
1 Star
```

Use backend filtering/pagination for large datasets.

---

# 29. REVIEW IMAGES

Audit:

```text
Upload
Preview
Multiple Images
Remove Before Submit
Delete Existing Image
Display Gallery
Validation
Storage
```

Check:

```text
File Type
File Size
Image Count
Image Validity
```

Never trust file extensions alone.

---

# 30. REVIEW IMAGE SECURITY

Prevent:

```text
Executable uploads
Invalid MIME types
Huge files
Unsafe filenames
Path manipulation
```

Use the existing Laravel storage architecture.

Do not expose internal server paths.

---

# 31. REVIEW IMAGE UX

Before submitting:

```text
Select Images
 ↓
Preview
 ↓
Remove unwanted image
 ↓
Submit Review
```

After submission, display images cleanly.

Handle broken/missing images gracefully.

---

# 32. API INTEGRATION AUDIT

Audit every frontend API call.

Check:

```text
Correct URL
Correct HTTP Method
Correct Request Data
Correct Authentication
Correct Response Handling
Correct Error Handling
Loading State
Retry behavior where appropriate
```

Find:

```text
Broken APIs
Unused APIs
Duplicate APIs
Duplicate requests
Hard-coded URLs
Incorrect endpoint paths
```

---

# 33. CENTRALIZE API CONFIGURATION

Avoid code like:

```javascript
axios.get("http://localhost:8000/api/products")
```

throughout many components.

Use the project's centralized API configuration.

Example concept:

```text
API Base URL
   ↓
Axios Instance
   ↓
API Services
   ↓
Vue Components
```

Use environment variables appropriately.

---

# 34. API SERVICE STRUCTURE

Where appropriate, organize API services logically:

```text
authApi
productApi
cartApi
wishlistApi
orderApi
reviewApi
notificationApi
```

Do not create unnecessary abstraction if a good structure already exists.

---

# 35. AUTHENTICATION API

Check:

```text
Register
Login
Logout
Current User
Protected Requests
Expired Authentication
401 Handling
403 Handling
```

Avoid unnecessary repeated `/me` requests.

---

# 36. HTTP ERROR HANDLING

Handle:

```text
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
422 Validation Error
429 Too Many Requests
500 Server Error
503 Service Unavailable
```

Do not show:

```text
AxiosError
SQLSTATE
Stack Trace
```

to customers.

---

# 37. VALIDATION ERRORS

Laravel validation:

```json
{
    "errors": {
        "email": [
            "The email field is required."
        ]
    }
}
```

should display near the correct field where possible.

Do not display only:

```text
Request failed with status code 422
```

---

# 38. LOADING STATES

Every API-driven page should have an appropriate loading state.

Examples:

```text
Loading products...
Loading order...
Loading reviews...
Processing checkout...
Saving review...
```

Use skeletons where useful.

Do NOT add excessive spinners everywhere.

---

# 39. EMPTY STATES

Handle:

```text
No Products
No Search Results
Empty Cart
Empty Wishlist
No Orders
No Reviews
No Notifications
```

Provide useful next actions.

Example:

```text
Your wishlist is empty.

Browse Products
```

---

# 40. ERROR STATES

If an API fails:

```text
Unable to load products.

[ Try Again ]
```

Do not leave blank pages.

Where appropriate provide retry functionality.

---

# 41. FRONTEND PERFORMANCE

Audit:

```text
Vue re-renders
Watchers
Computed properties
State updates
API calls
Images
Large components
Large bundles
Unused packages
```

Find actual performance bottlenecks.

---

# 42. DUPLICATE API REQUESTS

Use browser Network tools to identify:

```text
/api/products
/api/products
/api/products
```

being called unnecessarily.

Fix duplicate requests.

Do not simply cache everything without understanding the cause.

---

# 43. PARALLEL API REQUESTS

If independent data is required:

Bad:

```text
Load Products
 ↓
Wait
 ↓
Load Categories
 ↓
Wait
 ↓
Load Brands
```

Where safe, improve to:

```text
Products ───────┐
Categories ─────┼──→ Render
Brands ─────────┘
```

using appropriate parallel requests.

---

# 44. LAZY LOADING

Use route-level lazy loading where appropriate.

Example concept:

```javascript
() => import(...)
```

Avoid loading the entire application bundle unnecessarily on first load.

---

# 45. IMAGE PERFORMANCE

Audit:

```text
Product Images
Review Images
Category Images
Brand Logos
User Images if supported
```

Check:

```text
File Size
Dimensions
Lazy Loading
Fallback Images
Broken URLs
```

Use:

```html
loading="lazy"
```

where appropriate.

---

# 46. RESPONSIVE DESIGN

Test:

```text
Mobile
Tablet
Desktop
```

Important pages:

```text
Home
Product List
Product Details
Cart
Wishlist
Checkout
Orders
Order Details
Profile
Reviews
```

Fix:

```text
Horizontal Overflow
Broken Grids
Tiny Buttons
Overlapping Text
Broken Images
Unusable Forms
```

---

# 47. MOBILE NAVIGATION

Check:

```text
Menu
Search
Cart
Wishlist
Profile
Notifications
```

Make common actions easy to reach.

---

# 48. ACCESSIBILITY

Check basic accessibility:

```text
Labels
Button names
Alt text
Keyboard navigation
Focus states
Form errors
Semantic HTML
Contrast
```

Do not remove useful focus indicators without providing an accessible replacement.

---

# 49. UI CONSISTENCY

Review the whole customer website for consistent:

```text
Buttons
Inputs
Cards
Spacing
Typography
Icons
Modals
Alerts
Tables
Pagination
Loading States
Empty States
```

Reuse existing components.

Do not create five different button designs for the same action.

---

# 50. CUSTOMER UX

Look for unnecessary friction.

Ask:

```text
Can customer understand this page?
Is the next action obvious?
Are there too many clicks?
Is the form asking unnecessary information?
Is the error understandable?
Is loading too slow?
Does navigation preserve useful state?
```

Improve problems without changing correct business rules.

---

# 51. TOAST / FEEDBACK

Provide useful feedback for actions:

```text
Added to cart.
Removed from wishlist.
Profile updated.
Review submitted.
Order cancelled.
```

Avoid excessive popups.

Use the existing notification/toast system if available.

---

# 52. FORM DOUBLE SUBMISSION

Prevent:

```text
Submit
Submit
Submit
```

while a request is processing.

Examples:

```text
Signing in...
Saving...
Submitting review...
Placing order...
```

Disable relevant controls during critical requests.

Backend must still protect critical operations.

---

# 53. ROUTE PROTECTION

Verify customer protected routes:

```text
/profile
/orders
/wishlist
/checkout
/notifications
```

according to project requirements.

Guest-accessible routes normally include:

```text
/
/products
/products/:slug
/categories
/search
/login
/register
```

Do not unnecessarily require login just to browse products.

---

# 54. 404 PAGE

Ensure invalid frontend routes display a useful:

```text
404 — Page Not Found
```

with navigation back to useful pages.

---

# 55. API 404

If:

```text
/products/not-existing-product
```

does not exist, show a proper product-not-found state.

Do not crash the Vue application.

---

# 56. QA — FUNCTIONAL TESTING

Test all major customer features:

```text
Register
Login
Logout

Browse Products
Search
Filter
Sort
Pagination
Product Details
Variants

Cart
Wishlist
Checkout
Coupon
Address
Shipping
Payment

Orders
Order Details
Tracking

Reviews
Review Images

Profile
Notifications
```

---

# 57. QA — API TESTING

Test important endpoints with:

```text
Valid Request
Invalid Request
Unauthenticated Request
Unauthorized Request
Missing Data
Invalid Data
Not Found
Duplicate Request
```

Verify:

```text
HTTP status
Response structure
Validation
Authorization
Database changes
```

---

# 58. QA — SECURITY TESTING

Test:

```text
IDOR
Authorization
Ownership
Price Manipulation
Quantity Manipulation
Coupon Manipulation
Shipping Manipulation
Order Manipulation
Review Ownership
File Upload Validation
XSS-related Input
Mass Assignment
```

Example:

Customer A must not access:

```text
Customer B's Order
Customer B's Address
Customer B's Review Management
Customer B's Notification
```

---

# 59. QA — REVIEW TESTS

Test:

```text
Create Review
Edit Own Review
Delete Own Review
Invalid Rating
Empty Comment
Review Without Purchase
Duplicate Review
Upload Image
Invalid Image
Huge Image
Delete Review Image
Unauthorized Review Update
```

Adapt purchased-product restrictions to the actual business rule.

---

# 60. QA — CART TESTS

Test:

```text
Add Simple Product
Add Variant Product
Add Same Item Twice
Update Quantity
Quantity = 0
Quantity > Stock
Remove
Clear Cart
Out-of-Stock Product
Deleted Product
Price Changed
```

---

# 61. QA — CHECKOUT TESTS

Test:

```text
Empty Cart
Invalid Address
Invalid Shipping
Invalid Coupon
Expired Coupon
Out of Stock
Price Changed
Payment Success
Payment Failure
Double Click Place Order
Network Failure
```

---

# 62. QA — ORDER TESTS

Test:

```text
View Own Order
View Another Customer's Order
Order Details
Cancel Eligible Order
Cancel Invalid Order
Tracking
Payment Status
Shipment Status
```

---

# 63. QA — RESPONSIVE TESTING

Test common viewport sizes.

Check:

```text
Navigation
Forms
Product Grid
Product Detail
Cart
Checkout
Orders
Reviews
Modals
Tables
```

No horizontal overflow should occur unexpectedly.

---

# 64. QA — BROWSER CONSOLE

Check every major flow for:

```text
JavaScript Errors
Vue Warnings
Unhandled Promise Rejections
404 Assets
Failed API Calls
CORS Errors
```

Fix actual problems.

Do not simply suppress warnings.

---

# 65. QA — NETWORK

Inspect:

```text
Number of Requests
Duplicate Requests
Slow Requests
Large Responses
Large Images
Failed Requests
```

Identify the slowest important requests.

Optimize actual bottlenecks.

---

# 66. QA — PERFORMANCE

Measure real performance before and after meaningful optimizations.

Check:

```text
Initial Page Load
Home
Product List
Product Details
Search
Cart
Checkout
Orders
```

Do NOT invent performance numbers.

Record actual observations.

---

# 67. QA — REGRESSION TESTING

After every significant fix, ensure existing functionality still works.

Critical flow:

```text
Register/Login
     ↓
Browse
     ↓
Product
     ↓
Cart
     ↓
Checkout
     ↓
Payment
     ↓
Order
     ↓
Shipping
     ↓
Review
```

Do not stop testing after the individual bug is fixed.

---

# 68. AUTOMATED FRONTEND TESTS

Use the project's existing testing framework if available.

Do not introduce a large new testing stack without reason.

Add tests for critical components/flows where practical.

Focus on:

```text
Rendering
User Interaction
API States
Validation
Conditional UI
Error Handling
```

---

# 69. BACKEND TEST COORDINATION

If frontend testing reveals a backend bug:

Do not hide it with frontend workarounds.

Document:

```text
Endpoint
Request
Expected Response
Actual Response
Business Impact
```

Fix it if it is within the task's integration scope, or clearly report it for the responsible backend member.

---

# 70. MOCK DATA

Do not leave production/customer pages using mock data when real APIs are available.

Search for:

```text
Hard-coded Products
Fake Reviews
Fake Orders
Fake Users
Fake Notifications
Temporary JSON
```

Replace temporary data with real API integration where appropriate.

Keep legitimate test fixtures in test environments.

---

# 71. ENVIRONMENT CONFIGURATION

Check:

```text
.env
VITE_API_URL
API Base URL
Development Configuration
Production Configuration
```

Do not hard-code:

```text
localhost
127.0.0.1
```

throughout components.

Use environment configuration.

Never expose backend secrets in frontend environment variables.

---

# 72. ERROR LOGGING

Frontend errors should be useful for development without exposing sensitive information to customers.

Backend errors should be logged appropriately.

Do not log:

```text
Passwords
Tokens
Payment Secrets
Private Credentials
```

---

# 73. COMPLETE CUSTOMER JOURNEY

Perform this full test:

```text
Open Website
      ↓
Home
      ↓
Search Product
      ↓
Filter Products
      ↓
Product Details
      ↓
Select Variant
      ↓
Add Wishlist
      ↓
Move to Cart
      ↓
Update Quantity
      ↓
Checkout
      ↓
Login/Register
      ↓
Select Address
      ↓
Apply Coupon
      ↓
Select Shipping
      ↓
Select Payment
      ↓
Place Order
      ↓
Payment
      ↓
Order Confirmation
      ↓
Order Details
      ↓
Shipment Tracking
      ↓
Delivered
      ↓
Write Review
      ↓
Upload Review Images
```

Every step must work together.

---

# 74. TEST FAILURE JOURNEY

Also test:

```text
Add Product
      ↓
Product becomes unavailable
      ↓
Cart detects problem
      ↓
Checkout blocked correctly
```

Test:

```text
Checkout
      ↓
Payment fails
      ↓
Useful error
      ↓
No duplicate order/payment
      ↓
Retry if supported
```

Test:

```text
Submit Review
      ↓
Image upload fails
      ↓
Application handles failure safely
```

---

# 75. BUG CLASSIFICATION

Classify bugs:

```text
P0 — CRITICAL
P1 — HIGH
P2 — MEDIUM
P3 — LOW
```

Examples:

P0:

```text
Checkout broken
Payment duplication
Customer data exposure
App crashes
```

P1:

```text
Cart incorrect
API integration broken
Order inaccessible
Review authorization bug
```

P2:

```text
Responsive issue
Slow page
Incorrect empty state
```

P3:

```text
Spacing
Minor UI inconsistency
Minor wording
```

Fix higher-priority problems first.

---

# 76. BUG REPORT FORMAT

For every meaningful bug record:

```text
Bug ID:
Feature:
Priority:
Environment:
Steps to Reproduce:
Expected:
Actual:
Root Cause:
Fix:
Test Result:
Status:
```

Do not mark:

```text
FIXED
```

without retesting.

---

# 77. DEFINITION OF DONE — CUSTOMER WEBSITE

```text
[ ] Home works
[ ] Navigation works
[ ] Product list works
[ ] Search works
[ ] Filters work
[ ] Product details work
[ ] Variants work
[ ] Cart works
[ ] Wishlist works
[ ] Checkout works
[ ] Orders work
[ ] Tracking works
[ ] Profile works
[ ] Notifications work
[ ] Responsive design works
```

---

# 78. DEFINITION OF DONE — REVIEWS

```text
[ ] Review list works
[ ] Create works
[ ] Update own review works
[ ] Delete own review works
[ ] Rating works
[ ] Business rules work
[ ] Ownership works
[ ] Pagination works
[ ] Average rating works
```

---

# 79. DEFINITION OF DONE — REVIEW IMAGES

```text
[ ] Upload works
[ ] Preview works
[ ] Multiple images work if supported
[ ] Remove works
[ ] Validation works
[ ] Storage works
[ ] Security checked
[ ] Lazy loading works where appropriate
```

---

# 80. DEFINITION OF DONE — API INTEGRATION

```text
[ ] API base URL centralized
[ ] No unnecessary hard-coded URLs
[ ] Authentication works
[ ] 401 handled
[ ] 403 handled
[ ] 404 handled
[ ] 422 handled
[ ] 500 handled
[ ] Loading states work
[ ] Error states work
[ ] Duplicate requests removed
[ ] Pagination integrated
```

---

# 81. DEFINITION OF DONE — QA

```text
[ ] Functional tests completed
[ ] API tests completed
[ ] Security checks completed
[ ] Responsive testing completed
[ ] Browser console checked
[ ] Network checked
[ ] Performance checked
[ ] Edge cases tested
[ ] Regression testing completed
[ ] Critical bugs fixed
```

---

# 82. CROSS-MODULE INTEGRATION

Verify:

```text
Authentication
      ↓
Customer Website
      ↓
Products
      ↓
Variants
      ↓
Wishlist
      ↓
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
Shipment
      ↓
Notification
      ↓
Review
```

The frontend should represent this as one connected experience.

---

# 83. DO NOT OVERENGINEER

Do NOT:

- rewrite the entire frontend without reason
- replace the existing design system unnecessarily
- add unnecessary dependencies
- create duplicate components
- create duplicate API services
- change working APIs without checking backend impact
- add animations everywhere
- hide performance problems behind loading screens
- disable errors instead of fixing them

Prefer:

```text
Simple
Reusable
Responsive
Maintainable
Fast
Consistent
```

solutions.

---

# 84. FINAL QA DOCUMENT

Create:

```text
docs/FRONTEND_QA_AUDIT.md
```

Include:

```markdown
# E-KHMER Frontend & QA Audit

## Executive Summary

## Customer Website Audit

## Page-by-Page Status

## Reviews

## Review Images

## API Integration

## Responsive Design

## User Experience

## Accessibility

## Performance

## Security Testing

## Functional Testing

## API Testing

## Browser Testing

## Customer Journey Testing

## Bugs Found

## Bugs Fixed

## Remaining Bugs

## Automated Tests

## Performance Improvements

## Recommendations

## Final Status
```

---

# 85. FINAL BUG REPORT

Also create:

```text
docs/BUG_REPORT.md
```

Use:

```markdown
| ID | Feature | Priority | Problem | Root Cause | Fix | Test | Status |
|----|---------|----------|---------|------------|-----|------|--------|
```

Do not mark a bug fixed without verification.

---

# 86. FINAL STATUS

Report:

```text
Customer Website: PASS / NEEDS WORK
Responsive Design: PASS / NEEDS WORK

Reviews: PASS / NEEDS WORK
Review Images: PASS / NEEDS WORK

API Integration: PASS / NEEDS WORK
Authentication Integration: PASS / NEEDS WORK
Product Integration: PASS / NEEDS WORK
Cart Integration: PASS / NEEDS WORK
Checkout Integration: PASS / NEEDS WORK
Order Integration: PASS / NEEDS WORK
Shipping Integration: PASS / NEEDS WORK
Notification Integration: PASS / NEEDS WORK

Functional Testing: PASS / NEEDS WORK
API Testing: PASS / NEEDS WORK
Security Testing: PASS / NEEDS WORK
Responsive Testing: PASS / NEEDS WORK
Performance: PASS / NEEDS WORK
Regression Testing: PASS / NEEDS WORK
```

Also report:

```text
Pages checked:
Features checked:
Missing features completed:
Bugs found:
Critical bugs fixed:
Remaining bugs:
API issues fixed:
UI issues fixed:
Responsive issues fixed:
Security issues fixed:
Performance issues fixed:
Tests added:
Tests passed:
Tests failed:
```

---

# 87. EXECUTION ORDER

Follow this order:

```text
INSPECT PROJECT
      ↓
UNDERSTAND FRONTEND ARCHITECTURE
      ↓
MAP ALL CUSTOMER PAGES
      ↓
AUDIT ROUTES
      ↓
AUDIT API INTEGRATION
      ↓
AUDIT HOME
      ↓
AUDIT PRODUCTS
      ↓
AUDIT PRODUCT DETAILS
      ↓
AUDIT CART
      ↓
AUDIT WISHLIST
      ↓
AUDIT CHECKOUT
      ↓
AUDIT ORDERS
      ↓
AUDIT PROFILE
      ↓
AUDIT NOTIFICATIONS
      ↓
AUDIT REVIEWS
      ↓
AUDIT REVIEW IMAGES
      ↓
CHECK RESPONSIVE DESIGN
      ↓
CHECK ACCESSIBILITY
      ↓
CHECK PERFORMANCE
      ↓
RUN SECURITY TESTS
      ↓
IDENTIFY MISSING FEATURES
      ↓
FIX P0 BUGS
      ↓
FIX P1 BUGS
      ↓
COMPLETE MISSING FEATURES
      ↓
IMPROVE UX
      ↓
OPTIMIZE PERFORMANCE
      ↓
TEST APIs
      ↓
TEST MOBILE
      ↓
TEST TABLET
      ↓
TEST DESKTOP
      ↓
RUN COMPLETE CUSTOMER JOURNEY
      ↓
RUN FAILURE SCENARIOS
      ↓
RUN REGRESSION TESTS
      ↓
GENERATE QA REPORT
```

# 88. MOST IMPORTANT REQUIREMENT

Do NOT only inspect the project and tell me what is wrong.

Actually fix the code.

If a feature is:

```text
MISSING
```

implement it when it belongs to this task.

If it is:

```text
PARTIAL
```

complete it.

If it is:

```text
BROKEN
```

find the root cause and fix it.

If it is:

```text
SLOW
```

measure and optimize the real bottleneck.

If it has:

```text
BAD UX
```

improve it without breaking the business logic.

If an API integration is broken:

```text
Frontend
   ↓
Request
   ↓
Laravel API
   ↓
Database
   ↓
Response
   ↓
Frontend State
   ↓
UI
```

trace the complete flow and fix the actual root cause.

Do not hide backend problems with frontend workarounds.

Do not mark anything COMPLETE without testing it.

The final customer journey must work smoothly:

```text
Home
 ↓
Products
 ↓
Product Details
 ↓
Wishlist / Cart
 ↓
Checkout
 ↓
Payment
 ↓
Order
 ↓
Shipment
 ↓
Notification
 ↓
Review
```

Final priority:

**Working Features → Correct API Integration → Business Logic → Security → Customer UX → Responsive Design → Performance → Testing → Maintainable Code.**