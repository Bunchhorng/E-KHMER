F13 — CHECKOUT & RESERVATION ABUSE PROTECTION

Status: PASS.

Root Risk: Repeated new `POST /api/checkout` requests could each reserve stock and create another unpaid parent order. The former generic 30/minute throttle did not bound concurrent holds or protect against slow accumulation.

Checkout Flow Before: `CheckoutRequest` validation → optional owner-scoped `checkout_attempts` lookup → resolve cart/address → cart-line locks and server totals/coupon/delivery validation → `InventoryService::reserveMany()` → pending parent order/items/shop allocations/payment/shipments/coupon usage in one transaction. There is no separate reservation model/table: `inventories.reserved_quantity`, inventory ledger entries, and the pending parent order represent the hold. Confirmation consumes the hold; cancellation/expiry releases it.

Checkout Flow After: Resolve authenticated/guest identity → recognize an exact, validated, committed owner-scoped retry → IP and owner attempt budgets for new/changed/malformed submissions → standard request validation → lock the owner's unique cart row → locked idempotency lookup → check active parent checkout quota → existing authoritative validation, atomic reservations and order creation. The owner lock remains held until the outer transaction commits. The only customer endpoint that creates stock holds is `POST /api/checkout`; quotes, browsing, cart reads, confirmation and cancellation do not use this new limiter. Existing confirmation/payment verification throttles remain in place.

Authenticated Identity Key: `checkout:user:{authenticated_user_id}`. Idempotency retains `SHA256("user:" + user_id)` plus the request key. Client-supplied user IDs do not determine ownership.

Guest Identity Key: Existing `X-Session-Id` cart owner; rate key `checkout:guest:{SHA256("guest:" + stored_session_id)}`. An indexed lookup resolves the stored cart session so MySQL case/collation aliases share a budget. Missing, blank or over-255-byte guest sessions fail safely and still count as attempts. Active quota uses the persisted payment's cart ID or the existing guest session token; cart-ID matching also prevents session case aliases from hiding holds. Idempotency retains the original owner hash format and guest order authorization.

IP Strategy: Separate, broader `checkout:ip:{SHA256(request.ip())}` budget. The owner limit applies across IP changes; the IP budget limits session rotation. Different accounts/guests on one IP retain their own normal owner budgets. Use Laravel's resolved request IP; do not trust arbitrary client-supplied forwarding headers. Behind an unconfigured reverse proxy this can be the proxy address, so shared-IP capacity must be considered when tuning deployments.

Rate Limit Configuration: 8 new attempts per owner per 60 seconds; 120 new attempts per resolved IP per 60 seconds. Eight permits normal corrections/retries, while 120 accommodates many distinct customers sharing a network. The three-hold cap prevents slow stock accumulation. These are starting operational defaults, not measured production traffic claims. Atomic framework counter increments occur before comparing the limit; blocked attempts cannot race through a read-then-increment check. The API returns `429`, `CHECKOUT_RATE_LIMIT`, a neutral message and `Retry-After`, without publishing quota thresholds.

Active Reservation Limit: 3 active parent checkouts per account or guest cart/session. This counts checkouts, not product lines, units, child shop orders, or KHQR rows; no arbitrary product quantity cap was introduced.

Active Pending Order Limit: The same three-checkout quota covers pending unpaid orders holding stock/payment windows. It is one shared bound, not two independent counters. Changed carts and different idempotency keys create separate attempts subject to that bound.

Active Status Definitions: Parent `status = pending`, `payment_status IN (unpaid, partial)`, associated payment `status = pending`, and `placed_at > now - reservation_minutes`, scoped to the owner. Confirmed/processing/shipped/delivered, cancelled, paid, failed, refunded, and expired checkouts do not consume the active quota. A COD order counts only during its initial pending reservation stage, before confirmation consumes the hold. Soft-deleted orders follow the existing model scope.

Reservation Expiry: Existing `ORDER_RESERVATION_MINUTES=15`, derived from `placed_at`. Creation and exact replay return the same derived `reservation_expires_at`; retrying/reading a QR never extends it. Confirmation rejects an expired pending checkout even before scheduled cleanup. Expiry equality is now included by cleanup.

