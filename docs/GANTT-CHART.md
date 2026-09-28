# Gantt Chart — Multi-Branch E-Commerce Web Application

> **Project:** E-KHMER — Multi-Branch E-Commerce Web Application
> **Timeline:** 26 Aug 2026 → 28 Sep 2026 (**5 weeks**, 26 working days)
> **Source:** reconstructed from the actual repository — `git log` (26 commits,
> 10 active dates, 458 unique files touched) and the dated migration filenames in
> `backend/database/migrations/`.

---

## 1. Phase Summary

| # | Phase | Dates | Working days | Commits | Primary output |
|---:|---|---|---:|---:|---|
| 1 | Project setup & architecture planning | 26–31 Aug | 4 | 7 | Repo, `AGENTS.md`, compose file |
| 2 | Database design & models | 31 Aug – 1 Sep | 2 | — | 15 tables, 12 models, factories, seeders |
| 3 | Backend API layer | 31 Aug – 6 Sep | 5 | — | 31 controllers, 10 services, 25 resources, 22 form requests |
| 4 | Frontend (storefront / account / admin) | 31 Aug – 14 Sep | 10 | — | 44 views, 24 components, 4 layouts, 51 routes |
| 5 | Theming & i18n | 5–12 Sep | 5 | 3 | 10-token light/dark system, EN + KM i18n |
| 6 | Integration, audit & hardening | 6–12 Sep | 5 | 8 | Checkout, reviews, settings, tracking fixes |
| 7 | **QA pass** | 24 Sep | 1 | 1 | 1 critical + 5 high + 13 med + 12 low findings |
| 8 | **Performance optimisation** | 25 Sep | 1 | 1 | OPcache, gzip, Redis → 5–11 ms warm |
| 9 | **Multi-branch (multi-shop)** | 25–28 Sep | 2 | 2 | 10 migrations, 8 policies, 9 endpoints |
| 10 | Documentation & presentation | 14–28 Sep | 8 | — | ARCHITECTURE, API-REFERENCE, QA + Perf reports, deck |
| | **Total** | **26 Aug – 28 Sep** | **26** | **26** | **73 Vue files · 30 models · 44 migrations · 115 routes** |

**Actual commit distribution**

| Date | Commits | Focus |
|---|---:|---|
| 2026-08-26 | 1 | first commit |
| 2026-08-31 | 6 | schema, models, seeders, API, tests, agent guidelines |
| 2026-09-05 | 6 | reports, PDFs, settings, email verification, uploads, theming |
| 2026-09-06 | 2 | checkout / reviews / settings / tracking audit fixes |
| 2026-09-07 | 1 | fixes |
| 2026-09-12 | 4 | avatar upload, address & form UI revamp, full-page forms |
| 2026-09-14 | 2 | UI prompt file, updates |
| 2026-09-24 | 1 | QA hardening: checkout, orders, inventory, auth, validation |
| 2026-09-25 | 2 | performance + multi-shop branch foundation |
| 2026-09-28 | 1 | per-branch ownership & authorization |

---

## 2. Gantt Chart

**Weeks of 26 Aug – 25 Sep 2026 · one column per week**

