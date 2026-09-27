# Multi-Shop Audit & P1 Implementation Report

**Date:** 2026-09-28
**Scope:** P1 (broken data integrity, authorization and session handling) only
**Baseline:** 151 tests / 667 assertions passing → **Final: 167 tests / 729 assertions passing**
**Reference spec:** `docs/project-audit-and-implement-missing-features.md`

---

## 1. What was in scope

You selected **P1 — broken items only**. P2 (missing features) and P3 (hardening) are
explicitly deferred. The following architectural decisions were recorded for when
merchant-facing work begins, and were **not** implemented here:

| Decision | Choice |
| --- | --- |
| Merchant route space | `/shop/*` (note: the public branch page already owns `/shop` — see §6) |
| Staff model | Reuse `shop_users` with an added permission layer |
| Payouts | Ledger-only, manually marked by an admin; no gateway integration |

---

## 2. Findings fixed in P1

### 2.1 Branch ownership was never recorded

`shop_id` was silently dropped on nearly every write path, so no branch could ever
report on its own catalogue, stock, sales or reviews.

| Surface | Before | After |
| --- | --- | --- |
| `order_items.shop_id` | Never written at checkout | Stamped per line from the sold product |
| `inventories.shop_id` | NULL unless a caller happened to pass it | Derived from variant → product |
| `inventory_transactions.shop_id` | Always NULL (see §2.2) | Inherited from the stock row |
| `reviews.shop_id` | Column did not exist | Added, backfilled, stamped on create |
| `coupons.shop_id` / `shipping_methods.shop_id` | Global | Branch-scoped, default shop backfilled |
| `product_variants.shop_id` | Column did not exist | Added, cascaded on reassignment |

Ownership is **derived, never trusted from the request**: the shop is resolved from
variant → product, so guest checkout, the reservation-expiry cron and refunds all
land on the correct branch without needing a shop context.

### 2.2 Two silent-corruption bugs found by the new tests

These are the highest-value finds, because both failed *silently* rather than loudly:

1. **`InventoryTransaction` was missing `shop_id` in its `#[Fillable]` attribute.**
   The service was correctly passing the value, and Eloquent was correctly discarding
   it. The entire inventory ledger was being written branch-less with no error.

2. **`InventoryService` only stamped `shop_id` when *creating* a stock row.** Any
   pre-existing row with a NULL `shop_id` stayed NULL forever, and every subsequent
   ledger entry copied that NULL. Fixed with a `syncShop()` helper called on every
   mutation, which makes the service self-healing.

I then swept every model carrying a `shop_id` column; `InventoryTransaction` was the
only remaining gap.

### 2.3 Variant SKU uniqueness was global

`product_variants.sku` had a global `UNIQUE` index, so a manufacturer SKU could only
ever be listed by one branch — a real blocker for a multi-branch catalogue, and it
surfaced as a raw **500** (`UniqueConstraintViolationException`) rather than a
validation error.

Per your decision, uniqueness is now **per branch**:

- New migration adds `product_variants.shop_id` and replaces the global index with
  `unique(shop_id, sku)`.
- Reassigning a product cascades `shop_id` to its variants, stock rows and ledger.
- Reassigning to a branch that already uses one of the product's SKUs is now rejected
  with a **422** on `shop_id` before anything is written.

Variants whose product has no shop keep a NULL `shop_id`; SQL unique indexes do not
treat NULLs as duplicates, so platform-level variants remain unconstrained and
`shop:assign-default` can still claim them.

### 2.4 Branch authorization

Added and registered policies for products, inventory, orders, orders items, coupons,
shipping methods and reviews. The base controller now uses `AuthorizesRequests`, so
`$this->authorize()` is available at the HTTP layer.

Notable behaviours:

- `ProductPolicy::changeShop()` stops a manager moving a product out of their branch.
- `restore()` was previously reachable with no ownership check; it now resolves the
  trashed row explicitly (implicit binding cannot see soft-deleted rows) and
  authorizes against it.
- `AdminOrderController` scopes order listings and strips other branches' line items
  from `show`/`receipt`, so a shared order cannot leak a competitor's items or prices.

> **Caveat worth acting on:** every `/api/admin/*` route is still gated by the `admin`
> middleware, which admits `role = admin` only. A branch manager therefore *cannot
> reach* these controllers at all, which makes the branch policies currently
> defence-in-depth rather than load-bearing. The new tests assert policies through the
> `Gate` and data integrity end-to-end over HTTP. Exposing a manager-scoped admin
> surface is the prerequisite for making those policies load-bearing — see §6.

### 2.5 Stale frontend session on 401

A 401 from any request left the stored token and cached user in place and never
redirected. The Axios interceptor now clears session state, emits `auth:unauthorized`,
and the app listens once at bootstrap to redirect to `/auth/login?redirect=…`.

### 2.6 Product restore + resource exposure

`ProductDetailResource` now exposes `shop_id` and the `shop` object (it was the only
product resource missing them, so admin create/update responses returned a product
with no branch). `CouponResource` gained `shop_id`. Admin product, coupon and shipping
indexes accept a `shop_id` filter (`?shop_id=N`, `?shop_id=none` for unassigned).

---

## 3. Migrations

| Migration | Purpose |
| --- | --- |
| `2026_09_27_000001_add_shop_id_to_reviews_and_backfill_shop_ownership.php` | Adds `reviews.shop_id` + composite index; backfills all shop-less rows |
| `2026_09_27_000002_scope_variant_sku_uniqueness_per_shop.php` | Adds `product_variants.shop_id`; swaps the global SKU unique for a per-branch composite |

