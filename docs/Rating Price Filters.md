# E-KHMER Improvement #06 — Rating & Price Filters

Act as a senior **Laravel + Vue.js + TypeScript Marketplace Engineer, Catalog/Search Engineer, UX Engineer, Performance Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace called **E-KHMER**.

Previous fixes:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
F03 — Discount Cap & Checkout Total Consistency
F04 — COD Collection & Paid-State Lifecycle
F05 — Guest Checkout Policy & Web Flow
```

Do NOT regress these fixes.

Now work ONLY on:

# F06 — Rating & Price Filters

---

# 1. CURRENT DEFECTS

The current audit identified two related catalog-filter problems.

## Problem A — Fractional Rating Filters

The frontend offers rating thresholds such as:

```text
4.5 stars & up
3.5 stars & up
```

but the backend currently rejects values like:

```text
rating=4.5
```

because validation expects an integer.

This creates a broken filter where the UI offers a value the API cannot accept.

---

## Problem B — Hardcoded Price Maximum

The frontend price filter currently assumes something like:

```text
Maximum price = $450
```

instead of using actual catalog price metadata.

This means a catalog containing:

```text
$500
$800
$1,200
```

may make those products impossible or confusing to filter correctly.

---

# 2. PRIMARY GOAL

Make rating and price filtering:

```text
Frontend
      ↓
Valid Query
      ↓
Laravel Validation
      ↓
CatalogService
      ↓
Database Query
      ↓
Correct Results
```

The frontend and backend must use the same filter contract.

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing anything.

Focus especially on:

```text
frontend/src/views/ShopView.vue

backend/app/Http/Requests/CatalogFilterRequest.php
backend/app/Services/CatalogService.php
```

Also inspect:

```text
frontend catalog API service
query-string generation
router query synchronization
product resources
rating aggregation
product models
review models
shop storefront filtering
public catalog endpoints
catalog tests
browser tests
```

Search for:

```text
rating
min_rating
price_min
price_max
min_price
max_price
450
priceRange
price_range
```

Do not assume only one page is affected.

---

# 4. TRACE THE FULL FILTER FLOW

Trace:

```text
Customer selects filter
        ↓
Vue state
        ↓
Router query
        ↓
API request
        ↓
Form Request validation
        ↓
CatalogService query
        ↓
MySQL
        ↓
API response
        ↓
UI
```

Identify the exact mismatch before modifying code.

---

# 5. DEFINE ONE FILTER CONTRACT

Create or preserve one clear API contract.

Conceptually:

```text
rating_min
price_min
price_max
```

or the repository's current naming.

Do not create multiple aliases unless backward compatibility requires them.

Example:

```text
GET /api/products?rating=4.5&min_price=50&max_price=300
```

Use actual route conventions.

---

# 6. FRACTIONAL RATING VALIDATION

If UI supports:

```text
5.0
4.5
4.0
3.5
3.0
```

backend validation must accept those supported values.

Possible validation concept:

```text
numeric
min:0
max:5
```

and optionally enforce increments such as:

```text
0.5
```

if that is the intended UX.

Do NOT simply remove validation.

---

# 7. DECIDE SUPPORTED RATING STEPS

Inspect existing UI and rating data.

Recommended if current UI uses half-stars:

```text
5.0
4.5
4.0
3.5
3.0
2.5
2.0
1.5
1.0
```

You do not need to expose every value if the design only uses:

```text
4.5+
4.0+
3.5+
3.0+
```

The key requirement is:

```text
Every value offered by frontend
must be accepted by backend.
```

---

# 8. RATING SEMANTICS

Confirm whether selecting:

```text
4.5 stars
```

means:

```text
rating >= 4.5
```

or:

```text
rating == 4.5
```

For e-commerce filtering, recommended behavior is:

```text
rating >= selected threshold
```

Example:

```text
Filter: 4.5 & up

Product A = 4.8 → included
Product B = 4.5 → included
Product C = 4.4 → excluded
```

Use existing business intent if already defined.

---

# 9. RATING SOURCE

Confirm which field is the source of truth.

Possible:

```text
products.rating
products.average_rating
review aggregate query
approved reviews only
```

Use the existing implementation.

Do NOT calculate ratings differently just for filtering.

Remember F10 separately addresses stale aggregate maintenance.

F06 should use the existing intended rating source.

---

# 10. RATING PRECISION

Do not accidentally round:

```text
4.49 → 4.5
```

and then include it in:

```text
4.5 & up
```

unless the business intentionally filters on displayed rounded rating.

Recommended:

```text
filter using stored/calculated actual rating
display rounded rating separately
```

Document the decision.

---

# 11. NO-RATING PRODUCTS

Determine expected behavior for products with:

```text
rating = null
review_count = 0
```

Recommended:

```text
rating filter active
→ unrated products excluded
```

Without a rating filter:

```text
unrated products may appear normally
```

Test explicitly.

---

# 12. ACTUAL PRICE RANGE

Remove the hardcoded:

```text
$450
```

or any fixed maximum used only for UI convenience.

The catalog should return or expose actual price range metadata.

Example:

```json
{
  "meta": {
    "price_range": {
      "min": 2.50,
      "max": 1299.99
    }
  }
}
```

Adapt to existing resource conventions.

---

# 13. DETERMINE PRICE-RANGE SOURCE

The range should represent purchasable catalog prices.

Inspect whether price lives on:

```text
products
variants
sale prices
selling prices
```

Determine correct source of truth.

For variant products:

```text
T-Shirt

