# E-KHMER — Full Application Performance Optimization Prompt

You are a senior **Laravel + Vue.js + MySQL + Docker performance engineer**.

I have an existing E-Commerce application called **E-KHMER**.

The application currently feels **slow, delayed, and not smooth** when navigating pages, loading data, opening the dashboard, searching, filtering, submitting forms, and communicating with the API.

Your task is to **inspect the existing project first, identify the real performance problems, then fix and optimize them directly in the code**.

Do NOT only give recommendations.

You must:
**INSPECT → MEASURE → IDENTIFY → OPTIMIZE → TEST → VERIFY**

---

# 1. IMPORTANT RULES

Before changing anything:

* Inspect the complete existing project.
* Understand the current Laravel backend architecture.
* Understand the Vue frontend architecture.
* Understand the database structure.
* Understand Docker configuration.
* Do not rewrite the whole application unnecessarily.
* Do not remove existing features.
* Do not change business logic unless required for performance.
* Do not break existing API endpoints.
* Do not break authentication/authorization.
* Do not create duplicate code.
* Do not add unnecessary packages.
* Keep the existing UI/design unless performance requires a small change.
* Do not use fake optimization.
* Do not simply add loading spinners and call the application optimized.
* Find the actual bottleneck before fixing it.

Every optimization must be verified.

---

# 2. FIRST: PERFORMANCE AUDIT

Inspect:

```text
Laravel Backend
Vue Frontend
MySQL Database
API Requests
Docker
Nginx
Redis
Vite
Images
JavaScript
CSS
Network Requests
Database Queries
```

Identify:

* slow API requests
* slow database queries
* N+1 queries
* excessive API requests
* duplicate API requests
* unnecessary frontend re-renders
* large API responses
* unnecessary database columns
* missing indexes
* inefficient joins
* inefficient Eloquent queries
* unnecessary eager loading
* unnecessary lazy loading
* large images
* large JavaScript bundles
* unnecessary dependencies
* slow dashboard queries
* slow search/filter operations
* unnecessary Docker overhead
* slow development server configuration
* blocking requests
* sequential API requests that could run concurrently
* unnecessary page reloads
* unnecessary state updates

Create a performance report before making major changes.

---

# 3. PERFORMANCE STATUS

For every important area, classify:

```text
FAST
ACCEPTABLE
SLOW
VERY SLOW
CRITICAL
```

Record:

```text
Area
Current Problem
Root Cause
Impact
Recommended Fix
Priority
```

Priority:

```text
CRITICAL
HIGH
MEDIUM
LOW
```

---

# 4. LARAVEL BACKEND OPTIMIZATION

Inspect all controllers, services, repositories, models, middleware, routes, and API resources.

Optimize:

* Eloquent queries
* relationships
* eager loading
* pagination
* API Resources
* query filtering
* sorting
* searching
* aggregation queries
* dashboard statistics
* authentication
* authorization
* middleware
* validation
* serialization

Look specifically for N+1 queries.

Bad example:

```php
foreach ($products as $product) {
    $product->category->name;
}
```

Replace with appropriate eager loading:

```php
Product::with('category')->get();
```

But do NOT blindly eager-load every relationship.

Only load relationships that are actually required.

---

# 5. DATABASE QUERY OPTIMIZATION

Inspect the database schema and migrations.

Find columns frequently used for:

```text
WHERE
JOIN
ORDER BY
GROUP BY
SEARCH
FOREIGN KEY
```

Check whether appropriate indexes exist.

Potential areas:

```text
users
categories
brands
products
product_variants
inventories
orders
order_items
payments
carts
cart_items
wishlists
wishlist_items
coupons
reviews
notifications
shipments
```

Add indexes only where they improve real queries.

Do NOT create unnecessary indexes everywhere.

Check:

* slow queries
* full table scans
* unnecessary SELECT *
* inefficient joins
* duplicate queries
* unnecessary COUNT queries
* unnecessary database calls

Prefer:

```php
select(...)
```

when the API does not need every column.

---

# 6. PAGINATION

Find every page that loads large datasets.

Examples:

```text
Products
Orders
Customers
Reviews
Notifications
Inventory
Payments
Users
Categories
Brands
```

Do not load thousands of records at once.

Use appropriate pagination:

```php
->paginate(20)
```

or another reasonable page size.

Make sure frontend pagination works correctly.

---

# 7. API RESPONSE OPTIMIZATION

Inspect every API endpoint.

Avoid returning unnecessary data.

Bad:

```text
products
    ├── every product column
    ├── every relationship
    ├── every image
    ├── every review
    ├── every inventory transaction
    └── every order
```

Return only the data required by the current page.

Use:

```text
API Resources
select()
pagination
conditional relationships
```

Check response sizes.

