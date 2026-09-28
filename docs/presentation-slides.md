# Presentation Slides — Multi-Branch E-Commerce Web Application

> **Version 2 — rewritten to match the actual codebase.**
> Every statement below was verified against the repository, not assumed.
> Facts were confirmed by reading `backend/`, `frontend/`, `docker-compose.yml`,
> and by executing `docker compose exec app php artisan test` in Docker.
>
> **Source of truth for the numbers in this deck:**
>
> | Claim | How it was verified |
> |---|---|
> | 167 tests / 729 assertions passing | `docker compose exec -T app php artisan test` → `Tests: 167 passed (729 assertions)`, 215.51 s |
> | 115 API routes (63 under `/api/admin`, 9 shop routes) | `php artisan route:list --path=api --except-vendor --json` |
> | 7 Docker services | `docker-compose.yml` (app, scheduler, nginx, mysql, redis, phpmyadmin, frontend) |
> | Response-time figures | `docs/Performance-Report.md` (measured with `curl` against the Nginx container) |
> | QA findings | `docs/QA-REPORT.md` (real reproductions with order numbers and HTTP codes) |
> | Multi-branch status | `docs/multi-shop-audit-and-p1-report.md` |

---

## Corrections applied in this version

The previous draft of this deck did not match the project. The following were
wrong and are now fixed. **Do not revert these.**

| Previous draft claimed | Actual project |
|---|---|
| "JWT auth with Refresh/Access token rotation" | **Laravel Sanctum** personal access tokens. There is **no** refresh endpoint and **no** token rotation — `config/sanctum.php` has `expiration => null`. Do not claim rotation. |
| "Bootstrap 5 + Bootstrap Icons" | **Tailwind CSS 3.4.19** (via PostCSS). `package.json` contains no Bootstrap at all. Icons are **lucide-vue-next**. |
| "Multi-Shop / multi-**vendor** marketplace" where independent sellers each run a shop | **Multi-branch of one brand.** `docs/multi-shop-plan.md` explicitly decided: *"'Shop' = physical/regional branches of ONE brand, unified under a single Super Admin."* There is no vendor registration and no independent seller onboarding. |
| Four working roles: Super Admin, Shop Owner, Shop Staff, Customer | Schema and policies support `owner` / `manager` / `staff` via `shop_users`, **but `shop_users` has 0 rows and the `admin` middleware only admits `role = admin`.** Branch roles are enforced in code but cannot yet be logged into. Slide 5 states this explicitly. |
| A "Shop Dashboard" | **No shop-owner dashboard exists in the frontend.** `AdminLayout` is the only admin area. Backend has `GET /api/admin/shops` CRUD + status changes. |
| "Commission" and "Payouts" as features | **Not implemented.** Only a reserved `shops.commission_rate` column exists. There is no ledger and no payout flow. |
| Public `/shops` and `/shop/:slug` pages in Vue | **Not implemented in the frontend.** The 3 backend routes exist and are tested; no Vue route consumes them. |
| "Recharts" | **Chart.js 4 + vue-chartjs 5**, in 5 chart components. |
| "JWT", "Recommendations", "AI" as built | These belong on the **Future Work** slide, not the feature slides. |

Also removed: any implication of a Shop Staff login, payouts, commission
settlement, and per-shop settings. All of these are *planned*, not built.

---

## Project facts to speak from (memorise these)

- **Name:** E-KHMER — Multi-Branch E-Commerce Web Application
- **Backend:** Laravel 13 (`^13.17`) on PHP 8.3, REST API, Sanctum 4
- **Frontend:** Vue 3.5 + TypeScript 6 + Vite 8, Tailwind CSS 3.4, Pinia 4, Axios 1.20, Chart.js 4
- **Data:** MySQL 8 (host port 3307), Redis (6379)
- **Edge:** Nginx (8000) → php-fpm (`app:9000`)
- **Frontend dev server:** Vite on 5174, proxies `/api` → `http://nginx`
- **Third-party PHP package:** only `barryvdh/laravel-dompdf` (PDF receipts/reports)
- **i18n:** English + Khmer (`km`), 19 message namespaces
- **Theme:** light/dark toggle, class-based, FOUC-safe via inline pre-paint script
- **Order number format:** `SV-{year}-{6 digits}`, e.g. `SV-2026-000280`
- **Reservation window:** 15 minutes, expired by a scheduler running every minute
- **Tax:** flat 10 % on (subtotal − discount)
- **Demo login:** `admin@ekhmer.dev` / `password`; customers e.g. `olivia.bennett@example.com` / `password`

---

# SLIDE 1 — Title

**On slide**
```
Multi-Branch E-Commerce Web Application
E-KHMER

Student Name        ______________
University          ______________
Faculty / Dept.     ______________
Lecturer            ______________
Academic Year       ______________
```

**Design note:** thin blue rule under the title. No logo — a simple
line-art storefront glyph in blue is enough. Do not use a stock photo.

**Speaker notes (30 s)**
> "Good morning. My project is a multi-branch e-commerce web application, which I
> call E-KHMER. It is a full-stack system where a single retail brand operates
> several physical branches through one shared platform. Over the next eighteen
> slides I will cover the problem, the architecture, the main business rules, and
> the measured test and performance results. My name is ___ and my supervisor is ___."

---

# SLIDE 2 — Project Introduction

**On slide**
- A **web-based retail platform** where one brand operates **multiple branches**
- Full **customer storefront** + **central administration dashboard**
- Shared catalogue; **independent stock and orders per branch**
- Buyers can hold products from **different branches in one cart** and pay once

