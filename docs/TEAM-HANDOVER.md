# E-KHMER — Team Handover

Complete project walkthrough, implementation record and operating guide

**Prepared:** 19 October 2026 · **Baseline:** `f950a779c0517472ac62321beda890db0639dfc5` · **Audience:** development, QA, product and operations teams.

This document explains the implementation visible in the repository and the reasons its main mechanisms matter. It describes project work rather than claiming that one person authored every change. Commit references establish a change history; personal contribution ownership must be confirmed with the contributors. The QA results cited here were collected earlier on 19 October and are recorded in `docs/QA-REPORT.md`; generating this document does not rerun them.

## 1. Start here: what the team is receiving

E-KHMER is a full-stack marketplace application. Customers discover products from multiple shops, add specific product options to a cart, choose delivery and place an order. Shop owners and managers maintain their own catalog, inventory and fulfillment. A super administrator manages platform-wide records and operations.

The implementation extends beyond a storefront prototype. The backend persists orders, stock reservations, shop allocations, payment verification, returns, refunds and support. The frontend provides separate customer, seller and administration workspaces. Docker configuration, scheduled maintenance, queue processing, backups and CI are supplied alongside the application.

**Current boundary:** implemented software and automated checks are available. Real bank transfers, production email, remote deployment, physical-device scanning and operational acceptance still require completion. Checkout currency is USD. The platform does not hold seller balances or send bank payouts.

### How to read this document

| Team member | Suggested reading order |
| --- | --- |
| New teammate | Chapters 1–5, then local setup in Chapter 11 and the demonstration in Chapter 16. |
| Frontend developer | Chapters 4–6, 9, 12 and 13; use the source map in Chapter 18. |
| Backend developer | Chapters 4, 7–10, 12–14 and the database catalog. |
| QA / product | Feature walkthrough, business examples, verification limits and the remaining-work checklist. |
| Operations | Chapters 11, 14, 15 and 17; follow the detailed Oracle deployment guide before release. |

The Word document is an export of this Markdown source. Update the source first, then run `python docs/tools/build_team_handover.py` from the repository root to regenerate the Word file. This exporter uses Python's standard library; no application dependencies or services are needed.

## 2. Work completed and recent change history

The table records significant repository changes and their user-visible effect. It is not an exhaustive commit list or a production release certificate.

| Date / commit | Work delivered | Result for users and teammates |
| --- | --- | --- |
| 18 Oct · `f950a77` | Product-form stock editing and regression cases. | Sellers can change stock for a simple product or individual options. Unrelated edits preserve stock; reservations cannot be overwritten. |
| 18 Oct · `cf9aca5`, `162f063` | Consistent demo seed data and `.com` account domains. | Repeatable related records make local demonstrations easier; old login/reset/session data is cleared during reseeding. |
| 18 Oct · `1520333` | Shared layout alignment. | Store, account, seller and admin pages use consistent content widths, covered at desktop and mobile sizes. |
| 17 Oct · `5187a45` | Full-frame barcode scanning and torch constraints. | Labels near the frame edges or rotated vertically can be decoded; native detection and background processing reduce main-thread work. |
| 17 Oct · `245d32a` | Seller camera barcode workflow. | Camera input fills the selected barcode field with permission guidance and cleanup; the seller reviews and explicitly saves. |
| 17 Oct · `2a75d37` and related commits | Seller commerce, public shops, verified Bakong, delivery, returns, refunds, support and deployment tooling. | Multi-shop orders have persistent financial and fulfillment records instead of UI-only success messages. |
| 7 Oct · `7ec2658`, `ee3a59e`, `b79593e` | Admin dashboards, receipt layout and notification improvements. | Teams can inspect operational summaries, branded receipts and configured order notifications. |

Earlier work provides authentication, catalog, cart, checkout, wishlist, addresses, coupons, reviews and administration. For a detailed change investigation, run `git log --oneline` and `git show <commit>`; inspect final source rather than assuming an older commit message describes every current detail.

### Important differences from the original agenda

Authentication uses Sanctum personal access tokens, not JWT refresh/access rotation. Styling currently uses Tailwind, although root project guidance still mentions Bootstrap. Shop access comes from membership in addition to platform roles. An order can have several shop allocations and shipments. Online production checkout uses verified Bakong; generic card/bank/gateway labels are legacy sandbox paths, not integrated production providers.

## 3. Technology stack and responsibilities

Versions below come from current manifests/locks or the earlier container verification. A package range is a declared requirement, not a claim about every installed environment.

| Technology | Version / source | Responsibility |
| --- | --- | --- |
| Laravel | 13.37.0 in `backend/composer.lock` | REST API, validation, authorization, ORM, notifications and scheduling. |
| PHP | Project requires 8.3+; checked container ran 8.3.33 | Backend runtime inside Docker. |
| Sanctum | `^4.0` requirement | Bearer-token authentication with configurable expiry and revocation. |
| Vue / TypeScript | Vue `^3.5.41`, TypeScript `~6.0.2` | Reactive UI with typed models and Composition API. |
| Vite / Vue Router / Pinia | Vite `^8.2.2`, router `^5.3.0`, Pinia `^4.0.3` | Build/dev server, navigation and shared frontend state. |
| Axios / Tailwind | Axios `^1.20.0`, Tailwind `^3.4.19` | Shared HTTP client and utility-class styling. |
| Chart.js / Dompdf | Chart.js `^4.5.1`, Dompdf Laravel integration `^3.1` | Dashboard charts and server-rendered PDF documents. |
| ZXing | Browser 0.1.5, library 0.21.3 | Lazy-loaded camera barcode decoding and worker fallback. |
| MySQL / Redis | MySQL 8 image; Redis container | Persistent business data and configurable cache/session/queue storage. |
| Docker / Nginx / Caddy | Compose services and production configuration | Reproducible execution, HTTP routing and production HTTPS. |
| PHPUnit / Playwright | PHPUnit `^12.5.12`, Playwright `^1.64.1` | Backend regressions and desktop/mobile Chromium UI checks. |

