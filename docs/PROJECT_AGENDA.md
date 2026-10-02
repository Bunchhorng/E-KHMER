# PROJECT AGENDA

Master execution checklist for the 6-member E-Commerce Web Application project.
Planning -> Setup -> Database -> Backend -> API -> Frontend -> Integration -> Testing -> Bug Fixing -> Security -> Deployment -> Documentation -> Presentation.

## How to Use This Document

- Update this file directly in the repository. Commit agenda updates with `docs: update project agenda`.
- Tick a checkbox (`[x]`) only after the task is implemented **and** tested. Never tick automatically.
- Allowed Status values: `NOT STARTED`, `IN PROGRESS`, `BLOCKED`, `REVIEW`, `TESTING`, `DONE`.
- Allowed Priority values: `CRITICAL`, `HIGH`, `MEDIUM`, `LOW`.
- Owner is written as `M1` ... `M6` (see Section 2).
- Task ID prefixes: `PLN` Planning, `SET` Setup, `DB` Database, `AUTH`, `PRD`, `CART`, `WISH`, `CHK`, `ORD`, `PAY`, `CPN`, `INV`, `SHP`, `NTF`, `REV`, `CUI` Customer UI, `ADB` Admin UI, `API`, `INT` Integration, `SEC`, `TST`, `BUG`, `DOC`, `DEP`, `REL`.
- Current repo state note: the working tree already contains migrations, API routes, and Vue views for most modules. Every box below is therefore unconfirmed until the owning member verifies it against the running application.

---

# 1. PROJECT DASHBOARD

| Field | Value |
| ----- | ----- |
| Project | E-Commerce Web Application (multi-shop capable) |
| Team | 6 Members |
| Team Leader | M1 |
| Start Date | To be filled by Team Leader |
| Target Completion | To be filled by Team Leader |
| Current Phase | Phase 14 - Testing (re-confirm with team) |
| Overall Status | NOT STARTED (unconfirmed - audit required) |
| Overall Progress | 0% confirmed - run the Section 32 audit |

## Area Summary

| Area | Owner | Status | Progress | Notes |
| ---- | ----- | ------ | -------- | ----- |
| Authentication | M1 | TESTING | 85% | Register/login/me/logout, email verification, password reset, 429 throttling, remember-me lifetime and token revocation implemented and covered by 30 auth tests; merge to `dev` and a 500-path test still pending |
| Users and Addresses | M1 | TESTING | 80% | Profile update, password change, notification ownership and address CRUD (ownership, default-flag invariant, column limits) implemented and covered by 24 tests; avatar upload and account reviews endpoints remain untested |
| Products | M2 | TESTING | 70% | Public catalog (listing, detail, featured, facets, search, filters, sorting, variant resolve, active-shop scoping, throttling) and admin product CRUD (transactional writes, slug/SKU uniqueness, per-shop variant SKUs, combination uniqueness, image path validation, cover handling, bulk status) implemented and covered by `CatalogTest` (24), `AdminOpsTest`, `AdminMediaTest`, `CartTest`, `ReviewTest`; remaining: draft/archived status, description sanitization, admin attribute CRUD, explicit shop filter, disabled variant combinations |
| Shopping (Cart/Wishlist/Checkout) | M3 | NOT STARTED | 0% | Session cart plus Sanctum cart routes exist |
| Orders | M4 | NOT STARTED | 0% | `PUT admin/orders/{order}/transition` exists; verify state machine |
| Payments | M4 | NOT STARTED | 0% | Payment tables exist; no gateway integration yet |
| Coupons | M4 | NOT STARTED | 0% | `POST coupons/validate` exists; verify rule order |
| Inventory | M5 | NOT STARTED | 0% | `inventories` + `inventory_transactions` exist; verify atomicity |
| Shipping | M5 | NOT STARTED | 0% | Shipments + `tracking_events` exist; verify status flow |
| Notifications | M5 | NOT STARTED | 0% | Notifications table + admin endpoints exist |
| Customer Website | M6 | NOT STARTED | 0% | Views exist for all customer pages; verify states and integration |
| Admin Dashboard | M6 | NOT STARTED | 0% | Admin views exist; "every button must work" audit pending |
| Testing | M6 | NOT STARTED | 0% | No release-blocking test suite signed off |
| Deployment | M1 | NOT STARTED | 0% | Docker Compose present; production build unverified |
| Documentation | M1 | NOT STARTED | 0% | `docs/` populated; needs consolidation into deliverables |

---

# 2. TEAM MEMBERS

| Member | Role | Main Responsibility | Branch | Status |
| ------ | ---- | ------------------- | ------ | ------ |
| M1 | Team Leader / Backend Lead | Authentication, Users, Addresses, integration, Git management, code review | `feature/auth-users` | ACTIVE |
| M2 | Product Developer | Products, Categories, Brands, Product Images, Product Variants, Attributes | `feature/products` | ACTIVE |
| M3 | Shopping Developer | Cart, Wishlist, Checkout | `feature/cart-checkout` | ACTIVE |
| M4 | Order and Payment Developer | Orders, Order Items, Order Status, Payments, Payment Transactions, Coupons | `feature/orders-payment` | ACTIVE |
| M5 | Inventory and Shipping Developer | Inventory, Inventory Transactions, Shipping, Shipments, Notifications | `feature/inventory-shipping` | ACTIVE |
| M6 | Frontend and QA Developer | Customer Website, Reviews, Review Images, API Integration, Testing, QA | `feature/customer-ui` | ACTIVE |

## Shared Branches

| Branch | Purpose | Managed By |
| ------ | ------- | ---------- |
| `dev` | Integration branch, default base for all merges | M1 |
| `main` | Release branch, production deployments only | M1 |
| `fix/qa-hotfixes` | Post-QA critical and high bug fixes | M1 with owning member |

Existing local branches: `dev`, `auth-users`, `fix/qa-hotfixes`. Branch names in the table above are the target convention; create any that do not exist yet.

---

# 3. PROJECT PHASES

Each phase lists Objective, Owner, Dependencies, Tasks, and Acceptance Criteria.

## Phase 1 - Planning

- Objective: agree scope, roles, branches, and delivery dates.
- Owner: M1
- Dependencies: none
- Tasks:

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| PLN-01 | [ ] | Confirm scope of the 6 feature areas and write scope statement | M1 | CRITICAL | NOT STARTED | - |
| PLN-02 | [ ] | Confirm start date, target completion date, and demo date | M1 | CRITICAL | NOT STARTED | - |
| PLN-03 | [ ] | Create the six feature branches from `dev` | M1 | HIGH | NOT STARTED | PLN-02 |
| PLN-04 | [ ] | Assign every task ID in this document to an owner | M1 | CRITICAL | NOT STARTED | PLN-01 |
| PLN-05 | [ ] | Agree branch strategy: feature branch -> PR -> `dev` -> `main` | M1 | HIGH | NOT STARTED | PLN-03 |
| PLN-06 | [ ] | Agree pull request review rule (1 approval minimum, M1 reviews all merges) | M1 | HIGH | NOT STARTED | PLN-05 |
| PLN-07 | [ ] | Agree API contract format and versioning rule | M1 | HIGH | NOT STARTED | PLN-01 |
| PLN-08 | [ ] | Agree database naming conventions and migration rules | M1 | MEDIUM | NOT STARTED | PLN-01 |
| PLN-09 | [ ] | Publish the sprint/weekly cadence and meeting schedule | M1 | MEDIUM | NOT STARTED | PLN-02 |

- Acceptance criteria: every member knows their branch, their task IDs, their deadlines, and the PR rule; dates are written in Section 1.

## Phase 2 - Project Setup

- Objective: a reproducible local + Docker environment for all members.
- Owner: M1
- Dependencies: Phase 1
- Tasks:

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| SET-01 | [ ] | Docker and Docker Compose running on all machines | M1 | CRITICAL | NOT STARTED | - |
| SET-02 | [ ] | `docker compose up -d` brings up app, frontend, nginx, mysql, redis, phpmyadmin | M1 | CRITICAL | NOT STARTED | SET-01 |
| SET-03 | [ ] | Laravel app boots and `artisan migrate` succeeds in container | M1 | CRITICAL | NOT STARTED | SET-02 |
| SET-04 | [ ] | Vue app boots in container with hot reload | M6 | HIGH | NOT STARTED | SET-02 |
| SET-05 | [ ] | `.env` files documented and excluded from Git | M1 | CRITICAL | NOT STARTED | SET-03 |
| SET-06 | [ ] | Frontend `VITE_API_URL` configured for container networking | M6 | HIGH | NOT STARTED | SET-04 |
| SET-07 | [ ] | Redis cache and session driver working | M5 | MEDIUM | NOT STARTED | SET-03 |
| SET-08 | [ ] | README with setup steps for every member | M1 | HIGH | NOT STARTED | SET-03, SET-04 |

- Acceptance criteria: a member who has never run the project can clone, configure, migrate, seed, and start the app using the README only.

## Phase 3 - Database

- Objective: complete schema, ERD, indexes, seeders, factories, and test data.
- Owner: M2 (schema), M1 (review), all members (their own tables)
- Dependencies: Phase 2
- Tasks: see Section 5 for the full table-level checklist.
- Acceptance criteria: `migrate:fresh --seed` completes with no error on a clean database; every foreign key and unique index required by Section 5 exists; ERD is exported and committed to `docs/`.

## Phase 4 - Authentication

- Objective: secure registration, login, logout, session management, and role separation.
- Owner: M1
- Dependencies: Phase 3 (`users`, `personal_access_tokens`)
- Tasks: see Section 6.
- Acceptance criteria: unauthenticated requests to protected endpoints return 401; non-admin requests to `/api/admin/*` return 403; passwords are hashed and never returned by any endpoint.

## Phase 5 - Product Management

- Objective: full catalog CRUD with categories, brands, images, variants, and attributes.
- Owner: M2
- Dependencies: Phase 3, Phase 4 (admin authorization)
- Tasks: see Section 7.
- Acceptance criteria: an admin can create a product with images and at least two variants and correct stock; a customer can browse, filter, sort, and search published products only.

## Phase 6 - Shopping

- Objective: cart, wishlist, and checkout pipeline with server-side totals.
- Owner: M3
- Dependencies: Phase 5 (variants and stock), Phase 4
- Tasks: see Sections 8, 9, 10.
- Acceptance criteria: totals returned by the API always match a fresh server-side recomputation; stock is validated on add, update, and checkout.

## Phase 7 - Orders

- Objective: order creation, snapshots, history, and enforced status transitions.
- Owner: M4
- Dependencies: Phase 6
- Tasks: see Section 11.
- Acceptance criteria: `order_items` store price/title/variant snapshot values at checkout; every transition is written to `order_status_histories`; invalid transitions are rejected with 422.

## Phase 8 - Payment

- Objective: payment records, transactions, and status handling without storing sensitive card data.
- Owner: M4
- Dependencies: Phase 7
- Tasks: see Section 12.
- Acceptance criteria: no PAN/CVV column exists in the schema; a successful payment deducts stock exactly once; a duplicate payment attempt on a paid order is rejected.

## Phase 9 - Inventory

- Objective: atomic stock control with a full transaction ledger.
- Owner: M5
- Dependencies: Phase 7, Phase 8
- Tasks: see Section 14.
- Acceptance criteria: stock can never go negative under concurrent requests; every quantity change has exactly one `inventory_transactions` row with a reason code.

## Phase 10 - Shipping

- Objective: shipping methods, fees, shipments, and tracking.
- Owner: M5
- Dependencies: Phase 7
- Tasks: see Section 15.
- Acceptance criteria: shipping fee is applied once per order and appears in order totals; tracking events are stored and shown to the customer.

## Phase 11 - Customer Website

- Objective: complete customer-facing Vue application.
- Owner: M6
- Dependencies: Phases 4 to 10 APIs
- Tasks: see Section 18.
- Acceptance criteria: every customer page handles loading, error, and empty states; the full customer flow in Section 24 completes without a manual backend fix.

## Phase 12 - Admin Dashboard

- Objective: complete admin interface where every control performs a real action.
- Owner: M6
- Dependencies: Phase 4 authorization, Phases 5, 7, 8, 9, 10 admin APIs
- Tasks: see Section 19.
- Acceptance criteria: zero dead buttons in admin views; every destructive action confirms and shows a success/error toast; no admin page leaks data from another shop or user.

## Phase 13 - Integration

- Objective: verify end-to-end wiring between frontend, API, database, and notifications.
- Owner: M1 (coordination), all members (fixes)
- Dependencies: Phases 11, 12
- Tasks:

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| INT-01 | [ ] | Merge all six feature branches into `dev` cleanly | M1 | CRITICAL | NOT STARTED | Phases 11, 12 |
| INT-02 | [ ] | Verify no route/controller/frontend contract mismatches | M1 | CRITICAL | NOT STARTED | INT-01 |
| INT-03 | [ ] | Verify shop scoping is applied on every multi-tenant query | M2 | HIGH | NOT STARTED | INT-01 |
| INT-04 | [ ] | Verify notification triggers fire on order, payment, shipping, low stock | M5 | HIGH | NOT STARTED | INT-01 |
| INT-05 | [ ] | Verify Telegram/automation hooks do not leak secrets or spam | M1 | MEDIUM | NOT STARTED | INT-01 |
| INT-06 | [ ] | Verify order cancellation restores stock and coupon usage | M4 | HIGH | NOT STARTED | INT-01 |
| INT-07 | [ ] | Run the two complete flows in Sections 24 and 25 on `dev` | M6 | CRITICAL | NOT STARTED | INT-02 |

- Acceptance criteria: Sections 24 and 25 pass on a fresh `migrate:fresh --seed` database.

## Phase 14 - Testing

- Objective: unit, API, UI, and integration coverage plus a signed QA report.
- Owner: M6 (lead), all members (own modules)
- Dependencies: Phase 13
- Tasks: see Section 23.
- Acceptance criteria: no failing test in CI/local suite; every API in Section 20 is marked Tested; QA report published with zero open CRITICAL bugs.

## Phase 15 - Bug Fixing

- Objective: close all CRITICAL and HIGH bugs from the bug tracker.
- Owner: M1 (triage), owning member (fix), M6 (verify)
- Dependencies: Phase 14
- Tasks: see Section 26.
- Acceptance criteria: zero open CRITICAL bugs; every fix has a regression test or a documented manual retest note.

## Phase 16 - Security

- Objective: verify and harden authentication, authorization, input handling, and secret management.
- Owner: M1 (lead), M6 (verification)
- Dependencies: Phase 14
- Tasks: see Section 22.
- Acceptance criteria: every box in Section 22 is verified by a second member; findings are logged as bugs with priority.

## Phase 17 - Deployment

- Objective: production-like Docker deployment with health checks and rollback plan.
- Owner: M1
- Dependencies: Phase 15, Phase 16
- Tasks: see Section 33.
- Acceptance criteria: production build runs with `APP_ENV=production`, no debug endpoints exposed, deployment repeatable from documented steps.

## Phase 18 - Documentation

- Objective: architecture, API reference, user manual, and technical manual.
- Owner: M1 (lead), all members (module sections)
- Dependencies: Phase 13
- Tasks: see Section 33 documentation items.
- Acceptance criteria: every endpoint in `docs/API-REFERENCE.md` matches the implemented routes; ERD, setup guide, and user manual are committed.

## Phase 19 - Presentation

- Objective: demo script, slides, rehearsal, and final defense.
- Owner: M1 (lead), M6 (demo build)
- Dependencies: Phase 17, Phase 18
- Tasks: see Section 33 presentation items.
- Acceptance criteria: full demo rehearsed twice end to end on `main` with seeded demo data; each member can present their own module.

---

# 4. PROJECT SETUP CHECKLIST