**Speaker notes (40 s)**
> "The system has two sides. On the customer side there is a normal online shop:
> browse, filter, add to cart, apply a coupon, check out, track the order, and
> leave a review. On the admin side there is a dashboard for the whole company.
> The important idea is that this is not one warehouse. It is one brand with
> several branches, and each branch keeps its own stock. A customer can put items
> from branch A and branch B into the same cart and pay for them in a single
> transaction, while the system still records which branch fulfils which line."

---

# SLIDE 3 — Problem Statement

**On slide**
| Problem | Why it hurts |
|---|---|
| One catalogue, many branches | No way to know which branch holds which stock |
| Stock tracked globally | Overselling when two branches sell the same SKU |
| Orders not attributable | A branch cannot fulfil, ship, or report on its own sales |
| No permission layer per branch | Staff could read or edit another branch's data |
| Heavy catalogue | Variants, images, categories, brands are painful to manage |
| No verified reviews | Fake reviews damage trust |
| Weak order control | Customers could skip status steps; refunds corrupt data |

**Speaker notes (40 s)**
> "A traditional single-store system assumes one catalogue and one inventory. The
> moment you add a second branch it breaks. Two branches can each hold twenty
> units of the same product, and a global stock counter would let the platform
> sell forty. Orders also lose meaning: the branch does not know what it must
> pick, pack, and ship, and it cannot be held responsible. On top of that there
> was no per-branch permission boundary, so staff could reach data they should
> not see. These seven problems drove the design."

---

# SLIDE 4 — Project Objectives

**On slide**
1. Build a **branch-aware** e-commerce platform on one shared catalogue
2. Guarantee **stock isolation** between branches — no overselling across branches
3. Enforce **server-side authorisation** on every branch-scoped operation
4. Implement the full commerce flow: cart → coupon → checkout → payment → shipping → tracking → review
5. Provide a **central admin dashboard** with analytics, PDF/CSV exports
6. Support **15-minute stock reservation** so unpaid orders never hold stock forever
7. Cover the system with an **automated API test suite**
8. Keep the stack **modern, containerised, and reproducible** with Docker

**Speaker notes (35 s)**
> "Eight objectives. The first three are about correctness across branches. The
> fourth is the complete commerce pipeline. The fifth is giving management real
> visibility — charts, reports, and downloadable PDFs. The sixth is a subtle but
> important detail I will explain later: when checkout begins we reserve stock for
> fifteen minutes, so a customer who abandons payment does not permanently block
> the item. Seven is about proving it works, and eight is about delivering it the
> same way every time."

---

# SLIDE 5 — Users, Roles & Current Implementation Status

**On slide**
```
                    ┌──────────────────────┐
                    │   SUPER ADMIN        │  role = "admin"
                    │  Full platform scope │
                    └──────────┬───────────┘
                               │  manages all branches
        ┌──────────────────────┼──────────────────────┐
        ▼                      ▼                      ▼
 ┌─────────────┐        ┌─────────────┐        ┌─────────────┐
 │ Branch A    │        │ Branch B    │        │ Branch C    │
 │ owner       │        │ manager     │        │ staff       │
 │ manager     │        │ staff       │        │ (pivot role)│
 │ staff       │        └─────────────┘        └─────────────┘
 └─────────────┘
        ▲
        │  browses · carts · pays · reviews
 ┌──────────────────────────────────┐
 │          CUSTOMER                │  role = "customer"
 └──────────────────────────────────┘
```

**Status table — show this, it is the honest part:**

| Role | Backend | Frontend login | Status |
|---|---|---|---|
| Super Admin (`users.role = admin`) | ✅ full | ✅ `/admin/*` | **Working** |
| Customer (`users.role = customer`) | ✅ full | ✅ storefront + `/account/*` | **Working** |
| Branch owner / manager / staff (`shop_users`) | ✅ enforced by 8 policies | ❌ none | **Backend only, not yet exposed** |

**Speaker notes (50 s)**
> "There are two roles that work end to end today: the Super Admin, who sees
> everything, and the Customer. The third layer — branch owner, manager, and
> staff — is modelled in the database through a `shop_users` membership table and
> is enforced in code by eight authorisation policies. I want to be precise here:
> that layer is enforced in the backend and covered by tests, but no branch user
> can sign in yet, because the admin middleware still admits only the global
> admin role. So the data model and the security rules are finished; the branch
> staff interface is the next piece of work."

---

# SLIDE 6 — Multi-Branch Architecture

**On slide**
```
                    ┌───────────────────────────────┐
                    │        SUPER ADMIN            │
                    │  one catalogue · one brand    │
                    └───────────────┬───────────────┘
                                    │
      ┌─────────────────────────────┼─────────────────────────────┐
      ▼                             ▼                             ▼
┌───────────┐                ┌───────────┐                 ┌───────────┐
│  BRANCH A │                │  BRANCH B │                 │  BRANCH C │
│───────────│                │───────────│                 │───────────│
│ own stock │                │ own stock │                 │ own stock │
│ own orders│                │ own orders│                 │ own orders│
└───────────┘                └───────────┘                 └───────────┘
      └──────────── shared, single copy ────────────┘
              categories · brands · products · variants
```

Key design decisions (this is the intellectual core — spend time here):
- **Shared catalogue, per-branch inventory.** One `products` row, one SKU set;
  stock lives in `inventories`, keyed by `(product_variant_id, shop_id)`.
- **One order, branch-tagged lines.** No parent/sub-order split. A single
  `orders` row whose `order_items` each carry their fulfilling `shop_id`.
  This keeps existing reports and queries valid.
- **Global taxonomy.** Categories and brands are platform-wide, not per branch.
- **Non-destructive migration.** Existing rows were backfilled to a default
  branch via `php artisan shop:assign-default` — no data was deleted.

