You are a Senior Full-Stack Engineer, Software Architect, QA Engineer, and
Security Engineer.

I have an existing MULTI-SHOP / MULTI-VENDOR E-COMMERCE project.

Technology stack:

Backend:
- Laravel
- MySQL
- Redis
- REST API
- Nginx
- Docker

Frontend:
- Vue 3
- TypeScript
- Vite
- Vue Router
- Pinia
- Axios
- Bootstrap
- Bootstrap Icons

Your job is NOT simply to add new code.

Your main task is:

AUDIT THE ENTIRE EXISTING PROJECT
→ CHECK EVERY REQUIRED FEATURE
→ CHECK WHETHER IT EXISTS
→ CHECK WHETHER IT ACTUALLY WORKS
→ FIX BROKEN FEATURES
→ IMPLEMENT MISSING FEATURES
→ TEST EVERYTHING
→ DO NOT DUPLICATE EXISTING FEATURES

==================================================
1. VERY IMPORTANT RULE
==================================================

Before creating anything:

DO NOT assume a feature is missing.

DO NOT create duplicate tables.

DO NOT create duplicate APIs.

DO NOT create duplicate Vue pages.

DO NOT rewrite working code unnecessarily.

First inspect the existing project.

For every feature, classify it as:

[EXISTS + WORKING]
[EXISTS + PARTIALLY WORKING]
[EXISTS + BROKEN]
[MISSING]

Then:

EXISTS + WORKING
→ Keep it.

EXISTS + PARTIALLY WORKING
→ Complete it.

EXISTS + BROKEN
→ Find root cause and fix it.

MISSING
→ Implement it properly.

==================================================
2. PROJECT INSPECTION
==================================================

First inspect:

Backend:

- app/
- routes/
- models
- migrations
- controllers
- services
- requests
- resources
- policies
- middleware
- jobs
- notifications
- events
- database seeders
- factories
- tests

Frontend:

- src/
- pages
- components
- layouts
- stores
- services
- router
- types
- composables
- assets

Infrastructure:

- docker-compose.yml
- Dockerfiles
- Nginx configuration
- environment configuration
- Redis configuration
- MySQL configuration

Do not modify anything during the initial inspection.

First understand the existing architecture.

==================================================
3. CREATE FEATURE AUDIT
==================================================

Create a checklist for these features.

--------------------------------------------------
AUTHENTICATION
--------------------------------------------------

- Admin login
- Shop owner login
- Shop staff login
- Customer login
- Logout
- Authentication persistence
- Password reset
- Email verification if required
- Protected routes
- Token/session handling
- 401 handling
- 403 handling

--------------------------------------------------
USER & ROLE MANAGEMENT
--------------------------------------------------

- Users
- Roles
- Permissions
- Super Admin
- Shop Owner
- Shop Staff
- Customer
- Role assignment
- Permission assignment
- Authorization

--------------------------------------------------
MULTI-SHOP
--------------------------------------------------

- Shop creation
- Shop registration/application
- Shop approval
- Shop rejection
- Shop suspension
- Shop activation
- Shop profile
- Shop logo
- Shop banner
- Shop description
- Shop status
- Shop owner
- Shop staff
- Multiple staff per shop
- User/shop relationship
- Shop permissions

--------------------------------------------------
SHOP SECURITY
--------------------------------------------------

Verify that:

Shop A cannot access Shop B's:

- Products
- Product images
- Product variants
- Inventory
- Inventory transactions
- Orders
- Customers
- Coupons
- Reviews
- Reports
- Earnings
- Payouts

Test this using actual API requests.

Do NOT trust frontend restrictions.

Backend must enforce shop ownership.

--------------------------------------------------
PRODUCTS
--------------------------------------------------

- Product CRUD
- Product search
- Product filtering
- Product sorting
- Product pagination
- Product images
- Product variants
- SKU
- Price
- Sale price
- Categories
- Brands
- Product status
- Product visibility
- Product ownership
- Product validation

--------------------------------------------------
CATEGORIES
--------------------------------------------------

- Category CRUD
- Search
- Status
- Parent category if supported
- Product count
- Validation

--------------------------------------------------
BRANDS
--------------------------------------------------

- Brand CRUD
- Logo
- Status
- Product count
- Validation

