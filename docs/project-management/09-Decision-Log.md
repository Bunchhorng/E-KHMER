# 9. Decision Log

> Records important project decisions. Newest on top. Each row gets an ID referenced from minutes/reports/PRs.

| # | Date | Topic | Decision | Rationale | Decided by | Status | Related docs |
|---|------|-------|----------|-----------|------------|--------|--------------|
| D1 | 2026-09-28 | Scope | Maintain the customer storefront and administrative e-commerce system. | Keeps delivery aligned with the approved project architecture. | Manager | Decided | `docs/ARCHITECTURE.md` |
| D5 | 2026-09-28 | Stock semantics | Inventory ownership derived from variant→product, never trusted from request | Silent-corruption bugs found in P1 | Manager | Decided | P1 report §2.1 |
| D6 | 2026-09-28 | Payment | `PAYMENT_MODE` sandbox default; production rejects online confirm | No real gateway in scope | Manager + M4 | Decided | `docs/QA-REPORT.md` HIGH-02 |
| — | (add) | | | | | Proposed / Decided / Rejected / Deferred | |

## Open decisions awaiting input

| # | Topic | Owner | Needed by | Options | Ref |
|---|-------|-------|-----------|---------|-----|
| — | — | — | — | — | — |