**Speaker notes (60 s)**
> "This is the core design decision, so I will explain it slowly. First, the
> catalogue is shared. We do not duplicate a product row for every branch —
> that would mean editing the same shirt description five times. Instead the
> product and its variants live once, and only the stock is per branch. A phone
> can be in branch A with twenty units and branch B with fifty, and those
> numbers are completely independent.
>
> Second, there is one order, not a parent order with sub-orders. Each order
> line simply records which branch must ship it. This is much simpler and it
> means a customer pays once, but the system can still tell branch A to pick
> only its own items. Third, the migration was non-destructive: I created a
> default branch and backfilled the existing rows into it, so no data was lost."

---

# SLIDE 7 — System Architecture & Docker

**On slide**
```
┌────────────────────┐
│  Browser           │   Vue 3 SPA (Vite dev server :5174)
└─────────┬──────────┘
          │  HTTPS/JSON,  /api proxied to http://nginx
          ▼
┌────────────────────┐
│  nginx  :8000      │   gzip, dotfile deny, try_files → index.php
└─────────┬──────────┘
          │  FastCGI  app:9000
          ▼
┌────────────────────┐
│  app               │   Laravel 13 · PHP 8.3 FPM
│  (php-fpm)         │   Routes → Controllers → FormRequests
└─────────┬──────────┘   → Services → Models/Resources
          │
    ┌─────┴─────┬──────────────┐
    ▼           ▼              ▼
┌────────┐ ┌────────┐  ┌──────────────┐
│ mysql  │ │ redis  │  │  scheduler   │
│ :3307  │ │ :6379  │  │  every 1 min │
└────────┘ └────────┘  │  expire stale│
                        │  reservations│
                        └──────────────┘
```

**Docker Compose — 7 services**

| Service | Image / build | Port | Role |
|---|---|---|---|
| `app` | `./docker/php` (php:8.3-fpm) | — | Laravel API, PHP-FPM, OPcache on |
| `scheduler` | same image | — | `php artisan schedule:work` |
| `nginx` | `nginx:alpine` | 8000 | Only public API entry point |
| `mysql` | `mysql:8.0` | 3307 | Primary datastore, `ecommerce_db` |
| `redis` | `redis:alpine` | 6379 | Cache, session, queue |
| `phpmyadmin` | `phpmyadmin:latest` | 8080 | DB inspection |
| `frontend` | `node:22-alpine` | 5174 | Vite dev server |

**Layer responsibilities**
- **Nginx** — terminates HTTP, gzip, serves nothing static itself, forwards PHP
- **Form Requests (22 classes)** — all validation lives here, never in controllers
- **Services (10 classes)** — all business logic; controllers stay thin
- **API Resources (25 classes)** — one consistent JSON shape per model
- **Scheduler** — a dedicated worker, not a cron hack

**Speaker notes (60 s)**
> "Read this from the top. The browser loads a single-page Vue application. It
> never talks to the database directly — it only speaks JSON to Nginx. Nginx is
> the only public port, and it forwards PHP requests to the FPM container over
> FastCGI on an internal network.
>
> Inside Laravel I kept three layers strictly separate. Routes are thin. Form
> Request classes do all validation — there are twenty-two of them. Service
> classes hold the business logic — there are ten, and the important ones are
> Checkout, Inventory, Order, Coupon, and Review. API Resources then shape the
> JSON so every endpoint returns the same field names.
>
> One service people often miss is the scheduler. It runs in its own container
> and wakes every minute to release stock reservations from abandoned checkouts."

---

# SLIDE 8 — Technology Stack

**On slide**

| Layer | Technology | Version |
|---|---|---|
| **Frontend framework** | Vue 3 (Composition API, `<script setup>`) | ^3.5.41 |
| **Language** | TypeScript | ~6.0.2 |
| **Build tool** | Vite | ^8.2.2 |
| **Routing** | Vue Router (lazy-loaded routes) | ^5.3.0 |
| **State** | Pinia (6 stores) | ^4.0.3 |
| **HTTP** | Axios (auth + 401 interceptors) | ^1.20.0 |
| **Styling** | **Tailwind CSS** (CSS-variable design tokens) | ^3.4.19 |
| **Icons** | lucide-vue-next | ^1.0.0 |
| **Charts** | Chart.js + vue-chartjs | ^4.5.1 |
| **i18n** | vue-i18n (English / Khmer) | ^11.4.10 |
| **Backend** | Laravel + PHP | ^13.17 / ^8.3 |
| **Auth** | Laravel Sanctum (bearer tokens) | ^4.0 |
| **Database** | MySQL | 8.0 |
| **Cache / Session / Queue** | Redis | alpine |
| **Web server** | Nginx | alpine |
| **PDF** | barryvdh/laravel-dompdf | ^3.1 |
| **Containerisation** | Docker + Docker Compose | 7 services |

**Why this stack (one line each):**
- Vite + TS → type safety across 37 views and 18 API modules
- Pinia → cart, auth, wishlist, theme, locale, UI flags in one predictable place
- Tailwind → shared design tokens let me ship light **and** dark themes consistently
- Sanctum → first-party, simple token auth; no extra identity server
- MySQL + Redis → ACID data in MySQL, volatile reads in Redis
- Docker → every lecturer runs it with one command

**Speaker notes (45 s)**
> "The stack is deliberately conventional so it is maintainable. Vue 3 with the
> Composition API and strict TypeScript on the front, Laravel 13 with Sanctum on
> the back. I chose Tailwind rather than a component framework because the design
> tokens are CSS variables, which let one palette drive both the light and the
> dark theme. Charts are Chart.js, wrapped in five reusable components. I18n
> gives English and Khmer, nineteen message namespaces, which matters because the
> target users are local. In PHP the only third-party functional package is
> dompdf, which I use for receipts and order reports."

