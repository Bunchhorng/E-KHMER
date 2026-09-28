# Team Work Breakdown — E-KHMER E-Commerce (5 Members)

Status: PLANNING · Based on the current repo layout (Laravel REST API + Vue 3/TS SPA, Docker, MySQL, Redis).

Every ownership table below maps to **real files that exist today**, so the team can branch and merge without stepping on each other.

---

## 1. Team Overview

| Member | Role | Backend scope | Frontend scope | DB (migrations) | Branch prefix | Route group |
|----|----|----|----|----|----|----|
| M1 | Project Lead · Auth · User & Org Management | Auth, account, addresses, customers, notifications, settings, shops | Auth pages, account portal, admin customers/notifications/settings | `users`, `personal_access_tokens`, `addresses`, `notifications`, `settings`, `shops`, `shop_users` | `feature/auth-*`, `feature/org-*` | `/auth`, `/account`, `/addresses`, `/admin/customers`, `/admin/notifications`, `/admin/settings`, `/admin/shops`, `/shops` |
| M2 | Product · Category · Brand · Reviews · Media | Catalog/search/facets, products+variants+attributes, media uploads, reviews | Storefront browse pages, admin product/category/brand/review CRUD | `categories`, `brands`, `products`, `product_images`, `attributes`, `attribute_values`, `product_variants`, `variant_attribute_values`, `reviews`, `review_images` | `feature/catalog-*` | `/catalog`, `/categories`, `/brands`, `/attributes`, `/products/{id}/reviews`, `/reviews`, `/admin/products`, `/admin/categories`, `/admin/brands`, `/admin/reviews`, `/admin/media` |
| M3 | Cart · Wishlist · Checkout · Orders · Coupons | Cart, guest carts, checkout reservation lock, order state machine, coupons | Cart/checkout/order pages, account orders/wishlist, admin orders/coupons | `carts`, `cart_items`, `wishlists`, `wishlist_items`, `orders`, `order_items`, `order_status_histories`**, `coupons`, `coupon_usages` | `feature/cart-*`, `feature/order-*`, `feature/coupon-*` | `/cart`, `/checkout`, `/orders`, `/wishlist`, `/coupons`, `/admin/orders`, `/admin/coupons` |
| M4 | Payment · Shipping · Inventory · Dashboard · Reports | Payments/transactions, shipments, shipping methods, inventory + ledger, admin KPIs/reports | Admin inventory/payments/shipments/shipping-methods/dashboard/reports | `inventories`, `inventory_transactions`, `shipping_methods`, `shipments`, `payments`, `payment_transactions`, `tracking_events` | `feature/payment-*`, `feature/fulfill-*`, `feature/report-*` | `/shipping-methods`, `/admin/payments`, `/admin/shipping-methods`, `/admin/shipments`, `/admin/inventory`, `/admin/dashboard`, `/admin/reports` |
| M5 | Frontend Foundation · UI Integration · Testing | none (consumes APIs) | Layouts, shared components, router, i18n, theming, responsiveness, E2E/QA across all modules | none | `feature/ui-*` | none |

> **Table marked `**` (`order_status_histories`)** — requested entity with **no migration in the repo yet**; M3 creates it. Same for `review_images` (M2), `avatars`/profile fields (M1): create migrations if absent.

---

## 2. Detailed Tasks per Member

### Member 1 — Project Lead · Authentication · User Management

1. **Main responsibility**
   - Own the auth/user/org domain end-to-end, keep the repo healthy (CI, conventions, branch strategy), and arbitrate conflicts. First to set up the skeleton so everyone can start in parallel.

2. **Features to develop**
   - Register, Login, Logout, Me, Password change/reset, Email verification, Avatar upload
   - RBAC: `customer` vs `admin` role, `EnsureUserIsAdmin` middleware, role helpers
   - Customer account: profile, addresses (default-address logic), notifications (read/unread/all)
   - Admin: customers list/detail (orders_count, lifetime_spend), notifications, settings, shop (branch) CRUD + shop_users membership
   - Multi-branch org: `shops`, `shop_users`, `ShopPolicy`, public shop page

3. **Database tables**
   - `users`, `personal_access_tokens`, `addresses`, `notifications`, `settings`, `shops`, `shop_users`, `cache`, `jobs` (infra — owned by lead)

4. **Backend API tasks**
   - `AuthController` (register/login/me/logout), `AccountController` (dashboard/profile/password/notifications/read), `AddressController` (CRUD + default), `AdminCustomerController`, `AdminNotificationController`, `SettingsController`, `AdminShopController` + `ShopController` (public)
   - Requests: `LoginRequest`, `RegisterRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ChangePasswordRequest`, `UpdateProfileRequest`, `UpdateAvatarRequest`, `AddressRequest`
   - Resources: `UserResource`, `AddressResource`, `ShopResource`

