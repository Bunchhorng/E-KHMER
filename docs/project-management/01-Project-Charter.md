# 1. Project Charter

> One-paragraph definition of the project, its objectives, scope, and team.

## 1.1 Project Identity

| Field | Value |
|-------|-------|
| Project name | E-KHMER — Full-Stack E-Commerce Web Application |
| Version | 1.0 |
| Status | Draft / Approved |
| Prepared by | Project Manager |
| Date | 2026-09-28 |
| Base repo | `C:\All_Document\My_Project\E-Ecommerce` |

## 1.2 Background

The company owns an existing single-branch e-commerce codebase (Laravel REST API + Vue 3/TypeScript SPA, MySQL, Redis, Docker). This project turns that codebase into a production-quality, multi-branch marketplace managed by a 5-person team.

## 1.3 Objectives

1. Deliver all customer-webstore and admin-dashboard modules end-to-end with no mock data.
2. Implement multi-branch (multi-shop) ownership and authorization so branch data is fully isolated.
3. Pass QA, security, and performance gates (see `docs/QA-REPORT.md`, `docs/Performance-Report.md`).
4. Ship a documented, deployable release via Docker.

## 1.4 Scope

- **In scope:** 13 admin modules (Dashboard, Products, Categories, Brands, Variants, Inventory, Orders, Payments, Shipping, Customers, Coupons, Reviews, Notifications, Reports, Settings) + full customer website + shared infrastructure + documentation.
- **Out of scope (this phase):** real payment-gateway integration (mock/sandbox `PAYMENT_MODE`), payouts/commissions ledger, browser automation suite. See Decision Log.

## 1.5 Team

| Member | Role |
|--------|------|
| M1 | Project Lead · Authentication & User Management |
| M2 | Product, Category, Brand, Reviews, Media |
| M3 | Cart, Wishlist, Checkout, Orders, Coupons |
| M4 | Payment, Shipping, Inventory, Dashboard, Reports |
| M5 | Frontend Foundation, UI Integration, Testing |

Full breakdown: [`docs/TEAM-WORK-BREAKDOWN.md`](../TEAM-WORK-BREAKDOWN.md)

## 1.6 Success Criteria

- [ ] Full backend automated suite green (target ≥ 167 tests / 729 assertions)
- [ ] Frontend production build clean (`vue-tsc -b && vite build`)
- [ ] E2E critical-flow checklist signed off (see DoD §9 in the breakdown)
- [ ] All 10 management documents maintained with zero stale entries

## 1.7 Constraints

- PHP/npm/artisan/composer run **inside Docker only** (`docker compose exec app|frontend …`).
- Conventional commits; PRs must target `develop`; no secrets in the repo.

## 1.8 Approvals

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Project Manager | | | |
| Tech Lead | | | |
| Sponsor | | | |