---

# SLIDE 9 — Database Design

**On slide**
```
 users ──1:N──> addresses
   │  1:N      reviews ──N:1──> products ──1:N──> product_images
   │                                     │  N:1 brands
   │                                     └──1:N──> product_variants
   │                                              │  1:1 inventories ──1:N──> inventory_transactions
   │                                              │  1:N variant_attribute_values
   │                                              └──N:1──> attribute_values ──N:1──> attributes
   ├──1:1──> carts ──1:N──> cart_items
   ├──1:1──> wishlists ──1:N──> wishlist_items
   ├──1:N──> shop_users >──1:N── shops
   └──1:N──> orders ──1:N──> order_items (each line carries shop_id)
                 │  1:1
                 ├──1:1──> payments ──1:N──> payment_transactions
                 └──1:1──> shipments ──N:1──> shipping_methods

 coupons ──1:N──> coupon_usages ──N:1──> orders
```

**Scale of the schema (verified):** 30 models · 44 migrations · 25 API Resources
· 22 Form Requests · 8 policies · 10 services

**Relationships worth calling out:**
- `orders` → `order_items` is a **snapshot**: title, SKU, variant label, unit
  price and image are copied at checkout, so later catalogue edits never rewrite
  history
- `inventories` unique key is **`(product_variant_id, shop_id)`** — this is what
  makes branch stock independent
- `product_variants.sku` uniqueness is **scoped per shop**, not globally
- `order_items` carries `shop_id` → the fulfilling branch, resolved at checkout
  from the variant's parent product

**Speaker notes (55 s)**
> "Thirty models built through forty-four migrations. I will not show every
> table, only the four relationships that carry the design.
>
> First, order items are a snapshot. When checkout completes we copy the product
> name, the SKU, the variant label, the unit price and the image into the order
> line. If we later rename the product or change its price, the old order still
> shows what the customer actually bought. This matters legally and for refunds.
>
> Second, inventory is unique per variant *and* per branch — that composite key
> is what makes the stock independent. Third, variant SKU uniqueness is scoped
> per branch rather than globally, so two branches may legitimately use the same
> supplier SKU. Fourth, every order line records which branch must ship it."

---

# SLIDE 10 — Catalog: Variants, Attributes & Media

**On slide**
```
        Category (global)          Brand (global)
              │                        │
              └────────┬───────────────┘
                       ▼
                   Product
              ┌────────┴────────┐
              ▼                 ▼
        Product Images     Attribute Values
        (gallery)          Color: 13 swatches
                           Size:  7 values
                       │
                       ▼
              variant_attribute_values   ← the EAV join
                       │
                       ▼
              Product Variant  (Color:Blue + Size:L → one SKU)
                       │
                       ▼
                  Inventory
```

**Dynamic variant resolution:** the catalog API joins
`variant_attribute_values` across the selected attribute values, so choosing
*Color = Midnight* and *Size = L* returns exactly one `product_variant_id` —
with its own price, SKU, image, and **live stock level**. The same join powers
the colour and size filter chips in the product grid.

**Media handling** — `MediaUploadService`:
- Stores to the `public` disk under `images/{products|brands|categories|avatars}`
- **Deletes the previous file** when an image or logo is replaced
- Rejects non-image uploads (validated in `AdminImageUploadRequest`)

**Other implemented catalog features:** full CRUD, soft delete + restore, bulk
status update, image reordering, meta title/description/SEO keywords, 12 seeded
products with 3 images each, one deliberately out-of-stock variant.

**Speaker notes (50 s)**
> "The catalogue uses an Entity-Attribute-Value design for variants. Attributes
> are things like colour and size; attribute values are the specific options,
> like midnight or size L. A variant is defined by a row in the join table
> linking it to its attribute values. The important consequence is dynamic
> resolution: when the customer picks midnight and L, the backend joins those
> two values and returns exactly one variant with its own price, SKU, and live
> stock. The same mechanism powers the filter chips on the listing page.
> Media is handled by a dedicated service that stores images per context and, on
> replacement, deletes the old file so we never accumulate orphans."

---

# SLIDE 11 — Inventory Reservation Engine

**On slide**
```
  Checkout begins                Payment confirmed              Cancelled / expired
  ─────────────────              ──────────────────             ──────────────────
  reserved += qty                reserved -= qty                reserved -= qty
  available -= qty   ──────►    available -= qty                available += qty
  order = pending                available -= reserved          order = cancelled
                                 order = confirmed              coupon usage released
        │                              │
        │      15-minute window        │
        └────────► scheduler runs every minute
                    → expireStaleReservations()
                    → release stock, cancel order
```

**Ledger:** every single movement is appended to `inventory_transactions`
(`reserve` · `release` · `deduct` · `adjust`) with the acting user, a reference,
and a note. The inventory screen shows this ledger per item.

**Derived values on `Inventory`:**
- `available_quantity = quantity − reserved_quantity`
- `is_low_stock` when `available ≤ low_stock_threshold` (seeded at 5)
- Crossing the threshold fires **one** `LowStockNotification` to admins
  (guarded by `low_stock_notified_at`, then reset on restock)

**Cart quantities are clamped to available stock server-side** — a client
cannot request more than exists.

