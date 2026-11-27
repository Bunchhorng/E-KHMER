# E-KHMER Database Seeding & Factories Guide

This document explains how E-KHMER separates essential platform configuration from local/testing Cambodian marketplace demo data, how foreign-key dependencies are ordered, and how to safely seed or reset isolated environments.

---

## 1. Essential vs. Demo Seeding

E-KHMER separates safe baseline configuration from synthetic development fixtures using `app()->environment(['local', 'testing'])` guards:

| Class | Scope & Environment | Purpose |
| --- | --- | --- |
| `SettingSeeder` | **All environments** (including `production`) | Safely creates the 11 core application settings (`firstOrCreate`, never overwriting existing administrator edits). |
| `DatabaseSeeder` | **Entrypoint** | Runs `SettingSeeder` inside a database transaction, and invokes `DemoMarketplaceSeeder` **only** when `APP_ENV` is `local` or `testing`. Existing accounts, overrides, and tokens are preserved. |
| `DemoMarketplaceSeeder` | **`local` / `testing` only** | Orchestrates the complete Cambodian multi-vendor demo dataset in strict dependency order; throws `RuntimeException` in production. |
| `BusinessForecastSeeder` | **`local` / `testing` only** (opt-in standalone) | Seeds 4+ years of synthetic monthly/quarterly/yearly analytics fixtures (`DEMO-FC-GROWTH`, `DEMO-FC-STEADY`, `DEMO-FC-DECLINE`) with costed COGS and operating expenses. |

> **Production Safety:** Demo seeders (`DemoMarketplaceSeeder`, `UserSeeder`, `ShopSeeder`, `CatalogSeeder`, `OrderSeeder`, `ReviewSeeder`, `BusinessForecastSeeder`, etc.) reject execution outside `local` or `testing` environments so synthetic customers, fake orders, and demo reviews never enter production.

---

## 2. Seeder Execution Order (Foreign-Key Safe)

`DemoMarketplaceSeeder` runs seeders in the following parent-before-child order:

1. `SettingSeeder::seedDemoMetadata()` — Demo metadata keys (`demo.*`), separate from live platform settings.
2. `UserSeeder` — Core login accounts + 12 shop owners/managers + 49 additional Cambodian customer accounts.
3. `ShopSeeder` — 18 fictional Cambodian shops across Phnom Penh, Siem Reap, Battambang, Kampot, Kandal, Takeo, Kampong Cham, and Preah Sihanouk (including active, pending, suspended, and rejected statuses, plus Bakong test merchant config and GD-generated local logos/banners).
4. `AddressSeeder` — Cambodian delivery addresses (`KH`, Sangkat/Khan zones, `+855` phone numbers) including secondary work/family addresses for multi-address checkout testing.
5. `CatalogSeeder` (invokes `AttributeSeeder`) —
   - 7 multi-value filterable attributes (`color` with swatches, `size`, `weight-pack`, `material`, `storage`, `capacity`, `finish`)
   - 15 Cambodian/marketplace categories matching storefront icon slugs (`utensils`, `tractor`, `sparkles`, `shoppingbasket`, `shirt`, `gem`, `lampdesk`, `package`, `footprints`, `smartphone`, `flower2`, `cookingpot`, `bookopen`, `dumbbell`, `baby`)
   - 22 local & imported global brands
   - 250 distinct products (245 active, 5 inactive drafts) with bilingual Khmer/English titles/summaries, local GD PNG assets on the `public` disk, multi-variant attributes, low-stock and out-of-stock examples, and opening `InventoryTransaction` entries.