Local `.env.example` defaults may use database-backed cache, sessions and queues. Production Compose explicitly selects Redis. The presence of a Redis container does not mean every development subsystem automatically uses it.

## 4. Architecture and repository walkthrough

### Request flow

```text
Customer / seller / administrator browser
                  |
            Vue 3 application
       Router + Pinia + Axios client
                  | JSON /api requests
           Vite proxy locally
                  |
             Nginx -> PHP-FPM
                  |
    Routes -> Middleware -> Form Request
                  |
       Controller -> Domain service
                  |
         Eloquent -> MySQL
                  |
      API Resource -> JSON -> Vue

Scheduler -> expire reservations / reconcile payments
Worker    -> configured queued jobs and notifications
Laravel   -> Bakong API / mail transport when configured
```

In production, Caddy terminates HTTPS and forwards traffic to the web image. The frontend build and API share an origin. Locally, Vite exposes port 5174 on the host and proxies `/api` to Nginx. PHP-FPM is an internal service, not the browser's HTTP endpoint.

### Code map

| Location | What belongs here |
| --- | --- |
| `backend/routes/api.php` | API endpoints, authentication groups and throttles. |
| `backend/app/Http/Controllers/Api` | HTTP handling; seller/admin adapters live in subdirectories. |
| `backend/app/Http/Requests` | Input validation and request preparation. |
| `backend/app/Services` | Checkout, inventory, payment, refund and other domain rules. |
| `backend/app/Models`, `Policies` | Relationships, casts, scopes and authorization decisions. |
| `backend/app/Http/Resources` | Public JSON contracts and exclusion of private fields. |
| `backend/database/migrations`, `seeders`, `factories` | Schema history, demo data and test records. |
| `backend/routes/console.php` | Scheduled reservation/payment work and operational commands. |
| `frontend/src/views`, `layouts`, `components` | Screens, page shells and reusable UI. |
| `frontend/src/api`, `types` | Typed HTTP wrappers and shared data contracts. |
| `frontend/src/stores` | Auth, cart, wishlist, locale, theme, UI and seller workspace state. |
| `frontend/src/workers`, `utils` | Barcode worker and reusable client logic. |
| `frontend/src/locales` | English and Khmer translation dictionaries. |
| `docker`, Compose files, `.github/workflows` | Runtime configuration, deployment, backup scripts and CI. |
| `docs` | Handover, API, architecture, frontend, QA and operating documentation. |

The backend owns money, stock, eligibility and authorization. Vue displays results and gathers input. Changing a frontend badge must never be treated as changing an order/payment state on the server.

## 5. Roles, permissions and data ownership

| Actor | Access model | Practical boundary |
| --- | --- | --- |
| Guest | Public routes plus browser-held session ID for cart/guest checkout. | Can browse and purchase without accessing another session's order. Treat the session identifier as a bearer secret. |
| Customer | Authenticated user whose base role is `customer`. | Can maintain their own profile, addresses, wishlist, orders, eligible returns and reviews. |
| Shop owner | Active `shop_users` owner membership. | Can manage that shop and change its banking details under the settings rules. |
| Shop manager | Active `shop_users` manager membership. | Can operate the shop workspace; changing the payment recipient is restricted. |
| Super admin | Base role `super_admin`; `User::isAdmin()` checks this role. | Can use platform administration and relevant policies across shops. |

The legacy `admin` constant still exists, but current platform administrator checks use `super_admin`. A seller is normally a customer with shop membership, not a separate global seller role.

`EnsureManagedShop` validates membership, checks product/inventory/shop-order ownership and supplies server-validated shop context. A request cannot select another seller's shop by changing a body `shop_id`. Frontend navigation guards improve the user experience; Laravel middleware and policies are the access-control boundary.

Only trusted provisioning code should assign privileged roles. `User` excludes role and email verification from ordinary mass assignment. Private bank configuration, purchase costs and provider data must remain excluded from public resource representations.

## 6. Feature walkthrough and team demonstration paths

### Customer experience

| Capability | Screen / route | What it does |
| --- | --- | --- |
| Discover products | `/`, `/shop`, `/product/:slug` | Browse/search/filter, inspect options/images, see price and availability. |
| Discover sellers | `/shops`, `/shops/:slug` | View active shop profiles and search within one shop. |
| Purchase | `/cart`, `/checkout` | Review selected variants, address, shipping by shop, coupon and payment method. |
| Pay / track | `/checkout/:orderNumber/payment`, `/account/orders` | Check separate shop payments and inspect order/allocation progress. |
| Manage account | `/account/profile`, `/account/addresses`, `/account/password` | Update personal information, delivery addresses and credentials. |
| Engage after purchase | `/account/reviews`, `/account/returns`, `/account/support` | Review delivered purchases, request eligible returns and read support replies. |
| Saved items / alerts | `/account/wishlist`, `/account/notifications` | Retain desired products and inspect account notifications. |

The cart holds a variant ID and quantity. A product is the public listing; a variant is the exact sellable option such as a blue shirt in size L. Availability is rechecked during checkout, so adding an item to a cart is not a permanent stock guarantee.

### Seller experience

