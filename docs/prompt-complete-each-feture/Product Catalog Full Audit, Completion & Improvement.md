# E-KHMER — Product Catalog Full Audit, Completion & Improvement

Act as a senior **Laravel + Vue.js + MySQL E-Commerce Architect and Product Catalog Engineer**.

I have an existing E-Commerce application called **E-KHMER**.

Tech stack:

- Laravel
- Vue.js
- MySQL
- REST API
- Docker
- Git/GitHub

Your responsibility is to fully inspect, test, complete, fix, integrate, and improve:

1. Products
2. Categories
3. Brands
4. Product Images
5. Product Variants
6. Attributes
7. Attribute Values
8. Variant Attribute Values

The goal is NOT simply to make CRUD work.

The goal is to build a **correct, scalable, fast, secure, and user-friendly E-Commerce Product Catalog**.

Do NOT only give recommendations.

Actually:

**INSPECT → AUDIT → FIND MISSING FEATURES → FIX → COMPLETE → IMPROVE → TEST → VERIFY**

---

# 1. INSPECT EXISTING CODE FIRST

Before modifying anything, inspect:

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
Seeders
Factories

Vue Pages
Vue Components
Forms
Tables
Modals
State Management
API Services
Router

Database Relationships
Docker
Laravel Logs
Browser Console
Network Requests
```

Do NOT create duplicate features if they already exist.

Do NOT rewrite working features unnecessarily.

---

# 2. FEATURE AUDIT

Audit:

```text
Products
Categories
Brands
Product Images
Product Variants
Attributes
Attribute Values
Variant Attribute Values
```

Classify every feature:

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

Do not mark a feature COMPLETE without actually testing it.

---

# 3. DATABASE RELATIONSHIPS

Inspect and verify relationships such as:

```text
Category
   ↓
Products

Brand
   ↓
Products

Product
   ├── Category
   ├── Brand
   ├── Images
   ├── Variants
   ├── Reviews
   └── Inventory

Product
   ↓
Variants
   ↓
Variant Attribute Values
   ↓
Attribute Values
   ↓
Attributes
```

Adapt this structure to the existing project.

Do NOT duplicate existing relationships.

Fix incorrect foreign keys, constraints, model relationships, or migrations safely.

---

# 4. PRODUCT MANAGEMENT

Audit the complete Product feature.

Admin should be able to:

```text
Create Product
View Products
View Product Details
Edit Product
Activate Product
Deactivate Product
Delete/Archive Product
Search Products
Filter Products
Sort Products
Paginate Products
Manage Images
Manage Variants
Manage Attributes
```

Check product fields based on the existing database.

Typical fields may include:

```text
name
slug
sku
description
category_id
brand_id
price
sale_price
status
featured
```

Do not add fields unnecessarily if equivalent fields already exist.

---

# 5. PRODUCT CREATION UX

Product creation should be simple and logical.

Recommended flow:

```text
Basic Information
        ↓
Category & Brand
        ↓
Pricing
        ↓
Images
        ↓
Variants / Attributes
        ↓
Inventory
        ↓
Status
        ↓
Save Product
```

Avoid an unnecessarily complicated form.

Group related fields clearly.

---

# 6. PRODUCT VALIDATION

Validate:

```text
Name
Slug
SKU
Category
Brand
Price
Sale Price
Images
Variants
Attributes
Status
```

Business rules:

```text
Price >= 0
Sale Price >= 0
Sale Price should normally not exceed regular price
SKU must be unique where required
Slug must be unique where required
Category must exist
Brand must exist
```

Use Laravel validation as the source of truth.

Frontend validation should improve UX.

---

# 7. PRODUCT STATUS

Inspect existing status logic.

If appropriate, support:

```text
Draft
Active
Inactive
Out of Stock
Archived
```

Do not add unnecessary statuses if the existing project already has a simpler correct model.

Customer-facing pages should normally display only products allowed for sale.

Admin should still be able to manage inactive/draft products.

---

# 8. SAFE PRODUCT DELETION

Do NOT blindly hard-delete products.

A product may already exist in:

```text
Orders
Order Items
Cart
Wishlist
Reviews
Inventory Transactions
Payment records indirectly
```

Historical orders must remain valid.

Determine whether the project should use:

```text
Soft Delete
Archive
Inactive Status
```

based on the existing architecture.

Never break historical order data.

---

# 9. CATEGORIES

Audit:

```text
Create Category
List Categories
View Category
Edit Category
Delete/Archive Category
Search
Status
Product Count
```

Check relationships:

```text
Category
   ↓
