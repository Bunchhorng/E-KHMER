# E-KHMER Feature Completion Report

Last updated: 7 October 2026

This is the working completion record for the marketplace. A feature is marked **Complete** only when its implementation is present and its relevant automated checks pass. **In progress** means code exists but a workflow, verification, or UI surface remains incomplete. **Blocked** means completion requires a product or provider decision rather than an implementation assumption.

## 1. Completed customer and administration features

| Area | Status | Evidence |
| --- | --- | --- |
| Account access | Complete | Registration, Sanctum authentication, password reset, email verification, profile, password, avatar, addresses, and customer notifications are implemented. |
| Storefront catalog | Complete | Search, pagination, categories, brands, facets, sorting, product gallery/zoom, variants, availability, and reviews are implemented. |
| Cart and checkout foundation | Complete | Guest and authenticated carts, coupon validation, order snapshots, stock reservation, reservation expiry, order receipts, and order tracking are implemented. |
| Receipt PDF and printing | Complete | The receipt uses the blue E-KHMER invoice layout: large dynamic shop identity/logo and contact details, payment/shipping/invoice panels, image-led line items, tax/total breakdown, and balanced thank-you footer. The duplicate barcode/order-code block was removed because the invoice number already identifies the order. A single-shop receipt uses that shop's stored branding; a multi-shop parent receipt safely uses marketplace branding. Receipt buttons now open the browser print dialog rather than downloading a file. |
| Inventory core | Complete | Atomic reservations, stock deduction/release/restock, low-stock notifications, adjustments, and an admin inventory ledger are implemented. |
| Customer engagement | Complete | Wishlist, delivered-purchase review verification, review moderation, and account notifications are implemented. |
| Platform administration | Complete | Dashboard metrics/charts, catalog management, category tree, brands, orders, payments, shipments, coupons, reviews, customers, reports, and settings screens are implemented. |
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
| Receipt branding and PDF checks | Passed: the blue invoice design was verified with 3 tests/10 assertions (single-shop dynamic logo, multi-shop fallback, admin receipt generation); the final simplified-footer PDF regression also passed (1 test, 4 assertions). |
| Vue production build after print-preview update | Passed. |
| Latest seller/settings implementation | Final Docker test/build rerun pending after the route-context, inventory workspace, and operational-settings changes. |

The next delivery step is the seller/settings regression run, then seller product media/variant controls. Multi-shop shipping is intentionally held until its checkout and fulfilment policy is decided.
