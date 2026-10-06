# E-KHMER — Upgrade Existing E-Commerce to Multi-Vendor Marketplace

Act as a senior **Laravel + Vue.js + MySQL Software Architect, Multi-Vendor Marketplace Engineer, Security Engineer, and QA Engineer**.

I have an existing E-Commerce application called **E-KHMER**.

## Current Stack

- Laravel Backend
- Vue.js Frontend
- MySQL
- REST API
- Docker
- Git/GitHub

The project already contains features such as:

```text
Authentication
Users
Addresses

Products
Categories
Brands
Product Images
Product Variants
Attributes

Cart
Wishlist
Checkout

Orders
Order Items
Order Status
Payments
Payment Transactions
Coupons

Inventory
Inventory Transactions
Shipping
Shipments
Notifications

Reviews
Review Images

Admin Dashboard
Customer Website
API Integration
```

The application currently behaves mainly like a normal/single-store E-Commerce system.

I want to upgrade it into a:

# MULTI-VENDOR / MULTI-SHOP MARKETPLACE

Similar conceptually to a marketplace where:

```text
Super Admin
     ↓
E-KHMER Marketplace
     ↓
Multiple Shops
     ↓
Multiple Shop Owners
     ↓
Products from all shops
     ↓
Customer Marketplace
```

Customers must be able to browse and purchase products from different shops through one E-KHMER website.

---

# 1. MOST IMPORTANT RULE

DO NOT rebuild the project from scratch.

First:

```text
INSPECT
   ↓
UNDERSTAND
   ↓
BACKUP / PROTECT EXISTING LOGIC
   ↓
DESIGN MIGRATION
   ↓
UPGRADE DATABASE
   ↓
UPGRADE BACKEND
   ↓
UPGRADE FRONTEND
   ↓
MIGRATE EXISTING DATA
   ↓
TEST
```

Preserve existing working features whenever possible.

Do not delete working code just because a different architecture is easier.

Do not destroy existing data.

Use safe migrations.

---

# 2. TARGET ARCHITECTURE

Upgrade E-KHMER to:

```text
                       E-KHMER
                      SUPER ADMIN
                           │
              ┌────────────┼────────────┐
              ↓            ↓            ↓
           Shop A        Shop B       Shop C
           Owner A       Owner B      Owner C
              │            │            │
          Products      Products     Products
              │            │            │
              └────────────┼────────────┘
                           ↓
                  E-KHMER MARKETPLACE
                           ↓
                       Customer
                           ↓
                Browse All Shop Products
                           ↓
                         Cart
                           ↓
                       Checkout
                           ↓
                    Marketplace Order
                           ↓
               Split into Shop Orders
```

---

# 3. USER ROLES

Upgrade the authorization system to support:

```text
super_admin
shop_owner
shop_admin
customer
```

If the existing system uses different role names, migrate carefully.

Do NOT blindly replace existing roles without checking their usage.

---

# 4. ROLE RESPONSIBILITIES

## Super Admin

Super Admin controls the entire marketplace.

Can manage:

```text
All Shops
Shop Applications
Shop Owners
Shop Admins
Customers

All Products
Categories
Brands

All Orders
Shop Orders
Payments
Shipments

Coupons
Reviews
Notifications

Marketplace Reports
Settings
```

Super Admin has marketplace-level visibility.

---

# 5. SHOP OWNER

Shop Owner manages their own shop.

Shop Owner dashboard:

```text
Dashboard

Products
Inventory
Orders
Shipping
Coupons
Reviews
Customers
Reports
Notifications

Staff / Admins

Shop Settings
```

Shop Owner MUST only access their own shop data.

---

# 6. SHOP ADMIN

Shop Owner should be able to add staff/admin users to help manage the shop.

Example:

```text
Shop Owner
     ↓
Shop
     ↓
┌────────────┬─────────────┬─────────────┐
↓            ↓             ↓             ↓
Manager   Product Staff  Order Staff  Inventory Staff
```

For the first version, it is acceptable to use:

```text
shop_admin
```

as one general staff role.

Design the architecture so permissions can become more granular later.

---

# 7. CUSTOMER

Customer can:

```text
Browse all active shops
Browse products from all shops
Search products
Filter products
Visit shop pages
View product details
Add products to wishlist
Add products from multiple shops to cart
Checkout
Pay
View orders
Track shipments
Write reviews
Manage profile
View notifications
```

---

# 8. CREATE SHOPS TABLE

Add a `shops` table.

Recommended concept:

```text
shops
--------------------------------
id
owner_id
name
slug
logo
cover_image
description
email
phone
address
status
created_at
updated_at
```

Possible status:

```text
pending
active
suspended
rejected
```

