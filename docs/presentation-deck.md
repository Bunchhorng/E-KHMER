# Project Presentation Deck — E-KHMER

> **Multi-Branch E-Commerce Web Application**
> Structure follows the required format: Cover → Members → Contents →
> Production Introduction → Project Functionalities → … → Project GUI.
>
> **Every figure below was measured from the source.** Verification method is
> given beside each number so you can defend it live if an examiner asks.
> Run `docker compose exec app php artisan test` to reproduce the test figures.

---

## Slide index (quick reference)

| # | Slide | Status |
|---:|---|---|
| 1 | Cover Slide | fill in institution details |
| 2 | Project Team — Member Names & Photos | **needs real photos** |
| 3 | Table of Contents | ready |
| 4 | Production Introduction | ready |
| 5 | Project Functionalities | ready |
| 6 | System Architecture & Docker | ready |
| 7 | Technology Stack | ready |
| 8 | Database Design | ready |
| 9 | Multi-Branch Architecture | ready |
| 10 | Backend Layering & the API Surface | ready |
| 11 | Catalog — EAV Variants & Media | ready |
| 12 | Inventory Reservation Engine | ready |
| 13 | Order Lifecycle State Machine | ready |
| 14 | Cart, Coupons & Multi-Branch Checkout | ready |
| 15 | Reviews, Notifications & Order Tracking | ready |
| 16 | Admin Dashboard, Reports & Exports | ready |
| 17 | Security & Authorization | ready |
| 18 | Testing & Quality Assurance | ready |
| 19 | Performance Results | ready |
| 20 | Challenges, Limitations & Future Work | ready |
| 21 | **Project GUI** | **needs real screenshots** |

Two slides need input from you: **2** (photos) and **21** (screenshots).
Everything else is complete and verified.

---

# SLIDE 1 — Cover Slide

**On slide**
```
        ┌──────────────────────────────────────────────┐
        │                                              │
        │        [ Insert University Logo ]            │
        │                                              │
        │   ─────────────────────────────────────      │
        │                                              │
        │   Multi-Branch E-Commerce                     │
        │   Web Application                             │
        │                                              │
        │   E-KHMER                                    │
        │                                              │
        │   ─────────────────────────────────────      │
        │                                              │
        │   University      : ______________________   │
        │   Faculty         : ______________________   │
        │   Department      : ______________________   │
        │   Supervisor      : ______________________   │
        │   Academic Year   : ______________________   │
        │                                              │
        └──────────────────────────────────────────────┘
```

**Design:** university logo top-left or centred, thin blue rule above and below
the title, no gradient, no background photo. Title in primary blue `#2563EB`,
subtitle in ink `#111827`. Institution block bottom-left, left-aligned.

**Speaker notes (25 s)**
> "Good morning. Our project is a multi-branch e-commerce web application called
> E-KHMER. It is a full-stack system in which a single retail brand operates
> several physical branches through one shared platform. I am ___ , and I will
> be presenting the work done by our team of ___ members. Our supervisor is
> ___ ."

---

# SLIDE 2 — Project Team: Member Names & Photos

**On slide** — 3-member layout, repeat or drop columns to match your team size

```
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ ┌─────────────┐ │ │ ┌─────────────┐ │ │ ┌─────────────┐ │   photo:
│ │             │ │ │             │ │ │             │ │   square,
│ │   PHOTO 1   │ │ │   PHOTO 2   │ │ │   PHOTO 3   │ │   1:1 aspect,
│ │             │ │ │             │ │ │             │ │   face centred,
│ └─────────────┘ │ │ └─────────────┘ │ │ └─────────────┘ │   plain
│  Member One     │ │  Member Two     │ │  Member Three   │   background
│  Student ID     │ │  Student ID     │ │  Student ID     │
│  ─────────────  │ │  ─────────────  │ │  ─────────────  │
│  ▸ Frontend     │ │  ▸ Backend &    │ │  ▸ Database &   │
│  ▸ UI design    │ │    API          │ │    Testing      │
│  ▸ i18n         │ │  ▸ Auth &       │ │  ▸ QA           │
│                 │ │    Security     │ │  ▸ Performance  │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

**Photo requirements — read before you shoot**
| Item | Specification |
|---|---|
| Aspect ratio | **1:1 square** (a 3:4 portrait will misalign the cards) |
| Minimum size | **400 × 400 px** — will be printed at ~4 cm on a projector |
| Background | Plain, uncluttered. No busy wallpaper, no filters |
| Framing | Head and shoulders, eyes in the upper third, centred |
| Format | JPG, under 300 KB each |
| Consistency | Same lighting, same background, same crop for all members |
| Add a border | 2 px `#E5E7EB` in the deck so photos do not bleed on a dark projector |

**Replace each `PHOTO n` block with your real image.** If your team has four or
five members, use four or five columns, or split across two rows — do not shrink
the photos below 400 px to fit more in.

**Speaker notes (30 s)**
> "Our team has ___ members. ___ handled the frontend interface and the
> Khmer and English localisation. ___ built the backend API, authentication, and
> the security layer. ___ designed the database, wrote the automated test suite,
> and produced the QA and performance reports. I coordinated the integration
> between the three parts and implemented the multi-branch inventory logic."

**Adjust the contribution bullets to match what each member actually did.**
Supervisors check this, and it is the fastest way to lose credibility.

---

# SLIDE 3 — Table of Contents

**On slide**
```
┌────────────────────────────┬────────────────────────────┐
│ 01  Cover                          12  Order Lifecycle
│ 02  Project Team                   13  Cart, Coupons &
│ 03  Table of Contents                   Checkout
│ 04  Production Introduction         14  Reviews & Notifications
│ 05  Project Functionalities         15  Admin Dashboard &
│ 06  System Architecture & Docker        Reports
│ 07  Technology Stack                16  Security & Authorization
│ 08  Database Design                 17  Testing & QA
│ 09  Multi-Branch Architecture       18  Performance Results
│ 10  Backend Layering & API          19  Challenges & Future Work
│ 11  Catalog — EAV & Media          20  Project GUI
└────────────────────────────┴────────────────────���───────┘
```

**Design:** two columns, numbered, no descriptions, no icons. Blue numbers
`#2563EB`, black labels. Highlight nothing — a highlighted entry implies the
others are less important.

**Speaker notes (20 s)**
> "Here is the outline. I will start with what the system is and what it does,
> then move into how it is built — the architecture, the database, and the
> multi-branch design. After that I will cover the specific business rules that
> were the hardest part of the project: stock reservation, the order state
> machine, and coupons. I will finish with security, the measured test and
> performance results, and then show you the actual application."

---

# SLIDE 4 — Production Introduction

**On slide**
**What the system is**
- A production-grade web platform where **one brand operates multiple branches**
- Full **customer storefront** plus **central administration dashboard**
- **Shared catalogue** · **independent stock and orders per branch**
- A customer can buy from **several branches in one cart and pay once**

**The problem it solves**

| Problem in a single-store system | Consequence |
|---|---|
| One global stock counter | Two branches oversell the same SKU |
| Orders not attributable to a branch | No branch can fulfil, ship, or report |
| No per-branch permission boundary | Staff read or edit another branch's data |
| Free-text order status | Customers skip steps; refunds corrupt records |
| Unverified reviews | Trust destroyed by fake feedback |
| No audit trail on stock | Nobody can explain why a number changed |

