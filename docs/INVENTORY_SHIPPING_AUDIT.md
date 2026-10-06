# E-KHMER Inventory & Shipping Audit

## Executive Summary

Inventory is variant-based and `inventories` is the sole stock authority.
Checkout reserves stock, confirmation deducts it, and cancellation/refund
releases or restores it through the inventory ledger. This audit completed the
logistics safety layer by centralizing shipment transitions, synchronizing valid
shipment milestones with orders, and preserving an accurate stock audit trail.

## Inventory

### Status: PASS (container verification pending)

- Inventory is keyed uniquely to a product variant; products/variants do not
  carry a competing stock value.
- Reserve, release, deduct, restock, and adjustment operations use transactions
  and row locks.
- Checkout rechecks availability and reservations prevent overselling.
- Deduction now requires a matching reservation, preventing a caller from
  recording an unreserved sale or inflating sold count.
- Release ledger records now use the actual released amount, not an over-large
  requested amount.

## Inventory Transactions

### Status: PASS (container verification pending)

- Every inventory mutation creates an immutable ledger row with type, delta,
  resulting balance, source reference, note, timestamp, and optional actor.
- Admin stock edits use the adjustment service and record the administrator.
- Low-stock alerts are de-duplicated by `low_stock_notified_at` and reset once
  the item recovers above the threshold.

## Shipping Methods

### Status: PASS (container verification pending)

- Checkout loads active shipping methods and calculates the persisted shipping
  price server-side.
- Admin changes invalidate the public shipping-method cache.
- Validation now enforces a unique method code and prevents a maximum delivery
  estimate below its minimum.

## Shipments and Shipment Status

### Status: PASS (container verification pending)

- Shipment updates now go through `ShipmentService`, which locks the shipment
  and enforces `pending → shipped → in_transit → delivered/returned`.
- Backwards or terminal-state transitions are rejected.
- Cancelled/refunded orders cannot be shipped.
- Dispatch requires a processing/shipped order and synchronizes a processing
  order to `shipped`; delivery synchronizes a shipped order to `delivered`.
- Shipment address snapshots remain independent of later saved-address edits.

## Notifications

### Status: PASS (container verification pending)

- Order lifecycle transitions send database notifications to the owning
  customer. Shipment dispatch/delivery use those same order transitions, so no
  parallel notification mechanism was introduced.
- Low-stock notifications are sent once per threshold crossing.
- Notification list, unread count, read/mark-all-read, and owner-scoped delete
  operations are available for account/admin flows.

## Security and Data Integrity Improvements

- Inventory writes remain admin/service-only; customers cannot send stock or
  shipping-price fields that affect persistence.
- Shipment status cannot be manipulated through arbitrary status updates.
- Notification delete/read operations resolve through the authenticated user's
  own notification relation.
- Shipment status and inventory writes occur within database transactions.

## Automated Tests

Updated shipping regression coverage now verifies:

- shipment progression through shipped then delivered;
- order processing/shipment synchronization prerequisites;
- rejection of an invalid backwards shipment transition.

Existing inventory tests cover stock filters, ledger history, adjustment actor,
reservations, deductions, and checkout stock behaviour.

## Remaining Issues / Future Improvements

- Add a carrier integration/webhook adapter only when a real carrier is chosen;
  signed carrier callbacks should feed the same `ShipmentService` state map.
- Tracking-number uniqueness is carrier-dependent and should be enforced as a
  composite carrier/tracking identifier once carrier requirements are known.
- Focused Docker tests could not run in this session because Docker access was
  declined. Verify with the command below before declaring runtime completion.

```powershell
docker compose exec app php artisan test --filter="(AdminInventoryTest|AdminOpsTest|CheckoutTest)"
```

## Final Status

| Area | Status |
| --- | --- |
| Inventory / Transactions | PASS — pending container verification |
| Shipping Methods | PASS — pending container verification |
| Shipments / Shipment Status | PASS — pending container verification |
| Notifications | PASS — pending container verification |
| Product / Variant / Checkout / Order Integration | PASS — pending container verification |
| Stock Accuracy / Concurrency / Security | PASS — pending container verification |
| Automated Tests | NEEDS WORK — execution pending Docker access |
