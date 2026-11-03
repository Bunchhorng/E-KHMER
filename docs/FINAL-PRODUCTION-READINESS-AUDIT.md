# E-KHMER - Final Production Readiness Audit

**Audit date:** 2026-11-03 (Asia/Bangkok)  
**Audited revision:** `2471f5da416d943b4fca910b5c896ccc0a59e728` on `dev`  
**Decision:** **NOT PRODUCTION READY**

## 1. Executive Summary

The application has substantial production-oriented implementation coverage: containerized Laravel/Vue services, MySQL/Redis runtime checks, queue/scheduler heartbeats, isolated MySQL concurrency tests, a successful production frontend build, dependency audits, and a local backup/restore verification.

It is not eligible for launch. The full Laravel suite currently has **14 failures**, the full browser suite exits non-zero with **2 final failures**, and no off-host production backup/restore operating model or live external-service acceptance evidence is available. These are release gates, not cosmetic findings.

## 2. Architecture & Deployment Review

| Area | Result | Evidence |
|---|---|---|
| Runtime topology | PASS | Laravel app, worker, scheduler, MySQL, Redis, Nginx/frontend development services; production Compose puts MySQL and Redis on internal networks and exposes Caddy on 80/443 only. |
| Production build path | PASS | Multi-stage production Dockerfile uses `npm ci`, built assets, Composer `--no-dev`, and disables app debug by production configuration. |
| Operational workers | PASS (local) | Worker, scheduler and heartbeat checks were running; `ops:health --require-heartbeats` passed. |
| Live deployment/DNS/TLS | NOT VERIFIED | No deployed production environment, production secrets, domain, certificate, or external health endpoint was supplied. |
| Container hardening | P2 | Production containers do not declare a non-root `USER`. |

## 3. Automated Test Results

| Check | Result | Details |
|---|---|---|
| Laravel complete suite | **FAIL** | `14 failed, 766 passed (5,752 assertions)` in `318.68s`. Failures are in `BusinessForecastApiTest` and `BusinessForecastSeederTest`. |
| MySQL checkout/idempotency concurrency | PASS | `CheckoutConcurrencyTest`: `11 passed (190 assertions)` in `62.74s`, using isolated MySQL + Redis. |
| MySQL order-number concurrency | PASS | `OrderNumberConcurrencyTest`: `3 passed (156 assertions)` in `42.09s`, using isolated MySQL. |
| Vue type-check + production build | PASS | `vue-tsc -b && vite build`; 2,375 modules transformed; built in 15.18s. |
| Playwright full suite | **FAIL** | Command exited `1`. Planned 394 tests; logs/artifacts show 2 final desktop failures and 3 first-attempt flaky cases that passed on retry. One mobile-only desktop case was skipped. |
| Dependency vulnerability audits | PASS | `npm audit --omit=dev --audit-level=moderate --package-lock-only`: 0 vulnerabilities. `composer audit --no-dev`: no advisories. |
| Composer platform requirements | PASS | All required PHP extensions/packages satisfied in the app container. |

The default Laravel suite uses SQLite in-memory storage, array cache/mail/session, and synchronous queue settings. The targeted checkout and order-number tests were therefore run separately against disposable MySQL/Redis services to cover database locking behavior.

## 4. Backend Module Verification

### F01–F18 functional modules