**Who uses it**
- **Super Admin** — governs the whole platform
- **Customer** — browses, buys, tracks, reviews
- **Branch staff** — modelled and authorised in the backend *(see Slide 5)*

**Scope note — state this plainly:** this is a **multi-branch of one brand**
system, not an open multi-vendor marketplace. There is no seller self-registration.

**Speaker notes (55 s)**
> "Before the technical detail, what the system is and why it exists.
>
> The core situation is a business with one brand and several physical branches.
> That sounds simple but it breaks most e-commerce systems, which assume a single
> catalogue and a single inventory. Give two branches twenty units each of the
> same phone and a global counter will happily sell forty. Orders also stop making
> sense, because a branch does not know what it must pick and ship and cannot be
> held responsible for its own sales.
>
> On the right are six problems that drove the design. Two of them — free-text
> order status and no audit trail on stock — are the ones that caused real
> corrupted data during our own testing, and they are why slides twelve and
> thirteen exist.
>
> One point of scope I want to be clear about: this is one brand with several
> branches, not an open marketplace where anyone can sign up and sell. That was a
> deliberate design decision, and I will explain why on slide nine."

---

# SLIDE 5 — Project Functionalities

**On slide** — four groups, all implemented

```
┌─ AUTHENTICATION & ACCESS ─────────────────────────────────────────┐
│ Register · Login · Logout · Email verification · Forgot/Reset      │
│ Password · Bearer tokens · Guest session carts                     │
└───────────────────────────────────────────────────────────────────┘
┌─ CATALOG & INVENTORY ─────────────────────────────────────────────┐
│ Categories (tree) · Brands · Products + gallery · Variants (EAV)   │
│ Attributes & values · Image upload/replace/delete · Stock ledger    │
│ Low-stock alerts · Soft delete + restore · Bulk status              │
└───────────────────────────────────────────────────────────────────┘
┌─ SHOPPING & FULFILMENT ───────────────────────────────────────────┐
│ Faceted search & sort · Wishlist · Cart (guest or account)         │
│ Coupons · Multi-branch checkout · 15-min stock reservation          │
│ Order lifecycle · Payments · Shipping + tracking · PDF receipts     │
│ Verified reviews with moderation · Email notifications              │
└───────────────────────────────────────────────────────────────────┘
┌─ ADMINISTRATION & REPORTING ──────────────────────────────────────┐
│ Dashboard with 5 charts · Order pipeline · Inventory ledger         │
│ Customer & review management · Settings · Branch CRUD + status     │
│ Reports: PDF + CSV export                                          │
└───────────────────────────────────────────────────────────────────┘
```

**Implementation status — show the third column, do not hide it**

| Area | Backend | Frontend | Note |
|---|:--:|:--:|---|
| Customer storefront, cart, checkout, orders, reviews | ✅ | ✅ | full |
| Admin console — 22 views, 6 nav groups | ✅ | ✅ | full |
| Branch management — 6 endpoints (`/api/admin/shops`) | ✅ | ❌ | **no Vue screen yet** |
| Public branch pages — 3 endpoints (`/api/shops/{slug}`) | ✅ | ❌ | **no Vue screen yet** |
| Branch staff login (`shop_users` roles) | ⚠ | ❌ | policies enforced; `shop_users` empty |
| Commission & payouts | ❌ | ❌ | **not built** — `commission_rate` column reserved only |
| Payment gateway integration | ⚠ | ⚠ | COD + sandbox mode only |

**Scale:** 115 API routes (63 admin) · 30 models · 44 migrations · 25 API
Resources · 22 Form Requests · 8 policies · 10 services · 51 frontend routes ·
44 views · 24 components · 6 Pinia stores · 18 API modules

**Speaker notes (60 s)**
> "The functionality falls into four groups: access, catalogue and inventory,
> shopping and fulfilment, and administration and reporting.
>
> I want to draw your attention to the status table at the bottom, because I
> would rather state the gaps myself. Everything in the top two rows is finished
> and works end to end. The branch management endpoints exist in the API and are
> covered by tests, but I have not built the Vue screen that calls them, so
> there is nothing to click. The branch staff authorisation layer is written and
> tested, but the membership table is empty, so a branch manager cannot yet sign
> in. And commission and payouts are not built at all — I only reserved a column
> in the shop table for the future. Being clear about this is deliberate."

---

# SLIDE 6 — System Architecture & Docker

**On slide**
```
┌────────────────────┐
│  Browser           │   Vue 3 SPA  (Vite dev server :5174)
└─────────┬──────────┘
          │  JSON over HTTP   /api proxied → http://nginx
          ▼
┌────────────────────┐
│  nginx    :8000    │   gzip · dotfile deny · try_files → index.php
└─────────┬──────────┘
          │  FastCGI  app:9000
          ▼
┌────────────────────┐
│  app  (php-fpm)    │
│  Laravel 13 · 8.3  │   routes/api.php
│  OPcache enabled   │     ↓
└─────────┬──────────┘   Controllers (16 customer + 15 admin)
          │                  ↓
    ┌─────┴──────┬──────────┴──────────────┐
    ▼            ▼                         ▼
┌─────────┐ ┌──────────┐          ┌────────────────┐
│ mysql   │ │  redis   │          │   scheduler    │
│ :3307   │ │  :6379   │          │   every 1 min   │
│ 8.0     │ │ cache /  │          │ release stale   │
│ 26 tbls │ │ session  │          │ reservations    │
└─────────┘ └──────────┘          └────────────────┘
```
**Docker Compose — 7 services** (`docker-compose.yml`)

| Service | Image / build | Host port | Role |
|---|---|---:|---|
| `app` | `./docker/php` → `php:8.3-fpm` | — | Laravel API, OPcache on |
| `scheduler` | same image | — | `php artisan schedule:work` |
| `nginx` | `nginx:alpine` | **8000** | only public API entry point |
| `mysql` | `mysql:8.0` | **3307** | `ecommerce_db`, volume-persisted |
| `redis` | `redis:alpine` | **6379** | cache, session, queue |
| `phpmyadmin` | `phpmyadmin:latest` | **8080** | database inspection |
| `frontend` | `node:22-alpine` | **5174** | Vite dev server |

**Why this shape**
- **Nginx is the only public port.** The Vite container proxies `/api` to
  `http://nginx` over the internal bridge network — using `localhost` would loop
  back into the frontend container.
- **State is isolated to three services.** `app` and `frontend` are stateless and
  bind-mounted for hot reload, so a rebuild never loses data.
- **The scheduler is a separate long-running container**, not a cron hack — it
  is what makes the 15-minute reservation guarantee reliable.

**Speaker notes (55 s)**
> "Read this from the top. The browser loads a single-page Vue application. It
> never touches the database — it speaks only JSON, to Nginx, which is the single
> public port. Nginx forwards PHP requests to the FPM container over FastCGI on an
> internal network. One detail worth noting: the Vite dev container proxies its
> API calls to Nginx over the Docker network rather than to localhost, because
> localhost inside the frontend container would point back at itself.
>
> There are seven services. Three of them hold state — MySQL, Redis, and the
> scheduler — and those use named volumes, so a rebuild never destroys data. The
> application and frontend containers are stateless and bind-mounted, so code
> changes are hot.
>
> The scheduler is the one people miss. It runs as its own long-lived container
> and wakes every minute to release stock reservations from abandoned checkouts.
> Without it, an unpaid order would hold inventory forever."

