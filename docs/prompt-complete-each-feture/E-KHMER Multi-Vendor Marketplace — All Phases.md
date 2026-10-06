# E-KHMER Multi-Vendor Marketplace — All Phases

Act as a senior **Laravel + Vue.js + MySQL Software Architect, Marketplace Engineer, Security Engineer, DevOps Engineer, and QA Engineer**.

I have an existing E-Commerce project called **E-KHMER**.

## Stack

- Laravel Backend
- Vue.js Frontend
- MySQL
- REST API
- Docker
- Git/GitHub

I want to upgrade the existing project into a complete:

# MULTI-VENDOR / MULTI-SHOP MARKETPLACE

The system should support:

```text
Super Admin
     ↓
Marketplace
     ↓
Multiple Shops
     ↓
Shop Owners / Shop Admins
     ↓
Products
     ↓
Customers
     ↓
Multi-Shop Cart
     ↓
Checkout
     ↓
Parent Order
     ↓
Shop Orders
     ↓
Payments
     ↓
Inventory
     ↓
Separate Shipments
     ↓
Notifications
     ↓
Reviews
```

Do NOT rebuild the project from scratch.

Always:

```text
INSPECT
→ UNDERSTAND
→ PLAN
→ BACKUP
→ MODIFY
→ MIGRATE
→ TEST
→ VERIFY
→ REGRESSION TEST
→ REPORT
```

Do not move to the next phase until critical tests in the current phase pass.

---

# OVERALL ROADMAP

```text
PHASE 1
Marketplace Foundation
Roles + Shops + Super Admin + Seller

        ↓

PHASE 2
Multi-Shop Shopping
Cart + Checkout + Parent Order + Shop Orders

        ↓

PHASE 3
Payments + Inventory + Shipping

        ↓

PHASE 4
Marketplace Operations
Coupons + Reviews + Notifications

        ↓

PHASE 5
Dashboards + Reports + UX + Performance

        ↓

PHASE 6
Security + QA + Regression Testing

        ↓

PHASE 7
Production + Deployment + Final Release
```

---

# ==========================================

# PHASE 1 — MARKETPLACE FOUNDATION

# ==========================================

## Goal

Transform the existing single-store architecture into the foundation of a multi-vendor marketplace.

Implement:

```text
Roles
Shops
Shop Applications
Super Admin
Shop Owner
Shop Admin
Shop Dashboard
Product Ownership
Public Shop Pages
Shop Isolation
```

---

## 1.1 Inspect Existing Architecture

Before changing anything inspect:

```text
Authentication
Users
Roles
Products
Inventory
Orders
Cart
Checkout
Payments
Shipping

Laravel Models
Migrations
Controllers
Services
Policies
Middleware
Routes

Vue Router
State Management
API Services
Admin Dashboard
Customer Website
```

Do not duplicate existing functionality.

---

## 1.2 Roles

Support:

```text
super_admin
shop_owner
shop_admin
customer
```

Safely migrate existing users.

Existing admin accounts may become:

```text
super_admin
```

only after checking existing requirements.

---

## 1.3 Shops

Create/adapt:

```text
shops

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
rejection_reason
created_at
updated_at
```

Statuses:

```text
pending
active
suspended
rejected
```

---

## 1.4 Shop Staff

Create/adapt:

```text
shop_users

id
shop_id
user_id
role
status
created_at
updated_at
```

Prevent duplicate shop-user relationships.

---

## 1.5 Seller Application

Implement:

```text
User
 ↓
Become Seller
 ↓
Shop Application
 ↓
Pending
 ↓
Super Admin Review
 ↓
Approve / Reject
```

---

## 1.6 Super Admin

Create marketplace administration:

```text
Dashboard
Shops
Shop Owners
Shop Admins
Customers
Products
Categories
Brands
```

Super Admin can:

```text
Approve Shop
Reject Shop
Suspend Shop
Reactivate Shop
View Shop Details
```

---

## 1.7 Seller Dashboard

Create:

```text
/seller
```

Initial navigation:

```text
Dashboard
Products
Inventory
Orders
Shipping
Coupons
Reviews
Notifications
Staff
Shop Settings
```

---

## 1.8 Product Ownership

Add/adapt:

```text
products.shop_id
```

Relationship:

```text
Shop
 ↓
Products
```

Seller product creation must automatically determine `shop_id` from authenticated authorization context.

NEVER trust frontend `shop_id`.

---

## 1.9 Existing Product Migration

Safely migrate old products.

