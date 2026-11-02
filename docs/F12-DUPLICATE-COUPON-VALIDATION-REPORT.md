F12 — DUPLICATE COUPON VALIDATION

Status: PASS

Root Cause: `AdminCouponRequest` already trimmed and uppercased codes but had no uniqueness rule. The controller wrote directly to the model, so a duplicate reached the database index and escaped as an unhandled database exception. The coupon form ignored Laravel field errors. Editing also depended on the first page of coupon listings, and the admin resource omitted expiry, status, and usage-limit metadata needed to preserve the existing form values.

Uniqueness Policy: GLOBAL — a code is reserved across every shop and platform coupons (`shop_id = null`). This preserves F01 and the existing deterministic checkout lookup; shop context does not select a second coupon with the same code. There are no seller coupon CRUD routes in the current application. Existing coupon management remains restricted to authenticated super admins; no new seller permissions were introduced.

Database Constraint: Existing InnoDB `coupons_code_unique UNIQUE(code)` remains authoritative. The actual development schema was inspected: `code VARCHAR(255) COLLATE utf8mb4_unicode_ci`, with a separate non-unique shop index. This existing collation compares codes case-insensitively and also retains its existing accent-comparison behavior. The application continues to limit newly managed codes to 50 characters. No constraint was removed or replaced.

Application Validation: `AdminCouponRequest` runs `bail|required|string|max:50` and global `Rule::unique('coupons', 'code')`. Updates ignore only the trusted, bound route `Coupon` model. Body fields such as `id`, `coupon_id`, `ignore`, and `shop_id` cannot widen the uniqueness scope or exempt another coupon. Existing field validation and controller policy checks are retained.

Coupon Code Normalization: Shared `Coupon::normalizeCode()` applies the existing trim-and-uppercase behavior. Request preparation, management service writes, and checkout lookup use it. There is no model mutator or bulk data rewrite: existing records are not renamed by unrelated updates. Hyphens, underscores, internal spaces, and the existing supported string syntax remain allowed. Non-string/empty inputs now reach normal validation instead of a `trim()` type error.

Case Sensitivity: Case-insensitive for managed codes, consistent with uppercase storage, checkout `UPPER(code)` lookup, and the inspected MySQL unique index. `SAVE20`, `save20`, and `Save20` conflict. MySQL also verifies that an existing legacy lowercase row is rejected before insertion. The SQLite feature suite tests normalized canonical codes; it is not used to infer MySQL collation semantics.

Whitespace Policy: Trim surrounding whitespace before validation/storage/lookup. `"  SAVE20  "` conflicts with `SAVE20`; whitespace-only codes fail required validation. Internal spaces remain supported. Existing stored values are not automatically trimmed or rewritten.

Create Duplicate Behavior: HTTP 422 with `errors.code[0] = "The coupon code has already been taken."` Normal duplicates are rejected before a model insertion is attempted. One canonical unique code still returns 201.

Update Same-Code Behavior: Succeeds after normal normalization; only the current route coupon is excluded from uniqueness. Changing the maximum discount while retaining the code works. Ownership, usage counters, start date, and other untouched data remain unchanged. The editor preserves an unchanged expiry timestamp rather than truncating its time to midnight.

Update Duplicate-Code Behavior: HTTP 422 on `code`; both coupons remain unchanged. A forged body ID cannot bypass the rule. A code claimed between validation and the actual UPDATE also returns a safe 422 without overwriting the original coupon.

Disabled Coupon Policy: Code remains reserved while the disabled coupon exists. Disabling does not create another coupon namespace.

Expired Coupon Policy: Code remains reserved while the expired coupon exists. Expiration does not recycle a code.

Soft-Deleted Coupon Policy: N/A — `Coupon` does not use soft deletes. Existing deletion physically removes the row, so the code can be reused afterward. This policy and existing deletion/cascade behavior were not changed.

Platform Coupon Behavior: Platform and shop coupons share the same global namespace. A platform code conflicts with the same code in any shop, and shop codes conflict across shops. `NULL` shop ownership does not bypass validation.

Database Race Protection: Management writes go through `CouponService::create()` / `update()`. Laravel `UniqueConstraintViolationException` is translated into the same normal field-level `ValidationException`. Other database failures are not mislabeled as coupon conflicts. Raw SQLSTATEs, queries, constraint names, and stack traces do not appear in duplicate responses, even with debug mode enabled. The existing unique index is the final race safeguard; there are no new transactions, tables, locks, audit events, notifications, or usage accounting changes.