---

# SLIDE 7 — Technology Stack

**On slide**

**Backend**
| | Technology | Version |
|---|---|---|
| Framework | Laravel | ^13.17 |
| Language | PHP | ^8.3 |
| Authentication | Laravel Sanctum (bearer tokens) | ^4.0 |
| Database | MySQL | 8.0 |
| Cache / Session / Queue | Redis | alpine |
| PDF generation | barryvdh/laravel-dompdf | ^3.1 |
| Testing | PHPUnit | ^12.5.12 |

**Frontend**
| | Technology | Version |
|---|---|---|
| Framework | Vue 3 (`<script setup>`) | ^3.5.41 |
| Language | TypeScript | ~6.0.2 |
| Build tool | Vite | ^8.2.2 |
| Routing | Vue Router (all 51 routes lazy) | ^5.3.0 |
| State | Pinia (6 stores) | ^4.0.3 |
| HTTP | Axios (auth + 401 interceptors) | ^1.20.0 |
| Styling | **Tailwind CSS** (10 token variables) | ^3.4.19 |
| Charts | Chart.js + vue-chartjs | ^4.5.1 |
| Icons | lucide-vue-next | ^1.0.0 |
| i18n | vue-i18n (English / Khmer) | ^11.4.10 |

**Infrastructure**
Docker · Docker Compose (7 services) · Nginx · OPcache

**Selection rationale — one line each**
- **Sanctum, not JWT** — first-party, zero extra infrastructure, exactly matches the need
- **Tailwind, not a component framework** — 10 CSS-variable tokens meant dark mode
  cost 10 changed values instead of 44 rewritten views
- **Chart.js** — 5 wrapped components; no chart code ships to the customer side
- **TypeScript strict** — the API contract is typed end to end; a renamed response
  field fails the build, not production

**Correction if asked:** the project uses **Tailwind CSS, not Bootstrap**, and
**Chart.js, not Recharts**. Some older documentation in the repository still says
otherwise.

**Speaker notes (45 s)**
> "The stack is deliberately conventional. Laravel thirteen with Sanctum on the
> back, Vue three with strict TypeScript on the front, MySQL for transactional
> data and Redis for anything volatile.
>
> Two choices I would defend. First, Sanctum instead of JWT — it ships with
> Laravel, needs no identity server, and covers exactly what we needed. Second,
> Tailwind with CSS-variable tokens rather than a component framework, because I
> could declare ten colour variables and get both light and dark themes from the
> same component code.
>
> One correction worth making proactively: the documentation folder has some older
> files that still say Bootstrap and Recharts. The project actually uses Tailwind
> and Chart.js. We found that drift during our QA pass and it is written up in the
> QA report."

---

# SLIDE 8 — Database Design

**On slide**
```
 users ──1:N──> addresses                    categories ──1:N──> products
   │                                              ▲
   │  1:N  reviews ──N:1──> products              └──N:1── brands
   │  1:1  carts ──1:N──> cart_items
   │  1:1  wishlists ──1:N──> wishlist_items
   │  1:N  shop_users >──1:N── shops
   └──1:N──> orders ──1:N──> order_items ◀── each line carries shop_id
                 │  1:1
                 ├──1:1──> payments ──1:N──> payment_transactions
                 └──1:1──> shipments ──N:1──> shipping_methods

 products ──1:N──> product_images
 products ──1:N──> product_variants ──1:1──> inventories ──1:N──> inventory_transactions
 product_variants ──1:N──> variant_attribute_values ──N:1──> attribute_values
 coupons ──1:N──> coupon_usages ──N:1──> orders
```
**Schema scale:** 30 models · 44 migrations · 26 seeded tables

**The four relationships that carry the design**
1. **Order items are a snapshot** — title, SKU, variant label, unit price and
   image are copied at checkout, so later catalogue edits never rewrite history
2. **`inventories` unique key is `(product_variant_id, shop_id)`** — this
   composite is what makes branch stock independent
3. **`product_variants.sku` is unique per shop, not globally** — two branches may
   legitimately carry the same supplier SKU
4. **`order_items.shop_id`** records the fulfilling branch, derived server-side
   from the variant's parent product — never read from client input

**Migration integrity**
- The multi-branch change was **additive only**: new columns were nullable, then
  backfilled by `php artisan shop:assign-default` into a default branch
- **No data was deleted.** Reported row counts before and after
- A composite index `(product_id, product_variant_id)` was added on `order_items`

**Speaker notes (55 s)**
> "Thirty models across forty-four migrations. I will not show every table — only
> the four relationships that carry the design.
>
> First, order items are a snapshot. When checkout completes we copy the product
> name, SKU, variant label, price and image onto the order line. If we rename the
> product or change its price next month, the old order still shows what the
> customer actually bought. That matters for refunds and for disputes.
>
> Second, the inventory table is unique on variant *and* branch together. That one
> composite key is the entire mechanism behind independent branch stock.
> Third, SKU uniqueness is scoped per branch, so two branches can hold the same
> supplier code. Fourth, every order line records which branch ships it.
>
> On migration integrity, this is the part I would ask an examiner to check. The
> multi-branch change was purely additive — new nullable columns, then a backfill
> command that moved existing rows into a default branch. Nothing was deleted, and
> the command reports the row counts before and after."

---

# SLIDE 9 — Multi-Branch Architecture

**On slide**
```
                    ┌──────────────────────────────────┐
                    │          SUPER ADMIN             │
                    │   one catalogue · one brand      │
                    └────────────────┬─────────────────┘
                                     │
   ┌─────────────────┬───────────────┼───────────────┐
   ▼                 ▼               ▼               ▼
┌──────────┐  ┌──────────┐   ┌──────────┐   ┌──────────┐
│ BRANCH A │  │ BRANCH B │   │ BRANCH C │   │ BRANCH D │
│ own stock│  │ own stock│   │ own stock│   │ own stock│
│ own order│  │ own order│   │ own order│   │ own order│
└──────────┘  └──────────┘   └──────────┘   └──────────┘
   └────────────── shared, single copy ───────────────┘
            categories · brands · products · variants
```

**Four decisions, and the reasoning behind each**

| # | Decision | Why |
|---|---|---|
| 1 | **Shared catalogue, per-branch inventory** | One description edited once. Stock lives in `inventories`, keyed by `(variant, shop)` |
| 2 | **One order, branch-tagged lines** — *not* parent + sub-orders | One brand, one checkout, one payment. Sub-orders would duplicate payment, tax and status logic |
| 3 | **Global taxonomy** — categories and brands are platform-wide | Prevents a customer seeing the same category three times with different contents |
| 4 | **Non-destructive migration** | Nullable columns, then backfill to a default branch. No data deleted |

**Isolation is enforced, not assumed** — 8 policies, each with:
- a `before()` hook granting super admins full access
- branch scoping via the `shop_users` membership table
- **`403` on any cross-branch access** — covered by 35 dedicated tests

**Explicit rule:** `shop_id` is **never** trusted from a request body. The
fulfilling branch is derived server-side from the product being purchased.