**Speaker notes (60 s)**
> "This is the part I am most pleased with. The naive approach is to decrement
> stock when the order is created. That fails twice: if the customer never pays,
> the stock is lost forever; and if two customers check out the last item at the
> same time, you oversell.
>
> So checkout does not decrement anything. It *reserves*: reserved goes up,
> available goes down, but the physical quantity is untouched. The order is
> created as pending. If payment succeeds within fifteen minutes, we deduct for
> real and move the order to confirmed. If the customer cancels, or the
> scheduler notices the order is stale, the reservation is released and the stock
> comes back. And crucially, every one of those movements is written to an
> inventory transaction ledger with the user who caused it, so I can always
> explain why the number is what it is."

---

# SLIDE 12 — Order Lifecycle State Machine

**On slide**
```
                    ┌──────────┐
                    │ PENDING  │  reservation held
                    └────┬─────┘
              confirm    │    cancel
       ┌─────────────────┴─────────────────┐
       ▼                                   ▼
 ┌───────────┐        cancel          ┌───────────┐
 │ CONFIRMED │ ─────────────────────▶ │ CANCELLED │
 └─────┬─────┘                         └───────────┘
   process │                 ┌──────────┐
       ┌───┴──────┐  refund  │ REFUNDED │
       ▼          ├─────────▶│  (final) │
 ┌────────────┐   │          └──────────┘
 │ PROCESSING │   │
 └─────┬──────┘   │
    ship │   cancel│
       ┌─┴──────┐ │
       ▼        ▼ │
 ┌──────────┐  ┌───────────┐
 │  SHIPPED  │  │ CANCELLED │
 └────┬─────┘  └───────────┘
  deliver│  refund
       ┌─┴──────┐
       ▼        ▼
 ┌───────────┐  ┌──────────┐
 │ DELIVERED │─▶│ REFUNDED │
 └───────────┘  └──────────┘
```

**Rules enforced server-side**
- Only the transitions above are legal — the map lives in `OrderService`
- `PUT /api/admin/orders/{id}/transition` rejects anything else with **422**
- Customer self-cancel is allowed **only** while `pending`
- `shipped` writes a **tracking event** and sends an `OrderStatusNotification`
- Any transition to a stock-affecting terminal state **restores inventory** and,
  if the order was paid, sets `payment_status = refunded`
- Requires: order has a paid payment OR a recorded reason to cancel

**Speaker notes (55 s)**
> "Order status is a strict state machine, not a free-text field. A lecturer can
> try to jump an order from confirmed straight to delivered through the API and
> the server rejects it with a validation error. Cancellation and refund are
> first-class branches, not flags.
>
> Two side effects matter. Moving to shipped records a tracking event that the
> customer sees on the tracking page, and fires a database notification. And when
> an order reaches any terminal state that affects stock, the inventory service
> puts the quantity back and, if the order had been paid, marks the payment as
> refunded. Those two paths are covered by dedicated regression tests, because
> this was where I found real bugs."

---

# SLIDE 13 — Cart, Coupons & Multi-Branch Checkout

**On slide**
**Customer journey**
```
Browse & filter → Product detail (variant + live stock)
   → Add to cart ─────────────────────────────┐
   → Apply coupon (validated server-side)      │
   → Checkout: address · shipping · payment    │
   → Stock reserved 15 min, order = pending    │
   → Confirm payment → order = confirmed       │
   → Branch picks, packs, ships               │
   → Delivered → customer may review          ▼
```

**Cart**
- **Guest carts work.** A `X-Session-Id` UUID is generated in the browser; no
  account needed to shop
- On **login or register, the guest cart is merged** into the user's cart
- Quantities clamped to available stock; totals recomputed **on the server** —
  the client is never trusted with money
- Totals = subtotal − discount, + flat 10 % tax, + shipping

**Coupon validation order** (`CouponService`)
1. Coupon is active
2. Within start / expiry window
3. Global usage limit not exceeded
4. Minimum order amount met
5. Per-user usage limit not exceeded
→ then **percentage** or **fixed** discount, with an optional maximum-discount cap
→ records a `coupon_usages` row; **released on cancel, refund, or reservation expiry**

**Branch handling at checkout:** every `order_item` is stamped with the `shop_id`
derived from its variant's parent product. One `orders` row, one payment, many
fulfilling branches.

**Speaker notes (60 s)**
> "The cart works for guests. The browser generates a session ID, so someone can
> add items before registering. When they finally log in, the guest cart is merged
> into their account cart, so nothing is lost.
>
> Two rules protect the business. Quantities are clamped to available stock, and
> all totals are recomputed on the server — the browser's numbers are never
> trusted, because anyone can edit them in devtools.
>
> Coupons are validated in a fixed order: active, in date range, global limit,
> minimum spend, then per-user limit. Only after all five pass is the discount
> applied, as a percentage or a fixed amount with an optional cap. Usage is
> recorded, and released again if the order is cancelled, refunded, or expires.
>
> Finally, at checkout each order line is stamped with the branch that must ship
> it, derived from the product, not from anything the client sends."

---

# SLIDE 14 — Reviews, Notifications & Order Tracking

**On slide**
**Verified-purchase reviews**
```
Customer writes a review
        │
        ▼
DB check: does this user have an order
containing this product with
status = DELIVERED ?
        │
   ┌────┴────┐
   NO        YES
   ▼          ▼
  403     pending → admin moderation
                     │
            ┌────────┴────────┐
         approved          rejected
            ▼                  ▼
  product rating_avg and    Reviewer notified
  rating_count recalculated
```
- One review per customer per product
- `verified` flag stored from the source order status
- Only **approved** reviews count toward the product rating

**Notifications (5 database-channel types)**
`OrderPlaced` · `OrderStatus` · `LowStock` · `ReviewApproved` · `ReviewRejected`
- Unread badge on the bell in both the admin sidebar and account sidebar
- Mark-read per notification, with ownership enforced