```
Phase                              W1              W2              W3              W4              W5
                             26-30 Aug      31 Aug-4 Sep   5-11 Sep       12-18 Sep      19-25 Sep      26-28 Sep
                             Mon    Fri     Mon    Fri     Mon    Fri     Mon    Fri     Mon    Fri     Mon  Wed
                          ┌────────────┬────────────┬────────────┬────────────┬────────────┬─────────┐
 1  Setup & architecture   │████████████│            │            │            │            │         │
    repo · compose · plan │████████████│            │            │            │            │         │
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
 2  Database & models      │            │██████      │            │            │            │         │
    15 tables · 12 models  │            │██████      │            │            │            │         │
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
 3  Backend API layer      │            │████████████│████████████│            │            │         │
    31 ctrl · 10 services  │            │████████████│████████████│            │            │         │
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
 4  Frontend               │            │████████████│████████████│████████████│            │         │
    44 views · 51 routes   │            │████████████│████████████│████████████│            │         │
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
 5  Theming & i18n         │            │            │████████████│████████████│            │         │
    10 tokens · EN + KM    │            │            │████████████│████████████│            │         │
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
 6  Integration & audit    │            │            │████████████│████████████│            │         │
    checkout · reviews     │            │            │████████████│████████████│            │         │
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
 7  QA pass                │            │            │            │            │▓▓▓▓▓▓▓▓▓▓▓│         │
    1 crit · 5 high        │            │            │            │            │▓▓▓▓▓▓▓▓▓▓▓│         │
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
 8  Performance            │            │            │            │            │▓▓▓▓▓▓▓▓▓▓▓│         │
    9.6s → 7ms             │            │            │            │            │▓▓▓▓▓▓▓▓▓▓▓│         │
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
 9  Multi-branch (NEW)     │            │            │            │            │▓▓▓▓▓▓▓▓▓▓▓│█████████│
    10 migr · 8 policies   │            │            │            │            │▓▓▓▓▓▓▓▓▓▓▓│█████████│
                          ├────────────┼────────────┼────────────┼────────────┼────────────┼─────────┤
10  Docs & presentation    │            │            │            │ ░░░░░░░░░░ │░░░░░░░░░░░│█████████│
    reports · deck         │            │            │            │ ░░░░░░░░░░░ │░░░░░░░░░░░│█████████│
                          └────────────┴────────────┴────────────┴────────────┴────────────┴─────────┘
                             ████ build   ▓▓▓▓ quality/audit   ░░░░ docs/presentation

 ── milestones ──
 26 Aug  M1  Repo + architecture plan agreed
 31 Aug  M2  Schema + models + seeders complete          ─┐ 6 commits, 1 day — the
 05 Sep  M3  API + frontend MVP (storefront, cart,       │ heaviest single day
           checkout, orders) end-to-end                    │ in the project
 12 Sep  M4  Full feature set + theming + i18n complete  ─┘
 24 Sep  M5  QA pass complete — 19 findings logged
 25 Sep  M6  Performance target met — 5–11 ms warm
 28 Sep  M7  Multi-branch ownership + authorisation done
```

---

## 3. Phase Detail

### Phase 1 — Setup & Architecture Planning
**26–31 Aug 2026 · 4 days · 7 commits**

| Deliverable | Evidence |
|---|---|
| Repository, branching, agent guidelines | `AGENTS.md` (26–31 Aug) |
| Container topology | `docker-compose.yml` — 7 services |
| Nginx + PHP images | `docker/php/Dockerfile`, `docker/nginx/default.conf` |
| Architecture decision record | `docs/ARCHITECTURE.md` |

**Decisions locked here:** Laravel REST API decoupled from a Vue SPA; Nginx as the
sole public API port; MySQL for transactional data and Redis for cache/session;
strict `routes → controller → request → service → model` backend layering.

---

### Phase 2 — Database Design & Models
**31 Aug – 1 Sep 2026 · 2 days**

| Deliverable | Count | Evidence |
|---|---:|---|
| Migrations | 15 | `2026_08_31_000001…000015` |
| Eloquent models | 12 | `User`, `Product`, `ProductVariant`, `Order`, `Cart`, `Coupon`, … |
| Model factories | 12 | `database/factories` |
| Seeders | 8 | `User`, `Category`, `Brand`, `Attribute`, `Product`, `ShippingMethod`, `Coupon`, `Order` |

**Key design:** the variant **EAV** model — `attributes → attribute_values →
variant_attribute_values ← product_variants`, so a variant is resolved by its
attribute-value combination. Seeded: 6 categories, 7 brands, 12 products,
2 attributes (13 colour values, 7 size values), 3 shipping methods, 5 coupons.

---

### Phase 3 — Backend API Layer
**31 Aug – 6 Sep 2026 · 5 days**