**Speaker notes (60 s)**
> "This is the core design decision, so I will take it slowly.
>
> First, the catalogue is shared. We do not copy a product row per branch —
> otherwise the same shirt description is edited five times. The product and its
> variants exist once, and only the stock is per branch, so a phone can be in
> branch A with twenty units and branch B with fifty, completely independently.
>
> Second, there is one order, not a parent order with sub-orders. Each order line
> simply records which branch must ship it. This is far simpler, the customer
> still pays once, and it keeps our existing reports valid. Sub-orders would have
> meant duplicating payment, tax and status logic for no benefit.
>
> Third, categories and brands stay global — otherwise a shopper would see
> 'Electronics' three times with different contents in it.
>
> And the isolation is enforced, not assumed. Eight policies grant super admins a
> bypass and otherwise scope every request to the caller's branches. The rule I
> care most about is that we never read the branch identifier from the request
> body — it is derived from the product, which closes the obvious attack where
> someone edits an ID to read another branch's data."

---

# SLIDE 10 — Backend Layering & the API Surface

**On slide**
```
routes/api.php
     │
     ▼
Controllers  ──────────── 31 total (16 customer + 15 admin)
     │                       thin: parse input, delegate, return a Resource
     ▼
Form Requests ─────────── 22 classes
     │                       ALL validation lives here
     ▼
Services ──────────────── 10 classes   ← business logic lives here
     │  CartService · CatalogService · CheckoutService · CouponService
     │  DashboardService · InventoryService · MediaUploadService
     │  OrderService · OrderNumberGenerator · ReviewService
     ▼
Models  ──────────────── 30 Eloquent models (+ SoftDeletes, HasFactory)
     │
     ▼
API Resources ─────────── 25 classes  → one consistent JSON shape per model
```
**Plus:** 8 Policies registered in `AppServiceProvider::boot()` ·
1 custom middleware (`EnsureUserIsAdmin`, aliased `admin`)

**API surface — 115 routes, counted via `route:list --json`**

| Group | Prefix | Auth | Count |
|---|---|---|---:|
| Public auth | `/api/auth/*` | none | 6 |
| Public catalogue | `/api/catalog/*`, `/api/categories`, `/api/brands` | none | 8 |
| Public branch pages | `/api/shops*` | none | 3 |
| Cart & checkout | `/api/cart/*`, `/api/checkout/*` | optional (`X-Session-Id`) | 9 |
| Customer account | `/api/orders`, `/wishlist`, `/addresses`, `/reviews`, `/account` | `auth:sanctum` | 26 |
| **Administration** | `/api/admin/*` | `auth:sanctum` + `admin` | **63** |
| **Total** | | | **115** |

**Why this layering matters:** business rules are testable without HTTP. The
checkout, inventory, coupon and review rules are all in service classes, which is
why the test suite can exercise them directly instead of only through requests.

**Speaker notes (50 s)**
> "The backend is strictly layered, and the point of the layering is testability.
> Routes are thin. All validation lives in twenty-two Form Request classes — never
> inline in a controller. All business logic lives in ten service classes. Models
> handle persistence, and twenty-five API Resources shape the JSON.
>
> The practical benefit shows up in slide eighteen. Because the checkout and
> inventory rules are in service classes rather than scattered through
> controllers, the test suite can drive them directly, and adding the multi-branch
> behaviour meant editing one class rather than hunting through twenty files.
>
> The API surface is a hundred and fifteen routes, sixty-three of them under the
> admin prefix, and every one of those sixty-three is behind both authentication
> and the admin role middleware."

---

# SLIDE 11 — Catalog — EAV Variants & Media

**On slide**
```
      Category (global)          Brand (global)
              │                        │
              └───────────┬────────────┘
                          ▼
                      Product
            ┌─────────────┴──────────────┐
            ▼                            ▼
      Product Images              Attribute Values
      (gallery, 1:N)              Color → 13 values, each
                                  with a real swatch hex
                                  Size  → 7 values
            │                            │
            └────────────┬───────────────┘
                         ▼
             variant_attribute_values   ← the EAV join
                         │
                         ▼
                Product Variant
                (Midnight + L → one SKU,
                 own price, own stock)
```

**Dynamic variant resolution** — the catalogue API joins
`variant_attribute_values` across the selected attribute values:
select *Color = Midnight* and *Size = L* → exactly **one** `product_variant_id`
returned, with its own price, SKU, image and **live stock level**. The same join
powers the colour and size filter chips on the listing page.

**Media handling** — `MediaUploadService`
- Stores to the `public` disk under `images/{products|brands|categories|avatars}`
- **Deletes the previous file** when an image, logo, or avatar is replaced —
  no orphaned files accumulate
- Rejects non-image uploads via `AdminImageUploadRequest`

**Also implemented:** full CRUD · soft delete + restore · bulk status update ·
meta title / description / SEO keywords · 12 seeded products, 3 images each,
including one deliberately out-of-stock variant

**Speaker notes (45 s)**
> "Variants use an Entity-Attribute-Value design. Attributes are things like
> colour and size; attribute values are the specific options, like midnight or
> size L. A variant is defined by its rows in the join table.
>
> The useful consequence is dynamic resolution. When the customer picks midnight
> and L, the backend joins those two values and returns exactly one variant with
> its own price, SKU and live stock — the frontend never guesses. The same join
> powers the filter chips, so the grid filters by real stock rather than by
> product-level metadata.
>
> Media is handled by a dedicated service. The detail I would highlight is that
> replacing an image or a brand logo deletes the previous file, so we never
> accumulate orphans on disk."

---

# SLIDE 12 — Inventory Reservation Engine

**On slide**
```
  Checkout begins            Payment confirmed           Cancelled / expired
  ────────────────           ──────────────────          ──────────────────
  reserved  += qty           reserved  -= qty             reserved  -= qty
  available -= qty           available -= qty             available += qty
  order = PENDING            available -= reserved        order  = CANCELLED
                             order    = CONFIRMED        coupon usage released
       │                            │
       │     15-minute window       │
       └────▶ scheduler runs every minute
                → expireStaleReservations()
                → release stock, cancel the order
```

**The problem this solves:** decrementing stock at order creation fails twice —
an abandoned payment loses the item **permanently**, and two customers racing
for the last unit cause an **oversell**.

**Every movement is written to a ledger** — `inventory_transactions`
`reserve` · `release` · `deduct` · `adjust`, each recording the acting user, a
reference, and a note. The inventory screen exposes this ledger per item.

**Derived state on `Inventory`**
- `available_quantity = quantity − reserved_quantity`
- `is_low_stock` when `available ≤ low_stock_threshold` (seeded at 5)
- Crossing the threshold fires **one** `LowStockNotification` to admins, guarded
  by `low_stock_notified_at`, then reset on restock

**Cart quantities are clamped to available stock on the server** — a client
cannot request more than exists.

**Speaker notes (60 s)**
> "This is the part of the project I am most satisfied with, and it is worth
> explaining because the obvious approach is wrong.
>
> The naive version decrements stock when the order is created. It fails twice.
> If the customer never pays, the stock is gone forever. And if two customers
> check out the last unit at the same moment, you oversell.
>
> So checkout does not decrement anything. It reserves: reserved goes up, available
> goes down, but the physical quantity is untouched, and the order is created as
> pending. If payment succeeds within fifteen minutes, we deduct for real and move
> the order to confirmed. If the customer cancels — or the scheduler notices the
> order is stale — the reservation is released and the stock returns.
>
> And every one of those movements is written to a transaction ledger with the user
> who caused it. So if a number ever looks wrong, I can always explain exactly why
> it is what it is. The low-stock alert fires once per threshold crossing, not on
> every request."

