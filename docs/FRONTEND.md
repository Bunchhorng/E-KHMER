# Frontend Documentation

This document describes the Vue 3 + TypeScript frontend: its structure, routing, state management, API layer, and screens.

## 1. Tech Stack

- **Vue 3** — Composition API with `<script setup>`
- **TypeScript** — strict typing for props, emits, and API responses
- **Vite** — build tooling / dev server
- **Vue Router** — SPA routing
- **Pinia** — global state
- **Axios** — API client
- **Tailwind CSS** — styling
- **SweetAlert2** — dialogs
- **Chart.js** — admin charts
- **i18n** — English (`en`) and Khmer (`km`) locales

## 2. Project Layout (`frontend/src`)

```
src/
├── api/client.ts            # Axios instance + interceptors
├── assets/                  # static assets
├── components/              # shared + admin components
│   ├── admin/               # Admin UI components
│   │   └── charts/          # RevenueChart, OrderStatusChart, SalesCategoryChart
│   ├── AppFooter.vue
│   ├── AppHeader.vue
│   ├── BaseBadge.vue, BaseModal.vue, BasePagination.vue
│   ├── CartDrawer.vue
│   ├── CategoryNavBar.vue
│   ├── DataTableSkeleton.vue, EmptyState.vue
│   ├── LanguageSwitcher.vue
│   ├── PdpSkeleton.vue, ProductCard.vue, ProductGridSkeleton.vue, ProductRail.vue
│   ├── QuantityCounter.vue, StarRating.vue, StatusTag.vue
│   └── plugins/i18n.ts      # i18n setup
├── data/mock.ts             # Mock data (coupons, etc.)
├── layouts/                 # StoreLayout, AccountLayout, AdminLayout
├── locales/                 # en.json, km.json
├── router/index.ts          # Route definitions + guards
├── stores/                  # cart.ts, wishlist.ts, ui.ts
├── types/index.ts           # Shared TypeScript types
├── utils/format.ts          # Formatting helpers
└── views/                   # Page components
```

## 3. Routing & Layouts

Defined in `frontend/src/router/index.ts`. Three top-level layout roots:

| Path root | Layout | Auth | Description |
| --- | --- | --- | --- |
| `/` | `StoreLayout` | Public | Public storefront |
| `/account` | `AccountLayout` | `requiresAuth` | Customer portal |
| `/admin` | `AdminLayout` | `requiresAuth` + `admin` | Admin panel |

Route `meta` fields: `title` (document title), `requiresAuth`, `admin`. A `beforeEach` guard sets the document title.

### Store routes (`/`)

| Path | Name | View |
| --- | --- | --- |
| `/` | home | `HomeView` |
| `/shop` | shop | `ShopView` |
| `/product/:slug` | product-detail | `ProductDetailView` |
| `/cart` | cart | `CartView` |
| `/checkout` | checkout (auth) | `CheckoutView` |
| `/order/success/:orderId` | order-success | `OrderSuccessView` |
| `/order/tracking/:orderId` | order-tracking | `OrderTrackingView` |

### Account routes (`/account`)

`account-dashboard`, `account-orders`, `account-wishlist`, `account-addresses`, `account-profile`, `account-notifications`, `account-reviews`, `account-password`.

### Admin routes (`/admin`)

`admin-dashboard`, `admin-products`, `admin-product-create`, `admin-orders`, `admin-customers`, `admin-coupons`, `admin-reviews`, `admin-categories`, `admin-brands`, `admin-shipping`.

## 4. API Layer (`api/client.ts`)

A single Axios instance is the only entry point for HTTP:

```ts
export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  timeout: 15000,
  headers: { Accept: 'application/json' }
})
```

- **Request interceptor** — attaches `Authorization: Bearer <access_token>` from `localStorage`.
- **Response interceptor** — on `401`, clears stored tokens.

Configure the backend URL with `VITE_API_URL` in `frontend/.env` (e.g. `VITE_API_URL=http://localhost:8000/api`).

## 5. State Management (Pinia)

| Store | Purpose |
| --- | --- |
| `stores/cart.ts` | Cart items, applied coupon, computed subtotal/discount/tax/total; persists to `localStorage` under `ekhmer_cart`. Includes coupon validation logic (percentage/fixed, min-order). |
| `stores/wishlist.ts` | Customer wishlist. |
| `stores/ui.ts` | Shared UI state (drawers, toasts, etc.). |

## 6. Screens & Features

### Storefront
- **HomeView** — hero, featured product rail, category nav.
- **ShopView** — product listing with a **dynamic filter sidebar** (facets from `/catalog/facets`) and sorting.
- **ProductDetailView** — gallery, **variant selector** (attributes → concrete variant), zoom, **stock badge**, add-to-cart.
- **CartView** — full cart page with quantity controls and coupon application.
- **CheckoutView** — multi-step checkout: **address → shipping → coupon → payment**.
- **OrderSuccessView** — confirmation after checkout.
- **OrderTrackingView** — order status stepper (Pending → Confirmed → Processing → Shipped → Delivered).

### Account portal (`/account`, requires auth)
- **DashboardView** — account summary.
- **OrdersView** — order history.
- **WishlistView** — saved products.
- **AddressesView** — address manager.
- **ProfileView** — edit profile.
- **NotificationsView** — notifications.
- **ReviewsView** — my reviews.
- **ChangePasswordView** — password change.

### Admin panel (`/admin`)
- **AdminDashboardView** — KPI cards (revenue/orders/customers), revenue chart, recent orders, low-stock alerts.
- **AdminProductsView** / **AddProductView** — product & variant CRUD.
- **AdminCategoriesView**, **AdminBrandsView** — taxonomy manager.
- **AdminOrdersView** — order pipeline / status transition.
- **AdminCustomersView** — customer list.
- **AdminCouponsView** — coupon creator/manager.
- **AdminReviewsView** — review moderation queue (approve/reject).
- **AdminShippingMethodsView** — shipping config.

Shared building blocks include `ProductCard`, `CartDrawer`, `StatusTag`, `StarRating`, `BaseModal`, `BasePagination`, and skeleton loaders (`ProductGridSkeleton`, `PdpSkeleton`, `DataTableSkeleton`, `EmptyState`) plus admin `AdminDataTable` and chart components.

## 7. Globalization

`plugins/i18n.ts` wires `vue-i18n` with locales in `src/locales/{en,km}.json`. A `LanguageSwitcher` component toggles the active language.
