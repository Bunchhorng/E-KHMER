# E-KHMER Shopping Flow Audit

## Executive Summary

The shopping flow uses variant-based cart lines, server-calculated prices,
database-backed inventory reservations, transactional order creation, coupon
usage tracking, and order-item snapshots. This audit strengthened the places
where the browser and API could disagree and added regression coverage for the
most important ownership, availability, and post-confirmation behaviours.

## Cart

### Status: PASS (focused verification pending container test access)

### Existing strengths

- Guest carts are isolated by `X-Session-Id`; authenticated carts are isolated
  by user ID.
- `cart_items` has a database uniqueness constraint on cart plus variant.
- The server derives line prices from the current variant/product, not request
  payload values.
- Guest-cart login merge checks product availability and caps quantity by live
  stock.

### Changes Made

- Requests exceeding live stock now receive a useful validation error instead
  of silently changing the requested quantity.
- Updating a cart item now rejects unavailable products/variants and stock
  overages rather than writing an invalid quantity.
- Cart totals now correctly fall back to the parent product price when a
  variant inherits pricing.
- Cart API responses include selected variant attributes, so the cart and order
  review can show the actual option selection.
- The customer cart estimate now calculates tax after discount, matching the
  checkout calculation.
- A failed clear request no longer clears the browser cart optimistically.

## Wishlist

### Status: PASS (focused verification pending container test access)

### Existing strengths

- Wishlist ownership is authenticated and constrained by one wishlist per user
  and one item per wishlist/product.

### Changes Made

- Inactive or unpublished products can no longer be added to a wishlist.
- The frontend now uses the API response as the source of truth and restores
  prior state after a failed request.
- Moving a wishlist product to cart waits for a successful cart add before
  removing it from the wishlist.

## Checkout

### Status: PASS for implemented reservation flow; online-gateway integration remains future work

### Existing strengths

- Checkout creates orders, order items, payments, shipment records, coupon
  usage, and inventory reservations inside a database transaction.
- Stock is reserved atomically and released on cancellation/expiry.
- Confirming an order is locked and cannot settle an already-settled order.
- Address ownership is enforced for saved addresses; shipping price and coupon
  discount are recalculated server-side.

### Changes Made

- Checkout now re-reads and locks cart rows, then rejects inactive,
  unpublished, or otherwise unavailable variants before reserving stock.
- Guest carts are cleared after confirmation, just as authenticated carts are.
- The payment record carries the server-side cart ID so confirmation clears the
  correct cart without trusting the browser.
- The checkout UI shows actionable API failures and keeps the cart available
  for review.
- A successful confirmation refreshes cart state rather than making a second
  destructive clear request.

## Business Logic Improvements

- Prices and totals remain server authoritative. The client sends product
  variant IDs and quantities only; checkout recalculates subtotal, discount,
  tax, shipping, and grand total.
- Product, variant, and shop availability is checked again immediately before
  inventory reservation.
- Coupon application is revalidated during checkout; the cart-page coupon
  preview never becomes a financial commitment.

## Security Improvements

- Cart item mutation remains scoped through the resolved current cart.
- Wishlist mutation remains scoped through the authenticated user's wishlist.
- Stale/inactive variants are blocked at both cart mutation and checkout.
- Cart IDs used during confirmation come from the persisted payment metadata,
  not request input.

## User Experience Improvements

- Customers receive available-stock messages instead of invisible quantity
  clipping.
- Variant option labels are present in cart API data.
- Checkout failures are displayed in the page and preserve the cart.

## Automated Tests

Added or updated coverage for:

- rejecting cart quantities above live stock;
- blocking checkout after a cart variant becomes inactive;
- clearing a guest cart after successful confirmation;
- wishlist duplicate prevention, ownership isolation, and inactive-product
  rejection.

## Remaining Issues / Future Improvements

- A production payment gateway webhook/signature flow is still required before
  enabling real card or gateway settlement. The existing production safeguard
  correctly rejects self-confirmed online payments.
- Checkout begin does not yet accept a formal idempotency key. The UI prevents
  duplicate clicks, but a dedicated persisted idempotency key is recommended
  before high-volume production use.
- Coupon preview endpoint accepts a supplied subtotal for display purposes;
  checkout itself recalculates and validates the true subtotal. A cart-bound
  coupon-preview endpoint would remove this remaining preview-only mismatch.
- Container test execution was requested but Docker access was not granted in
  this session. Do not mark focused tests as executed until
  `docker compose exec app php artisan test --filter="(CartTest|WishlistTest|CheckoutTest)"`
  passes.

## Final Status

| Area | Status |
| --- | --- |
| Cart | PASS — pending container verification |
| Wishlist | PASS — pending container verification |
| Checkout | PASS — pending container verification |
| Product / Variant integration | PASS — pending container verification |
| Inventory integration | PASS — pending container verification |
| Address / Shipping / Coupon integration | PASS — pending container verification |
| Payment integration | NEEDS WORK — real gateway integration remains |
| Order integration | PASS — pending container verification |
| Automated tests | NEEDS WORK — not executable in this session |