---

# SLIDE 13 — Order Lifecycle State Machine

**On slide**
```
              ┌──────────┐
              │ PENDING  │   ← stock reserved
              └────┬─────┘
       confirm     │          cancel
     ┌─────────────┴──────────────┐
     ▼                            ▼
┌───────────┐   cancel       ┌───────────┐        ┌──────────┐
│ CONFIRMED ├────────────────▶│ CANCELLED │        │ REFUNDED │
└─────┬─────┘                 └───────────┘        │ (final)  │
  process│   refund                                └────▲─────┘
     ┌───┴───────┐                                     │
     ▼           │            Delivered ── refund ───────┤
┌────────────┐   │                                     │
│ PROCESSING │   │            Shipped ─── refund ───────┘
└─────┬──────┘   │
  ship │   cancel │
     ┌─┴──────┐   ▼
     ▼        ▼
┌──────────┐  ┌───────────┐
│  SHIPPED │  │ CANCELLED │
└────┬─────┘  └───────────┘
  deliver│  refund
     ┌─┴──────┐
     ▼        ▼
┌───────────┐  ┌──────────┐
│ DELIVERED │─▶│ REFUNDED │
└───────────┘  └──────────┘
```
**Legal transitions only** — the map lives in `OrderService`:
```
pending    → confirmed, cancelled
confirmed  → processing, cancelled, refunded
processing → shipped, cancelled
shipped    → delivered, refunded
delivered  → refunded
```
- `PUT /api/admin/orders/{id}/transition` rejects anything else with **422**
- Customer self-cancel is allowed **only** while `pending`
- `shipped` writes a **tracking event** + sends an `OrderStatusNotification`
- Any stock-affecting terminal state **restores inventory** and, if the order was
  paid, sets `payment_status = refunded`

**This is where our QA pass found real bugs.** Cancelling a *paid* order
originally corrupted the payment state, lost the stock, and wrote no refund
record — a **critical** finding. All critical and high findings are now fixed and
locked behind a dedicated regression suite.

**Speaker notes (55 s)**
> "Order status is a strict state machine, not a free-text field. The legal
> transitions are declared in one map inside the order service. You can try to
> jump an order from confirmed straight to delivered through the API and the
> server rejects it with a validation error.
>
> Cancellation and refund are first-class branches, not flags. Customers may
> self-cancel only while the order is still pending, which is the honest rule —
> once a branch has picked and packed, the branch decides.
>
> Two side effects matter. Moving to shipped records a tracking event the customer
> can see, and any terminal state that affects stock restores the inventory and
> marks the payment refunded if money had been taken.
>
> This slide also explains why we ran a formal QA pass. Cancelling a paid order
> originally corrupted the payment state, lost the stock, and wrote no refund. It
> was our one critical finding, and it is exactly the kind of bug that only shows
> up when you write a state machine instead of trusting the frontend."

---

# SLIDE 14 — Cart, Coupons & Multi-Branch Checkout

**On slide**
**Customer journey**
```
Browse & filter → Product detail (variant + live stock)
  → Add to cart ────────────────────────────────┐
  → Apply coupon (validated server-side)        │
  → Checkout: address · shipping · payment      │
  → Stock reserved 15 min, order = PENDING      │
  → Confirm payment → order = CONFIRMED         │
  → Branch picks, packs, ships                  │
  → Delivered → customer may review             ▼
```

**Guest-first cart** — a friction decision that shapes the whole funnel
- `X-Session-Id` UUID generated in the browser; **no account needed to shop**
- On **login or register the guest cart is merged** into the account cart
- The cart is re-fetched when authentication changes, so the drawer and the header
  badge can never disagree

**Totals are presented, never trusted** — the browser displays a figure for the
user's benefit; **the server recomputes everything** at checkout. Anyone can
edit a number in devtools.

**Coupon validation order** — `CouponService`, fixed sequence
```
1. coupon is active          4. per-user usage limit
2. within start / expiry     5. ──▶ apply PERCENTAGE or FIXED discount
3. global usage limit            with optional maximum-discount cap
                                   → record coupon_usages row
```
Usage is **released** on cancel, refund, or reservation expiry.

**Totals:** `subtotal − discount`, **+ 10 % flat tax**, `+ shipping`

**Branch handling at checkout:** every `order_item` is stamped with the `shop_id`
derived from its variant's parent product → **one `orders` row, one payment, many
fulfilling branches.**

**Speaker notes (55 s)**
> "The cart is the highest-stakes screen, so two decisions matter most.
>
> First, guest-first. You can shop with no account at all, because the browser
> generates a session identifier. And at the moment of highest intent — when the
> customer logs in — the guest cart is merged into their account cart. Nothing is
> lost at the exact moment they are most willing to buy.
>
> Second, totals are presented but never trusted. The client shows a number; the
> server recomputes everything at checkout. Anyone can edit a number in devtools,
> so the only number that counts is the one the server calculates.
>
> Coupons are validated in a fixed order: active, then date range, then global
> usage limit, then minimum spend, then per-user limit. Only after all five pass is
> the discount applied. Usage is recorded — and released again if the order is
> cancelled, refunded, or expires, which was another bug our tests caught.
>
> Finally, each order line is stamped with the fulfilling branch, derived from the
> product. One order, one payment, many branches shipping."

---

# SLIDE 15 — Reviews, Notifications & Order Tracking

**On slide**
**Verified-purchase reviews**
```
     Customer submits a review
                │
                ▼
   DB check: does this user have an order containing
   this product with status = DELIVERED ?
                │
          ┌─────┴─────┐
          NO          YES
          ▼            ▼
        403      pending → admin moderation
                        │
             ┌──────────┴──────────┐
          approved             rejected
             ▼                     ▼
   rating_avg & rating_count   Reviewer notified
   recalculated (approved only)
```
- **One review per customer per product** — enforced by the backend
- `verified` flag stored from the source order's status at submission
- Only **approved** reviews count toward the product rating

**Notifications — 5 database-channel types**
`OrderPlaced` · `OrderStatus` · `LowStock` (admins only) ·
`ReviewApproved` · `ReviewRejected`
- Unread badge on the bell in both the admin and account sidebars
- Mark-read per notification, **with ownership enforced**

**Order tracking**
- `tracking_events` written on every `shipped` transition
- Customer stepper at `/order/tracking/{orderNumber}`
- Guests can look up an order by number: `GET /api/orders/guest/{orderNumber}`
  (format `SV-{year}-{6 digits}`, e.g. `SV-2026-000280`)
- **PDF receipt** available to the owner and to admins

**Speaker notes (45 s)**
> "Reviews are not free text from anyone. Before a review is accepted the backend
> checks the database: does this user actually have a delivered order containing
> this product? If not, the request is rejected with a 403. Accepted reviews go
> into a moderation queue, and only approved reviews move the product's average
> rating — a rejected spam review changes nothing. The reviewer is notified either
> way, which is better than silence.
>
> On notifications there are five event types, including the low-stock alert that
> only admins receive. For tracking, each shipment transition writes a tracking
> event and the customer follows a stepper. Guests can also check an order using
> just the order number, which is useful when the buyer and the recipient are
> different people."

