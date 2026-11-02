# F06 — RATING & PRICE FILTERS

Status:
PASS

Root Causes:
The UI offered fractional ratings but CatalogFilterRequest required integers. ShopView hardcoded a 0–450 price slider and ignored catalog price metadata. Existing metadata used unscoped product base prices rather than purchasable variant prices. The shop page did not forward rating filters or expose scoped range controls. Product failures were shown as empty results; catalog requests had no latest-response guard. Price bounds could silently swap instead of reporting an invalid range.

Rating Validation Before:
Integer, minimum 1, maximum 5; 4.5 and 3.5 returned 422.

Rating Validation After:
Numeric, minimum 1, maximum 5. Malformed, negative and above-maximum ratings remain rejected. The existing rating/min/max query names are retained without aliases.

Supported Rating Thresholds:
API: numeric values from 1 through 5, including fractional thresholds. UI: 4.5+, 4.0+, 3.5+ and Any, shared across desktop, mobile and the public shop page.

Rating Filter Semantics:
products.rating_avg >= selected threshold, using the existing stored two-decimal approved-review aggregate without rounding again. Products with rating_count = 0 are excluded when the rating filter is active and may appear without a rating filter. A stored 4.49 does not match 4.5+. F10 aggregate maintenance is unchanged.

Price Range Before:
ShopView used a hardcoded $0–$450 slider. The existing global facets price_range was not used and aggregated product base prices. Text inputs and shop-specific controls were absent.

Price Range After:
Catalog and shop listings include numeric meta.price_range alongside pagination; facets preserve data.price_range. Bounds follow search, category/descendants, brand, rating, attributes, stock visibility and shop, excluding current min/max and pagination. Missing purchasable prices produce null bounds. Shared number inputs/sliders accept cents, allow explicit bounds outside current metadata, validate min <= max, debounce edits and reset to actual bounds. Product and metadata errors provide retry instead of inventing a ceiling.

Price Source of Truth:
An active, non-deleted variant with available inventory uses its selling price, falling back to products.price when the variant price is null. Active/public product and active-shop visibility apply. Fully reserved, hidden, inactive and deleted inventory does not affect metadata. Existing imported listings without variants retain base-price listing/filter behavior but cannot inflate purchasable metadata. Products with no available variant can still appear without price/stock filtering; they do not match an active price window.

Variant Price Behavior:
One purchasable variant must satisfy both price bounds and selected attribute values. A product with variants $20/$100 matches max=50 at $20 and min=80&max=120 at $100; it does not match min=30&max=90. The card displays the lowest matching variant price, orders an available variant at that price first for add-to-cart, and sorts by the same advertised price.

Sale Price Behavior:
Filters use current selling price. A $400 selling price with a crossed-out $500 price matches max=450. compare_at_price and private purchase cost are not filter inputs.

Shop-Specific Range:
YES — scoped by the public shop route; another shop's $2,000 product cannot expand this shop's $10–$100 range.

Category-Aware Range:
YES — including the existing category descendant rules. Price metadata follows non-price filters while other facet counts retain their existing semantics.

Hardcoded $450 Removed:
YES — removed from storefront filter logic on both screen sizes.

Files Changed:

- backend/app/Http/Requests/CatalogFilterRequest.php
- backend/app/Services/CatalogService.php
- backend/app/Http/Controllers/Api/CatalogController.php
- backend/app/Http/Controllers/Api/ShopController.php
- backend/app/Http/Resources/ProductResource.php
- backend/tests/Feature/Api/CatalogFiltersTest.php
- frontend/src/api/catalog.ts
- frontend/src/components/CatalogPriceFilter.vue
- frontend/src/components/CatalogRatingFilter.vue
- frontend/src/views/ShopView.vue
- frontend/src/views/ShopStorefrontView.vue
- frontend/tests/e2e/catalog-filters.spec.ts
- docs/API-REFERENCE.md
- docs/COMMERCE-COMPLETION.md
- docs/F06-RATING-PRICE-FILTERS-REPORT.md