Start at `/seller`, select a shop, then use `/seller/shops/:id`. Products, inventory, orders, payments, returns and settings have dedicated screens. Settings includes a public-shop link; the numeric management ID and public shop slug are different identifiers.

Sellers can create simple products or options, set purchase and selling prices, upload/reorder/remove photos, capture a photo, enter or scan barcodes, manage total stock and process their shop's fulfillment. Payments shows verified receipts and refund actions. An independently shipped shop allocation does not mark every other seller's delivery complete.

### Platform administration

The `/admin` workspace includes dashboard summaries/charts, catalog/categories/brands, shops and members, customers, inventory, orders/shipments, coupons, reviews, payments, settings, returns, support and CSV/PDF reports. The dashboard is a view of persisted records, not a bank settlement statement. Current COD collection and some report-validation gaps are listed in Chapter 17.

## 7. Database design and important relationships

Migrations are authoritative. The following catalog groups all 37 business tables listed by `DatabaseSeeder::TABLES`; framework authentication/cache/queue tables are separate.

| Tables | Important data / relationships |
| --- | --- |
| `users`, `addresses` | Accounts, password hashes and contact data; saved addresses belong to users. |
| `shops`, `shop_users` | Shop profile/status/private recipient settings; membership links users to shops with a role and status. |
| `categories`, `brands`, `products`, `product_images` | Product taxonomy, shop ownership, listing data and ordered gallery entries. Categories can have parent categories. |
| `attributes`, `attribute_values`, `variant_attribute_values`, `product_variants` | Attribute definitions, possible values, option-to-value links and exact sellable SKUs/barcodes/prices/costs. |
| `inventories`, `inventory_transactions` | One stock row per variant; on-hand/reserved/sold quantities and shop/actor-linked movement history. |
| `carts`, `cart_items`, `wishlists`, `wishlist_items` | User or guest shopping state; cart lines reference variants and wishlist lines reference products. |
| `orders`, `order_items`, `shop_orders` | Parent purchase, immutable item snapshots and one seller allocation per order/shop. |
| `payments`, `payment_transactions`, `bakong_payments` | Aggregate payment, capture/refund transaction history and persistent shop-specific QR/recipient/amount snapshots. |
| `shipping_methods`, `shipments`, `tracking_events` | Delivery configuration, shop allocation shipment data and timestamped lifecycle history. |
| `coupons`, `coupon_usages` | Promotion rules, limits/counters and order-linked reservations of coupon usage. |
| `reviews`, `notifications` | Verified-purchase moderation and stored user messages. |
| `order_returns`, `payment_refunds` | Item quantities/evidence/return state and uniquely referenced audited refunds. |
| `checkout_attempts` | Owner-scoped request key, payload hash and order link for retry-safe checkout. |
| `support_requests`, `newsletter_subscriptions`, `settings` | Persisted support conversations, consent/unsubscribe state and application configuration. |

### Relationship map

```text
users --< addresses
users --< shop_users >-- shops --< products
products --< product_images
products --< product_variants -- inventories --< inventory_transactions
product_variants --< variant_attribute_values >-- attribute_values >-- attributes
users / guest session -- carts --< cart_items >-- product_variants
users -- wishlists --< wishlist_items >-- products
users --< orders --< order_items >-- product_variants
orders --< shop_orders >-- shops
shop_orders --< order_items
shop_orders -- shipment / tracking events / Bakong payment
orders -- payments --< payment_transactions
orders --< coupon_usages >-- coupons
order_items --< order_returns -- payment_refunds
orders --< payment_refunds
users --< reviews >-- products
```

`--<` means one-to-many; singular links describe the intended model relationship rather than promising every historical row has a corresponding record. Legacy orders can retain parent-level shipment data. Do not reduce the current schema to one shipment per parent order.

### Data rules that preserve history

`order_items` stores the purchased name, variant label, SKU, image, unit price, quantity and line total. Orders snapshot addresses and totals. Shop allocations snapshot their financial share. Bakong records snapshot the recipient, amount, currency and QR. Editing a catalog price, customer address or seller bank account must not rewrite a past transaction.

Money uses decimal database columns and explicit rounding. Allocation and return code handles cent remainders; do not replace that logic with independent client-side rounding. Schema changes require migrations, and tests/factories/seeders must evolve with the model.

## 8. Core workflows: authentication, catalog and inventory

### Authentication and session handling

Registration creates a customer, schedules verification email after the response and attempts to merge the guest cart. Login validates the stored hash, merges guest items within available stock and issues a Sanctum token. The client stores `access_token`, attaches a bearer header and supplies `X-Session-Id` for guest state. Logout revokes the current token; password reset revokes the user's tokens.

Global token expiry is configured by `SANCTUM_TOKEN_EXPIRATION_MINUTES`. Login can also issue an explicit remembered-token expiry. There is no implemented JWT refresh endpoint simply because a frontend storage key mentions a refresh token. Auth endpoints have explicit throttles. A 401 clears frontend authentication state; bad credentials are validation errors rather than authenticated-session expiry.

### Products, options and scanner behavior

`SellerProductController` adapts the seller request into shared product handling. A simple listing gets a default variant; option listings have independent variant rows and attribute values. A null variant selling price falls back to the product price where supported. Purchase cost is private and must not be serialized in a public catalog response.

Barcode strings preserve leading zeros. Typing and USB/Bluetooth keyboard scanners use the same input; scanner Enter does not accidentally submit the form. Camera scanning needs HTTPS or localhost and permission. `SellerBarcodeField.vue` uses native detection when supported, then a background ZXing worker or compatibility fallback. Camera frames are processed locally; results fill only the selected field and need an explicit save. The 80 ms loop target is not a physical-device speed guarantee.

