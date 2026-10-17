# QA Report — E-KHMER E-Commerce System

## Camera barcode scanning verification — 2026-10-17

The frontend-only camera extension passed the full **40 desktop/mobile Chromium checks**, the Vue TypeScript/production build and a production npm audit (**0 vulnerabilities**). New checks decode real EAN-8 bar pixels (`00001236`) from a simulated camera MediaStream with the actual ZXing reader, verify selected-variant isolation and explicit-save behavior, and cover permission denial/retry, missing cameras, insecure contexts, cancel-before-permission and component teardown. The scanner dialog was visually inspected at desktop and Pixel 7 sizes. Camera frames remain local; English/Khmer prompts, HTTPS guidance and manual/USB/Bluetooth fallback are present. ZXing dependencies are lazy-loaded and pinned to Node-22-compatible releases. Physical phone cameras, Safari and dedicated scanner hardware still need acceptance testing; Chromium mobile emulation is not device certification. No backend changes or migration were needed for this camera-only extension; the backend results below belong to the preceding barcode/pricing verification.

## Barcode and purchase pricing verification — 2026-10-17

The barcode/pricing extension passed **380 backend tests / 1,911 assertions**, **26 desktop/mobile browser checks**, and the Vue TypeScript/production build. The nullable variant barcode/cost columns and shop-scoped barcode indexes were migrated locally. Coverage includes preserved leading zeros, independent variant values, partial edits, optional-field clearing, shop-scoped product/stock search, private purchase costs, scanner Enter handling and existing-record editing. Scheduler tests now freeze their clock so reservation assertions do not depend on wall-clock jumps. USB/Bluetooth scanner support is keyboard-input based. Camera decoding was added and verified separately above; no physical-scanner hardware certification is implied.

## Commerce implementation verification — 2026-10-17

The older audit below is retained as historical evidence, not the current release status.

- Final full Laravel suite: **373 tests / 1,837 assertions passed** after security updates. The focused new-feature regression also passed **28 tests / 221 assertions** before the dependency upgrade.
- Desktop + Pixel 7 Chromium: **20 checks passed**, covering shop discovery/search, settings/Bakong recipient save, product options, photo upload/cover/removal, camera capture, support persistence, per-seller payment progress, refund-ledger proof submission, scoped delivery, expired-checkout retries and customer/admin routing. UI APIs are mocked; financial and authorization behavior is tested separately on Laravel.
- Vue TypeScript check and production build passed. The final multi-stage production image built successfully, including PHP dependencies without development packages; Nginx configuration validation passed.
- Compatible security updates applied: Laravel **13.37.0**, CommonMark **2.10.3**, Symfony HttpFoundation **7.4.21**, and Laravel Prompts **0.3.26**. The final backend production dependency audit returned no advisories. The npm production dependency audit also reported no vulnerabilities; seven npm development-only findings remain pending a tested Tailwind major upgrade. CI now checks both production dependency audits.
- All four new migrations applied locally. Production Compose configuration validated. Worker/scheduler/database/cache heartbeat check passed after moving app/worker/scheduler dependencies to a shared Docker vendor volume and restarting the services. The host's ignored vendor directory is not used at runtime.
- Database/media backup archive integrity passed and the SQL dump restored to a separate disposable MySQL server: **53 migration records, 14 orders, 13 products**. The live database was not overwritten. Off-server replication and full disaster-recovery acceptance remain operator launch tasks.
- Real Bakong transfers, SMTP delivery and remote OCI/DNS/TLS activation have **not** been performed; no live-payment or remote deployment success is implied. Production currency remains USD.

See [commerce completion](./COMMERCE-COMPLETION.md) and [Oracle deployment](./ORACLE-CLOUD.md) for operating rules and remaining activation requirements.