---

# SLIDE 16 — Admin Dashboard, Reports & Exports

**On slide**
```
┌─ Admin Dashboard ──────────────────────────────────────────────┐
│  KPI: Revenue · Orders · Customers · Low-stock count           │
│  ┌───────────┬───────────┬────────────┬──────────────┐         │
│  │ Revenue   │ Orders    │ Order      │ Payment      │         │
│  │ trend     │ trend     │ status bar │ status ring  │         │
│  │ (line)    │ (bar)     │            │ (doughnut)   │         │
│  └───────────┴───────────┴────────────┴──────────────┘         │
│  Sales by category ring · Top sellers · Recent orders           │
│  Recent customers · Recent reviews · Recent payments            │
│  Low-stock alert list                                            │
└────────────────────────────────────────────────────────────────┘
```

**Administration modules — 22 views, 6 nav groups, all functional**

| Group | Modules |
|---|---|
| Overview | Dashboard with **5 charts**, custom date range |
| Catalog | Products (CRUD, soft delete + restore, bulk status), Categories, Brands, image upload |
| Fulfilment | Orders + state-machine transitions, Inventory + transaction ledger, Shipments, Payments |
| Marketing | Coupons (percentage / fixed / minimum spend / per-user limits) |
| Community | Review moderation queue (approve / reject / delete) |
| System | Customers, Notifications, Settings |

**Exports — real files, not placeholders**
- **PDF** — `orders.pdf`, per-order receipts, admin receipt (via **dompdf**)
- **CSV** — `orders.csv`, `products.csv`, `payments.csv`
- Chart theming: `useChartTheme` watches the theme store and rewrites Chart.js
  defaults, so all 5 charts recolour on dark-mode toggle

**One table component, seven screens** — `AdminDataTable.vue` (294 lines) is
reused by products, orders, customers, coupons, reviews, brands, and shipping.
Columns are typed (`text | number | status | badge | currency | date | image |
actions`) so a currency cell cannot be rendered as plain text.

**Speaker notes (45 s)**
> "The admin side is a different design problem — dense tables, not browsing. The
> dashboard has KPI cards and five charts, all from Chart.js: revenue trend, order
> trend, order status distribution, payment status ring, and sales by category.
>
> The sidebar is grouped into six sections, and there are no placeholder pages —
> everything listed is functional. For reporting we generate real PDFs with dompdf
> for orders and per-order receipts, and CSV for orders, products and payments.
>
> The one component worth pointing at is the admin data table. It is two hundred
> and ninety-four lines and I reused it for seven different screens. Two things
> make that reuse safe: columns are typed, so a currency cell cannot silently
> render as text; and the table owns the three states usually forgotten — a
> loading skeleton that renders the real column count, an empty state, and
> pagination."

---

# SLIDE 17 — Security & Authorization

**On slide**
**Request flow — three gates before the controller**
```
  Request
     ▼
  Sanctum bearer token ────── invalid ──▶ 401
     ▼
  EnsureUserIsAdmin ──── not admin ─────▶ 403
     ▼
  Policy check (8 policies) ── wrong branch ─▶ 403
     ▼
  Controller
```

| Layer | Implementation |
|---|---|
| Authentication | Sanctum personal access tokens; **revoked on logout and on password reset** |
| Email verification | `MustVerifyEmail` + signed URL, `hash_equals` comparison, resend gating |
| Password security | bcrypt; a legacy non-bcrypt hash returns "invalid credentials", **not a 500** |
| Global RBAC | `admin` middleware guards the entire `/api/admin/*` prefix — all 63 routes |
| Branch RBAC | 8 policies, each with a `before()` super-admin bypass + `shop_users` scoping |
| IDOR prevention | Orders, addresses, wishlist, cart, notifications are **owner-scoped by query**, never by input |
| Input validation | 22 Form Request classes; negative price/quantity → **422**, never a SQL error |
| Password reset | Signed, expiring, **non-enumerating** response; revokes all existing tokens |
| File uploads | Non-image rejected; old file deleted on replace; Nginx denies dotfiles |

**The rule that matters most:** `shop_id` is **never** read from the request
body. The fulfilling branch is derived server-side from the product being
purchased. This closes the obvious IDOR attack — editing a URL to read another
branch's data.

**Independent QA audit** (`docs/QA-REPORT.md`)
| Severity | Count | Status |
|---|---:|---|
| Critical | 1 | **fixed** |
| High | 5 | **fixed** |
| Medium | 13 | deferred |
| Low | 12 | deferred |
| Security findings | 7 | reviewed |

All critical and high findings are locked behind a dedicated regression suite of
**10 tests** in `QaHotfixTest.php`.

**Speaker notes (55 s)**
> "Security is layered, and every layer is on the server. The client is never
> trusted.
>
> First, Sanctum issues a bearer token which is revoked on logout and on any
> password reset. Second, an admin middleware guards the entire admin prefix —
> all sixty-three routes. Third, eight policies enforce branch-level access, each
> with a super-admin bypass.
>
> The rule I care most about is that we never read the branch identifier from the
> request body. It is derived from the product. That closes the obvious attack
> where someone edits an ID in the URL to read another branch's private data.
>
> We also commissioned an independent QA pass over the whole codebase. It found
> one critical and five high severity bugs. The critical one was that cancelling
> a paid order corrupted the payment state and lost stock. All of those are fixed
> now and locked behind ten regression tests, so they cannot come back silently."

---

# SLIDE 18 — Testing & Quality Assurance

**On slide**
```
   docker compose exec -T app php artisan test

   Tests:   167 passed (729 assertions)
   Duration: 215.51 s          ← executed live, all green
```

**16 test classes — what each group proves**

| Test class | Tests | Focus |
|---|---:|---|
| `AuthTest` | 21 | register, login, logout, reset, full email-verification flow, token revocation |
| `ShopTest` | 19 | branch CRUD, cross-branch **403**, staff scoping, inventory isolation, soft delete |
| `AdminOpsTest` | 16 | dashboard, payments, shipments, notifications, CSV export, bulk update, restore |
| `ShopOwnershipTest` | 16 | `shop_id` stamping, policy bypass rules, per-branch SKU uniqueness |
| `AdminTest` | 11 | admin guards, state machine, refund path, PDF downloads |
| `AdminMediaTest` | 11 | upload guards, gallery sync, logo replacement, old-file deletion |
| `QaHotfixTest` | 10 | regression suite for every QA finding |
| `CheckoutTest` | 10 | reserve → confirm → deduct, cancel release, double-confirm, guest checkout |
| `AdminInventoryTest` | 9 | stock filters, ledger, `adjust` transaction logging |
| `CartTest` | 9 | guest session carts, stock clamping, cart merge on login |
| `CatalogTest` | 9 | filters, sort, search, facets, variant + gallery detail |
| `AccountAndSettingsTest` | 7 | profile, avatar, settings, tracking events |
| `CouponTest` | 7 | all five validation rules, both discount types |
| `NotificationTest` | 5 | all 5 notification types, low-stock fires exactly once |
| `ReviewTest` | 4 | verified-purchase enforcement, one-per-user, rating recalculation |
| `ScheduleTest` | 3 | reservation expiry runs, and leaves fresh reservations alone |
| **Total** | **167** | |

