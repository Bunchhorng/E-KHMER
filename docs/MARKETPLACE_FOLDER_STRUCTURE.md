# Marketplace Folder Structure

This is the target organisation for the existing E-KHMER codebase. It is a map for checking and adding marketplace features safely; current working screens should be moved only as part of the feature that changes them.

```text
E-Ecommerce/
├── backend/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── Admin/                 # Super Admin marketplace controls
│   │   │   │   │   ├── AdminShopController.php
│   │   │   │   │   ├── AdminShopApplicationController.php
│   │   │   │   │   ├── AdminShopOrderController.php
│   │   │   │   │   └── AdminMarketplaceReportController.php
│   │   │   │   ├── Seller/                # Shop owner / shop-admin APIs
│   │   │   │   │   ├── SellerDashboardController.php
│   │   │   │   │   ├── SellerProductController.php
│   │   │   │   │   ├── SellerOrderController.php
│   │   │   │   │   ├── SellerInventoryController.php
│   │   │   │   │   └── SellerStaffController.php
│   │   │   │   ├── ShopController.php     # Public shop storefront APIs
│   │   │   │   └── CheckoutController.php # Parent order + shop-order split
│   │   │   ├── Requests/
│   │   │   │   ├── ShopApplicationRequest.php
│   │   │   │   ├── ShopStaffRequest.php
│   │   │   │   └── Seller/
│   │   │   └── Resources/
│   │   │       ├── ShopResource.php
│   │   │       ├── ShopOrderResource.php
│   │   │       └── MarketplaceDashboardResource.php
│   │   ├── Models/
│   │   │   ├── Shop.php
│   │   │   ├── ShopUser.php
│   │   │   ├── ShopOrder.php
│   │   │   └── ShopApplication.php
│   │   ├── Policies/
│   │   │   ├── ShopPolicy.php
│   │   │   └── ShopOrderPolicy.php
│   │   └── Services/
│   │       ├── ShopApplicationService.php
│   │       ├── ShopOrderService.php
│   │       └── MarketplaceReportService.php
│   ├── database/migrations/
│   │   └── marketplace/                  # Optional naming group for new marketplace migrations
│   └── tests/Feature/Api/
│       ├── Marketplace/
│       │   ├── ShopApplicationTest.php
│       │   ├── ShopOrderTest.php
│       │   └── ShopIsolationTest.php
│       └── Seller/
│           ├── SellerProductTest.php
│           └── SellerStaffTest.php
│
├── frontend/src/
│   ├── api/
│   │   ├── admin.ts                     # Existing Super Admin calls
│   │   ├── seller.ts                    # Shop-owner / staff calls
│   │   ├── shops.ts                     # Public shop marketplace calls
│   │   └── shop-orders.ts
│   ├── layouts/
│   │   ├── AdminLayout.vue              # Super Admin navigation
│   │   └── SellerLayout.vue             # Shop owner / shop-admin navigation
│   ├── views/
│   │   ├── admin/
│   │   │   ├── marketplace/
│   │   │   │   ├── ShopsView.vue
│   │   │   │   ├── ShopApplicationsView.vue
│   │   │   │   ├── ShopOwnersView.vue
│   │   │   │   ├── MarketplaceOrdersView.vue
│   │   │   │   ├── ShopOrdersView.vue
│   │   │   │   ├── MarketplaceReportsView.vue
│   │   │   │   └── AuditLogsView.vue
│   │   │   └── ...existing catalog, fulfilment, marketing screens
│   │   ├── seller/
│   │   │   ├── SellerDashboardView.vue
│   │   │   ├── SellerProductsView.vue
│   │   │   ├── SellerOrdersView.vue
│   │   │   ├── SellerInventoryView.vue
│   │   │   ├── SellerStaffView.vue
│   │   │   └── SellerSettingsView.vue
│   │   └── marketplace/
│   │       ├── ShopsView.vue
│   │       ├── ShopDetailView.vue
│   │       └── ShopApplicationView.vue
│   ├── components/
│   │   └── marketplace/
│   │       ├── ShopBadge.vue
│   │       ├── ShopOrderGroup.vue
│   │       └── ShopStatusBadge.vue
│   └── stores/
│       ├── auth.ts
│       └── seller.ts
│
└── docs/
    ├── MARKETPLACE_FOLDER_STRUCTURE.md  # This map
    ├── MARKETPLACE_MIGRATION_PLAN.md
    └── MARKETPLACE_UPGRADE_REPORT.md
```

## Super Admin page folders

```text
frontend/src/views/admin/marketplace/
├── ShopsView.vue
├── ShopApplicationsView.vue
├── ShopOwnersView.vue
├── MarketplaceOrdersView.vue
├── ShopOrdersView.vue
├── MarketplaceReportsView.vue
└── AuditLogsView.vue
```

Keep global **Brands**, **Categories**, and platform settings in their existing admin folders. They are not owned by an individual shop.