Products
```

If hierarchical categories already exist, also verify:

```text
Parent Category
      ↓
Child Category
```

Do not introduce category hierarchy unless the project actually needs it.

---

# 10. CATEGORY BUSINESS RULES

Check:

- unique category name/slug where appropriate
- active/inactive state
- products belonging to category
- safe deletion
- customer filtering
- API performance

If a category contains products, do not blindly delete it.

Use a safe business rule.

For example:

```text
Block deletion
OR
Require reassignment
OR
Archive category
```

Choose the option that best fits the existing architecture.

---

# 11. BRANDS

Audit:

```text
Create Brand
List Brands
View Brand
Edit Brand
Delete/Archive Brand
Search
Filter
Status
Logo if supported
Product Count
```

Verify:

```text
Brand
   ↓
Products
```

A customer should be able to filter products by brand where useful.

---

# 12. BRAND BUSINESS RULES

Check:

```text
Duplicate names
Duplicate slugs
Brand status
Brand logo validation
Products attached to brand
Safe deletion
```

Do not break products when a brand is deleted.

Use appropriate archive/deactivation/reassignment logic.

---

# 13. PRODUCT IMAGES

Audit:

```text
Upload Image
Multiple Images
Preview
Delete Image
Reorder Images
Primary Image
Image Validation
Image Storage
Image URLs
Fallback Image
```

Product should ideally support:

```text
Product
   ├── Primary Image
   ├── Image 2
   ├── Image 3
   └── Image N
```

The first/primary image should be clearly defined.

---

# 14. IMAGE VALIDATION

Validate:

```text
File type
File size
Image dimensions if required
Number of images
```

Allow only appropriate image formats.

Do not trust file extensions alone.

Store images using the project's Laravel storage strategy.

Do NOT expose unsafe file paths.

---

# 15. IMAGE PERFORMANCE

Optimize image loading.

Check:

```text
Large image sizes
Unnecessary full-resolution images
Broken image URLs
Too many simultaneous image requests
Missing lazy loading
```

Use lazy loading where appropriate:

```html
<img loading="lazy">
```

Use thumbnails if the project supports them.

Product listing pages should not download huge original images unnecessarily.

---

# 16. IMAGE CLEANUP

When replacing/removing images:

- remove unused files safely
- do not delete images still referenced
- avoid orphaned database records
- avoid orphaned files
- handle failed uploads correctly

Do not accidentally remove historical data required elsewhere.

---

# 17. PRODUCT VARIANTS

Audit the complete Product Variant system.

Example:

```text
T-Shirt
   │
   ├── Small / Black
   ├── Small / White
   ├── Medium / Black
   ├── Medium / White
   └── Large / Black
```

Each variant may have:

```text
SKU
Price
Sale Price
Stock
Status
Image
Attribute Values
```

Adapt this to the existing schema.

---

# 18. ATTRIBUTES

Audit:

```text
Attributes
Attribute Values
Variant Attribute Values
```

Example:

```text
Attribute: Color

Values:
Black
White
Blue
```

Another:

```text
Attribute: Size

Values:
S
M
L
XL
```

Relationships should logically support:

```text
Product
   ↓
Variant
   ↓
Color: Black
Size: M
```

---

# 19. VARIANT GENERATION

If the existing application supports automatic variant generation, verify it carefully.

Example:

```text
Color:
Black
White

Size:
S
M
L
```

Possible combinations:

```text
Black / S
Black / M
Black / L

White / S
White / M
White / L
```

Do not create duplicate combinations.

Do not automatically generate huge numbers of combinations without safeguards.

---

# 20. VARIANT UNIQUENESS

Prevent duplicate variants.

Invalid:

```text
Black + M
Black + M
Black + M
```

Each valid attribute combination should normally represent one unique variant.

Also check SKU uniqueness.

---

# 21. VARIANT PRICING

Clearly define pricing logic.

Possible business rule:

```text
Product Base Price = $20