| ID | Done | Item | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| SET-01 | [ ] | GitHub repository created and all members added | M1 | CRITICAL | NOT STARTED | - |
| SET-02 | [ ] | Branch strategy documented and enforced by PR rules | M1 | CRITICAL | NOT STARTED | SET-01 |
| SET-03 | [ ] | Laravel setup (`/backend`) running inside Docker | M1 | CRITICAL | NOT STARTED | SET-01 |
| SET-04 | [ ] | Vue setup (`/frontend`) running inside Docker | M6 | HIGH | NOT STARTED | SET-01 |
| SET-05 | [ ] | MySQL 8 setup with correct charset and timezone | M1 | CRITICAL | NOT STARTED | SET-03 |
| SET-06 | [ ] | Docker Compose setup for app, frontend, nginx, mysql, redis, phpmyadmin | M1 | CRITICAL | NOT STARTED | SET-03 |
| SET-07 | [ ] | Environment configuration documented (`.env.example` for backend and frontend) | M1 | HIGH | NOT STARTED | SET-03 |
| SET-08 | [ ] | Database connection verified from Laravel | M1 | CRITICAL | NOT STARTED | SET-05 |
| SET-09 | [ ] | API connection verified (`GET /api/catalog/products` reachable from nginx) | M6 | CRITICAL | NOT STARTED | SET-08 |
| SET-10 | [ ] | Frontend to API connection verified via Axios base URL | M6 | CRITICAL | NOT STARTED | SET-09 |
| SET-11 | [ ] | README with prerequisites and step-by-step run instructions | M1 | HIGH | NOT STARTED | SET-10 |
| SET-12 | [ ] | `.gitignore` covers `.env`, `vendor`, `node_modules`, `storage`, uploads | M1 | CRITICAL | NOT STARTED | SET-01 |
| SET-13 | [ ] | Coding standards documented (PSR-12 for PHP, TS strict for Vue) | M1 | MEDIUM | NOT STARTED | SET-01 |
| SET-14 | [ ] | API standards documented (resource naming, envelope, error format, pagination) | M1 | HIGH | NOT STARTED | SET-13 |
| SET-15 | [ ] | Git hooks or CI running lint and tests on every PR | M6 | MEDIUM | NOT STARTED | SET-14 |

---

# 5. DATABASE CHECKLIST

## 5.1 Schema Tasks

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| DB-01 | [ ] | ERD drawn and exported to `docs/` | M2 | CRITICAL | NOT STARTED | - |
| DB-02 | [ ] | All relationships documented (one-to-many, many-to-many, one-to-one) | M2 | HIGH | NOT STARTED | DB-01 |
| DB-03 | [ ] | Migrations exist for every table (no manual schema edits) | M2 | CRITICAL | NOT STARTED | DB-01 |
| DB-04 | [ ] | Foreign keys defined with explicit `onDelete` behaviour | M2 | CRITICAL | NOT STARTED | DB-03 |
| DB-05 | [ ] | Indexes on every foreign key and every filter/sort column | M2 | HIGH | NOT STARTED | DB-03 |
| DB-06 | [ ] | Unique indexes on SKU, slug, email, coupon code | M2 | HIGH | NOT STARTED | DB-03 |
| DB-07 | [ ] | Multi-shop scoping columns and indexes present (`shop_id` on owned tables) | M2 | HIGH | NOT STARTED | DB-03 |
| DB-08 | [ ] | Seeders for roles, admin user, customers, categories, brands, attributes | M2 | HIGH | NOT STARTED | DB-03 |
| DB-09 | [ ] | Factories for all models used in tests | M2 | HIGH | NOT STARTED | DB-03 |
| DB-10 | [ ] | Demo/test data set covering multiple shops and edge cases | M2 | MEDIUM | NOT STARTED | DB-08 |
| DB-11 | [ ] | `migrate:fresh --seed` verified on a clean container | M2 | CRITICAL | NOT STARTED | DB-08 |
| DB-12 | [ ] | Rollback (`migrate:rollback`) verified for the newest migration | M2 | MEDIUM | NOT STARTED | DB-03 |
| DB-13 | [ ] | Money columns defined as integer minor units or `decimal(12,2)` consistently | M2 | HIGH | NOT STARTED | DB-03 |
| DB-14 | [ ] | Timestamps present on all mutable tables | M2 | MEDIUM | NOT STARTED | DB-03 |

## 5.2 Table Checklist

| # | Table | Migration Exists | Relationships Defined | Indexes | Seeded | Notes |
| - | ----- | ---------------- | --------------------- | ------- | ------ | ----- |
| 1 | `users` | [ ] | [ ] | [ ] | [ ] | Role column drives admin authorization |
| 2 | `addresses` | [ ] | [ ] | [ ] | [ ] | Belongs to user; one default per user |
| 3 | `categories` | [ ] | [ ] | [ ] | [ ] | Self-referencing parent for tree view |
| 4 | `brands` | [ ] | [ ] | [ ] | [ ] | Logo upload path |
| 5 | `products` | [ ] | [ ] | [ ] | [ ] | Slug unique, soft delete, `shop_id` |
| 6 | `product_images` | [ ] | [ ] | [ ] | [ ] | Sort order and primary flag |
| 7 | `product_variants` | [ ] | [ ] | [ ] | [ ] | SKU unique per shop, price override |
| 8 | `attributes` | [ ] | [ ] | [ ] | [ ] | EAV master table |
| 9 | `attribute_values` | [ ] | [ ] | [ ] | [ ] | Belongs to attribute |
| 10 | `variant_attribute_values` | [ ] | [ ] | [ ] | [ ] | Pivot with composite unique |
| 11 | `inventories` | [ ] | [ ] | [ ] | [ ] | One row per variant, `shop_id` |
| 12 | `inventory_transactions` | [ ] | [ ] | [ ] | [ ] | Append-only ledger, `shop_id` |
| 13 | `carts` | [ ] | [ ] | [ ] | [ ] | One active cart per user or session |
| 14 | `cart_items` | [ ] | [ ] | [ ] | [ ] | Unique per cart + variant |
| 15 | `wishlists` | [ ] | [ ] | [ ] | [ ] | One per user |
| 16 | `wishlist_items` | [ ] | [ ] | [ ] | [ ] | Unique per wishlist + product |
| 17 | `orders` | [ ] | [ ] | [ ] | [ ] | Order number unique, totals snapshot |
| 18 | `order_items` | [ ] | [ ] | [ ] | [ ] | Title/price/variant snapshot, `shop_id` |
| 19 | `order_status_histories` | [ ] | [ ] | [ ] | [ ] | Append-only audit of transitions |
| 20 | `payments` | [ ] | [ ] | [ ] | [ ] | One per order, status enum |
| 21 | `payment_transactions` | [ ] | [ ] | [ ] | [ ] | Append-only, no PAN/CVV columns |
| 22 | `shipping_methods` | [ ] | [ ] | [ ] | [ ] | Fee, estimated days, `shop_id` |
| 23 | `shipments` | [ ] | [ ] | [ ] | [ ] | Tracking number unique per carrier |
| 24 | `coupons` | [ ] | [ ] | [ ] | [ ] | Type, value, limits, window, `shop_id` |
| 25 | `coupon_usages` | [ ] | [ ] | [ ] | [ ] | Per-order and per-user limit support |
| 26 | `reviews` | [ ] | [ ] | [ ] | [ ] | Rating check constraint, moderation status, `shop_id` |
| 27 | `review_images` | [ ] | [ ] | [ ] | [ ] | Limited count and file size |
| 28 | `notifications` | [ ] | [ ] | [ ] | [ ] | Polymorphic type, read timestamp |
| 29 | `shops` | [ ] | [ ] | [ ] | [ ] | Slug, status, owner |
| 30 | `shop_users` | [ ] | [ ] | [ ] | [ ] | Membership roles |
| 31 | `tracking_events` | [ ] | [ ] | [ ] | [ ] | Shipment timeline |
| 32 | `settings` | [ ] | [ ] | [ ] | [ ] | Key/value store for admin settings |

## 5.3 Ownership Table

| Table | Owner | Status | Tested |
| ----- | ----- | ------ | ------ |
| `users` | M1 | NOT STARTED | [ ] |
| `addresses` | M1 | NOT STARTED | [ ] |
| `shops` | M1 | NOT STARTED | [ ] |
| `shop_users` | M1 | NOT STARTED | [ ] |
| `settings` | M1 | NOT STARTED | [ ] |
| `categories` | M2 | NOT STARTED | [ ] |
| `brands` | M2 | NOT STARTED | [ ] |
| `products` | M2 | NOT STARTED | [ ] |
| `product_images` | M2 | NOT STARTED | [ ] |
| `product_variants` | M2 | NOT STARTED | [ ] |
| `attributes` | M2 | NOT STARTED | [ ] |
| `attribute_values` | M2 | NOT STARTED | [ ] |
| `variant_attribute_values` | M2 | NOT STARTED | [ ] |
| `carts` | M3 | NOT STARTED | [ ] |
| `cart_items` | M3 | NOT STARTED | [ ] |
| `wishlists` | M3 | NOT STARTED | [ ] |
| `wishlist_items` | M3 | NOT STARTED | [ ] |
| `orders` | M4 | NOT STARTED | [ ] |
| `order_items` | M4 | NOT STARTED | [ ] |
| `order_status_histories` | M4 | NOT STARTED | [ ] |
| `payments` | M4 | NOT STARTED | [ ] |
| `payment_transactions` | M4 | NOT STARTED | [ ] |
| `coupons` | M4 | NOT STARTED | [ ] |
| `coupon_usages` | M4 | NOT STARTED | [ ] |
| `inventories` | M5 | NOT STARTED | [ ] |
| `inventory_transactions` | M5 | NOT STARTED | [ ] |
| `shipping_methods` | M5 | NOT STARTED | [ ] |
| `shipments` | M5 | NOT STARTED | [ ] |
| `tracking_events` | M5 | NOT STARTED | [ ] |
| `notifications` | M5 | NOT STARTED | [ ] |
| `reviews` | M6 | NOT STARTED | [ ] |
| `review_images` | M6 | NOT STARTED | [ ] |

---

# 6. AUTHENTICATION AGENDA

Verification evidence for this section: `docker compose exec app php artisan test` -> 200 passed (897 assertions). New coverage added in this pass: `tests/Feature/Api/AddressTest.php` (14 tests), `tests/Feature/Api/AccountTest.php` (10 tests), and 10 new cases in `tests/Feature/Api/AuthTest.php`. Merge into `dev` (DOD-11) is still outstanding, so confirm with the team before the final tick.

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| AUTH-01 | [x] | Register endpoint with validation and unique email enforcement | M1 | CRITICAL | DONE | DB-03 |
| AUTH-02 | [x] | Login endpoint issuing a Sanctum personal access token | M1 | CRITICAL | DONE | AUTH-01 |
| AUTH-03 | [x] | Logout endpoint revoking the current token | M1 | CRITICAL | DONE | AUTH-02 |
| AUTH-04 | [x] | Current user endpoint (`GET auth/me`) returning user + role | M1 | HIGH | DONE | AUTH-02 |
| AUTH-05 | [x] | Password hashing (bcrypt/argon2) applied at model level | M1 | CRITICAL | DONE | - |
| AUTH-06 | [x] | Forgot password endpoint with signed, expiring token | M1 | HIGH | DONE | AUTH-02 |
| AUTH-07 | [x] | Reset password endpoint invalidating previous tokens | M1 | HIGH | DONE | AUTH-06 |
| AUTH-08 | [x] | Email verification endpoint and verification notice | M1 | MEDIUM | DONE | AUTH-01 |
| AUTH-09 | [x] | Protected route middleware `auth:sanctum` applied consistently | M1 | CRITICAL | DONE | AUTH-02 |
| AUTH-10 | [x] | Role permissions (customer vs admin) on user record | M1 | CRITICAL | DONE | AUTH-01 |
| AUTH-11 | [x] | Admin authorization middleware `admin` protecting `/api/admin/*` | M1 | CRITICAL | DONE | AUTH-10 |
| AUTH-12 | [ ] | Customer authorization: resource ownership check on all user data | M1 | CRITICAL | IN PROGRESS | AUTH-09 |
| AUTH-13 | [x] | Validation rules for every auth payload with consistent 422 format | M1 | HIGH | DONE | AUTH-01 |
| AUTH-14 | [ ] | Error handling: 401, 403, 422, 429 lockout, 500 | M1 | HIGH | IN PROGRESS | AUTH-13 |
| AUTH-15 | [x] | Throttling on login, register, password reset, and verification resend | M1 | MEDIUM | DONE | AUTH-02 |
| AUTH-16 | [x] | API tests for register, login, logout, me, reset, role checks | M1 | CRITICAL | DONE | AUTH-11, AUTH-12 |
| AUTH-17 | [ ] | Frontend auth flow tests: login, register, protected route redirect | M6 | HIGH | NOT STARTED | AUTH-16 |
| AUTH-18 | [x] | Address CRUD API with ownership enforcement | M1 | HIGH | DONE | AUTH-12, DB-03 |
| AUTH-19 | [x] | Token revocation on password change | M1 | MEDIUM | DONE | AUTH-07 |

### 6.1 Ownership Coverage Notes

- AUTH-12 is partially covered: addresses, account profile, notifications, cart and order ownership are enforced and tested. Every remaining user-scoped resource must be re-checked before the task can be ticked.
- AUTH-14 covers 401, 403, 422 and 429 with automated tests. The production 500 sanitiser in `bootstrap/app.php` still needs a dedicated test.
- `role` and `email_verified_at` were removed from `User::$fillable`; only the seeder, factory and artisan commands write them, through `forceFill`.
- "Remember me" is implemented: a remembered login issues a token that expires in 30 days, a normal login keeps the previous no-expiry behaviour.
- Auth endpoints are throttled: register 5/min, login 10/min, forgot-password 3/min, reset-password 5/min, verification resend 3/min.
- Email addresses are trimmed and lowercased in register, login, forgot-password and reset-password so the stored value and the lookup always agree.

- Acceptance criteria: a fresh token grants access to `/api/auth/me`; a revoked or missing token returns 401; a customer token on any `/api/admin/*` route returns 403; no password or hash appears in any response body.

---

# 7. PRODUCT AGENDA

## 7.1 Products

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| PRD-01 | [x] | Admin product create with full validation | M2 | CRITICAL | DONE | AUTH-11, DB-05 |
| PRD-02 | [x] | Admin product read/show | M2 | CRITICAL | DONE | PRD-01 |
| PRD-03 | [x] | Admin product update | M2 | CRITICAL | DONE | PRD-02 |
| PRD-04 | [x] | Admin product delete (soft delete) and restore | M2 | HIGH | DONE | PRD-03 |
| PRD-05 | [x] | Product image upload, validation, ordering, primary image | M2 | HIGH | DONE | PRD-01 |
| PRD-06 | [x] | Product image delete with storage cleanup | M2 | MEDIUM | DONE | PRD-05 |
| PRD-07 | [ ] | Product status (draft, active, archived) with bulk patch | M2 | HIGH | IN PROGRESS | PRD-03 |
| PRD-08 | [x] | Price stored as decimal/minor units with currency formatting | M2 | CRITICAL | DONE | PRD-01, DB-13 |
| PRD-09 | [ ] | SKU generation and uniqueness validation per shop | M2 | HIGH | IN PROGRESS | PRD-01, DB-06 |
| PRD-10 | [ ] | Description (rich text or markdown) with sanitization | M2 | HIGH | NOT STARTED | PRD-01 |
| PRD-11 | [x] | Slug generation with uniqueness and friendly URLs | M2 | HIGH | DONE | PRD-11, DB-06 |
| PRD-12 | [ ] | Public catalog list with pagination, sorting, and shop filter | M2 | CRITICAL | IN PROGRESS | PRD-02 |
| PRD-13 | [x] | Public catalog detail by slug with variants, images, attributes | M2 | CRITICAL | DONE | PRD-12 |
| PRD-14 | [x] | Featured products endpoint for the home page | M2 | MEDIUM | DONE | PRD-12 |
| PRD-15 | [x] | Facets endpoint (categories, brands, price range, attributes) | M2 | HIGH | DONE | PRD-12 |
| PRD-16 | [x] | Search across name, description, SKU | M2 | HIGH | DONE | PRD-12 |
| PRD-17 | [x] | Filtering by category, brand, price, attribute values, in-stock | M2 | HIGH | DONE | PRD-12, PRD-19 |
| PRD-18 | [x] | Sorting by newest, price, name, popularity | M2 | MEDIUM | DONE | PRD-12 |
| PRD-19 | [x] | Dynamic variant filtering resolving variant ids from selected attribute values | M2 | CRITICAL | DONE | PRD-24, PRD-25 |
| PRD-20 | [ ] | Only active products visible to customers; drafts hidden | M2 | CRITICAL | IN PROGRESS | PRD-07, PRD-12 |
| PRD-21 | [x] | Product tests: CRUD, visibility rules, filters, search, sorting | M2 | HIGH | DONE | PRD-13 |

