# Architecture

This document describes the architectural design of the E-Commerce System: the layered application architecture, the database schema and relationships, and the core business-logic engine that powers transactional correctness.

---

## 1. System Overview

The system is a classic **client–server** application split into two deployable units:

- **Frontend** — Vue 3 + TypeScript SPA (`:5173`). Talks to the backend exclusively over a JSON REST API.
- **Backend** — Laravel REST API (`:8000`, proxied by Nginx). Owns all business logic, data, and security.

Both are containerized via Docker Compose, with MySQL 8 (primary store), Redis (cache/session/queue), and phpMyAdmin (admin tooling).

### Layered architecture (backend)

Laravel enforces a clean separation of concerns:

```
routes/api.php
      │
      ▼
Controllers (HTTP layer) ──► Requests (validation)
      │
      ▼
Services (business logic)
      │
      ▼
Models (Eloquent ORM) ──► Resources (JSON serialization)
      │
      ▼
MySQL / Redis
```

| Layer | Directory | Responsibility |
| --- | --- | --- |
| HTTP | `App\Http\Controllers\Api` | Handle HTTP verbs, bind route params, delegate to services |
| Validation | `App\Http\Requests` | Validate incoming payloads (Form Requests) |
| Business logic | `App\Services` | Encapsulate non-trivial domain rules (checkout, inventory, coupons, reviews) |
| Persistence | `App\Models` | Eloquent models: relationships, casts, scopes |
| Serialization | `App\Http\Resources` | Shape API responses, hide internal fields |
| Middleware | `App\Http\Middleware` | Auth + RBAC (`admin` middleware) |

Keeping complex logic in **Service classes** (as prescribed in `AGENTS.md`) keeps controllers thin and testable.

---

## 2. Database Schema & Relationships

The schema is created via migrations in `backend/database/migrations`. It follows an **EAV (Entity-Attribute-Value)** pattern for product variants plus relational tables for commerce entities.

### 2.1 Entity relationship diagram (core)

```
[ users ] 1─────────< [ addresses ]
   │
   ├─ 1────────────< [ orders ] 1──< [ order_items ] >──1 [ product_variants ]
   ├─ 1────────────< [ carts ] 1──< [ cart_items ] >──1 [ product_variants ]
   ├─ 1────────────< [ wishlists ] 1─< [ wishlist_items ] >─1 [ products ]
   └─ 1────────────< [ reviews ]

[ products ] 1─────< [ product_variants ] 1───< [ inventories ]
   │                      │                    │
   ├─────< [ categories ] │                    └──< [ inventory_transactions ]
   └─────< [ brands ]     └──< [ variant_attribute_values ] >─1 [ attribute_values ]

   [ attributes ] 1──< [ attribute_values ]

[ orders ] 1──1 [ payments ] 1──< [ payment_transactions ]
[ orders ] 1──1 [ shipments ] ──1 [ shipping_methods ]
[ coupons ] 1──< [ coupon_usages ] >──1 [ orders ]
[ users ] 1──< [ notifications ]
```

### 2.2 Module breakdown

**User & Auth Module**
- `users` — customers and admins; role field drives RBAC. Has one `personal_access_tokens` per session (Sanctum).
- `addresses` — a user's saved shipping/billing addresses; one may be marked default.

**Product Catalog & EAV Module**
- `categories` (1:N) → `products`
- `brands` (1:N) → `products`
- `products` (1:N) → `product_images`
- `products` (1:N) → `product_variants`
- `attributes` (1:N) → `attribute_values` (e.g. *Color* → Black/White/Blue)
- `product_variants` (1:N) → `variant_attribute_values` (N:1) `attribute_values`
  - A variant is uniquely resolved by the combination of its `attribute_values`, e.g. **Size L + Color Blue**.

**Inventory & Cart Module**
- `product_variants` (1:1) → `inventories`
- `inventories` (1:N) → `inventory_transactions` (full stock ledger)
- `users` (1:1) → `carts` (1:N) → `cart_items` (N:1) `product_variants`
- `users` (1:1) → `wishlists` (1:N) → `wishlist_items` (N:1) `products`

