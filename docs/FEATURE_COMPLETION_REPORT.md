# E-KHMER Feature Completion Report

Last updated: 7 October 2026

This is the working completion record for the marketplace. A feature is marked **Complete** only when its implementation is present and its relevant automated checks pass. **In progress** means code exists but a workflow, verification, or UI surface remains incomplete. **Blocked** means completion requires a product or provider decision rather than an implementation assumption.

## 1. Completed customer and administration features

| Area | Status | Evidence |
| --- | --- | --- |
| Account access | Complete | Registration, Sanctum authentication, password reset, email verification, profile, password, avatar, addresses, and customer notifications are implemented. |
| Storefront catalog | Complete | Search, pagination, categories, brands, facets, sorting, product gallery/zoom, variants, availability, and reviews are implemented. |
| Cart and checkout foundation | Complete | Guest and authenticated carts, coupon validation, order snapshots, stock reservation, reservation expiry, order receipts, and order tracking are implemented. |
| Receipt PDF and printing | Complete | The receipt uses a print-safe blue E-KHMER invoice layout: large readable type, dynamic shop identity/logo and contact details, PDF-safe visual markers, balanced payment/shipping/invoice panels, correctly proportioned image-led line items, and a tax/total breakdown. The duplicate barcode/order-code block was removed because the invoice number already identifies the order. A single-shop receipt uses that shop's stored branding; a multi-shop parent receipt safely uses marketplace branding. Receipt buttons now open the browser print dialog rather than downloading a file. |
| Inventory core | Complete | Atomic reservations, stock deduction/release/restock, low-stock notifications, adjustments, and an admin inventory ledger are implemented. |
| Customer engagement | Complete | Wishlist, delivered-purchase review verification, review moderation, and account notifications are implemented. |
| Admin dashboard overview | Complete | Period KPIs, all requested date filters, revenue/order/category/payment/status charts, recent orders/customers/reviews/payments, current stock alerts, shop approvals, and catalog/shop totals are connected to the Laravel API. Dashboard and linked admin regressions pass. See the detailed audit below. |
| Other platform administration | In progress | Catalog, category, brand, order, payment, shipment, coupon, review, customer, report, and settings screens exist. Their presence does not certify every action: for example, the order bulk "Print labels" action downloads the general orders PDF rather than shipping labels for the selected orders. Operational settings and the previously recorded project backlog remain separate work. |
| Operational settings | In progress | Maintenance mode now returns a storefront 503 while preserving auth/admin access; order and low-stock email toggles now select notification channels; changing the low-stock threshold updates existing and new inventory records. Container regression verification is pending. |
| Multi-shop foundation | In progress | Shops, memberships, ownership policies, shop product ownership, per-shop inventory, and parent-order splitting are implemented. Seller fulfilment and per-shop shipping remain incomplete. |

## 2. Repairs completed in this work session

| Item | Status | Change |
| --- | --- | --- |
| Seller order build failure | Complete | Date formatting now accepts a nullable API date and renders an em dash when no order date is available. |
| Bulk notification read endpoint | Complete | The no-parameter `all/read` route now performs the intended bulk update instead of throwing a 500. |
| Shop-owned checkout response | Complete | Checkout now loads `shopOrders.items` correctly instead of requesting a non-existent `Shop::items` relation. |
| Role-renaming test drift | Complete | Seeder tests now assert the migrated `super_admin` role and the three demo accounts created by the current seeder. |
| Multiple shop applications | Complete | The test contract now matches the deliberate multi-shop application behaviour. |
| Admin dashboard missing panels | Complete | Added low/out-of-stock alerts, recent customer/review/payment panels, category/payment charts, catalog counts, daily/weekly/monthly revenue, and current order status totals. |
| Dashboard date and revenue accuracy | Complete | Added today, yesterday and custom ranges; validated date order and a maximum of 366 days; made all period analytics use the selected dates. Paid-sales calculations exclude unpaid, cancelled, refunded, and soft-deleted orders. Category order counts now count distinct orders rather than line items. |
| Dashboard interactions and feedback | Complete | Added request cancellation to prevent stale range responses, refresh timestamps, optional one-minute polling that pauses while the tab is hidden, explicit API/shop-action errors, success messages, and empty states. Stock links initialize the inventory page with the appropriate search and stock filters. |
| Mobile admin layout | Complete | Replaced the always-visible sidebar on smaller screens with a drawer. It opens from the menu button and closes from the backdrop, Escape, or navigation. Hidden navigation is inert; desktop collapse remains available. Browser checks confirm a 358px dashboard content area within a 390px viewport. |
| Docker frontend refresh | Complete | The running Vite server retained the previous dashboard because Windows bind-mount edits were not detected. Enabled polling through `VITE_USE_POLLING` for Docker development and recreated the frontend service. Browser verification then loaded the new dashboard without a manual code rebuild. |
| Seller route context | In progress | The seller middleware verifies the manager, route shop, and route resource before controllers use its trusted context. Product and inventory seller routes now use that scope; create/update cannot move products across shops. Container regression verification is pending. |
| Seller inventory workspace | In progress | Sellers can now search their shop inventory, set on-hand stock safely above reservations, and inspect its transaction ledger. The API now exposes restock ledger filtering as well. Frontend build verification is pending. |
| Operational settings | In progress | Maintenance mode is enforced for storefront API calls; email preferences control order and low-stock notification channels; updating the global low-stock threshold synchronizes current stock and becomes the default for new records. Tests were added but need a final container run. |

## 3. Remaining work, in delivery order