Small  = $10
Medium = $12
Large  = $15
```

the catalog range should account for actual purchasable variant pricing according to existing product-card price rules.

---

# 14. SALE PRICE

Inspect how E-KHMER represents:

```text
regular price
selling price
original price
sale price
```

Price filtering should normally use the price the customer actually pays.

Do not filter using:

```text
original crossed-out price
```

if the active selling price is different.

Keep behavior consistent with product cards and checkout.

---

# 15. ACTIVE PRODUCTS ONLY

Public price-range metadata should normally be calculated from:

```text
active shop
+
active/public product
+
purchasable variant
```

Do not let:

```text
hidden product = $5,000
```

artificially push public price range to `$5,000` if customers cannot buy it.

Follow existing catalog visibility rules.

---

# 16. SHOP-SCOPED PRICE RANGE

For public shop pages:

```text
/shops/tech-store
```

the filter range should ideally reflect:

```text
Tech Store products
```

not the entire marketplace.

If Shop A prices are:

```text
$10 → $100
```

and Shop B has:

```text
$2,000
```

Shop A storefront should not need a `$2,000` slider.

Inspect how ShopStorefrontView currently queries catalog data.

---

# 17. CATEGORY-SCOPED PRICE RANGE

Decide whether price range metadata should update when:

```text
Category = Electronics
```

is selected.

Recommended UX:

```text
price range reflects current non-price filters
```

where practical.

Example:

```text
Marketplace:
$2 → $2,000

Electronics:
$100 → $2,000

Books:
$5 → $50
```

Do not create expensive queries without considering performance.

---

# 18. AVOID SELF-RESTRICTING PRICE METADATA

Important filter behavior:

If current query is:

```text
price_min=100
price_max=300
```

the API should not necessarily return metadata:

```text
min = 100
max = 300
```

just because those filters were already applied.

Otherwise the user cannot easily expand the range again.

Recommended:

```text
calculate range from other active filters
but exclude current price_min/price_max
```

if architecture supports faceted filtering.

If not, use a simpler stable catalog range and document it.

---

# 19. PRICE INPUT TYPES

Support valid decimals.

Examples:

```text
0
5
19.99
450.50
1299.99
```

Reject:

```text
-5
abc
NaN
Infinity
```

Backend validation should enforce:

```text
numeric
min:0
```

and logical range ordering.

---

# 20. MIN/MAX RELATIONSHIP

Reject:

```text
min_price = 500
max_price = 100
```

with a clear validation error.

Example:

```text
Maximum price must be greater than or equal to minimum price.
```

Do not silently swap values unless that behavior is deliberately designed.

---

# 21. PRICE = 0

If free products are valid in E-KHMER:

```text
0
```

must remain valid.

If free products are not supported, follow existing product-price validation.

Do not arbitrarily use:

```text
min:1
```

if `$0` is technically valid in current schema.

---

# 22. FRONTEND PRICE CONTROL

Improve current price UI.

Recommended:

```text
Price

$0 ─────────────── $1,299

Min
[ $0 ]

Max
[ $1,299 ]
```

Slider and text inputs should stay synchronized.

Do not require a slider only.

Text fields improve accessibility and precision.

---

# 23. INITIAL RANGE

When page loads:

```text
API
→ price_range.min
→ price_range.max
```

Use those values to initialize controls.

Do not write:

```ts
const MAX_PRICE = 450
```

unless `$450` is an intentional platform policy—which current audit indicates it is not.

---

# 24. FILTER URL STATE

Preserve filter values in URL when existing UX does this.

Example:

```text
/products?rating=4.5&min_price=100&max_price=500
```

Benefits:

```text
refresh works
back button works
shareable filter URL
```

Do not introduce duplicated or stale query keys.

---

# 25. RESET FILTERS

"Clear filters" should restore:

```text
rating = none
price min = actual minimum
price max = actual maximum
```

not:

```text
0 → 450
```

---

# 26. MOBILE AND DESKTOP CONSISTENCY

Test both interfaces.

The same filters must produce the same API query.

Example:

```text
Desktop:
4.5 & up

