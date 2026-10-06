# Marketplace migration plan

## Current architecture

The application already has `shops`, `shop_users`, `products.shop_id`, shop-aware inventory and order-item snapshots, active-shop catalog visibility, staff policies, and public shop endpoints. `orders` is still the single customer order and `shipments` are attached directly to it.

## Target and compatibility decision

`orders` remains the customer-facing parent and payment record. New `shop_orders` rows are created per selling shop at checkout; `order_items.shop_order_id` assigns each snapshot item to exactly one shop order. Existing order IDs, URLs, payments and order history remain valid.

## Safe migration sequence

1. Create `shop_orders` and add a nullable `shop_order_id` to `order_items`.
2. Preserve legacy order items with a nullable `shop_order_id`; run the separately reviewed backfill only after production data has been audited. This prevents guessing a seller for historical rows with missing ownership.
3. New checkout writes parent order, item snapshots, then shop orders in one database transaction.
4. Move shipments to shop orders only in a later, separately tested migration; current parent-order shipment behaviour stays intact.

## Integrity and security

- Unique `(order_id, shop_id)` prevents duplicate seller orders.
- Shop-order number is unique and derived from the parent order number.
- Each `shop_order_id` belongs to the same parent order as its item.
- Shop staff queries must be scoped through `shop_users`; super admins retain marketplace visibility.
- No destructive schema change or historical order deletion is included.

## Rollback

The migration removes only the newly-added foreign key/column/table. Rollback must not be used after production shop-order data has been created without a database backup.

## Verification

Run fresh migration, legacy-data upgrade, a two-shop checkout, shop-isolation authorization probes, and customer order rendering before marking the migration complete.