1. **Finish seller operations** — verify manager access and the new inventory workspace; add product media/variant management, order detail/status actions, shipment tracking, shop shipping methods, coupons, reports, and team management.
2. **Finish multi-shop fulfilment** — add a per-shop shipment and order-status model, including customer-facing tracking for each seller allocation. This needs a product decision on whether checkout charges one platform shipment or one shipment per shop.
3. **Add privileged-action audit logs** — record actor, target, action, before/after state, timestamp, and reason for shop, member, payment/refund, and setting changes.
4. **Add frontend automated tests and linting** — introduce a Vue/Vitest test suite and quality scripts for route authorization, forms, API errors, checkout, and seller operations.
5. **Integrate a real payment provider** — card/bank/gateway checkout is intentionally sandbox-only. This is blocked until a provider, country/currency support, webhook policy, and credentials are chosen. Production currently rejects self-confirmed online payments safely.
6. **JWT refresh-token rotation (only if required)** — the project intentionally uses Sanctum tokens. Replacing it with JWT requires confirmation because it changes the auth architecture and client session model.

## 4. Latest verification

| Check | Result |
| --- | --- |
| `docker compose exec -T frontend npm run build` | Passed after the nullable-date repair. |
| Full Laravel suite before repairs | 283 passed, 8 failed. |
| Targeted Laravel suite after initial repairs | 68 passed, 2 seller authorization checks still failing; the other six original failures are resolved. |
| Latest static diff check | Passed. |
| Dashboard and linked admin regressions | Passed: 67 tests, 386 assertions across `AdminDashboardTest`, `AdminOpsTest`, `AdminTest`, and `AdminInventoryTest` in the Laravel Docker container. |
| Dashboard frontend production build | Passed: Vue TypeScript checks and Vite production build. |
| Running dashboard API / MySQL | Passed: authenticated demo-admin request returned the correct seven-day series, five recent orders with customer snapshot data, and five current stock alerts. The verification session was logged out afterwards. |
| Running dashboard browser checks | Passed: desktop and 390px mobile rendering, five live charts, all 14 dashboard panels, custom-range empty states, sidebar open/close, and low-stock navigation with the inventory filter applied. No runtime exceptions or dashboard load alerts; no page-level horizontal overflow. Temporary browser sessions were closed and logged out. |
| Receipt branding and PDF checks | Passed: 3 tests and 10 assertions on the final print-safe blue invoice layout (single-shop dynamic logo, multi-shop fallback, and admin receipt generation). |
| Vue production build after print-preview update | Passed. |
| Latest seller/settings implementation | Final Docker test/build rerun pending after the route-context, inventory workspace, and operational-settings changes. |

The next delivery step is the seller/settings regression run, then seller product media/variant controls. Multi-shop shipping is intentionally held until its checkout and fulfilment policy is decided.

## 5. Admin dashboard audit — 7 October 2026

Scope: `/admin/dashboard`, its overview API, and dashboard links into inventory. This audit does not mark every other admin page or the whole marketplace complete.

| Dashboard feature | Previously missing or incorrect | Result |
| --- | --- | --- |
| Revenue, orders, customers, average order value | Lifetime values were shown beside month-only comparisons; selected-period totals were missing. | Complete: period totals and comparisons against the preceding equal-length period, plus explicit lifetime totals. An empty comparison baseline is shown as unavailable. |
| Today/week/month revenue | Returned by the API but not displayed. | Complete: dedicated revenue summary. |
| Products/categories/brands and shop counts | Catalog counts were hidden; failed supporting requests could silently appear as zero shops. | Complete: visible summary with separate loading/error handling for shops. |
| Date controls | Today/yesterday/custom controls were missing; invalid custom dates could reach parsing without validation. | Complete: all seven options, server/client validation, actual date labels, and bounded daily series. |
| Revenue/order/category/status/payment charts | Category/payment charts were unused; some calculations ignored the dates or included unpaid sales. | Complete: consistent date filtering and paid-sales rules, currency tooltips and integer count axes. |
| Top products | Could count unpaid sales. | Complete: paid item sales only, with explicit pre-discount/shipping/tax labels. |
| Recent orders | Separate request ignored the selected dates and did not supply the customer data the UI expected. | Complete: bounded five-order list in the overview response, customer snapshots, safe user details, currency, and order detail links. |
| Recent customers/reviews/payments | API data had no dashboard UI. | Complete: all three panels, date filtering, detail links and empty states. Payment dates are filtered by their order's placement date and labelled accordingly. |
| Low/out-of-stock alerts | API data had no dashboard UI. | Complete: current available stock (on hand minus reservations), active product/variant alerts, thresholds, and inventory search/filter links. |
| Shop approval/rejection | Failures had no useful feedback. | Complete: action locking, success/error feedback, rejection reasons, and refreshed shop totals. |
| Refresh and resilience | Supporting errors were swallowed and overlapping range responses could overwrite newer data. | Complete: cancel superseded requests, retain the last successful result with a warning, manual refresh and optional one-minute polling. |
| Layout and language | Compressed overview omitted operational information and used hardcoded labels; the fixed sidebar squeezed mobile content. | Complete: responsive cards/panels, mobile sidebar drawer, scrollable order table, existing light/dark theme and English/Khmer labels. |
| Docker development updates | Vite kept serving the earlier Vue component after source edits on the Windows bind mount. | Complete: Docker-only file polling enabled and the frontend service recreated; confirmed the new component is served in the browser. |

Revenue uses the order's placement date and current paid status; it is not an accounting report grouped by payment settlement date. Category and top-product amounts are item subtotals before order-level discounts, shipping and tax. Stock, catalog, and shop approval summaries intentionally represent current operations regardless of the selected historical period.