**Order Processing & Fulfillment Module**
- `users` (1:N) → `orders` (1:N) → `order_items` (N:1) `product_variants`
- `orders` (1:1) → `payments` (1:N) → `payment_transactions`
- `orders` (1:1) → `shipments` (N:1) `shipping_methods`
- `coupons` (1:N) → `coupon_usages` (N:1) `orders`
- `users` (1:N) → `reviews` (N:1) `products`
- `users` (1:N) → `notifications`

---

## 3. Core Business Logic Engine

The important domain rules are implemented in `App\Services` and are the heart of the application.

### 3.1 Authentication & authorization

- **Auth** — Laravel Sanctum issues bearer token(s). The frontend stores `access_token` in `localStorage` and attaches it as `Authorization: Bearer <token>` (see `frontend/src/api/client.ts`).
- **RBAC** — the `admin` middleware guards the `/admin` prefix. Regular tokens authenticate customers; only the `admin` role can access admin routes (already wired in `routes/api.php:103-105`).

### 3.2 Dynamic variant filtering (`CatalogService`)

The shop resolves the *exact* `product_variant` a customer is choosing by mapping selected attributes. When the customer selects **Size: L** and **Color: Blue**, the service joins `variant_attribute_values` across the chosen `attribute_value` combinations and resolves the concrete `product_variant_id`, exposing its price, SKU, and live stock.

### 3.3 Inventory reservation lock (`InventoryService` / `CheckoutService`)

Stock integrity is protected with an **atomic reservation** flow:

1. When checkout **begins**, the requested quantity is **reserved** in `inventories` (increasing `reserved_quantity`, decreasing available).
2. Reservations are held for a bounded window (~15 minutes).
3. On **payment success**, reserved qty is **permanently deducted** and a stock-out `inventory_transaction` is recorded.
4. On **failure/expiry/cancel**, the reservation is **released** back to available stock.

Every stock change is appended to `inventory_transactions`, giving a full audit ledger.

### 3.4 Order lifecycle state machine (`OrderService`)

Orders follow a strict, validated progression:

```
Pending → Confirmed → Processing → Shipped → Delivered
```

Transitions are enforced server-side (the `orders/{order}/transition` admin endpoint), so invalid jumps are rejected.

**Snapshotting rule:** at checkout time the item's `title`, `unit price`, and variant details are copied **directly into `order_items`**. This guarantees that later catalog price/title changes never alter historic order logs.

### 3.5 Promotion & discount engine (`CouponService`)

Coupon validation applies rules in a fixed order:

1. **Expiry date** check
2. **Usage limit** check (per coupon via `coupon_usages`)
3. **Minimum order amount** check
4. Apply **percentage** or **fixed-amount** discount to the subtotal

A successful application produces a `coupon_usage` record linked to the order.

### 3.6 Verified review authorization (`ReviewService`)

Reviews are **moderated** and **verified**. Before allowing creation, the service enforces a DB check that the reviewing `user_id` has an `orders` record containing the target product with status = `Delivered`. Only then is a review permitted (and it can later be approved/rejected by an admin).

### 3.7 Reports (`DashboardService`, `AdminReportController`)

Admin reporting aggregates key metrics (revenue, orders, customers) from the core tables and exposes an exportable `orders.csv` report. Charts on the frontend consume these aggregates via Chart.js.

---

## 4. Frontend Architecture (summary)

See [`FRONTEND.md`](./FRONTEND.md) for full detail. The SPA is organized into three layout roots defined in `frontend/src/router/index.ts`:

- **StoreLayout** (`/`) — public storefront: home, shop, product, cart, checkout, order success, order tracking.
- **AccountLayout** (`/account`) — authenticated customer portal: dashboard, orders, wishlist, addresses, profile, notifications, reviews, password.
- **AdminLayout** (`/admin`) — admin panel (route guard enforces `admin: true` meta): dashboard, products, orders, customers, coupons, reviews, categories, brands, shipping.

State is managed with Pinia (`cart`, `wishlist`, `ui` stores). API access goes through a single Axios instance with request/response interceptors for auth + 401 handling.