The supplied docs/Rating Price Filters.md is unchanged. No F07 mobile color control was modified.

Schema Changes:
None.

Indexes Added:
None. Read-only MySQL EXPLAIN showed the existing product visibility index, (product_id, is_active) variant index, inventory variant/shop index and shop primary key support these queries. The small development product listing uses a scan/filesort for derived-price ordering; a simple products.price index would not order a price derived from live variant availability. No new index was justified. Price bounds are aggregated in SQL and catalog relationships stay eager-loaded. A test confirms query count remains constant from one to nine products. The temporary diagnostic script was removed.

Tests Added:
30 backend cases covering integer/fractional ratings, invalid and non-finite input, exact rating boundaries/unrated products, decimal/zero/high prices, ordered bounds, numeric/empty metadata, variant selection, selling-price fallback, hidden/unavailable products, shop/category scope, composed filters, sorting and constant query counts. Eight browser scenarios run on both desktop and Pixel 7 emulation (16 cases), covering the $800 regression, 3.5 thresholds, synchronized controls, validation, clear/reset, URL history/refresh, composition/page reset, changing metadata, stale responses, retry and shop ranges.

Backend Tests:
Passed — 298 relevant catalog and F01–F05 regression tests, 2,219 assertions, using isolated SQLite. Read-only catalog/sort/range smoke queries and EXPLAIN also ran against the development MySQL database without changing its data.

Frontend Tests:
Passed — all 16 F06 browser cases and 84 existing commerce browser cases.

Browser Tests:
Passed — 100 cases across desktop Chrome and Pixel 7 emulation, with retries disabled, in two runs (16 F06 + 84 F01–F05). Browser APIs are mocked; Laravel tests exercise actual database/API behavior separately.

Type Check:
PASS — Docker frontend vue-tsc -b.

Production Build:
PASS — Docker npm run build (vue-tsc -b && vite build).

F01 Regression:
PASS — coupon ownership/isolation backend tests and shop-coupons browser suite.

F02 Regression:
PASS — cart/guest merge/recovery backend tests and cart-recovery browser suite.

F03 Regression:
PASS — checkout quote/payment totals backend tests and checkout-totals browser suite.

F04 Regression:
PASS — COD lifecycle/collection backend tests and cod-collection browser suite.

F05 Regression:
PASS — guest checkout/auth/address/payment backend coverage and guest-checkout browser suite, including browse/add/cart/checkout.

Remaining Risks:
Production-scale timing should be measured with representative catalog data, especially sorting by a price derived from live variants. F10's stale aggregate maintenance remains separate. F07's existing mobile color value/slug mismatch remains unchanged. USD-only behavior is preserved.

Related Follow-Up:
F07 — Mobile Color Filters (not started).

Concrete before/after:

```text
BEFORE
UI: 4.5 stars & up
API: 422 validation error
Price slider: $0 → $450
$800 product: cannot be represented correctly by the price control

AFTER (regression fixture)
UI: 4.5 stars & up
API: rating=4.5 accepted; only products rated 4.5+ remain
Initial range: actual $4 → $1,299.99 catalog metadata
Price selection: min=500&max=1000
$800 product rated 4.8: visible and fully filterable
```

Validation commands (all runtimes inside Docker):

```sh
docker compose exec -T app php artisan test --compact --filter 'CatalogTest|CatalogFiltersTest|ShopTest|CategoryTest|ShopCouponOwnershipTest|CartRecoveryTest|CartTest|Checkout|CouponTest|CodCollectionTest|BakongPaymentTest|AuthTest|NotificationTest|MarketplaceFlowTest'
docker compose run --rm browser-tests npx playwright test tests/e2e/catalog-filters.spec.ts --reporter=line --retries=0
docker compose run --rm browser-tests npx playwright test tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts tests/e2e/guest-checkout.spec.ts --reporter=line --retries=0
docker compose exec -T frontend npm run build
```

Changed PHP files passed Laravel Pint; git diff --check passed. No environment file, credentials, production deployment or development data reset is included. Stop after F06.