Small = $20
Medium = $22
Large = $25
```

Determine whether variants:

```text
inherit product price
```

or:

```text
have their own price
```

based on the existing project.

Make this logic consistent across:

```text
Admin
API
Product Details
Cart
Checkout
Order
```

---

# 22. PRODUCT WITHOUT VARIANTS

The system must handle simple products correctly.

Example:

```text
Laptop Charger
Price: $20
Stock: 30
```

Do not force every product to have:

```text
Color
Size
Variants
```

Support both:

```text
Simple Product
Variant Product
```

if that matches the project's requirements.

---

# 23. PRODUCT WITH VARIANTS

Example:

```text
T-Shirt
        ↓
Color
   ├── Black
   └── White

Size
   ├── S
   ├── M
   └── L
```

Customer must select a valid combination before adding it to cart.

Example:

```text
Color: Black
Size: M

Price: $22
Stock: 5

[ Add to Cart ]
```

---

# 24. INVALID VARIANT COMBINATIONS

Do not allow customers to select combinations that do not exist.

Example:

Available:

```text
Black / M
Black / L
White / S
```

Customer must not be able to buy:

```text
White / L
```

if that variant does not exist.

Disable or hide invalid combinations appropriately.

---

# 25. PRODUCT + INVENTORY INTEGRATION

Verify:

```text
Product
   ↓
Variant
   ↓
Inventory
```

Stock must come from the correct source.

Avoid having conflicting stock values in multiple places.

Determine the single source of truth.

For simple products:

```text
Product → Inventory
```

For variant products:

```text
Product
   ↓
Variant
   ↓
Inventory
```

Adapt this to the existing schema.

---

# 26. STOCK UX

Customer product page should clearly communicate:

```text
In Stock
Low Stock
Out of Stock
```

Do not allow:

```text
Add to Cart
```

for unavailable variants unless backorders are intentionally supported.

---

# 27. CART INTEGRATION

Test:

```text
Product
   ↓
Variant Selection
   ↓
Add to Cart
```

Cart item must preserve:

```text
Product
Variant
Selected Attributes
Price
Quantity
```

Example:

```text
Classic T-Shirt

Color: Black
Size: M

$22 × 2
```

Do not display only:

```text
Classic T-Shirt
```

when variant information matters.

---

# 28. ORDER INTEGRATION

Critical:

Orders must preserve the product information at the time of purchase.

If admin later changes:

```text
Product Name
Price
Image
SKU
Variant
```

an old order should still correctly represent what the customer purchased.

Inspect the existing `order_items` design.

Do not make historical orders depend entirely on current product values.

---

# 29. PRODUCT DETAILS CUSTOMER UX

Product details should clearly show:

```text
Product Name
Images
Price
Sale Price
Category
Brand
Description
Variant Options
Stock
Quantity
Add to Cart
Wishlist
Reviews
```

Only show information that is useful.

Do not overwhelm customers with internal data such as:

```text
Database IDs
Internal foreign keys
Internal status codes
```

---

# 30. PRODUCT LISTING UX

Product listing should support where appropriate:

```text
Search
Category Filter
Brand Filter
Price Filter
Availability Filter
Sort
Pagination
```

Possible sorting:

```text
Newest
Price: Low → High
Price: High → Low
Popular
Best Rated
```

Only implement sorting options that can be supported correctly.

---

# 31. SEARCH

Audit product search.

Search useful fields such as:

```text
Product Name
SKU
Category
Brand
```

depending on user context.

Use database-level searching.

Do NOT load every product into Vue and search everything client-side for large datasets.

---

# 32. SEARCH UX

Do not send an API request immediately after every keystroke.

Use reasonable debounce.

Example:

```text
User types:

i
ip
iph
ipho
iphone