| Deliverable | Count | Notes |
|---|---:|---|
| Controllers | 16 customer + 15 admin | thin; delegate to services |
| Services | 10 | `Checkout`, `Inventory`, `Order`, `Coupon`, `Review`, `Catalog`, `Cart`, `Dashboard`, `MediaUpload`, `OrderNumberGenerator` |
| Form Requests | 22 | all validation here, none inline |
| API Resources | 25 | one JSON shape per model |
| Policies | 6 (grew to 8) | `Product`, `Order`, `Inventory`, `Review`, `OrderItem`, `Shop` |
| Middleware | 1 | `EnsureUserIsAdmin` aliased `admin` |
| Auth | Sanctum | register, login, logout, `me`, email verification, password reset |

**Business rules implemented:** inventory reserve/deduct/release ledger ·
15-minute reservation lock · order state machine · coupon validation cascade ·
verified-purchase review gate · guest carts via `X-Session-Id`.

---

### Phase 4 — Frontend
**31 Aug – 14 Sep 2026 · 10 days**

| Deliverable | Count |
|---|---:|
| Views | 44 (7 storefront · 10 account · 22 admin · 5 auth) |
| Components | 24 (19 root · `AdminDataTable` · 5 chart wrappers) |
| Layout shells | 4 (Store · Account · Admin · Auth) |
| Routes | 51, **all lazy-loaded** |
| Pinia stores | 6 (`auth`, `cart`, `wishlist`, `theme`, `locale`, `ui`) |
| API modules | 18 |
| Total SFC code | 73 files · ~12,300 lines |

**Structure:** one Axios client with a bearer-token request interceptor and a
401 response interceptor that clears the session and redirects with the original
destination preserved. Route guards declared on the route via `meta`
(`requiresAuth`, `admin`, `guestOnly`) rather than scattered through components.

---

### Phase 5 — Theming & Internationalisation
**5–12 Sep 2026 · 5 days · 3 commits**

| Deliverable | Detail |
|---|---|
| Token system | 10 CSS variables (`src/style.css:6-32`) mapped in `tailwind.config.js` |
| Dark mode | class-based, FOUC-safe via a blocking inline script in `index.html` |
| Reusable UI classes | 22 in `@layer components` |
| i18n | `vue-i18n`, **19 namespaces, ~1,035 keys**, `en` + `km` |
| Khmer typography | `html.font-khmer-mode` → Kantumruy Pro at `line-height: 1.75`, fixing clipped diacritics |
| Locale store | persisted to `localStorage`; also drives the font class |

**The outcome worth noting:** because tokens are semantic (`surface`, `ink`,
`muted`) rather than raw hex, dark mode cost **10 changed variable values** — not
44 rewritten views. The 530 `dark:` utilities that exist are hover/border
refinements, not re-theming.

---

### Phase 6 — Integration, Audit & Hardening
**6–12 Sep 2026 · 5 days · 8 commits**

| Commit | Scope |
|---|---|
| `feat(fullstack): complete audit fixes for checkout, reviews, settings, and tracking` | cross-cutting |
| `feat: email verification, image uploads, backend cart, and API wiring` | auth + media |
| `feat: admin reports, order PDF receipts, settings` | reporting |
| `feat(profile): avatar upload, redesigned profile, dashboard i18n and theme symmetry` | account |
| `style(addresses): revamp address UI for laptop with dark-mode modal symmetry` | responsive |
| `feat(admin): convert category, brand, coupon, and shipping forms to full-page views` | admin UX |
| `feat(account): convert address form to full-page view without modal` | account UX |

**Why this phase exists:** long forms inside a modal became unusable on a laptop
screen, so the four admin entity forms and the address form were converted to
dedicated full pages. This is a UX finding from real use, not a planned feature.

---

### Phase 7 — QA Pass
**24 Sep 2026 · 1 day · 1 commit** → `docs/QA-REPORT.md`

| Severity | Found | Fixed | Deferred |
|---|---:|---:|---:|
| **Critical** | 1 | **1** | 0 |
| **High** | 5 | **5** | 0 |
| Medium | 13 | — | 13 |
| Low | 12 | — | 12 |
| Security | 7 | reviewed | — |

**Method:** Docker environment + automated suite + live API probes with real order
numbers + full source review + frontend production build.

**The critical finding** — cancelling a **paid** order corrupted the payment
state, lost the stock, and wrote no refund record. Reproduced live against order
`SV-2026-000065`. All critical and high fixes are locked behind **10 regression
tests** in `QaHotfixTest.php`.

