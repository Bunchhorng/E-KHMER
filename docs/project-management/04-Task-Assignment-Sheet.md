# 4. Task Assignment Sheet

> Who does what. Newest on top. Duplicates the module ownership in `docs/TEAM-WORK-BREAKDOWN.md` for quick tracking.

## 4.1 Open / In-progress tasks

| # | Task | Owner | Module | Status | PR/branch | Start | Due | Notes |
|---|------|-------|--------|--------|-----------|-------|-----|-------|
| | (add rows) | | | To-do / In progress / In review / Done | | | | |

## 4.2 Assigned modules (baseline)

| # | Module | Owner | Backend files | Frontend files | Migrations |
|---|--------|-------|---------------|----------------|------------|
| 1 | Authentication | M1 | `AuthController`, auth Form Requests, `UserResource` | `views/auth/*`, `stores/auth.ts`, `api/auth.ts` | `users`, `personal_access_tokens` |
| 2 | Account & Profile | M1 | `AccountController`, `UpdateProfileRequest` | account views, `api/account.ts` | — |
| 3 | Addresses | M1 | `AddressController`, `AddressRequest` | `AddressesView`, `AddressFormView` | `addresses` |
| 4 | Notifications | M1 | `AccountController@notifications`, `AdminNotificationController` | account/admin notification views | `notifications` |
| 5 | Customers (admin) | M1 | `AdminCustomerController` | `AdminCustomersView`, `AdminCustomerDetailView` | — |
| 6 | Settings | M1 | `SettingsController`, `Setting` | `AdminSettingsView` | `settings` |
| 7 | Shops / Org | M1 | `AdminShopController`, `ShopController`, `ShopPolicy` | shop CRUD pages | `shops`, `shop_users` |
| 8 | Products | M2 | `CatalogController`, `AdminProductController`, `CatalogService` | `ShopView`, `ProductDetailView`, `AdminProductsView`, `AddProductView` | `products`, `product_images`, `product_variants`, `variant_attribute_values` |
| 9 | Categories | M2 | `CategoryController`, `AdminCategoryController` | `AdminCategoriesView`, `CategoryNavBar` | `categories` |
| 10 | Brands | M2 | `BrandController`, `AdminBrandController` | `AdminBrandsView` | `brands` |
| 11 | Attributes / Variants | M2 | `AttributeController`, `AdminProductController` | product variant selector | `attributes`, `attribute_values`, `variant_attribute_values` |
| 12 | Media | M2 | `AdminMediaController`, `MediaUploadService` | image upload UIs | `product_images` |
| 13 | Reviews | M2 | `ReviewController`, `AdminReviewController`, `ReviewService` | `ReviewsView`, `AdminReviewsView`, `StarRating` | `reviews`, `review_images` |
| 14 | Cart | M3 | `CartController`, `CartService` | `CartView`, `CartDrawer`, `stores/cart.ts` | `carts`, `cart_items` |
| 15 | Wishlist | M3 | `WishlistController` | `WishlistView`, `stores/wishlist.ts` | `wishlists`, `wishlist_items` |
| 16 | Checkout | M3 | `CheckoutController`, `CheckoutService`, `CheckoutRequest` | `CheckoutView`, `OrderSuccessView` | — (uses orders + inventories) |
| 17 | Orders | M3 | `OrderController`, `AdminOrderController`, `OrderService` | `OrdersView`, `OrderDetailView`, `AdminOrdersView`, `OrderTrackingView` | `orders`, `order_items`, `order_status_histories` |
| 18 | Coupons | M3 | `CouponController`, `AdminCouponController`, `CouponService` | `AdminCouponsView`, `CouponFormView`, `api/coupons.ts` | `coupons`, `coupon_usages` |
| 19 | Payments | M4 | `AdminPaymentController`, payment gate config | `AdminPaymentsView` | `payments`, `payment_transactions` |
| 20 | Shipping | M4 | `ShippingMethodController`, `AdminShippingMethodController`, `AdminShipmentController` | `AdminShippingMethodsView`, `AdminShipmentsView` | `shipping_methods`, `shipments` |
| 21 | Inventory | M4 | `AdminInventoryController`, `InventoryService` | `AdminInventoryView` | `inventories`, `inventory_transactions` |
| 22 | Dashboard | M4 | `DashboardController`, `DashboardService` | `AdminDashboardView`, charts | — |
| 23 | Reports | M4 | `AdminReportController` | `AdminReportsView`, `api/admin.ts` | — |
| 24 | UI foundation | M5 | — | layouts, shared components, `router/index.ts`, i18n, theme, `api/client.ts` | — |
| 25 | E2E / QA | M5 | — | test checklist, console/network sweeps | — |

## 4.3 Done

| # | Task | Owner | Status | Verified by | Date |
|---|------|-------|--------|-------------|------|
| | (add rows when complete) | | | | |