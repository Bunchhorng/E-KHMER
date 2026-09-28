# 3. Team Member List

> Names, roles, responsibilities, and contact info. Edit with real data as the team is formed.

## 3.1 Members

| ID | Name | Role | Email | GitHub | Branch prefix | Main responsibility |
|----|------|------|-------|--------|---------------|---------------------|
| M1 | (fill) | Project Lead / Backend | | | `feature/auth-*`, `feature/org-*` | Lead, auth, users, customers, notifications, settings, shops; resolves conflicts |
| M2 | (fill) | Backend / Frontend | | | `feature/catalog-*` | Products, categories, brands, variants, media, reviews |
| M3 | (fill) | Backend / Frontend | | | `feature/cart-*`, `feature/order-*`, `feature/coupon-*` | Cart, wishlist, checkout, orders, coupons |
| M4 | (fill) | Backend / Frontend | | | `feature/payment-*`, `feature/fulfill-*`, `feature/report-*` | Payments, shipping, inventory, dashboard, reports |
| M5 | (fill) | Frontend / QA | | | `feature/ui-*` | Layouts, shared components, router, i18n, theme, E2E testing |

## 3.2 Responsibilities matrix

| Area | Owner | Backup |
|------|-------|--------|
| Docker / CI / environment | M1 | M5 |
| `routes/api.php` ownership sections | M1 (lead) | — |
| `frontend/src/router/index.ts` | M5 | M1 |
| `docs/API-REFERENCE.md` contract | all (own PRs) | M1 |
| Migrations (per table) | table owner | owner only |

## 3.3 Onboarding checklist

- [ ] Access to repo (read/write) + Docker Desktop
- [ ] `docker compose up -d` works locally
- [ ] Read `docs/DEVELOPMENT.md` (Docker-only rule) + `AGENTS.md`
- [ ] Read module ownership in `docs/TEAM-WORK-BREAKDOWN.md`
- [ ] Branch naming + commit format agreed