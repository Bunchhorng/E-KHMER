# E-KHMER — Authentication, Users & Core Business Logic Audit

Act as a senior **Laravel + Vue.js E-Commerce Architect, Backend Engineer, Security Engineer, and UX Reviewer**.

I am developing an existing E-Commerce application called **E-KHMER** using:

- Laravel
- Vue.js
- MySQL
- REST API
- Docker
- Git/GitHub

Your responsibility is to inspect, test, fix, and improve these areas:

1. Authentication
2. Users
3. Addresses
4. Project Integration
5. Git Management
6. Code Review

The main goal is NOT just to make the code work.

The goal is to make the **business logic correct, secure, simple, fast, and user-friendly for a real E-Commerce application**.

Do not only give recommendations.

Actually inspect the existing code, identify problems, implement improvements, test them, and report the changes.

---

# 1. AUDIT FIRST

Before changing code, inspect:

```text
Laravel routes
Controllers
Models
Services
Repositories
Middleware
Policies
Form Requests
API Resources
Migrations
Seeders
Vue pages
Vue components
Router
State management
Axios/API services
Authentication storage
Error handling
Docker configuration
Git configuration
```

For every feature classify it as:

```text
COMPLETE
PARTIAL
BROKEN
MISSING
NEEDS IMPROVEMENT
```

Also classify priority:

```text
CRITICAL
HIGH
MEDIUM
LOW
```

Do not mark anything COMPLETE without testing it.

---

# 2. AUTHENTICATION

Audit the complete authentication flow.

Check:

```text
Register
Login
Logout
Current User / Me
Forgot Password
Reset Password
Change Password
Email verification if used
Session/token handling
Role authorization
Protected routes
Guest routes
```

## Registration Business Logic

A customer should be able to register easily.

Recommended flow:

```text
Register
   ↓
Validate Data
   ↓
Check Duplicate Email
   ↓
Hash Password
   ↓
Create Customer
   ↓
Login automatically OR redirect to login
   ↓
Continue Shopping
```

Check validation for:

```text
Name
Email
Phone
Password
Password confirmation
```

Do not request unnecessary information during registration.

Do NOT require the customer to enter:

```text
Address
City
Postal Code
Shipping information
```

unless the business specifically requires it.

Those can be collected during checkout or profile setup.

Improve validation messages.

Bad:

```text
422 Validation Error
```

Better:

```text
This email address is already registered.
```

---

# 3. LOGIN EXPERIENCE

Login must be:

- fast
- secure
- clear
- predictable

Check:

```text
Valid credentials
Invalid email
Invalid password
Inactive account
Unauthorized role
Expired authentication
Network error
Server error
```

The frontend must display useful messages.

Example:

```text
Incorrect email or password.
```

Do not expose sensitive information.

After successful login:

Customer:

```text
Login
   ↓
Return to previous page if appropriate
OR
Customer Home
```

Admin:

```text
Login
   ↓
Admin Dashboard
```

Avoid unnecessary page reloads.

---

# 4. AUTHENTICATION STATE

Check whether the Vue frontend makes unnecessary `/me` requests.

Avoid:

```text
Page Load
→ /me

Navigate
→ /me

Open Product
→ /me

Open Cart
→ /me
```

when authentication state can safely be reused.

Create a clean authentication state strategy.

Example:

```text
Application starts
      ↓
Restore authentication
      ↓
Load current user
      ↓
Store user state
      ↓
Reuse state across pages
```

Make sure logout clears:

```text
Authentication token/session
User state
Sensitive cached data
Cart state if appropriate
```

---

# 5. AUTHORIZATION

Authentication and authorization must be separated correctly.

Check roles such as:

```text
Admin
Customer
```

If additional roles exist, inspect them too.

Customer must NOT access:

```text
Admin Dashboard
Product Management
User Management
Order Administration
Inventory Management
Reports
Settings
```

Admin permissions must be enforced on the backend.

Do NOT rely only on Vue route guards.

Correct security:

```text
Vue Route Guard
        +
Laravel Middleware / Policy
        +
Backend Authorization
```

---

# 6. USER MANAGEMENT

Audit the `users` feature.

Check:

```text
Create user
View users
View user details
Update user
Delete/deactivate user
Search
Filter
Sort
Pagination
Role
Status
```

User management should follow real E-Commerce business logic.

Recommended user data:

```text
id
name
email
phone
password
role
status
email_verified_at
created_at
updated_at
```

Do not expose:

```text
password
remember_token
sensitive authentication data
```

through APIs.

---

# 7. CUSTOMER ACCOUNT STATUS

If appropriate for the existing project, support:

```text
Active
Inactive
Suspended
```

Business logic:

```text
ACTIVE
→ Login allowed

INACTIVE
→ Login blocked or restricted according to business rules

SUSPENDED
→ Login blocked
```

