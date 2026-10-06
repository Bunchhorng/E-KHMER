# E-KHMER Marketplace Upgrade

## Executive summary

The existing application was already partially multi-shop: shops, memberships, product ownership, public shop pages, active-shop catalog filtering, and shop-aware inventory/order-item snapshots existed. This pass adds the missing parent-order to shop-order foundation without replacing existing customer orders or payments.

## Architecture changes

- `orders` remains the marketplace parent order and the single customer payment record.
- New `shop_orders` represent a seller's share of a parent order.
- New checkout rows are split by `order_items.shop_id`; every order item receives `shop_order_id`.
- Discount, tax, and shipping are allocated proportionally by seller subtotal, with rounding remainder assigned to the final shop order.

## Database changes

- Added `shop_orders` with parent-order, shop, status, totals, and unique seller order number.
- Added nullable `order_items.shop_order_id` to preserve historical data safely.

## Security and integrity

- Unique `(order_id, shop_id)` prevents duplicate shop orders.
- Legacy/platform lines without a shop remain on the parent order; seller allocation is derived from persisted product ownership rather than client input.
- Existing shop/staff policy architecture remains the authority for future seller-facing shop-order endpoints.

## Status

| Area | Status |
| --- | --- |
| Shops, active visibility, public shop pages | Present before this pass |
| Parent order → shop order split | Implemented for new checkout |
| Historical backfill | Needs audited production-data command |
| Seller shop-order APIs/dashboard | Needs work |
| Per-shop shipments/status aggregation | Needs work |
| Customer grouped order UI | Needs work |
| Automated tests / container verification | Pending Docker execution |

## Next phase

Add shop-order query and transition endpoints scoped through shop memberships, migrate shipment ownership to shop orders, render grouped seller fulfilment in customer order screens, then complete cross-shop authorization and checkout integration tests.