## 7.2 Categories

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| PCD-01 | [ ] | Category create | M2 | HIGH | NOT STARTED | DB-03 |
| PCD-02 | [ ] | Category read/list as tree | M2 | HIGH | NOT STARTED | PCD-01 |
| PCD-03 | [ ] | Category update | M2 | HIGH | NOT STARTED | PCD-02 |
| PCD-04 | [ ] | Category delete with product handling (block or reassign) | M2 | HIGH | NOT STARTED | PCD-03 |
| PCD-05 | [ ] | Category validation (name, parent, image, uniqueness per shop) | M2 | HIGH | NOT STARTED | PCD-01 |
| PCD-06 | [ ] | Product relationship exposed in category detail (count, preview) | M2 | MEDIUM | NOT STARTED | PCD-02 |
| PCD-07 | [ ] | Parent category cycle prevention on update | M2 | MEDIUM | NOT STARTED | PCD-03 |

## 7.3 Brands

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| PBD-01 | [ ] | Brand create | M2 | HIGH | NOT STARTED | DB-04 |
| PBD-02 | [ ] | Brand read/list | M2 | HIGH | NOT STARTED | PBD-01 |
| PBD-03 | [ ] | Brand update | M2 | HIGH | NOT STARTED | PBD-02 |
| PBD-04 | [ ] | Brand delete with product handling | M2 | HIGH | NOT STARTED | PBD-03 |
| PBD-05 | [ ] | Brand logo upload with validation | M2 | MEDIUM | NOT STARTED | PBD-01 |
| PBD-06 | [ ] | Brand validation (name, slug, uniqueness per shop) | M2 | HIGH | NOT STARTED | PBD-01 |
| PBD-07 | [x] | Product relationship: brand filter on catalog | M2 | HIGH | DONE | PBD-02, PRD-17 |

## 7.4 Variants and Attributes

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| PVA-01 | [ ] | Attribute create/read/update/delete | M2 | HIGH | NOT STARTED | DB-09 |
| PVA-02 | [ ] | Attribute value create/read/update/delete scoped to attribute | M2 | HIGH | NOT STARTED | PVA-01 |
| PVA-03 | [x] | Variant creation under product with attribute value mapping | M2 | CRITICAL | DONE | PVA-02, PRD-01 |
| PVA-04 | [x] | Variant SKU unique per shop | M2 | HIGH | DONE | PVA-03, DB-06 |
| PVA-05 | [x] | Variant price override falling back to product price | M2 | CRITICAL | DONE | PVA-03 |
| PVA-06 | [x] | Variant stock display joined from inventories | M2 | CRITICAL | DONE | PVA-03, INV-01 |
| PVA-07 | [ ] | Variant selection on product detail with disabled combinations | M2 | HIGH | IN PROGRESS | PVA-03, PRD-19 |
| PVA-08 | [x] | Variant update and delete handling with product references | M2 | HIGH | DONE | PVA-03 |
| PVA-09 | [x] | Combination uniqueness (no duplicate attribute value sets) | M2 | MEDIUM | DONE | PVA-03, DB-06 |
| PVA-10 | [x] | Tests for variant resolution and stock display | M2 | HIGH | DONE | PVA-07 |

Verification evidence for this section: `docker compose exec app php artisan test --filter=CatalogTest` -> 24 passed (126 assertions); the last full-suite run before the final catalog additions was 228 passed (1020 assertions). Frontend `npx vue-tsc --noEmit -p tsconfig.app.json` and `npm run build` both pass after the shop-facet and swatch changes.

Deliberate scope decisions recorded during this pass:
- `products` keeps a boolean `is_active`; no draft/archived column was added, so PRD-07 and PRD-20 stay IN PROGRESS rather than DONE.
- No HTML sanitizer dependency was introduced, so PRD-10 remains NOT STARTED and rich-text rendering must stay escaped.
- Attribute and attribute-value management is still read-only on the public API; PVA-01/PVA-02 stay NOT STARTED.
- Variant CRUD is intentionally part of the product write endpoints, not standalone `/variants` routes.

---

# 8. CART AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| CRT-01 | [ ] | Add item to cart by variant with quantity | M3 | CRITICAL | NOT STARTED | PVA-03, AUTH-09 |
| CRT-02 | [ ] | Add item with auto-select of default variant when no variant given | M3 | MEDIUM | NOT STARTED | CRT-01 |
| CRT-03 | [ ] | Update item quantity | M3 | CRITICAL | NOT STARTED | CRT-01 |
| CRT-04 | [ ] | Remove item | M3 | HIGH | NOT STARTED | CRT-01 |
| CRT-05 | [ ] | Clear cart | M3 | MEDIUM | NOT STARTED | CRT-04 |
| CRT-06 | [ ] | Cart count (badge total) | M3 | HIGH | NOT STARTED | CRT-01 |
| CRT-07 | [ ] | Cart list with product, variant, image, price, line total | M3 | CRITICAL | NOT STARTED | CRT-01 |
| CRT-08 | [ ] | Subtotal computed server-side only | M3 | CRITICAL | NOT STARTED | CRT-07 |
| CRT-09 | [ ] | Discount line from applied coupon | M3 | HIGH | NOT STARTED | CPN-14 |
| CRT-10 | [ ] | Shipping cost estimate per cart | M3 | HIGH | NOT STARTED | SHP-01 |
| CRT-11 | [ ] | Grand total returned by totals endpoint | M3 | CRITICAL | NOT STARTED | CRT-08, CRT-09, CRT-10 |
| CRT-12 | [ ] | Stock validation on add (reject more than available) | M3 | CRITICAL | NOT STARTED | INV-01 |
| CRT-13 | [ ] | Stock validation on update | M3 | CRITICAL | NOT STARTED | CRT-03, INV-01 |
| CRT-14 | [ ] | Variant validation (exists, active, belongs to product) | M3 | HIGH | NOT STARTED | CRT-01 |
| CRT-15 | [ ] | Inactive/removed product handling in existing cart lines | M3 | HIGH | NOT STARTED | PRD-20 |
| CRT-16 | [ ] | Merge guest cart into user cart on login | M3 | HIGH | NOT STARTED | AUTH-02, CRT-01 |
| CRT-17 | [ ] | Cart persistence across devices for logged-in users | M3 | MEDIUM | NOT STARTED | CRT-01 |
| CRT-18 | [ ] | Cart API tests (add, update, remove, totals, stock errors) | M3 | CRITICAL | NOT STARTED | CRT-11 |

- Acceptance criteria: `GET cart/totals` equals a manual recomputation of subtotal minus discount plus shipping; adding more than available stock returns 422 with a clear message; a customer can never see or modify another user's cart.

---

# 9. WISHLIST AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| WSH-01 | [ ] | Add product to wishlist | M3 | HIGH | NOT STARTED | AUTH-09, DB-15 |
| WSH-02 | [ ] | Remove product from wishlist | M3 | HIGH | NOT STARTED | WSH-01 |
| WSH-03 | [ ] | Wishlist list with product details, price, availability | M3 | HIGH | NOT STARTED | WSH-01 |
| WSH-04 | [ ] | Duplicate prevention (same product cannot be added twice) | M3 | HIGH | NOT STARTED | WSH-01, DB-16 |
| WSH-05 | [ ] | Move to cart with quantity choice | M3 | MEDIUM | NOT STARTED | WSH-01, CRT-01 |
| WSH-06 | [ ] | Wishlist item count for navigation badge | M3 | LOW | NOT STARTED | WSH-01 |
| WSH-07 | [ ] | Authentication required; 401 for guests | M3 | HIGH | NOT STARTED | AUTH-09 |
| WSH-08 | [ ] | Wishlist tests | M3 | MEDIUM | NOT STARTED | WSH-03 |

---

# 10. CHECKOUT AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| CHK-01 | [ ] | Checkout begin: revalidate cart contents and prices server-side | M3 | CRITICAL | NOT STARTED | CRT-11 |
| CHK-02 | [ ] | Address selection and validation (saved address or new) | M3 | CRITICAL | NOT STARTED | AUTH-18, CHK-01 |
| CHK-03 | [ ] | Shipping method selection with fee and estimate | M3 | CRITICAL | NOT STARTED | SHP-01, CHK-01 |
| CHK-04 | [ ] | Coupon application with full rule validation | M3 | HIGH | NOT STARTED | CPN-14, CHK-01 |
| CHK-05 | [ ] | Coupon removal and recalculation | M3 | MEDIUM | NOT STARTED | CHK-04 |
| CHK-06 | [ ] | Order summary with items, subtotal, discount, shipping, total | M3 | CRITICAL | NOT STARTED | CHK-02, CHK-03, CHK-04 |
| CHK-07 | [ ] | Final total calculation in one shared service | M3 | CRITICAL | NOT STARTED | CHK-06 |
| CHK-08 | [ ] | Stock re-validation at checkout start | M3 | CRITICAL | NOT STARTED | INV-02, CHK-01 |
| CHK-09 | [ ] | Order creation in a database transaction | M3 | CRITICAL | NOT STARTED | CHK-07, ORD-01 |
| CHK-10 | [ ] | Payment method selection and order link | M3 | HIGH | NOT STARTED | PAY-01, CHK-09 |
| CHK-11 | [ ] | Checkout confirm step (place order) | M3 | CRITICAL | NOT STARTED | CHK-09, CHK-10 |
| CHK-12 | [ ] | Checkout cancel with cleanup (released reservations, cart intact) | M3 | HIGH | NOT STARTED | CHK-09 |
| CHK-13 | [ ] | Validation messages surfaced per step | M3 | HIGH | NOT STARTED | CHK-02 |
| CHK-14 | [ ] | Error handling: out of stock, expired coupon, invalid address | M3 | CRITICAL | NOT STARTED | CHK-13 |
| CHK-15 | [ ] | Checkout tests: happy path plus each failure path | M3 | CRITICAL | NOT STARTED | CHK-11, CHK-14 |

- Acceptance criteria: a successful checkout produces exactly one order, one payment record, stock deducted once, and a confirmation notification; every failure path leaves no partial order, no partial stock deduction, and no orphan reservation.

---

# 11. ORDER AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| ORD-01 | [ ] | Create order from cart with item snapshots | M4 | CRITICAL | NOT STARTED | CHK-09 |
| ORD-02 | [ ] | Order detail for customer (items, totals, address, status) | M4 | CRITICAL | NOT STARTED | ORD-01, AUTH-12 |
| ORD-03 | [ ] | Customer order list with pagination | M4 | HIGH | NOT STARTED | ORD-02 |
| ORD-04 | [ ] | Order items store title, price, variant attributes at checkout time | M4 | CRITICAL | NOT STARTED | ORD-01 |
| ORD-05 | [ ] | Order status field with allowed values | M4 | CRITICAL | NOT STARTED | ORD-01 |
| ORD-06 | [ ] | Status transition service enforcing the state machine | M4 | CRITICAL | NOT STARTED | ORD-05 |
| ORD-07 | [ ] | Status history records (from, to, actor, note, timestamp) | M4 | CRITICAL | NOT STARTED | ORD-06 |
| ORD-08 | [ ] | Customer cancel allowed only before processing | M4 | HIGH | NOT STARTED | ORD-06, INV-06 |
| ORD-09 | [ ] | Admin order list with filter by status, date, shop, customer | M4 | HIGH | NOT STARTED | ORD-03 |
| ORD-10 | [ ] | Admin order detail with full history and actions | M4 | HIGH | NOT STARTED | ORD-02 |
| ORD-11 | [ ] | Admin status transition endpoint | M4 | CRITICAL | NOT STARTED | ORD-06 |
| ORD-12 | [ ] | Order search by order number, customer, product | M4 | MEDIUM | NOT STARTED | ORD-09 |
| ORD-13 | [ ] | Order receipt endpoint (HTML or PDF) | M4 | MEDIUM | NOT STARTED | ORD-02 |
| ORD-14 | [ ] | Guest order lookup by order number plus email/phone check | M4 | MEDIUM | NOT STARTED | ORD-02 |
| ORD-15 | [ ] | Order tests: creation, transitions, invalid transitions, cancel restore | M4 | CRITICAL | NOT STARTED | ORD-11, ORD-08 |

## 11.1 Order Status Flow

`Pending -> Confirmed -> Processing -> Shipped -> Delivered`

| From | Allowed Next | Actor | Side Effects | Status |
| ---- | ------------ | ----- | ------------- | ------ |
| Pending | Confirmed, Cancelled | Admin, Customer (cancel only) | On confirm: validate stock again, notify customer | NOT STARTED |
| Confirmed | Processing | Admin | On processing: deduct stock, notify customer | NOT STARTED |
| Processing | Shipped | Admin | Create shipment, notify customer with tracking | NOT STARTED |
| Shipped | Delivered | Admin, Customer (confirm receipt) | Notify customer, enable review | NOT STARTED |
| Delivered | (terminal) | - | Review allowed for purchased products | NOT STARTED |
| Cancelled | (terminal) | - | Restore stock, release coupon usage, refund payment | NOT STARTED |

## 11.2 Transition Rules

| Transition | Valid | Notes |
| ---------- | ----- | ----- |
| Pending -> Confirmed | Yes | Admin only |
| Pending -> Cancelled | Yes | Customer or admin; stock not yet deducted |
| Pending -> Processing | No | Must confirm first |
| Pending -> Shipped | No | Must pass processing |
| Pending -> Delivered | No | Must pass all stages |
| Confirmed -> Processing | Yes | Admin only; deducts stock exactly once |
| Confirmed -> Shipped | No | Must process first |
| Confirmed -> Cancelled | Yes | Admin only; restores nothing since stock not deducted |
| Processing -> Shipped | Yes | Admin only; shipment must exist |
| Processing -> Cancelled | No | Requires admin override with reason recorded |
| Processing -> Delivered | No | Must ship first |
| Shipped -> Delivered | Yes | Admin or customer confirm receipt |
| Shipped -> Cancelled | No | Not allowed |
| Delivered -> any | No | Terminal state |
| Cancelled -> any | No | Terminal state |
| Same -> Same | No | Reject duplicate transition with 422 |

- Acceptance criteria: every attempted transition is evaluated by the state machine, invalid transitions return 422 with the current status, and each valid transition writes exactly one history row.

---

# 12. PAYMENT AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| PAY-01 | [ ] | Payment record created with the order (transaction) | M4 | CRITICAL | NOT STARTED | ORD-01 |
| PAY-02 | [ ] | Payment status values (pending, paid, failed, refunded) | M4 | CRITICAL | NOT STARTED | PAY-01 |
| PAY-03 | [ ] | Payment transaction rows for each attempt | M4 | HIGH | NOT STARTED | PAY-01 |
| PAY-04 | [ ] | Payment success handler: mark paid, deduct stock, move order | M4 | CRITICAL | NOT STARTED | PAY-02, INV-03 |
| PAY-05 | [ ] | Payment failure handler: record failure, release reservation | M4 | CRITICAL | NOT STARTED | PAY-02 |
| PAY-06 | [ ] | Idempotency: duplicate success callback does not double-deduct | M4 | CRITICAL | NOT STARTED | PAY-04 |
| PAY-07 | [ ] | Duplicate payment prevention on an already paid order | M4 | CRITICAL | NOT STARTED | PAY-02 |
| PAY-08 | [ ] | Payment history for customer and admin | M4 | HIGH | NOT STARTED | PAY-03 |
| PAY-09 | [ ] | Order/payment relationship enforced (one active payment per order) | M4 | HIGH | NOT STARTED | DB-04 |
| PAY-10 | [ ] | Sandbox/test payment simulation with deterministic outcomes | M4 | HIGH | NOT STARTED | PAY-04 |
| PAY-11 | [ ] | Refund flow for cancelled paid orders | M4 | MEDIUM | NOT STARTED | PAY-02, ORD-08 |
| PAY-12 | [ ] | Admin payment list and detail pages data | M4 | MEDIUM | NOT STARTED | PAY-08 |
| PAY-13 | [ ] | Payment tests: success, failure, duplicate, idempotency | M4 | CRITICAL | NOT STARTED | PAY-06 |
| PAY-14 | [ ] | Confirm no PAN, CVV, or full card number column exists anywhere | M1 | CRITICAL | NOT STARTED | DB-03 |

