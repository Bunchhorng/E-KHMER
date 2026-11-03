# E-KHMER - UI/UX System and Symmetry Refactor Report

**Date:** 2026-11-03  
**Scope:** Source-based frontend inventory and first refactor slice  
**Status:** **FOUNDATION IMPLEMENTED - PAGE-BY-PAGE COMPLETION REMAINS**

## 1. UI Inventory

| Item | Count | Source of truth |
|---|---:|---|
| Named application routes | 84 | `frontend/src/router/index.ts`; redirects and navigation-guard return targets excluded |
| View files | 66 | `frontend/src/views/**/*.vue` |
| Shared components | 53 | `frontend/src/components/**/*.vue` |
| Layouts | 7 | `frontend/src/layouts/**/*.vue` |

| Area | Routes | Shared layout/template |
|---|---:|---|
| Public/storefront | 12 | `StoreLayout` and `ContentLayout` |
| Authentication | 5 | `AuthLayout` |
| Customer account | 13 | `AccountLayout` |
| Seller workspace | 13 | `SellerLayout` over `DashboardLayout` |
| Super admin | 40 | `AdminLayout` over `DashboardLayout` |
| System/utility | 1 | `NotFoundView` |

The inventory includes non-route UI such as the cart drawer, account navigation drawer, admin table action menu, BaseModal, product-image media, barcode field, rich-text editor, filter controls, payment/COD panels, chart components, and loading/empty components.

## 2. Completed Design-System Work

### Central semantic tokens

`frontend/src/style.css` now defines a shared semantic token layer for both themes:

- Surface hierarchy: canvas, surface, raised, muted, hover, active.
- Text hierarchy: primary, secondary, muted, disabled.
- Borders: default, strong, focus.
- Semantic actions: primary, accent, success, warning, danger, info.
- Shared control/card radius and card/raised shadow values.

The Tailwind configuration exposes those tokens rather than requiring new page-specific colours. Global primitives now cover page headers, panels, filter bars, table shells, controls, empty states, focus rings, reduced motion, and standard button sizes.

### Cross-context primitives

| Primitive | Status | Result |
|---|---|---|
| `AppPageHeader` | STANDARDIZED | One title/description/action alignment contract for dashboard pages. |
| `DashboardPageHeader` | REFACTORED | Delegates to `AppPageHeader`, preserving all current seller/admin callers. |
| `AppImage` | STANDARDIZED | Lazy loading, cover/contain modes, and accessible no-broken-image fallback. |
| `AssetImage` | REFACTORED | Uses `AppImage` without changing its existing public API. |
| `ProductCard` | REFACTORED | Uses the shared image primitive; image failures now render a branded-neutral fallback rather than a missing/broken media area. |
| `AdminDataTable` | REFACTORED | Uses `AppImage` for table media. |
| `DashboardLayout` | REFACTORED | Stops overriding global colour tokens, so seller and admin share the same light/dark semantic system as the storefront. |
| Existing `BaseModal`, `BaseBadge`, `EmptyState`, pagination, dashboard sections | RETAINED | Already reusable and compatible with the centralized tokens. |

## 3. Theme and Language Baseline

| Concern | Result | Evidence |
|---|---|---|
| Light mode | PASS - system baseline | Explicit light semantic palette; components consume token classes. |
| Dark mode | PASS - system baseline | Distinct canvas/surface/raised/muted hierarchy, not simple inversion. |
| Theme persistence | PASS | Existing Pinia store persists `ekhmer_theme`. |
| Theme flash prevention | PASS | `index.html` applies the stored theme before the Vue application mounts. |
| Khmer typography | PASS - baseline | Kantumruy Pro and Khmer-specific line-height treatment remain in the global stylesheet. |
| English typography | PASS - baseline | Inter-first hierarchy remains intact. |
| Full visual parity audit | NEEDS WORK | Each content-heavy view still needs visual review in Khmer/English and both themes. |