**The high findings:** admin cancel leaked reservations · client-trusted payment
confirmation · a plaintext seeded admin hash produced a 500 instead of a 401 ·
negative variant price/quantity produced a 500 with a SQL error leaked in the
message · coupon usage was never reclaimed on cancel.

---

### Phase 8 — Performance Optimisation
**25 Sep 2026 · 1 day · 1 commit** → `docs/Performance-Report.md`

| Endpoint | Cold | Warm | Gain |
|---|---:|---:|---|
| `GET /api/categories` | ~4.7 s | **6–8 ms** | ~600× |
| `GET /api/brands` | ~9.6 s | **6–7 ms** | ~1300× |
| `GET /api/shipping-methods` | ~5.2 s | **5–6 ms** | ~900× |
| `GET /api/catalog/featured` | ~5.1 s | **6–11 ms** | ✔ |
| `GET /api/catalog/facets` | ~3.9 s | **8 ms** | ✔ |
| `GET /api/admin/inventory` | **HTTP 500** | **200 OK** | bug fixed |

**Actions, by impact:** OPcache enabled via a mounted ini (dominant TTFB cost) ·
Nginx gzip for CSS/JS/JSON/SVG/XML/PDF · Redis caching of stable public reference
data only, with `Cache::forget` invalidation in the three mutating admin
controllers · composite index on `order_items (product_id, product_variant_id)`.

**Deliberate exclusion:** cart, orders, checkout and reports are **not** cached —
stale money is worse than slow money.

---

### Phase 9 — Multi-Branch Architecture
**25–28 Sep 2026 · 2 days · 2 commits**

> This is the **scope change** of the project. Originally a single-store system;
> multi-branch was added in the final week, on top of a working system.

| Date | Commit |
|---|---|
| 2026-09-25 | `feat(backend): add multi-shop branch foundation with per-branch inventory` |
| 2026-09-28 | `fix(backend): enforce per-branch shop ownership and authorization` |

| Deliverable | Count | Detail |
|---|---:|---|
| Migrations | 10 | `shops`, `shop_users`, then `shop_id` on products · order_items · inventories · inventory_transactions · shipping_methods · coupons · reviews |
| Index changes | 1 | `product_variants.sku` uniqueness rescoped from global to `(shop_id, sku)` |
| Backfill command | 1 | `shop:assign-default` — non-destructive; **no data deleted** |
| New policies | 4 | `Coupon`, `Order`, `Review`, `ShippingMethod` (total now 8) |
| New endpoints | 9 | 3 public `/api/shops*` + 6 admin `/api/admin/shops*` |
| Tests | 36 | `ShopTest` (19) + `ShopOwnershipTest` (16) + 1 in the console suite |

**Suite growth proving the change was safe:** 132 tests / 626 assertions before
multi-branch → **167 tests / 729 assertions** after, all green.

**Known limitation, recorded at the time:** the `admin` middleware still admits
only `role = admin`, so branch staff cannot yet authenticate. The policies are
enforced but the branch staff interface is not built.

---

### Phase 10 — Documentation & Presentation
**14–28 Sep 2026 · 8 days**

| Artefact | Purpose | Status |
|---|---|---|
| `ARCHITECTURE.md` | layered design, ER diagram, business rules | complete |
| `API-REFERENCE.md` | 981 lines generated from the **live** backend | complete |
| `API.md` | hand-written endpoint overview | complete |
| `QA-REPORT.md` | 19 findings with live reproductions | complete |
| `Performance-Report.md` | measured before/after timings | complete |
| `multi-shop-plan.md` | the branch design decision record | superseded by the audit report |
| `multi-shop-audit-and-p1-report.md` | implemented-vs-planned status | current |
| `FIGMA-PROMPTS.md` | UI prototype specification | reference |
| `presentation-deck.md` | 21-slide defence deck | current |
| `presentation-ux-ui-slides.md` | 19-slide UX/UI deck | current |

**Doc-drift finding worth noting:** the QA pass recorded that `AGENTS.md`,
`README.md` and `ARCHITECTURE.md` all specified **Bootstrap 5**, while the project
actually uses **Tailwind CSS 3.4.19** and **Chart.js**. It is still recorded as an
open low-severity item.