5. **Frontend tasks**
   - `views/auth/*` (Login, Register, Forgot, Reset, VerifyEmail), `layouts/AuthLayout.vue`
   - `views/account/*` (Dashboard, Profile, ChangePassword, Addresses, AddressForm, Notifications)
   - `views/admin/*` (AdminCustomers, AdminCustomerDetail, AdminNotifications, AdminSettings)
   - `stores/auth.ts`, `api/auth.ts`, `api/account.ts`, `api/addresses.ts`, avatar part of `api/uploads.ts`
   - Router guards: `requiresAuth` + `admin` meta (coordinate with M5 on `router/index.ts`)

6. **CRUD**
   - Addresses (full CRUD + set-default), customers (R), settings (RU), shops (CRUD + status transitions for super admin)

7. **Validation**
   - Email uniqueness, password `min:8 confirmed`, current-password check on change, address `required` fields, non-bcrypt-hash login → 401 not 500

8. **Auth/authorization**
   - Sanctum bearer tokens; `admin` middleware for all `/admin/*`; customers scoped to their own resources; shop staff scoped via `ShopPolicy`; never trust `role` from the client

9. **Testing tasks**
   - `AuthTest`, `AccountAndSettingsTest`, `ShopTest`, `NotificationTest`; regenerate PERSONAL-ACCESS tokens covered by auth tests; ensure login/register/401/403 matrix

10. **Expected deliverables**
    - Auth + account + customer management merged; `develop` green; README/API-REFERENCE auth section updated; lead publishes the file-ownership map + CI config

### Member 2 — Product · Category · Brand · Reviews · Media

1. **Main responsibility**
   - Catalog domain: everything a customer sees and an admin manages about products.

2. **Features to develop**
   - Product listing with search/filter/sort/pagination, facets for the filter sidebar, featured rail
   - Product detail with gallery, **variant selector (EAV resolution → concrete variant + live stock)**, zoom, stock badge
   - Product CRUD incl. variants/attributes/SKUs, images (primary, upload, delete)
   - Category tree (parent/child), brand list, attribute values
   - Review submission (verified-purchase rule) + moderation queue (approve/reject + `rating_avg` recalc)

3. **Database tables**
   - `categories`, `brands`, `products`, `product_images`, `attributes`, `attribute_values`, `product_variants`, `variant_attribute_values`, `reviews`, `review_images` (**create if absent**)

4. **Backend API tasks**
   - `CatalogController` (index/show/featured/facets), `CategoryController`, `BrandController`, `AttributeController`, `ReviewController` (public index + store/update), `AdminProductController`, `AdminCategoryController`, `AdminBrandController`, `AdminReviewController`, `AdminMediaController`
   - Services: `CatalogService`, `MediaUploadService`, `ReviewService`
   - Requests: `AdminProductRequest` (min:0 on price/compare/quantity), `AdminCategoryRequest`, `AdminBrandRequest`, `AdminImageUploadRequest`, `ReviewStoreRequest`, `ReviewUpdateRequest`
   - Resources: `ProductResource`, `ProductDetailResource`, `ProductImageResource`, `CategoryResource`, `BrandResource`, `AttributeResource`, `AttributeValueResource`, `ReviewResource`

5. **Frontend tasks**
   - `HomeView`, `ShopView` (filter sidebar + sorting), `ProductDetailView` (variant selector, gallery, zoom, stock badge)
   - Components: `CategoryNavBar`, `ProductCard`, `ProductRail`, `PdpSkeleton`, `ProductGridSkeleton`, `StarRating`
   - Admin CRUD: `AdminProductsView`, `AddProductView`, `AdminCategoriesView`, `AdminBrandsView`, `AdminReviewsView`, `CategoryFormView`, `BrandFormView`
   - Account: `ReviewsView` (my reviews). APIs: `catalog.ts`, `categories.ts`, `brands.ts`, `attributes.ts`, `reviews.ts`, product section of `admin.ts`, `utils/product.ts`

6. **CRUD**
   - Products (C/R/U/D/restore) incl. nested variants + inventory init, categories, brands, reviews(m)

7. **Validation**
   - Slug/name uniques, per-branch SKU uniqueness (`unique(shop_id, sku)`), `min:0` price/quantity, image type/size, review `rating 1–5`, verified-purchase enforcement (Delivered order) + no duplicates

8. **Auth/authorization**
   - Public catalog anonymous; review store `auth:sanctum`; admin product/category/brand/review `Bearer` + `admin`; `ProductPolicy`/`ReviewPolicy` for cross-branch limits (defence-in-depth); media uploads admin-only