If an endpoint returns huge JSON responses, optimize it.

---

# 8. DASHBOARD OPTIMIZATION

The Admin Dashboard must load quickly.

Inspect all dashboard queries.

Check:

```text
Total Users
Total Products
Total Orders
Total Sales
Pending Orders
Low Stock
Recent Orders
Recent Customers
Sales Statistics
Order Statistics
```

Do not execute many expensive queries every time the dashboard loads.

Optimize dashboard statistics using:

* efficient aggregate queries
* caching where appropriate
* optimized indexes
* limited result sets
* parallel API requests when appropriate

Do NOT cache data that must always be real-time unless the cache strategy is safe.

---

# 9. LARAVEL CACHE

Identify data that changes infrequently.

Possible cache candidates:

```text
Categories
Brands
Shipping Methods
System Settings
Dashboard statistics
Frequently requested configuration
```

Use Laravel Cache appropriately.

Example:

```php
Cache::remember(...)
```

Do not cache user-specific or highly dynamic data incorrectly.

Make sure cache invalidation works when data changes.

---

# 10. VUE FRONTEND OPTIMIZATION

Inspect the complete Vue application.

Find:

* unnecessary component re-renders
* unnecessary watchers
* unnecessary computed calculations
* duplicate API calls
* unnecessary state updates
* large components
* duplicated components
* inefficient loops
* unnecessary DOM rendering
* unnecessary requests when navigating pages

Optimize components where necessary.

Do not prematurely optimize every component.

Focus on real bottlenecks.

---

# 11. API REQUEST OPTIMIZATION

Inspect the browser Network tab behavior.

Find:

```text
Duplicate requests
Requests repeated unnecessarily
Requests triggered multiple times
Sequential requests
Large requests
Slow requests
Requests after every keystroke
```

For example, if searching:

```text
Do not send an API request for every single character immediately.
```

Use appropriate debounce behavior.

Example:

```text
User types:
phone

Instead of:
p → API
ph → API
pho → API
phon → API
phone → API

Use a short debounce.
```

---

# 12. PARALLEL API REQUESTS

Find pages that perform independent requests sequentially.

Example:

```text
Get categories
wait
Get brands
wait
Get products
wait
Get statistics
```

If requests are independent, consider running them concurrently.

For example:

```javascript
Promise.all([
    getCategories(),
    getBrands(),
    getProducts(),
    getStatistics()
])
```

Only do this when the requests are actually independent.

---

# 13. VUE ROUTING OPTIMIZATION

Inspect Vue Router.

Use lazy loading for large pages/components where appropriate.

Example:

```javascript
const Dashboard = () => import('./pages/Dashboard.vue')
```

Apply route-level code splitting where useful.

Do not lazy-load tiny components unnecessarily.

---

# 14. FRONTEND BUNDLE OPTIMIZATION

Inspect:

```text
package.json
Vite configuration
JavaScript bundle
CSS bundle
Images
Dependencies
```

Find:

* unused dependencies
* unnecessarily large libraries
* duplicate libraries
* unnecessary imports
* importing entire libraries when only one function is needed

Optimize imports where appropriate.

Do not remove dependencies without checking whether the application uses them.

---

# 15. IMAGE OPTIMIZATION

Inspect all product images and frontend images.

Optimize:

```text
Image dimensions
Image file size
Image format
Lazy loading
Thumbnail usage
```

Use:

```html
loading="lazy"
```

where appropriate.

Do not load full-size product images when a small thumbnail is enough.

Avoid loading hundreds of images simultaneously.

---

# 16. SEARCH / FILTER / SORT

Optimize:

```text
Product Search
Product Filtering
Category Filtering
Brand Filtering
Price Filtering
Order Filtering
Customer Search
Inventory Search
Review Filtering
```

Make sure filtering happens efficiently.

Avoid loading all records into PHP/Vue and filtering them there when the database can efficiently handle the filtering.

Prefer database-level filtering:

```php
Product::query()
    ->when(...)
    ->where(...)
    ->orderBy(...)
    ->paginate(...);
```

---

# 17. FRONTEND TABLE OPTIMIZATION

Inspect large tables such as:

```text
Products
Orders
Customers
Inventory
Payments
Reviews
```

Avoid rendering thousands of rows simultaneously.

Use:

```text
Pagination
Server-side filtering
Server-side sorting
Limited page size
```

Only use virtual scrolling if genuinely necessary.

---

# 18. FORM PERFORMANCE

Inspect:

```text
Product Form
Product Variant Form
Checkout Form
Customer Form
Category Form
Brand Form
Coupon Form
Shipping Form
```

Prevent unnecessary API calls.

Validation should be efficient.

Do not submit duplicate requests.

Prevent double-click submission.

Use appropriate:

```text
loading state
disabled state
request handling
error handling
```

---

# 19. LOADING EXPERIENCE

Improve perceived performance without hiding real problems.

Use:

```text
Skeleton loading
Loading states
Lazy loading
Progress indicators
Optimistic UI where safe
```

But do NOT use loading animations to hide slow APIs.

The actual backend and frontend performance must be improved.

---

# 20. MYSQL OPTIMIZATION

Inspect:

```text
Indexes
Foreign Keys
Relationships
Query performance
Data types
Duplicate data
Large tables
```

Check important queries using query analysis tools where available.

Look for:

```text
Full table scans
Slow joins
Unindexed filters
Unindexed foreign keys
Large result sets
Repeated queries
```

Do not change production data destructively.

---

# 21. REDIS

If Redis already exists in the project:

Inspect whether it is configured correctly.

Consider Redis for:

```text
Cache
Sessions
Queues
Background jobs
```

Do not introduce Redis unnecessarily if the current application does not benefit from it.

---

# 22. QUEUES / BACKGROUND JOBS

Find operations that should not block the HTTP request.

Potential examples:

```text
Email notifications
Telegram notifications
Large report generation
Image processing
Heavy background processing
```

Use Laravel Jobs/Queues when appropriate.

The user should not have to wait for long background operations unnecessarily.

---

# 23. DOCKER PERFORMANCE

Inspect:

```text
docker-compose.yml
Dockerfile
Volumes
Networks
PHP configuration
Nginx
MySQL
Redis
Node/Vite
```

Find unnecessary overhead.

Check:

* volume configuration
* file synchronization
* container resource usage
* unnecessary services
* PHP configuration
* OPcache
* MySQL configuration
* Nginx configuration

For development, make sure file watching/HMR works efficiently.

Do not optimize development configuration in a way that breaks production.

---

# 24. PHP OPcache

Check whether OPcache is enabled where appropriate.

For production configuration, ensure PHP bytecode caching is configured correctly.

Do not use unsafe development settings in production.

---

# 25. NGINX OPTIMIZATION

Inspect Nginx configuration.

Check:

```text
Compression
Static file serving
Caching headers
Keep-alive
Proxy configuration
PHP-FPM connection configuration
```

Only make safe changes that fit the existing architecture.

---

# 26. VITE DEVELOPMENT PERFORMANCE

Inspect Vite configuration.

Make sure:

```text
HMR
File watching
Aliases
Dependency optimization
```

are configured correctly.

Do not disable HMR or important development functionality just to make the application appear faster.

---

# 27. NETWORK PERFORMANCE

Inspect browser Network requests.

For each major page identify:

```text
Number of requests
Largest request
Slowest request
Duplicate request
Total loading time
```

Reduce unnecessary requests.

Do not combine unrelated APIs into one huge endpoint just to reduce request count.

Optimize based on actual behavior.

---

# 28. AUTHENTICATION PERFORMANCE

Inspect:

```text
Login
Register
Logout
/me
Permission checking
Role checking
Middleware
```

Make sure authentication does not cause unnecessary repeated requests.

If `/me` is requested multiple times unnecessarily, fix the frontend state flow.

Do not weaken security for performance.

---

# 29. ADMIN DASHBOARD PERFORMANCE

Test:

```text
Dashboard
Products
Categories
Brands
Inventory
Orders
Payments
Shipping
Customers
Coupons
Reviews
Notifications
Reports
Settings
```

Every page should:

* load only required data
* use pagination
* avoid duplicate requests
* show loading state
* handle errors
* avoid unnecessary re-rendering

---

# 30. CUSTOMER WEBSITE PERFORMANCE

Test:

```text
Home
Product Listing
Product Details
Search
Category
Brand
Cart
Wishlist
Checkout
Orders
Profile
Reviews
```

Focus especially on:

```text
Product listing
Product details
Search
Cart
Checkout
```

because these are high-traffic user flows.

---

# 31. MOBILE PERFORMANCE

Test the frontend at:

```text
Mobile
Tablet
Desktop
```

Make sure optimization does not break responsive UI.

---

# 32. ERROR AND EXCEPTION CHECK

Inspect:

```text
Laravel logs
Browser console
Network errors
Vue errors
MySQL errors
Docker logs
Nginx logs
```

Fix performance-related errors.

Do not ignore warnings that indicate repeated failures or unnecessary requests.

---

# 33. MEASURE BEFORE AND AFTER

For important pages record:

```text
Before optimization
After optimization
Improvement
```

Example:

```text
Admin Dashboard
Before: 3.8 seconds
After: 1.4 seconds

Products API
Before: 1.9 seconds
After: 0.4 seconds

Product page
Before: 2.7 seconds
After: 1.1 seconds
```