→ Wait briefly
→ Search
```

Also handle:

```text
No results
Loading
API error
Empty search
```

---

# 33. FILTER BUSINESS LOGIC

Filters should work together.

Example:

```text
Category = Shoes
Brand = Nike
Price = $20-$100
Status = In Stock
Sort = Price Low → High
```

Changing one filter should not unexpectedly reset unrelated filters.

Preserve filters during pagination where appropriate.

---

# 34. PAGINATION

Do not load every product.

Use server-side pagination.

Example:

```text
20–30 products per page
```

depending on UI requirements.

API should return useful pagination metadata.

---

# 35. API DESIGN

Audit endpoints related to:

```text
Products
Categories
Brands
Images
Variants
Attributes
Attribute Values
```

Check:

```text
HTTP methods
Status codes
Validation
Authorization
Resources
Pagination
Filtering
Sorting
Error responses
```

Avoid unnecessarily huge responses.

---

# 36. PRODUCT API RESPONSE

Product listing should return only required data.

Do NOT automatically return:

```text
Every Image
Every Variant
Every Attribute
Every Review
Every Inventory Transaction
Every Order
```

for each product in the list.

Create lightweight list responses.

Product details can return richer data.

---

# 37. N+1 QUERY CHECK

Look specifically for N+1 queries.

Example problem:

```text
Load 100 products

Then separately:
100 category queries
100 brand queries
100 image queries
```

Optimize with appropriate eager loading.

But do NOT eager-load everything blindly.

Load only relationships required by the current endpoint.

---

# 38. DATABASE PERFORMANCE

Inspect indexes for frequently queried fields such as:

```text
category_id
brand_id
product_id
variant_id
sku
slug
status
price
created_at
```

Only add indexes where they support real query patterns.

Use query analysis when appropriate.

---

# 39. ADMIN PRODUCT TABLE

Admin product list should be easy to manage.

Useful columns might include:

```text
Image
Product
SKU
Category
Brand
Price
Stock
Status
Updated
Actions
```

Do not overcrowd the table.

Provide:

```text
Search
Filter
Sort
Pagination
```

Actions:

```text
View
Edit
Archive/Delete
```

depending on business rules.

---

# 40. PRODUCT FORM UX

Improve product forms.

Use clear sections:

```text
Basic Information
Category & Brand
Pricing
Images
Variants
Inventory
Status
```

Provide useful validation near fields.

Prevent double submission.

Show:

```text
Saving...
Uploading...
Creating...
Updating...
```

---

# 41. VARIANT MANAGEMENT UX

Do not make admins manually create every combination if the system can safely assist.

Example UI:

```text
Attributes

Color
[x] Black
[x] White

Size
[x] S
[x] M
[x] L

Generate Variants
```

Then:

```text
Variant       SKU       Price      Stock

Black / S     TS-B-S    $20        10
Black / M     TS-B-M    $22        5
Black / L     TS-B-L    $25        2
White / S     TS-W-S    $20        8
...
```

Only implement this if it fits the existing architecture.

---

# 42. BULK ACTIONS

Inspect whether admin product management would benefit from safe bulk operations.

Examples:

```text
Activate selected
Deactivate selected
Archive selected
Change category
Change brand
```

Do not add bulk operations that can easily cause destructive mistakes.

Require confirmation for destructive actions.

---

# 43. EMPTY STATES

Improve empty states.

Instead of showing an empty table:

```text
No products yet.

Create your first product.
```

Similarly:

```text
No categories found.
No brands found.
No variants configured.
No images uploaded.
```

Provide useful actions where appropriate.

---

# 44. ERROR HANDLING

Customers should never see:

```text
SQLSTATE
AxiosError
Exception stack trace
500 Internal Server Error details
```

Display friendly messages.

Log technical information appropriately.

---

# 45. LOADING EXPERIENCE

Use appropriate:

```text
Skeletons
Loading indicators
Disabled buttons
Lazy-loaded images
```

But do NOT hide slow APIs behind loading animations.

Fix actual performance issues.

---

# 46. SECURITY

Audit:

```text
Admin authorization
Product ownership if multi-shop exists
File upload security
Mass assignment
Validation
SQL injection
XSS-related data handling
IDOR
Unsafe delete operations
```

Frontend restrictions are NOT enough.

Laravel must enforce permissions.

---

# 47. MULTI-SHOP READINESS

Inspect whether E-KHMER currently supports or plans to support multiple shops/vendors.

If multi-shop functionality already exists, verify product ownership:

```text
Shop
 ↓
Products
 ↓
Variants
 ↓