- Acceptance criteria: only masked references (gateway id, last4, brand) are stored; stock is deducted exactly once per paid order; retrying the same payment callback leaves totals and stock unchanged.

---

# 13. COUPON AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| CPN-01 | [ ] | Coupon create | M4 | HIGH | NOT STARTED | AUTH-11 |
| CPN-02 | [ ] | Coupon update | M4 | HIGH | NOT STARTED | CPN-01 |
| CPN-03 | [ ] | Coupon delete | M4 | MEDIUM | NOT STARTED | CPN-01 |
| CPN-04 | [ ] | Activate/deactivate toggle | M4 | HIGH | NOT STARTED | CPN-02 |
| CPN-05 | [ ] | Discount type: percentage or fixed amount | M4 | CRITICAL | NOT STARTED | CPN-01 |
| CPN-06 | [ ] | Discount value with type-specific validation | M4 | CRITICAL | NOT STARTED | CPN-05 |
| CPN-07 | [ ] | Minimum order amount requirement | M4 | HIGH | NOT STARTED | CPN-01 |
| CPN-08 | [ ] | Maximum discount cap for percentage coupons | M4 | HIGH | NOT STARTED | CPN-06 |
| CPN-09 | [ ] | Start date and expiration date | M4 | HIGH | NOT STARTED | CPN-01 |
| CPN-10 | [ ] | Total usage limit | M4 | HIGH | NOT STARTED | CPN-01 |
| CPN-11 | [ ] | Per-user usage limit | M4 | HIGH | NOT STARTED | CPN-10 |
| CPN-12 | [ ] | Shop scoping so coupons apply only to their shop | M4 | MEDIUM | NOT STARTED | CPN-01, DB-24 |
| CPN-13 | [ ] | Coupon code uniqueness and case-insensitive lookup | M4 | HIGH | NOT STARTED | CPN-01, DB-06 |
| CPN-14 | [ ] | Validation pipeline in fixed order: active -> expiry window -> usage limit -> per-user limit -> minimum amount -> apply | M4 | CRITICAL | NOT STARTED | CPN-09, CPN-10, CPN-11, CPN-07 |
| CPN-15 | [ ] | Validate endpoint returning discount amount and messages | M4 | HIGH | NOT STARTED | CPN-14 |
| CPN-16 | [ ] | Usage tracking written on order creation | M4 | CRITICAL | NOT STARTED | CPN-14, ORD-01 |
| CPN-17 | [ ] | Usage release on order cancellation | M4 | HIGH | NOT STARTED | CPN-16, ORD-08 |
| CPN-18 | [ ] | Coupon tests for each rejection reason and both discount types | M4 | HIGH | NOT STARTED | CPN-15 |

- Acceptance criteria: every rejection reason returns a distinct machine-readable message; the discount never exceeds the subtotal or the configured cap; usage counts cannot exceed limits under concurrent checkout attempts.

---

# 14. INVENTORY AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ |---------- |
| INV-01 | [ ] | Stock record per variant (one row, unique per variant) | M5 | CRITICAL | NOT STARTED | PVA-03 |
| INV-02 | [ ] | Stock increase with reason code | M5 | CRITICAL | NOT STARTED | INV-01 |
| INV-03 | [ ] | Stock decrease on order confirmation with row locking | M5 | CRITICAL | NOT STARTED | INV-01, ORD-06 |
| INV-04 | [ ] | Manual stock adjustment with mandatory reason and note | M5 | HIGH | NOT STARTED | INV-02 |
| INV-05 | [ ] | Inventory transaction ledger row for every change | M5 | CRITICAL | NOT STARTED | INV-02, INV-03 |
| INV-06 | [ ] | Stock restoration on order cancellation | M5 | CRITICAL | NOT STARTED | INV-03, ORD-08 |
| INV-07 | [ ] | Negative stock prevention (conditional update / `lockForUpdate`) | M5 | CRITICAL | NOT STARTED | INV-03 |
| INV-08 | [ ] | Atomic stock reservation at checkout with expiry | M5 | HIGH | NOT STARTED | CHK-08 |
| INV-09 | [ ] | Reservation release on payment failure or timeout | M5 | HIGH | NOT STARTED | INV-08 |
| INV-10 | [ ] | Low stock threshold and low-stock detection | M5 | HIGH | NOT STARTED | INV-01 |
| INV-11 | [ ] | Out-of-stock flag surfaced in catalog and variant selection | M5 | HIGH | NOT STARTED | INV-10 |
| INV-12 | [ ] | Variant stock display on product detail | M5 | HIGH | NOT STARTED | INV-01, PVA-06 |
| INV-13 | [ ] | Admin inventory list with filters (shop, low stock, category) | M5 | HIGH | NOT STARTED | INV-01 |
| INV-14 | [ ] | Inventory transaction history per record | M5 | HIGH | NOT STARTED | INV-05 |
| INV-15 | [ ] | Low-stock notification trigger | M5 | MEDIUM | NOT STARTED | INV-10, NTF-01 |
| INV-16 | [ ] | Concurrency test: parallel checkout never oversells | M5 | CRITICAL | NOT STARTED | INV-07 |
| INV-17 | [ ] | Reconciliation check: variant stock equals ledger net change | M5 | MEDIUM | NOT STARTED | INV-05 |

- Acceptance criteria: 100 parallel checkout attempts for a stock of 10 produce exactly 10 successful orders; every stock delta is traceable to exactly one ledger row; stock never becomes negative in any test.

---

# 15. SHIPPING AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| SHP-01 | [ ] | Shipping method create/read/update/delete with fee and ETA | M5 | HIGH | NOT STARTED | AUTH-11, DB-22 |
| SHP-02 | [ ] | Public shipping method list for checkout | M5 | HIGH | NOT STARTED | SHP-01 |
| SHP-03 | [ ] | Shop scoping on shipping methods | M5 | MEDIUM | NOT STARTED | SHP-01 |
| SHP-04 | [ ] | Shipment creation when order moves to shipped | M5 | CRITICAL | NOT STARTED | ORD-06 |
| SHP-05 | [ ] | Tracking number generation or entry, unique per shipment | M5 | HIGH | NOT STARTED | SHP-04 |
| SHP-06 | [ ] | Shipment status values and update endpoint | M5 | HIGH | NOT STARTED | SHP-04 |
| SHP-07 | [ ] | Tracking event timeline creation per status change | M5 | HIGH | NOT STARTED | SHP-06, DB-31 |
| SHP-08 | [ ] | Delivery confirmation sets order to delivered | M5 | HIGH | NOT STARTED | ORD-06, SHP-06 |
| SHP-09 | [ ] | Shipment relationship to order and shipping method | M5 | HIGH | NOT STARTED | SHP-04, DB-04 |
| SHP-10 | [ ] | Admin shipment list with filters and detail view data | M5 | HIGH | NOT STARTED | SHP-06 |
| SHP-11 | [ ] | Customer tracking view by order number | M5 | HIGH | NOT STARTED | SHP-07, ORD-14 |
| SHP-12 | [ ] | Shipping fee applied once per order in totals | M5 | HIGH | NOT STARTED | SHP-02, CHK-07 |
| SHP-13 | [ ] | Shipping tests including fee application and flow transitions | M5 | HIGH | NOT STARTED | SHP-12 |

## 15.1 Shipment Flow

`Pending -> Processing -> Shipped -> Delivered`

| From | Allowed Next | Actor | Notes | Status |
| ---- | ------------ | ----- | ----- | ------ |
| Pending | Processing, Shipped, Cancelled | Admin | Created with order confirmation | NOT STARTED |
| Processing | Shipped, Cancelled | Admin | Requires tracking number before shipping | NOT STARTED |
| Shipped | Delivered | Admin, Customer | Delivered triggers review eligibility | NOT STARTED |
| Delivered | (terminal) | - | No further transitions | NOT STARTED |
| Cancelled | (terminal) | - | Order moves to cancelled path | NOT STARTED |

---

# 16. NOTIFICATION AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| NTF-01 | [ ] | Notification creation service | M5 | HIGH | NOT STARTED | DB-28 |
| NTF-02 | [ ] | Notification list for user (paginated) | M5 | HIGH | NOT STARTED | NTF-01 |
| NTF-03 | [ ] | Read/unread state and unread count | M5 | HIGH | NOT STARTED | NTF-01 |
| NTF-04 | [ ] | Mark as read and mark all as read | M5 | HIGH | NOT STARTED | NTF-03 |
| NTF-05 | [ ] | Order notification on creation, confirmation, cancellation | M5 | HIGH | NOT STARTED | NTF-01, ORD-06 |
| NTF-06 | [ ] | Payment notification on success and failure | M5 | HIGH | NOT STARTED | NTF-01, PAY-04 |
| NTF-07 | [ ] | Shipping notification with tracking number | M5 | HIGH | NOT STARTED | NTF-01, SHP-05 |
| NTF-08 | [ ] | Low-stock notification for admin | M5 | MEDIUM | NOT STARTED | NTF-01, INV-15 |
| NTF-09 | [ ] | Review moderation notification for admin and author | M5 | MEDIUM | NOT STARTED | NTF-01, REV-10 |
| NTF-10 | [ ] | Admin notification center (list, unread count, delete) | M5 | HIGH | NOT STARTED | NTF-02 |
| NTF-11 | [ ] | Ownership check: user only reads own notifications | M5 | CRITICAL | NOT STARTED | NTF-02 |
| NTF-12 | [ ] | Notification tests including ownership isolation | M5 | HIGH | NOT STARTED | NTF-11 |

---

# 17. REVIEW AGENDA

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| REV-01 | [ ] | Create review with rating and text | M6 | HIGH | NOT STARTED | AUTH-09, DB-26 |
| REV-02 | [ ] | Verified purchase rule: order item must exist with delivered status | M6 | CRITICAL | NOT STARTED | REV-01, ORD-06 |
| REV-03 | [ ] | Update own review (text and rating) | M6 | MEDIUM | NOT STARTED | REV-01 |
| REV-04 | [ ] | Delete own review or admin delete | M6 | MEDIUM | NOT STARTED | REV-01 |
| REV-05 | [ ] | Rating value constrained to 1-5 | M6 | HIGH | NOT STARTED | REV-01 |
| REV-06 | [ ] | Review text length limit and sanitization | M6 | HIGH | NOT STARTED | REV-01 |
| REV-07 | [ ] | Review image upload (multiple, size and type limits) | M6 | MEDIUM | NOT STARTED | REV-01 |
| REV-08 | [ ] | Product relationship with rating aggregate (average, count) | M6 | HIGH | NOT STARTED | REV-01 |
| REV-09 | [ ] | Customer relationship with review list | M6 | MEDIUM | NOT STARTED | REV-01 |
| REV-10 | [ ] | Review moderation: pending, approved, rejected states | M6 | HIGH | NOT STARTED | REV-01 |
| REV-11 | [ ] | Admin approve and reject endpoints | M6 | HIGH | NOT STARTED | REV-10 |
| REV-12 | [ ] | Duplicate review prevention (one review per product per customer) | M6 | HIGH | NOT STARTED | REV-01, DB-06 |
| REV-13 | [ ] | Only approved reviews visible publicly | M6 | HIGH | NOT STARTED | REV-10 |
| REV-14 | [ ] | Shop scoping on reviews | M6 | MEDIUM | NOT STARTED | REV-01 |
| REV-15 | [ ] | Review tests: verified purchase gate, moderation, duplicates | M6 | HIGH | NOT STARTED | REV-11 |

- Acceptance criteria: a user without a delivered order for the product receives 403 on review creation; unapproved reviews never appear in the public review list; duplicate submission for the same product is rejected.

---

# 18. CUSTOMER WEBSITE AGENDA

## 18.1 Pages

| ID | Done | Page | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| CUI-01 | [ ] | Home page with hero, featured products, categories | M6 | HIGH | NOT STARTED | PRD-14 |
| CUI-02 | [ ] | Product listing with filter sidebar and sorting | M6 | CRITICAL | NOT STARTED | PRD-12, PRD-17, PRD-18 |
| CUI-03 | [ ] | Product detail with gallery, zoom, variants, stock badge | M6 | CRITICAL | NOT STARTED | PRD-13, PVA-07, INV-12 |
| CUI-04 | [ ] | Category page | M6 | HIGH | NOT STARTED | CUI-02, PCD-02 |
| CUI-05 | [ ] | Brand page | M6 | HIGH | NOT STARTED | CUI-02, PBD-02 |
| CUI-06 | [ ] | Search results page | M6 | HIGH | NOT STARTED | PRD-16 |
| CUI-07 | [ ] | Cart page/drawer | M6 | CRITICAL | NOT STARTED | CRT-11 |
| CUI-08 | [ ] | Wishlist page | M6 | HIGH | NOT STARTED | WSH-03 |
| CUI-09 | [ ] | Checkout multi-step flow | M6 | CRITICAL | NOT STARTED | CHK-06 |
| CUI-10 | [ ] | Order success/confirmation page | M6 | CRITICAL | NOT STARTED | CHK-11 |
| CUI-11 | [ ] | Order history with status stepper | M6 | HIGH | NOT STARTED | ORD-03 |
| CUI-12 | [ ] | Order detail with items, totals, actions, receipt | M6 | HIGH | NOT STARTED | ORD-02 |
| CUI-13 | [ ] | Order tracking page | M6 | MEDIUM | NOT STARTED | SHP-11 |
| CUI-14 | [ ] | Login page | M6 | CRITICAL | NOT STARTED | AUTH-02 |
| CUI-15 | [ ] | Registration page | M6 | HIGH | NOT STARTED | AUTH-01 |
| CUI-16 | [ ] | Forgot/reset password pages | M6 | MEDIUM | NOT STARTED | AUTH-06 |
| CUI-17 | [ ] | Customer dashboard | M6 | MEDIUM | NOT STARTED | AUTH-04 |
| CUI-18 | [ ] | Profile page (edit profile, change password) | M6 | HIGH | NOT STARTED | AUTH-18 |
| CUI-19 | [ ] | Address manager (list, add, edit, set default, delete) | M6 | HIGH | NOT STARTED | AUTH-18 |
| CUI-20 | [ ] | Notifications page | M6 | MEDIUM | NOT STARTED | NTF-02 |
| CUI-21 | [ ] | Reviews page (my reviews, write review) | M6 | MEDIUM | NOT STARTED | REV-01 |
| CUI-22 | [ ] | Shop listing and shop page | M6 | MEDIUM | NOT STARTED | DB-29 |
| CUI-23 | [ ] | Public header, navigation, footer, mobile menu | M6 | HIGH | NOT STARTED | - |
| CUI-24 | [ ] | 404 and error pages | M6 | MEDIUM | NOT STARTED | - |

## 18.2 Per-Page Quality Gate

Apply this gate to every page above before marking its task `[x]`.