Possible:

```text
Existing Products
 ↓
Default Platform Shop
```

Do not delete existing products.

---

## 1.10 Public Shops

Create:

```text
/shops
/shops/:slug
```

Customers can browse active shops and their active products.

---

## 1.11 Phase 1 Security

Critical:

```text
Shop A
✗ Product B
✗ Inventory B
✗ Staff B
✗ Shop Settings B
```

Backend authorization is mandatory.

---

## 1.12 Phase 1 Tests

Test:

```text
[ ] Roles work
[ ] Seller application works
[ ] Shop approval works
[ ] Rejection works
[ ] Suspension works
[ ] Seller dashboard works
[ ] Product ownership works
[ ] Public shops work
[ ] Existing products preserved
[ ] Shop A cannot manage Shop B
```

Do not continue until Shop Isolation passes.

---

# ==========================================

# PHASE 2 — MULTI-SHOP SHOPPING & ORDERS

# ==========================================

## Goal

Allow customers to buy products from multiple shops in one checkout.

Implement:

```text
Multi-Shop Cart
Checkout
Parent Order
Shop Orders
Order Items
Order Status
```

---

## 2.1 Multi-Shop Cart

Cart should support:

```text
Shop A
├── Product A
└── Product B

Shop B
├── Product C
└── Product D
```

Group cart UI by shop.

---

## 2.2 Cart Validation

Backend validates:

```text
Shop active
Product active
Product belongs to shop
Variant valid
Stock available
Quantity valid
Current price
```

Never trust frontend:

```text
price
subtotal
shop_id
discount
stock
```

---

## 2.3 Checkout

Customer flow:

```text
Cart
 ↓
Address
 ↓
Shipping
 ↓
Coupon
 ↓
Payment Method
 ↓
Review
 ↓
Place Order
```

---

## 2.4 Parent Order

Use/adapt:

```text
orders
```

as marketplace/customer parent order.

Concept:

```text
orders

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

---

## 2.5 Shop Orders

Create:

```text
shop_orders

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

---

## 2.6 Split Checkout

Example:

```text
Cart

Shop A = $100
Shop B = $50
Shop C = $25
```

Create:

```text
Parent Order
Total = $175

├── Shop Order A = $100
├── Shop Order B = $50
└── Shop Order C = $25
```

---

## 2.7 Order Items

Every item belongs to the appropriate shop order.

Preserve snapshot:

```text
Product Name
SKU
Variant
Attributes
Unit Price
Quantity
Subtotal
```

Historical orders must not change when products change later.

---

## 2.8 Order Access

Customer:

```text
Own Parent Order
+
All Shop Orders inside it
```

Seller:

```text
Only Own Shop Orders
```

Super Admin:

```text
All Orders
```

---

## 2.9 Shop Order Status

Possible:

```text
pending
confirmed
processing
shipped
delivered
cancelled
```

Use controlled transitions.

Prevent:

```text
delivered → pending
cancelled → shipped
```

---

## 2.10 Parent Status

Derive parent order status from shop orders where appropriate.

Example:

```text
Shop A = delivered
Shop B = shipped
Shop C = processing

Parent = partially_completed / appropriate project status
```

Centralize this logic.

---

## 2.11 Phase 2 Tests

Critical:

```text
[ ] Products from multiple shops enter cart
[ ] Cart groups by shop
[ ] Checkout validates every shop
[ ] One parent order created
[ ] Correct shop orders created
[ ] Correct items assigned
[ ] Totals correct
[ ] Historical snapshots preserved
[ ] Seller sees only own shop order
[ ] Customer sees complete order
[ ] Super Admin sees all
```

---

# ==========================================

# PHASE 3 — PAYMENT, INVENTORY & SHIPPING

# ==========================================

## Goal

Make marketplace fulfillment financially and operationally correct.

Implement/improve:

```text
Payments
Payment Transactions
Inventory
Inventory Transactions
Shipping Methods
Shipments
Tracking
```

---

## 3.1 Payment Architecture

For Version 1:

```text
Customer
 ↓
Parent Order
 ↓
One Payment
 ↓
Payment Transaction
```

Do not implement complicated seller payout yet.

---

## 3.2 Backend Financial Authority

Never trust frontend:

```text
Subtotal
Discount
Shipping
Grand Total
Payment Amount
```

Backend calculates everything.

---

## 3.3 Payment Status

Support existing appropriate statuses:

```text
pending
paid
failed
cancelled
refunded
```

Keep separate:

```text
Order Status
Payment Status
Shipment Status
```

---

## 3.4 Payment Idempotency

Repeated:

```text
Payment Callback
Webhook
Retry
Refresh
```

must NOT create:

```text
Duplicate Payments
Duplicate Orders
Duplicate Stock Deduction
```

---

## 3.5 Inventory

Architecture:

```text
Shop
 ↓
Product
 ↓
Variant
 ↓
Inventory
```

Every seller manages only their own stock.

---

## 3.6 Stock Deduction

Define one consistent strategy.

Example:

```text
Order Confirmed / Payment Confirmed
 ↓
Inventory Deduction
 ↓
Inventory Transaction
```

Do not deduct stock from multiple events.

---

## 3.7 Inventory Transactions

Every change should record:

```text
Inventory
Type
Quantity
Before
After
Reference
Reason
Actor
Timestamp
```

---

## 3.8 Prevent Overselling

Test:

```text
Stock = 1

Customer A checkout
Customer B checkout
```

Only one may successfully purchase the final unit.

Use appropriate transaction/locking/atomic logic.

---

## 3.9 Cancellation

When cancellation requires stock restoration:

```text
Cancel
 ↓
Restore Inventory
 ↓
Inventory Transaction
```

Restore exactly once.

---

## 3.10 Shipping

Determine whether shipping methods are:

```text
Marketplace Global
```

or:

```text
Shop Specific
```

For seller-managed shipping, associate methods with shop.

---

## 3.11 Separate Shipments

Architecture:

```text
Parent Order
     ↓
┌────┴────┐
↓         ↓
Shop A   Shop B
↓         ↓
Shipment Shipment
```

---

## 3.12 Shipment

Support:

```text
Carrier
Tracking Number
Shipping Method
Status
Shipped Date
Delivered Date
```

---

## 3.13 Shipment Status

Possible:

```text
pending
preparing
shipped
in_transit
out_for_delivery
delivered
failed
returned
cancelled
```

Use only statuses needed by the project.

Prevent invalid transitions.

---

## 3.14 Phase 3 Tests

```text
[ ] Payment total correct
[ ] Payment cannot be manipulated
[ ] Duplicate payment prevented
[ ] Correct shop inventory deducted
[ ] No overselling
[ ] Inventory history correct
[ ] Cancellation restores exactly once
[ ] Shop A cannot adjust Shop B inventory
[ ] Shop A cannot ship Shop B order
[ ] Separate shipments work
[ ] Tracking works
```

---

# ==========================================

# PHASE 4 — MARKETPLACE OPERATIONS

# ==========================================

## Goal

Complete supporting marketplace features.

Implement:

```text
Coupons
Reviews
Review Images
Notifications
```

---

## 4.1 Coupons

Support architecture for:

```text
Marketplace Coupon
Shop Coupon
```

Possible:

```text
coupons.shop_id nullable
```

Meaning:

```text
NULL
→ Marketplace Coupon

shop_id = X
→ Shop Coupon
```

---

## 4.2 Coupon Isolation

Shop A coupon must not incorrectly discount Shop B products.

Backend validates coupon scope.

---

## 4.3 Reviews

Customer can:

```text
View Reviews
Create Review
Edit Own Review
Delete Own Review
Upload Review Images
```

Prefer verified-purchase reviews if compatible with business requirements.

---

## 4.4 Seller Review Access

Seller can:

```text
View reviews for own products
```

Do not allow seller to secretly edit customer rating/comment.

---

## 4.5 Review Images

Validate:

```text
MIME
Size
Count
Ownership
Storage
```

Prevent unsafe uploads.

---

## 4.6 Shop Rating

Optionally derive shop rating from product reviews.

Do not create fake ratings.

---

## 4.7 Notifications

Customer:

```text
Order Placed
Payment
Shipped
Delivered
Cancelled
```

Seller:

```text
New Order
Payment Confirmed
Low Stock
New Review
Cancellation
```

Super Admin:

```text
Shop Application
Shop Issues
Marketplace Events
```

---

## 4.8 Prevent Notification Duplication

Repeated events must not spam users.

Do not create notifications from page refreshes.

---

## 4.9 Phase 4 Tests

```text
[ ] Marketplace coupon works
[ ] Shop coupon works
[ ] Cross-shop coupon misuse blocked
[ ] Reviews work
[ ] Review ownership works
[ ] Review images secure
[ ] Seller sees own product reviews
[ ] Notifications correct
[ ] Notification ownership correct
[ ] Duplicate notifications prevented
```