6. `ShippingMethodSeeder` — Standard and Express Cambodia courier methods per shop.
7. `CouponSeeder` — Platform-wide coupons (`WELCOME10`, `KHMERNEWYEAR`, `EKSAVE5`, `PHNOMPENH20`), shop-scoped coupons (`KAMPOT10`, `ANGKOR15`, `TECHHUB15`, `ROASTERY3`), plus boundary fixtures (`OLDPROMO2025` expired, `LIMITED50` limit reached, `DRAFTCOUPON` inactive).
8. `OrderSeeder` — 36 synthetic multi-scenario orders covering `pending`, `confirmed`, `processing`, `shipped`, `delivered`, and `cancelled`, single-shop and multi-shop `ShopOrder` splits, COD unpaid awaiting collection, COD collected (`PaymentTransaction` `type = collection`), Bakong paid & pending QR fixtures, all 4 `OrderReturn` stages (`requested`, `approved`, `received`, `refunded`), `PaymentRefund` rows, and 3 months of shop `BusinessExpense` rows.
9. `ReviewSeeder` — Verified-purchase reviews linked strictly to `delivered` orders and items actually purchased by that user (`unique(user_id, product_id)`), mixing `approved`, `pending`, and `rejected` moderation states, and recalculating `products.rating_avg` & `rating_count` via `ReviewService`.
10. `WishlistSeeder` — Curated wishlists for `customer@ekhmer.com` and 27 additional customers.
11. `CartSeeder` — Active multi-shop shopping cart (3 items across multiple shops) for `customer@ekhmer.com` plus 17 additional customer carts.
12. `NotificationSeeder` — Read and unread notifications for admin, seller, and customer accounts.
13. `EngagementSeeder` — Newsletter subscriptions and realistic open/closed Cambodian customer support inquiries.

---

## 3. Demo Test Accounts (Local / Testing Only)

All seeded demo accounts use password `password` and verified `email_verified_at` timestamps:

| Role / Access | Email | Notes |
| --- | --- | --- |
| Super Admin | `admin@ekhmer.com` | Full platform admin + owner of `E-KHMER Official Store` (`EKHMER`) |
| Demo Customer | `customer@ekhmer.com` | 8 multi-status orders, 3-item multi-shop cart, 6-item wishlist, 2 KH addresses |
| Demo Seller | `seller@ekhmer.com` | Owner of 4 active shops (`PPTECH`, `KAMPOT`, `ANGKOR`, `ROASTERY`) & manager on `EKHMER`, `MEKONG` |
| Dedicated Shop Owners | `owner.<shop>@ekhmer.test` | Fictional shop owners for remaining regional shops (`mekong`, `champa`, `bamboo`, `heritage`, etc.) |
| Demo Customers | `<name>@ekhmer.test` | 49 synthetic Cambodian buyer accounts with `+855` test numbers |

---

## 4. Refactored Model Factories

All 14 factories in `backend/database/factories/` use Cambodian-friendly defaults (`KH`, `+855`, USD, Phnom Penh / provincial hubs) and preserve compatibility with existing PHPUnit suites:

- `AddressFactory` (`->defaultAddress()`)
- `BrandFactory` (`->inactive()`)
- `BusinessExpenseFactory`
- `CartFactory` (`->forSession($id)`) & `CartItemFactory`
- `CategoryFactory` (`->inactive()`, `->withIcon($icon)`, `->childOf($parent)`)
- `CouponFactory` (`->fixed()`, `->forShop($shop)`, `->expired()`, `->notStarted()`, `->inactive()`, `->usageLimitReached()`)
- `InventoryFactory` (`->outOfStock()`, `->lowStock()`, `->reserved()`)
- `OrderFactory` (`->confirmed()`, `->processing()`, `->shipped()`, `->delivered()`, `->cancelled()`)
- `ProductFactory` (`->inactive()`, `->featured()`, `->forShop($shop)`, `->withVariant($price, $stock)`)
- `ProductVariantFactory` (`->withInventory($stock)`)
- `ShippingMethodFactory` (`->forShop($shop)`, `->free()`)
- `ShopFactory` (`->pending()`, `->suspended()`, `->rejected()`, `->closed()`, `->withBakong()`, `->default()`)
- `UserFactory` (`->unverified()`, `->admin()`)

---

## 5. Safe Commands (Always Run Inside Docker)

```bash
# Idempotent seed on local dev database (preserves existing accounts/settings)
docker compose exec app php artisan db:seed

# Seed only essential configuration (safe in any environment)
docker compose exec app php artisan db:seed --class=SettingSeeder --force

# Seed optional 4-year analytics/forecast history (local/testing only)
docker compose exec app php artisan db:seed --class=BusinessForecastSeeder

# Generate public storage symlink if media assets are missing over HTTP
docker compose exec app php artisan storage:link

# Validate seeders cleanly against isolated SQLite :memory: via PHPUnit
docker compose exec app php artisan test tests/Feature/DatabaseSeederTest.php tests/Feature/BusinessForecastSeederTest.php tests/Feature/SeededMarketplaceApiTest.php
```

> ⚠️ **Destructive reset warning:** Never run `php artisan migrate:fresh --seed` on a shared or populated database without backing up first. Only use `migrate:fresh --seed` on a disposable local container database when an intentional full reset is needed.