Adapt fields to the current project requirements.

Add indexes/constraints where appropriate.

---

# 9. SHOP RELATIONSHIPS

Implement:

```text
User
 ↓
Owns
 ↓
Shop
```

Laravel relationships conceptually:

```text
User
hasMany / hasOne Shops

Shop
belongsTo Owner

Shop
hasMany Products

Shop
hasMany ShopUsers
```

Choose `hasOne` or `hasMany` owner relationships based on the desired business rule.

Design for multiple shops per owner only if required.

For the initial version, one owner → one shop is acceptable and simpler.

---

# 10. SHOP STAFF

Create something similar to:

```text
shop_users
--------------------------------
id
shop_id
user_id
role
status
created_at
updated_at
```

Use this to connect shop staff/admins to shops.

Do not rely only on a global `admin` role.

---

# 11. SHOP APPLICATION FLOW

Implement:

```text
User Registration
      ↓
Apply to Become Seller
      ↓
Enter Shop Information
      ↓
Submit
      ↓
Shop Status = Pending
      ↓
Super Admin Reviews
      ↓
Approve / Reject
```

If approved:

```text
Shop = Active
      ↓
Owner gets Shop Dashboard
```

If rejected:

```text
Shop = Rejected
```

Provide a useful rejection reason if supported.

---

# 12. SHOP SUSPENSION

Super Admin can suspend a shop.

When:

```text
Shop = suspended
```

determine appropriate behavior.

Normally:

```text
Owner can access limited account information
Owner cannot publish/sell products
Products are hidden/not purchasable
Existing orders remain accessible for resolution
Historical records remain intact
```

Do NOT delete the shop's historical data.

---

# 13. PRODUCT OWNERSHIP

Add:

```text
shop_id
```

to products.

Concept:

```text
Shop
 ↓
Products
```

Example:

```text
Tech Store
├── iPhone
├── Laptop
└── Mouse

Fashion Store
├── Shirt
├── Shoes
└── Bag
```

---

# 14. EXISTING PRODUCTS MIGRATION

The project may already contain products.

Do NOT break them.

Create a safe migration strategy.

For example:

```text
Existing Marketplace/Admin Products
        ↓
Assign to default/platform shop
```

or another appropriate migration.

Document the decision.

Do not leave invalid `shop_id` values.

---

# 15. PRODUCT CREATION SECURITY

Never trust this:

```json
{
    "name": "Laptop",
    "shop_id": 200
}
```

from a shop owner.

Laravel must determine which shop the authenticated owner/admin is authorized to manage.

Concept:

```text
Authenticated User
      ↓
Authorized Shop
      ↓
Create Product
      ↓
Automatically assign shop_id
```

---

# 16. STRICT SHOP OWNERSHIP

This is CRITICAL.

Shop A must never manage:

```text
Shop B Product
Shop B Inventory
Shop B Order
Shop B Coupon
Shop B Shipment
Shop B Staff
Shop B Reports
```

Test ID manipulation.

Example:

```text
Shop A owner:

PUT /api/products/SHOP_B_PRODUCT_ID
```

Expected:

```text
403 Forbidden
```

Do not rely only on Vue route guards.

Enforce ownership using Laravel:

```text
Policies
Middleware
Query Scopes
Services
Authorization
```

as appropriate.

---

# 17. CATEGORY ARCHITECTURE

Keep Categories global initially.

Example:

```text
Super Admin
 ↓
Categories

Electronics
Fashion
Beauty
Sports
Books
Home
```

Shop owners select categories when creating products.

Avoid duplicate marketplace categories created by every seller.

---

# 18. BRAND ARCHITECTURE

Prefer global brands initially.

Super Admin manages:

```text
Apple
Samsung
Nike
Adidas
Sony
```

Shop owner selects an existing brand.

Future enhancement:

```text
Shop Owner
 ↓
Request New Brand
 ↓
Super Admin Approval
```

Do not overcomplicate the first version.

---

# 19. PRODUCT STATUS

Support appropriate statuses such as:

```text
draft
active
inactive
archived
```

Products should be customer-visible only when:

```text
Product = active
AND
Shop = active
```

---

# 20. CUSTOMER MARKETPLACE

Upgrade the customer website so it displays products from all active shops.

Example:

```text
E-KHMER

Recommended Products

iPhone 17
$999
Tech Store

Nike Shoes
$80
Sport Store

T-Shirt
$15
Fashion KH
```

Every product card should clearly identify its shop where useful.

---

# 21. SHOP PUBLIC PAGE

Create public shop routes.

Example:

```text
/shops
/shops/{slug}
```

Shop page:

```text
Shop Logo
Shop Cover

Shop Name
Description
Rating
Location if appropriate

Products
Categories
Reviews summary
```

Customer can browse products from a specific shop.

---

# 22. PRODUCT DETAILS + SELLER

Product details should show:

```text
Product Name
Images
Price
Variants
Stock
Description
Reviews

Sold By:
Shop Name

[ Visit Shop ]
```

---

# 23. MARKETPLACE SEARCH

Search should work across products from all active shops.

Potential filters:

```text
Keyword
Category
Brand
Shop
Price
Rating
Availability
```

Only return products customers are allowed to purchase.

---

# 24. MULTI-SHOP CART

Upgrade cart to support products from multiple shops.

Example:

```text
CART

Tech Store

iPhone
$999

Charger
$20


Fashion KH

T-Shirt
$15

Shoes
$50
```

Group cart items visually by shop.

---

# 25. CART SECURITY

Each cart item must preserve:

```text
Product
Variant
Shop
Quantity
Current Valid Price
```

Backend remains source of truth.

Do NOT trust:

```text
shop_id
price
subtotal
discount
stock
```

from frontend.

---

# 26. CART VALIDATION

Before checkout validate:

```text
Shop active?
Product active?
Product belongs to shop?
Variant valid?
Stock available?
Quantity valid?
Current price correct?
```

---

# 27. MULTI-SHOP CHECKOUT

Customer should be able to purchase products from multiple shops in one checkout.

Example:

```text
Customer Cart

Shop A = $100
Shop B = $50
Shop C = $25

Marketplace Checkout
```

The system should internally separate seller responsibilities.

---

# 28. PARENT ORDER

Use the existing `orders` table as the customer/marketplace parent order if compatible.

Concept:

```text
Order
--------------------------------
id
user_id
order_number
subtotal
discount
shipping
total
status
...
```

Do not rewrite the existing order architecture unnecessarily.

---

# 29. SHOP ORDERS

Introduce:

```text
shop_orders
```

Recommended concept:

```text
shop_orders
--------------------------------
id
order_id
shop_id
shop_order_number
subtotal
discount
shipping
total
status
created_at
updated_at
```

Adapt to existing schema.

---

# 30. ORDER SPLITTING

Example:

```text
Customer Order
#EK10001

Total = $1084
```

Internally:

```text
#EK10001-A

Shop:
Tech Store

iPhone
Charger

Total:
$1019
```

and:

```text
#EK10001-B

Shop:
Fashion KH

T-Shirt
Shoes

Total:
$65
```

Relationship:

```text
Customer
   ↓
Order
   ↓
┌───────────────┐
↓               ↓
Shop Order A   Shop Order B
↓               ↓
Items           Items
```

---

# 31. ORDER ITEMS

Connect order items to the appropriate shop order.

Concept:

```text
order_items
--------------------------------
id
shop_order_id
product_id
variant_id

product_name
sku
variant_snapshot

unit_price
quantity
subtotal
...
```

Preserve historical purchase information.

---

# 32. SHOP OWNER ORDER ACCESS

Shop owner sees ONLY their shop orders.

Example:

```text
Tech Store Dashboard

Orders

EK10001-A
EK10005-A
EK10010-A
```

They must not see another shop's items or private seller information.

---

# 33. CUSTOMER ORDER VIEW

Customer should see one marketplace order:

```text
Order #EK10001
```

Then grouped:

```text
Tech Store

iPhone
Charger

Processing


Fashion KH

T-Shirt
Shoes

Shipped
```

Each shop order may progress independently.

---

# 34. ORDER STATUS

Separate:

```text
Parent Order Status
```

from:

```text
Shop Order Status
```

Example:

```text
Parent Order:
Partially Shipped

Shop A:
Delivered

Shop B:
Shipped

Shop C:
Processing
```

Define consistent aggregation rules.

Do not hard-code status logic across many controllers.

---

# 35. INVENTORY

Inventory must remain tied to the correct shop-owned product/variant.

Concept:

```text
Shop
 ↓
Product
 ↓
Variant
 ↓
Inventory
```

Shop owner can only adjust inventory belonging to their shop.

---

# 36. INVENTORY TRANSACTIONS

Preserve inventory transaction history.

Example:

```text
Shop:
Tech Store

Product:
iPhone 17

Before:
10

Order:
-1

After:
9

Reference:
EK10001-A
```

---

# 37. SHIPPING METHODS

Decide whether shipping methods are:

```text
Global Marketplace Shipping
```

or:

```text
Per-Shop Shipping
```

For the first version, choose one clear strategy based on the existing architecture.

If sellers manage their own shipping:

```text
shipping_methods
shop_id
```

must be protected by shop ownership.

---

