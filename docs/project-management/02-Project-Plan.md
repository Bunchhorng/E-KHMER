# 2. Project Plan

> Overall development plan: phases, approach, and links to the detailed breakdown.

## 2.1 Approach

- **Backend-first, feature-branch per module**, one owner per module (see `docs/TEAM-WORK-BREAKDOWN.md`).
- Contract-first API development: `docs/API-REFERENCE.md` + `frontend/src/types/index.ts` updated in the same PR as the backend change.
- Milestones reviewed each week; plan updated after every sprint.

## 2.2 Phases (10)

| Phase | Name | Duration | Lead | Exit criterion |
|----|----|----|----|----|
| 1 | Project setup (Docker, CI, skeleton, design system) | Week 1 | M1 + M5 | `docker compose up` healthy; `develop` ready |
| 2 | Database (migrations + models) | Weeks 1–2 | M1–M4 | `php artisan migrate` green from scratch |
| 3 | Authentication + RBAC | Week 2 | M1 | Register/login/401/403 matrix passing |
| 4 | Backend APIs (catalog / cart-checkout-orders / payment-shipping-inventory) | Weeks 2–4 | M2 / M3 / M4 in parallel | API-REFERENCE up to date, tests green |
| 5 | Admin Dashboard (wired to real APIs) | Weeks 4–5 | all (owner per module) | No mock data remaining |
| 6 | Customer Website | Weeks 4–5 | M5 (+ M2/M3) | Critical storefront flow works |
| 7 | Cart & Checkout integration | Week 5 | M3 + M5 | Reservation lock + stepper verified |
| 8 | Payment & Shipping | Week 5 | M4 | Payment gate enforced; shipments tracked |
| 9 | Testing (backend suite + E2E + perf/security) | Weeks 5–6 | all; M5 leads | Full suite + checklist green |
| 10 | Deployment + final docs | Week 6 | M1 | `develop` → `main`; demo ready |

## 2.3 Key references

- Work breakdown (modules, ownership, git rules): `../TEAM-WORK-BREAKDOWN.md`
- Architecture: `../ARCHITECTURE.md` · Frontend: `../FRONTEND.md` · API live ref: `../API-REFERENCE.md`
- Development workflow (Docker/git): `../DEVELOPMENT.md` · QA baseline: `../QA-REPORT.md`

## 2.4 Plan changes

| Date | Change | Reason | Decision ref |
|------|--------|--------|--------------|
| — | — | — | `09-Decision-Log.md` |