Use actual measurements.

Do NOT invent numbers.

---

# 34. PERFORMANCE TARGETS

Aim for:

### Frontend

```text
Fast initial page load
Fast navigation
Minimal unnecessary requests
Smooth interaction
No obvious UI freezing
```

### API

Most normal CRUD API requests should ideally respond within:

```text
< 500ms
```

when tested locally under normal conditions.

Heavy operations may take longer but should be optimized or moved to background processing when appropriate.

### Database

Normal queries should generally be very fast.

Investigate any frequently executed query that takes noticeably long.

---

# 35. DO NOT BREAK EXISTING FEATURES

After optimization, test:

```text
Authentication
Authorization
Products
Categories
Brands
Variants
Cart
Wishlist
Checkout
Orders
Payments
Coupons
Inventory
Shipping
Notifications
Reviews
Admin Dashboard
Customer Website
Search
Filter
Pagination
Reports
```

Everything that worked before must continue to work.

---

# 36. REGRESSION TESTING

After each major optimization:

1. Run backend tests.
2. Run frontend tests if available.
3. Test API endpoints.
4. Test database operations.
5. Test authentication.
6. Test customer flow.
7. Test admin flow.
8. Check browser console.
9. Check Network tab.
10. Check Laravel logs.
11. Check Docker logs.

Do not make many unrelated changes at once without testing.

---

# 37. PERFORMANCE PRIORITY

Fix issues in this order:

### P0 — Critical

* Application freezes
* Extremely slow APIs
* Infinite requests
* Infinite loops
* Database queries causing major delays
* Memory problems
* Requests repeated continuously

### P1 — High

* N+1 queries
* Slow dashboard
* Large API responses
* Missing important indexes
* Duplicate API requests
* Large frontend bundles

### P2 — Medium

* Image optimization
* Lazy loading
* Route splitting
* Cache improvements
* UI rendering improvements

### P3 — Low

* Minor code cleanup
* Small bundle improvements
* Non-critical refactoring

---

# 38. CODE QUALITY

While optimizing:

* Keep code readable.
* Keep Laravel conventions.
* Keep Vue conventions.
* Avoid premature optimization.
* Avoid complicated solutions when a simple solution works.
* Add comments only where necessary.
* Remove dead code when safely verified.
* Do not duplicate logic.
* Keep reusable functions/components reusable.

---

# 39. FINAL PERFORMANCE TEST

After all optimizations, test the application from a fresh start.

Start:

```text
Docker
Laravel
Vue/Vite
MySQL
Redis if used
```

Then test the complete application.

Check:

```text
Login
Dashboard
Product listing
Product details
Search
Cart
Wishlist
Checkout
Order
Admin product management
Admin order management
Inventory
Reports
```

Measure the slowest pages and APIs.

---

# 40. FINAL REPORT

Create:

```text
docs/PERFORMANCE_OPTIMIZATION_REPORT.md
```

Include:

```markdown
# E-KHMER Performance Optimization Report

## 1. Executive Summary

## 2. Problems Found

## 3. Root Causes

## 4. Backend Optimizations

## 5. Database Optimizations

## 6. API Optimizations

## 7. Vue Optimizations

## 8. Image Optimizations

## 9. Docker Optimizations

## 10. Caching

## 11. Query Optimization

## 12. Before vs After Measurements

## 13. Bugs Fixed

## 14. Tests Performed

## 15. Remaining Performance Issues

## 16. Recommendations

## 17. Final Performance Status
```

---

# 41. FINAL OUTPUT

At the end, report:

```text
Total Problems Found:
Critical:
High:
Medium:
Low:

Problems Fixed:
Problems Remaining:

Slowest API:
Slowest Page:
Slowest Database Query:

Duplicate Requests Removed:
N+1 Queries Fixed:
Indexes Added:
API Responses Optimized:
Components Optimized:
Images Optimized:

Testing Status:
Backend:
Frontend:
Database:
Docker:
Customer Flow:
Admin Flow:

Overall Performance:
BEFORE:
AFTER:
```

Use real measurements only.

---

# 42. MOST IMPORTANT INSTRUCTION

Do not stop after identifying problems.

Do not only generate a report.

Actually:

```text
INSPECT
↓
FIND BOTTLENECKS
↓
FIX THE CODE
↓
OPTIMIZE DATABASE
↓
OPTIMIZE API
↓
OPTIMIZE VUE
↓
OPTIMIZE DOCKER
↓
TEST
↓
MEASURE
↓
FIX REGRESSION BUGS
↓
TEST AGAIN
↓
GENERATE PERFORMANCE REPORT
```

The final goal is:

> Make the existing E-KHMER application feel significantly faster, smoother, and more responsive while preserving all existing functionality, security, and business logic.