| Area | Result | Notes |
|---|---|---|
| F01 Authentication and account recovery | PASS | Sanctum token lifecycle, verification and password reset flows are covered. This is Sanctum personal-token auth, not the JWT scheme described in the planning notes. |
| F02 Catalog, search, variants, inventory | PASS | Eloquent-backed catalog and stock flows reviewed; inventory race coverage passes on MySQL. |
| F03 Cart and checkout | PASS | Server-calculated checkout totals, lock-based stock reservation, idempotency and expiry paths are implemented and the MySQL concurrency suite passes. |
| F04 COD collection | PASS | Privileged collection state changes and payment synchronization are implemented and covered by regression tests. |
| F05 Bakong/KHQR | PASS (local implementation) | Per-shop receiver/amount/reference validation and duplicate transaction prevention are implemented. Real provider acceptance is not verified. |
| F06–F08 orders, fulfilment, returns/refunds | PASS (code/regression evidence) | Snapshotting, status controls, return/refund records and ledger paths are present. Real courier/refund operation is not verified. |
| F09–F12 shop, admin, RBAC and tenancy | PASS | Shop membership and shop-scoped authorization are implemented; cross-shop tests exist. |
| F13 checkout race resistance | PASS | Dedicated MySQL/Redis suite passed with stock=1 race, idempotency and rate/limit coverage. |
| F14 order numbers | PASS | Dedicated MySQL concurrency suite passed. |
| F15 reports and forecasts | **FAIL** | Forecast/report regression tests are currently red; do not approve finance/reporting release behavior. |
| F16 rich descriptions/XSS | PASS | Product description sanitization is used; browser malicious-markup coverage passed. |
| F17–F18 responsive/scanner UI | PASS by targeted browser cases | Barcode/camera desktop and mobile cases completed successfully; the overall browser gate remains red for unrelated account-heading cases. |

### Forecast failure diagnosis

The failures are reproducible in the complete suite and produce stale/zero forecast summaries where fixture data are expected. The likely mechanism is test cache isolation: `BusinessForecastService` caches by revision/date/options, while its model-event invalidation is registered through `DB::afterCommit()`. Transactional test work is rolled back rather than committed, so cached results may survive between tests. This is a diagnosis, not a waiver: add deterministic cache isolation/invalidation tests and make the complete suite green before release.

## 5. Frontend Verification

The production Vue build and TypeScript project build passed. Browser coverage exercised customer, seller, admin, payment, localization, responsive, and camera/barcode paths.

The final failing Playwright tests are both desktop cases in `frontend/tests/e2e/account-ui.spec.ts` for English and Khmer. They expected exactly one `main h1` and saw none within five seconds; snapshots later showed the Dashboard heading. Three additional dashboard/oversight scenarios passed only after retry, indicating route/data-settling flakiness. Treat this as a P1 CI/release-quality issue until the tests and their waiting contract are made deterministic.

## 6. Security Review

| Control | Result | Notes |
|---|---|---|
| Authentication/authorization | PASS (source and tests) | Sanctum, policies, active shop memberships, and server-side shop scope enforcement are in use. |
| SQL injection | PASS (source review) | Request-controlled sorting uses a fixed whitelist; no unsafe raw request SQL was found. This is not a penetration test. |
| Stored XSS | PASS (source + browser coverage) | Rich product HTML is sanitized before rendering; the limited `v-html` use is tied to sanitized content. |
| Secrets scan | PASS | No application secret was found in tracked application/config source. Environment files were not read or exposed. |
| Dependency vulnerabilities | PASS | Production dependency audits returned no advisory. |
| CORS | N/A for supplied production topology | Production uses same-origin `/api`; a separate-origin deployment must add and test an explicit CORS policy. |
| Clickjacking/CSP | P2 | Caddy sets HSTS, `X-Content-Type-Options`, referrer policy and permissions policy, but no `X-Frame-Options` or CSP `frame-ancestors` was found. |
| Guest capability lifetime | P2 | Guest order access relies on a high-entropy browser-held session capability; introduce server-side expiry/revocation policy. |

## 7. Data Integrity, Backup & Recovery

Local backup verification passed without changing the live development database:

```text
Backup verified: ecommerce-20261103T072952Z
restored_migrations 57
restored_orders 372
restored_products 22
Restore check complete. The live database was not changed.
```

This is valuable local evidence, but it is not a production disaster-recovery program. The provided setup stores backups locally and supplies no verified offsite/encrypted copy, retention schedule, alerting, recovery objective, or production restore drill. This is a **P1 launch blocker**.