9. **Testing tasks**
   - `CatalogTest`, `AdminTest` (product/category/brand), `AdminMediaTest`, `ReviewTest`, product parts of `ShopOwnershipTest`; verify variant EAV resolution and facets

10. **Expected deliverables**
    - Catalog + product/review management merged; facet + product-detail API stable; `ProductDetailResource` exposes `shop_id`/`shop`; media uploads verified in Docker

### Member 3 — Cart · Wishlist · Checkout · Orders · Coupons

1. **Main responsibility**
   - The buying pipeline: from add-to-cart to paid order, including the coupon engine and the inventory reservation lock (MI with M4).

2. **Features to develop**
   - Cart (guest via `X-Session-Id` + authenticated), add/update/remove/clear/totals, stock-capping
   - Wishlist (add/list/remove)
   - Multi-step checkout: address → shipping → coupon → payment; inventory **reservation lock (~15 min)**; begin/confirm/cancel
   - Order state machine `Pending → Confirmed → Processing → Shipped → Delivered` (+ cancelled/refunded), order snapshotting (price/title copied into `order_items`), cancel + refund/restock + coupon usage reclaim
   - Coupon rules: expiry → usage limit → min-order → percentage/fixed; `coupon_usages` at confirm with rollback

3. **Database tables**
   - `carts`, `cart_items`, `wishlists`, `wishlist_items`, `orders`, `order_items`, `order_status_histories` (**create if absent**), `coupons`, `coupon_usages`

4. **Backend API tasks**
   - `CartController` (index/add/update/remove/clear/totals), `CheckoutController` (begin/confirm/cancel), `OrderController` (list/show/cancel), `WishlistController`, `CouponController` (validate), `AdminOrderController` (list/show/transition), `AdminCouponController`
   - Services: `CartService`, `CheckoutService`, `OrderService`, `OrderNumberGenerator`, `CouponService`
   - Requests: `CartAddRequest`, `CartUpdateRequest`, `CheckoutRequest`, `OrderTransitionRequest`, `CouponValidateRequest`, `AdminCouponRequest`
   - Resources: `CartResource`, `CartItemResource`, `OrderResource`, `OrderListResource`, `OrderItemResource`, `CouponResource`

5. **Frontend tasks**
   - `CartView`, `CheckoutView` (multi-step), `OrderSuccessView`, `OrderTrackingView` (status stepper), `CartDrawer`
   - Account: `OrdersView`, `OrderDetailView`, `WishlistView`
   - Admin: `AdminOrdersView`, `AdminOrderDetailView`, `AdminCouponsView`, `CouponFormView`
   - `stores/cart.ts`, `stores/wishlist.ts`; `api/cart.ts`, `api/checkout.ts`, `api/orders.ts`, `api/coupons.ts`, `api/wishlist.ts`, orders/coupons section of `admin.ts`

6. **CRUD**
   - Cart (full CRUD + totals), wishlist (C/R/D), orders (R + status transition + cancel), coupons (full CRUD)

7. **Validation**
   - `quantity 1–99`, cap to available stock, coupon rules server-side, order transition allowed-paths, checkout address rules, amounts **always recomputed server-side** (never trust frontend totals)

8. **Auth/authorization**
   - Cart/checkout public but scoped by `X-Session-Id`; own-order isolation (`OrderService::listFor`), non-owner → 404; admin orders `Bearer`+`admin`; `OrderPolicy`/`OrderItemPolicy`/`CouponPolicy` for branch scoping; row-lock `orders`+`inventories` in transactions

9. **Testing tasks**
   - `CartTest`, `CheckoutTest`, `CouponTest`, `AdminOpsTest`, `CheckoutGuest`, `QaHotfixTest`, order/cart parts of `ShopOwnershipTest`; verify reservation lock, no oversell, coupon reclaim on cancel

10. **Expected deliverables**
    - Cart→order pipeline green with reservation-lock tests; order cancellation refunds/restocks; coupon usage reclaimed; order snapshotting verified

### Member 4 · Payment · Shipping · Inventory · Dashboard · Reports

1. **Main responsibility**
   - Money + stock + fulfilment integrity: payments, shipments, inventory ledger, and the admin analytics that read them.

2. **Features to develop**
   - Payments: create/record, status lifecycle, transactions history, **mock-vs-production payment gate** (`config/ecommerce.php` `payment_mode`), never expose card/CVV
   - Shipping: shipping methods CRUD, shipments (tracking number/carrier/status), COD reconciliation path
   - Inventory: per-variant (+ per-branch) stock, `quantity - reserved_quantity` availability, low-stock threshold + alerts, adjustment + full `inventory_transactions` ledger, release/deduct/re-reserve, ledger never branch-less
   - Admin dashboard KPIs (revenue/orders/customers), revenue trend, status distribution, sales-by-category, low-stock alerts
   - Reports: `orders.csv` export with status/date filters

