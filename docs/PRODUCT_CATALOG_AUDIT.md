# E-KHMER Product Catalog Audit

## Executive Summary

The catalog already contains a substantial Laravel implementation: soft-deleted products and variants, public active-product scopes, shop visibility safeguards, variant inventory, server-side search/filtering/pagination, image validation, and catalog integration tests. This pass fixed three business-integrity gaps found during code inspection:

- deleting a used brand silently removed the product-to-brand relationship;
- a stale guest cart could merge unavailable items or quantities above live stock after login;
- the admin “base stock” control was discarded for products created without generated option variants.

Docker-based runtime tests were not run in this pass because Docker command approval was declined. No feature is marked complete solely from inspection.

## Products

### Status: PARTIAL — HIGH

### Problems Found

- Simple-product creation from the admin form exposed base stock but did not create an inventory-bearing cartable variant when no option variants were generated.

### Changes Made

- The admin form now sends one explicit `Default` variant with the configured base price and base stock whenever no generated variants exist, for both creation and edit paths.
- Product deletion is already soft delete, preserving historical order-item snapshots.

### Missing Features

- The visible “Save draft” action is presently UI-only and does not persist a distinct draft state. The existing data model has `is_active`, not a draft/archived status enum; this requires a product decision and migration/API plan.

## Categories

### Status: PARTIAL — MEDIUM

- Existing deletion handling is safe: categories with children or attached products are blocked.
- Category hierarchy, cache invalidation, active filtering, and tree resources are present.
- Runtime tests remain pending.

## Brands

### Status: IMPROVED — HIGH

### Problems Found

- Brand deletion was unconditional. Because `products.brand_id` is `nullOnDelete`, deleting a brand silently removed its catalog association from every attached product.
- Duplicate slugs depended on the database unique index, risking an unfriendly database exception.

### Changes Made

- Block deletion of a brand with products and return a clear reassignment message.
- Validate brand slug uniqueness at the request layer and normalise supplied slugs.

## Product Images

### Status: PARTIAL — MEDIUM

- Multi-image galleries, cover-image fallback, media type/size request validation, safe storage URLs, and cleanup on gallery sync are implemented.
- The media service restricts deletion to project-owned `images/` public-storage paths.
- Runtime upload/delete testing remains pending.

## Product Variants

### Status: PARTIAL — HIGH

- Variant SKU collision checks, attribute-combination duplicate detection, price fallback, stock integration, and soft deletes are present.
- Variant resolution requires all selected attribute values and returns live availability.
- Simple products now receive the default variant required by cart/checkout from the admin workflow.

## Attributes

### Status: PARTIAL — MEDIUM

- Attributes and values are created/mapped while variants are saved.
- Catalog filters use AND semantics across attribute groups and OR semantics within a group; exact value comparison avoids substring false matches.
- Runtime CRUD and concurrent-creation verification remain pending.

## Database Relationships

- `Product` belongs to nullable `Category`, `Brand`, and `Shop`; it has images, variants, reviews, wishlists, and order items.
- `ProductVariant` has inventory and attribute-value pivot rows.
- Order items persist title, SKU, variant label, image path, unit price, and quantity, so catalog changes do not rewrite purchase history.
- Products and variants use soft deletes. Categories/brands use safe controller rules to avoid accidental relation loss.

## Business Logic Improvements

- Used brands must be explicitly reassigned before deletion.
- Guest-cart migration now omits unpublished/inactive/out-of-stock variants and caps merged quantities at live available stock.
- A default variant carries simple-product stock into inventory/cart/checkout.

## Customer UX Improvements

- Customers no longer receive stale, unpurchasable cart lines after authentication.
- Simple catalog items created through the admin form can be shown in stock and purchased when base stock is set.

## Admin UX Improvements

- The base-stock field now has the expected effect for products without selected options.
- Brand deletion gives a clear, actionable reassignment response rather than silently changing products.

## API Improvements

- Duplicate brand slugs produce normal Laravel validation errors (422) instead of relying on a database constraint failure.
- Catalog listing uses bounded pagination, allowed sorts, eager loading, and public active-product scoping.

## Security Improvements

- Admin routes are protected by Sanctum plus admin middleware.
- Catalog management validates product media paths before gallery attachment.
- Guest-cart migration now repeats purchaseability checks server-side rather than trusting stale cart data.

## Performance Improvements

- Existing catalog facets aggregate relationship counts through eager loading instead of per-value counts.
- Existing listing/detail endpoints use explicit eager loads and bounded pagination.
- No performance claims are made without runtime measurements.

## Bugs Fixed

- Prevented silent product-brand disassociation on brand deletion.
- Prevented duplicate brand slugs from reaching a database error.
- Prevented stale guest-cart lines and excessive quantities after login.
- Connected simple-product base stock to a real inventory-bearing default variant.

## Tests Added

- Brand with products cannot be deleted.
- Brand slug must be unique.
- Guest-cart merge caps the combined quantity at current stock.
- Guest-cart merge omits variants made inactive before login.

## Remaining Issues

- Run backend feature tests and frontend type/build checks in Docker.
- Decide whether `Draft` and `Archived` need first-class persisted product statuses; the current active/inactive model cannot accurately represent the UI’s draft action.
- Add an explicit concurrent image-cover test if the admin workflow expects simultaneous gallery edits.

## Future Improvements

- Add a dedicated persisted-draft workflow only after defining product status transitions and storefront visibility rules.
- Add image derivatives/thumbnails if production image payloads prove large.
- Add integration coverage for product creation through the Vue form and checkout of a simple product.

## Final Status

| Area | Status |
| --- | --- |
| Products | NEEDS WORK (runtime verification pending) |
| Categories | NEEDS WORK (runtime verification pending) |
| Brands | NEEDS WORK (runtime verification pending) |
| Product Images | NEEDS WORK (runtime verification pending) |
| Product Variants | NEEDS WORK (runtime verification pending) |
| Attributes | NEEDS WORK (runtime verification pending) |
| Inventory Integration | NEEDS WORK (runtime verification pending) |
| Cart Integration | NEEDS WORK (runtime verification pending) |
| Order Integration | NEEDS WORK (runtime verification pending) |
| API | NEEDS WORK (runtime verification pending) |
| Admin UX | NEEDS WORK (runtime verification pending) |
| Customer UX | NEEDS WORK (runtime verification pending) |
| Security | NEEDS WORK (runtime verification pending) |
| Performance | NEEDS WORK (not measured) |
| Automated Tests | NEEDS WORK (not run) |