Cleanup Strategy: Reuse `CheckoutService::expireStaleReservations()` and the existing every-minute `checkout:expire-reservations` scheduler with `withoutOverlapping()`. `release()` locks the order, checks its current state, releases stock and coupon usage once, cancels the pending order and synchronizes fulfillment. Repeated cancellation/cleanup does not duplicate ledger releases. No second cleanup job was added. Expired rows stop counting immediately; physical stock release occurs on the next successful scheduled cleanup.

COD Handling: Existing confirmation deducts reserved stock, confirms fulfillment, and leaves payment unpaid/pending collection. Such confirmed COD orders do not count against the active hold quota while awaiting delivery/collection. F04 collection/accounting is preserved.

Bakong/KHQR Pending Handling: Pending online checkouts within their payment window count once per parent, including partial multi-shop payments. Existing `BakongService::prepare()` locks the order and reuses existing shop payment rows/QRs. It rejects expired windows and does not reserve more stock. Missing Bakong/shop configuration fails before reservation. If later QR initialization fails, its transaction rolls back payment-intent creation; the existing order/hold remains bounded by quota and the original expiry or explicit cancellation. Existing bank verification and refund-required handling remain authoritative.

Idempotency Interaction: Exact committed retries with unchanged validated details and the same owner/key bypass both new-attempt budgets and the active quota, including after network loss or confirmation. Changed/malformed payloads still count against rate limits and cannot recover another owner's order. The middleware uses the same payload digest and validation as the controller. An in-flight, uncommitted attempt is not a committed replay; a retry can use the same key after completion. Expired/cancelled replay results retain the original order; the frontend clears both persistent and in-memory retry keys before the next intentional submission.

Concurrent Request Protection: Existing unique `carts.user_id`/`carts.session_id` rows provide owner-level `FOR UPDATE` serialization, including requests without keys and those using different keys. Owner lock acquisition precedes snapshot reads inside the idempotent transaction. Attempts, quota orders and payment subqueries use current locking reads, avoiding stale MySQL REPEATABLE READ decisions. Reservation inventory row locks and outer rollback remain authoritative. Checkout transactions retry database deadlocks up to three attempts. Parallel same-key requests recover the same full resource and one hold; parallel requests competing for the last quota slot admit only one.

Redis Usage: Laravel's `Illuminate\Cache\RateLimiter` uses the explicitly configured shared Redis store, independent of the application's default cache. Production Compose pins that store to Redis and forwards the tuning settings to app/worker/scheduler. All app replicas must share the Redis connection and cache namespace. Redis errors reject new checkout with a safe `503 CHECKOUT_TEMPORARILY_UNAVAILABLE`; there is no process-local production fallback. Committed exact retries can recover without invoking the limiter. Tests use array cache normally and an isolated, persistence-disabled Redis service for actual shared-cache checks.

Blocked Request Side Effects: NONE — no order, payment, shipment, reservation ledger, coupon usage/counter, stock change, low-stock marker/notification, or committed checkout-attempt row. Only rate counters and safe application limit logs change. An already committed idempotent result can be returned. No audit-table/Telegram event is emitted for each rejection. Logs contain limit type/count, user ID or guest owner hash, endpoint and resolved IP; they omit contact/address data, session tokens, cookies, authorization and idempotency keys.

Inventory Protection: Quota runs before stock mutation or order-number allocation. Existing server-side cart availability checks, variant validation, row-locked reservations and transactional rollback remain intact. Huge quantities are rejected against actual stock. A second-line reservation failure rolls back an earlier line, ledger entry and marker.

Coupon Usage Protection: F01/F03/F12 ownership, eligibility, usage limits, normalized codes and totals are unchanged. Blocked attempts never invoke coupon redemption. Cancellation/expiry retains existing idempotent usage release.

Low-Stock Notification Interaction: F11 remains reservation-aware. Rejected rate/quota attempts never reserve or start an episode. A regression places stock just above its threshold, blocks another hold, and verifies no marker/notification/ledger side effect.

Frontend 429 UX: Shared typed error extraction recognizes `CHECKOUT_RATE_LIMIT`, shows the retry delay and says the cart was kept. Existing generic login throttling messages are unchanged. Place Order remains disabled while pending and re-enables in `finally`; errors preserve the cart and current retry key. Refresh never posts a new checkout automatically.