Display a user-friendly message.

Do not permanently delete customer accounts unnecessarily.

Prefer safe deactivation/soft deletion when appropriate.

Do not add this if it conflicts with existing requirements.

---

# 8. CUSTOMER PROFILE

Customer should have a simple account page.

Check or implement:

```text
Profile
Edit Profile
Change Password
Addresses
Orders
Wishlist
Reviews
Logout
```

Profile editing should allow:

```text
Name
Email
Phone
Avatar if supported
```

Changing email may require additional verification depending on the current authentication design.

---

# 9. ADDRESS MANAGEMENT

Audit the complete Address feature.

Customer should be able to:

```text
Add Address
View Addresses
Edit Address
Delete Address
Set Default Address
Select Address During Checkout
```

Recommended fields:

```text
id
user_id
recipient_name
phone
address_line_1
address_line_2
city
province/state
postal_code
country
is_default
```

Adapt these fields to the actual E-KHMER database.

Do not create duplicate fields if equivalent fields already exist.

---

# 10. ADDRESS BUSINESS RULES

Implement clear rules.

### Ownership

A customer may only:

```text
View their own addresses
Create their own addresses
Update their own addresses
Delete their own addresses
```

Never trust:

```text
user_id
```

sent from the frontend.

Determine the owner from the authenticated user.

Example concept:

```php
$request->user()
```

---

# 11. DEFAULT ADDRESS

A customer should normally have only one default address.

Example:

```text
Address A → Default
Address B
Address C
```

When customer selects Address B as default:

```text
Address A → Not Default
Address B → Default
Address C → Not Default
```

Ensure this operation is safe and consistent.

Consider using a database transaction.

---

# 12. ADDRESS DELETION

Handle cases such as:

```text
Deleting normal address
Deleting default address
Deleting last address
Address already used by an order
```

Important:

Historical orders must NOT lose their shipping information because the customer deleted their saved address.

Order shipping data should remain historically accurate.

If the current system directly depends on the live address record, inspect and improve the design safely.

---

# 13. CHECKOUT ADDRESS UX

Checkout should be easy.

Recommended flow:

```text
Checkout
   ↓
Existing Address?
   ↓
YES ──→ Select Address
   ↓
NO
   ↓
Add Address
   ↓
Shipping Method
   ↓
Payment
   ↓
Review Order
   ↓
Place Order
```

Do not force an existing customer to re-enter the same address for every order.

Allow:

```text
Use saved address
Add new address
Edit address
Set default address
```

---

# 14. GUEST VS AUTHENTICATED CUSTOMER

Inspect existing business requirements.

Determine whether E-KHMER supports:

```text
Guest browsing
Guest cart
Guest checkout
Authenticated checkout
```

At minimum, users should normally be able to browse products without logging in.

Avoid forcing login for:

```text
Home
Product List
Product Details
Search
Categories
Brands
```

Authentication should only be required where the business logic requires it.

---

# 15. CART + LOGIN INTEGRATION

Check integration between Authentication and Cart.

Example scenario:

```text
Guest adds Product A
Guest adds Product B

↓

Guest logs in

↓

Cart should not unexpectedly disappear.
```

If guest cart support exists, inspect whether guest and customer carts should be merged.

Prevent duplicate cart items.

Do not implement guest cart if it conflicts with the project's requirements; document the decision.

---

# 16. WISHLIST + AUTHENTICATION

Check:

```text
Add wishlist item
Remove wishlist item
View wishlist
Login requirement
Ownership
Duplicate prevention
```

If customer tries to add a wishlist item while logged out:

Provide a friendly login flow.

After login, consider returning them to the previous product/page.

---

# 17. ORDER + USER INTEGRATION

Check relationships:

```text
User
 ↓
Orders
 ↓
Order Items
 ↓
Payment
 ↓
Shipment
```

Customer must only see their own orders.

Admin can see orders according to permissions.

Test:

```text
/api/orders/1
/api/orders/2
```

A customer must never access another customer's order by changing an ID.

---

# 18. API SECURITY

Audit all related endpoints.

Check:

```text
Authentication
Authorization
Ownership
Validation
Mass assignment
Rate limiting
Sensitive data exposure
IDOR
SQL injection
XSS-related data handling
```

Especially test ownership attacks such as:

```text
/api/users/2
/api/addresses/5
/api/orders/10
/api/reviews/20
```

A logged-in customer must not gain access to another customer's private resources simply by changing IDs.

---

# 19. API RESPONSE STANDARD

Make API responses consistent.

Example success:

```json
{
  "success": true,
  "message": "Profile updated successfully.",
  "data": {}
}
```

Example error:

```json
{
  "success": false,
  "message": "Unable to update profile.",
  "errors": {}
}
```

Do not blindly change every existing endpoint if the project already has a good response standard.

Use one consistent approach.

---

# 20. VALIDATION UX

Backend validation is mandatory.

Frontend validation should improve UX.

Example:

```text
Email is required.
Please enter a valid email address.
Password must contain at least 8 characters.
Phone number is invalid.
Address is required.
```

Do not rely only on frontend validation.

Laravel must remain the source of truth.

---

# 21. LOADING UX

Every async action should clearly communicate its state.

Examples:

```text
Login...
Saving...
Updating...
Deleting...
Loading addresses...
```

Disable buttons while critical requests are running.

Example:

```text
[ Sign In ]
```

becomes:

```text
[ Signing In... ]
```

Prevent double submission.

---

# 22. ERROR UX

Do not show technical errors directly to customers.

Bad:

```text
SQLSTATE[23000]
AxiosError
500 Internal Server Error
```

Customer-facing UI should display understandable messages.

Technical errors should be logged appropriately for developers.

---

# 23. SUCCESS FEEDBACK

Provide clear feedback after important actions.

Examples:

```text
Account created successfully.
Welcome back!
Profile updated successfully.
Address added successfully.
Default address updated.
Password changed successfully.
```

Avoid unnecessary alerts that interrupt normal browsing.

Use the project's existing toast/notification system if available.

---

# 24. PROJECT INTEGRATION

Check whether these modules integrate correctly:

```text
Authentication
   ↓
User
   ↓
Profile
   ↓
Address
   ↓
Cart
   ↓
Checkout
   ↓
Order
   ↓
Payment
   ↓
Shipping
   ↓
Notification
```

Do not test Authentication or Users in isolation only.

Test complete business flows.

---

# 25. CUSTOMER BUSINESS FLOW

Test this complete scenario:

```text
Customer opens website
        ↓
Browse products
        ↓
Search/filter products
        ↓
View product
        ↓
Add to cart
        ↓
Register/Login
        ↓
Cart remains correct
        ↓
Checkout
        ↓
Select/Add Address
        ↓
Select Shipping
        ↓
Select Payment
        ↓
Place Order
        ↓
Order Confirmation
        ↓
View Order
        ↓
Track Order
```

Identify anything confusing, unnecessary, broken, or slow.

Improve the flow where appropriate.

---

# 26. ADMIN BUSINESS FLOW

Test:

```text
Admin Login
   ↓
Dashboard
   ↓
Customers
   ↓
Customer Details
   ↓
Orders
   ↓
Payments
   ↓
Shipping
```

Admin should be able to understand a customer's important information without navigating through unnecessary pages.

Do not expose sensitive information.

---

# 27. PERFORMANCE

Check these features for performance:

```text
Login
/me
User list
User details
Addresses
Profile
Orders
Admin customer list
```

Find:

```text
Duplicate API requests
N+1 queries
Large API responses
Unnecessary SELECT *
Missing pagination
Missing indexes
Unnecessary re-renders
```

Optimize actual bottlenecks.

---

# 28. GIT MANAGEMENT

Audit Git project hygiene.

Check:

```text
.gitignore
Branches
Commit history
Secrets
.env
Generated files
Large unnecessary files
Merge conflicts
```

Never commit:

```text
.env
API secrets
Passwords
Tokens
Private keys
Database credentials
```

Check whether sensitive information was accidentally committed.

Do not print secret values in the report.

---

# 29. BRANCH STRATEGY

Use the existing project strategy where possible.

Recommended structure:

```text
main
develop
feature/*
fix/*
```

For this module, a branch could be:

```text
feature/auth-users
```

Do not create unnecessary branches automatically if the team already has an established workflow.

---

# 30. COMMIT QUALITY

Commits should be small and understandable.

Examples:

```text
feat(auth): improve customer login flow

feat(address): add default address handling

fix(auth): prevent duplicate login requests

fix(user): enforce customer resource ownership

perf(user): optimize customer list query

refactor(auth): simplify authentication state

test(address): add address ownership tests
```

Do not mix many unrelated changes into one commit.

---

# 31. CODE REVIEW

Review changed code for:

```text
Correctness
Business logic
Security
Performance
Readability
Maintainability
Laravel conventions
Vue conventions
Error handling
Validation
Authorization
Database integrity
UX
```

Look for:

```text
Duplicate code
Huge controllers
Unused imports
Dead code
Hard-coded values
Repeated queries
Missing validation
Missing authorization
Incorrect relationships
Poor naming
Business logic inside Vue components
```

Move important business logic to the appropriate backend layer when necessary.

---

# 32. AUTOMATED TESTING

Create or improve tests for critical business rules.

At minimum test:

```text
Register
Login
Logout
Protected routes
Role authorization
Profile update
Address creation
Address update
Address deletion
Default address
Address ownership
User ownership
Order ownership
Invalid authentication
Invalid validation
```

Especially test:

```text
User A cannot access User B's address.
User A cannot edit User B's address.
User A cannot delete User B's address.
User A cannot view User B's order.
Customer cannot access admin endpoints.
```

---

# 33. EDGE CASES

Test:

```text
Duplicate email
Invalid email
Wrong password
Expired authentication
Deleted/deactivated account
Missing address
Deleting default address
Multiple default addresses
Duplicate request
Double-click submit
Network failure
API timeout
Empty user list
Empty address list
Unauthorized request
Forbidden request
Missing record
```

The application must fail gracefully.

---

# 34. BUSINESS LOGIC REVIEW

For every feature ask:

```text
Does the customer need this?
Is this step necessary?
Can this process be simpler?
Is the business rule correct?
Is it secure?
Is it fast?
Is the error understandable?
Does it require unnecessary clicks?
Does it require unnecessary data entry?
Does it integrate correctly with other modules?
```

Remove unnecessary friction without removing important security or validation.

---

# 35. DO NOT OVERENGINEER

Do NOT:

- rewrite working modules without reason
- introduce unnecessary design patterns
- add unnecessary packages
- create unnecessary database tables
- add complicated architecture for simple CRUD
- change APIs without checking frontend usage
- change database schema without checking relationships
- weaken security for better UX

Prefer simple, maintainable solutions.

---

# 36. DEFINITION OF DONE

A feature is DONE only when:

```text
[ ] Business logic is correct
[ ] Backend works
[ ] Frontend works
[ ] API integration works
[ ] Validation works
[ ] Authorization works
[ ] Ownership works
[ ] Error handling works
[ ] Loading state works
[ ] Empty state works
[ ] Performance is acceptable
[ ] Mobile UX works
[ ] Tests pass
[ ] No browser console errors
[ ] No unexpected Laravel errors
[ ] Existing features still work
```

---

# 37. FINAL REPORT

Create:

```text
docs/AUTH_USERS_AUDIT.md
```

Include:

```markdown
# Authentication & Users Audit

## Executive Summary

## Authentication
### Problems Found
### Changes Made
### Tests

## Users
### Problems Found
### Changes Made
### Tests

## Addresses
### Problems Found
### Changes Made
### Tests

## Business Logic Improvements

## User Experience Improvements

## Security Improvements

## Performance Improvements

## Project Integration

## Git Review

## Code Quality Review

## Bugs Fixed

## Remaining Issues

## Recommended Future Improvements

## Final Status
```

---

# 38. FINAL SUMMARY

At completion report:

```text
Authentication: PASS / NEEDS WORK
Users: PASS / NEEDS WORK
Addresses: PASS / NEEDS WORK
Authorization: PASS / NEEDS WORK
Business Logic: PASS / NEEDS WORK
User Experience: PASS / NEEDS WORK
Security: PASS / NEEDS WORK
Performance: PASS / NEEDS WORK
Project Integration: PASS / NEEDS WORK
Git Management: PASS / NEEDS WORK
Code Quality: PASS / NEEDS WORK
Automated Tests: PASS / NEEDS WORK
```

Also report:

```text
Critical bugs fixed:
Security issues fixed:
Business logic improved:
UX improvements:
Performance improvements:
Tests added:
Remaining issues:
```

---

# 39. EXECUTION ORDER

Follow this order:

```text
INSPECT EXISTING PROJECT
        ↓
UNDERSTAND CURRENT BUSINESS LOGIC
        ↓
AUDIT AUTHENTICATION
        ↓
AUDIT USERS
        ↓
AUDIT ADDRESSES
        ↓
AUDIT AUTHORIZATION & SECURITY
        ↓
TEST PROJECT INTEGRATION
        ↓
IDENTIFY UX PROBLEMS
        ↓
IDENTIFY PERFORMANCE PROBLEMS
        ↓
FIX CRITICAL ISSUES
        ↓
IMPROVE BUSINESS LOGIC
        ↓
IMPROVE USER EXPERIENCE
        ↓
OPTIMIZE PERFORMANCE
        ↓
ADD/UPDATE TESTS
        ↓
RUN REGRESSION TESTS
        ↓
REVIEW CODE
        ↓
GENERATE FINAL REPORT
```

## MOST IMPORTANT REQUIREMENT

Do not only analyze the project.

Actually inspect and modify the existing code.

Preserve working functionality.

The final result should make **Authentication → User → Address → Cart → Checkout → Order** feel like one connected E-Commerce experience rather than separate CRUD modules.

Prioritize:

**Correct Business Logic → Security → User Experience → Performance → Maintainable Code.**