**Order tracking**
- `tracking_events` table, written on every `shipped` transition
- Customer-facing stepper at `/order/tracking/{orderNumber}`
- Guests can look up an order by number: `GET /api/orders/guest/{orderNumber}`

**Speaker notes (50 s)**
> "Reviews are not free text from anyone. Before a review is accepted, the
> backend checks the database: does this user actually have a delivered order
> containing this product? If not, the request is rejected. Accepted reviews go
> into a moderation queue, and only approved reviews move the product's average
> rating — a rejected spam review changes nothing. The reviewer is notified
> either way, which is better UX than silence.
>
> On notifications, five event types are sent, including the low-stock alert
> that only admins receive and only once per threshold crossing. For tracking,
> each shipment transition writes a tracking event, and the customer can follow
> a stepper. Guests can also check an order using just the order number, which is
> `SV`, the year, then six digits."

---

# SLIDE 15 — Admin Dashboard, Reports & Exports

**On slide**
```
┌─ Admin Dashboard ───────────────────────────────────────────────┐
│  KPI: Revenue · Orders · Customers · Low-stock count            │
│  ┌────────────┬────────────┬────────────┬──────────────┐       │
│  │ Revenue    │ Orders     │ Order      │ Payment       │       │
│  │ trend line │ trend bar  │ status bar │ status ring   │       │
│  └────────────┴────────────┴────────────┴──────────────┘       │
│  Sales by category ring · Top sellers · Recent orders/customers │
│  · Recent reviews · Recent payments · Low-stock alert list      │
└────────────────────────────────────────────────────────────────┘
```

**Administration modules (all functional)**

| Group | Modules |
|---|---|
| Overview | Dashboard with 5 Chart.js visualisations, custom date range |
| Catalog | Products (CRUD, soft delete + restore, bulk status), Categories, Brands, image upload |
| Fulfilment | Orders + state-machine transitions, Inventory + transaction ledger, Shipments, Payments |
| Marketing | Coupons (percentage / fixed / min spend / per-user limits) |
| Community | Review moderation queue (approve / reject / delete) |
| System | Customers, Notifications, Settings |
| *(API only, no screen yet)* | **Shops** — `GET/POST /api/admin/shops`, `PUT/DELETE /api/admin/shops/{id}`, `PATCH .../status`. The 6 endpoints are live and tested, but **no Vue page consumes them yet.** |

**Not built (API absent, do not show as a feature):** commission settlement,
payouts, vendor/branch self-registration, per-branch settings or categories.

**Exports**
- `orders.pdf` and `reports/orders.pdf` — generated with **dompdf**
- `orders.csv`, `products.csv`, `payments.csv`
- Per-order and per-admin **PDF receipt** download

**Screenshot placeholders**
```
[Insert Admin Dashboard Screenshot]
[Insert Admin Inventory / Transaction Ledger Screenshot]
[Insert Admin Orders State Machine Screenshot]
```

**Speaker notes (50 s)**
> "The admin side is a single dashboard with KPI cards and five charts, all from
> Chart.js: revenue trend, order trend, order status distribution, payment status
> ring, and sales by category. Below that are top-selling products, recent
> customers, recent reviews, and a low-stock alert list.
>
> The sidebar is grouped into six sections: overview, catalog, fulfilment,
> marketing, community, and system. Everything listed there is functional —
> there are no placeholder pages. For reporting I generate real PDFs with dompdf
> for orders and per-order receipts, and CSV for orders, products, and payments.
> The branch management screen is also here, under system, with create, edit,
> and activate or suspend. One honest note: those six branch endpoints exist in
> the API and are tested, but I have not yet built the Vue screen that calls
> them, so there is nothing to click here — it is on my future-work slide."

---

# SLIDE 16 — Security & Authorization

**On slide**
**Request flow**
```
Request
   ▼
Sanctum bearer token  ── invalid ──▶ 401
   ▼
EnsureUserIsAdmin middleware  ── not admin ──▶ 403
   ▼
Policy check (8 policies)  ── wrong branch ──▶ 403
   ▼
Controller
```

**Layers of defence (all server-side)**

| Layer | Implementation |
|---|---|
| Authentication | Sanctum personal access tokens; revoked on logout and on password reset |
| Email verification | `MustVerifyEmail` + signed URL, `hash_equals` comparison, resend throttling |
| Password security | bcrypt via Laravel Hash; legacy non-bcrypt hashes rejected as invalid credentials, not a 500 |
| Global RBAC | `admin` middleware on the whole `/api/admin/*` prefix |
| Branch RBAC | 8 policies, each with a `before()` super-admin bypass and branch scoping via `shop_users` |
| IDOR prevention | Orders, addresses, wishlist, cart, and notifications are all owner-scoped by query, not by input |
| Input validation | 22 Form Request classes; negative price/quantity rejected with 422, not a SQL error |
| Mass assignment | Status and role are never client-writable |
| Password reset | Signed, expiring, non-enumerating response; revokes all existing tokens |
| File uploads | Non-image rejected; old files deleted on replace; dotfiles denied by Nginx |

**Explicit rule:** `shop_id` is **never** trusted from the request body. The
fulfilling branch is derived server-side from the product of the variant being
purchased.

**Audit finding worth mentioning:** an independent QA pass
(`docs/QA-REPORT.md`) found 1 critical, 5 high, 13 medium, and 12 low issues
plus 7 security findings — **all critical and high issues are now fixed and
locked behind 10 dedicated regression tests.**