3. **Database tables**
   - `inventories`, `inventory_transactions`, `shipping_methods`, `shipments`, `payments`, `payment_transactions`, `tracking_events`

4. **Backend API tasks**
   - `ShippingMethodController` (public list), `AdminShippingMethodController`, `AdminShipmentController`, `AdminPaymentController`, `AdminInventoryController`, `DashboardController`, `AdminReportController`
   - Services: `InventoryService` (self-healing `syncShop`, reserve/deduct/release, ledger), `DashboardService`
   - Requests: `AdminShippingMethodRequest`
   - Resources: `InventoryResource`, `InventoryTransactionResource`, `PaymentResource`, `ShipmentResource`, `ShippingMethodResource`

5. **Frontend tasks**
   - Admin: `AdminDashboardView` + `components/admin/charts/*` (Revenue, OrderStatus, PaymentStatus, OrdersTrend, SalesCategory) + `useChartTheme`, `AdminInventoryView`, `AdminPaymentsView`, `AdminShipmentsView`, `AdminShippingMethodsView`, `ShippingMethodFormView`, `AdminReportsView`
   - `api/shipping.ts`, inventory/payments/dashboard/reports sections of `admin.ts`, `utils/download.ts` (CSV)

6. **CRUD**
   - Shipping methods (full CRUD), shipments (R + tracking/status U), payments (R + status), inventory (stock in/out/adjust + history), dashboard/reports (R)

7. **Validation**
   - `price >= 0`, estimated days `>= 0`, stock never negative, `low = available > 0 && <= threshold` (not overlapping `out`), dashboard `days` clamped, report `from/to` dates validated (422 not 500)

8. **Auth/authorization**
   - Public: only active shipping methods; admin inventory/payments/shipments/shipping `Bearer`+`admin`; `InventoryPolicy`/`ShippingMethodPolicy` for branch scoping; stock decrements inside `DB::transaction` + `lockForUpdate`

9. **Testing tasks**
   - `AdminInventoryTest`, `ScheduleTest` (reservation-expiry cron), `CheckoutTest` inventory assertions, inventory parts of `ShopOwnershipTest`; verify `available = quantity - reserved`, low/out buckets, ledger rows carry `shop_id`

10. **Expected deliverables**
    - Payment gate flag (sandbox default; production rejects online confirm with 422), inventory ledger fully branch-labeled, dashboard/reports rendering real data, CSIs `orders.csv`

### Member 5 · Frontend Foundation · UI Integration · Testing

1. **Main responsibility**
   - The customer-facing UI shell, the design system, and the integration/QA role that turns the four backend members' APIs into one working storefront.

2. **Features to develop**
   - Layouts (Store/Account/Admin), header/nav/footer, cart drawer entry, theme (light/dark) + i18n (EN/KM) + language switcher
   - Shared UI primitives: `BaseModal`, `BaseBadge`, `BasePagination`, `EmptyState`, `DataTableSkeleton`, `StatusTag`, `QuantityCounter`, `ThemeToggle`, `LanguageSwitcher`
   - Axios client + interceptors (auth header, **401 → clear session + redirect**), router with route guards and lazy-loaded routes
   - Responsive design pass, loading/empty/error states everywhere, browser-console + network-error sweep
   - Full end-to-end manual + automated smoke testing of the customer + account + admin journeys

3. **Database tables** — none (read-only consumer). May add `dev` data or fixtures/seeder reviews.

4. **Backend API tasks** — none. Owns the **API contract**: keeps `frontend/src/types/index.ts` and `frontend/src/api/*` aligned with `docs/API-REFERENCE.md`; flags drift back to the owning member.

5. **Frontend tasks**
   - `layouts/{StoreLayout,AccountLayout,AdminLayout}.vue`, `App.vue`, `main.ts`, `router/index.ts`
   - Shared components listed above; `plugins/i18n.ts`, `locales/{en,km}.json`, `stores/{ui,theme,locale}.ts`
   - `api/client.ts`, `api/index.ts`, `utils/{format,download}.ts`
   - Wire up every feature view (import/路由 lifecycle), responsive + empty/error/loading states, PWA-free build optimization (`npm run build` clean)

6. **CRUD** — none directly. UI states for all CRUD screens of M1–M4 (list/form/modal/confirm/toast).

7. **Validation** — client-side mirror of 422 messages; consistent form errors; never enforce authorization on the frontend (display-only).

8. **Auth/authorization** — implement guards (`requiresAuth`, `admin` meta) but always call real APIs; on 401 clear tokens + redirect to `?redirect=`; no data in `localStorage` except tokens/cart/session.