**Honest scope — say this out loud**
- Tests run against **in-memory SQLite**, not MySQL
- **No** load test at 1,000 / 10,000 products was executed
- **No** browser-level UI automation (no Playwright in the environment)
- `tests/Unit` is configured but **empty** — all coverage is feature-level API tests

**Also verified:** frontend `vue-tsc -b && vite build` compiles with **0 errors** ·
`en.json` and `km.json` key parity confirmed · Laravel Pint clean on new files

**Speaker notes (55 s)**
> "Quality here is measured, not claimed. I ran the suite in Docker immediately
> before this presentation: one hundred and sixty-seven tests, seven hundred and
> twenty-nine assertions, all passing, in about three and a half minutes.
>
> The suite is organised by feature, and the two largest groups are the branch
> tests — nineteen and sixteen — because cross-branch isolation is the riskiest
> part of the design. Checkout is covered end to end: reserve, confirm, deduct,
> cancel and release, and rejecting a double confirm. And there is a dedicated
> regression file for the ten bugs the QA pass uncovered.
>
> I also want to be clear about what is not covered. These tests run against
> in-memory SQLite rather than MySQL. I did not run a load test at a thousand or
> ten thousand products, because that would have mutated the demo database — it
> needs a staging dataset. There is no browser automation, and the unit-test
> suite directory is configured but empty, so all our coverage is at the API
> level. The frontend does compile with zero type errors."

---

# SLIDE 19 — Performance Results

**On slide**
**Measured cold vs warm** — `curl` through the Nginx container
(`docs/Performance-Report.md`)

| Endpoint | Cold (first hit) | Warm | Gain |
|---|---:|---:|---|
| `GET /api/categories` | ~4.7 s | **6–8 ms** | ~600× |
| `GET /api/brands` | ~9.6 s | **6–7 ms** | ~1300× |
| `GET /api/shipping-methods` | ~5.2 s | **5–6 ms** | ~900× |
| `GET /api/catalog/featured` | ~5.1 s | **6–11 ms** | ✔ |
| `GET /api/catalog/facets` | ~3.9 s | **8 ms** | ✔ |
| `GET /api/admin/inventory` | **HTTP 500** | **200 OK** | bug fixed |

**What produced the gain — in order of impact**
1. **OPcache** (mounted `opcache.ini`) — removes per-request recompilation of the
   whole framework, which was the dominant time-to-first-byte cost
2. **Nginx gzip** — CSS, JS, JSON, SVG, XML, PDF
3. **Redis caching of stable public data only** — category tree (24 h TTL), brands,
   shipping methods — with `Cache::forget` invalidation in the three admin
   controllers that mutate them.
   **Cart, orders, checkout and reports are deliberately NOT cached** — stale
   money is worse than slow money
4. **Composite index** on `order_items (product_id, product_variant_id)`

**Also fixed:** `GET /api/admin/inventory` returned **500** on a soft-deleted
product (`Attempt to read property "id" on null`) — caught by testing, now 200.

**Not done:** no caching of featured / facets / products · no load test ·
no browser bundle-size audit

**Speaker notes (50 s)**
> "These are measured numbers, not targets. Cold — the very first request after a
> container start — took between four and ten seconds, and almost all of that was
> framework boot time.
>
> Enabling OPcache was by far the biggest win. Once the cache was warm, the same
> endpoints respond in six to eight milliseconds, which is roughly a thousand
> times faster. Nginx gzip and Redis caching of the stable reference data
> completed the work, and I added one composite index on the order-items table.
>
> The deliberate decision worth flagging is what I did *not* cache. I cache the
> category tree, brands and shipping methods, because they change rarely. I do
> not cache carts, orders, checkout, or reports. Serving a stale price or a stale
> stock count is far worse than serving it a few milliseconds slower.
>
> The admin inventory endpoint also used to return a 500 error when a product had
> been soft-deleted. Testing caught that, and it now returns 200."

---

# SLIDE 20 — Challenges, Limitations & Future Work

**On slide**
**Challenges and how we solved them**

| Challenge | Solution |
|---|---|
| Multiple branches on one catalogue | Branch ownership on rows + composite inventory key `(variant, shop)` |
| Cross-branch data leakage | 8 policies with a `before()` admin bypass; `shop_id` never read from input |
| Inventory consistency | Reserve-then-deduct, 15-min window, scheduler expiry, full transaction ledger |
| Multi-branch checkout | One order, `order_items.shop_id` stamped server-side from the variant's product |
| Order integrity | Strict state machine; illegal transitions rejected with 422 |
| Large datasets | Pagination, eager loading, composite index, Redis for stable public reads |
| Slow cold start | OPcache + gzip → 4–10 s down to 5–11 ms |
| Payments without a gateway | `payment_mode` config gate; COD in production, non-COD blocked unless sandbox |
| Documentation drift | QA pass recorded it; docs corrected |

**Limitations we are honest about**
| # | Limitation | Reason |
|---|---|---|
| 1 | **No payment gateway** | No provider SDK; non-COD confirmation is blocked in production mode |
| 2 | **Branch staff cannot sign in** | `shop_users` is empty; the `admin` middleware admits only `role = admin` |
| 3 | **No branch management UI** | The 6 `/api/admin/shops` endpoints work, but no Vue screen calls them |
| 4 | **No commission / payouts** | Only a reserved `shops.commission_rate` column exists |
| 5 | **No rate limiting** | Flagged in QA, deferred |
| 6 | **No load test at scale** | Needs a staging dataset |
| 7 | **No browser automation** | No Playwright in the environment; `tests/Unit` empty |
| 8 | **No 404 view** | Bad URLs silently redirect to `/` |

**Future work, in priority order**
1. Seed `shop_users` + add the permission layer → build the branch-staff dashboard
   *(policies already written and tested — mostly UI work)*
2. Build the `/admin/shops` management screen *(endpoints already exist)*
3. Integrate a real payment gateway
4. Commission and payout ledger
5. Wire the two unused skeleton loaders → the storefront stops loading blank
6. `prefers-reduced-motion` + focus trap in `BaseModal` + a 404 view
7. Load testing and browser automation in CI
8. Extend public branch storefronts to the Vue app

**Speaker notes (55 s)**
> "I want to close honestly, on both what was hard and what is missing.
>
> The hardest problems were all about correctness across branches. The most
> instructive was the reservation lock — the obvious approach of decrementing
> stock at order creation fails in two separate ways, and getting it right forced
> us to keep a full audit ledger. The second was the order state machine, because
> once status is a real state machine with side effects, cancelling a paid order
> became a genuine bug that only surfaced when we tested it.
>
> On the right are eight limitations. The two I would most like to fix next are
> that branch staff cannot yet sign in, and that there is no branch management
> screen. In both cases the backend is written and tested — the authorisation
> policies and the six admin endpoints all work. What is missing is the
> interface, and the membership data. That is genuinely a UI task, not a redesign.
>
> I would also like to be clear that we do not have a real payment gateway. We
> support cash on delivery, and card confirmation is deliberately blocked in
> production mode rather than pretending to work."

---

# SLIDE 21 — Project GUI