- **Date:** 2026-09-24
- **Environment:** Docker Compose (`app`, `scheduler`, `nginx`, `mysql`, `redis`, `phpmyadmin`, `frontend`)
- **Backend:** Laravel 13 (PHP 8.3, Sanctum)
- **Frontend:** Vue 3 + TypeScript + Vite 8 + Tailwind CSS
- **Method:** Docker + backend automated suite (105 tests) + live API test-runner probes + full source review of controllers/services/requests/resources/migrations/models + frontend production build.

---

## A. QA SUMMARY

| Area | Status |
| --- | --- |
| Docker & environment | PASS (frontend container was stopped; restarted OK) |
| Backend automated tests | PASS — 105 passed / 419 assertions, 0 failures |
| Frontend build | PASS — `vue-tsc -b && vite build` clean |
| Auth (register/login/verify/reset) | PASS (except broken seeded admin hash, see SEC-02) |
| Authorization (401/403, admin RBAC) | PASS — customer token correctly blocked from `/admin` (403) |
| Public catalog, brands, categories, facets, shipping | PASS |
| Cart / totals / stock capping | PASS (two minor edge bugs, see MED-06, LOW-06) |
| Wishlist / addresses / reviews / notifications | PASS |
| Checkout begin/confirm/cancel + reservation lock | PASS flow mechanics; **state-correctness bugs found** (B-CRIT-01, HIGH-02, MED-01) |
| Admin dashboard / inventory / reports (CSV/PDF) | PASS (+ 2 hard-crash bugs: HIGH-04, MED-09) |
| Security posture | Multiple findings — see Section F (incl. payment-gateway gap, rate limiting, debug leakage) |
| Performance / N+1 | No critical N+1 found; eager-loading is consistent. Unbounded pagination vector found (MED-05) |
| Concurrency | Row-locks used for inventory, but not for `orders` state — race found (MED-11) |

**Overall: the system is in good functional shape and feature-complete for a demo/SDK, but is NOT production-safe yet.** The automated suite is green, yet live testing exposed real money/stock consistency defects (cancelled orders leak inventory + corrupt payment state, coupon usage is never reclaimed) and several security gaps.

---

## B. CRITICAL BUGS

### B-CRIT-01 — Cancel of a PAID order corrupts payment state, loses stock, and writes no refund
- Severity: **CRITICAL** — Priority: P1
- Module: Orders / Payments / Inventory
- File: `backend/app/Services/OrderService.php:161-188`
- Root cause: `cancelOwn()` permits cancellation from `confirmed`/`processing` but only releases stock for `pending`. It forcibly sets `payment_status = unpaid` while the `payments` row stays `completed` and writes no refund `PaymentTransaction`.
- Steps to reproduce (verified live, order `SV-2026-000065`): customer adds variant → `POST /checkout` → `POST /checkout/{order}/confirm` (paid, stock deducted) → `POST /orders/{order}/cancel`.
- Expected: refund recorded, inventory restored, payment consistent.
- Actual: `orders.payment_status=unpaid`, `payments.status=completed`, inventory `quantity` permanently reduced, `sold_count` still +2, no refund transaction.
- Recommended fix: `cancelOwn()` should (a) only allow cancel before payment, or (b) for paid orders invoke a refund path (`payment_status=refunded`, `payments.status=refunded`, refund `payment_transaction`) **and** restore quantity in `inventories` (`quantity += qty`) for all cancelled orders that were deducted.

---

## C. HIGH PRIORITY BUGS