---

# ==========================================

# PHASE 5 — DASHBOARDS, REPORTS, UX & PERFORMANCE

# ==========================================

## Goal

Make the marketplace professional and usable.

---

# 5.1 Super Admin Dashboard

Include:

```text
Total Shops
Pending Shops
Active Shops

Total Sellers
Total Customers
Total Products

Total Orders
Marketplace Sales

Recent Shops
Recent Orders

Top Shops
Top Products
```

Use real database values.

---

# 5.2 Seller Dashboard

Display only seller data:

```text
My Products
My Orders
My Sales

Pending Orders
Processing
Shipped

Low Stock
Out of Stock

Recent Orders
Recent Reviews
```

---

# 5.3 Reports

Super Admin reports:

```text
Marketplace Sales
Orders
Shops
Products
Customers
Top Shops
Top Products
```

Seller reports:

```text
My Sales
My Orders
My Products
My Inventory
```

---

# 5.4 Search

Marketplace search:

```text
Products
Categories
Brands
Shops
```

Use server-side search.

---

# 5.5 Filters

Product filters:

```text
Shop
Category
Brand
Price
Rating
Availability
```

---

# 5.6 Pagination

Server-side pagination:

```text
Products
Shops
Orders
Shop Orders
Inventory
Customers
Reviews
Notifications
```

---

# 5.7 Responsive Design

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

---

# 5.8 UX

Every page needs appropriate:

```text
Loading
Error
Empty
Success
Disabled Processing State
```

---

# 5.9 Performance

Audit:

```text
N+1 Queries
Duplicate API Calls
Slow Queries
Large API Responses
Large Images
Vue Re-renders
Large Bundles
Repeated Auth Requests
```

Measure before/after.

Do not invent performance metrics.

---

# 5.10 Database Performance

Inspect indexes around:

```text
shop_id
user_id
owner_id
product_id
variant_id
order_id
shop_order_id
status
slug
created_at
```

---

# 5.11 Phase 5 Tests

```text
[ ] Super Admin stats accurate
[ ] Seller stats isolated
[ ] Reports accurate
[ ] Search works
[ ] Filters work
[ ] Pagination works
[ ] Responsive design works
[ ] Loading/error/empty states work
[ ] Duplicate API calls removed
[ ] Major N+1 queries fixed
```

---

# ==========================================

# PHASE 6 — SECURITY, QA & REGRESSION

# ==========================================

## Goal

Test the marketplace as one complete system.

---

# 6.1 Role Security

Test:

```text
Super Admin
Shop Owner
Shop Admin
Customer
Guest
```

against every protected module.

---

# 6.2 Cross-Shop Security

Create:

```text
Shop A
Shop B
```

Attempt cross-shop access for:

```text
Shop Settings
Products
Images
Variants
Inventory
Inventory Transactions
Orders
Shop Orders
Coupons
Shipments
Reviews Management
Staff
Reports
```

Shop A must NEVER manage Shop B private data.

---

# 6.3 Customer Ownership

Customer A must not access Customer B:

```text
Addresses
Orders
Payments
Reviews Management
Notifications
```

---

# 6.4 Financial Security

Try manipulating:

```text
Price
Subtotal
Discount
Shipping
Total
Payment Amount
Payment Status
```

Backend must reject/ignore manipulated financial values.

---

# 6.5 Inventory Security

Try:

```text
Negative quantity
Quantity > stock
Fake stock
Duplicate deduction
Duplicate restoration
Concurrent checkout
```

---

# 6.6 Upload Security

Test:

```text
Shop Logo
Cover
Product Images
Review Images
```

for:

```text
Invalid MIME
Huge File
Invalid Image
Unsafe Filename
Executable Upload
```

---

# 6.7 API Testing

Test:

```text
200
201
400
401
403
404
422
429
500
```

where appropriate.

---

# 6.8 Browser Testing

Check:

```text
Console Errors
Vue Warnings
Failed Requests
CORS
404 Assets
Unhandled Promise Rejections
```

Fix causes rather than suppressing warnings.

---

# 6.9 Full Customer Journey

Test:

```text
Register/Login
 ↓
Browse Marketplace
 ↓
Visit Shop A
 ↓
Add Product
 ↓
Visit Shop B
 ↓
Add Product
 ↓
Cart
 ↓
Checkout
 ↓
Payment
 ↓
Parent Order
 ↓
Shop Orders
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

# 6.10 Full Seller Journey

Test:

```text
Register
 ↓