**Speaker notes (55 s)**
> "Security is layered, and every layer is on the server. The client is never
> trusted. First, Sanctum issues a bearer token, which is revoked on logout and
> on any password reset. Second, the admin middleware guards the whole admin
> prefix. Third, eight policies enforce branch-level access, each with a
> super-admin bypass.
>
> The rule I care most about is this: the shop identifier is never read from the
> request body. If a client sends a shop ID, it is ignored — the branch is
> derived from the product being purchased. That closes the obvious IDOR attack
> where someone edits an ID in the URL to read another branch's data.
>
> I also ran an independent QA pass. It found one critical and five high
> severity bugs, including a cancel-of-paid-order bug that corrupted payment
> state and lost stock. All of those are fixed and covered by ten regression
> tests, and the suite is green."

---

# SLIDE 17 — Testing & Quality Assurance

**On slide**
```
  docker compose exec -T app php artisan test

  Tests:  167 passed (729 assertions)
  Duration: 215.51 s          ← run live, all green
```

**16 test classes, 167 tests — what each group proves**

| Test file | Tests | Focus |
|---|---:|---|
| `AuthTest` | 21 | Register, login, logout, reset, email-verification flow, token revocation |
| `ShopTest` | 19 | Branch CRUD, cross-branch 403s, staff scoping, inventory isolation, soft delete |
| `AdminOpsTest` | 16 | Dashboard, payments, shipments, notifications, CSV exports, bulk update, restore |
| `ShopOwnershipTest` | 16 | `shop_id` stamping, policy bypass rules, per-branch SKU uniqueness |
| `AdminTest` | 11 | Admin guards, state machine, refund path, PDF downloads |
| `AdminMediaTest` | 11 | Upload guards, gallery sync, logo replacement, file deletion |
| `QaHotfixTest` | 10 | Regression suite for every QA finding above |
| `CheckoutTest` | 10 | Reserve → confirm → deduct, cancel release, double-confirm, guest checkout |
| `AdminInventoryTest` | 9 | Stock filters, ledger, `adjust` transaction logging |
| `CartTest` | 9 | Guest session carts, stock clamping, cart merge on login |
| `CatalogTest` | 9 | Filters, sort, search, facets, variant + gallery detail |
| `AccountAndSettingsTest` | 7 | Profile, avatar, settings, tracking events |
| `CouponTest` | 7 | All five validation rules and both discount types |
| `NotificationTest` | 5 | All 5 notification types, low-stock fires once |
| `ReviewTest` | 4 | Verified-purchase enforcement, one-per-user, rating recalculation |
| `ScheduleTest` | 3 | Reservation expiry runs, and leaves fresh reservations alone |
| **Total** | **167** | |

**Honest scope statement — say this out loud**
- Runs against **in-memory SQLite**, not MySQL
- **No** load test at 1 000 / 10 000 products was executed
- **No** browser-level UI automation (no Playwright in the environment)
- `tests/Unit` is configured but empty — all coverage is feature-level API tests

**Also verified manually:** frontend `vue-tsc -b && vite build` compiles with
**0 errors**; i18n key parity between `en.json` and `km.json`.

**Speaker notes (55 s)**
> "Quality is measured, not claimed. I ran the suite in Docker just before this
> presentation: one hundred and sixty-seven tests, seven hundred and twenty-nine
> assertions, all passing, in about three and a half minutes.
>
> The suite is organised by feature, and the two largest groups are the branch
> tests — nineteen and twenty tests respectively — because isolation across
> branches is the riskiest part of the design. Checkout is tested end to end:
> reserve, confirm, deduct, cancel and release, and rejecting a double confirm.
> And there is a dedicated regression file for the ten bugs the QA pass found.
>
> I also want to be clear about what is *not* covered. The tests run against
> in-memory SQLite rather than MySQL, I did not run a load test at a thousand or
> ten thousand products, and there is no browser automation. The frontend does
> compile cleanly with zero type errors, and I verified the English and Khmer
> translation files have matching keys."

---

# SLIDE 18 — Performance, Conclusion & Future Work

**On slide**
**Measured — cold vs warm** (`docs/Performance-Report.md`, `curl` via Nginx)

| Endpoint | Cold (first hit) | Warm | Change |
|---|---|---|---|
| `GET /api/categories` | ~4.7 s | **6–8 ms** | ~600× |
| `GET /api/brands` | ~9.6 s | **6–7 ms** | ~1300× |
| `GET /api/shipping-methods` | ~5.2 s | **5–6 ms** | ~900× |
| `GET /api/catalog/featured` | ~5.1 s | **6–11 ms** | ✔ |
| `GET /api/catalog/facets` | ~3.9 s | **8 ms** | ✔ |
| `GET /api/admin/inventory` | **HTTP 500** | **200 OK** | bug fixed |

**What produced the gain**
1. **OPcache** enabled via mounted ini — removes per-request framework recompilation (the dominant cost)
2. **Nginx gzip** for CSS/JS/JSON/SVG/PDF
3. **Redis caching of stable public data only** — category tree (24 h), brands, shipping
   methods — with `Cache::forget` invalidation in the three admin controllers.
   Cart, orders, checkout and reports are deliberately **not** cached
4. **Composite index** on `order_items (product_id, product_variant_id)`

**Conclusion**
- A working multi-branch e-commerce platform on one shared catalogue
- Independent stock and orders per branch, enforced by 8 policies
- Full commerce lifecycle: cart → coupon → reserve → pay → ship → track → review
- Central dashboard, analytics, and PDF/CSV exports
- 167 automated tests green; public endpoints respond in single-digit milliseconds

**Future work (honest, in priority order)**
1. **Branch staff access + branch management UI** — populate `shop_users`, add the permission
   layer, build the branch-owner dashboard and the `/admin/shops` screen. Policies and
   endpoints are ready; the interface is not.