---

## 4. Cumulative Deliverables

| By | Backend | Frontend | Tests | Docs |
|---|---|---|---|---|
| **31 Aug** | 15 tables, 12 models, 8 seeders | — | — | `AGENTS.md`, `ARCHITECTURE.md` |
| **06 Sep** | 31 controllers, 10 services, 22 requests, 25 resources, 6 policies | MVP storefront, cart, checkout, orders | initial feature suite | — |
| **12 Sep** | + email verification, media, settings | 44 views, 4 shells, 51 routes, 6 stores | + account & settings | `API.md` |
| **25 Sep** | + reports, PDF receipts | + admin dashboards, charts | 132 / 626 | `QA-REPORT.md`, `Performance-Report.md` |
| **28 Sep** | + `shops`, `shop_users`, 8 policies, 9 shop endpoints | unchanged | **167 / 729** | audit report, decks |

**Final state:** 30 models · 44 migrations · 25 API resources · 22 form requests ·
8 policies · 10 services · 115 API routes (63 admin) · 73 Vue SFCs (~12,300
lines) · 44 views · 24 components · 51 frontend routes · 6 Pinia stores ·
**167 tests / 729 assertions passing**.

---

## 5. Milestone Summary

| ID | Milestone | Date | Criterion |
|---|---|---|---|
| **M1** | Architecture plan agreed | 26 Aug 2026 | repo, compose topology, layering ADR written |
| **M2** | Schema & models complete | 31 Aug 2026 | 15 tables migrated, 12 models, seeders run |
| **M3** | End-to-end MVP | 05 Sep 2026 | browse → cart → checkout → order works |
| **M4** | Full feature set + theming + i18n | 12 Sep 2026 | 44 views, dark mode, EN/KM complete |
| **M5** | QA pass complete | 24 Sep 2026 | 19 findings logged; all critical + high fixed |
| **M6** | Performance target met | 25 Sep 2026 | public endpoints 5–11 ms warm |
| **M7** | Multi-branch complete | 28 Sep 2026 | branch isolation enforced; 167/729 green |

---

## 6. Honest Timeline Notes

State these if a supervisor asks about the schedule — they are defensible answers.

| Observation | Explanation |
|---|---|
| **Only 10 of 25 working days have commits** | The repository tracks finalised work, not daily progress. 26 commits across 458 files, not 26 days of output. |
| **Week 3 was the heaviest** (6 Sep) | Reports, PDFs, settings, email verification, uploads, and the theme revamp all landed in one commit batch. |
| **Week 4 (13–18 Sep) is nearly empty** | A gap before the QA pass. `docs/QA.md` records the schedule slipping. |
| **Multi-branch was a scope change, not a plan** | The original spec (`AGENTS.md`, `docs/multiple-shops.md`) was a single-store system. Multi-branch was added in the final 2 days on top of a working codebase. `multi-shop-plan.md` is dated 25 Sep, one day before the work started. |
| **10 migrations in 2 days** | Possible because the design was additive: nullable `shop_id` columns, then a backfill command. The harder day was 28 Sep, enforcing ownership and authorisation (16 tests). |
| **The project did not stay "as planned"** | It is a single-store system plus a 2-day multi-branch layer, not a multi-branch system designed up front. Say so rather than presenting the branch design as the original plan. |

---

## Appendix — Reproducing This Chart

```powershell
# commit history, oldest first, with dates
git log --date=short --pretty=format:"%ad|%s" --reverse

# commits per active date
git log --date=short --pretty=format:"%ad" | Group-Object | Sort-Object Name

# unique files touched across the project
git log --name-only --pretty=format:"" | Where-Object { $_ -ne "" } | Sort-Object -Unique

# migration dates (the most reliable phase evidence)
Get-ChildItem backend\database\migrations | Sort-Object Name
```

**Why `git log` under-reports the duration:** 26 commits in 10 distinct dates does
not mean 26 days of work — it means 26 finalised change sets. The phases above
span the full 26 Aug → 28 Sep window because the schedule, not the commit
frequency, defines the timeline.