| ID | Done | Check | Owner | Priority | Status | Depends On |
| -- | ---- | ----- | ----- | -------- | ------ | ---------- |
| CUIQ-01 | [ ] | Loading state (skeleton or spinner) present | M6 | HIGH | NOT STARTED | - |
| CUIQ-02 | [ ] | Error state with retry action | M6 | HIGH | NOT STARTED | - |
| CUIQ-03 | [ ] | Empty state with helpful message and call to action | M6 | HIGH | NOT STARTED | - |
| CUIQ-04 | [ ] | Responsive at mobile, tablet, desktop breakpoints | M6 | HIGH | NOT STARTED | - |
| CUIQ-05 | [ ] | API integration via Axios service, no direct fetch in components | M6 | HIGH | NOT STARTED | - |
| CUIQ-06 | [ ] | Navigation links correct, breadcrumbs, back behaviour | M6 | MEDIUM | NOT STARTED | - |
| CUIQ-07 | [ ] | Client-side validation with server error mapping | M6 | HIGH | NOT STARTED | - |
| CUIQ-08 | [ ] | Guards for authenticated routes redirect to login | M6 | HIGH | NOT STARTED | - |
| CUIQ-09 | [ ] | Accessible labels, focus order, alt text | M6 | MEDIUM | NOT STARTED | - |
| CUIQ-10 | [ ] | No console errors or unhandled promise rejections | M6 | HIGH | NOT STARTED | - |

- Acceptance criteria: every page in 18.1 passes all ten checks in 18.2; verification is done on a real device width, not only in a desktop browser.

---

# 19. ADMIN DASHBOARD AGENDA

| ID | Done | Screen | Owner | Priority | Status | Depends On |
| -- | ---- | ------ | ----- | -------- | ------ | ---------- |
| ADB-01 | [ ] | Dashboard with KPI cards (revenue, orders, customers) | M6 | HIGH | NOT STARTED | AUTH-11 |
| ADB-02 | [ ] | Revenue chart with date range filter | M6 | MEDIUM | NOT STARTED | ADB-01 |
| ADB-03 | [ ] | Recent orders table and low-stock alerts on dashboard | M6 | MEDIUM | NOT STARTED | ADB-01, INV-13 |
| ADB-04 | [ ] | Products list with search, filter, status, pagination | M6 | CRITICAL | NOT STARTED | PRD-01 |
| ADB-05 | [ ] | Add/edit product form with images and variants | M6 | CRITICAL | NOT STARTED | PRD-01, PVA-03 |
| ADB-06 | [ ] | Categories list and tree view manager | M6 | HIGH | NOT STARTED | PCD-02 |
| ADB-07 | [ ] | Brands list and form | M6 | HIGH | NOT STARTED | PBD-02 |
| ADB-08 | [ ] | Inventory list with adjust-stock action and transaction history | M6 | CRITICAL | NOT STARTED | INV-13, INV-14 |
| ADB-09 | [ ] | Orders list with status filter and search | M6 | CRITICAL | NOT STARTED | ORD-09 |
| ADB-10 | [ ] | Order detail with status transition actions and history | M6 | CRITICAL | NOT STARTED | ORD-10, ORD-11 |
| ADB-11 | [ ] | Payments list and detail | M6 | MEDIUM | NOT STARTED | PAY-12 |
| ADB-12 | [ ] | Shipping methods list and form | M6 | HIGH | NOT STARTED | SHP-01 |
| ADB-13 | [ ] | Shipments list and detail with tracking update | M6 | HIGH | NOT STARTED | SHP-10 |
| ADB-14 | [ ] | Customers list with search and detail page | M6 | HIGH | NOT STARTED | AUTH-04 |
| ADB-15 | [ ] | Coupons list and form with usage counters | M6 | HIGH | NOT STARTED | CPN-01 |
| ADB-16 | [ ] | Reviews moderation queue with approve/reject/delete | M6 | HIGH | NOT STARTED | REV-11 |
| ADB-17 | [ ] | Notifications center with unread badge | M6 | MEDIUM | NOT STARTED | NTF-10 |
| ADB-18 | [ ] | Reports with CSV and PDF export | M6 | HIGH | NOT STARTED | ORD-09, PAY-12 |
| ADB-19 | [ ] | Settings screen bound to the settings endpoint | M6 | MEDIUM | NOT STARTED | AUTH-11 |
| ADB-20 | [ ] | Shops management (multi-shop: list, create, edit, status) | M6 | HIGH | NOT STARTED | DB-29 |
| ADB-21 | [ ] | Admin layout: sidebar, top bar, breadcrumb, responsive collapse | M6 | HIGH | NOT STARTED | - |
| ADB-22 | [ ] | Admin route guard rejecting non-admin users | M6 | CRITICAL | NOT STARTED | AUTH-11 |

## 19.1 Admin Interaction Rules

| ID | Done | Rule | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| ADBQ-01 | [ ] | Every button performs a real action against the API | M6 | CRITICAL | NOT STARTED | ADB-04..ADB-20 |
| ADBQ-02 | [ ] | No placeholder, disabled-without-reason, or no-op controls | M6 | CRITICAL | NOT STARTED | ADBQ-01 |
| ADBQ-03 | [ ] | Every destructive action requires confirmation | M6 | HIGH | NOT STARTED | ADBQ-01 |
| ADBQ-04 | [ ] | Success and error feedback shown after every action | M6 | HIGH | NOT STARTED | ADBQ-01 |
| ADBQ-05 | [ ] | Forms show server validation errors next to fields | M6 | HIGH | NOT STARTED | ADBQ-01 |
| ADBQ-06 | [ ] | Buttons disable while a request is in flight to prevent double submit | M6 | MEDIUM | NOT STARTED | ADBQ-01 |
| ADBQ-07 | [ ] | Lists refresh after create/update/delete without a manual page reload | M6 | HIGH | NOT STARTED | ADBQ-01 |
| ADBQ-08 | [ ] | Admin never sees data outside the admin's shop scope | M1 | CRITICAL | NOT STARTED | AUTH-12 |
| ADBQ-09 | [ ] | Pagination, sorting, and filtering consistent across all lists | M6 | MEDIUM | NOT STARTED | ADB-04 |
| ADBQ-10 | [ ] | Admin screens meet the same quality gate as 18.2 | M6 | HIGH | NOT STARTED | ADB-21 |

- Acceptance criteria: a manual click-through of every admin screen finds zero dead controls; every mutation is verified to persist after a page reload.

---

# 20. API CHECKLIST

## 20.1 Per-Endpoint Gate

For every endpoint in the tracking table below, verify each item.

| ID | Done | Check | Owner | Priority | Status | Depends On |
| -- | ---- | ----- | ----- | -------- | ------ | ---------- |
| APIC-01 | [ ] | Route exists with the documented method and URI | Owner of route | CRITICAL | NOT STARTED | - |
| APIC-02 | [ ] | Controller method exists and delegates to a service | Owner of route | HIGH | NOT STARTED | APIC-01 |
| APIC-03 | [ ] | Form request validation exists (no inline array rules) | Owner of route | HIGH | NOT STARTED | APIC-02 |
| APIC-04 | [ ] | Authorization: policy, ownership check, or role middleware | Owner of route | CRITICAL | NOT STARTED | APIC-02 |
| APIC-05 | [ ] | Correct HTTP method semantics (no GET for mutations) | Owner of route | MEDIUM | NOT STARTED | APIC-01 |
| APIC-06 | [ ] | Success response uses the standard envelope and status code | Owner of route | HIGH | NOT STARTED | APIC-02 |
| APIC-07 | [ ] | Error response uses the standard error format with field errors | Owner of route | HIGH | NOT STARTED | APIC-03 |
| APIC-08 | [ ] | Authentication middleware applied where required | Owner of route | CRITICAL | NOT STARTED | APIC-04 |
| APIC-09 | [ ] | Shop scoping applied on multi-tenant queries | Owner of route | HIGH | NOT STARTED | APIC-02 |
| APIC-10 | [ ] | Pagination on all list endpoints | Owner of route | MEDIUM | NOT STARTED | APIC-02 |
| APIC-11 | [ ] | Automated test exists covering success and failure | Owner of route | HIGH | NOT STARTED | APIC-07 |
| APIC-12 | [ ] | Documented in `docs/API-REFERENCE.md` | Owner of route | MEDIUM | NOT STARTED | APIC-06 |

## 20.2 API Tracking Table

Status values: NOT STARTED / IN PROGRESS / BLOCKED / REVIEW / TESTING / DONE.

| ID | Method | Endpoint | Owner | Status | Tested |
| -- | ------ | -------- | ----- | ------ | ------ |
| API-01 | POST | `/api/auth/register` | M1 | TESTED | [x] |
| API-02 | POST | `/api/auth/login` | M1 | TESTED | [x] |
| API-03 | POST | `/api/auth/forgot-password` | M1 | TESTED | [x] |
| API-04 | POST | `/api/auth/reset-password` | M1 | TESTED | [x] |
| API-05 | GET | `/api/auth/email/verify/{id}/{hash}` | M1 | TESTED | [x] |
| API-06 | GET | `/api/auth/me` | M1 | TESTED | [x] |
| API-07 | POST | `/api/auth/logout` | M1 | TESTED | [x] |
| API-08 | POST | `/api/auth/email/verification-notification` | M1 | TESTED | [x] |
| API-09 | GET | `/api/catalog/products` | M2 | TESTED | [x] |
| API-10 | GET | `/api/catalog/featured` | M2 | TESTED | [x] |
| API-11 | GET | `/api/catalog/facets` | M2 | TESTED | [x] |
| API-12 | GET | `/api/catalog/products/{slug}` | M2 | TESTED | [x] |
| API-13 | GET | `/api/categories` | M2 | TESTED | [x] |
| API-14 | GET | `/api/brands` | M2 | TESTED | [x] |
| API-15 | GET | `/api/attributes` | M2 | NOT STARTED | [ ] |
| API-16 | GET | `/api/shipping-methods` | M5 | NOT STARTED | [ ] |
| API-17 | GET | `/api/products/{product}/reviews` | M6 | NOT STARTED | [ ] |
| API-18 | POST | `/api/coupons/validate` | M4 | NOT STARTED | [ ] |
| API-19 | GET | `/api/shops` | M1 | TESTED | [x] |
| API-20 | GET | `/api/shops/{shop:slug}` | M1 | IN PROGRESS | [ ] |
| API-21 | GET | `/api/shops/{shop:slug}/products` | M1 | TESTED | [x] |
| API-22 | GET | `/api/orders/guest/{orderNumber}` | M4 | NOT STARTED | [ ] |
| API-23 | GET | `/api/cart` | M3 | TESTED | [x] |
| API-24 | POST | `/api/cart` | M3 | TESTED | [x] |
| API-25 | PUT | `/api/cart/items/{cartItem}` | M3 | NOT STARTED | [ ] |
| API-26 | DELETE | `/api/cart/items/{cartItem}` | M3 | NOT STARTED | [ ] |
| API-27 | DELETE | `/api/cart` | M3 | TESTED | [x] |
| API-28 | GET | `/api/cart/totals` | M3 | TESTED | [x] |
| API-29 | POST | `/api/checkout` | M3 | NOT STARTED | [ ] |
| API-30 | POST | `/api/checkout/{orderNumber}/confirm` | M3 | NOT STARTED | [ ] |
| API-31 | POST | `/api/checkout/{orderNumber}/cancel` | M3 | NOT STARTED | [ ] |
| API-32 | GET | `/api/orders` | M4 | NOT STARTED | [ ] |
| API-33 | GET | `/api/orders/{orderNumber}` | M4 | NOT STARTED | [ ] |
| API-34 | GET | `/api/orders/{orderNumber}/receipt` | M4 | NOT STARTED | [ ] |
| API-35 | POST | `/api/orders/{orderNumber}/cancel` | M4 | NOT STARTED | [ ] |
| API-36 | GET | `/api/wishlist` | M3 | NOT STARTED | [ ] |
| API-37 | POST | `/api/wishlist` | M3 | NOT STARTED | [ ] |
| API-38 | DELETE | `/api/wishlist/{product}` | M3 | NOT STARTED | [ ] |
| API-39 | GET | `/api/addresses` | M1 | TESTED | [x] |
| API-40 | POST | `/api/addresses` | M1 | TESTED | [x] |
| API-41 | PUT | `/api/addresses/{address}` | M1 | TESTED | [x] |
| API-42 | DELETE | `/api/addresses/{address}` | M1 | TESTED | [x] |
| API-43 | POST | `/api/addresses/{address}/default` | M1 | TESTED | [x] |
| API-44 | POST | `/api/reviews` | M6 | NOT STARTED | [ ] |
| API-45 | PUT | `/api/reviews/{review}` | M6 | NOT STARTED | [ ] |
| API-46 | GET | `/api/account/dashboard` | M1 | TESTED | [x] |
| API-47 | PUT | `/api/account/profile` | M1 | TESTED | [x] |
| API-48 | POST | `/api/account/avatar` | M1 | NOT STARTED | [ ] |
| API-49 | POST | `/api/account/password` | M1 | TESTED | [x] |
| API-50 | GET | `/api/account/reviews` | M6 | NOT STARTED | [ ] |
| API-51 | GET | `/api/account/notifications` | M5 | TESTED | [x] |
| API-52 | POST | `/api/account/notifications/{notification}/read` | M5 | TESTED | [x] |
| API-53 | GET | `/api/admin/dashboard/overview` | M6 | NOT STARTED | [ ] |
| API-54 | GET | `/api/admin/shops` | M1 | NOT STARTED | [ ] |
| API-55 | POST | `/api/admin/shops` | M1 | NOT STARTED | [ ] |
| API-56 | GET | `/api/admin/shops/{shop}` | M1 | NOT STARTED | [ ] |
| API-57 | PUT | `/api/admin/shops/{shop}` | M1 | NOT STARTED | [ ] |
| API-58 | PATCH | `/api/admin/shops/{shop}/status` | M1 | NOT STARTED | [ ] |
| API-59 | DELETE | `/api/admin/shops/{shop}` | M1 | NOT STARTED | [ ] |
| API-60 | GET | `/api/admin/products` | M2 | NOT STARTED | [ ] |
| API-61 | POST | `/api/admin/products` | M2 | NOT STARTED | [ ] |
| API-62 | GET | `/api/admin/products/{product}` | M2 | NOT STARTED | [ ] |
| API-63 | PUT | `/api/admin/products/{product}` | M2 | NOT STARTED | [ ] |
| API-64 | DELETE | `/api/admin/products/{product}` | M2 | NOT STARTED | [ ] |
| API-65 | PATCH | `/api/admin/products` | M2 | NOT STARTED | [ ] |
| API-66 | POST | `/api/admin/products/{product}/restore` | M2 | NOT STARTED | [ ] |
| API-67 | POST | `/api/admin/products/{product}/images` | M2 | NOT STARTED | [ ] |
| API-68 | DELETE | `/api/admin/products/{product}/images/{image}` | M2 | NOT STARTED | [ ] |
| API-69 | POST | `/api/admin/uploads/image` | M6 | NOT STARTED | [ ] |
| API-70 | GET | `/api/admin/categories` | M2 | NOT STARTED | [ ] |
| API-71 | POST | `/api/admin/categories` | M2 | NOT STARTED | [ ] |
| API-72 | PUT | `/api/admin/categories/{category}` | M2 | NOT STARTED | [ ] |
| API-73 | DELETE | `/api/admin/categories/{category}` | M2 | NOT STARTED | [ ] |
| API-74 | POST | `/api/admin/categories/{category}/image` | M2 | NOT STARTED | [ ] |
| API-75 | GET | `/api/admin/brands` | M2 | NOT STARTED | [ ] |
| API-76 | POST | `/api/admin/brands` | M2 | NOT STARTED | [ ] |
| API-77 | PUT | `/api/admin/brands/{brand}` | M2 | NOT STARTED | [ ] |
| API-78 | DELETE | `/api/admin/brands/{brand}` | M2 | NOT STARTED | [ ] |
| API-79 | POST | `/api/admin/brands/{brand}/logo` | M2 | NOT STARTED | [ ] |
| API-80 | GET | `/api/admin/shipping-methods` | M5 | NOT STARTED | [ ] |
| API-81 | POST | `/api/admin/shipping-methods` | M5 | NOT STARTED | [ ] |
| API-82 | PUT | `/api/admin/shipping-methods/{method}` | M5 | NOT STARTED | [ ] |
| API-83 | DELETE | `/api/admin/shipping-methods/{method}` | M5 | NOT STARTED | [ ] |
| API-84 | GET | `/api/admin/orders` | M4 | NOT STARTED | [ ] |
| API-85 | GET | `/api/admin/orders/{order}` | M4 | NOT STARTED | [ ] |
| API-86 | GET | `/api/admin/orders/{order}/receipt` | M4 | NOT STARTED | [ ] |
| API-87 | PUT | `/api/admin/orders/{order}/transition` | M4 | NOT STARTED | [ ] |
| API-88 | GET | `/api/admin/inventory` | M5 | NOT STARTED | [ ] |
| API-89 | GET | `/api/admin/inventory/{inventory}/transactions` | M5 | NOT STARTED | [ ] |
| API-90 | GET | `/api/admin/coupons` | M4 | NOT STARTED | [ ] |
| API-91 | POST | `/api/admin/coupons` | M4 | NOT STARTED | [ ] |
| API-92 | PUT | `/api/admin/coupons/{coupon}` | M4 | NOT STARTED | [ ] |
| API-93 | DELETE | `/api/admin/coupons/{coupon}` | M4 | NOT STARTED | [ ] |
| API-94 | GET | `/api/admin/reviews` | M6 | NOT STARTED | [ ] |
| API-95 | POST | `/api/admin/reviews/{review}/approve` | M6 | NOT STARTED | [ ] |
| API-96 | POST | `/api/admin/reviews/{review}/reject` | M6 | NOT STARTED | [ ] |
| API-97 | DELETE | `/api/admin/reviews/{review}` | M6 | NOT STARTED | [ ] |
| API-98 | GET | `/api/admin/settings` | M1 | NOT STARTED | [ ] |
| API-99 | PUT | `/api/admin/settings` | M1 | NOT STARTED | [ ] |
| API-100 | GET | `/api/admin/customers` | M1 | NOT STARTED | [ ] |
| API-101 | GET | `/api/admin/customers/{user}` | M1 | NOT STARTED | [ ] |
| API-102 | GET | `/api/admin/reports/summary` | M6 | NOT STARTED | [ ] |
| API-103 | GET | `/api/admin/reports/orders.csv` | M6 | NOT STARTED | [ ] |
| API-104 | GET | `/api/admin/reports/orders.pdf` | M6 | NOT STARTED | [ ] |
| API-105 | GET | `/api/admin/reports/products.csv` | M6 | NOT STARTED | [ ] |
| API-106 | GET | `/api/admin/reports/payments.csv` | M6 | NOT STARTED | [ ] |
| API-107 | GET | `/api/admin/payments` | M4 | NOT STARTED | [ ] |
| API-108 | GET | `/api/admin/payments/{payment}` | M4 | NOT STARTED | [ ] |
| API-109 | GET | `/api/admin/shipments` | M5 | NOT STARTED | [ ] |
| API-110 | GET | `/api/admin/shipments/{shipment}` | M5 | NOT STARTED | [ ] |
| API-111 | PUT | `/api/admin/shipments/{shipment}` | M5 | NOT STARTED | [ ] |
| API-112 | GET | `/api/admin/notifications` | M5 | NOT STARTED | [ ] |
| API-113 | GET | `/api/admin/notifications/unread-count` | M5 | NOT STARTED | [ ] |
| API-114 | POST | `/api/admin/notifications/{notification}/read` | M5 | NOT STARTED | [ ] |
| API-115 | DELETE | `/api/admin/notifications/{notification}` | M5 | NOT STARTED | [ ] |
| API-124 | POST | `/api/catalog/variants/resolve` | M2 | TESTED | [x] |
| API-125 | POST | `/api/admin/products` | M2 | TESTED | [x] |
| API-126 | PUT | `/api/admin/products/{product}` | M2 | TESTED | [x] |
| API-127 | PATCH | `/api/admin/products` (bulk status patch) | M2 | TESTED | [x] |