2. **Public branch storefronts** — consume the existing `GET /api/shops/{slug}` routes in Vue
3. **Payment gateway integration** — currently COD + sandbox mode only
4. **Commission & payout ledger** — the `commission_rate` column is reserved, unused
5. **Full-frontend multi-branch UI** — shop-context Pinia store, branch switching, i18n keys
6. Load testing, browser automation, and a translation-complete Khmer UI
7. Beyond scope: mobile app, recommendations, additional gateways, real-time push

**Speaker notes (60 s)**
> "Finally, performance. These are measured numbers, not targets. Cold — the very
> first request after a container start — took between four and ten seconds,
> almost entirely framework boot time. Enabling OPcache was the single biggest
> win; once the cache was warm the same endpoints respond in six to eight
> milliseconds, which is roughly a thousand times faster. Nginx gzip, Redis
> caching of the stable public reference data, and one composite index completed
> the work. I deliberately did *not* cache carts, orders, or checkout, because
> stale money is worse than slow money.
>
> To conclude: the platform works, the branch isolation is enforced in code and
> proven by tests, the commerce lifecycle is complete, and the public API is fast.
> My priority for future work is the branch-staff layer — the policies exist and
> are tested, so it is mainly a matter of building the interface and seeding
> memberships. Thank you. I am happy to take questions."

---

# SLIDE 19 — Thank You

**On slide**
```
        Thank You
   Questions & Answers

   E-KHMER — Multi-Branch E-Commerce Web Application
   Laravel 13 · Vue 3 + TypeScript · MySQL · Redis · Nginx · Docker
```

**Speaker notes (20 s)**
> "Thank you for your time. I have documentation for the architecture, the full
> API reference generated from the running backend, the QA report, and the
> performance report, and the system is running in Docker if you would like to
> see it. I am happy to answer any questions."

---

# Appendix A — Likely Defence Questions

| Question | Answer |
|---|---|
| Why not JWT? | Sanctum is first-party, ships with Laravel, and covers exactly the need here. I am aware long-lived tokens need a refresh/expiry strategy — currently `expiration => null` — and that is planned work. |
| Why one order instead of parent + sub-orders? | One brand, one checkout, one payment. Tagging each `order_item` with its `shop_id` preserves per-branch reporting without a second order system. Sub-orders would duplicate payment, tax, and status logic. |
| How do you stop overselling? | Reservations. `reserved_quantity` rises at checkout, so a second customer sees `available` fall. Real deduction happens only on payment confirmation. The scheduler releases stale reservations every minute. |
| What stops a branch reading another branch's data? | 8 policies, each with a `before()` admin bypass and branch scoping. The two branch test files add 35 tests, most of them asserting a 403 on cross-branch access. |
| Is the frontend really multi-branch? | No. The backend and policies are; the Vue UI is single-brand. I have listed this as the top future item rather than overstating it. |
| Why 15 minutes? | It is configurable in `config/ecommerce.php` as `reservation_minutes`, and it matches a reasonable card-payment window and a realistic cart abandonment time. |
| How are reviews prevented from being fake? | A delivered-order check in the database, plus admin moderation, plus only approved reviews counting toward the rating. |
| What is not production-ready? | No real payment gateway, no rate limiting, no load test, no browser automation, empty `tests/Unit`, and branch staff cannot yet authenticate. |

---

# Appendix B — Design Requirements

**Layout and typography**
- 16:9, one idea per slide, large headings (min 32 pt), body min 20 pt
- Consistent 8-pt spacing grid; identical title position on every slide
- Slide number bottom-right; project name bottom-left
- Diagrams built from plain shapes and connectors — no 3-D, no drop shadows
  heavier than 2 px, no smart art

**Colour — blue / white / light gray / black**
```
Primary      #1E3A8A      Headings, diagram accents
Primary dark #172554      Hover, emphasis
Canvas       #FFFFFF      Background
Surface      #F3F4F6      Card and panel fill
Ink          #111827      Body text
Muted        #6B7280      Captions, axis labels
Border       #D1D5DB      Dividers
Accent green #166534      Success states only
Accent amber #B45309      Warning / pending states only
```
- **No gradients.** No drop shadows below 2 px. No glassmorphism.
- Colour-blind safe: pair every colour with a label or icon, never colour alone

**Icons** — use **lucide-vue-next** (the project's actual icon set), one
consistent stroke weight (2 px), never mixing icon styles.
Do **not** use Bootstrap Icons — the project does not use Bootstrap.

**Typography** — the project loads **Inter** (UI) and **Kantumruy Pro** (Khmer).
Use Inter for the deck; use Kantumruy Pro only for a Khmer wordmark.

**Diagrams**
- All diagrams are plain boxes + arrows, blue `#1E3A8A` strokes, white fills
- Maximum depth 3 levels (except the multi-branch tree, which is inherently 2)
- Label every arrow. An unlabelled arrow is a question from the examiner.
- Never redraw a diagram that is already on an earlier slide

**Content rules — these are not stylistic preferences**
- **No invented statistics.** Every number in this deck was measured; see the
  verification table at the top of this file
- **No fake screenshots** — use the marked placeholders
- **No slide claims a feature that is not in the code.** Commission, payouts,
  vendor onboarding, and the branch-staff dashboard do not exist yet
- If asked about a missing feature, say so directly and point to the future-work
  slide. That reads as competence, not weakness.

**Screenshot placeholders to insert before the defence**
```
[Insert Customer Home / Product Listing Screenshot]
[Insert Product Detail with Variant Selector Screenshot]
[Insert Cart and Checkout Screenshot]
[Insert Admin Dashboard Screenshot]
[Insert Admin Inventory Transaction Ledger Screenshot]
[Insert Branch Management Screen Screenshot]
```