Photo upload supports JPEG/PNG/WebP optimization and orientation handling; animated GIFs are preserved. Relevant limits include 5 MB per image, 10 MB combined newly uploaded product images, 20 gallery entries and 12.5 megapixels. There is no crop editor, independent thumbnail pipeline or HEIC transcoder. Check both request validation and reverse-proxy upload limits when changing this flow.

### Inventory reservation example

```text
On hand = 12; reserved = 3; available = 9
New checkout reserves 2 -> on hand 12; reserved 5; available 7
That checkout pays      -> on hand 10; reserved 3; available 7
Instead it expires     -> on hand 12; reserved 3; available 9
```

`InventoryService` locks stock rows for mutations and records movements. Product edits now call the same stock adjustment path used by inventory operations. Sellers set the total on-hand amount, not an increment. A new total below existing reservations is rejected. The product form sends changed stock values only, so saving a description does not overwrite newer stock with an old value.

Checkout reservation duration defaults to 15 minutes. The scheduler releases expired pending orders. Paid cancellation restores deducted quantities through the appropriate workflow, while financial status depends on the payment method and refund evidence. A low-stock rearming gap on reservation release remains open.

## 9. Checkout, payments, delivery, returns and engagement

### Multi-shop checkout sequence

```text
Buyer -> POST /checkout (address, delivery by shop, coupon, method)
API   -> validate ownership, current products and available stock
API   -> reserve inventory; calculate totals; snapshot order/items
API   -> split shop_orders; create payment and shipment records
Buyer -> display each shop QR; pay seller directly
API   -> verify bank amount + recipient + currency + unique hash
API   -> when every shop is paid: confirm order and deduct stock
Seller-> process and ship only their allocation
API   -> parent progress follows the least advanced allocation
```

Amounts are recomputed server-side. Coupon validation checks activation, dates, usage limits and minimum subtotal before applying fixed/percentage discounts. Usage is reserved during order creation and reclaimed on release/cancellation/refund. The coupon row is locked and rechecked during usage creation.

`Idempotency-Key` is optional. For the same user/guest owner, the server stores a key and payload hash. Retrying the same request returns its existing order; reusing the key with different details is rejected. This protects network retries, not deliberate creation of unlimited distinct checkouts.

### Bakong and payment exceptions

Each shop allocation has a persistent dynamic KHQR payment record. The buyer transfers directly to that seller. The server checks the bank using its private API token; a browser-supplied transaction string alone cannot mark a production online order paid. Changing a shop recipient does not redirect an already-issued QR snapshot.

When only one shop in a two-shop order is paid, the order remains partial. If the reservation window expires, stock is released and collected money becomes refund required. Late receipts are reconciled into the refund workflow rather than silently reviving fulfillment. The scheduled reconciliation job currently scans pending records created within the last seven days; older/unmatched transfers require operator investigation.

The platform does not initiate payouts or refunds. For a Bakong refund, the seller makes the transfer using the exact order/return-specific memo shown by the app, then submits its hash. `RefundService` verifies sender, recipient, amount, currency, unique reference and memo before recording it. Cash refunds require a receipt reference; a ledger entry alone is not evidence of a bank transfer.

### Fulfillment and order states

The main progression is pending → confirmed → processing → shipped → delivered. Cancellation/refund branches are restricted by the current state and whether deliveries have already been dispatched. A bank-paid order cannot bypass the return/refund rules by selecting an arbitrary status.

Seller allocations progress independently. The parent follows the least advanced allocation, preserving another seller's unfinished work. Shipments track carrier, number, state and timestamps. The API resources expose allowed transitions and cancellation eligibility so the UI can present valid actions.

COD confirmation deducts reserved inventory and confirms fulfillment without claiming that cash has been collected. A complete audited COD collection action is still missing; do not interpret delivered COD records as automatically paid revenue.

### Returns, reviews and support

A customer can request an item return only for their own delivered, settled purchase within the configured return window, normally 14 days. Quantities cannot exceed eligible purchased units after existing requests. Refund amounts use cumulative allocation in cents to prevent repeated partial returns from exceeding the item budget. Delivery fees are outside the net-goods refund calculation.

Return states are requested → approved → received → refunded, or requested → rejected. Rejection needs a reason. Receiving returned goods includes an explicit restock decision. Refunding requires the verified bank proof or cash receipt described above. Evidence photos are private authenticated downloads, not public media URLs.

Reviews require a delivered order containing the product. New reviews are moderated, and an approved review edited by its author returns to pending. Rejection currently lacks aggregate recalculation; this remains an open defect. Support persists guest/customer requests and administrator replies; newsletter subscriptions retain consent and unsubscribe state. Mass marketing automation is not included.

## 10. Frontend state and API contracts

`frontend/src/api/client.ts` is the shared Axios client. Its base URL is `VITE_API_URL` or `/api`; it attaches bearer/session headers and handles rejected authentication. Use relative same-origin requests locally through the Vite proxy unless there is an intentional separately configured API origin.

| Store / layer | Responsibility |
| --- | --- |
| `auth.ts` | Current identity, token-related login/logout behavior and administrator/seller capabilities. |
| `cart.ts`, `wishlist.ts` | Shared shopping state synchronized with the API. |
| `sellerWorkspace.ts` | Available shops, selected shop and shop dashboard; ignores stale requests after an account/shop change. |
| `locale.ts`, `theme.ts`, `ui.ts` | Language, appearance and shared interaction state. |
| `router/index.ts` | Layout nesting, lazy screen loading and authentication/admin navigation guards. |
| `types/index.ts`, specialized type files | Response, product-photo, variant and scanner contracts. |