Both use **correlated subqueries rather than `UPDATE ... JOIN`**, so they run
unchanged on MySQL and on the SQLite in-memory database the test suite uses. (The
first draft used `UPDATE ... JOIN`; that is MySQL-only syntax and it broke the entire
suite at migration time — worth remembering as a house rule for this repo.)

`down()` on migration 2 restores the global `unique('sku')`, which will fail loudly if
two branches now legitimately share a SKU. That is intentional: a visible failure
beats silent data loss.

### Live MySQL verification

Both migrations applied. `NULL shop_id` counts are now **0** across `reviews`,
`order_items`, `inventories`, `inventory_transactions`, `coupons`, `shipping_methods`
and `product_variants`. Confirmed indexes on `product_variants`:

```
PRIMARY                                  seq=1 col=id         uniq=YES
product_variants_shop_sku_unique         seq=1 col=shop_id    uniq=YES
product_variants_shop_sku_unique         seq=2 col=sku        uniq=YES
product_variants_shop_id_index           seq=1 col=shop_id    uniq=no
```

---

## 4. Tests

`backend/tests/Feature/Api/ShopOwnershipTest.php` — **16 tests, 62 assertions**, all
failing before the fixes and passing after:

- checkout stamps `shop_id` on every line across two branches in one order
- `InventoryService` derives the shop, creates on-demand rows, and repairs legacy NULL rows
- inventory ledger entries carry the owning shop
- admin can create a product in a chosen branch; stock follows
- product / coupon listing filters by branch
- reassigning a product cascades to stock, and reclaims/releases its SKU correctly
- restore policy + end-to-end restore
- `changeShop` blocks moving a product out of your own branch
- reviews inherit the product's branch; moderation policy denies cross-branch access
- coupons and shipping methods are branch-scoped, persisted and filterable
- SKU is unique per branch, not globally

---

## 5. Verification performed

```
docker compose exec -T app php artisan test    → 167 passed (729 assertions)
docker compose exec -T frontend npm run build   → built in 9.71s
docker compose exec -T app ./vendor/bin/pint --test <new files> → PASS
docker compose exec -T app php artisan migrate --force → 1 migration applied
```

Note on Pint: the repository is **not** Pint-clean at baseline (seeders, existing tests
and routes all report pre-existing violations). I did not run a repo-wide format, since
that would bury the P1 changes in unrelated churn. Both new files are clean, and no
style violation I introduced remains.

---

## 6. Known gaps and recommended next steps

1. **Branch policies are not yet load-bearing.** The `admin` middleware blocks branch
   managers from every admin route. Introduce a manager-scoped surface (or relax the
   middleware to accept branch membership) and the policies already written will start
   enforcing isolation for real. This is the single highest-value follow-up.
2. **`/shop/*` is contested.** The public branch page already occupies `/shop`. Your
   chosen merchant path is also `/shop/*`; these will need to be separated (e.g. the
   public page moves to `/stores/*`) before merchant work starts.
3. **`shop_users` is empty** (0 rows) and has no permission layer — the merchant
   dashboard, commission and payout ledger features all depend on it.
4. **Review images** remain unimplemented (deferred from P1).
5. **Null-`shop_id` variants are unconstrained** by the new unique index, a documented
   consequence of SQL's NULL semantics. If you want platform-level products to also
   enforce unique SKUs, that needs a sentinel shop id rather than NULL.

---

## 7. Files changed

**New**
```
backend/app/Policies/CouponPolicy.php
backend/app/Policies/OrderPolicy.php
backend/app/Policies/ReviewPolicy.php
backend/app/Policies/ShippingMethodPolicy.php
backend/database/migrations/2026_09_27_000001_add_shop_id_to_reviews_and_backfill_shop_ownership.php
backend/database/migrations/2026_09_27_000002_scope_variant_sku_uniqueness_per_shop.php
backend/tests/Feature/Api/ShopOwnershipTest.php
```

**Modified (backend)**
```
app/Http/Controllers/Controller.php
app/Http/Controllers/Api/CheckoutController.php
app/Http/Controllers/Api/OrderController.php
app/Http/Controllers/Api/Admin/AdminCouponController.php
app/Http/Controllers/Api/Admin/AdminInventoryController.php
app/Http/Controllers/Api/Admin/AdminOrderController.php
app/Http/Controllers/Api/Admin/AdminProductController.php
app/Http/Controllers/Api/Admin/AdminReviewController.php
app/Http/Controllers/Api/Admin/AdminShippingMethodController.php
app/Http/Controllers/Api/Admin/AdminShopController.php
app/Http/Requests/AdminCouponRequest.php
app/Http/Requests/AdminProductRequest.php
app/Http/Requests/AdminShippingMethodRequest.php
app/Http/Resources/CouponResource.php
app/Http/Resources/InventoryResource.php
app/Http/Resources/InventoryTransactionResource.php
app/Http/Resources/OrderItemResource.php
app/Http/Resources/ProductDetailResource.php
app/Http/Resources/ProductResource.php
app/Http/Resources/ReviewResource.php
app/Http/Resources/ShippingMethodResource.php
app/Models/InventoryTransaction.php
app/Models/ProductVariant.php
app/Models/Review.php
app/Policies/InventoryPolicy.php
app/Policies/ProductPolicy.php
app/Providers/AppServiceProvider.php
app/Services/CheckoutService.php
app/Services/InventoryService.php
app/Services/OrderService.php
app/Services/ReviewService.php
database/factories/ProductFactory.php
```

**Modified (frontend)**
```
src/api/client.ts
src/main.ts
src/stores/auth.ts
```