Variants are intentionally managed through `POST`/`PUT /api/admin/products[/{product}]` (`syncVariants`) rather than dedicated `/variants` routes, so API-116 is satisfied by the routes above.

Endpoints that are planned but not present in `backend/routes/api.php` and must be added by their owner:

| ID | Method | Endpoint | Owner | Priority | Status | Depends On |
| -- | ------ | -------- | ----- | -------- | ------ | ---------- |
| API-116 | POST/PUT/DELETE | `/api/admin/products/{product}/variants` (variant CRUD) | M2 | CRITICAL | SUPERSEDED - use `PUT /api/admin/products/{product}` | PVA-03 |
| API-117 | POST/PUT/DELETE | `/api/admin/attributes`, `/api/admin/attributes/{attribute}/values` | M2 | HIGH | NOT STARTED | PVA-01 |
| API-118 | POST | `/api/admin/inventory/{inventory}/adjust` | M5 | CRITICAL | NOT STARTED | INV-04 |
| API-119 | POST | `/api/admin/shipments` (create shipment for an order) | M5 | CRITICAL | NOT STARTED | SHP-04 |
| API-120 | POST | `/api/checkout/{orderNumber}/payment` (payment submission) | M4 | CRITICAL | NOT STARTED | PAY-01 |
| API-121 | POST | `/api/webhooks/payment/{provider}` (idempotent callback) | M4 | HIGH | NOT STARTED | PAY-06 |
| API-122 | DELETE | `/api/reviews/{review}` | M6 | MEDIUM | NOT STARTED | REV-04 |
| API-123 | GET | `/api/account/wishlist/count` | M3 | LOW | NOT STARTED | WSH-06 |

---

# 21. FRONTEND/API INTEGRATION

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| INTG-01 | [ ] | Centralized Axios client with base URL from `VITE_API_URL` | M6 | CRITICAL | NOT STARTED | SET-10 |
| INTG-02 | [ ] | Request interceptor attaching the auth token | M6 | CRITICAL | NOT STARTED | INTG-01, AUTH-02 |
| INTG-03 | [ ] | Response interceptor normalizing errors | M6 | HIGH | NOT STARTED | INTG-01 |
| INTG-04 | [ ] | 401 handling: clear session and redirect to login with return URL | M6 | CRITICAL | NOT STARTED | INTG-02 |
| INTG-05 | [ ] | 403 handling: show permission-denied message, no redirect loop | M6 | HIGH | NOT STARTED | INTG-03 |
| INTG-06 | [ ] | 422 handling: map field errors into form state | M6 | CRITICAL | NOT STARTED | INTG-03 |
| INTG-07 | [ ] | 404 handling: not-found page | M6 | MEDIUM | NOT STARTED | INTG-03 |
| INTG-08 | [ ] | 500 handling: generic error view with support hint, no stack trace | M6 | HIGH | NOT STARTED | INTG-03 |
| INTG-09 | [ ] | Network error and timeout handling | M6 | MEDIUM | NOT STARTED | INTG-03 |
| INTG-10 | [ ] | Typed API service modules per module (auth, catalog, cart, orders) | M6 | HIGH | NOT STARTED | INTG-01 |
| INTG-11 | [ ] | TypeScript response interfaces for every endpoint used | M6 | HIGH | NOT STARTED | INTG-10 |
| INTG-12 | [ ] | Pinia stores for auth, cart, wishlist, notifications with persistence | M6 | HIGH | NOT STARTED | INTG-10 |
| INTG-13 | [ ] | Global loading indicator and per-component loading states | M6 | MEDIUM | NOT STARTED | INTG-03 |
| INTG-14 | [ ] | Form submission helper with pending state and error display | M6 | HIGH | NOT STARTED | INTG-06 |
| INTG-15 | [ ] | Optimistic updates only where rollback is implemented | M6 | MEDIUM | NOT STARTED | INTG-12 |
| INTG-16 | [ ] | Pagination component reused by every list page | M6 | MEDIUM | NOT STARTED | INTG-10 |
| INTG-17 | [ ] | No duplicate requests on rapid filter changes (debounce/cancel) | M6 | MEDIUM | NOT STARTED | CUI-02 |
| INTG-18 | [ ] | Environment files for dev/prod documented | M6 | MEDIUM | NOT STARTED | SET-06 |

---

# 22. SECURITY CHECKLIST

| ID | Done | Check | Owner | Priority | Status | Depends On |
| -- | ---- | ----- | ----- | -------- | ------ | ---------- |
| SEC-01 | [ ] | Passwords hashed (bcrypt/argon2), never stored or logged in plain text | M1 | CRITICAL | NOT STARTED | AUTH-05 |
| SEC-02 | [ ] | Sanctum token auth on every protected endpoint | M1 | CRITICAL | NOT STARTED | AUTH-09 |
| SEC-03 | [ ] | `admin` middleware on every admin route group | M1 | CRITICAL | NOT STARTED | AUTH-11 |
| SEC-04 | [ ] | Resource ownership checks on all user-scoped data (orders, addresses, reviews, notifications, cart) | M1 | CRITICAL | NOT STARTED | AUTH-12 |
| SEC-05 | [ ] | Role checks tested with a customer token against every admin endpoint | M6 | CRITICAL | NOT STARTED | SEC-03 |
| SEC-06 | [ ] | Shop scoping prevents cross-shop data access | M1 | HIGH | NOT STARTED | INT-03 |
| SEC-07 | [x] | Mass assignment protection: explicit `$fillable`/guarded on every model | M1 | CRITICAL | TESTING | DB-03 |
| SEC-08 | [ ] | All input validated server-side; client validation is convenience only | M1 | CRITICAL | NOT STARTED | APIC-03 |
| SEC-09 | [ ] | SQL injection protection: no raw concatenated queries in application code | M1 | CRITICAL | NOT STARTED | SEC-08 |
| SEC-10 | [ ] | XSS protection: output encoding, no `v-html` on untrusted content, sanitized rich text | M1 | CRITICAL | NOT STARTED | SEC-08 |
| SEC-11 | [ ] | CSRF protection on state-changing cookie-auth routes | M1 | HIGH | NOT STARTED | AUTH-09 |
| SEC-12 | [ ] | File upload validation: mime/extension allowlist, size limit, random stored filename, no execution | M2 | CRITICAL | NOT STARTED | PRD-05 |
| SEC-13 | [ ] | Upload directory not web-executable; served through a controller | M2 | HIGH | NOT STARTED | SEC-12 |
| SEC-14 | [ ] | Rate limiting on login, register, password reset, coupon validate | M1 | MEDIUM | IN PROGRESS | AUTH-15 |
| SEC-15 | [ ] | No secrets committed: `.env`, tokens, webhook keys, bot tokens excluded from Git | M1 | CRITICAL | NOT STARTED | SET-12 |
| SEC-16 | [ ] | `APP_DEBUG=false` and debug routes disabled outside local | M1 | CRITICAL | NOT STARTED | DEP-03 |
| SEC-17 | [ ] | No sensitive data in logs (tokens, passwords, card data, personal data) | M1 | HIGH | NOT STARTED | SEC-01 |
| SEC-18 | [ ] | Payment data handling complies with Section 12 (no PAN/CVV storage) | M4 | CRITICAL | NOT STARTED | PAY-14 |
| SEC-19 | [x] | IDOR probe: try reading another user's order, address, review, notification by id | M6 | CRITICAL | TESTING | SEC-04 |
| SEC-20 | [ ] | Dependency audit for known vulnerabilities (composer audit, npm audit) | M6 | MEDIUM | NOT STARTED | - |
| SEC-21 | [ ] | Security headers configured (CSP, X-Frame-Options, X-Content-Type-Options) | M1 | MEDIUM | NOT STARTED | DEP-03 |
| SEC-22 | [ ] | Findings logged in Section 26 with priority and owner | M1 | HIGH | NOT STARTED | SEC-19 |

Evidence for this section, from the current pass:

- SEC-07 is verified for `User`: `role` and `email_verified_at` are no longer mass assignable, `POST /api/auth/register` and `PUT /api/account/profile` ignore a self-assigned role, and `POST /api/addresses` ignores a supplied `user_id`. Every other model still needs the same sweep.
- SEC-14 covers the auth surface: register 5/min, login 10/min, forgot-password 3/min, reset-password 5/min, verification resend 3/min. The coupon validate throttle is still missing. The five public catalog routes were throttled at 120/min in this pass, which is not listed in the SEC-14 scope and should be added to it.
- SEC-12 now has partial product-media protection: attached image paths must already belong to the product or exist under `images/products/` on the public disk, so a crafted path cannot attach or delete an unrelated file. Mime/extension allowlists, size limits and non-guessable stored filenames are still open, so SEC-12 stays NOT STARTED.
- SEC-19 has automated 404 probes for addresses and notifications. Order, review and wishlist scoping relies on existing tests and still needs the manual probe from M6.

---

# 23. TESTING AGENDA

## 23.1 Unit Testing

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| TST-01 | [ ] | Model tests: relationships, casts, scopes | Owner of model | HIGH | NOT STARTED | DB-03 |
| TST-02 | [ ] | Service tests: totals calculation, discount engine, state machine | Owner of service | CRITICAL | NOT STARTED | Section 7, 13, 11 |
| TST-03 | [ ] | Coupon rule tests for each rejection branch | M4 | HIGH | NOT STARTED | CPN-18 |
| TST-04 | [ ] | Order state machine tests: all valid and invalid transitions | M4 | CRITICAL | NOT STARTED | ORD-15 |
| TST-05 | [ ] | Inventory concurrency and no-negative-stock tests | M5 | CRITICAL | NOT STARTED | INV-16 |
| TST-06 | [ ] | Totals service parity test (cart, checkout, order) | M3 | CRITICAL | NOT STARTED | CRT-18 |
| TST-07 | [ ] | Test suite runs green via a single documented command | M6 | HIGH | NOT STARTED | TST-01 |

## 23.2 API Testing

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| TSTA-01 | [x] | Authentication endpoints (register, login, logout, me, reset, roles) | M1 | CRITICAL | TESTED | AUTH-16 |
| TSTA-02 | [ ] | Catalog and product endpoints (list, filter, search, detail, variants) | M2 | CRITICAL | NOT STARTED | PRD-21 |
| TSTA-03 | [ ] | Cart endpoints (add, update, remove, totals, stock errors) | M3 | CRITICAL | NOT STARTED | CRT-18 |
| TSTA-04 | [ ] | Wishlist endpoints | M3 | MEDIUM | NOT STARTED | WSH-08 |
| TSTA-05 | [ ] | Checkout endpoints (begin, confirm, cancel, failure paths) | M3 | CRITICAL | NOT STARTED | CHK-15 |
| TSTA-06 | [ ] | Order endpoints (list, detail, transition, cancel) | M4 | CRITICAL | NOT STARTED | ORD-15 |
| TSTA-07 | [ ] | Payment endpoints and idempotency | M4 | CRITICAL | NOT STARTED | PAY-13 |
| TSTA-08 | [ ] | Coupon admin and validate endpoints | M4 | HIGH | NOT STARTED | CPN-18 |
| TSTA-09 | [ ] | Inventory and transactions endpoints | M5 | CRITICAL | NOT STARTED | INV-16 |
| TSTA-10 | [ ] | Shipping methods and shipments endpoints | M5 | HIGH | NOT STARTED | SHP-13 |
| TSTA-11 | [ ] | Notification endpoints and ownership | M5 | HIGH | NOT STARTED | NTF-12 |
| TSTA-12 | [ ] | Review endpoints including verified purchase gate | M6 | HIGH | NOT STARTED | REV-15 |
| TSTA-13 | [x] | Address and account endpoints (avatar and account reviews still untested) | M1 | HIGH | TESTING | AUTH-18 |
| TSTA-14 | [ ] | Admin endpoints authorization matrix (customer vs admin) | M6 | CRITICAL | NOT STARTED | SEC-05 |
| TSTA-15 | [ ] | Every API in Section 20 has at least one passing test | Owner of route | HIGH | NOT STARTED | Section 20 |