9. **Testing tasks**
   - `npm run build` (vue-tsc + vite) green for every PR; browser console/network sweep; end-to-end smoke of the **critical flow** (register→login→browse→detail→cart→checkout→success→tracking; admin login→dashboard→orders→inventory); i18n key parity check; cross-browser (desktop/tablet/mobile)

10. **Expected deliverables**
    - Working storefront shell + shared design system; clean production build; an end-to-end test checklist signed off against the final integration checklist (§9/§10 below); documented UI/API contract notes

---

## 3. Database Ownership Table (migrations)

| Migration file | Owner |
|----|----|
| `0001_01_01_000000_create_users_table` | M1 |
| `0001_01_01_000001_create_cache_table`, `...000002_create_jobs_table` | M1 (infra) |
| `create_personal_access_tokens_table` | M1 |
| `2026_08_31_000024_create_addresses_table` | M1 |
| `2026_08_31_000025_create_notifications_table` | M1 |
| `2026_09_06_000001_create_settings_table` | M1 |
| `2026_09_26_000001_create_shops_table` | M1 |
| `2026_09_26_000002_create_shop_users_table` | M1 |
| `2026_08_31_000001_create_categories_table` | M2 |
| `2026_08_31_000002_create_brands_table` | M2 |
| `2026_08_31_000003_create_attributes_table` | M2 |
| `2026_08_31_000004_create_attribute_values_table` | M2 |
| `2026_08_31_000005_create_products_table` | M2 |
| `2026_08_31_000006_create_product_images_table` | M2 |
| `2026_08_31_000007_create_product_variants_table` | M2 |
| `2026_08_31_000008_create_variant_attribute_values_table` | M2 |
| `2026_08_31_000023_create_reviews_table` + `review_images` (new) | M2 |
| `2026_09_26_000003_add_shop_id_to_products_table` | M2 |
| `2026_09_27_000001_add_shop_id_to_reviews_and_backfill_shop_ownership` | M2 |
| `2026_09_27_000002_scope_variant_sku_uniqueness_per_shop` | M2 |
| `2026_08_31_000011_create_carts_table` / `000012_create_cart_items_table` | M3 |
| `2026_08_31_000013_create_wishlists_table` / `000014_create_wishlist_items_table` | M3 |
| `2026_08_31_000016_create_coupons_table` / `000022_create_coupon_usages_table` | M3 |
| `2026_08_31_000017_create_orders_table` / `000018_create_order_items_table` | M3 |
| `order_status_histories` (new) | M3 |
| `2026_09_26_000006_add_shop_id_to_order_items_table` | M3 |
| `2026_09_26_000008_add_shop_id_to_coupons_table` | M3 |
| `2026_09_25_000001_add_order_items_product_index` | M3 |
| `2026_08_31_000009_create_inventories_table` / `000010_create_inventory_transactions_table` | M4 |
| `2026_09_04_000001_add_low_stock_notified_at_to_inventories_table` | M4 |
| `2026_09_26_000004_add_shop_id_to_inventories_table` / `000005_..._inventory_transactions_table` | M4 |
| `2026_08_31_000015_create_shipping_methods_table` | M4 |
| `2026_08_31_000019_create_payments_table` / `000020_create_payment_transactions_table` | M4 |
| `2026_08_31_000021_create_shipments_table` | M4 |
| `2026_09_06_000002_create_tracking_events_table` | M4 |
| `2026_09_26_000007_add_shop_id_to_shipping_methods_table` | M4 |

Rule: new columns/constraints on a table go to that table's owner. The only shared migration concern is `down()` correctness — but **only the owner edits the file**.

---

## 4. API Ownership Table

| Endpoints (route group) | Owner |
|----|----|
| `POST /auth/register` `login` `GET /auth/me` `POST /auth/logout` | M1 |
| `GET /account/dashboard` `PUT /account/profile` `POST /account/password` `GET /account/notifications` `POST /account/notifications/{n}/read` | M1 |
| `/addresses` (GET/POST/PUT/DELETE/set-default) | M1 |
| `/admin/customers` (list/detail) | M1 |
| `/admin/notifications`, `/admin/settings`, `/admin/shops`, `/shops` (public) | M1 |
| `/catalog/products`, `/catalog/featured`, `/catalog/facets`, `/catalog/products/{slug}` | M2 |
| `/categories`, `/brands`, `/attributes` | M2 |
| `GET /products/{product}/reviews` `POST /reviews` `PUT /reviews/{review}` | M2 |
| `/admin/products`, `/admin/categories`, `/admin/brands`, `/admin/reviews`, `/admin/media` | M2 |
| `/cart` (GET/POST/DELETE), `/cart/items/{id}` (PUT/DELETE), `/cart/totals` | M3 |
| `/checkout`, `/checkout/{orderNumber}/confirm`, `/checkout/{orderNumber}/cancel` | M3 |
| `/orders`, `/orders/{orderNumber}`, `POST /orders/{orderNumber}/cancel` | M3 |
| `/wishlist` (GET/POST/DELETE/{product}) | M3 |
| `POST /coupons/validate`, `/admin/coupons` | M3 |
| `/admin/orders`, `PUT /admin/orders/{order}/transition` | M3 |
| `GET /shipping-methods` (public), `/admin/shipping-methods`, `/admin/shipments` | M4 |
| `/admin/payments`, `/admin/inventory`, `/admin/dashboard/overview`, `/admin/reports/orders.csv` | M4 |