--------------------------------------------------
INVENTORY
--------------------------------------------------

- Current stock
- Reserved stock
- Available stock
- Low-stock threshold
- Out-of-stock status
- Add stock
- Remove stock
- Stock adjustment
- Inventory transactions
- Inventory history
- Stock ownership per shop
- Concurrent stock protection

Test:

Shop A product stock must never affect Shop B product stock.

--------------------------------------------------
CUSTOMER
--------------------------------------------------

- Customer registration
- Customer login
- Customer profile
- Addresses
- Order history
- Wishlist
- Reviews
- Notifications

--------------------------------------------------
SEARCH
--------------------------------------------------

- Product search
- Shop search
- Category filter
- Brand filter
- Shop filter
- Price filter
- Rating filter
- Stock filter
- Sorting
- Pagination

--------------------------------------------------
CART
--------------------------------------------------

- Add to cart
- Update quantity
- Remove item
- Clear cart
- Cart persistence
- Stock validation
- Price validation

IMPORTANT:

Test products from multiple shops in one cart.

Example:

Shop A → Product A
Shop B → Product B
Shop C → Product C

All must work together correctly.

--------------------------------------------------
MULTI-SHOP CHECKOUT
--------------------------------------------------

This is a critical feature.

Test:

Customer adds:

Shop A Product = $20
Shop B Product = $30
Shop C Product = $50

Checkout total = $100

Verify:

- Correct subtotal
- Correct discount
- Correct shipping
- Correct tax if supported
- Correct total
- Correct shop ownership
- Correct inventory reduction
- Correct order creation
- Correct payment
- Correct order items

Do NOT trust frontend totals.

Recalculate important values on the backend.

--------------------------------------------------
ORDERS
--------------------------------------------------

- Create order
- View order
- Order items
- Order status
- Order status history
- Cancel order
- Process order
- Shipping
- Delivery
- Refund if supported

Verify:

Shop A sees only its own order items.

Super Admin can see all order data.

--------------------------------------------------
PAYMENTS
--------------------------------------------------

- Payment creation
- Payment status
- Payment transaction
- Payment verification
- Failed payment
- Refund if supported
- Payment history

Never expose sensitive payment information.

--------------------------------------------------
SHIPPING
--------------------------------------------------

- Shipping methods
- Shipping price
- Shipment
- Tracking number
- Shipment status
- Shipping history

Test multi-shop shipping if supported.

--------------------------------------------------
COUPONS
--------------------------------------------------

- Platform coupons
- Shop coupons
- Fixed discount
- Percentage discount
- Expiration
- Usage limit
- Per-user usage
- Minimum order
- Maximum discount

Verify Shop A cannot modify Shop B's coupons.

--------------------------------------------------
REVIEWS
--------------------------------------------------

- Create review
- Review validation
- Review moderation
- Review images
- Product review
- Shop review
- Customer review history

--------------------------------------------------
NOTIFICATIONS
--------------------------------------------------

Customer:

- Order notification
- Payment notification
- Shipping notification
- Review notification

Shop:

- New order
- Low stock
- New review
- Payment/order update
- Payout notification

Admin:

- New shop
- Shop application
- Payout request
- Payment issue
- System notification

--------------------------------------------------
COMMISSION
--------------------------------------------------

Check whether the project supports:

- Platform commission
- Shop commission
- Percentage commission
- Fixed commission if supported
- Commission calculation
- Commission history
- Shop earnings

Example:

Product = $100
Commission = 10%

Platform = $10
Shop = $90

Verify calculations on the backend.

--------------------------------------------------
PAYOUT
--------------------------------------------------

Check:

- Shop balance
- Pending balance
- Available balance
- Payout request
- Payout status
- Payout history
- Admin approval
- Paid amount

Do not implement real money transfers unless a payment provider exists.

--------------------------------------------------
ADMIN DASHBOARD
--------------------------------------------------

Check:

- Dashboard statistics
- Total shops
- Active shops
- Customers
- Products
- Orders
- Revenue
- Commission
- Payouts
- Recent orders
- Recent shops
- Low stock
- Charts
- Reports

All data must come from real APIs/database.

NO MOCK DATA.

--------------------------------------------------
SHOP OWNER DASHBOARD
--------------------------------------------------