Mobile:
4.5 & up
```

Both send:

```text
rating=4.5
```

not one sending:

```text
4
```

or a label string.

---

# 27. FILTER COMPOSITION

Rating and price must work together with:

```text
search
category
brand
shop
stock
color
size
sort
pagination
```

Example:

```text
Electronics
Samsung
$100–$500
4.5 & up
In Stock
```

All should compose in one query.

Do not reset unrelated filters when changing rating or price.

---

# 28. PAGINATION

When a filter changes:

```text
page
```

should normally reset to:

```text
1
```

Otherwise customer may be on page 8 and see an empty result after narrowing filters.

Preserve other filters.

---

# 29. EMPTY RESULTS

If filters produce no products:

```text
No products match these filters.
```

Provide:

```text
Clear filters
```

or individual removable filter chips.

Do not display a generic API failure.

---

# 30. FILTER CHIPS

If current design supports chips, display:

```text
Rating: 4.5+
Price: $100–$500
Samsung
Electronics
```

Customer should understand what is active.

This is an improvement only if it fits current UI without unnecessary redesign.

---

# 31. LOADING STATE

When filters change:

```text
show loading/skeleton state
```

Do not leave old results looking final while the new request is in progress.

---

# 32. REQUEST RACE CONDITIONS

Filtering can generate rapid API requests.

Example:

```text
price slider:
100
110
120
130
```

Old response must not overwrite newer response.

Use existing request cancellation/latest-request patterns if present.

Consider:

```text
debounce
AbortController
request sequence
```

Do not add excessive complexity.

---

# 33. DEBOUNCE PRICE INPUT

If price slider fires constantly:

```text
debounce API request
```

for a short reasonable interval.

Do not debounce so heavily that the UI feels slow.

---

# 34. BACKEND QUERY PERFORMANCE

Inspect generated queries.

Rating and price filters may hit:

```text
products
variants
reviews
shops
```

Avoid N+1 behavior.

Use:

```text
indexed fields
efficient aggregates
exists queries
subqueries
```

as appropriate.

Do not optimize blindly.

---

# 35. DATABASE INDEXES

Inspect existing indexes before adding new ones.

Potentially relevant:

```text
products.price
products.status
products.shop_id
product_variants.price
product_variants.status
products.rating
```

Only add an index if query plans/use justify it.

Do not create unnecessary indexes for a small table just because the field is filtered.

---

# 36. API METADATA

If catalog already returns pagination metadata, extend cleanly.

Example:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "price_range": {
      "min": 4,
      "max": 1299.99
    }
  }
}
```

Do not break existing clients.

---

# 37. CURRENCY

Current storefront is USD-only according to project status.

For F06:

```text
use current USD catalog behavior
```

Do NOT implement KHR storefront filtering.

That is separate future work.

---

# 38. DISPLAY FORMATTING

Backend should provide numeric data.

Frontend can format:

```text
1299.99
```

as:

```text
$1,299.99
```

using existing currency utilities.

Do not send formatted values back as query parameters:

```text
"$1,299.99"
```

Use:

```text
1299.99
```

---

# 39. RATING DISPLAY

Filter label:

```text
4.5 & up
```

Product card can continue displaying:

```text
4.7 ★
```

Use existing component styles.

Do not change review design unnecessarily.

---

# 40. AUTOMATED BACKEND TESTS

Add/update tests for at least:

## Test 1 — Integer rating

```text
rating=4

→ accepted
```

## Test 2 — Fractional rating

```text
rating=4.5

→ accepted
```

## Test 3 — Another supported fractional rating

```text
rating=3.5

→ accepted
```

## Test 4 — Rating above max

```text
rating=5.5

→ 422
```

## Test 5 — Negative rating

```text
rating=-1

→ 422
```

## Test 6 — Rating filtering semantics

Products:

```text
4.8
4.5
4.4
```

Filter:

```text
4.5
```

Expected:

```text
4.8
4.5
```

only.

## Test 7 — No-rating product

Verify expected behavior.

---

# 41. PRICE BACKEND TESTS

## Test 8

```text
min_price=10
max_price=100

→ correct products
```

## Test 9

```text
min_price=19.99
max_price=49.99

→ decimal filtering works
```

## Test 10

```text
max_price > 450
```

Example:

```text
max_price=1000
```

must be accepted.

## Test 11

Product priced:

```text
$800
```

must remain discoverable/filterable.

## Test 12

```text
min=500
max=100

→ validation error
```

## Test 13

Negative prices rejected.

## Test 14

Actual price-range metadata is correct.

---

# 42. VARIANT PRICE TEST

If variants control actual selling price:

```text
Product:
Variant A = $20
Variant B = $100
```

Verify filter behavior according to current catalog semantics.

Example:

```text
max_price=50
```

Determine whether product appears because it has an eligible `$20` variant.