**On slide** — screenshot gallery, 3 × 2 grid
```
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│                 │ │                 │ │                 │
│  SCREENSHOT 1   │ │  SCREENSHOT 2   │ │  SCREENSHOT 3   │
│                 │ │                 │ │                 │
│  Home / Hero    │ │  Shop + filters │ │  Product detail │
│                 │ │  + swatches     │ │  variant picker │
└─────────────────┘ └─────────────────┘ └─────────────────┘
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│                 │ │                 │ │                 │
│  SCREENSHOT 4   │ │  SCREENSHOT 5   │ │  SCREENSHOT 6   │
│                 │ │                 │ │                 │
│  Cart drawer    │ │  Checkout +     │ │  Admin dashboard│
│  or cart page   │ │  order summary  │ │  + 5 charts     │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```
Below the grid, one line:
```
Laravel 13 REST API  ·  Vue 3 + TypeScript  ·  MySQL 8  ·  Redis  ·  Nginx  ·  Docker
```

**Capture checklist — do all of these before the defence**

| # | Screen | Route | What must be visible |
|---:|---|---|---|
| 1 | Home | `/` | hero, category tiles, product rail |
| 2 | Shop + filters | `/shop` | **colour swatch filters, removable filter chips, sort** |
| 3 | Product detail | `/product/aurora-wireless-headphones` | **variant selector, live stock badge, rating** |
| 4 | Cart | `/cart` | line items, quantity stepper, **coupon applied state**, sticky summary |
| 5 | Checkout | `/checkout` | stepper, address selection, payment method, order summary |
| 6 | Admin dashboard | `/admin/dashboard` | KPI cards + at least 3 of the 5 charts |
| 7 | Admin inventory | `/admin/inventory` | **the stock ledger** — reserved / available columns |
| 8 | Order tracking | `/order/tracking/SV-2026-000100` | the 5-stage stepper |

**Shooting rules**
| Item | Requirement |
|---|---|
| Aspect ratio | **16:10 or 16:9** — crop browser chrome, not the page |
| Resolution | **1600 px wide minimum**; a soft screenshot looks worse than none |
| Zoom | Browser at **80–90 %** so the full page fits without shrinking text to nothing |
| Dark mode | Shoot **one light and one dark** pair (the theme toggle exists) to show the token system |
| Empty states | Include one empty state, e.g. an empty wishlist — it shows design thinking |
| Seeding | Use the demo account `admin@ekhmer.dev` / `password`; customers are `olivia.bennett@example.com` / `password` |
| Privacy | Blur or use seeded fake names only — never real customer data |
| Border | 1 px `#E5E7EB` frame per screenshot, so edges are visible on a projector |

**If you can only show four**, pick **2** (faceted filters), **3** (variant
selector + live stock), **6** (dashboard + charts), and **7** (inventory ledger).
Those four demonstrate the most engineering: dynamic variant resolution, the
data model, the analytics layer, and the reservation engine.

**Speaker notes (40 s)**
> "Finally, the actual application. I have ordered these so the story runs left to
> right, top to bottom — discovery, then detail, then purchase, then
> administration.
>
> The shop page shows the faceted filters with real colour swatches; those swatch
> colours come from the API, and filtering by one returns exactly the variants
> that have it, with live stock. The product page shows the variant selector —
> choosing a colour and a size resolves to one specific variant and its real
> stock level.
>
> The admin inventory screen is the one I would draw your attention to. Those
> reserved and available columns are the reservation engine from slide twelve,
> visible in the interface, with a transaction ledger underneath that records
> every movement and who caused it.
>
> The dark-mode toggle in the top right switches the entire interface, which is
> possible because the colour palette is defined in ten CSS variables. Thank you."

---

# Appendix A — Likely Defence Questions

| Question | Answer |
|---|---|
| Why not JWT? | Sanctum is first-party, needs no identity server, and covers the need. I know long-lived tokens need an expiry strategy — `expiration` is currently `null` — and that is planned work. |
| Why one order, not parent + sub-orders? | One brand, one checkout, one payment. Tagging each `order_items.shop_id` preserves per-branch reporting without a second order system. Sub-orders would duplicate payment, tax and status logic. |
| How do you prevent overselling? | Reservations. `reserved_quantity` rises at checkout so a second customer sees availability fall. Real deduction happens only on payment confirmation; the scheduler releases stale reservations every minute. |
| What stops a branch reading another branch's data? | 8 policies with a `before()` admin bypass and branch scoping. Two dedicated test files add 35 tests, most asserting a 403 on cross-branch access. |
| Is the frontend multi-branch? | No. Backend and policies are; the Vue UI is single-brand. That is the top future item, not a hidden gap. |
| Why 15 minutes? | Configurable in `config/ecommerce.php` as `reservation_minutes`. It matches a realistic card-payment window and cart-abandonment behaviour. |
| How are fake reviews prevented? | A delivered-order check in the database, plus admin moderation, plus only approved reviews counting toward the rating. |
| How do you stop the client sending a fake price? | All totals are recomputed server-side at checkout. The client value is display only. |
| Why cache some endpoints but not others? | Category, brand and shipping data change rarely, so they are cached with invalidation. Cart, orders, checkout and reports are not cached — stale money is worse than slow money. |
| What is not production-ready? | No real payment gateway, no rate limiting, no load test, no browser automation, empty `tests/Unit`, branch staff cannot authenticate, and there is no 404 view. All listed on slide 20. |
| Why does the README say Bootstrap? | Documentation drift caught in our QA pass. The project actually uses Tailwind CSS 3.4.19 and Chart.js — there is no Bootstrap in `package.json`. |

---

# Appendix B — Design Specifications

**Layout**
- 16:9 · one idea per slide · heading ≥ 32 pt · body ≥ 20 pt
- 8 pt spacing grid · identical title position on every slide
- Slide number bottom-right · project name bottom-left

**Colour — take the palette from the application itself**
```
primary     #2563EB      primary-dark #1E40AF
canvas      #F9FAFB      surface      #FFFFFF
ink         #111827      muted        #6B7280
border      #E5E7EB      accent       #FBBF24
success     #10B981
```
- No gradients **on the slides.** (The app does use one hero gradient —
  `#1E3A8A → #0F172A`. Expect it in the GUI screenshots; do not claim the app has
  no gradients anywhere.)
- Two elevation values only, matching the app: a soft card shadow and a popover
  shadow. Nothing heavier.

**Typography**
- **Inter** for the deck (matches the app UI face)
- **Kantumruy Pro** only for a Khmer wordmark — never for body copy
- On a Khmer slide, set line-height ≥ 1.6 or the diacritics clip — this is the
  same constraint the application solves in `src/style.css`

**Icons** — lucide-vue-next, 2 px stroke, matching the app. Do not use Bootstrap
Icons; the project does not use Bootstrap.

**Diagrams**
- Blue `#2563EB` strokes, white fills, plain boxes and arrows
- Maximum 3 levels of depth, except the branch tree which is inherently 2
- **Label every arrow** — an unlabelled arrow is an examiner's question
- Do not redraw a diagram already shown on an earlier slide

**Content rules — these are not stylistic preferences**
- **No invented statistics.** Nothing in this project was A/B tested. No
  "conversion increased by 18%", no fake user counts, no fabricated revenue.
- Every number in this deck was measured; the method is recorded at the top
- **No fake screenshots** — use the marked placeholders and the capture checklist
- **Never present a limitation as a shipped feature.** Specifically: commission,
  payouts, vendor registration, branch management UI, and branch staff login are
  **not built**
- If asked about a gap, name it yourself and point at slide 20. Volunteering your
  own limitations reads as competence, not weakness
