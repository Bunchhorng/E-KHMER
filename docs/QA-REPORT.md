# QA Report — E-KHMER E-Commerce System

## Super-admin oversight update — 2026-10-20

Verified the working changes based on `22aee07`: the overview now prioritizes live pending reviews, payment exceptions, returns/support counts and shop approvals. Detailed merchandising analytics moved to Reports; duplicate navigation and the inactive search were removed. Shop membership uses one navigation entry with role filters. Stock/shipment/shipping screens require deliberate opt-in to existing admin correction controls. Settings and API validation now match USD checkout; commission is labeled as reference metadata.

- Full Laravel suite: **388 tests / 2,652 assertions passed** (106.66 seconds). New regressions cover unresolved work outside the sales range, partial/refund/failed payment filtering, private payload exclusion, unsupported currency rejection and paid report analytics/date validation.
- Frontend TypeScript check and production build: **passed**. The changed PHP files passed Pint after import ordering was fixed.
- **All 14 new desktop/mobile admin checks passed**, including Khmer, role navigation, exception filtering, stock corrections and report relocation. The wider 86-check Chromium run recorded **85 passes and one camera-test timeout during a Vite reload**. That exact desktop camera case then passed separately against unchanged source (**1/1**, 3.1 seconds). This was not a clean single-run 86/86 result.
- Desktop and Pixel 7 previews were visually reviewed. UI APIs/media remain mocked; no new real-bank, cloud or physical-device acceptance is implied. No dependency or schema changes were required.

## Previous project review — 2026-10-19

- **Reviewed:** 2026-10-19 (Asia/Bangkok).
- **Source baseline:** `f950a779c0517472ac62321beda890db0639dfc5` — seller stock editing, committed 2026-10-18.
- **Scope:** Current source, existing regression coverage, Docker verification and outstanding findings. This replaces the mixed September audit and October addenda previously in this file.
- **Evidence:** Fresh results, source-review conclusions and earlier reported results are distinguished below. Source inspection alone does not imply a live reproduction.

## 1. Current project

| Area | Current implementation |
| --- | --- |
| Backend | Laravel **13.37.0** in `composer.lock`, PHP **8.3.33** in the checked container, Eloquent, API Resources and Sanctum bearer tokens with configurable expiry. JWT refresh/access rotation is not implemented. |
| Frontend | Vue 3, TypeScript, Vite **8.2.2**, Pinia, Vue Router, Axios, Tailwind CSS **3.4.19**, English/Khmer and theme switching. Axios reads `VITE_API_URL`, defaulting to same-origin `/api`. Bootstrap is not installed. |
| Customer | Account/auth, addresses, catalog/filtering, public shops, cart, multi-shop checkout, orders/tracking/receipts, wishlist, verified reviews, returns and support. |
| Admin and seller | Super-admin administration plus shop owner/manager access through membership; products/options/photos, inventory ledger, fulfillment, shop settings/delivery, payment/refund records, support and reports. |
| Payments | Direct-to-seller Bakong/KHQR, server-side verification, separate shop allocations and USD checkout. Legacy demo online methods self-confirm only in sandbox. COD confirmation leaves payment unpaid. |
| Infrastructure | MySQL 8, Redis, Nginx, app, worker, scheduler, frontend and phpMyAdmin in development Compose; optional browser-test and backup/restore profiles. Production Compose supplies HTTPS hosting and operational configuration. |

Local frontend: `http://localhost:5174`; API: `http://localhost:8000/api`. Browser containers use port `5173` inside the frontend network namespace.

Related documentation: [commerce operating guide](./COMMERCE-COMPLETION.md), [API reference](./API-REFERENCE.md), [development guide](./DEVELOPMENT.md), [Oracle deployment](./ORACLE-CLOUD.md).

## 2. Current verification

All PHP, Composer, npm and browser commands run inside the supplied Docker containers.