Concurrent Create: PASS — two authenticated HTTP-kernel processes both pass Form Request validation and authorization, pause immediately before insertion, and are released together against isolated MySQL 8. Exactly one returns 201 and the other returns 422; exactly one coupon row exists.

Frontend Validation UX: The form reuses `extractFieldErrors()` and `extractErrorMessage()` from the existing error helper. The code field has a persistent inline error, `aria-invalid`, and an associated error description. Other form values remain populated; both submit buttons recover after failure. Both buttons and form inputs are disabled while saving/loading, and the handler also rejects repeated/programmatic submissions. The edit form loads its exact coupon via the new authorized `GET /api/admin/coupons/{coupon}` endpoint, including coupons outside page one. The admin-only resource now supplies usage limit, start date, expiry, and status; the public validation response retains its metadata boundary. Maximum-discount editing has English/Khmer labels. Localized server field errors display through the same parser; broader backend Khmer translations remain outside this task.

Files Changed:

| File | Change |
| --- | --- |
| `backend/app/Http/Requests/AdminCouponRequest.php` | Normalized global uniqueness, trusted self-ignore, safe malformed-input validation |
| `backend/app/Models/Coupon.php` | Shared existing normalization and translated duplicate-code message |
| `backend/app/Services/CouponService.php` | Management writes with unique-conflict fallback; reuse normalization for lookup |
| `backend/app/Http/Controllers/Api/Admin/AdminCouponController.php` | Delegate writes and expose authorized direct coupon retrieval |
| `backend/app/Http/Resources/CouponResource.php` | Editor metadata on admin responses only |
| `backend/routes/api.php` | Authenticated/admin coupon show route |
| `frontend/src/api/admin.ts` | Typed coupon ownership and direct getter |
| `frontend/src/views/admin/CouponFormView.vue` | Field errors, retained values, safe retry/duplicate-submit guard, direct edit loading, discount-cap editing |
| `frontend/src/locales/en.json` | Maximum-discount label |
| `frontend/src/locales/km.json` | Matching Khmer label |
| `backend/tests/Feature/Api/DuplicateCouponTest.php` | 27 validation, scope, mutation, fallback, edit, and security cases |
| `backend/tests/Integration/CouponUniquenessConcurrencyTest.php` | 3 opt-in real MySQL HTTP/concurrency/collation cases |
| `backend/tests/Support/coupon-race-worker.php` | Guarded HTTP worker and deterministic pre-write barrier |
| `frontend/tests/e2e/duplicate-coupons.spec.ts` | 5 scenarios on desktop/mobile |
| `docs/F12-DUPLICATE-COUPON-VALIDATION-REPORT.md` | Decisions, evidence, and operational limits |

Schema Changes: None. No migrations or dependency changes. The supplied task document is unchanged. A read-only audit of 20 existing development coupons found **0 noncanonical codes and 0 normalized collision groups**. No coupons were renamed, deleted, or otherwise repaired.

Indexes Changed: None.

Tests Added: 27 normal backend cases, 3 opt-in MySQL integration cases, 5 browser scenarios × desktop/mobile = 10 browser cases. Tests prove rejection before insert/update, safe races after validation, self-ignore without trusting body IDs, shop/platform global uniqueness, inactive/expired reservation, physical deletion/reuse, supported syntax and length, admin-only metadata, and exact editor retrieval. Browser tests cover field errors, all populated values, corrected retries, unchanged-code cap edits, duplicate-code updates, double-submit prevention, and Khmer rendering of localized server errors.

Backend Tests: Passed — full suite **714 passed / 5,327 assertions**, 773.19 seconds, no failures or skips. Focused coupon/ownership tests: **62 passed / 399 assertions**, including all 27 new cases. F01–F11 backend regressions pass.

Frontend Tests: Passed — all 10 new coupon browser cases passed across desktop and mobile in the final combined regression.

Browser Tests: Passed — final F01–F12/account regression **267 passed, 1 existing intentional skip**, 21.4 minutes, no retries. All 10 new F12 cases passed. The skip is the existing desktop run of the mobile-only account navigation test. Initial focused run: 9/10 passed with one initial desktop edit-load timeout; the isolated editor rerun passed on both desktop and mobile. Browser fixtures use stateful mocked API responses; actual validation, writes, ownership, and conflicts are verified separately in backend/API/MySQL tests. Browser tests do not modify development data.