## 23.3 UI Testing

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| TSTU-01 | [ ] | Customer pages: navigation and rendering | M6 | HIGH | NOT STARTED | Section 18 |
| TSTU-02 | [ ] | Admin pages: navigation and rendering | M6 | HIGH | NOT STARTED | Section 19 |
| TSTU-03 | [ ] | Forms: validation messages, submit states, error mapping | M6 | HIGH | NOT STARTED | INTG-14 |
| TSTU-04 | [ ] | Responsive layout at 375px, 768px, 1440px | M6 | HIGH | NOT STARTED | CUIQ-04 |
| TSTU-05 | [ ] | Loading, error, and empty states on every page | M6 | HIGH | NOT STARTED | CUIQ-01 |
| TSTU-06 | [ ] | Cross-browser check (Chrome, Firefox, Edge) | M6 | MEDIUM | NOT STARTED | - |
| TSTU-07 | [ ] | No console errors during a full click-through | M6 | HIGH | NOT STARTED | CUIQ-10 |
| TSTU-08 | [ ] | Manual regression pass after each release build | M6 | HIGH | NOT STARTED | DEP-05 |

## 23.4 Integration Testing

| ID | Done | Task | Owner | Priority | Status | Depends On |
| -- | ---- | ---- | ----- | -------- | ------ | ---------- |
| TSTI-01 | [ ] | Complete customer flow (Section 24) | M6 | CRITICAL | NOT STARTED | TSTA-01..TSTA-13 |
| TSTI-02 | [ ] | Complete admin flow (Section 25) | M6 | CRITICAL | NOT STARTED | TSTA-02, TSTA-06, TSTA-09 |
| TSTI-03 | [ ] | Payment success path moves order, stock, notifications together | M4 | CRITICAL | NOT STARTED | PAY-13 |
| TSTI-04 | [ ] | Cancellation path restores stock, coupon usage, payment | M4 | CRITICAL | NOT STARTED | ORD-08 |
| TSTI-05 | [ ] | Multi-shop isolation: shop A admin cannot read or modify shop B data | M1 | HIGH | NOT STARTED | SEC-06 |
| TSTI-06 | [ ] | Fresh install path: `migrate:fresh --seed` then full smoke test | M6 | CRITICAL | NOT STARTED | DB-11 |
| TSTI-07 | [ ] | QA report published with findings and severity | M6 | HIGH | NOT STARTED | TSTI-01..TSTI-06 |

---

# 24. COMPLETE CUSTOMER TEST FLOW

Run on a freshly seeded database. Every row must pass before release.

| ID | Done | Step | Expected Result | Owner | Priority | Status |
| -- | ---- | ---- | --------------- | ----- | -------- | ------ |
| CF-01 | [ ] | Register a new customer | Account created, redirected to login, welcome notification stored | M6 | CRITICAL | NOT STARTED |
| CF-02 | [ ] | Log in | Token issued, redirected to home or intended page | M6 | CRITICAL | NOT STARTED |
| CF-03 | [ ] | Browse home page | Featured products and categories load with no error | M6 | HIGH | NOT STARTED |
| CF-04 | [ ] | Search for a product | Matching products listed, no results state handled | M6 | HIGH | NOT STARTED |
| CF-05 | [ ] | Apply filters (category, brand, price, in-stock) | Result set narrows correctly and is reflected in the URL | M6 | HIGH | NOT STARTED |
| CF-06 | [ ] | Sort results | Sort order changes as selected | M6 | MEDIUM | NOT STARTED |
| CF-07 | [ ] | Open product details | Gallery, price, variants, stock badge, description visible | M6 | CRITICAL | NOT STARTED |
| CF-08 | [ ] | Select a variant | Price and stock update, invalid combinations disabled | M6 | CRITICAL | NOT STARTED |
| CF-09 | [ ] | Attempt to add more than available stock | Rejected with a clear error, cart unchanged | M6 | CRITICAL | NOT STARTED |
| CF-10 | [ ] | Add item to cart | Cart badge and subtotal update | M6 | CRITICAL | NOT STARTED |
| CF-11 | [ ] | Update quantity in cart | Line total and order totals recalculate | M6 | CRITICAL | NOT STARTED |
| CF-12 | [ ] | Remove item and clear cart | Cart updates correctly in both cases | M6 | HIGH | NOT STARTED |
| CF-13 | [ ] | Add product to wishlist | Item appears in wishlist, duplicate add prevented | M6 | MEDIUM | NOT STARTED |
| CF-14 | [ ] | Apply valid coupon | Discount reflected in totals, usage recorded | M6 | HIGH | NOT STARTED |
| CF-15 | [ ] | Apply expired/over-limit/minimum-not-met coupon | Rejected with the correct reason, totals unchanged | M6 | HIGH | NOT STARTED |
| CF-16 | [ ] | Start checkout | Cart revalidated, address step shown | M6 | CRITICAL | NOT STARTED |
| CF-17 | [ ] | Select delivery address | Saved address selectable, new address can be added | M6 | CRITICAL | NOT STARTED |
| CF-18 | [ ] | Select shipping method | Fee and estimate applied once to totals | M6 | CRITICAL | NOT STARTED |
| CF-19 | [ ] | Review order summary | Subtotal, discount, shipping, total all correct | M6 | CRITICAL | NOT STARTED |
| CF-20 | [ ] | Submit payment (sandbox success) | Payment recorded, order confirmed, stock deducted once | M6 | CRITICAL | NOT STARTED |
| CF-21 | [ ] | Submit payment (sandbox failure) | Failure recorded, order not advanced, reservation released | M6 | CRITICAL | NOT STARTED |
| CF-22 | [ ] | View order confirmation | Order number, totals, delivery estimate shown | M6 | CRITICAL | NOT STARTED |
| CF-23 | [ ] | View order in history | Order listed with correct status | M6 | HIGH | NOT STARTED |
| CF-24 | [ ] | Track order | Status stepper matches backend status and history | M6 | HIGH | NOT STARTED |
| CF-25 | [ ] | Download receipt | Receipt opens/downloads with correct order data | M6 | MEDIUM | NOT STARTED |
| CF-26 | [ ] | Cancel order while pending | Order cancelled, notification sent, cart restorable | M6 | HIGH | NOT STARTED |
| CF-27 | [ ] | Receive notifications | Order, payment, and shipping notifications arrive and are marked read | M5 | HIGH | NOT STARTED |
| CF-28 | [ ] | Attempt review without a delivered order | Rejected with a clear message | M6 | HIGH | NOT STARTED |
| CF-29 | [ ] | Write review after delivery with image | Review submitted, appears as pending moderation | M6 | HIGH | NOT STARTED |
| CF-30 | [ ] | Manage profile, password, and addresses | Changes persist and require the current password to change it | M1 | HIGH | NOT STARTED |
| CF-31 | [ ] | Log out, then access a protected page | Redirected to login; back button does not reveal cached data | M1 | CRITICAL | NOT STARTED |

---

# 25. COMPLETE ADMIN TEST FLOW

| ID | Done | Step | Expected Result | Owner | Priority | Status |
| -- | ---- | ---- | --------------- | ----- | -------- | ------ |
| AF-01 | [ ] | Log in as admin | Admin dashboard loads, admin nav visible | M6 | CRITICAL | NOT STARTED |
| AF-02 | [ ] | Verify dashboard KPIs | Revenue, order count, customer count match the database | M6 | HIGH | NOT STARTED |
| AF-03 | [ ] | Create a category | Category appears in the tree and in the customer filter | M2 | HIGH | NOT STARTED |
| AF-04 | [ ] | Create a brand | Brand appears in the admin list and customer filter | M2 | HIGH | NOT STARTED |
| AF-05 | [ ] | Create a product | Draft product created with slug and SKU | M2 | CRITICAL | NOT STARTED |
| AF-06 | [ ] | Upload product images | Images stored, ordered, primary set, displayed on the detail page | M2 | HIGH | NOT STARTED |
| AF-07 | [ ] | Add variants with attributes | Variants created, combinations unique, SKUs unique per shop | M2 | CRITICAL | NOT STARTED |
| AF-08 | [ ] | Add stock to variants | Stock visible on the product detail page, ledger row written | M5 | CRITICAL | NOT STARTED |
| AF-09 | [ ] | Publish product | Product becomes visible in the public catalog | M2 | CRITICAL | NOT STARTED |
| AF-10 | [ ] | Adjust stock manually | Adjustment applied with reason, ledger history shows both entries | M5 | HIGH | NOT STARTED |
| AF-11 | [ ] | Attempt to set stock below zero | Rejected with an error, no ledger row written | M5 | CRITICAL | NOT STARTED |
| AF-12 | [ ] | View incoming order | Order detail shows items, snapshot prices, totals, customer, address | M4 | CRITICAL | NOT STARTED |
| AF-13 | [ ] | Confirm order | Status becomes confirmed, history recorded, customer notified | M4 | CRITICAL | NOT STARTED |
| AF-14 | [ ] | Attempt an invalid transition (pending -> shipped) | Rejected with 422, status unchanged, history unchanged | M4 | CRITICAL | NOT STARTED |
| AF-15 | [ ] | Process order | Status becomes processing, stock deducted exactly once | M4 | CRITICAL | NOT STARTED |
| AF-16 | [ ] | Create a shipment | Shipment created and linked to the order and method | M5 | CRITICAL | NOT STARTED |
| AF-17 | [ ] | Update shipment with tracking number | Status and tracking saved, customer notified, tracking timeline visible | M5 | HIGH | NOT STARTED |
| AF-18 | [ ] | Mark order delivered | Status delivered, customer notified, review now allowed | M5 | HIGH | NOT STARTED |
| AF-19 | [ ] | Cancel a paid order | Stock restored, coupon usage released, payment marked refunded | M4 | CRITICAL | NOT STARTED |
| AF-20 | [ ] | Create and activate a coupon | Coupon usable by a customer within all limits | M4 | HIGH | NOT STARTED |
| AF-21 | [ ] | Exceed coupon usage limit | Further use rejected with the correct reason | M4 | HIGH | NOT STARTED |
| AF-22 | [ ] | Moderate reviews | Approve and reject work, public list reflects the state | M6 | HIGH | NOT STARTED |
| AF-23 | [ ] | Manage customers | Search, view detail, no access to another admin's shop data | M1 | HIGH | NOT STARTED |
| AF-24 | [ ] | View admin notifications | Order, payment, shipping, low-stock notifications present | M5 | MEDIUM | NOT STARTED |
| AF-25 | [ ] | Generate reports | CSV and PDF download with correct filtered data | M6 | HIGH | NOT STARTED |
| AF-26 | [ ] | Update settings | Settings persist and affect the storefront where applicable | M1 | MEDIUM | NOT STARTED |
| AF-27 | [ ] | Log in as a customer and try every admin URL | Every attempt returns 403 or redirects; nothing is exposed | M6 | CRITICAL | NOT STARTED |

---

# 26. BUG TRACKER

Status values: `OPEN`, `IN PROGRESS`, `FIXED`, `TESTING`, `CLOSED`.
Priority values: `CRITICAL`, `HIGH`, `MEDIUM`, `LOW`.

Rules:

- One row per defect. Never combine two defects in one row.
- CRITICAL: blocks the demo, loses data, or is a security hole. Fix before release.
- HIGH: breaks a documented feature. Fix before release.
- MEDIUM: degrades usability or has a workaround. Fix if time allows.
- LOW: cosmetic or content issue. Fix if time allows.
- A bug is CLOSED only after M6 verifies the fix.

| Bug ID | Feature | Description | Priority | Assigned | Status | Fix |
| ------ | ------- | ----------- | -------- | -------- | ------ | --- |
| BUG-001 | Product images | Admin product write accepted any string in `images`, so a crafted path such as `../../.env` could be attached to a product and then removed through the media endpoint | CRITICAL | M2 | TESTING | Path must already belong to the product or exist under `images/products/` on the public disk; `AdminOpsTest::test_store_rejects_image_paths_that_were_never_uploaded`, `AdminOpsTest::test_store_rejects_a_path_traversal_attempt` |
| BUG-002 | Product variants | Two variants of one product could not exchange SKUs, because the per-shop unique index rejected the intermediate write | HIGH | M2 | TESTING | Two-phase SKU parking in `AdminProductController::syncVariants`; `AdminOpsTest::test_variants_can_swap_skus_within_the_same_product` |
| BUG-003 | Products | Creating a product whose slug was already taken hit the unique index instead of de-duplicating | HIGH | M2 | TESTING | `uniqueSlug()` with `Str::slug`; `AdminOpsTest::test_store_deduplicates_a_slug_that_is_already_taken` |
| BUG-004 | Catalog visibility | The public catalog, the shop product list, reviews and the wishlist returned inactive products and products owned by a suspended shop | HIGH | M2 | TESTING | `Product::scopeActive()` applied in `CatalogService`, `ShopController`, `ReviewController` and `WishlistController`; `CatalogTest::test_hides_products_from_non_active_shops`, `CatalogTest::test_lists_only_active_products_with_meta`, `ReviewTest::test_reviews_of_an_unpublished_product_are_not_readable` |
| BUG-005 | Cart | The cart accepted a variant whose product was unpublished, whose variant was inactive, or whose shop was not active | HIGH | M3 | TESTING | Active checks in `CartService::add()`; `CartTest::test_cannot_add_a_variant_of_an_unpublished_product`, `CartTest::test_cannot_add_an_inactive_variant` |
| BUG-006 | Product variants | A variant with no price override serialised as `0` instead of the parent product price | HIGH | M2 | TESTING | Parent-price fallback in `ProductDetailResource` and in the variant resolver; `CatalogTest::test_variant_price_falls_back_to_the_product_price_on_both_endpoints` |
| BUG-007 | Catalog filtering | Attribute filters used `LIKE`, so `colors=Red` also matched `Crimson Red` | MEDIUM | M2 | TESTING | Exact value match, OR within one attribute and AND across attributes; `CatalogTest::test_attribute_filter_matches_exact_values_only`, `CatalogTest::test_color_and_size_filters_use_and_across_attributes` |
| BUG-008 | Catalog filtering | Facet counts included inactive products and products of suspended shops, so the sidebar disagreed with the listing | MEDIUM | M2 | TESTING | Counts computed through the active scope; `CatalogTest::test_facet_counts_ignore_inactive_products` |
| BUG-009 | Products | The bulk status patch reported a count that did not match the rows actually written | MEDIUM | M2 | TESTING | Count taken from the authorised query; `AdminOpsTest::test_bulk_status_reports_the_number_of_rows_actually_updated` |
| BUG-010 | Product images | Uploading a new cover image left the previous image flagged as cover as well | MEDIUM | M2 | TESTING | Previous cover demoted on promotion; `AdminMediaTest::test_uploading_a_new_cover_demotes_the_previous_cover`, `AdminMediaTest::test_uploading_a_non_cover_keeps_the_existing_cover` |
| BUG-011 | Product variants | Two variants could share one attribute-value combination, which makes variant resolution ambiguous | MEDIUM | M2 | TESTING | `assertUniqueCombinations()` on create and update; `AdminOpsTest::test_variants_cannot_share_the_same_attribute_combination` |
| BUG-012 | Catalog API | Public catalog routes had no rate limiting and `perPage` was unbounded, so a client could dump the whole table | MEDIUM | M2 | TESTING | `throttle:120,1` on every catalog route and a `perPage` cap of 48; `CatalogTest::test_rejects_unbounded_or_invalid_pagination`, `CatalogTest::test_caps_page_size_instead_of_dumping_the_whole_table` |
| BUG-013 | Products | An unsafe slug (spaces, uppercase, path-like characters) was stored verbatim instead of being normalised | MEDIUM | M2 | TESTING | `Str::slug` normalisation plus a generated fallback for non-latin names; `AdminOpsTest::test_store_normalises_an_unsafe_slug`, `AdminOpsTest::test_store_falls_back_to_a_generated_slug_for_non_latin_names` |
| BUG-014 | Products | Over-long `name`, `sku` or `short_description` reached MySQL and failed as a database error instead of a `422` | MEDIUM | M2 | TESTING | Column-width bounds in `AdminProductRequest`; `AdminOpsTest::test_store_rejects_an_over_long_short_description` |
| BUG-015 | Catalog search | Catalog search ignored the long `description` field required by PRD-16, and treated `%` and `_` as wildcards | MEDIUM | M2 | TESTING | `LOWER(description)` search with escaped wildcards; `CatalogTest::test_search_matches_long_description_and_treats_wildcards_literally` |
| BUG-016 | Products | A product SKU that already existed on another product was accepted until the database rejected the insert | MEDIUM | M2 | TESTING | Product-level SKU uniqueness rule; `AdminOpsTest::test_store_rejects_a_product_sku_that_already_exists`, `AdminOpsTest::test_update_rejects_duplicate_sku_against_other_products_but_allows_own` |