Inventory
```

A shop must not edit another shop's products.

If multi-shop is NOT currently implemented, do not redesign the entire project.

Document what would need to change later.

---

# 48. PRODUCT SEO / SLUG

If customer product pages use slugs, verify:

```text
Unique slug
Stable URLs
Correct slug generation
Safe updates
404 handling
```

Example:

```text
/products/iphone-17-pro
```

Avoid exposing only database IDs when the existing design supports readable URLs.

---

# 49. PRODUCT PERFORMANCE

Measure:

```text
Product List API
Product Detail API
Product Search
Category Filter
Brand Filter
Admin Product List
Product Creation
Product Update
Image Upload
```

Find actual bottlenecks.

Optimize:

```text
Queries
API payload
Images
Frontend rendering
Duplicate requests
```

Do not invent before/after numbers.

---

# 50. RESPONSIVE UX

Test:

```text
Desktop
Tablet
Mobile
```

Especially:

```text
Product Cards
Product Images
Filters
Variant Selector
Product Form
Admin Product Table
```

Do not break mobile UX.

---

# 51. COMPLETE CUSTOMER FLOW TEST

Test:

```text
Home
 ↓
Category
 ↓
Product List
 ↓
Filter by Brand
 ↓
Search
 ↓
Product Details
 ↓
Select Variant
 ↓
Check Stock
 ↓
Add to Cart
 ↓
Checkout
 ↓
Order
```

Verify that product information remains correct through the entire flow.

---

# 52. COMPLETE ADMIN FLOW TEST

Test:

```text
Admin Login
 ↓
Create Category
 ↓
Create Brand
 ↓
Create Attributes
 ↓
Create Product
 ↓
Upload Images
 ↓
Configure Variants
 ↓
Configure Inventory
 ↓
Publish Product
 ↓
Customer Website
```

Then:

```text
Edit Product
 ↓
Change Price
 ↓
Change Images
 ↓
Change Variant
 ↓
Change Status
 ↓
Verify Customer Website
```

---

# 53. EDGE CASES

Test:

```text
Product without image
Product without brand
Product without variant
Product with many variants
Duplicate SKU
Duplicate slug
Deleted category
Deleted brand
Out-of-stock product
Out-of-stock variant
Inactive product
Invalid image
Huge image
Failed image upload
Missing product
Invalid variant
Duplicate attribute combination
Price = 0
Negative price
Sale price > regular price
Stock = 0
Negative stock
Concurrent updates
```

Application must fail gracefully.

---

# 54. AUTOMATED TESTS

Create or improve tests for:

```text
Product CRUD
Category CRUD
Brand CRUD
Image upload
Image deletion
Variant CRUD
Attribute CRUD
Attribute Values
Variant combinations
Validation
Authorization
Search
Filters
Pagination
Product status
Stock integration
```

Critical business tests:

```text
Inactive products are not purchasable.

Out-of-stock variants cannot be purchased.

Duplicate variant combinations are rejected.

Invalid category IDs are rejected.

Invalid brand IDs are rejected.

Unauthorized users cannot manage products.