## 4. Route-Coverage Baseline

Every route now inherits the central token layer through the application root; account, storefront, auth, seller, and admin continue to use their intended layouts. The following represents source inventory and baseline template coverage, not a claim that every individual view has been manually redesigned.

| Area | Route families discovered | Current template status |
|---|---|---|
| Storefront | Home, catalog, shops, shop storefront, product, cart, checkout, Bakong payment, success, tracking, support/help | `StoreLayout` + unified tokens; product media standardized. |
| Auth | Login, register, forgot/reset password, email verification | `AuthLayout` + shared card/form/button primitives. |
| Customer | Dashboard, orders/detail, wishlist, addresses/create/edit, profile, notifications, reviews, password, returns, support | `AccountLayout` + shared token baseline. |
| Seller | Application, dashboard, analytics, settings, returns, payments, products/create/edit, inventory, orders/detail | `SellerLayout` on the common `DashboardLayout`; shared page-header contract. |
| Admin | Dashboard, shops/members/owners/managers, catalog, orders, inventory, reports, analytics, payments, shipments, customers, settings, coupons, reviews, categories, brands, shipping | `AdminLayout` on the common `DashboardLayout`; common table/image/header primitives. |
| System | Not found | Global typography, tokens and focus system apply. |

## 5. Quality Controls Added or Preserved

- Consistent visible focus outline across native and shared controls.
- Reduced-motion fallback for transitions and animations.
- Minimum 40px standard button/control height, with small and large variants.
- Accessible image fallback with descriptive `aria-label`.
- Existing modal focus trap, restore-focus behavior, Escape support, and responsive modal sizes preserved.
- Existing responsive sidebar/drawer behavior for account, seller and admin preserved.
- Business logic, route authorization, checkout, inventory, payments, and API contracts were not changed.

## 6. Verification

| Check | Result |
|---|---|
| Vue TypeScript project build | PASS |
| Vite production build | PASS - 2,379 modules transformed, build completed in 16.00s |
| Existing full browser suite | NOT RE-RUN | It was already red before this visual refactor: two final account-heading failures and retry flakiness are recorded in the production-readiness audit. |
| Backend/business-logic suite | NOT RUN for this UI-only slice | No backend code changed; existing forecast-suite failures remain a release blocker. |

## 7. Files Changed

- `frontend/src/style.css`
- `frontend/tailwind.config.js`
- `frontend/src/components/AppPageHeader.vue` (new)
- `frontend/src/components/AppImage.vue` (new)
- `frontend/src/components/dashboard/DashboardPageHeader.vue`
- `frontend/src/components/AssetImage.vue`
- `frontend/src/components/ProductCard.vue`
- `frontend/src/components/admin/AdminDataTable.vue`
- `frontend/src/layouts/DashboardLayout.vue`

## 8. Remaining UI Refactor Work

The request describes a complete application-wide redesign. This slice establishes the safe, reusable foundation without rewriting every page in one unreviewable change. The next work should be delivered subsystem-by-subsystem:

1. Apply `AppPageHeader`, `filter-bar`, `table-shell`, and form-section primitives to each seller/admin view.
2. Standardize storefront category/shop cards, checkout panels, cart rows, and payment states on top of the new semantic tokens.
3. Migrate account and authentication view headings/alerts to the shared primitives.
4. Add shared confirm-dialog, drawer, alert/toast, form-field, upload, and skeleton APIs where current local patterns differ.
5. Perform visual regression at 390/768/1024/1280/1440/1920px in Khmer and English, in light and dark themes, then fix individual content overflows.
6. Make the pre-existing browser and backend test gates green before considering production release.

## 9. Final UI Decision

**One E-KHMER design-system foundation: PASS.**  
**Complete route-by-route symmetry refactor: NEEDS WORK.**

The architecture is now ready for incremental, testable subsystem migrations. It would be inaccurate to label all 84 routes fully redesigned without completing the remaining page-level visual and responsive audit.
