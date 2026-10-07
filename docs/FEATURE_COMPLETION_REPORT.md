# E-KHMER Feature Completion Report

Last updated: 8 October 2026

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
| Operational settings | Complete | Maintenance-mode restrictions, order/stock notification preferences, and inventory threshold updates passed the full container regression suite. |
| Multi-shop marketplace flow | Complete | Super Admin platform access, active owner/manager memberships, isolated product/inventory/order workspaces, one shared customer storefront, mixed-shop checkout, independent fulfilment/shipment histories, and customer delivery tracking are implemented and verified. Checkout keeps its existing single shipping charge and payment; each shop has its own parcel. Other seller modules and production payment integration remain separate work. |

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
| Seller route context | Complete | Explicit seller controller adapters now bind Shop/Product/Inventory parameters correctly before the validated management context reaches existing controllers. The previously failing create/list routes and nested edit/adjust/ledger/delete workflows pass. Body shop IDs cannot move a seller's product across shops. |
| Seller inventory workspace | Complete | Own-shop search, availability, reservation-safe adjustments and transaction history are verified through the scoped API and production frontend build. |
| Operational settings | Complete | Maintenance restrictions, notification channel preferences and global/new inventory threshold behaviour passed the full regression suite. |
| Strict platform role | Complete | Only `super_admin` receives global platform access. Legacy `admin` values grant no global privileges; the existing role migration converts designated platform administrators to Super Admin. Shop owners/managers use membership permissions. |
| Independent shop fulfilment | Complete | Added seller order detail and validated processing/dispatch/delivery/tracking actions, separate shipment and history records, Super Admin allocation controls, overall progress synchronization, and customer tracking cards. |
| Marketplace lifecycle safeguards | Complete | Customer confirmation closes reservations and confirms allocations together; cancellation/expiry closes all allocations; dispatched allocations block whole-order cancellation; returned parcels block global delivery; unpaid online orders cannot be fulfilled through a platform status override. |
| Owner session and navigation | Complete | Login/me/profile responses include active managed-shop identities. Owners land in Seller Center, the account menu exposes their workspace, and saved sessions refresh against the API before route permissions are checked. |
| Financial allocation accuracy | Complete | Three-or-more-shop discounts, tax and shipping now use original totals when distributing shares, with deterministic rounding. Parent checkout prices remain unchanged. |
| Existing-order upgrade | Complete | Added shipment/history links and imported allocation status records while preserving original platform tracking. A forward correction clears inherited dates on pending imported parcels; actual new dispatch/delivery actions stamp their own dates. Original platform dates remain available. |
| Flow presentation | Complete | Added shop branding, progress cards, accessible histories, owner/customer controls, English/Khmer labels, compact mobile navigation and missing-image fallbacks. The earlier branch-only architecture plan was replaced with the approved independent-shop flow. |

## 3. Remaining work, in delivery order

1. **Extend seller operations** — add the remaining media/variant UI, shop shipping configuration, coupons, reports and team management. Core ownership, products, inventory, order detail/status and shipment tracking are now complete.
2. **Optional separate shop shipping quotes** — independent parcels are complete using the current single checkout shipping charge. Charging separately per shop is a distinct pricing change requiring delivery rules and fee policy.
3. **Add privileged-action audit logs** — record actor, target, action, before/after state, timestamp, and reason for shop, member, payment/refund, and setting changes.
4. **Add frontend automated tests and linting** — introduce a Vue/Vitest test suite and quality scripts for route authorization, forms, API errors, checkout, and seller operations.
5. **Integrate a real payment provider** — card/bank/gateway checkout is intentionally sandbox-only. This is blocked until a provider, country/currency support, webhook policy, and credentials are chosen. Production currently rejects self-confirmed online payments safely.
6. **JWT refresh-token rotation (only if required)** — the project intentionally uses Sanctum tokens. Replacing it with JWT requires confirmation because it changes the auth architecture and client session model.

## 4. Latest verification

| Check | Result |
| --- | --- |
| `docker compose exec -T frontend npm run build` | Passed after the nullable-date repair. |
| Historical full Laravel suite before repairs | 283 passed, 8 failed; retained as the earlier baseline. |
| Historical targeted suite after initial repairs | 68 passed, 2 seller route checks failed at that stage. Those route binding failures are resolved in the marketplace implementation. |
| Latest static diff check | Passed. |
| Dashboard and linked admin regressions | Passed: 67 tests, 386 assertions across `AdminDashboardTest`, `AdminOpsTest`, `AdminTest`, and `AdminInventoryTest` in the Laravel Docker container. |
| Dashboard frontend production build | Passed: Vue TypeScript checks and Vite production build. |
| Running dashboard API / MySQL | Passed: authenticated demo-admin request returned the correct seven-day series, five recent orders with customer snapshot data, and five current stock alerts. The verification session was logged out afterwards. |
| Running dashboard browser checks | Passed: desktop and 390px mobile rendering, five live charts, all 14 dashboard panels, custom-range empty states, sidebar open/close, and low-stock navigation with the inventory filter applied. No runtime exceptions or dashboard load alerts; no page-level horizontal overflow. Temporary browser sessions were closed and logged out. |
| Receipt branding and PDF checks | Passed: 3 tests and 10 assertions on the final print-safe blue invoice layout (single-shop dynamic logo, multi-shop fallback, and admin receipt generation). |
| Vue production build after print-preview update | Passed. |
| Full Laravel regression after marketplace implementation | Passed: 328 tests, 1,472 assertions. |
| Final marketplace/shop/checkout/admin regressions | Passed: 102 tests, 529 assertions after adding strict dispatch-cancellation and shop-name/code safeguards. |
| Confirmation/returned-parcel regression checks | Passed: 60 tests, 417 assertions across marketplace, checkout and admin operations. |
| Final marketplace-specific checks | Passed: 21 tests, 169 assertions, including the imported-date correction and upgrade compatibility. |
| Final Vue production build | Passed: TypeScript and Vite build after owner/customer screens, session refresh, compact navigation and image fallbacks. |
| Marketplace migrations on running MySQL | Applied the fulfilment migration, existing administrator-role rename, and forward import-date correction successfully. Original orders, money totals and platform tracking were preserved. |
| Final marketplace browser/API checks | Passed against existing demo data: scoped seller detail with carrier/tracking controls, restored managed-shop session metadata, customer delivery/history cards, safe payment output, and desktop/390px mobile layouts. No load alerts, runtime exceptions or page-level horizontal overflow. Orders/payments were not changed by these checks; browser sessions were closed and logged out. |