# 38. MULTI-SHOP SHIPPING

Different shops may ship separately.

Architecture:

```text
Parent Order
      ↓
 ┌────┴─────┐
 ↓          ↓
Shop A     Shop B
 ↓          ↓
Shipment A Shipment B
```

Each shipment can have:

```text
Carrier
Tracking Number
Shipment Status
Shipped Date
Delivered Date
```

---

# 39. SHIPPING ADDRESS

Preserve an order-time shipping address snapshot.

Do not make old orders depend completely on the customer's current saved address.

---

# 40. PAYMENTS

Keep payment secure and marketplace-aware.

For the first version, customer can make one payment for the parent order.

Concept:

```text
Customer
 ↓
Parent Order
 ↓
Payment
 ↓
Payment Transaction
```

Then revenue can be calculated per `shop_order`.

---

# 41. DO NOT IMPLEMENT COMPLEX PAYOUTS YET

For the first marketplace version, do NOT introduce complex:

```text
Automatic Seller Payout
Bank Settlement
Escrow
Payment Splitting
```

unless already required.

First correctly implement:

```text
Customer Payment
+
Shop Revenue Tracking
```

Payouts can be a future module.

---

# 42. SHOP REVENUE

Shop dashboard should calculate:

```text
Today's Sales
Total Orders
Revenue
Pending Orders
Delivered Orders
```

using only that shop's valid order data.

Do not calculate revenue using all marketplace orders.

---

# 43. PLATFORM REVENUE

Super Admin can see:

```text
Total Marketplace Sales
Orders
Sales by Shop
Top Shops
Top Products
```

Do not add marketplace commission yet unless explicitly required.

---

# 44. COUPONS

Prepare coupon architecture for:

```text
Platform Coupon
Shop Coupon
```

Possible design:

```text
coupons

shop_id nullable
```

Meaning:

```text
shop_id = NULL
→ Marketplace coupon

shop_id = 10
→ Shop 10 coupon
```

Define clearly how discounts are allocated in multi-shop checkout.

Do not allow Shop A's coupon to discount Shop B's products unless intentionally supported.

---

# 45. REVIEWS

Continue product reviews.

Because:

```text
Product
 ↓
Shop
```

the marketplace can calculate shop rating using product reviews if desired.

Display:

```text
Product Rating
Review Count
Verified Purchase
```

where supported.

---

# 46. REVIEW SECURITY

Shop owners must NOT edit customer reviews to improve ratings.

Allow appropriate actions such as:

```text
View
Report
Respond
```

only if implemented.

Super Admin may moderate reviews according to marketplace policy.

---

# 47. NOTIFICATIONS

Upgrade notifications for:

## Customer

```text
Order Placed
Payment Successful
Shop Order Shipped
Delivered
Cancelled
```

## Shop Owner

```text
New Order
Payment Confirmed
Low Stock
New Review
Order Cancelled
```

## Super Admin

```text
New Shop Application
Suspicious Activity
Shop Issues
Marketplace Events
```

Avoid duplicate notification spam.

---

# 48. SUPER ADMIN DASHBOARD

Create/upgrade:

```text
SUPER ADMIN

Dashboard

Shops
├── Pending
├── Active
├── Suspended
└── Rejected

Shop Owners
Shop Admins

Customers

Products
Categories
Brands

Orders
Shop Orders
Payments

Inventory Overview

Shipping
Shipments

Coupons
Reviews

Notifications
Reports
Settings
```

---

# 49. SUPER ADMIN DASHBOARD STATISTICS

Useful metrics:

```text
Total Shops
Active Shops
Pending Shops

Total Customers
Total Products

Total Orders
Total Sales

Today's Orders
Today's Sales

Top Shops
Top Products
```

Use real database values.

Do not use hard-coded statistics.

---

# 50. SHOP OWNER DASHBOARD

Create:

```text
MY SHOP

Dashboard
Products
Inventory
Orders
Shipping
Coupons
Reviews
Customers
Reports
Notifications
Staff
Shop Settings
```

All queries must be scoped to the owner's shop.

---

# 51. SHOP DASHBOARD STATISTICS

Show:

```text
My Products
My Orders
My Revenue

Pending Orders
Processing Orders
Shipped Orders

Low Stock
Out of Stock

Recent Orders
Recent Reviews
```

Never include another shop's data.

---

# 52. SHOP SETTINGS

Owner should manage:

```text
Shop Name
Logo
Cover
Description
Phone
Email
Address
```

Do not allow owner to directly change protected marketplace fields such as:

```text
Approval Status
Suspension Status
Owner
```

unless business rules explicitly permit it.

---

# 53. SHOP STAFF MANAGEMENT