### HIGH-01 — Admin order transition to `cancelled` leaks inventory reservations permanently
- Severity: **HIGH** — Priority: P1
- Module: Orders / Inventory
- File: `backend/app/Services/OrderService.php:54-63` (`pending → cancelled` allowed), `68-121` (`transition()` performs no inventory handling)
- Root cause: `transition()` cancels a pending order but never calls `InventoryService::releaseMany()` (unlike `CheckoutService::release()` and `cancelOwn()`'s pending branch).
- Steps to reproduce (verified live, order id 32 / `SV-2026-000280`): customer begins checkout so `inventories.reserved_quantity=2` → admin `PUT /admin/orders/32/transition {"status":"cancelled"}`.
- Expected: `reserved_quantity` returns to 0.
- Actual: order shows `cancelled`, `reserved_quantity` **stays 2** — stock lost with no recovery.
- Recommended fix: in `transition()`, when `$to === cancelled` and prior status is `pending`, call `releaseMany()` on order items.

### HIGH-02 — Payment confirm is fully client-trusted (no gateway verification) — goods can be marked PAID without payment
- Severity: **HIGH** — Priority: P1 (blocking for production)
- Module: Checkout / Payments
- File: `backend/app/Services/CheckoutService.php:155-208`; route `routes/api.php` checkout group has no auth (by design for guests)
- Root cause: `confirm()` accepts any client-supplied `transaction_id` (or fabricates `PAY-...`) and unconditionally flips `payment=completed`, `order.payment_status=paid`, deducting stock. No gateway call, signature check, or amount verification. Totals ARE computed server-side (good), but payment legitimacy is not verified.
- Steps to reproduce: register customer → add to cart → `POST /checkout` → `POST /checkout/{order}/confirm` with any string → order becomes "paid".
- Expected: payment confirmation only after a real gateway success/authorization.
- Actual: an unauthenticated caller can obtain paid orders and deducted stock for free.
- Recommended fix: integrate a gateway (webhook + signed callbacks); treat the current behavior as an explicit "sandbox/payment-mocked" mode gated by config, never enabled in production. At minimum, add a server-side secret token/signature for the confirm call and mock-mode flag.

### HIGH-03 — Seeded admin account has plaintext password → login endpoint throws 500 + stack trace
- Severity: **HIGH** — Priority: P1 (env hygiene)
- Module: Auth / Data
- File: live DB row (`users.id=1`, `email=admin@shopverse.dev`, `password='admin12345'` plaintext); `AuthController::login` → `Auth::guard('web')->validate()` (line 73) → `BcryptHasher` throws `RuntimeException`.
- Root cause: database contains a pre-cast plaintext hash from an earlier seed/migration. Any login attempt returns HTTP 500 (not 401) with full exception trace (see SEC-01).
- Steps to reproduce: `POST /auth/login {"email":"admin@shopverse.dev","password":"admin12345"}`.
- Expected: 401 Invalid credentials (or successful login after re-hashing).
- Actual: HTTP 500 `"This password does not use the Bcrypt algorithm."` + stack trace.
- Recommended fix: re-hash account 1 (`UPDATE users SET password = <bcrypt> WHERE id=1`) or re-run `UserSeeder`; add a graceful handler in `AuthController::login` for non-bcrypt hashes (return 401) instead of letting the exception propagate.

### HIGH-04 — Admin product create: negative variant quantity → unhandled 500 with raw SQL leak; negative variant prices accepted
- Severity: **HIGH** — Priority: P1
- Module: Catalog (admin) / Inventory
- File: `backend/app/Http/Requests/AdminProductRequest.php:37-40` (no `min:0` on `variants.*.price`, `variants.*.compare_at_price`, `variants.*.quantity`); `backend/app/Http/Controllers/Api/Admin/AdminProductController.php:164` (`createVariants()` doesn't clamp quantity)
- Root cause: `quantity` is `unsignedInteger` (migration `..._create_inventories_table.php:17`); a negative value triggers MySQL out-of-range. No `min:0` validation rule.
- Steps to reproduce (verified live): `POST /admin/products` with `variants: [{quantity:-5, price:10}]` → 500 `SQLSTATE[22003] ... Out of range value for column 'quantity'` including the full SQL and DB host in the response.
- Expected: 422 validation error.
- Actual: HTTP 500 crash + SQL/db info disclosure.
- Recommended fix: add `min:0` (or `integer|min:0`) to `variants.*.price`, `variants.*.compare_at_price`, `variants.*.quantity`; clamp in `createVariants()`.

### HIGH-05 — Coupon usage is never reclaimed on cancelled/abandoned/expired orders
- Severity: **HIGH** — Priority: P1
- Module: Coupons / Orders
- File: `backend/app/Services/CouponService.php:74-84`; `backend/app/Services/CheckoutService.php:144-146` (usage applied at `begin()`, fired even if order abandoned); cancellation/expiry paths (`CheckoutService.php:213-233, 240-256`; `OrderService::cancelOwn`) never decrement.
- Root cause: coupon usage is reserved at order *creation*, not at payment success.
- Steps to reproduce (verified live): use `WELCOME10` (used_count 3823) → begin checkout → cancel order → `used_count` still 3824, `coupon_usages` row still present.
- Expected: unused coupon restored.
- Actual: abandoned checkouts permanently burn coupon capacity; customers denied valid discounts. Guests (user_id null) also bypass `per_user_limit` in `CouponService.php:48-55`.
- Recommended fix: move usage recording to `confirm()` and add rollback on cancel/expiry (delete `coupon_usages` row + decrement `used_count`, atomically).

---

## D. MEDIUM PRIORITY BUGS

### MED-01 — Guest cart is never cleared after successful confirm
- File: `backend/app/Services/CheckoutService.php:200-204` (clear is inside `if ($order->user_id !== null)`)
- Verified live: guest order `SV-2026-000057` confirmed but `GET /cart` still returns the purchased item.
- Fix: also clear the guest cart by `session_id` at confirm (resolve from `Payment.provider_data.session_id`).

### MED-02 — `OrderService::transition` / `confirm` / cron `release` do not row-lock `orders` — confirm-vs-expiry race can both write
- File: `CheckoutService.php:157, 240-256`; `OrderService.php:70`; inventory rows ARE locked with `lockForUpdate` but `orders` are not.
- Risk: a 15-min expiry cron `release()` racing `confirm()` can leave a cancelled order whose stock was already deducted, or refunded/deducted twice.
- Fix: `lockForUpdate()` the `orders` row inside the `DB::transaction` in `confirm()`, `release()`, `transition()`, `cancelOwn()`.

### MED-03 — Duplicate product slug on create → unhandled 500 (unique constraint)
- File: `backend/app/Http/Controllers/Api/Admin/AdminProductController.php:75`; slug auto-generated in `AdminProductRequest::prepareForValidation()` (`:49-56`).
- Fix: catch `UniqueConstraintViolationException`, or pre-check + append suffix, or validate `unique:products,slug`.

### MED-04 — Soft-deleted products remain purchasable and visible in wishlist
- File: `backend/app/Services/CartService.php:50` (checks only variant `is_active`); `backend/app/Http/Controllers/Api/WishlistController.php:19` (`is_active` stays true after soft delete). `Product` & `ProductVariant` use `SoftDeletes`.
- Fix: also filter `whereHas('product', fn($q) => $q->withoutGlobalScopes()->whereNull('deleted_at'))`, or hard-delete variants/inventory on product delete; skip soft-deleted products in wishlist.

### MED-05 — Unbounded pagination/limits: memory/CPU DoS on public endpoints
- File: `backend/app/Services/CatalogService.php:98` (`perPage` no cap); `backend/app/Http/Controllers/Api/CatalogController.php:50` (`featured limit` no cap).
- Fix: `perPage = min((int)($filters['perPage'] ?? 12), 100)` and cap `limit`.

### MED-06 — Cart line totals show $0 for null-priced variants while order charges product price (UI/API mismatch)
- File: `backend/app/Http/Resources/CartResource.php:17-21` vs `CartService::totals()` / `CheckoutService.php:113` (fallback to product price).
- Fix: make `CartResource` fall back to `variant.product.price` like the services.

### MED-07 — Approved-then-edited or rejected reviews leave stale product `rating_avg`/`rating_count`
- File: `backend/app/Services/ReviewService.php:96-102` (`reject()` never recalculates), `54-67` (`update()` edits rating without recalculation).
- Fix: recalc on reject and on update of an approved review; optionally revert status to pending on edit.

### MED-08 — Low-stock alerts don't re-arm when stock is restored via `release()`
- File: `backend/app/Services/InventoryService.php:84-102` (`release()` skips `checkLowStock()`).
- Fix: call `checkLowStock()` in `release()` too.

### MED-09 — Report export crashes on invalid `from`/`to` dates (500)
- File: `backend/app/Http/Controllers/Api/Admin/AdminReportController.php:19-25`.
- Fix: validate/parse defensively and return 422.

### MED-10 — No rate limiting on authentication endpoints (brute-force / reset spam)
- File: `routes/api.php` — no `throttle` middleware anywhere.
- Fix: apply `throttle::5,1` (or similar) on `auth/login`, `auth/register`, `auth/forgot-password`, `auth/reset-password`, and `checkout` routes.

### MED-11 — Coupon usage-limit check is non-atomic (read-then-increment race)
- File: `backend/app/Services/CouponService.php:36-39` + `76`.
- Fix: `lockForUpdate()` the coupon row (or atomic guard) before `used_count >= usage_limit` check.

### MED-12 — COD orders can never be reconciled to `paid`; COD revenue never reaches dashboard/reports
- File: `CheckoutService.php:173-190` (COD leaves payment pending); `backend/app/Http/Controllers/Api/Admin/AdminOrderController.php` (transition never touches payment); `DashboardService.php:15` (counts only `payment_status=paid`).
- Fix: add an admin "record cash receipt / mark paid" action; include `payment_method=cod` delivered orders in revenue.

### MED-13 — Unbounded pending orders per user can reserve all stock (denial-of-purchase)
- File: `backend/app/Http/Controllers/Api/CheckoutController.php:32-73` — repeated `begin()` re-reserves the same cart.
- Fix: cap open pending orders per user/session (e.g., release the previous pending order for the same cart on new begin) + throttle.

---

## E. LOW PRIORITY BUGS

### LOW-01 — Order-list resource uses a nonexistent aggregate attribute
- `backend/app/Http/Resources/OrderListResource.php:14` — `order_items_count` vs the real `items_count` from `withCount('items')`. Latent (masked by eager-load). Fix: use `items_count`.

### LOW-02 — `role` is mass-assignable on `User`
- `backend/app/Models/User.php:14`. Not exploited today (all controllers whitelist), but a `$request->all()` refactor would become privilege escalation. Fix: guard `role` from fillable (set explicitly only in admin flows).

### LOW-03 — `CartService::update()` can persist a zero-quantity cart item
- `backend/app/Services/CartService.php:98-99` — when `available() === 0`, `min($quantity, 0)` writes `quantity = 0`. Fix: delete the item (or throw) instead.

### LOW-04 — Category parent can be self-referential / cyclic
- `backend/app/Http/Requests/AdminCategoryRequest.php:18` + `AdminCategoryController` update — no guard `parent_id !== id` or descendant check. Fix: validate against self/descendants.

### LOW-05 — Percentage coupons accept `value > 100`; duplicate codes allowed
- `backend/app/Http/Requests/AdminCouponRequest.php:20`. A 200% coupon silently means free. Fix: `value` max 100 when `type=percentage`; enforce unique code (upper-case).

### LOW-06 — Inventory stock labels overlap (`low` includes `out`)
- `backend/app/Http/Controllers/Api/Admin/AdminInventoryController.php:30-32`. Zero-stock appears in both buckets. Fix: `low = available > 0 && available <= threshold`.

### LOW-07 — Order-number generator can collide under concurrency → 500
- `backend/app/Services/OrderNumberGenerator.php:14-19`; `CheckoutService.php:258-269` third attempt skips uniqueness check. Fix: retry loop with exception catch on unique violation.

### LOW-08 — Missing indexes on hot columns
- `orders.payment_status` (used by `DashboardService`), `addresses.user_id`. Add indexes.

### LOW-09 — `DashboardController::overview` accepts unbounded/negative `days`
- `backend/app/Http/Controllers/Api/Admin/DashboardController.php:17`. Fix: clamp `1..365`.

### LOW-10 — `tests/Unit` suite referenced by `phpunit.xml` but directory is empty
- Backend QA: no unit tests exist. Add unit coverage or remove the suite reference.

### LOW-11 — Docs drift: FRONTEND/docs declare Bootstrap 5, project uses Tailwind CSS
- `frontend/package.json` (no bootstrap), `frontend/src/style.css` (`@tailwind`). Update `AGENTS.md`, `docs/FRONTEND.md`, `docs/ARCHITECTURE.md`.

### LOW-12 — `frontend/.env` is absent; proxy target set via docker env
- Functional (dev), but `npm run build --mode production` would target `/api` (relative) instead of the configured URL. Consider adding `.env` guidance or using `VITE_API_URL` in CI.

---

## F. SECURITY FINDINGS

### SEC-01 — Debug stack traces + SQL leak in API responses (**HIGH**) — verified live
- `backend/.env`: `APP_DEBUG=true`. Error responses include `exception`, `file` (`/var/www/...`), full `trace`, and — for DB errors — the raw SQL and DB host. Verified twice live (login RuntimeException, negative-qty insert).
- Fix: disable `APP_DEBUG` in all non-local environments; the API error handler should always return generic JSON (`message` only) in production.

### SEC-02 — Plaintext admin password in database (**HIGH**) — verified live
- `users.id=1` stores `admin12345` in plaintext (also triggers the 500 above). Re-hash and add a seeder assertion (`Hash::needsRehash` guard).

### SEC-03 — No rate limiting / no brute-force protection (**MEDIUM**)
- `POST /auth/login`, `/auth/register`, `/auth/forgot-password` are fully open. See MED-10.

### SEC-04 — Payment confirmation trusted without gateway (**HIGH** for production) — see HIGH-02.

### SEC-05 — CORS `Access-Control-Allow-Origin: *` on an authenticated API (**MEDIUM**)
- No `config/cors.php`; Laravel default `*` applies (`HandleCors`). Works because dev uses same-origin proxy + bearer tokens, but restrict origins in production.

### SEC-06 — Guest cart / guest order gate is a client-controlled `X-Session-Id` (md5) (**LOW/MEDIUM**)
- `CartController` binds carts to a client-supplied header; `CheckoutController::resolveOwnedOrder` uses `md5(session_id)` stored in `Payment.provider_data`. If a session id leaks, other clients can read/modify. Standard guest-cart trade-off; consider signed/random opaque tokens with expiry.

### SEC-07 — No CSRF concern for API (Sanctum) but no throttling on checkout reservations (**LOW**)

### Not found (good):
- No SQL injection — all `whereRaw` uses are **bound parameters** (mask applied to the value, never interpolated into SQL). Verified.
- No XSS surface introduced by backend (frontend uses framework escaping).
- No IDOR found in customer scoping (`OrderService::findByNumber`, `AddressController`, `ReviewService::update`, wishlist all scope to the user).
- No secrets committed; `.env` files ignored (`backend/.gitignore`).
- Uploads (MediaUploadService) restrict contexts, random filenames, no path traversal.
- No client-supplied order totals/payment amounts (all recomputed server-side).
- No passwords/tokens/`provider_data` serialized by Resources.

---

## G. PERFORMANCE FINDINGS

- **N+1:** none found in main flows (`catalog`, `cart`, `orders`, `wishlist`, admin lists all eager-load correctly).
- **Unbounded pagination** on `/catalog/products` (`perPage`) and `/catalog/featured` (`limit`) — public DoS vector (MED-05).
- **Missing indexes:** `orders.payment_status`, `addresses.user_id` (LOW-08).
- **Dashboard `days` unbounded** (LOW-09).
- No Redis caching of catalog/facets (not a defect, but notable for scale).
- Automated suite runtime ~135s; DB queries outside SQLite are untested at scale (no load test run — 100/1k/10k product tests were not executed because that would mutate the demo DB; recommend a staging dataset).

---

## H. TESTS PASSED

1. `docker compose config` — valid.
2. Containers start; services reachable (API 200, frontend 200, phpMyAdmin, redis PONG, mysql query OK).
3. **Laravel suite: 105 passed / 419 assertions** (Auth, Catalog, Cart, Checkout, Coupon, Review, Notification, Admin, AdminInventory, AdminMedia, AccountAndSettings, Console/Schedule).
4. Frontend **production build** (`vue-tsc -b && vite build`) — clean, 0 errors.
5. Live API spot-checks: public catalog/facets/detail/categories/brands/shipping-methods, register/login/401/403, cart add, checkout begin/confirm, notifications, admin dashboard, review verified-purchase logic (via tests), i18n key parity, `Orders` customer scoping.

## I. TESTS FAILED

- None in the automated suite.
- (Live behavioral reproductions above are not "failed tests" — they document confirmed defects.)

## J. RECOMMENDED FIX ORDER

1. **CRITICAL-01 / HIGH-05** — Cancellation/refund correctness + coupon usage reclaim (state-machine + inventory + payment consistency).
2. **HIGH-02** — Harden/flag the payment confirm (gateway or explicit mock-only mode).
3. **HIGH-03 / SEC-01 / SEC-02** — Fix seeded hash, disable debug, ensure generic production error responses.
4. **HIGH-04** — Validate/clamp variant price & quantity.
5. **HIGH-01** — Release reservations on admin cancel.
6. **MED-01…MED-13** (security/consistency first: MED-10 rate limiting, MED-11 atomic coupon, MED-02 row locks, MED-04 soft-delete purchase).
7. Code-quality lows (LOW-01…LOW-12).

## K. FILES THAT NEED CHANGES

Backend (business logic):
- `backend/app/Services/OrderService.php` — refund/restock on cancel; release reservations on admin cancel; row-lock orders.
- `backend/app/Services/CheckoutService.php` — guest cart clear; coupon usage at confirm + rollback; row-lock orders; mock-payment gating.
- `backend/app/Services/CouponService.php` — atomic usage-limit check; rollback helper.
- `backend/app/Services/InventoryService.php` — `checkLowStock()` on release; clamp adjust below reserved.
- `backend/app/Services/ReviewService.php` — recalc on reject/update.
- `backend/app/Http/Requests/AdminProductRequest.php` — `min:0` on variant price/compare/quantity; unique slug handling in `AdminProductController.php`.
- `backend/app/Http/Controllers/Api/Admin/AdminReportController.php` — date validation.
- `backend/app/Http/Controllers/Api/Admin/AdminInventoryController.php` — low/out bucket logic.
- `backend/app/Http/Controllers/Api/Admin/DashboardController.php` — clamp `days`.
- `backend/app/Http/Controllers/Api/CatalogController.php` + `CatalogService.php` — cap limits.
- `backend/app/Http/Resources/CartResource.php` + `OrderListResource.php` — price fallback / aggregate field.
- `backend/bootstrap/app.php` or `routes/api.php` — throttle middleware.
- `backend/.env` — `APP_DEBUG=false` (non-local).
- Migrations — indexes (`orders.payment_status`, `addresses.user_id`); optionally `coupon_usages` cleanup behavior.
- Data fix — re-hash `users.id=1` password.

Frontend:
- No functional frontend bugs found in build; docs update only (`docs/FRONTEND.md`, `AGENTS.md`) for Bootstrap→Tailwind.

## L. FINAL QA CHECKLIST

- [x] Docker compose valid, containers healthy (frontend needed restart)
- [x] Laravel tests green (105/105)
- [x] Frontend type-checks and builds
- [x] Auth flow + RBAC verified (401/403 correct)
- [x] Verified-purchase review rule enforced (tests)
- [x] Inventory reservation/deduct/release mechanics work (negative case: admin-cancel leak)
- [x] Order lifecycle state machine enforced server-side (tests + live)
- [x] Coupon engine validates expiry/limit/min-order (usage-reclaim defect found)
- [x] Customer data isolation (orders/addresses/reviews) — no IDOR found
- [x] Security: no SQLi / no secrets committed / uploads safe / no mass-assignment exploit
- [x] Security gaps documented: debug leakage, rate limiting, CORS `*`, mock payment
- [ ] Load/performance test at 1k/10k products — NOT RUN (requires staging DB)
- [ ] Browser-level (Playwright) UI/console sweep — NOT RUN (no browser automation in env)

---

**Notable environment hygiene items:**
1. `ecommerce_frontend` container was `Exited` — always `docker compose up -d` after boot or use `restart: unless-stopped` on the frontend service too.
2. Rebuild trusted credentials: re-seed admin with a bcrypt hash (`UserSeeder` now does, but the live DB row predates the `hashed` cast).

## M. FIX STATUS (branch `fix/qa-hotfixes`, 2026-09-24)

The CRITICAL + HIGH findings were fixed in this branch. 10 regression tests were added in `backend/tests/Feature/Api/QaHotfixTest.php`; the full suite is green (**115 passed / 478 assertions**).

| Finding | Status | Fix |
| --- | --- | --- |
| B-CRIT-01 (cancel paid order: no refund/restock, corrupted payment state) | **FIXED** | `OrderService::cancelOwn()` now calls `revertFulfilment()` (restocks deducted qty, reverts sold_count), `markPaymentRefunded()` (refund tx + `payment_status=refunded`) and `coupon->releaseUsage()`. Removed the corrupting `payment_status = unpaid` write. |
| HIGH-01 (admin transition → cancelled leaks reservations) | **FIXED** | `OrderService::transition()` now reverts fulfilment on every `cancelled`/`refunded` transition — releases reservations for pending, restocks for confirmed+, marks payment refunded when money was taken. |
| HIGH-02 (client-trusted mock payment) | **FIXED (gated)** | New `config/ecommerce.php` `payment_mode` (`PAYMENT_MODE`, sandbox default; safe-by-default `production` on prod). `CheckoutService::confirm()` rejects online-payment confirm with 422 whenever mode ≠ sandbox; gate is checked **before** any stock deduction. |
| HIGH-03 / SEC-02 (plaintext admin hash → 500 login) | **FIXED** | `AuthController::login()` now catches non-bcrypt-hash errors and returns 401 (invalid credentials). Live DB re-hashed: `admin@shopverse.dev` / `password` (restored to the value documented by the original seeder, commit `c2ebbf4`). |
| HIGH-04 (negative variant qty/price → 500 + SQL leak) | **FIXED** | `AdminProductRequest` adds `min:0` to `variants.*.price`, `variants.*.compare_at_price`, `variants.*.quantity` (422 instead of 500); `AdminProductController::createVariants()` clamps defensively. |
| HIGH-05 (coupon usage never reclaimed) | **FIXED** | New `CouponService::releaseUsage()` (row-locked decrement + usage-row delete). Called on customer cancel, admin cancel/refund, and reservation expiry (`CheckoutService::release()` / `expireStaleReservations()`). |
| SEC-01 (debug stack/SQL leaked in API responses) | **FIXED (production guard)** | `bootstrap/app.php` adds a production-only exception renderer masking details for API/JSON requests; dev behavior unchanged. Also document `APP_DEBUG=false` for production. |
| Concurrency hardening (affects B-CRIT-01 safety) | **ADDED** | `lockForUpdate()` on the `orders` row in `transition()`, `cancelOwn()`, `confirm()`, `release()` to remove confirm-vs-release races. |

**Live verification performed:** `admin@shopverse.dev` login → HTTP 200 (token, role admin); admin product create with `quantity:-5` → HTTP 422 clean message (was 500 + SQL).

Remaining MEDIUM/LOW findings from Sections D/E/F are deferred (not part of this fix scope).
