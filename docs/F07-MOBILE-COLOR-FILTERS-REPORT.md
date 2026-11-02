# F07 — MOBILE COLOR FILTERS

Verified: 2026-11-02. Scope stops after F07.

Status:
PASS

Root Cause:
`ShopView.vue` had separate desktop and mobile color markup. Desktop passed `c.value` and rendered `c.swatch`; mobile passed `c.slug`, checked selection against `c.slug`, and used `c.slug` as CSS background color. The backend already matches actual attribute values, so `light-blue` did not match `Light Blue` and was also an invalid swatch. One shared typed `CatalogColorFilter` now handles both layouts.

Old Mobile Value:
`light-blue`

Correct Canonical Value:
`Light Blue` — the actual attribute value, using facet `value ?? name` for compatibility with older facet payloads.

Desktop Behavior:
Preserves canonical value filtering and API-provided swatches. The shared component adds visible color names and pressed state. Desktop and mobile read and update the same `filters.colors: string[]`.

Mobile Behavior After Fix:
Selecting Light Blue sends `colors[]=Light Blue`, displays its API swatch, and selects the same products as desktop. Selection, deselection, chips, chip removal, URL state, refresh/history, Clear all and responsive resize all use actual values. Color edits reset pagination and preserve other filters. The existing sheet applies changes immediately; Apply, Close and backdrop dismissal close the sheet while retaining those already-applied changes. There is no staged Cancel action.

Color Object Shape:
The real API facet, supplied from product/variant attribute metadata, is:

```json
{ "slug": "light-blue", "value": "Light Blue", "name": "Light Blue", "swatch_color": "#ADD8E6", "count": 1 }
```

The existing `FacetValue` TypeScript interface makes `value` and `swatch_color` optional for compatibility. The shared component normalizes to `{ slug, value, name, swatch, count }`. `value` is submitted, `name` is displayed, and `swatch` is a validated CSS color resolved from `swatch_color` or the existing fallback palette. Slug metadata is never submitted as the selected value. Missing/invalid swatches retain a visible, selectable label and use the name palette or neutral `#cbd5e1`.

Filter API Contract:
Existing `colors[]` values on `/api/catalog/products`, `/api/catalog/facets` and `/api/shops/{slug}/products`; single-string and CSV forms remain supported. Example: `colors[]=Light%20Blue&colors[]=Black`. Validation accepts at most 50 string entries, each at most 255 characters, and returns 422 for nested/non-string or excessive input. Unknown values return an empty listing. Slugs are not aliases. Matching explicitly lowercases actual values, so mixed case matches without depending on MySQL collation; spaces and punctuation remain significant. Multiple colors remain OR, with existing AND composition across attributes and other filters. Filtering, variant eligibility and pagination remain server-side. Vue page URLs keep repeated `colors` keys, such as `/shop?colors=Light+Blue&colors=Black`; API calls serialize bracketed arrays.

Multi-Word Colors:
PASS — Light Blue, Dark Green, Rose Gold, Midnight Blue and Space Gray. Black, White, Red, Blue, Black/White, Blue & Gray and Off-White also pass, including URL encoding.

Multiple Colors:
PASS — Light Blue + Black sends both actual values and returns both matching fixture products. Deselect and chip removal remove the exact value.

Swatch Rendering:
PASS — API `#ADD8E6` renders as `rgb(173, 216, 230)` on desktop and mobile. Missing/invalid custom swatches render the neutral fallback and retain their labels.

Marketplace Filter:
PASS — identical desktop and mobile requests and results, including composition and page reset.

Shop Storefront Filter:
PASS for URL/API filtering. Public shop pages have no existing color picker; picker comparison is N/A. They now forward canonical color and size URL arrays to their shop-scoped API and retain them through rating/price changes. Clear filters removes them. No new shop picker was introduced.

Rating + Price + Color Composition:
PASS — Light Blue with rating 4.5+, price $100–$500, category, brand, stock and sort produces one valid composed request and matching result. Backend tests also verify forced shop scope and price metadata.

Files Changed:

| File | Change |
| --- | --- |
| `frontend/src/components/CatalogColorFilter.vue` | New shared typed color control, canonical toggle values, pressed state, labels and validated/fallback swatches. |
| `frontend/src/views/ShopView.vue` | Reuse shared control on desktop/mobile; canonical active color chips/removal; accessible sheet and close labels. |
| `frontend/src/views/ShopStorefrontView.vue` | Forward canonical color/size URL arrays to shop product requests. |
| `frontend/src/api/shops.ts` | Permit typed string-array request parameters. |
| `backend/app/Http/Requests/CatalogFilterRequest.php` | Bounded color-array/string validation with existing single-value/CSV compatibility. |
| `backend/tests/Feature/Api/CatalogColorsTest.php` | New actual API/database regression coverage. |
| `frontend/tests/e2e/catalog-colors.spec.ts` | New shared-control and desktop/mobile browser regression coverage. |
| `docs/API-REFERENCE.md` | Document real color shapes, canonical values, matching and validation. |
| `docs/COMMERCE-COMPLETION.md` | Document F07 behavior and mobile immediate-apply policy. |
| `docs/F07-MOBILE-COLOR-FILTERS-REPORT.md` | This report and reproducible checks. |