Owner should be able to:

```text
View Staff
Add Staff
Remove Staff
Activate/Deactivate Staff
```

Ensure staff belong to the correct shop.

Super Admin can view/manage all shop staff where appropriate.

---

# 54. SECURITY — TENANT ISOLATION

Treat each shop as a tenant-like security boundary.

Critical rule:

```text
SHOP A DATA
≠
SHOP B DATA
```

Test:

```text
Product IDs
Inventory IDs
Shop Order IDs
Shipment IDs
Coupon IDs
Staff IDs
Report Endpoints
```

Never assume hiding UI buttons is security.

---

# 55. SHOP-A VS SHOP-B SECURITY TEST

Create:

```text
Shop A
Shop B
```

Then test Shop A trying to:

```text
View Shop B product management
Edit Shop B product
Delete Shop B product

View Shop B inventory
Adjust Shop B inventory

View Shop B order
Update Shop B order

View Shop B shipment
Update Shop B shipment

Use Shop B coupon management API

Manage Shop B staff

View Shop B private reports
```

Expected:

```text
403 Forbidden
```

or safe not-found behavior according to API design.

---

# 56. SUPER ADMIN SECURITY

Only `super_admin` can perform marketplace-wide actions such as:

```text
Approve Shop
Reject Shop
Suspend Shop
Reactivate Shop
Manage Marketplace Categories
Manage Marketplace Settings
View Marketplace Reports
```

---

# 57. CUSTOMER SECURITY

Customer must not be able to:

```text
Create Shop Product without authorization
Change Product Price
Change shop_id
Change Inventory
Change Order Status
Change Payment Status
Change Shipping Cost
Manage Shop Staff
Access Seller Dashboards
Access Super Admin Dashboard
```

---

# 58. ROUTE STRUCTURE

Review existing routes and organize logically.

Concept:

```text
/api/customer/...
/api/shop/...
/api/super-admin/...
```

or preserve the existing route structure if it is already clean.

Do not rename every endpoint unnecessarily.

---

# 59. FRONTEND ROUTING

Concept:

```text
/
 /products
 /products/:slug

 /shops
 /shops/:slug

 /cart
 /checkout

 /account
 /account/orders

 /seller
 /seller/products
 /seller/orders
 /seller/inventory

 /super-admin
 /super-admin/shops
 /super-admin/orders
```

Adapt to existing Vue Router conventions.

---

# 60. FRONTEND ROUTE GUARDS

Implement route guards for UX.

But remember:

```text
Vue Route Guard
≠
Security
```

Laravel must enforce authorization.

---

# 61. DATABASE CONSTRAINTS

Audit:

```text
Foreign Keys
Unique Constraints
Indexes
Nullable Fields
Cascade Behavior
Soft Deletes
```

Be extremely careful with cascade delete.

Deleting/suspending a shop must NOT destroy historical:

```text
Orders
Payments
Inventory Transactions
Shipments
Reviews
```

---

# 62. SAFE SHOP DELETION

Prefer:

```text
Suspended
Archived
Soft Deleted
```

over permanently deleting a shop with transaction history.

Never corrupt old orders.

---

# 63. EXISTING DATA MIGRATION

Create a migration strategy for existing:

```text
Products
Inventory
Orders
Coupons
Shipping
```

Do not run destructive migrations blindly.

Before changing schema:

1. Inspect existing data.
2. Determine required default shop/platform ownership.
3. Add nullable fields if needed temporarily.
4. Backfill data.
5. Validate.
6. Add constraints.
7. Remove temporary compatibility only when safe.

---

# 64. DATABASE BACKUP

Before destructive migration operations:

Document how to back up the database.

Do NOT automatically delete production data.

---

# 65. API RESPONSES

Make marketplace API responses clear.

Product may include:

```json
{
    "id": 1,
    "name": "iPhone 17",
    "price": 999,
    "shop": {
        "id": 10,
        "name": "Tech Store",
        "slug": "tech-store"
    }
}
```

Do not expose unnecessary shop-owner private data.

---

# 66. PERFORMANCE

Multi-vendor architecture can create N+1 problems.

Audit queries involving:

```text
Products + Shop
Orders + Shop Orders
Shop Orders + Items
Products + Inventory
Products + Reviews
Shops + Product Counts
```

Use appropriate eager loading.

Avoid unnecessary nested relationships.

---

# 67. PAGINATION

Use server-side pagination for:

```text
Shops
Products
Orders
Shop Orders
Customers
Inventory
Shipments
Reviews
Notifications
```

Do not load the entire marketplace into Vue.

---

# 68. DATABASE INDEXES

Inspect indexes for:

```text
shop_id
owner_id
user_id
product_id
variant_id
order_id
shop_order_id
status
slug
created_at
```

Add only justified indexes.

---

# 69. CACHE

Cache only where safe.

Possible candidates:

```text
Marketplace Categories
Active Brands
Public Shop Information
Popular Products
```

Do not cache private shop data in a way that leaks information between shops.

---

# 70. RESPONSIVE DESIGN

Test:

```text
Customer Website
Seller Dashboard
Super Admin Dashboard
```

on:

```text
Mobile
Tablet
Desktop
```

Seller dashboard should remain usable on smaller screens.

---

# 71. ERROR STATES

Implement useful errors:

```text
Shop not found.
Shop is currently unavailable.
Product not available.
You do not have permission to manage this shop.
Order not found.
```

Do not show:

```text
SQLSTATE
Stack Trace
AxiosError
```

to users.

---

# 72. LOADING STATES

Provide useful loading feedback for:

```text
Shop Dashboard
Products
Orders
Inventory
Shop Application
Checkout
Marketplace Search
```

Do not hide genuinely slow code behind spinners.

Fix actual performance problems.

---

# 73. AUTOMATED TESTS — SHOPS

Test:

```text
Create Shop Application
Approve Shop
Reject Shop
Suspend Shop
Reactivate Shop

Owner Access
Staff Access
Customer Access
Super Admin Access
```

---

# 74. AUTOMATED TESTS — PRODUCTS

Test:

```text
Owner creates product
Product gets correct shop_id
Owner edits own product
Owner deletes own product

Owner cannot edit another shop product
Owner cannot delete another shop product

Inactive shop products hidden
Suspended shop products not purchasable
```

---

# 75. AUTOMATED TESTS — INVENTORY

Test:

```text
Shop adjusts own inventory

Shop cannot adjust another shop inventory

Order deducts correct shop inventory

Cancellation restores correct inventory
```

---

# 76. AUTOMATED TESTS — MULTI-SHOP CART

Test:

```text
Add Shop A Product
Add Shop B Product

Both appear in cart

Correct grouping
Correct quantities
Correct prices
Correct shops
```

---

# 77. AUTOMATED TESTS — MULTI-SHOP CHECKOUT

Critical test:

```text
Cart:

Shop A Product = $100
Shop B Product = $50

Checkout
```

Expected:

```text
Parent Order = $150

Shop Order A = $100
Shop Order B = $50
```

Verify:

```text
Order Items
Inventory
Payment
Shipping
Notifications
```

---

# 78. AUTOMATED TESTS — ORDER SECURITY

Shop A must only see:

```text
Shop Order A
```

Shop B must only see:

```text
Shop Order B
```

Customer sees:

```text
Parent Order
+
Both Shop Orders
```

Super Admin sees:

```text
Everything
```

---

# 79. AUTOMATED TESTS — SHIPPING

Test:

```text
Shop A creates shipment for Shop A order
```

Allow.

Test:

```text
Shop A creates shipment for Shop B order
```

Reject.

---

# 80. AUTOMATED TESTS — COUPONS

Test:

```text
Shop A coupon
```

must not incorrectly discount:

```text
Shop B product
```

Test marketplace coupon separately if supported.

---

# 81. AUTOMATED TESTS — STAFF

Test:

```text
Shop A Owner
 ↓
Creates Shop A Staff
```

Staff can access only Shop A.

Changing IDs manually must not expose Shop B.

---

# 82. COMPLETE CUSTOMER FLOW

Test:

```text
Customer
 ↓
Register/Login
 ↓
Browse Marketplace
 ↓
Visit Shop A
 ↓
Add Shop A Product
 ↓
Visit Shop B
 ↓
Add Shop B Product
 ↓
Cart
 ↓
Checkout
 ↓
Payment
 ↓
Parent Order
 ↓
Shop Order A + Shop Order B
 ↓
Separate Shipments
 ↓
Notifications
 ↓
Delivered
 ↓
Reviews
```

---

# 83. COMPLETE SHOP OWNER FLOW

Test:

```text
Register
 ↓
Apply as Seller
 ↓
Super Admin Approval
 ↓
Shop Dashboard
 ↓
Create Product
 ↓
Add Images
 ↓
Create Variants
 ↓
Add Inventory
 ↓
Publish Product
 ↓
Customer Purchases
 ↓
Receive Shop Order
 ↓
Process Order
 ↓
Create Shipment
 ↓
Ship
 ↓
Delivered
 ↓
View Revenue
```

---

# 84. COMPLETE SUPER ADMIN FLOW

Test:

```text
Super Admin Login
 ↓
Dashboard
 ↓
Review Shop Application
 ↓
Approve
 ↓
Monitor Shop
 ↓
View Products
 ↓
View Orders
 ↓
View Payments
 ↓
View Shipments
 ↓
View Reviews
 ↓
View Reports
 ↓
Suspend Shop if required
```

---

# 85. CROSS-SHOP ATTACK TEST

Create:

```text
Shop A Owner
Shop B Owner
Customer
Super Admin
```

Attempt ID manipulation against every shop-owned resource.

Generate a test matrix.

No cross-shop private data leakage is acceptable.

---

# 86. REGRESSION TESTING

After marketplace upgrade, retest all existing modules:

```text
Authentication
Users
Addresses
Products
Categories
Brands
Images
Variants
Attributes

Cart
Wishlist
Checkout

Orders
Payments
Coupons

Inventory
Shipping
Shipments

Notifications
Reviews
```

The upgrade must not silently break previous functionality.

---

# 87. DO NOT OVERENGINEER VERSION 1

For the first marketplace release, focus on:

```text
Super Admin
Shop Owner
Shop Admin
Customer

Shop Approval
Shop Dashboard

Shop Products
Shop Inventory

Marketplace Products
Public Shop Page

Multi-Shop Cart
Multi-Shop Checkout

Parent Order
Shop Orders

Per-Shop Shipping

Notifications
Reviews
Reports
```

Do NOT prioritize yet:

```text
Automatic Seller Payouts
Complex Commission Engine
Escrow
Subscriptions
Seller Wallet
Affiliate System
Chat System
Auction
Complex Dispute System
Multi-Warehouse Marketplace
```

These can be Phase 2.

---

# 88. MIGRATION PLAN

Before coding, create:

```text
docs/MARKETPLACE_MIGRATION_PLAN.md
```

Include:

```text
Current Architecture
Target Architecture

Existing Tables Affected
New Tables Required
Columns Added
Relationships Changed

Existing Data Migration Strategy

Backend Changes
Frontend Changes
API Changes

Security Risks
Compatibility Risks

Migration Order
Rollback Strategy

Testing Strategy
```

Review the architecture before making destructive changes.

---

# 89. MARKETPLACE AUDIT REPORT

After implementation create:

```text
docs/MARKETPLACE_UPGRADE_REPORT.md
```

Include:

```text
# E-KHMER Marketplace Upgrade

## Executive Summary

## Architecture Changes

## Database Changes

## Roles & Permissions

## Super Admin

## Shops

## Shop Owners

## Shop Staff

## Products

## Inventory

## Customer Marketplace

## Multi-Shop Cart

## Multi-Shop Checkout

## Parent Orders

## Shop Orders

## Payments

## Shipping

## Coupons

## Reviews

## Notifications

## Security

## Performance

## Data Migration

## Tests

## Bugs Fixed

## Remaining Issues

## Future Phase

## Final Status
```

---

# 90. FINAL STATUS

Report:

```text
Super Admin: PASS / NEEDS WORK

Shop Registration: PASS / NEEDS WORK
Shop Approval: PASS / NEEDS WORK
Shop Suspension: PASS / NEEDS WORK

Shop Owner Dashboard: PASS / NEEDS WORK
Shop Staff: PASS / NEEDS WORK

Shop Products: PASS / NEEDS WORK
Shop Inventory: PASS / NEEDS WORK

Customer Marketplace: PASS / NEEDS WORK
Public Shop Pages: PASS / NEEDS WORK

Multi-Shop Cart: PASS / NEEDS WORK
Multi-Shop Checkout: PASS / NEEDS WORK

Parent Orders: PASS / NEEDS WORK
Shop Orders: PASS / NEEDS WORK

Payments: PASS / NEEDS WORK
Coupons: PASS / NEEDS WORK

Shipping: PASS / NEEDS WORK
Shipments: PASS / NEEDS WORK

Reviews: PASS / NEEDS WORK
Notifications: PASS / NEEDS WORK

Shop Isolation: PASS / NEEDS WORK
Authorization: PASS / NEEDS WORK
Security: PASS / NEEDS WORK

Performance: PASS / NEEDS WORK
Responsive Design: PASS / NEEDS WORK

Automated Tests: PASS / NEEDS WORK
Regression Tests: PASS / NEEDS WORK
```

Also report:

```text
New tables:
Modified tables:
New APIs:
Modified APIs:

New pages:
Modified pages:

Features added:
Bugs fixed:
Security issues fixed:
Performance improvements:

Tests added:
Tests passed:
Tests failed:

Migration issues:
Remaining issues:
Recommended next phase:
```

---

# 91. IMPLEMENTATION ORDER

Follow this order strictly:

```text
INSPECT EXISTING PROJECT
        ↓
DOCUMENT CURRENT ARCHITECTURE
        ↓
CREATE MIGRATION PLAN
        ↓
BACKUP / PROTECT EXISTING DATA
        ↓
ROLES & AUTHORIZATION
        ↓
SHOPS TABLE
        ↓
SHOP OWNER RELATIONSHIP
        ↓
SHOP STAFF
        ↓
SHOP APPLICATION
        ↓
SUPER ADMIN SHOP MANAGEMENT
        ↓
SHOP OWNER DASHBOARD
        ↓
PRODUCT → SHOP
        ↓
MIGRATE EXISTING PRODUCTS
        ↓
INVENTORY → SHOP PRODUCT
        ↓
PUBLIC SHOP PAGE
        ↓
MARKETPLACE PRODUCT LIST
        ↓
MULTI-SHOP CART
        ↓
MULTI-SHOP CHECKOUT
        ↓
PARENT ORDER
        ↓
SHOP ORDERS
        ↓
ORDER ITEMS
        ↓
PAYMENT INTEGRATION
        ↓
PER-SHOP SHIPPING
        ↓
COUPONS
        ↓
REVIEWS
        ↓
NOTIFICATIONS
        ↓
SHOP REPORTS
        ↓
SUPER ADMIN REPORTS
        ↓
SECURITY TESTS
        ↓
CROSS-SHOP TESTS
        ↓
PERFORMANCE TESTS
        ↓
RESPONSIVE TESTS
        ↓
REGRESSION TESTS
        ↓
FULL CUSTOMER FLOW
        ↓
FULL SELLER FLOW
        ↓
FULL SUPER ADMIN FLOW
        ↓
FINAL AUDIT
```

---

# 92. CRITICAL ARCHITECTURE RULES

Never violate these rules:

### Rule 1

```text
Every seller-owned Product
→ belongs to a Shop
```

### Rule 2

```text
Every Shop Owner/Admin
→ can manage only authorized Shop data
```

### Rule 3

```text
Customer
→ can browse products from all ACTIVE Shops
```

### Rule 4

```text
Cart
→ can contain products from multiple Shops
```

### Rule 5

```text
One Checkout
→ can create one Parent Order
→ containing multiple Shop Orders
```

### Rule 6

```text
Shop Owner
→ sees only their Shop Orders
```

### Rule 7

```text
Super Admin
→ sees the entire Marketplace
```

### Rule 8

```text
Inventory
→ belongs to the correct Shop Product / Variant
```

### Rule 9

```text
Shipment
→ belongs to the correct Shop Order
```

### Rule 10

```text
Shop A
→ NEVER accesses Shop B private data
```

---

# 93. MOST IMPORTANT REQUIREMENT

Do NOT simply generate new database tables and stop.

Do NOT only write documentation.

Actually upgrade the existing project.

For every existing module:

```text
Inspect Existing Implementation
        ↓
Determine Marketplace Impact
        ↓
Modify Safely
        ↓
Preserve Existing Data
        ↓
Integrate Shop Ownership
        ↓
Test
```

If existing architecture conflicts with this proposed design, do NOT blindly force the proposed schema.

Instead:

1. Understand the existing implementation.
2. Preserve working behavior.
3. Adapt the marketplace architecture to it.
4. Explain important architectural decisions in the migration report.
5. Avoid destructive schema changes unless absolutely necessary.

The final system should behave like:

```text
                     E-KHMER
                 MULTI-VENDOR MARKETPLACE
                          │
              ┌───────────┴───────────┐
              │                       │
        SUPER ADMIN                CUSTOMERS
              │                       │
         ALL SHOPS              ALL ACTIVE SHOPS
              │                       │
      ┌───────┼───────┐         ALL PRODUCTS
      ↓       ↓       ↓               │
   Shop A   Shop B   Shop C            │
      │       │       │                │
   Owner   Owner    Owner              │
      │       │       │                │
 Products Products Products ───────────┘
      │       │       │
 Inventory Inventory Inventory
      │       │       │
      └───────┼───────┘
              ↓
        CUSTOMER CART
              ↓
           CHECKOUT
              ↓
         PARENT ORDER
              ↓
      ┌───────┼────────┐
      ↓       ↓        ↓
 ShopOrder A ShopOrder B ShopOrder C
      ↓       ↓        ↓
 Shipment A Shipment B Shipment C
      ↓       ↓        ↓
      └───────┼────────┘
              ↓
           CUSTOMER
```

# FINAL PRIORITY

**Data Migration Safety → Shop Isolation → Authorization → Correct Marketplace Business Logic → Order/Data Integrity → Security → Customer Experience → Seller Experience → Super Admin Control → Performance → Maintainability.**