Apply Seller
 ↓
Approved
 ↓
Seller Dashboard
 ↓
Create Product
 ↓
Variants
 ↓
Inventory
 ↓
Publish
 ↓
Receive Order
 ↓
Process
 ↓
Ship
 ↓
Deliver
 ↓
View Revenue
 ↓
View Review
```

---

# 6.11 Full Super Admin Journey

Test:

```text
Login
 ↓
Dashboard
 ↓
Approve Shop
 ↓
Monitor Sellers
 ↓
Products
 ↓
Orders
 ↓
Payments
 ↓
Shipping
 ↓
Reviews
 ↓
Reports
```

---

# 6.12 Regression Testing

Retest ALL original modules:

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

---

# 6.13 Phase 6 Exit Criteria

Do not release if any of these fail:

```text
Shop Isolation
Authorization
Checkout Financial Accuracy
Payment Integrity
Inventory Integrity
Order Integrity
Customer Data Ownership
```

---

# ==========================================

# PHASE 7 — PRODUCTION & FINAL RELEASE

# ==========================================

## Goal

Prepare E-KHMER for deployment and final presentation.

---

# 7.1 Production Environment

Audit:

```text
APP_ENV
APP_DEBUG
APP_URL

Database
Redis
Queue
Cache
Mail
Storage
Frontend API URL
```

Production must not run with unsafe debug settings.

---

# 7.2 Secrets

Check Git history/current files for accidental:

```text
Passwords
API Keys
Tokens
Database Credentials
Payment Secrets
```

Do not commit `.env`.

Rotate exposed credentials if discovered.

---

# 7.3 Laravel Production

Prepare appropriate production operations such as:

```text
Composer production dependencies
Config cache
Route cache where compatible
View cache
Queue worker
Storage link
Correct permissions
```

Adapt commands to actual Docker architecture.

---

# 7.4 Vue Production

Verify:

```text
Production API URL
Production Build
No debug code
No mock data
No localhost dependencies
No broken assets
```

---

# 7.5 Docker

Verify:

```text
Laravel
Nginx
MySQL
Frontend
Redis if used
Queue Worker
Scheduler
```

Containers should restart appropriately.

---

# 7.6 Database

Before deployment:

```text
Backup
Migration Check
Foreign Key Check
Index Check
Seeder Safety
```

Never run destructive development seeders against production.

---

# 7.7 HTTPS

Production must use HTTPS when deployed publicly.

Especially protect:

```text
Login
Tokens
Customer Information
Checkout
Payment
```

---

# 7.8 Logs

Check:

```text
Laravel Logs
Nginx Logs
Docker Logs
Queue Failures
```

Do not expose logs publicly.

---

# 7.9 Final Smoke Test

After deployment test:

```text
Homepage
Register
Login

Seller Application
Super Admin Approval

Seller Dashboard
Create Product

Marketplace
Cart
Checkout

Order
Payment
Inventory
Shipping

Notifications
Reviews
```

---

# 7.10 Documentation

Complete:

```text
Project Charter
SRS
ERD
Database Dictionary
API Documentation
Marketplace Architecture

Git Workflow

Test Plan
Test Cases
Bug Report
Security Report
Performance Report

Deployment Guide
User Manual
Seller Manual
Super Admin Manual

Final Project Report
Presentation
```

---

# DOCUMENTS TO GENERATE DURING THE UPGRADE

Create:

```text
docs/
├── MARKETPLACE_MIGRATION_PLAN.md
├── MARKETPLACE_PHASE_1_REPORT.md
├── MARKETPLACE_PHASE_2_REPORT.md
├── MARKETPLACE_PHASE_3_REPORT.md
├── MARKETPLACE_PHASE_4_REPORT.md
├── PERFORMANCE_REPORT.md
├── SECURITY_AUDIT.md
├── QA_REPORT.md
├── BUG_REPORT.md
├── DEPLOYMENT_GUIDE.md
└── MARKETPLACE_FINAL_REPORT.md
```

---

# FINAL ARCHITECTURE

Target:

```text
                         E-KHMER
                    MULTI-VENDOR MARKETPLACE
                              │
              ┌───────────────┴───────────────┐
              │                               │
         SUPER ADMIN                      CUSTOMERS
              │                               │
       Manage Marketplace               Browse Marketplace
              │                               │
      ┌───────┼────────┐                      │
      ↓       ↓        ↓                      │
   Shop A   Shop B   Shop C                   │
      │       │        │                      │
   Owner   Owner    Owner                     │
      │       │        │                      │
 Products Products Products ──────────────────┘
      │       │        │
 Variants Variants Variants
      │       │        │
 Inventory Inventory Inventory
      │       │        │
      └───────┼────────┘
              ↓
         Customer Cart
              ↓
           Checkout
              ↓
         Parent Order
              ↓
     ┌────────┼────────┐
     ↓        ↓        ↓