| Check | Result on 2026-10-19 | Scope and limits |
| --- | --- | --- |
| `docker compose config --quiet` | **PASS** | Development Compose configuration validation. |
| `docker compose exec -T app php artisan test --compact` | **PASS — 385 tests / 2,615 assertions (388.36 seconds)** | In-memory SQLite, array cache/mail and synchronous queues. Does not certify concurrent MySQL requests or external services. |
| `docker compose exec -T frontend npm run build` | **PASS** | Vue TypeScript check and Vite production build completed. A plugin timing warning did not fail the build. |
| `docker compose --profile test run --rm --no-deps browser-tests` | **PASS — 72 checks (5.4 minutes)** | Desktop and Pixel 7 Chromium against the Vite development server. APIs and camera/native detector capabilities are mocked; ZXing worker/fallback tests decode real barcode pixels. |
| `docker compose exec -T app composer audit --no-dev` | **PASS — no advisories** | Production dependencies at review time. |
| `docker compose exec -T frontend npm audit --omit=dev --package-lock-only` | **PASS — 0 vulnerabilities** | Locked production dependencies at review time. |
| `docker compose exec -T frontend npm audit --package-lock-only --json` | **OPEN — 7 findings: 5 high, 2 moderate** | Development dependency paths through Tailwind tooling; full audit exits 1. |
| Full stack / live MySQL API checks | **BLOCKED in this session** | Startup failed because host port **3307** was already allocated. App/frontend started separately; API/MySQL/scheduler/worker/phpMyAdmin readiness was not verified. |
| Production image, Nginx, backup/restore and heartbeats | **NOT RERUN** | Earlier evidence is recorded separately below. |

**Assessment:** Functionality and regression coverage have expanded substantially since September. Production acceptance remains pending the open defects and external-service/device checks below. Automated checks do not establish live-payment or deployment readiness.

## 3. Recent changes covered

### Seller stock editing — 2026-10-18

The product form now supports total on-hand stock for simple products and individual existing options. It submits only changed stock values, so unrelated edits preserve current inventory. The backend uses `InventoryService::adjust()`, records an actor/shop ledger entry and rejects stock below checkout reservations. Regression cases cover negative/fractional values, shop isolation and atomic rollback when an option cannot be adjusted.

Evidence: [SellerProductController](../backend/app/Http/Controllers/Api/Seller/SellerProductController.php), [InventoryService](../backend/app/Services/InventoryService.php), [SellerWorkspaceTest](../backend/tests/Feature/Api/SellerWorkspaceTest.php), [browser suite](../frontend/tests/e2e/commerce.spec.ts).

The operating guide's older statement that existing stock cannot be changed through product edits is stale: those edits now use the inventory adjustment service and ledger.

### Demo data and layouts — 2026-10-18

The seeder replaces **37 business tables with 20 related records each**, clears old login/reset/session records and rolls back cleanup if seeding fails. Demo accounts now use `admin@ekhmer.com`, `customer@ekhmer.com` and `seller@ekhmer.com`, with documented demo password `password`. Passwords use the model's hashed cast; seller permissions come from membership.

[DatabaseSeederTest](../backend/tests/Feature/DatabaseSeederTest.php) covers counts, repeat seeding, rollback, relationships, eligible reviews and partial refunds in the isolated test database. This review did not reseed development MySQL; fixture counts are not live database counts.

The browser suite checks shared content width and horizontal overflow across store, account, seller and admin pages at desktop/mobile sizes.

### Barcode, pricing and camera — 2026-10-17

Barcodes preserve leading zeros; purchase costs are private and barcode search is shop-scoped. Entry supports typing, keyboard-wedge USB/Bluetooth devices and local camera decoding, without automatic product save.

Camera scanning uses native detection when available, a ZXing worker otherwise and a compatibility fallback if workers are blocked. It searches the full visible frame, periodically tries thorough/rotated decoding and targets an 80 ms attempt interval. That target is not a physical-device benchmark. Coverage includes real EAN-8 pixels, upper/lower-frame and vertical labels, selected-option isolation, permissions/retry/errors, late results, teardown and torch constraints. Physical hardware acceptance remains open.

## 4. September findings: current disposition

Original IDs are retained for traceability. **Addressed** means the relevant change exists in current source, with regression coverage where noted; it does not certify all production paths. Old live order IDs, credentials and line references no longer describe current source/data.