The supplied `Mobile Color Filter Value - Slug Mismatch.md` specification was left unchanged. `CatalogService` and API resources required no changes: the existing database value contract is correct. Existing ID-based variant resolution remains separate; F07 does not migrate catalog filters to IDs.

Schema Changes:
None. No migrations, new indexes or development data resets.

Tests Added:
21 backend cases: twelve canonical values on both marketplace and shop endpoints; unsupported slugs, unknown values and query fragments; explicit case behavior; multiple-color OR plus single-string/CSV compatibility; category/brand/rating/price/shop composition; pagination; real facet shape; and three malformed/excessive input cases. Eight browser scenarios run on both desktop Chrome and Pixel 7 emulation (16 cases): canonical request/swatch/results; multiple colors and chip removal; all twelve values and encoding; reload/history/clear; composed filters/page reset; missing/invalid swatches and legacy name fallback; sheet close and resize; and public shop URL/API forwarding.

Backend Tests:
Passed — 319 relevant catalog and F01–F06 tests, 2,356 assertions, using isolated SQLite. Includes 21 new F07 tests (137 assertions). Tests mock payment/email interactions and do not reset the development MySQL database.

Frontend Tests:
Passed — 16 new rendered-control/browser cases and 100 existing commerce browser cases. This repository uses Playwright for these frontend behavior tests; no separate unit-test runner was added.

Browser Tests:
Passed — 116 total cases across desktop Chrome and Pixel 7 emulation, retries disabled: 16 F07 + 100 F01–F06. Both browser runs passed on their first execution. Browser API responses are mocked; Laravel tests separately exercise actual request validation, database queries and responses.

Type Check:
PASS — Docker `vue-tsc -b`, also included in the production build.

Production Build:
PASS — Docker `npm run build` (`vue-tsc -b && vite build`).

F01 Regression:
PASS — shop coupon ownership/isolation backend tests and shop-coupons browser suite.

F02 Regression:
PASS — cart recovery/merge backend tests and cart-recovery browser suite.

F03 Regression:
PASS — checkout quote/payment totals backend tests and checkout-totals browser suite.

F04 Regression:
PASS — COD lifecycle/collection backend tests and cod-collection browser suite.

F05 Regression:
PASS — guest checkout/auth/address/payment backend coverage and guest-checkout browser suite.

F06 Regression:
PASS — CatalogFiltersTest and the desktop/mobile catalog-filters browser suite, plus new color/rating/price composition coverage.

Remaining Risks:
No known unresolved F07 defect. Browser coverage uses emulation and mocked APIs; physical-device and integrated staging acceptance remain separate. Existing bookmarks containing an unsupported slug such as `colors=light-blue` continue to return no match by the actual-value contract; F07 does not silently translate them.

Related Follow-Up:
F08 — Async Cart Mutations & Buy Now Errors. Not started; awaiting the requested F07 review.

Before/after example:

```text
BEFORE
Color: Light Blue
Mobile sent: light-blue
Backend expected: Light Blue
Result: wrong / empty filtering; invalid swatch CSS

AFTER
Color: Light Blue
Desktop sends: Light Blue
Mobile sends: Light Blue
Swatch: API-provided #ADD8E6
Result: Product A visible, Product B (Black) hidden on both layouts
Multiple selection: Light Blue + Black displays Product A and Product B
```

Validation commands (all runtimes inside Docker):

```sh
docker compose exec -T app php artisan test --compact --filter 'CatalogTest|CatalogFiltersTest|CatalogColorsTest|ShopTest|CategoryTest|ShopCouponOwnershipTest|CartRecoveryTest|CartTest|Checkout|CouponTest|CodCollectionTest|BakongPaymentTest|AuthTest|NotificationTest|MarketplaceFlowTest'
docker compose run --rm browser-tests npx playwright test tests/e2e/catalog-colors.spec.ts --output=/tmp/f07-playwright-results --reporter=line --retries=0
docker compose run --rm browser-tests npx playwright test tests/e2e/catalog-filters.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts tests/e2e/guest-checkout.spec.ts --reporter=line --retries=0
docker compose exec -T frontend npm run build
docker compose exec -T app vendor/bin/pint --test app/Http/Requests/CatalogFilterRequest.php tests/Feature/Api/CatalogColorsTest.php
```

PHP formatting and `git diff --check` passed. API workers were reloaded to use the validated request change. No production deployment or commit is included. Stop after F07.