All rows are `TESTING` rather than `CLOSED`: the fixes and their regression tests are in place, but M6 has not yet verified them.

### Bug Summary Counters

| Priority | Open | In Progress | Fixed | Testing | Closed |
| -------- | ---- | ----------- | ----- | ------- | ------ |
| CRITICAL | 0 | 0 | 0 | 1 | 0 |
| HIGH | 0 | 0 | 0 | 5 | 0 |
| MEDIUM | 0 | 0 | 0 | 10 | 0 |
| LOW | 0 | 0 | 0 | 0 | 0 |

---

# 27. DAILY TEAM AGENDA

Copy this block into the daily log at the end of each day. Keep the log in `docs/project-management/daily/`.

```markdown
## Daily Update - YYYY-MM-DD

**Sprint:** Week X
**Blockers for the team:** None

### M1 - Team Leader / Backend Lead (feature/auth-users)
- Yesterday completed:
- Today planned:
- Task IDs touched:
- Blocker:
- Help needed from:

### M2 - Product Developer (feature/products)
- Yesterday completed:
- Today planned:
- Task IDs touched:
- Blocker:
- Help needed from:

### M3 - Shopping Developer (feature/cart-checkout)
- Yesterday completed:
- Today planned:
- Task IDs touched:
- Blocker:
- Help needed from:

### M4 - Order and Payment Developer (feature/orders-payment)
- Yesterday completed:
- Today planned:
- Task IDs touched:
- Blocker:
- Help needed from:

### M5 - Inventory and Shipping Developer (feature/inventory-shipping)
- Yesterday completed:
- Today planned:
- Task IDs touched:
- Blocker:
- Help needed from:

### M6 - Frontend and QA Developer (feature/customer-ui)
- Yesterday completed:
- Today planned:
- Task IDs touched:
- Blocker:
- Help needed from:

### Commits merged today
-

### PRs opened / reviewed
-

### Bugs filed / closed
-

### Agenda updates made to this file
-
```

---

# 28. WEEKLY TEAM AGENDA

```markdown
# Week X

**Dates:** YYYY-MM-DD to YYYY-MM-DD
**Phase:**
**Overall status:** NOT STARTED / IN PROGRESS / BLOCKED / REVIEW / TESTING / DONE

## Completed
- [ ] Task ID - description (Owner)

## In Progress
- [ ] Task ID - description (Owner, progress percent, ETA)

## Blocked
- Task ID - blocked by what, needed from whom, since when

## Bugs
- Open: CRITICAL x, HIGH x, MEDIUM x, LOW x
- Closed this week: BUG-xxx, BUG-xxx

## Decisions
- Decision, rationale, who decided, date

## API changes
- New endpoint / changed contract / removed endpoint, communicated to all members

## Database changes
- New migration, affected tables, required seed updates

## Next Week
- [ ] Task ID - description (Owner)

## Risks
- Risk, impact, mitigation, owner
```

---

# 29. MEETING AGENDA

```markdown
# Meeting - YYYY-MM-DD

## Meeting Information

- Date:
- Time:
- Location / call link:
- Participants:
- Meeting leader:
- Absent:

## Agenda

- [ ] Progress review per member (5 min each)
- [ ] Blockers
- [ ] Bugs triage and priority assignment
- [ ] API contract changes
- [ ] Database migration changes
- [ ] Task reassignment
- [ ] Deadline review
- [ ] Definition of Done violations
- [ ] Tasks for next week

## Notes

-

## Decisions

- Decision - rationale - decided by - date

## Action Items

| Action | Owner | Deadline | Status |
| ------ | ----- | -------- | ------ |
| | | | NOT STARTED |
| | | | NOT STARTED |

## Next Meeting

- Date:
- Focus:
```

---

# 30. GIT CHECKLIST

## 30.1 Per Task

| ID | Done | Check | Owner | Priority | Status |
| -- | ---- | ----- | ----- | -------- | ------ |
| GIT-01 | [ ] | Feature branch created from an up-to-date `dev` | Task owner | HIGH | NOT STARTED |
| GIT-02 | [ ] | Branch name matches the assignment in Section 2 | Task owner | MEDIUM | NOT STARTED |
| GIT-03 | [ ] | Only files belonging to the task are committed | Task owner | HIGH | NOT STARTED |
| GIT-04 | [ ] | Commit message follows the convention below | Task owner | HIGH | NOT STARTED |
| GIT-05 | [ ] | No `.env`, credentials, or generated files committed | Task owner | CRITICAL | NOT STARTED |
| GIT-06 | [ ] | Code tested locally before pushing | Task owner | CRITICAL | NOT STARTED |
| GIT-07 | [ ] | Pull Request opened with description, task IDs, and test evidence | Task owner | HIGH | NOT STARTED |
| GIT-08 | [ ] | PR reviewed by at least one other member (M1 reviews all merges) | Reviewer | CRITICAL | NOT STARTED |
| GIT-09 | [ ] | Review comments resolved and re-reviewed | Task owner | HIGH | NOT STARTED |
| GIT-10 | [ ] | Merge conflicts resolved on the branch, never in the merge commit | Task owner | HIGH | NOT STARTED |
| GIT-11 | [ ] | All tests pass on the merged result in `dev` | M6 | CRITICAL | NOT STARTED |
| GIT-12 | [ ] | Branch merged into `dev` and deleted afterwards | M1 | HIGH | NOT STARTED |
| GIT-13 | [ ] | Agenda updated with the new statuses | M1 | MEDIUM | NOT STARTED |

## 30.2 Commit Message Convention

`type(scope): short imperative summary`

| Type | Use For |
| ---- | ------- |
| `feat` | New functionality |
| `fix` | Bug fix |
| `test` | Tests added or updated |
| `docs` | Documentation only |
| `refactor` | No behaviour change |
| `style` | Formatting only |
| `perf` | Performance improvement |
| `chore` | Build, dependency, config |
| `build` | Build system changes |
| `ci` | CI configuration |
| `revert` | Reverts a previous commit |

Examples:

```text
feat(products): add product CRUD with image upload
feat(orders): enforce order status transition state machine
fix(cart): correct quantity validation against available stock
fix(inventory): prevent negative stock under concurrent checkout
test(orders): add order API tests for invalid transitions
docs: update project agenda
chore(deps): upgrade laravel framework
```

Rules:

- One logical change per commit.
- Subject line under 72 characters, imperative mood, no trailing period.
- Body explains why, not what, when the reason is not obvious.
- Reference task IDs in the body: `Task: PRD-01, PRD-05`.
- Never commit directly to `main`. `main` receives only reviewed merges from `dev`.

---

# 31. DEFINITION OF DONE

A task is DONE only when every box below is checked.

| ID | Done | Criterion | Verified By |
| -- | ---- | --------- | ----------- |
| DOD-01 | [ ] | Requirement implemented as described in the task | Owner |
| DOD-02 | [ ] | Database migration included if schema changed (no manual edits) | Owner |
| DOD-03 | [ ] | Backend logic in a service or model, not in the controller | Reviewer |
| DOD-04 | [ ] | API endpoint documented and returning the standard response shape | Owner |
| DOD-05 | [ ] | Frontend integrated and reachable by a user (if applicable) | Owner |
| DOD-06 | [ ] | Server-side validation in place with correct 422 responses | Reviewer |
| DOD-07 | [ ] | Authorization in place (role and ownership) | Reviewer |
| DOD-08 | [ ] | Error handling for failure paths, no silent failures | Reviewer |
| DOD-09 | [ ] | Automated tests added for success and failure paths | Owner |
| DOD-10 | [ ] | Tests pass and code reviewed by at least one other member | Reviewer |
| DOD-11 | [ ] | Pull Request approved and merged into `dev` | M1 |
| DOD-12 | [ ] | Documentation updated (API reference, changelog, agenda) | Owner |
| DOD-13 | [ ] | Task ID ticked `[x]` in this document with the status set to DONE | Owner |

A task is not DONE if it is "almost done", "works on my machine", or "will be tested later".

---

# 32. FINAL PROJECT AUDIT

Fill this table only after the full test pass. A feature is complete only when every applicable column is checked.

| Feature | Database | Backend | API | Frontend | Testing | Status |
| ------- | -------- | ------- | --- | -------- | ------- | ------ |
| Authentication and users | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Addresses | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Roles and authorization | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Shops (multi-shop scoping) | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Categories | [ ] | [ ] | [x] | [ ] | [x] | IN PROGRESS |
| Brands | [ ] | [ ] | [x] | [ ] | [x] | IN PROGRESS |
| Products | [x] | [x] | [x] | [ ] | [x] | IN PROGRESS |
| Product images | [x] | [x] | [x] | [ ] | [x] | IN PROGRESS |
| Attributes and values | [x] | [ ] | [ ] | [ ] | [ ] | IN PROGRESS |
| Product variants | [x] | [x] | [x] | [ ] | [x] | IN PROGRESS |
| Catalog browsing (filter, sort, search) | [x] | [x] | [x] | [ ] | [x] | IN PROGRESS |
| Cart | [x] | [x] | [x] | [ ] | [ ] | IN PROGRESS |
| Wishlist | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Checkout | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Orders | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Order status state machine | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Payments | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Coupons | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Inventory | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Inventory transactions | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Shipping methods | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Shipments and tracking | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Notifications | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Reviews and review images | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Customer website (all pages) | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Admin dashboard (all screens) | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Reports (CSV, PDF) | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Settings | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Security controls | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Documentation | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |
| Deployment | [ ] | [ ] | [ ] | [ ] | [ ] | NOT STARTED |

### Audit Summary

Only the product-area rows above were filled in this pass; every other row still needs its own audit.
The Frontend column stays unticked throughout because the Section 18.2 page gate has not been run.

| Metric | Value |
| ------ | ----- |
| Features audited | 8 of 31 (products area only) |
| Features complete | 0 |
| Open CRITICAL bugs | 0 |
| Open HIGH bugs | 0 |
| APIs implemented (Section 20) | 119 |
| APIs tested | 34 |
| Test pass rate | 100% (230 tests / 1030 assertions, `php artisan test`) |
| Audit date | 2026-10-03 |
| Audited by | M2 product pass (agent-assisted) - needs a second reviewer |

---

# 33. FINAL RELEASE CHECKLIST

| ID | Done | Item | Owner | Priority | Status |
| -- | ---- | ---- | ----- | -------- | ------ |
| REL-01 | [ ] | All features in Section 32 marked complete | M1 | CRITICAL | NOT STARTED |
| REL-02 | [ ] | All APIs in Section 20 implemented and tested | Owners | CRITICAL | NOT STARTED |
| REL-03 | [ ] | All customer pages pass the Section 18.2 gate | M6 | CRITICAL | NOT STARTED |
| REL-04 | [ ] | All admin screens pass the Section 19.1 rules | M6 | CRITICAL | NOT STARTED |
| REL-05 | [x] | Authentication tested (register, login, reset, logout, roles) | M1 | CRITICAL | TESTED |
| REL-06 | [ ] | Authorization tested (customer cannot reach admin or other users' data) | M1 | CRITICAL | NOT STARTED |
| REL-07 | [ ] | Database verified: `migrate:fresh --seed` on a clean instance | M2 | CRITICAL | NOT STARTED |
| REL-08 | [ ] | Section 22 security checklist verified by a second member | M1 | CRITICAL | NOT STARTED |
| REL-09 | [ ] | Responsive UI verified on mobile, tablet, desktop | M6 | HIGH | NOT STARTED |
| REL-10 | [ ] | All CRITICAL bugs closed | M1 | CRITICAL | NOT STARTED |
| REL-11 | [ ] | All HIGH bugs closed or explicitly accepted by the team | M1 | HIGH | NOT STARTED |
| REL-12 | [ ] | Docker build tested from a clean clone | M1 | CRITICAL | NOT STARTED |
| REL-13 | [ ] | Production build tested (`npm run build`, optimized assets, no dev server dependency) | M6 | HIGH | NOT STARTED |
| REL-14 | [ ] | `APP_ENV=production`, `APP_DEBUG=false`, no debug output in responses | M1 | CRITICAL | NOT STARTED |
| REL-15 | [ ] | Database backups configured and a restore rehearsed | M1 | HIGH | NOT STARTED |
| REL-16 | [ ] | Health check and error monitoring working | M1 | MEDIUM | NOT STARTED |
| REL-17 | [ ] | README updated with install, run, and deploy instructions | M1 | HIGH | NOT STARTED |
| REL-18 | [ ] | API reference documentation matches the implemented routes | Owners | HIGH | NOT STARTED |
| REL-19 | [ ] | Architecture document and ERD final | M1 | HIGH | NOT STARTED |
| REL-20 | [ ] | User manual written (customer and admin) | M6 | HIGH | NOT STARTED |
| REL-21 | [ ] | Technical documentation written (setup, design decisions, trade-offs) | M1 | HIGH | NOT STARTED |
| REL-22 | [ ] | Demo data seeded and verified (multiple shops, products, orders) | M2 | HIGH | NOT STARTED |
| REL-23 | [ ] | Test accounts prepared (admin, customer) with credentials shared securely | M1 | HIGH | NOT STARTED |
| REL-24 | [ ] | Presentation slides finalized | M1 | HIGH | NOT STARTED |
| REL-25 | [ ] | Demo script written and rehearsed twice end to end | M1 | CRITICAL | NOT STARTED |
| REL-26 | [ ] | Each member can present their own module without notes | M1 | HIGH | NOT STARTED |
| REL-27 | [ ] | Final deployment to the presentation/demo environment completed | M1 | CRITICAL | NOT STARTED |
| REL-28 | [ ] | `main` merged from `dev`, tagged, and released | M1 | HIGH | NOT STARTED |
| REL-29 | [ ] | Rollback plan documented and understood by the team | M1 | MEDIUM | NOT STARTED |
| REL-30 | [ ] | Post-release monitoring plan agreed | M1 | LOW | NOT STARTED |

---

# 34. IMPORTANT INSTRUCTION

This file is a real working project management document, not a description of one.

## 34.1 Rules for Using This Document

- Every task carries a Task ID, description, owner, priority, and status. Do not add a task without all five fields.
- Use checkboxes so the team updates this file directly.
- Do not mark tasks as DONE automatically. Use `[ ]` for unfinished tasks.
- Use `[x]` only when the team confirms the task is completed, tested, merged, and documented (Section 31).
- When a task moves to BLOCKED, add the reason and the date to the daily log and mention it in the next meeting.
- When a task ID changes owner, update the Owner column in the same commit that transfers the work.
- When a new defect appears, add it to Section 26 the same day and update the counters.
- When an API is added or changed, update Section 20 and `docs/API-REFERENCE.md` in the same commit.
- When a migration is added, update Section 5 in the same commit.

## 34.2 Rules for Content

- No vague tasks. "Improve the cart" is not a task. "Cart update returns 422 when requested quantity exceeds available stock (CRT-13)" is.
- No duplicate task IDs. IDs are permanent; if a task is cancelled, mark it cancelled instead of deleting it.
- Priorities reflect business impact: data loss, security, and demo-blocking work is CRITICAL.
- Dependencies must be real. If task B cannot start before task A, list A in the Depends On column.
- Acceptance criteria belong to phases (Section 3) and to the module checklists. They state what "finished" means, not what to do.

## 34.3 Coverage Requirement

This agenda covers the entire project lifecycle:

Planning -> Setup -> Database -> Backend -> API -> Frontend -> Integration -> Testing -> Bug Fixing -> Security -> Deployment -> Documentation -> Presentation.

No phase may be skipped or reported as complete while any task inside it is unticked. If the team cannot complete a task before the deadline, change its status to BLOCKED and escalate it to M1 rather than silently dropping it.