**Route-file rule:** `backend/routes/api.php` has **owned sections** (one `// ==== M<N> ====` block per member). Each member edits **only their block**. If you need a route from another block, ask the owner in a PR comment — do not edit their block.

---

## 5. Frontend Page/File Ownership Table

| File group (`frontend/src`) | Owner |
|----|----|
| `views/auth/*`, `layouts/AuthLayout.vue`, `stores/auth.ts`, `api/auth.ts`, `api/account.ts`, `api/addresses.ts` | M1 |
| `views/account/{DashboardView,ProfileView,ChangePasswordView,AddressesView,AddressFormView,NotificationsView}.vue` | M1 |
| `views/admin/{AdminCustomersView,AdminCustomerDetailView,AdminNotificationsView,AdminSettingsView}.vue` | M1 |
| `views/{HomeView,ShopView,ProductDetailView}.vue` | M2 |
| `views/admin/{AdminProductsView,AddProductView,AdminCategoriesView,AdminBrandsView,AdminReviewsView,CategoryFormView,BrandFormView}.vue` | M2 |
| `components/{CategoryNavBar,ProductCard,ProductRail,PdpSkeleton,ProductGridSkeleton,StarRating}.vue`; `views/account/ReviewsView.vue` | M2 |
| `api/{catalog,categories,brands,attributes,reviews}.ts`, `utils/product.ts` | M2 |
| `views/{CartView,CheckoutView,OrderSuccessView,OrderTrackingView}.vue`, `components/CartDrawer.vue` | M3 |
| `views/account/{OrdersView,OrderDetailView,WishlistView}.vue` | M3 |
| `views/admin/{AdminOrdersView,AdminOrderDetailView,AdminCouponsView,CouponFormView}.vue` | M3 |
| `stores/{cart,wishlist}.ts`, `api/{cart,checkout,orders,coupons,wishlist}.ts` | M3 |
| `views/admin/{AdminDashboardView,AdminInventoryView,AdminPaymentsView,AdminShipmentsView,AdminShippingMethodsView,ShippingMethodFormView,AdminReportsView}.vue` | M4 |
| `components/admin/charts/*`, `composables/useChartTheme.ts`, `api/shipping.ts`, `utils/download.ts` | M4 |
| `layouts/{StoreLayout,AccountLayout,AdminLayout}.vue`, `App.vue`, `main.ts`, `router/index.ts` | M5 |
| `components/{AppHeader,AppFooter,BaseModal,BaseBadge,BasePagination,EmptyState,DataTableSkeleton,StatusTag,QuantityCounter,ThemeToggle,LanguageSwitcher}.vue` | M5 |
| `plugins/i18n.ts`, `locales/{en,km}.json`, `stores/{ui,theme,locale}.ts` | M5 |
| `api/client.ts`, `api/index.ts`, `utils/format.ts` | M5 |

**Shared-file rules**
- `api/admin.ts` holds every member's admin calls → **either split it into `api/admin/{products,orders,etc}.ts` at project start (M1 issue) or edit only your section**. Splitting is the recommended decision to avoid merge conflicts.
- `api/uploads.ts` is used by M1 (avatar) and M2 (product images) → M2 owns the file; M1 adds its function via a small PR M2 reviews.
- `router/index.ts` is owned by M5; members declare their routes by naming convention and M5 registers them. Do not reformat others' routes.
- `docs/API-REFERENCE.md` is the contract doc: update it in the **same PR** as the API change.

---

## 6. Git Branch Structure

```
main                            # release-ready (auto-deploy target)
 └── develop                    # integration branch — ALL PRs merge here
      ├── feature/auth-login            (M1)
      ├── feature/auth-password-reset   (M1)
      ├── feature/org-shops             (M1)
      ├── feature/catalog-products      (M2)
      ├── feature/catalog-reviews       (M2)
      ├── feature/cart-reservation      (M3)
      ├── feature/checkout-pipeline     (M3)
      ├── feature/coupon-engine         (M3)
      ├── feature/inventory-ledger      (M4)
      ├── feature/payment-shipping      (M4)
      ├── feature/dashboard-reports     (M4)
      ├── feature/ui-foundation         (M5)
      └── feature/ui-checkout-integration (M5)
```