| Original finding | Current disposition |
| --- | --- |
| B-CRIT-01, HIGH-01: cancellation corrupts payment/stock or leaks reservations | **Addressed:** order locks, fulfillment reversal, stock restoration/release and coupon release in `OrderService`; `QaHotfixTest`. Bakong cancellations become refund pending/required until a verified refund, not an assumed bank reversal. |
| HIGH-02, SEC-04: client-trusted online payment | **Addressed in code:** production blocks legacy self-confirmation; Bakong verifies amount/currency/recipient and unique transaction identity server-side. `BakongPaymentTest`, `SellerCompletionTest`, `QaHotfixTest`. Real transfers remain an acceptance gate. |
| HIGH-03, SEC-02: seeded plaintext password / login crash | **Addressed in source:** hashed cast, current `.com` demo accounts and graceful legacy invalid-hash handling. `QaHotfixTest`, `DatabaseSeederTest`. Invalid legacy credentials return **422**, not the old report's claimed 401. External database rows were not inspected. |
| HIGH-04: negative variant quantity/price | **Addressed:** non-negative bounded validation and defensive stock handling; `QaHotfixTest`, `SellerWorkspaceTest`. |
| HIGH-05: coupon capacity never reclaimed | **Addressed:** release on cancellation/refund/expiry; `QaHotfixTest`. Usage is still reserved at checkout creation, not moved to payment. Guest per-user identification remains limited. |
| MED-01: guest cart not cleared | **Addressed:** confirmation clears the exact cart ID stored privately with payment; `CheckoutTest`. |
| MED-02: order state races | **Hardened:** confirmation/release/transitions lock orders; inventory is locked too. SQLite tests do not prove concurrent MySQL behavior. |
| MED-03: duplicate product slug crashes | **Addressed for ordinary duplicates:** create/update use `uniqueSlug()`. Concurrent insert conflicts still need acceptance testing. |
| MED-04: deleted/unpublished products remain purchasable/visible | **Addressed:** active product/shop checks in cart/checkout/wishlist; `CatalogTest`, `CartTest`, `WishlistTest`. |
| MED-05: unbounded catalog pagination | **Addressed:** request/service caps; featured limit capped at 24; `CatalogTest`. |
| MED-06: null variant price mismatch | **Addressed:** cart resources fall back to product price; `CartTest`. |
| MED-07: stale review aggregate | **Partial:** published edits return to moderation and recalculate; rejection still does not. |
| MED-08: low-stock alert not rearmed on release | **Open:** `InventoryService::release()` omits `checkLowStock()`. |
| MED-09: invalid report dates | **Partial (2026-10-20):** summary dates are validated; CSV/PDF export paths still need equivalent validation. |
| MED-10, SEC-03: no authentication throttles | **Addressed:** login/register/reset/verification throttles in `routes/api.php`; `AuthTest`. |
| MED-11: coupon usage-limit race | **Hardened:** usage creation locks/revalidates the coupon inside checkout's transaction. MySQL contention tests remain pending. |
| MED-12: COD cannot be reconciled as paid | **Open:** confirmation leaves COD unpaid; reviewed fulfillment paths do not record cash capture. |
| MED-13, SEC-07: reservation abuse | **Open:** no explicit checkout-creation route throttle or pending-order quota. Idempotency handles retries, not distinct reservation attempts. |
| LOW-01: order-list aggregate fallback | **Latent:** resource still uses `order_items_count` when items are absent; current customer lists load items and calculate correctly. |
| LOW-02: mass-assignable role | **Addressed:** role/verification fields excluded from `User::$fillable`. |
| LOW-03: zero-quantity cart rows | **Addressed:** non-positive updates remove the item; unavailable quantities return validation errors. |
| LOW-04: category cycles | **Addressed:** self/descendant parenting validation; `CategoryTest`. |
| LOW-05: coupon value/duplicate code | **Partial:** percentage capped at 100. The database has a unique code constraint, but request-level uniqueness validation is absent; verify duplicate submissions return 422 rather than a database exception. |
| LOW-06: low/out stock filters overlap | **Open:** low-stock query includes zero availability. |
| LOW-07: order-number collisions | **Open:** final candidate is unchecked; no insert-conflict retry found. |
| LOW-08: missing indexes | **Review pending:** no explicit `orders.payment_status` index found. `addresses.user_id` has a constrained foreign key; inspect MySQL's backing index before claiming it is missing. |
| LOW-09: unbounded dashboard days | **Superseded:** enumerated ranges and custom-span validation in `AdminDashboardRequest`; `AdminDashboardTest`. |
| LOW-10: empty unit suite | **Obsolete:** `tests/Unit/ReceiptBrandingServiceTest.php` exists. |
| LOW-11: Bootstrap documentation drift | **Partial:** frontend docs/package use Tailwind; root agent guidelines still prescribe Bootstrap. |
| LOW-12: absent frontend environment file | **Clarified:** same-origin `/api` is intentional; `VITE_API_URL` is supported. An absent `.env` alone is not a build defect. |
| SEC-01: production debug leakage | **Guard implemented:** unexpected production JSON errors hide details; production Compose sets `APP_DEBUG=false`. Deployment verification remains required. |
| SEC-05: CORS | **Deployment review pending:** no project `config/cors.php`; inspect effective allowed origins for separate-origin deployments. No live headers were checked here. |
| SEC-06: guest identifiers | **Residual risk:** browser-supplied guest session ID acts as a bearer secret. Server-issued/signed credentials and expiry remain hardening options. |