Check:

- Shop statistics
- Products
- Orders
- Inventory
- Revenue
- Commission
- Earnings
- Payouts
- Reviews
- Notifications
- Reports

The shop owner must only see their own shop.

--------------------------------------------------
REPORTS
--------------------------------------------------

Check:

- Sales report
- Revenue report
- Product report
- Shop report
- Customer report
- Inventory report
- Payment report
- Commission report
- Payout report

Check:

- Date filtering
- Shop filtering
- Category filtering
- Product filtering
- Export if supported

--------------------------------------------------
SETTINGS
--------------------------------------------------

Check:

- Platform settings
- Shop settings
- Account settings
- Notification settings
- Security settings

==================================================
4. DATABASE AUDIT
==================================================

Inspect the existing database.

Check whether the following concepts exist:

users
shops
shop_users
addresses
categories
brands
products
product_images
product_variants
attributes
attribute_values
variant_attribute_values
inventories
inventory_transactions
carts
cart_items
wishlists
wishlist_items
orders
order_items
order_status_histories
payments
payment_transactions
shipping_methods
shipments
coupons
coupon_usages
reviews
review_images
notifications
commissions
payouts

IMPORTANT:

Do not automatically create every table.

Only add what is actually required by the existing architecture.

Check:

- Primary keys
- Foreign keys
- Indexes
- Unique constraints
- Nullable fields
- Relationships
- Soft deletes
- Cascading behavior

Do not delete existing production-like data.

If existing products/orders need a shop relationship, create a safe migration.

==================================================
5. API AUDIT
==================================================

Inspect all existing API routes.

For every required feature:

Check:

- Endpoint exists?
- Correct HTTP method?
- Correct authentication?
- Correct authorization?
- Correct validation?
- Correct response?
- Correct status code?
- Correct database operation?
- Correct error handling?

Do not create duplicate endpoints if an existing endpoint can be improved.

==================================================
6. FRONTEND AUDIT
==================================================

Check every Vue page.

For each page:

- Does it load?
- Does API call work?
- Does data come from backend?
- Does create work?
- Does update work?
- Does delete work?
- Does validation work?
- Does loading state work?
- Does empty state work?
- Does error state work?
- Does pagination work?
- Does filtering work?
- Does search work?

Remove fake/mock data where the real API already exists.

==================================================
7. ADMIN DASHBOARD AUDIT
==================================================

Open every admin page and test it.

Check:

/admin/dashboard
/admin/shops
/admin/products
/admin/categories
/admin/brands
/admin/inventory
/admin/orders
/admin/payments
/admin/shipping
/admin/customers
/admin/coupons
/admin/reviews
/admin/notifications
/admin/reports
/admin/settings

If a page exists but does not work:

FIX IT.

If it does not exist:

IMPLEMENT IT.

If it works:

DO NOT REBUILD IT.

==================================================
8. SHOP DASHBOARD AUDIT
==================================================

Check:

/shop/dashboard
/shop/products
/shop/inventory
/shop/orders
/shop/reviews
/shop/coupons
/shop/reports
/shop/earnings
/shop/payouts
/shop/settings

Apply the same rule:

Working → keep.

Broken → fix.

Partial → complete.

Missing → implement.

==================================================
9. CUSTOMER AUDIT
==================================================

Check:

- Home
- Shop listing
- Shop details
- Product listing
- Product details
- Search
- Cart
- Wishlist
- Checkout
- Orders
- Order details
- Profile
- Addresses
- Reviews
- Notifications

Test the complete customer workflow.

==================================================
10. SECURITY AUDIT
==================================================

Perform actual authorization tests.

Create:

Shop A
Shop B

Then attempt:

Shop A → Shop B product

Shop A → Shop B inventory

Shop A → Shop B order

Shop A → Shop B coupon

Shop A → Shop B review

Shop A → Shop B report

Shop A → Shop B payout

Expected result:

403 Forbidden or appropriate authorization response.

Also test IDOR vulnerabilities.

Example:

Change:

/api/shop/products/10

to:

/api/shop/products/11

and verify ownership is checked.

==================================================
11. PERFORMANCE AUDIT
==================================================

Check:

- N+1 queries
- Slow queries
- Missing indexes
- Large API responses
- Unnecessary API calls
- Duplicate requests
- Large frontend bundles
- Image size
- Pagination
- Redis usage
- Queue usage

Optimize only where necessary.

Do not sacrifice correctness for performance.

==================================================
12. DOCKER AUDIT
==================================================

Verify:

docker compose ps

Check:

- Laravel
- Vue
- MySQL
- Redis
- Nginx
- phpMyAdmin

Check logs.

Fix:

- Container crashes
- Port problems
- Volume problems
- Environment problems
- Database connection problems
- Frontend build problems
- Nginx problems

==================================================
13. AUTOMATIC IMPLEMENTATION RULE
==================================================

After auditing:

For every feature:

IF:
    EXISTS + WORKING
THEN:
    Keep it.

IF:
    EXISTS + BROKEN
THEN:
    Fix it.

IF:
    EXISTS + PARTIAL
THEN:
    Complete it.

IF:
    MISSING
THEN:
    Implement it.

Do not stop after fixing one feature.

Continue until the entire feature checklist has been audited.

==================================================
14. TEST AFTER EACH MAJOR FEATURE
==================================================

After implementing/fixing a feature:

1. Test backend.
2. Test API.
3. Test database.
4. Test frontend.
5. Test authorization.
6. Test error handling.
7. Test related workflows.

Then continue to the next feature.

==================================================
15. FINAL END-TO-END TEST
==================================================

Perform this complete workflow:

SUPER ADMIN
    ↓
Create Shop A
    ↓
Create Shop B
    ↓
Approve both shops
    ↓
Create Shop Owners
    ↓
Shop A creates products
    ↓
Shop B creates products
    ↓
Add inventory
    ↓
CUSTOMER
    ↓
Browse products
    ↓
Add Shop A product
    ↓
Add Shop B product
    ↓
Checkout
    ↓
Payment
    ↓
Order created
    ↓
Inventory updated
    ↓
Shop A sees its order items
    ↓
Shop B sees its order items
    ↓
Super Admin sees complete order
    ↓
Commission calculated
    ↓
Shop earnings updated
    ↓
Notification generated
    ↓
Reports updated

Then test unauthorized access.

==================================================
16. CODE QUALITY
==================================================

Follow the existing project architecture.

Avoid:

- Duplicate code
- Duplicate API endpoints
- Duplicate database tables
- Huge controllers
- Huge Vue components
- Hard-coded business logic
- Mock data
- Frontend-only authorization
- Unnecessary rewrites

Use:

- Laravel Form Requests
- Policies
- Services
- API Resources
- Transactions
- Vue composables where useful
- Pinia stores
- Reusable components
- TypeScript types

==================================================
17. FINAL REPORT
==================================================

When finished, provide a detailed report.

Create this table:

| Feature | Status | Action |
|---------|--------|--------|
| Authentication | Working | Kept |
| Shops | Missing | Added |
| Products | Broken | Fixed |
| Inventory | Partial | Completed |
| Orders | Working | Kept |

Use the actual project state.

Statuses:

WORKING
FIXED
COMPLETED
PARTIAL
BLOCKED

Then provide:

### 1. Existing Features
List features that already existed and worked.

### 2. Fixed Features
List features that were broken and how they were fixed.

### 3. Completed Features
List partially implemented features that were completed.

### 4. New Features
List features that were missing and added.

### 5. Database Changes
List:
- New tables
- Modified tables
- New columns
- Foreign keys
- Indexes
- Migrations

### 6. API Changes
List:
- New endpoints
- Modified endpoints
- Removed endpoints only if absolutely necessary

### 7. Frontend Changes
List:
- New pages
- Modified pages
- New components
- Stores
- Services
- Routes

### 8. Security
List:
- Authorization fixes
- IDOR fixes
- Validation fixes
- Permission fixes

### 9. Testing
Show what was actually tested.

### 10. Remaining Issues
List anything that could not be completed.

IMPORTANT:

Do not say "everything works" unless you actually tested it.

Do not hide errors.

Do not create fake success messages.

Do not use mock data to make incomplete features appear functional.

The goal is:

AUDIT → FIX → COMPLETE → TEST → REPORT

NOT:

REWRITE EVERYTHING.