Frontend Active-Limit UX: `ACTIVE_CHECKOUT_LIMIT` displays “Please complete or cancel an existing checkout before placing another order. Your cart has been kept.” No raw Axios/server exception is shown. Existing cart refresh on `409` preserves server-authoritative availability. Reopening/refreshing a payment page only reads its existing QR.

Files Changed:

| File | Change |
| --- | --- |
| `backend/app/Http/Middleware/ProtectCheckout.php` | Early identity/replay recognition and attempt limits |
| `backend/app/Services/CheckoutProtection.php` | Shared counters, safe errors/logs, active parent quota |
| `backend/app/Http/Controllers/Api/CheckoutController.php` | Owner serialization, current idempotency reads, replay expiry |
| `backend/app/Services/CheckoutService.php` | Locked quota before reservation, deadlock retries, expiry boundary |
| `backend/routes/api.php` | Apply protection only to hold creation |
| `backend/config/ecommerce.php`, `backend/.env.example` | Document/configure defaults |
| `.env.production.example`, `compose.production.yml` | Forward production tuning; require shared Redis |
| `backend/phpunit.xml` | Array limiter store for ordinary isolated tests |
| `backend/tests/Feature/Api/CheckoutProtectionTest.php` | Abuse/lifecycle/side-effect regressions |
| `backend/tests/Feature/Api/GuestCheckoutTest.php` | Local burst budget for the existing field-validation matrix |
| `backend/tests/Integration/CheckoutProtectionConcurrencyTest.php`, `backend/tests/Support/checkout-race-worker.php` | Real MySQL/Redis HTTP-process races and isolation guards |
| `docker-compose.yml` | Optional disposable `checkout-test-redis` test service |
| `frontend/src/api/errors.ts`, `frontend/src/views/CheckoutView.vue` | Actionable limit messages; clear expired retry keys |
| `frontend/tests/e2e/guest-checkout.spec.ts` | Desktop/mobile rejection, retry, refresh, expiry and QR recovery |
| `docs/F13-CHECKOUT-RESERVATION-ABUSE-PROTECTION-REPORT.md` | Implementation, configuration and validation record |

Schema Changes: None.

Indexes Added: None. Existing unique owner cart indexes, unique `(owner_key, request_key)` attempt index, unique payment `order_id`, and order `(user_id, status)`, `status`, `placed_at` indexes were reviewed. Live `php artisan db:table orders` confirmed InnoDB and the expected indexes. Guest matching uses existing payment JSON and the recent pending parent filter; no speculative index or migration was introduced.

Config Added: `CHECKOUT_LIMITER_STORE=redis`, `CHECKOUT_RATE_WINDOW_SECONDS=60`, `CHECKOUT_OWNER_ATTEMPTS=8`, `CHECKOUT_IP_ATTEMPTS=120`, `CHECKOUT_ACTIVE_LIMIT=3`. Reservation duration continues to use the existing setting. Use `REDIS_HOST=redis` inside the provided Docker stack; production Compose already supplies it. Keep production Redis shared, run the existing scheduler/worker, and rebuild cached Laravel configuration after changing environment settings. Do not deploy the testing-only array limiter store to production.

Tests Added: 25 feature cases; 11 real MySQL/Redis cases; 5 browser scenarios across desktop/mobile (10 executions). Coverage includes a 20-request owner burst, slow holds, independent owners on shared IP, guest session aliases, malformed requests, exact/changed/cross-owner retries, the last quota slot, the last Redis attempt, last-unit stock contention, five lines/two shops, QR reuse, cancellation, paid/COD orders, expiry equality, failure rollback and all protected side effects.

Backend Tests: Passed — full unit/feature suite **739 passed / 5,536 assertions**, 853.62 seconds; no failures or skips. This includes all 25 new protection cases and F01–F12 backend regressions. Pint passed for all changed PHP files; whitespace checks and development/production Compose schema checks passed.

Redis / Rate Limiter Tests: Passed — all 11 isolated MySQL/Redis HTTP-process tests, 190 assertions, 125.58 seconds. Includes shared owner/IP counters, simultaneous last-attempt admission and recovery of an exact committed retry at the rate limit.