### Representative API families

Paths below are relative to `/api`; the current complete route definition is `backend/routes/api.php`. These are entry points, not a replacement for the full API reference.

| Endpoint | Access / purpose |
| --- | --- |
| `POST /auth/register`, `POST /auth/login` | Public, throttled account creation/login. |
| `GET /auth/me`, `POST /auth/logout` | Sanctum identity/current-token revocation. |
| `GET /catalog/products`, `GET /catalog/products/{slug}` | Public active catalog and detail. |
| `GET /shops`, `GET /shops/{slug}/products` | Public shop discovery and scoped products. |
| `POST /cart`, `PUT /cart/items/{cartItem}` | User or guest session; add/update exact variants. |
| `POST /checkout` | User/guest checkout with optional idempotency header. |
| `GET /checkout/{orderNumber}/bakong`, `POST /checkout/{orderNumber}/bakong/verify` | Owned order; prepare/read and verify shop payments. |
| `GET /orders`, `POST /orders/{orderNumber}/cancel` | Authenticated customer's orders/cancellation. |
| `GET /seller/shops/{shop}/products`, `PUT /seller/shops/{shop}/products/{product}` | Active shop management membership and scoped product. |
| `POST /seller/shops/{shop}/inventory/{inventory}/adjust` | Scoped total-stock adjustment. |
| `PUT /seller/shops/{shop}/orders/{shopOrder}/transition` | Scoped seller fulfillment transition. |
| `POST /returns`, `GET /support` | Authenticated customer returns/private support inbox. |
| `GET /admin/dashboard/overview`, `GET /admin/reports/orders.csv` | Super-admin dashboard/report examples. |

### Request examples and response handling

The following values are placeholders. IDs must refer to records in the current database; do not assume the seed starts at ID 1.

```json
POST /api/cart
Authorization: Bearer <token>
X-Session-Id: <browser-session-id>

{"product_variant_id": 42, "quantity": 2}
```

```json
POST /api/checkout
Idempotency-Key: checkout-demo-001

{
  "shipping_methods": {"7": 12, "9": 15},
  "payment_method": "cod",
  "address": {
    "full_name": "Demo Buyer",
    "address_line1": "Example Street",
    "city": "Phnom Penh",
    "state": "Phnom Penh",
    "postal_code": "12000",
    "country": "KH"
  }
}
```

Success generally wraps resources in `data`; list metadata varies by endpoint. Validation uses 422 with a message/errors map, missing authentication 401, forbidden access 403, missing/scoped records 404 and throttles 429. Never assume a 200 from a payment-status query means paid: read the returned payment/allocation states. Multipart seller updates use POST plus Laravel's `_method=PUT` override because PHP parses uploaded files through POST.

## 11. Local setup, environment and demo data

### First-time setup

Use Docker Desktop/Engine with Compose. Run commands from the repository root. PHP, Composer and Node work stays in containers; teammates do not need host installations of those runtimes.

```powershell
# Copy once; preserve an existing backend/.env.
if (-not (Test-Path backend/.env)) {
    Copy-Item backend/.env.example backend/.env
}

docker compose up -d --build
docker compose exec app composer install

# First-time local key only; do not rotate an existing environment key.
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
docker compose up -d worker scheduler
docker compose ps
```

The frontend service installs packages and starts Vite automatically. Wait until its logs show readiness. Worker/scheduler may start before dependencies are installed on a brand-new volume; restart them after Composer completes if needed. Preserve application keys and existing data when working on a shared environment.

| Service | Host address | Internal connection |
| --- | --- | --- |
| Frontend | `http://localhost:5174` | Frontend listens on 5173. |
| API / Nginx | `http://localhost:8000/api` | Nginx forwards PHP to app. |
| phpMyAdmin | `http://localhost:8080` | Database administration for local development. |
| MySQL | `localhost:3307` | App uses host `mysql`, port 3306. |
| Redis | `localhost:6379` | App uses host `redis`. |

### Configuration reference

Use the committed example environment files for defaults, then configure private values outside Git. Do not copy a teammate's production secrets into this document.

