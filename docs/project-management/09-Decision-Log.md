# 9. Decision Log

> Records important project decisions. Newest on top. Each row gets an ID referenced from minutes/reports/PRs.

| # | Date | Topic | Decision | Rationale | Decided by | Status | Related docs |
|---|------|-------|----------|-----------|------------|--------|--------------|
| D1 | 2026-09-25 | Branch model | Shops = physical branches of ONE brand, single `orders` + `order_items.shop_id` | Chosen with product owner; avoids sub-order split | Manager + owner | Decided | `docs/multi-shop-plan.md` |
| D2 | 2026-09-25 | Categories/brands | Stay GLOBAL across branches | Decision taken during multi-shop Q&A | Manager | Decided | `docs/multi-shop-plan.md` |
| D3 | 2026-09-28 | P1 scope | Fix only broken data-integrity/authz/session items; P2/P3 deferred | P1 selected by manager | Manager | Decided | `docs/multi-shop-audit-and-p1-report.md` |
| D4 | 2026-09-28 | Merchant surface | `/shop/*` path for merchant area; public branch page to move to `/stores/*` | `/shop` already used by public page | Manager | Open (blocked) | `docs/multi-shop-audit-and-p1-report.md` |
| D5 | 2026-09-28 | Stock semantics | Inventory ownership derived from variant→product, never trusted from request | Silent-corruption bugs found in P1 | Manager | Decided | P1 report §2.1 |
| D6 | 2026-09-28 | Payment | `PAYMENT_MODE` sandbox default; production rejects online confirm | No real gateway in scope | Manager + M4 | Decided | `docs/QA-REPORT.md` HIGH-02 |
| — | (add) | | | | | Proposed / Decided / Rejected / Deferred | |

## Open decisions awaiting input

| # | Topic | Owner | Needed by | Options | Ref |
|---|-------|-------|-----------|---------|-----|
| D4a | Public branch page URL | M1 | M4 due | `/stores/*` vs other | D4 |