Frontend Tests: Passed — all 10 new desktop/mobile F13 executions passed, including disabled pending submission, actionable rate/quota errors, cart preservation, unchanged-key retry, no POST on refresh, expired-key replacement and reopening the same payment QR.

Browser Tests: Passed — all **277 executed cases passed across the combined run and focused rerun**, with **1 existing intentional desktop skip** for a mobile-only account-menu case. The combined F01–F13/account run finished with 275 passed, 2 mobile account timeouts, 1 skipped in 23.0 minutes, without retries. Both affected account tests visit 13 pages within a single 60-second budget; traces showed that budget expiring near the returns page. A focused sequential mobile rerun with a 120-second budget passed both cases in 1.1 minutes, without code changes or retries. Command: `docker compose run --rm browser-tests npx playwright test tests/e2e/account-ui.spec.ts --grep 'all account features' --project=mobile --workers=1 --timeout=120000 --retries=0 --reporter=line --output=test-results/f13-account-rerun`. Browser assertions use stateful mocked APIs; actual writes, ownership, limits and races are separately checked against backend/MySQL/Redis. No development data is changed by browser fixtures.

MySQL Concurrency: PASS — coordinated real HTTP processes against MySQL 8 InnoDB demonstrate one remaining quota slot cannot admit two new holds, concurrent identical keys create one order/hold and return equivalent complete resources, and different owners cannot reserve the same last unit. Both authenticated and guest paths are covered. Barriers announce before acquiring the cart lock and hold the first acquired lock until both requests have attempted it; these are actual overlapping transactions, not SQLite simulations.

Type Check: PASS — Docker `npx vue-tsc -b`; production build also runs it.

Production Build: PASS — Docker `npm run build` (`vue-tsc -b && vite build`).

F01 Regression: PASS — coupon ownership and mixed-shop eligibility; backend/browser.

F02 Regression: PASS — unavailable-cart recovery and safe removal; backend/browser.

F03 Regression: PASS — authoritative discount caps, taxes and totals; backend/browser.

F04 Regression: PASS — COD confirmation, collection and reversals; backend/browser.

F05 Regression: PASS — guest/account checkout, contact validation and ownership; backend/browser.

F06 Regression: PASS — catalog filtering; backend/browser.

F07 Regression: PASS — mobile color filtering; backend/browser.

F08 Regression: PASS — safe asynchronous cart/checkout mutations and errors; backend/browser.

F09 Regression: PASS — formatted descriptions and sanitizer; backend/browser.

F10 Regression: PASS — review aggregates and moderation; backend/browser.

F11 Regression: PASS — reservation-aware low-stock lifecycle, recipients, rollback and inbox; backend/browser.

F12 Regression: PASS — duplicate coupon validation, edit ownership and form errors; backend/browser.

Remaining Risks: The scheduler must remain healthy to physically release expired stock. Guest identities remain client-held sessions; distributed account/session/IP rotation is outside these bounded checkout controls. Reverse proxies must expose an appropriate resolved IP or operators must allow for the shared proxy/NAT budget; forwarding headers are not blindly trusted. At substantially larger pending-order volumes, profile the guest JSON quota query before adding a targeted schema/index change. Existing global reservation-window changes affect derived expiry for outstanding holds. Browser tests use stateful API fixtures; actual backend effects and races are checked separately against MySQL/Redis. No staging payment transfer or physical-device acceptance was performed.

Related Follow-Up: F14 — Order Number Collision Handling. Not started; order-number generation is unchanged.

Before/after example: Previously one owner could repeatedly create unpaid checkouts until legitimate customers saw false out-of-stock results. Now three pending parent checkouts exhaust that owner's active quota; further new attempts return `409 ACTIVE_CHECKOUT_LIMIT`, and excess bursts return `429 CHECKOUT_RATE_LIMIT`. Exact committed retries return the original checkout. Rejected requests create no additional stock hold, payment or coupon usage.

Validation commands run inside the project's Docker services. MySQL checks use only `ecommerce_f13_test` on tmpfs `restore-db`; Redis checks require `checkout-test-redis`, empty Redis URL and array default cache before any flush. No development database migrations, resets or cache flushes are required by this change. Both optional test services are stopped after verification.
