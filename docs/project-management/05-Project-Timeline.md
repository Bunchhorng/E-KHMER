# 5. Project Timeline

> Deadlines and milestones. Baseline: 6 weeks (26 Aug 2026 → 09 Oct 2026 plan window); adjust as sprints complete.

## 5.1 Milestones

| Milestone | Target date | Owner | Deliverable | Status |
|-----------|-------------|-------|-------------|--------|
| M1 — Project setup & skeleton | Week 1 | M1 + M5 | Docker healthy, `develop` branch, design system, CI | Planned |
| M2 — Database complete | Week 2 | M1–M4 | All migrations + models, migrate-from-scratch green | Planned |
| M3 — Auth complete | Week 2 | M1 | Auth/account/customers endpoints + pages | Planned |
| M4 — Backend APIs done | Week 4 | M2/M3/M4 | Catalog, cart/checkout/orders/coupons, payment/shipping/inventory endpoints tested | Planned |
| M5 — Admin dashboard live | Week 5 | all | All admin pages on real APIs, no mocks | Planned |
| M6 — Customer website live | Week 5 | M5 | Storefront critical flow works E2E | Planned |
| M7 — QA + hardening | Week 6 | all / M5 | Full test suite + security/perf gates + E2E checklist | Planned |
| M8 — Release | Week 6 | M1 | `develop` → `main`, docs final, demo | Planned |

## 5.2 Week-by-week

| Week | Dates | Focus | Owner |
|------|-------|-------|-------|
| 1 | (set) | Setup, DB design sign-off | M1/M5, all |
| 2 | (set) | Migrations, auth, catalog start | M1, M2 |
| 3 | (set) | Catalog, cart/checkout start, payment/inventory start | M2, M3, M4 |
| 4 | (set) | Backend APIs complete, admin pages start | all |
| 5 | (set) | Admin + storefront integration, checkout integration | all |
| 6 | (set) | QA, E2E, release | M5, M1 |

## 5.3 Deadlines per member

| Member | Deadline | Must ship by (DoD) |
|--------|----------|---------------------|
| M1 | Week 2 | Auth + account + customers + settings |
| M2 | Week 4 | Catalog + reviews + media APIs |
| M3 | Week 4 | Cart/checkout/orders/coupon APIs + tests |
| M4 | Week 4 | Inventory/payment/shipping APIs + reports |
| M5 | Week 5 | UI foundation + storefront integration |
| All | Week 6 | DoD passed, merged to `develop` |

> Progress details in `07-Progress-Reports/`. Any missed deadline → add an issue/risk in `08-Issue-Risk-Register.md`.