The approved core marketplace flow is implemented. The next seller delivery step is media/variant controls and the remaining shop administration modules. Per-shop shipping price changes and real provider/settlement work remain distinct decisions.

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

## 6. Approved marketplace flow

The implementation follows: Super Admin controls the platform; Owner A/Owner B/Owner C manage their assigned shops; published products feed one E-KHMER storefront; customers use a shared cart and checkout.

| Flow | Implemented behaviour |
| --- | --- |
| Super Admin → shops | Global dashboard, approvals, membership management, catalog/orders and individual allocation controls. Global access is restricted to Super Admin. |
| Owner → own shop | Active memberships authorize scoped workspaces; request-body shop IDs cannot change the trusted scope. Unmanaged contexts are refused; cross-shop resources are not disclosed. |
| Shops → website | The shared catalog exposes published products from active shops. Product details identify their seller. |
| Customer → checkout | A parent order/payment covers the cart; immutable items are grouped into shop allocations with correctly apportioned totals. |
| Each shop → delivery | Independent processing, carrier/tracking metadata, dispatch/delivery dates, status notes and history. One shop's action cannot advance another's shipment. |
| Deliveries → customer | Customer account detail and tracking pages show each allocation's items, status, carrier, tracking and timeline; overall progress follows the least advanced allocation. |

The existing one-time shipping charge is retained and allocated across parcels. Owner actions do not capture online payments or issue refunds. Production gateway/settlement, partial refunds and additional seller administration modules are not certified by this core-flow completion.

See `multi-shop-plan.md` for the current architecture, APIs, upgrade strategy and lifecycle rules.

## 7. Seller workspace UX/UI — 8 October 2026

Scope: the shop-owner workspace at `/seller`, its existing product/stock/order/application pages, and the data needed for its overview. This does not certify the remaining advanced seller modules.

Status: **Complete for this scope**. The existing design system and project testing guidance were retained; no new UI framework or browser dependency was introduced.

| Area | Improvement |
| --- | --- |
| Workspace navigation | Dedicated seller layout replaces the shopping promotion, category navigation and public footer. Four clear sections: Overview, Products, Stock and Orders. Shop selector includes current branding and remembers a selection separately for each signed-in user. Existing role permissions and URLs remain intact. |
| Overview | Real, shop-scoped counts for orders to prepare/dispatch, live low-stock options and live products; actionable next-step links, recent orders, stock alerts and a getting-started checklist. Pending/closed parent orders and returned parcels are not counted as actionable dispatch work. |
| Products | Image-led responsive rows, explicit search/empty-state recovery, Live/Hidden labels, edit/show/hide actions, and a deletion confirmation explaining historical-order preservation. |
| Product form | Grouped details, price/stock and visibility sections; clearer labels and field errors; loading/retry handling and unsaved-change protection. Editing a product does not reset inventory. Simple default-option prices and publication are now synchronized; multi-option prices are preserved. |
| Stock | Available, held and total quantities are clearly distinguished. Total-count updates explain that the entered value is not an increment; preview subtracts current holds. Stock-history dialog includes pagination, signed movements and error recovery. |
| Orders | Preparation/dispatch/delivery filters, order-code/customer search, task-specific action labels, returned-parcel indicator and next-step guidance on the detail page. Search recognizes the shop allocation code as well as the parent order number. |
| Shop application | Clear details → review → selling steps, review feedback, additional-shop applications and approved-workspace links. |
| Accessibility & presentation | Consistent typography, spacing and Lucide icons; responsive layouts, dark theme, English/Khmer workspace labels, keyboard focus indicators, modal focus containment/restoration and mobile navigation focus management. |

Verification: `SellerWorkspaceTest`, `SellerShopScopeTest` and `MarketplaceFlowTest` passed together in Docker: **33 tests, 232 assertions**. PHP formatting, production TypeScript/Vite build and static diff checks passed. The final running-browser review passed **40 checks**, covering desktop and 390px layouts, authorized shop switching, product search recovery, product create/edit screens, stock preview/history and focus restoration, order task filters/detail guidance, application steps, mobile navigation, dark theme and Khmer labels. No load alerts, runtime exceptions, missing translation keys or page-level mobile overflow were found. Browser verification did not change products, stock or orders; temporary browser sessions were closed and logged out. Legacy stock-in history now has a readable label and positive quantity sign.

Remaining seller work is unchanged: advanced media/variant controls, shop-specific shipping configuration, coupons, reports and team management. Payment providers, settlement and partial refunds remain separate workflows.