- Naming: `feature/<module>-<short-description>` (lowercase, hyphenated). Never `feature/<name-of-person>` only.
- Long-lived module branches are allowed **per member** (M1..M5) as the integration point, with short-lived task branches off them:
  e.g. `module/m3-cart` → `feature/cart-add-item` → PR → `module/m3-cart` → PR → `develop`.

---

## 7. Git Workflow

1. **Branch**: task branch off `develop` (or your module branch). Keep task branches small (< ~400 lines) and focused on one feature.
2. **Commit**: conventional commits — `type(scope): subject`, scope = module name.
   - `feat(cart): add item quantity capping to available stock`
   - `fix(checkout): release inventory on confirm timeout`
   - `refactor(coupon): atomic usage-limit check`
   - `docs(api): document /checkout reservation window`
   - Types: `feat fix refactor docs test chore perf build style`.
   - **Never commit** `.env`, `frontend/.env`, `backend/.env`, caches, builds.
3. **Pull Request**: open against `develop`. Title = `type(scope): subject`. Body must list: changed files, tests run, API/resource shape changes, and a screenshot/steps for UI work.
4. **Review rules**: every PR needs **1 approving review** (for UI/API contract changes, the affected owner M1–M5 must approve). CI must pass: `docker compose exec app php artisan test` + `docker compose exec frontend npm run build`.
5. **Merge rules**: squash-merge into `develop` (keeps history clean). Never merge a red PR. `develop` → `main` only at release time from `develop` (fast-forward or squash), after all modules green.
6. **Conflicts**: don't resolve by rebasing other people's unmerged work. Pull `develop`/module branch, `git merge`, resolve your side, commit `merge: resolve <file> conflicts with <module>`. Escalate to M1 (lead) for cross-module file disputes. **Never force-push** on shared branches.
7. **Multi-shop scoping note**: any schema/API change touching `shop_id` ownership must be reviewed by the owning module member + M1, since the P1 report showed ownership bugs are silent failures.

---

## 8. Development Timeline (6 weeks)

| Phase | Week(s) | Lead | Deliverable |
|----|----|----|----|
| 1 — Setup (Docker, CI, skeleton, design system) | 1 | M1+M5 | Compose up; `develop`; shared components + layouts; routes split decision |
| 2 — Migrations + models | 1–2 | M1(M2/M3/M4) | All table migrations/relationships by owner; `php artisan migrate` green |
| 3 — Auth + RBAC | 2 | M1 | `/auth/*`, account, addresses, guests header pattern |
| 4 — Catalog APIs | 2–3 | M2 | catalog/facets/products/reviews endpoints + media |
| 5 — Cart/checkout/orders APIs | 3–4 | M3 | reservation lock, state machine, coupons (parallel with M4) |
| 6 — Payments/shipping/inventory APIs | 3–4 | M4 | ledger, shipment tracking, dashboard/report endpoints |
| 7 — Admin dashboard + customer site build | 4–5 | M5 + all | All views wired to live APIs; no mock data |
| 8 — Integration + E2E testing | 5 | M5 | Critical-flow smoke, cross-module fixes |
| 9 — Hardening + QA + perf | 5–6 | M1 | QA pass, security (rate-limit/throttle), final tests |
| 10 — Docs + deployment | 6 | M1 | `develop`→`main`, deployment guide, demo |

Parallelism notes: weeks 4–6, M2/M3/M4 work in parallel on APIs. M5 starts on the UI shell in week 1 and wires views as each API lands (contract-first via `API-REFERENCE.md` + `types/index.ts`).

---

## 9. Definition of Done (per member)

| Member | Done when |
|----|----|
| M1 | All auth/account/customers/settings endpoints + pages merged; login/register/password/401/403 matrix passing; branch-guard meta consistent; CI green; API-REFERENCE auth section updated |
| M2 | Catalog, product/variant/attribute CRUD, media upload, review moderation merged and tested; facets + product-detail responses final; SKU/slug uniqueness enforced |
| M3 | Cart→checkout→order pipeline with reservation lock merged and tested (no oversell, cancel restocks, coupon reclaimed); state machine rejects invalid transitions |
| M4 | Inventory ledger fully branch-labeled, payment gate flag enforced, dashboard/reports render real data, `orders.csv` export verified |
| M5 | Production build clean (`vue-tsc -b && vite build`); storefront shell + design system live; all screens wired to real APIs (no mock data); E2E checklist signed off |

Common DoD (all members): no `TODO` placeholders for fixable work, no debug logging/`dd()`/`console.log` leftovers, formatting matches `pint` for PHP / editorconfig for TS, tests pass, `API-REFERENCE.md` + `types/index.ts` updated in the PR.

---

## 10. Final Integration Checklist