Type Check: PASS — `docker compose exec -T frontend npx vue-tsc -b`, plus the production build's type check.

Production Build: PASS — `docker compose exec -T frontend npm run build`; Vite bundle built successfully in 25.25 seconds.

F01 Regression: PASS — coupon ownership, eligible subtotal, mixed-shop behavior; backend and browser.

F02 Regression: PASS — cart recovery; backend and browser.

F03 Regression: PASS — discount caps and checkout totals; backend and browser.

F04 Regression: PASS — COD lifecycle; backend and browser.

F05 Regression: PASS — guest/account checkout; backend and browser.

F06 Regression: PASS — catalog filters; backend and browser.

F07 Regression: PASS — mobile color filters; backend and browser.

F08 Regression: PASS — safe asynchronous mutations/errors; backend and browser.

F09 Regression: PASS — safe formatted descriptions; backend and browser.

F10 Regression: PASS — review aggregates; backend and browser.

F11 Regression: PASS — low-stock lifecycle; backend and browser.

MySQL Concurrency: PASS — **3 tests / 41 assertions**, MySQL 8 in the disposable `ecommerce_f12_test` database on `restore-db`. Create and update races use real authenticated HTTP requests and a pre-write barrier; all contenders have already passed validation/authorization. Concurrent creates return `[201, 422]`, concurrent updates `[200, 422]`, with no SQL/exception details in the losing response. A legacy lowercase coupon is also rejected by the actual MySQL presence check before insertion. The test and worker refuse other environments/hosts/databases. Existing migrations ran only in the disposable database, fixtures were cleaned, and the service was stopped. Development data was not reset.

Remaining Risks: Existing MySQL collation remains the authority for legacy case/accent equivalence; deployment schemas must retain the existing unique index/collation. New management paths should use the same request/service instead of direct model writes. Existing coupon mutation rules requiring code/type/value on updates are retained; unrelated partial status-toggle redesign is outside F12. Full backend Khmer translation coverage and physical-device/staging acceptance remain outside this task. No automatic repair of existing coupons was performed.

Related Follow-Up: F13 — Checkout & Reservation Abuse Protection. Not started.

Before/after example:

```text
BEFORE

Existing coupon: SAVE20
Admin creates: SAVE20
Validation: PASS
Database: Duplicate entry error
Result: 500 / database exception

AFTER

Existing coupon: SAVE20
Admin creates: SAVE20
Laravel validation: FAIL
Response: 422
Field: code
Message: The coupon code has already been taken.
Database: No duplicate insert attempted
Frontend: Coupon Code field displays the error
```

Verification commands (all runtimes inside Docker):

```sh
docker compose exec -T app php artisan db:table coupons --no-interaction
docker compose exec -T app php artisan test --compact --filter='DuplicateCouponTest|CouponTest|ShopCouponOwnershipTest|ShopOwnershipTest'
docker compose exec -T app php artisan test --compact
docker compose exec -T frontend npx vue-tsc -b
docker compose exec -T frontend npm run build
docker compose run --rm browser-tests npx playwright test tests/e2e/duplicate-coupons.spec.ts tests/e2e/low-stock-lifecycle.spec.ts tests/e2e/account-ui.spec.ts tests/e2e/review-aggregates.spec.ts tests/e2e/product-descriptions.spec.ts tests/e2e/seller-product-form.spec.ts tests/e2e/cart-mutations.spec.ts tests/e2e/catalog-colors.spec.ts tests/e2e/catalog-filters.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts tests/e2e/guest-checkout.spec.ts --output=/app/test-results/f12-regression --reporter=line --retries=0
```

To run opt-in MySQL checks, start `restore-db`, create only `ecommerce_f12_test` there, and pass `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_HOST=restore-db`, `DB_DATABASE=ecommerce_f12_test`, its disposable credentials, blank `DB_URL`, array cache/mail, and sync queue to the app container. Run the existing migrations and `php artisan test --compact tests/Integration/CouponUniquenessConcurrencyTest.php`. Workers receive a short-lived test token through the process environment, not source code or command arguments. Stop the disposable service afterward. Pint was run on the explicit changed PHP files because the app container does not mount `.git`.