## 5. Open defects and next actions

These are source-review findings, not new live reproductions. This update changes documentation only.

| Priority | Finding | Evidence / action |
| --- | --- | --- |
| Medium | MED-07: rejecting a published review leaves stale ratings | [ReviewService::reject()](../backend/app/Services/ReviewService.php) changes status without recalculating. Recalculate and cover approved → rejected. |
| Medium | MED-08: released reservations do not rearm alerts | [InventoryService::release()](../backend/app/Services/InventoryService.php) does not re-evaluate the marker. Cover low → replenished → low. |
| Medium | MED-09: invalid export dates can throw | [AdminReportController](../backend/app/Http/Controllers/Api/Admin/AdminReportController.php) now validates summary dates. Extend format/order/span validation to CSV/PDF export paths, which still call `Carbon::parse()` directly. |
| Medium | MED-12: COD collection reconciliation missing | [CheckoutService](../backend/app/Services/CheckoutService.php) leaves COD unpaid. Define an authorized, audited cash-collection action before relying on COD payment/revenue reports. |
| Medium | MED-13 / SEC-07: reservation exhaustion | [Checkout routes](../backend/routes/api.php) lack creation throttling; pending-order quotas were not found. Add limits while preserving retry-safe checkout. |
| Low | LOW-06: low/out overlap | [AdminInventoryController](../backend/app/Http/Controllers/Api/Admin/AdminInventoryController.php) includes out-of-stock rows in low. Decide filter semantics and align labels/query. |
| Low | LOW-07: order-number conflict handling | [CheckoutService](../backend/app/Services/CheckoutService.php) returns an unchecked final candidate. Add bounded insert-conflict retry and forced-collision coverage. |
| Follow-up | LOW-01/05/08, SEC-05/06 and documentation | Verify resource fallback, duplicate coupons, effective MySQL indexes/CORS, guest credential policy and remaining stack guidance. |
| Dependency maintenance | Seven development audit findings | Affected packages: `braces`, `chokidar`, `fast-glob`, `micromatch`, `postcss-nested`, `postcss-selector-parser`, `tailwindcss`. Plan compatible fixes or a tested Tailwind major migration. Production dependency audits are clean. |

## 6. Acceptance still required

- Restore full local/staging MySQL connectivity, check/apply migrations and verify API, queue, scheduler, cache and reservation expiry together. This review did not migrate or reseed development data.
- Exercise real Bakong transfers, multi-shop/partial/late payments, cancellations and refunds. Bank tests mock responses. The app verifies externally initiated refunds; it does not send money.
- Verify SMTP delivery/domain, OCI/DNS/TLS, secrets, off-server backups, restore procedures and alerts. No remote deployment or disaster-recovery acceptance was performed here.
- Run concurrent MySQL checkout/stock/coupon tests and load tests against an isolated staging dataset. No 1k/10k-product or production-throughput certification is claimed.
- Test physical phone cameras, Safari/iOS, focus/torch and USB/Bluetooth scanners. Pixel 7 Chromium emulation is not device certification; confirm camera frames stay local.
- Complete accessibility, Khmer copy/translation and business approval of shipping, tax, return, privacy and retention rules. This was not a full manual UX audit.

## 7. Historical evidence — not current rerun results

These results were recorded in the previous report and refer to earlier code/environment states.

| Date / scope | Previously recorded result |
| --- | --- |
| 2026-09-24 initial audit | 105 backend tests / 419 assertions; frontend build and live API probes. Original findings above originated here. |
| 2026-09-24 critical/high hotfixes | 115 backend tests / 478 assertions; cancellation/refund, sandbox gating, validation, credential and production error guards. |
| 2026-10-17 commerce | 373 backend tests / 1,837 assertions; 20 desktop/mobile checks; production image/Nginx, heartbeats, production audits and isolated backup/restore checks. Restore snapshot: 53 migration records, 14 orders, 13 products — historical counts only. |
| 2026-10-17 barcode/pricing | 380 backend tests / 1,911 assertions; 26 desktop/mobile checks; frontend type check/build. |
| 2026-10-17 camera | 40 desktop/mobile Chromium checks; frontend build; clean production npm audit. |
| 2026-10-17 full-frame scanner | 32 focused development checks; 58 production-build checks plus two torch-constraint checks; frontend build. Physical devices not certified. |

The [CI workflow](../.github/workflows/ci.yml) defines backend tests, frontend build, production dependency audits and desktop/mobile browser tests. This report does not assert a remote CI run succeeded.