Use the same price semantics as product listing/detail pages.

Document the decision.

---

# 43. SALE PRICE TEST

Example:

```text
Original:
$500

Selling:
$400
```

Filter:

```text
max_price=450
```

Expected:

```text
included
```

if active selling price is the customer's payable price.

---

# 44. SHOP RANGE TEST

Shop A:

```text
$10
$50
$100
```

Shop B:

```text
$2,000
```

Shop A storefront price metadata should not incorrectly require `$2,000` if range is intended to be shop-scoped.

---

# 45. FRONTEND TESTS

Test:

```text
4.5 selection produces 4.5 query

3.5 selection produces 3.5 query

price range initializes from API

no hardcoded 450 remains

min/max inputs synchronize

clear filters resets range correctly

filters preserve category/brand/search

filter change resets page

mobile and desktop values match
```

---

# 46. BROWSER / E2E TEST

Create a regression test matching F06.

Fixture:

```text
Product A
rating 4.8
price $800

Product B
rating 4.2
price $300

Product C
rating 3.5
price $100
```

Test:

```text
Rating 4.5+
→ Product A only
```

Then:

```text
Price $500–$1000
→ Product A remains visible
```

This proves:

```text
fractional rating works
+
products above old $450 ceiling work
```

Run desktop/mobile where existing suite supports both.

---

# 47. API FAILURE

If catalog metadata request fails:

Do not reset slider to a misleading `$450`.

Use:

```text
safe error state
retry
```

or a reasonable existing fallback derived from loaded products only if clearly safe.

Do not silently invent catalog bounds.

---

# 48. F01–F05 REGRESSION

Run relevant regression tests.

Particularly confirm:

```text
F01 coupon isolation

F02 cart recovery

F03 financial totals

F04 COD lifecycle

F05 guest/account checkout policy
```

F06 should mainly touch catalog filtering, so those should remain unaffected.

---

# 49. FRONTEND TYPE CHECK

Run the project's existing:

```text
TypeScript type check
```

Do not solve errors using:

```text
any
@ts-ignore
```

without justification.

---

# 50. PRODUCTION BUILD

Run frontend production build.

Verify no:

```text
compile errors
broken imports
runtime config errors
```

---

# 51. DOCKER ONLY

Use existing Docker containers for:

```text
PHP
Composer
Node
tests
build
```

according to project standards.

Do not wipe development database.

Use isolated test data where possible.

---

# 52. DO NOT START F07

Only solve F06.

Do NOT begin mobile color-filter work.

That is:

```text
F07 — Mobile Color Filter Value/Slug Mismatch
```

If related code is encountered, document it without expanding scope.

---

# 53. DEFINITION OF DONE

Do not mark F06 complete until:

```text
[ ] 4.5 rating accepted

[ ] 3.5 rating accepted

[ ] Invalid ratings rejected

[ ] Rating filter uses correct >= semantics

[ ] Unrated-product behavior defined

[ ] Hardcoded $450 removed from filtering logic

[ ] Actual catalog price range returned/used

[ ] Products above $450 are filterable

[ ] Decimal prices work

[ ] min <= max validated

[ ] Selling price semantics are correct

[ ] Variant pricing behavior is correct

[ ] Public shop range behavior is correct

[ ] Desktop filters work

[ ] Mobile filters work

[ ] URL/query state works

[ ] Pagination resets appropriately

[ ] Filter composition works

[ ] Empty states work

[ ] Loading/error states work

[ ] Backend tests pass

[ ] Frontend tests pass

[ ] Browser regression passes

[ ] Type check passes

[ ] Production build passes

[ ] F01–F05 regressions pass
```

---

# 54. FINAL REPORT

At completion report exactly:

```text
F06 — RATING & PRICE FILTERS

Status:
PASS / NEEDS WORK

Root Causes:

Rating Validation Before:

Rating Validation After:

Supported Rating Thresholds:

Rating Filter Semantics:

Price Range Before:

Price Range After:

Price Source of Truth:

Variant Price Behavior:

Sale Price Behavior:

Shop-Specific Range:
YES / NO / N/A

Category-Aware Range:
YES / NO / N/A

Hardcoded $450 Removed:
YES / NO

Files Changed:

Schema Changes:
None / List

Indexes Added:
None / List + reason

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

F05 Regression:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F07 — Mobile Color Filters
```

Also provide a concrete before/after example:

```text
BEFORE

UI:
4.5 ★ & up

API:
422 validation error

Price slider:
$0 → $450

Product:
$800
Cannot be correctly represented by filter


AFTER

UI:
4.5 ★ & up

API:
Accepted

Rating results:
4.5+ only

Price range:
Derived from actual catalog

Example:
$4 → $1,299.99

$800 product:
Fully filterable
```

Stop after F06.

Do NOT automatically begin F07.

I will review the result first.