# E-KHMER Multi-Shop Marketplace Architecture

Updated: 7 October 2026. This replaces the earlier single-brand branch plan and follows the user-approved flow: Super Admin → independently managed shops → one E-KHMER website → customers.

## 1. Ownership and access

- Super Admin controls the platform dashboard, shop approvals, owner/manager assignments, platform orders and settings.
- Shop owners and managers work through active `shop_users` memberships. Their base user role can remain `customer`, allowing the same person to shop as well as manage their own business.
- The legacy `admin` role does not grant platform permissions. The existing role migration converts previously designated platform administrators to `super_admin`.
- Each seller request binds the selected Shop and verifies membership before binding/accessing that shop's products, inventory or order allocation. Request body/query `shop_id` values cannot select a different shop.
- A person can manage several assigned shops. Multiple memberships do not grant access to shops they do not manage.
- Authentication responses expose only the user's active managed-shop identities so the frontend can show Seller Center and direct owners there after login. The backend remains the authority for permissions.

## 2. Shared storefront and catalog

Customers use one website and one cart. Published products from active shops appear in the shared catalog; pending, suspended and closed shops are not publicly purchasable. Product details identify the selling shop.

Each product and variant belongs to its shop. Inventory remains per variant; `shop_id` is recorded on stock and ledger rows. Variant SKU uniqueness is scoped per shop. Categories and brands remain shared platform taxonomy.

Existing unassigned platform products remain supported for compatibility. They use a platform shipment when included in a mixed order.

## 3. Checkout and fulfilment

Checkout creates one customer-facing `orders` record, one payment and one `shop_orders` allocation per selling shop. Item titles, prices, quantities and shop ownership are checkout snapshots. Shop totals contain only that seller's items and its allocated discount, tax and shipping shares.

The current checkout charges its selected platform shipping fee once. That fee is apportioned across shop allocations; this update does not introduce additional fees per shop. Every shop nevertheless receives its own shipment, carrier details, tracking number, delivery dates and status history.

The lifecycle is:

1. Checkout reserves stock and creates pending parent/shop orders.
2. Checkout confirmation deducts reserved stock once and confirms the parent and its allocations. Cash on delivery remains unpaid until payment is collected; shop processing cannot self-confirm an unpaid online transaction.
3. Each owner opens their own order detail and moves it from confirmed to processing, shipped and delivered. A carrier and tracking number are required for seller dispatch.
4. The carrier shipment can advance through in-transit, delivered or returned states. Returned shipments require a platform/support follow-up; sellers cannot issue payment refunds.
5. Customers see each shop's items, progress, carrier, tracking and history separately, alongside overall order progress.

Overall progress follows the least advanced allocation:

| Shop A | Shop B | Overall order |
| --- | --- | --- |
| Shipped | Confirmed | Confirmed |
| Delivered | Processing | Processing |
| Delivered | Shipped | Shipped |
| Delivered | Delivered | Delivered |

A platform shipment for legacy/unassigned items must also reach the required shipping/delivery stage. Advancing one shop never advances another shop.

Super Admin can process an individual allocation or advance the platform order. Global transitions synchronize unfinished shop allocations and preserve allocations that are already further ahead. Whole-order cancellation is disallowed once any shop or platform shipment has dispatched, even if the aggregate parent status still says confirmed or processing. Valid pre-dispatch cancellation releases/restores stock once and closes all allocations.

## 4. Database and upgrade

Existing tables used by this flow: `users`, `shops`, `shop_users`, `products`, `product_variants`, `inventories`, `inventory_transactions`, `orders`, `order_items`, `shop_orders`, `payments`, `shipments` and `tracking_events`.

The new migration `2026_10_07_000001_add_shop_fulfilment_tracking.php` adds:

- Nullable, unique `shipments.shop_order_id`: one shipment for each allocation, with legacy platform shipments retained.
- Nullable `tracking_events.shop_order_id` and a history lookup index: shop history is separate from the parent timeline.
- A text description column for lifecycle notes.

The upgrade aligns existing pending allocations with their parent status and creates corresponding shipment/history records. Original carrier tracking is retained on the original platform shipment; it is not copied into several invented parcels. Existing orders, payment amounts, financial allocations, addresses and inventory are retained. Imported history is explicitly labelled as an import.

The forward migration `2026_10_07_000002_clear_imported_pending_shipment_dates.php` clears copied timestamps on imported parcels that are still pending. Original platform shipment dates remain intact; actual seller dispatch and delivery actions record their own timestamps.

Schema rollback removes the new relationships; it is not a restoration of pre-upgrade fulfilment history. Prefer a forward migration for future adjustments.

## 5. APIs and screens

| Workspace | Paths and actions |
| --- | --- |
| Super Admin | `/admin/dashboard`, `/admin/shops`, `/admin/shop-owners`, `/admin/shop-admins`, `/admin/orders/:id`; order details include independent shop controls. |
| Owner/manager | `/seller`, `/seller/shops/:id`, product/inventory/order workspaces and `/seller/shops/:id/orders/:orderId`. |
| Customer | Shared `/shop` catalog, product details, checkout, `/account/orders/:orderNumber` and `/order/tracking/:orderId`. |

Seller order APIs:

- `GET /api/seller/shops/{shop}/orders`
- `GET /api/seller/shops/{shop}/orders/{shopOrder}`
- `PUT /api/seller/shops/{shop}/orders/{shopOrder}/transition`
- `PUT /api/seller/shops/{shop}/orders/{shopOrder}/shipment`

Super Admin allocation APIs:

- `PUT /api/admin/orders/{order}/shop-orders/{shopOrder}/transition`
- `PUT /api/admin/orders/{order}/shop-orders/{shopOrder}/shipment`

Controllers validate inputs and return API Resources. Fulfilment writes lock the parent order before the allocation/shipment so updates to the same order are serialized. Cross-shop resources return 404 under a valid own-shop context; an unmanaged shop context returns 403.

## 6. Verification and remaining modules

`MarketplaceFlowTest` covers shop isolation, independent lifecycle progression, tracking requirements, stock restoration, cancellation after partial dispatch, proportional three-shop financial allocations, Super Admin access, owner identity, product/inventory routes, and upgrade compatibility. The existing shop, authentication, checkout and admin suites remain regression coverage.

See `FEATURE_COMPLETION_REPORT.md` for actual test/build/browser results and remaining project modules. A real payment provider, settlement/payout accounting, optional separate shipping quotes, seller media/variant UI, shop coupons/reports/team administration, and general privileged-action audit logs are separate work; their absence does not turn independent shop shipment tracking into a branch-only flow.