| Keys | Purpose / important choice |
| --- | --- |
| `APP_KEY`, `APP_ENV`, `APP_DEBUG` | Stable encryption key and runtime mode; production debug must be false. |
| `APP_URL`, `FRONTEND_URL` | Backend-generated URLs and browser links; use correct local ports or the real HTTPS origin. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Local Docker uses MySQL at `mysql:3306`; keep credentials in private environment configuration. |
| `REDIS_HOST`, `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | Select actual storage drivers; production Compose selects Redis. |
| `VITE_API_URL`, `VITE_PROXY_TARGET`, `VITE_USE_POLLING` | Public browser API base; dev-container proxy destination; reliable Windows file watching. Vite variables are public. |
| `PAYMENT_MODE`, `BAKONG_API_URL`, `BAKONG_API_TOKEN` | Sandbox/production policy, bank endpoint and server-only credential. Never place the token in `VITE_*`. |
| `ORDER_RESERVATION_MINUTES`, `ORDER_RETURN_DAYS` | Reservation duration and delivered-item return window. |
| `SANCTUM_TOKEN_EXPIRATION_MINUTES` | Global token-age policy; production Compose defaults to 1,440 minutes. |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Transport and sender. Log mail is useful locally; real SMTP needs credentials/domain acceptance. |

### Demo seed behavior

**Data-replacing operation:** `docker compose exec app php artisan db:seed` deletes existing business records and login/reset/session records before rebuilding the demo dataset. Use only an intended disposable local database. Back up anything needed first. It is not an additive sample-data command.

The seed creates 20 records in each of 37 business tables, with related orders, inventory, reviews, returns, support and settings. IDs are not reset. The cleanup/seed transaction keeps foreign keys enabled and restores prior records if the seed fails. Historical bank fixtures do not contact Bakong and are not real bank proof.

| Local demo identity | Email | Demo password |
| --- | --- | --- |
| Super administrator | `admin@ekhmer.com` | `password` |
| Customer | `customer@ekhmer.com` | `password` |
| Seller member | `seller@ekhmer.com` | `password` |

These credentials are published development fixtures, never production accounts. Other generated customer addresses range from `customer4@ekhmer.com` to `customer20@ekhmer.com`.

Do not use `migrate:fresh --seed` on retained data: it drops tables. `docker compose down -v` deletes volumes, including the database and container-managed dependency volumes. Neither is a routine fix for a missing package or stale UI.

## 12. How teammates should continue development

### A backend change

Start with the relevant route and Form Request. Put complex rules in an existing service or a narrowly scoped new service. Use policies/middleware for ownership, Eloquent for data access, transactions/locks for shared money-stock state and API Resources for serialization. Add a migration for schema changes, update factories/seeders and cover meaningful behavior with an appropriate regression.

Example: adding a product option field affects request validation, variant persistence, private/public resources, seller types, form serialization and edit loading. If it changes stock, call the inventory service rather than directly assigning database quantities.

```text
route -> request -> policy/context -> controller -> service/model
      -> resource -> typed API wrapper -> Vue form/view
      -> regression coverage -> documentation
