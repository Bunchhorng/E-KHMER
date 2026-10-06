# E-KHMER Marketplace Phase 1

## Executive Summary

The application already included shops, shop memberships, product ownership, policies, and public shop APIs. This pass adds the first safe seller-application workflow without replacing the existing `admin`/`customer` authentication model.

## Changes Made

- Added `shops.rejection_reason` through a reversible migration.
- Added authenticated seller-application APIs: `GET` and `POST /api/seller/application`.
- Application creates a pending shop and its owner membership in one transaction.
- Enforced one owner application per user in Phase 1.
- Generated collision-safe shop slugs server-side.
- Super Admin shop rejection now requires and records a rejection reason; approving/suspending/reactivating clears it.

## Existing Architecture Retained

- Global `admin` continues to be the Super Admin authority, avoiding a breaking role migration.
- `shop_users` is the shop-specific role system (`owner`, `manager`, `staff`).
- Product, inventory, review, coupon, and shipping ownership already use shop IDs and policies.
- Public shop APIs expose active shops only.

## Status

| Area | Status |
| --- | --- |
| Roles / safe compatibility | PASS (global admin plus shop memberships) |
| Shops / product ownership | PASS (existing) |
| Seller application / approval | IMPLEMENTED; tests pending |
| Rejection and suspension data preservation | IMPLEMENTED |
| Seller dashboard and route guard | NEEDS WORK |
| Seller product/staff/settings APIs and pages | NEEDS WORK |
| Public shop Vue pages / seller information on product UI | NEEDS WORK |
| Cross-shop automated verification | NEEDS WORK |

## Phase 2 boundary

Parent orders, shop orders, checkout splitting, per-shop shipment/payment allocation, and payouts are out of Phase 1 scope and must not be treated as validated by this report.

## Verification

`git diff --check` passed. Docker-based migration and test execution remain pending in this workspace.