## 8. Performance & Scalability

| Item | Result |
|---|---|
| Atomic inventory/order-number contention | PASS via isolated MySQL tests |
| Queue/scheduler basic liveness | PASS locally |
| Load, soak, and capacity test | NOT RUN |
| Production database sizing, index plan, slow-query monitoring | NOT VERIFIED |
| Production Redis/queue failure recovery | NOT VERIFIED |

No throughput, latency, or capacity conclusion should be made from this audit.

## 9. External Integration Readiness

| Integration | Status |
|---|---|
| Real Bakong/KHQR account and callback/transaction verification | NOT VERIFIED |
| Real outbound SMTP delivery and bounce/failed-mail handling | NOT VERIFIED |
| Courier/physical fulfilment and scanner hardware | NOT VERIFIED |
| Public domain, TLS issuance/renewal, production Caddy configuration | NOT VERIFIED |
| Production environment secrets and least-privilege credentials | NOT VERIFIED |
| Real refund settlement workflow | NOT VERIFIED |

Local mock/code-path evidence is not acceptance evidence for any of these integrations.

## 10. CI/CD and Release Controls

The checked-in CI workflow requires backend tests, frontend build, browser tests, production dependency audit, Composer platform checks, and Composer audit. It does not suppress those failures with `continue-on-error` or `|| true`.

That is the correct gate design; however, the candidate cannot pass it while the Laravel and browser failures above remain. No deployment pipeline, rollback execution, artifact promotion, or live-environment smoke test was available to assess.

## 11. Findings and Required Actions

| ID | Severity | Finding | Required closure evidence |
|---|---|---|---|
| P1-01 | P1 | Complete Laravel suite has 14 finance/forecast failures. | Fix/cache-isolate forecast tests and behavior as appropriate; attach a green complete-suite result. |
| P1-02 | P1 | Full Playwright suite exits non-zero: two final account UI failures and observed retry flakiness. | Make readiness/navigation deterministic; attach a green no-retry (or justified retry-policy) browser result. |
| P1-03 | P1 | No offsite/encrypted backup, retention/alerting, or production restore drill evidence. | Configure, document, and demonstrate an isolated restore from production-grade backup storage. |
| P2-01 | P2 | No CSP frame-ancestors or X-Frame-Options protection. | Add and test clickjacking protection in Caddy/application headers. |
| P2-02 | P2 | Production containers have no explicit non-root user. | Run relevant production services as a non-root user and verify writable paths. |
| P2-03 | P2 | Guest browser-held capability has no identified server-side expiry/revocation policy. | Define and test expiry/revocation aligned with order privacy requirements. |

## 12. Closure Checklist

- [ ] Laravel full suite green.
- [ ] Full Playwright suite green without unexplained flakes.
- [ ] Production backup copy is encrypted/off-host, retained, monitored, and restore-tested.
- [ ] Production domain/TLS, environment secrets, SMTP, Bakong, courier/scanner and refund acceptance completed.
- [ ] Load/capacity test and production observability review completed.
- [ ] P2 security/container hardening items accepted or remediated.
- [ ] Release owner signs the final go/no-go after evidence is attached.

## 13. Final Recommendation

**Do not deploy this revision to production.** Resolve P1-01 through P1-03, rerun the full gate set on a clean candidate, and complete the external production acceptance checklist. Reassess only when the evidence is green and reproducible.

## Appendix: Audit Scope and Environment

- Project services were exercised through Docker Compose only.
- PHP 8.3/Laravel, Vue 3/TypeScript/Vite, MySQL 8, Redis and Caddy/Nginx configuration were reviewed in the supplied workspace.
- Disposable MySQL and Redis services used for concurrency testing were stopped after the audit; normal development services were left untouched.
- The pre-existing briefing file `Production Readiness and Closure Verification.md` remained unmodified. No application source changes were made by this audit.