ShopOrder A ShopOrder B ShopOrder C
     ↓        ↓        ↓
 Inventory Inventory Inventory
     ↓        ↓        ↓
Shipment A Shipment B Shipment C
     └────────┼────────┘
              ↓
          Customer
              ↓
        Review Products
```

---

# PHASE GATE RULE

At the end of EVERY phase, report:

```text
PHASE X STATUS

Features Completed:
Features Partial:
Features Missing:

Bugs Found:
Critical Bugs:
Bugs Fixed:
Remaining Bugs:

Security Tests:
Passed:
Failed:

Automated Tests:
Passed:
Failed:

Regression:
PASS / FAIL

READY FOR NEXT PHASE:
YES / NO
```

If:

```text
READY FOR NEXT PHASE = NO
```

fix the current phase first.

Do NOT continue automatically just to finish faster.

---

# FINAL RELEASE GATE

The project is NOT ready for release until:

```text
[ ] Super Admin works
[ ] Seller application works
[ ] Shop Owner works
[ ] Shop Admin works
[ ] Shop isolation works

[ ] Products belong to shops
[ ] Inventory belongs to correct products/shops

[ ] Multi-shop cart works
[ ] Checkout works
[ ] Parent order works
[ ] Shop orders work

[ ] Payment works
[ ] Financial calculations verified

[ ] Separate shipping works
[ ] Tracking works

[ ] Coupons work
[ ] Reviews work
[ ] Notifications work

[ ] Customer dashboard works
[ ] Seller dashboard works
[ ] Super Admin dashboard works

[ ] Mobile works
[ ] Tablet works
[ ] Desktop works

[ ] No critical console errors
[ ] No critical Laravel errors

[ ] Shop A cannot access Shop B
[ ] Customer A cannot access Customer B

[ ] Price manipulation blocked
[ ] Payment manipulation blocked
[ ] Stock manipulation blocked

[ ] Concurrent checkout tested
[ ] Duplicate payment tested
[ ] Duplicate stock deduction tested

[ ] Regression tests pass
[ ] Production configuration checked
[ ] Final documentation completed
```

---

# FEATURES FOR PHASE 2 / FUTURE VERSION

Do NOT prioritize these until the core marketplace is stable:

```text
Marketplace Commission
Seller Wallet
Seller Payout
Automatic Revenue Splitting

Seller Subscription Plans
Featured Shops
Featured Products
Paid Advertising

Follow Shop
Shop Chat

Return & Refund Center
Dispute Management

Multiple Warehouses

Advanced Recommendation Engine

Affiliate Program

Loyalty Points
```

Keep the current architecture extensible enough to add them later.

---

# MOST IMPORTANT INSTRUCTION

Do NOT only analyze this project.

Actually inspect, modify, test, and improve the existing code.

For every phase:

```text
Inspect Existing Code
        ↓
Understand Business Logic
        ↓
Design Safest Change
        ↓
Protect Existing Data
        ↓
Implement
        ↓
Test
        ↓
Security Test
        ↓
Regression Test
        ↓
Report
```

Never mark a feature complete because the code merely exists.

A feature is COMPLETE only when:

```text
Backend works
+
Frontend works
+
Database is correct
+
Authorization works
+
Validation works
+
Business logic works
+
Error handling works
+
Tests pass
+
Integration works
```

# ABSOLUTE SECURITY RULE

The system must always maintain:

```text
SUPER ADMIN
    ↓
Can manage marketplace


SHOP A
    ↓
Can manage Shop A
    ✗
Cannot manage Shop B


SHOP B
    ↓
Can manage Shop B
    ✗
Cannot manage Shop A


CUSTOMER
    ↓
Can browse all ACTIVE shops
Can buy from multiple shops
Can manage only own customer data
```

# FINAL PRIORITY

**Data Safety → Shop Isolation → Authorization → Business Logic → Financial Accuracy → Inventory Integrity → Order Integrity → Security → Customer Experience → Seller Experience → Super Admin Experience →ance → Te Performsting → Deployment.**