Deleting a product does not corrupt old orders.
```

---

# 55. CODE REVIEW

Review code for:

```text
Business logic
Security
Performance
Readability
Maintainability
Laravel conventions
Vue conventions
Database integrity
Error handling
Validation
Authorization
```

Look for:

```text
Huge controllers
Duplicate code
Unused imports
Dead code
Repeated queries
Hard-coded values
Business logic inside Vue components
Missing transactions
Incorrect relationships
```

Refactor only where it improves the project.

---

# 56. DO NOT OVERENGINEER

Do NOT:

- rewrite the entire product system without reason
- add unnecessary packages
- create duplicate tables
- create duplicate APIs
- introduce unnecessary design patterns
- change working API contracts without checking frontend usage
- break existing database relationships
- remove existing working functionality

Prefer simple, maintainable solutions.

---

# 57. DEFINITION OF DONE

Products:

```text
[ ] Create works
[ ] Read works
[ ] Update works
[ ] Archive/delete works safely
[ ] Search works
[ ] Filters work
[ ] Sorting works
[ ] Pagination works
[ ] Status works
```

Categories:

```text
[ ] CRUD works
[ ] Product relationship works
[ ] Safe deletion works
[ ] Filtering works
```

Brands:

```text
[ ] CRUD works
[ ] Product relationship works
[ ] Safe deletion works
[ ] Filtering works
```

Images:

```text
[ ] Upload works
[ ] Multiple images work
[ ] Primary image works
[ ] Delete works
[ ] Preview works
[ ] Validation works
[ ] Performance is acceptable
```

Variants:

```text
[ ] Creation works
[ ] Update works
[ ] SKU works
[ ] Pricing works
[ ] Stock integration works
[ ] Duplicate prevention works
[ ] Customer selection works
```

Attributes:

```text
[ ] Attribute CRUD works
[ ] Values work
[ ] Variant mapping works
[ ] Duplicate combinations are prevented
```

Integration:

```text
[ ] Product → Category
[ ] Product → Brand
[ ] Product → Images
[ ] Product → Variants
[ ] Variant → Attributes
[ ] Variant → Inventory
[ ] Product → Cart
[ ] Product → Order
```

Quality:

```text
[ ] Validation works
[ ] Authorization works
[ ] Security checked
[ ] Performance checked
[ ] Responsive UI checked
[ ] API tested
[ ] Automated tests pass
[ ] No browser console errors
[ ] No unexpected Laravel errors
[ ] Existing features still work
```

---

# 58. FINAL AUDIT DOCUMENT

Create:

```text
docs/PRODUCT_CATALOG_AUDIT.md
```

Include:

```markdown
# E-KHMER Product Catalog Audit

## Executive Summary

## Products
### Problems Found
### Missing Features
### Changes Made

## Categories

## Brands

## Product Images

## Product Variants

## Attributes

## Database Relationships

## Business Logic Improvements

## Customer UX Improvements

## Admin UX Improvements

## API Improvements

## Security Improvements

## Performance Improvements

## Bugs Fixed

## Tests Added

## Remaining Issues

## Future Improvements

## Final Status
```

---

# 59. FINAL STATUS

Report:

```text
Products: PASS / NEEDS WORK
Categories: PASS / NEEDS WORK
Brands: PASS / NEEDS WORK
Product Images: PASS / NEEDS WORK
Product Variants: PASS / NEEDS WORK
Attributes: PASS / NEEDS WORK
Inventory Integration: PASS / NEEDS WORK
Cart Integration: PASS / NEEDS WORK
Order Integration: PASS / NEEDS WORK
API: PASS / NEEDS WORK
Admin UX: PASS / NEEDS WORK
Customer UX: PASS / NEEDS WORK
Security: PASS / NEEDS WORK
Performance: PASS / NEEDS WORK
Automated Tests: PASS / NEEDS WORK
```

Also report:

```text
Features completed:
Missing features implemented:
Bugs fixed:
Business logic improvements:
UX improvements:
Security issues fixed:
Performance improvements:
Database changes:
Tests added:
Remaining issues:
```

---

# 60. EXECUTION ORDER

Follow exactly:

```text
INSPECT EXISTING PROJECT
        ↓
UNDERSTAND DATABASE
        ↓
AUDIT PRODUCTS
        ↓
AUDIT CATEGORIES
        ↓
AUDIT BRANDS
        ↓
AUDIT IMAGES
        ↓
AUDIT VARIANTS
        ↓
AUDIT ATTRIBUTES
        ↓
CHECK INVENTORY INTEGRATION
        ↓
CHECK CART INTEGRATION
        ↓
CHECK ORDER INTEGRATION
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
TEST APIs
        ↓
ADD AUTOMATED TESTS
        ↓
TEST CUSTOMER FLOW
        ↓
TEST ADMIN FLOW
        ↓
REGRESSION TEST
        ↓
GENERATE FINAL AUDIT
```

# MOST IMPORTANT REQUIREMENT

Do not only analyze or write a report.

Actually inspect and modify the existing application.

Do not mark a feature as complete until it has been tested.

Treat these features as **one connected Product Catalog system**:

```text
Category + Brand
       ↓
     Product
       ↓
Product Images
       ↓
   Variants
       ↓
Attributes / Values
       ↓
   Inventory
       ↓
      Cart
       ↓
     Order
```

The final result must make product management easy for the admin and product selection easy for customers.

Priority:

**Correct Business Logic → Data Integrity → Security → User Experience → Performance → Maintainable Code.**