- [ ] `docker compose up -d` — app, scheduler, frontend, nginx, mysql, redis, phpmyadmin all healthy
- [ ] `docker compose exec app php artisan migrate --seed` on a fresh volume; no errors
- [ ] Seed admin can log in (`/admin`), customer register/login/logout works
- [ ] `docker compose exec app php artisan test` — full suite green (target 167+ tests / 729+ assertions)
- [ ] `docker compose exec frontend npm run build` — clean
- [ ] Storefront: home → facets → product detail → variant select → add to cart → cart totals (tax 10%) → checkout (address/shipping/coupon/payment) → success → tracking stepper
- [ ] Reserverations: begin checkout → confirm deducts; cancel/expiry releases; admin cancel releases
- [ ] Coupon: validate, apply, usage counts, reclaim on cancel
- [ ] Admin: dashboard KPIs reflect real orders; product create (variants+inventory) → appears in catalog; order transition fires notification + tracking; review approve/reject updates `rating_avg`
- [ ] Inventory: `available = quantity - reserved`; ledger rows carry `shop_id`; low/out buckets distinct
- [ ] Payments: sandbox confirm works; `PAYMENT_MODE=production` rejects online confirm (422); COD reconciliation marked
- [ ] AuthZ sweeps: customer token → admin endpoints = 403; non-owner order/address/review = 404/403; branch A → branch B data = 403 (policies)
- [ ] Security: `APP_DEBUG=false` on staging; rate limit on auth/checkout; generic 500 JSON; no secrets in repo
- [ ] Response 425-ms warm (OPcache + nginx gzip + Redis caches active); no N+1 on catalog/orders/admin
- [ ] Cross-browser (desktop/tablet/mobile), EN+KM i18n parity, dark mode
- [ ] API-REFERENCE, ARCHITECTURE, FRONTEND docs match the shipped code

---

## Shared Tasks (cross-cutting)

| Shared task | Owner | Concrete work |
|----|----|----|
| Database ERD | M1 | Keep `docs/ARCHITECTURE.md` ERD current; approve migration changes |
| API documentation | All + M1 | `docs/API-REFERENCE.md` updated in each PR; M1 reviews for consistency |
| Authentication (Sanctum) | M1 | Token issuance/rotation, guest `X-Session-Id` convention, 401 global handling |
| Docker setup | M1 | `docker-compose.yml`, `docker/nginx`, `docker/php`; everyone uses containers only |
| Environment configuration | M1 | `.env` templates, `VITE_API_URL`, `PAYMENT_MODE`, `APP_DEBUG` (never commit `.env`) |
| UI design system | M5 | Design tokens, shared components, Bootstrap classes, icons, spacing/a11y |
| Error handling | All | Uniform 400/401/403/404/422/429/500 rendering; production error masking (done via `bootstrap/app.php`) |
| Security | All + M1 | Throttling, CORS restriction, mass-assignment guards, IDOR tests, upload validation |
| Validation | All | Form Requests server-side + mirrored client messages |
| Testing | All | Module tests per owner (see §2); M5 runs the E2E suite; `TestCase::setUp` flush cache |
| Responsive design | M5 | Storefront + admin mobile pass |
| Deployment | M1 (+M5) | `develop`→`main`, staging env, `storage:link`, migrate on deploy, docs |
| Final documentation | M1 | ARCHITECTURE/FRONTEND/DEVELOPMENT refresh |
| Presentation | M5 | Demo deck + walkthrough video |

---

## Development Order (10 phases)

1. **Phase 1 — Project setup**: Docker up, CI (lint + test + build), skeleton, shared UI shell. *(M1 + M5)*
2. **Phase 2 — Database**: Migrations + models by owner; ERD sign-off. *(M1–M4)*
3. **Phase 3 — Authentication**: Sanctum auth, RBAC, guest session header, account/addresses. *(M1)*
4. **Phase 4 — Backend APIs**: Catalog (M2), cart/checkout/orders/coupons (M3), payment/shipping/inventory/dashboard (M4) in parallel.
5. **Phase 5 — Admin Dashboard**: Pages wired to real APIs (each owner) + shared `AdminDataTable`.
6. **Phase 6 — Customer Website**: Storefront views + layouts on live APIs. *(M5 with M2/M3)*
7. **Phase 7 — Cart & Checkout integration**: reservation lock UX, multi-step checkout, tracking stepper. *(M3 + M5)*
8. **Phase 8 — Payment & Shipping**: payment gate flag, shipments, COD, reports. *(M4)*
9. **Phase 9 — Testing**: full backend suite + E2E critical flow, QA + perf pass. *(all; M5 leads)*
10. **Phase 10 — Deployment**: `develop`→`main`, staging verify, final docs, demo. *(M1)*