```

### A frontend change

Use Vue Composition API with `<script setup lang="ts">`, typed props/emits and typed API contracts. Reuse the shared Axios client and existing layouts/components. Keep global state in Pinia only when shared ownership/lifetime requires it. Handle loading, empty, failure and permission states, and add English/Khmer messages. Check desktop width, small-screen overflow and keyboard access.

The installed styling is Tailwind. Root guidelines still mention Bootstrap, so keep that inconsistency explicit and settle it as a team before introducing a second styling framework. Avoid mixing dependencies as a side effect of an unrelated feature.

### Review habits

Use conventional commits such as `feat:`, `fix:`, `docs:` and `test:` on an appropriate feature branch. Review both successful and invalid inputs, cross-shop access, stale requests, partial payments and expiry. Include what changed, why, how it was verified and remaining limits in the PR. Updating the UI alone is insufficient for a new authorization or financial rule.

## 13. Testing, quality evidence and coverage limits

The following results are from the earlier 19 October QA run against this baseline. They were not repeated during this document export.

| Check | Recorded result | What it establishes |
| --- | --- | --- |
| Laravel suite | 385 passed; 2,615 assertions | Existing backend regressions pass with in-memory SQLite and test-specific drivers. |
| Playwright | 72 desktop/Pixel 7 Chromium checks passed | Current browser suite passed against the dev server with mocked UI APIs and fake camera input. |
| Frontend build | Passed | Vue TypeScript and production compilation completed. |
| Composer production audit | No advisories | Production PHP dependency audit at that time. |
| npm production audit | 0 vulnerabilities | Locked production frontend graph at that time. |
| Full npm audit | 7 findings: 5 high, 2 moderate | Open development-tool dependency paths. |
| Compose validation | Passed | Development configuration parsed. |
| Full stack / MySQL | Blocked by occupied port 3307 | No current full-stack health or live MySQL concurrency certification. |

### Commands and useful coverage

```sh
docker compose exec app php artisan test --compact
docker compose exec app php artisan test --filter=SellerWorkspaceTest
docker compose exec frontend npm run build
docker compose --profile test run --rm browser-tests
docker compose exec app composer audit --no-dev
docker compose exec frontend npm audit --omit=dev --package-lock-only
```

`QaHotfixTest` covers cancellation, stock/refund consistency, coupon release, validation and production sandbox gating. `SellerWorkspaceTest` covers stock edits/reservations and shop boundaries. Other suites cover Bakong, checkout idempotency, returns/support, catalog, ownership, photos and seed consistency. `ReceiptBrandingServiceTest` provides unit-level receipt coverage. Browser tests cover layout alignment, seller forms, actual barcode pixel decoding with mocked media, settings, payment progress, refund proof UI and expired-checkout retries.

The CI workflow defines backend tests, frontend build, production audits and browser checks. This document does not assert that a specific remote CI run succeeded. SQLite does not reproduce MySQL lock contention, fake cameras do not certify real phones and mocked bank responses do not prove bank activation. Use the QA report for finding IDs and evidence details.

## 14. Security and business-rule boundaries

Authentication, authorization and ownership are separate checks. A valid token identifies the caller; it does not grant membership in every shop. User order/address/review ownership must be verified on the server. Public shop resources must not reveal bank settings, commission configuration or private purchase costs.

Production Compose selects production payment mode and disables debug. Production exception rendering masks unexpected API errors even if debug is misconfigured. Environment files remain untracked. Avoid placing secrets in Vite settings, logs, sample requests or screenshots. A local demo account is a convenience fixture, not a production bootstrap strategy.

Image upload validates supported formats/context and uses generated filenames. Private return evidence must remain behind authenticated downloads. Guest session IDs currently act as browser-held bearer credentials; stronger server-issued/signature/expiry policies remain a review item. Inspect actual CORS behavior before a separately hosted frontend is deployed.

Business owners still need to approve shipping, tax, returns, commissions, privacy and retention policies. The application's tax calculation and help-page copy are existing behavior, not recorded business/legal approval. These policies should be deliberate configuration/product decisions rather than undocumented assumptions in a UI change.

## 15. Deployment, background work and recovery

### Development versus production

`docker-compose.yml` exposes development tools/ports and runs Vite. `compose.production.yml` uses project `ekhmer-production`, separate volumes, built images, internal MySQL/Redis, Caddy HTTPS, worker/scheduler and backup services. The production Dockerfile builds frontend assets and PHP dependencies without development packages. Shipping the development stack unchanged is not the supplied production workflow.

Before deployment, provision OCI Compute/storage/IP, domain DNS, restricted SSH/firewall, private `.env.production`, a stable `APP_KEY`, independent database passwords, SMTP credentials/sender domain and the server-only Bakong credential. Production is not already activated merely because these files exist.

```sh
docker compose --env-file .env.production -f compose.production.yml config --quiet
docker compose --env-file .env.production -f compose.production.yml build app web
docker compose --env-file .env.production -f compose.production.yml up -d mysql redis
docker compose --env-file .env.production -f compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yml up -d app worker scheduler web edge backup
```

These are deployment instructions, not actions performed while writing this handover. Preserve an existing key and bootstrap a real administrator using the team's controlled provisioning procedure. Do not run the demo seeder in production. The full deployment guide contains prerequisites, first-key handling and recovery details.

### Scheduler and worker

`routes/console.php` schedules expiry, payment reconciliation and operational heartbeats every minute. `payments:reconcile --limit=25` checks recent unsettled payments with bounded work; without a bank token it reports that reconciliation is skipped. The queue worker handles the configured queue connection. Notification behavior varies: registration verification is scheduled after response, while other operations may enqueue mail/jobs.

```sh
docker compose up -d worker scheduler
docker compose exec app php artisan ops:health --require-heartbeats
docker compose exec app php artisan queue:failed
docker compose logs --tail=80 worker scheduler
```

Allow the services to produce heartbeats before requiring them. Monitor database/cache health, stalled queues, missing scheduler heartbeats, Bakong failures/token expiry, TLS, disk space and failed/stale backups.

### Backup and restore

The backup script creates a transaction-consistent MySQL dump plus public/private media archives and verifies archive integrity. A local backup volume is not an off-server recovery plan. Configure protected offsite copies, retention and alerting, and separately back up the original key/private configuration.

The isolated restore-check profile uses a disposable MySQL service and the fixed database name `ecommerce_restore_check`. It refuses the live `mysql` hostname and refuses to overwrite an existing restore database. It checks SQL restoration and archive readability without extracting over live media. A successful row count still does not prove complete order/payment/media recovery.

For disaster recovery, restore into a new isolated stack, supply the original secrets/key, restore both media areas, verify financial/order consistency and switch traffic only after acceptance. The scripts do not implement MySQL binlog point-in-time recovery. Follow `docs/ORACLE-CLOUD.md` for exact maintenance-profile commands.

## 16. Suggested team walkthrough

Use an intended demo environment. The sample seed's orders are delivered historical fixtures, so show newly created checkout state separately from those historical records.

1. Show `/shops` and a public shop page; explain public versus private shop data.
2. Log in as the demo seller, select a shop and show its product, stock and order pages.
3. Create a simple listing and an option listing; explain SKU, barcode, private cost and selling price.
4. Upload a photo, scan or type a barcode, review the result and explicitly save.
5. Edit stock; show total on hand versus reserved/available. Try an invalid quantity in a disposable workflow.
6. Log in as the customer, choose a variant and build a cart. Show per-shop shipping at checkout.
7. Use COD for a local demonstration without bank configuration; explain that confirmed does not mean cash collected. Do not fake a successful production bank payment.
8. If bank acceptance is authorized/configured separately, show each shop QR and server verification, then partial-payment/expiry handling.
9. Process seller allocations independently and show customer tracking. Use a valid delivered fixture to explain returns and verified reviews.
10. Show admin charts, review moderation, support and reporting; finish with the QA report and remaining-work checklist.

This sequence demonstrates behavior without claiming that sample bank fixtures are actual transfers or that all planned launch tasks are complete.

## 17. Known issues, next steps and troubleshooting

### Prioritized remaining work

| Priority / owner area | Current gap | Completion evidence |
| --- | --- | --- |
| Backend correctness | Rejecting an approved review does not recalculate product rating. | Regression for approved → rejected and corrected aggregate. |
| Backend inventory | Releasing reservations does not rearm the low-stock notification marker. | Low → replenished → low notification regression. |
| Admin reporting | Report dates are parsed without complete input validation. | Invalid dates/order/span return clean 422 responses. |
| Commerce | Audited COD payment collection/reconciliation is missing. | Authorized capture action, ledger and revenue behavior verified. |
| Abuse controls | Checkout creation lacks an explicit route throttle/pending-order quota. | Distinct-reservation abuse tests plus preserved legitimate retries. |
| Reliability | Order-number final candidate/collision handling needs strengthening. | Forced insert-conflict retry test. |
| UX / API consistency | Low-stock filter includes out-of-stock rows; latent aggregate/coupon validation issues remain. | Agreed semantics and meaningful regression cases. |
| Dependency maintenance | Seven frontend development audit findings remain. | Tested compatible remediation or Tailwind migration; no blind forced major upgrade. |
| Operations / QA | Real bank, SMTP, OCI/TLS, offsite recovery, device and MySQL concurrency acceptance outstanding. | Recorded staging/real-service checks with approved accounts and environments. |
| Team documentation | Bootstrap/Tailwind guidance and some older schema/operating text are stale. | Reviewed agreement and updated source documentation. |

Additional review items include effective MySQL indexes, CORS when origins differ and guest credential policy. Refer to `docs/QA-REPORT.md` for original IDs, closure status and the evidence limitations.

### Troubleshooting

| Symptom | First checks |
| --- | --- |
| Port 3307 already allocated | Inspect running Docker services/host listeners. Resolve the conflict or deliberately change only the host mapping; internal DB host remains `mysql:3306`. Do not stop unrelated services blindly. |
| Vite unavailable / API proxy fails | Check frontend readiness/logs, Nginx and app status. `VITE_PROXY_TARGET` inside the frontend container should point to Nginx. |
| Missing PHP package/class | Run Composer inside app; app/worker/scheduler share `backend_vendor`, not the ignored host vendor directory. |
| Missing frontend package | Install through the frontend container and review the lock diff. Do not delete database volumes. |
| 401 / 403 | Check token expiry/current user, super-admin role or active shop membership; do not disable authorization to make a request work. |
| Images missing | Check local storage link, public disk URL/files, upload limits and production media mounts. |
| Camera denied / unavailable | Use HTTPS/localhost, allow permission, check another application is not holding the device and offer manual/scanner input. |
| Payment remains pending | Check all shop allocations, recipient settings, token/API availability and verification response; never manually mark an unverified transfer paid. |
| Reservation never expires / emails wait | Check scheduler/worker, queue driver and health heartbeats; distinguish log mail from real SMTP. |
| Stale schema | Check migration status and intended environment before applying pending migrations. Never use table-drop/reset commands as a routine repair. |

### Handover checklist

- Assign named owners for backend, frontend, QA, bank operations, deployment and recovery; names are not inferred here.
- Each teammate can run the local stack, explain the role model and locate the main services.
- Keep the source baseline, source documentation and Word export synchronized after significant changes.
- Record outstanding acceptance checks and decisions in the team's existing project-management records.
- Complete the launch gates with evidence before declaring production readiness.

## 18. Reference map and glossary

| Topic | Primary repository entry point |
| --- | --- |
| Project setup / docs index | [Root README](../README.md), [documentation index](./README.md). |
| Routes and auth | [API routes](../backend/routes/api.php), [AuthController](../backend/app/Http/Controllers/Api/AuthController.php), [User model](../backend/app/Models/User.php). |
| Shop authorization | [EnsureManagedShop](../backend/app/Http/Middleware/EnsureManagedShop.php), `backend/app/Policies`. |
| Stock and checkout | [InventoryService](../backend/app/Services/InventoryService.php), [CheckoutService](../backend/app/Services/CheckoutService.php), [ShopOrderService](../backend/app/Services/ShopOrderService.php). |
| Fulfillment / finance | [OrderService](../backend/app/Services/OrderService.php), [ShopOrderFulfillmentService](../backend/app/Services/ShopOrderFulfillmentService.php), [BakongService](../backend/app/Services/BakongService.php), [RefundService](../backend/app/Services/RefundService.php). |
| Returns / media | [ReturnService](../backend/app/Services/ReturnService.php), [MediaUploadService](../backend/app/Services/MediaUploadService.php). |
| Seller UI | [Product form](../frontend/src/views/seller/SellerProductFormView.vue), [barcode field](../frontend/src/components/seller/SellerBarcodeField.vue), [scanner worker](../frontend/src/workers/barcodeScanner.worker.ts). |
| Frontend integration | [Router](../frontend/src/router/index.ts), [Axios client](../frontend/src/api/client.ts), [frontend guide](./FRONTEND.md). |
| Schema and demo data | `backend/database/migrations`, [DatabaseSeeder](../backend/database/seeders/DatabaseSeeder.php), [DatabaseSeederTest](../backend/tests/Feature/DatabaseSeederTest.php). |
| Test/QA evidence | [QA report](./QA-REPORT.md), [browser suite](../frontend/tests/e2e/commerce.spec.ts), [CI workflow](../.github/workflows/ci.yml). |
| Operations | [Console schedule](../backend/routes/console.php), [deployment guide](./ORACLE-CLOUD.md), [commerce guide](./COMMERCE-COMPLETION.md). |
| Existing detailed guides | [Architecture](./ARCHITECTURE.md), [API reference](./API-REFERENCE.md), [development workflow](./DEVELOPMENT.md), [project management](./project-management/README.md). |

Some existing guides contain older sections. When they conflict, inspect the current source and the dated QA report; for example, stock can now be edited through the seller product form while still using the inventory ledger.

| Term | Meaning in this project |
| --- | --- |
| Product / variant | Public listing / exact sellable option with stock and SKU. |
| EAV | Attribute/value/link tables used to model options such as color and size. |
| On hand / reserved / available | Physical total / held for checkout / total minus reservations. |
| Parent order / allocation | Customer purchase / one shop's financial and fulfillment portion. |
| Snapshot | Transaction-time copy preserved despite later catalog/profile changes. |
| Idempotency | Repeating the same keyed request returns its existing outcome. |
| Sanctum token | Revocable bearer credential issued by Laravel for API authentication. |
| KHQR / Bakong | QR payment payload / bank integration used to verify direct seller receipts. |
| Reconciliation | Checking external receipts against persisted expected payments. |
| Refund ledger | Audited evidence of a verified bank/cash refund, not a transfer engine. |
| Worker / scheduler | Process queued work / trigger recurring maintenance. |
| Migration / seeder / factory | Version schema / replace demo data / create test records. |

End of handover. Update this source and the Word export together when the implementation or acceptance status changes.
