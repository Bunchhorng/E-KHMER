# F16 — EXPORT VALIDATION & BOUNDED MEMORY

Status:
PASS

Root Causes:
The four report exports parsed date strings directly and three CSV exports loaded their complete result sets before writing the response. PDF had no size boundary.

Old CSV Flow:
Request → direct `Carbon::parse()` → query `get()` → in-memory collection → `streamDownload()`.

New CSV Flow:
Request → `AdminReportFilterRequest` → normalized dates → keyset iteration with `lazyById(500)` → `fputcsv()` to `php://output`. Product aggregates retain F15’s canonical calculation and reject source sets above 10,000 rows before aggregation.

Old PDF Flow:
Request → direct date parsing → query `get()` → DomPDF, with no row or span limit.

New PDF Flow:
Request → shared validation → efficient `COUNT(*)` → 1,000-row cap → bounded `get()` → DomPDF.

Shared Validation:
YES

Accepted Date Format:
YYYY-MM-DD

Date Range Rule:
`to` must be on or after `from`; dates normalize to the start/end of the selected day.

Maximum CSV Span:
366 days inclusive

Maximum PDF Span:
91 days inclusive

PDF Row Limit:
1,000

Large PDF Strategy:
CAP — return 422 with guidance to narrow the range or use CSV.

CSV Iteration Strategy:
`lazyById(500)` for orders and payments; product export uses F15’s chunked canonical allocation calculation with a 10,000-source-row aggregation cap.

Full Dataset ->get() Removed:
YES — unbounded order and payment CSV collections were removed. The deliberately bounded PDF and capped product aggregation retain only their allowed result set.

CSV Formula Injection Protection:
YES — untrusted string cells beginning with `=`, `+`, `-`, or `@` receive a leading apostrophe.

UTF-8 / Khmer:
PASS — CSV is emitted through `fputcsv()` as UTF-8; the feature test covers Khmer customer text.

Sort Whitelist:
No caller-selected export sort is supported. `sort_by` and `sort_direction` are rejected, preventing an untrusted value from reaching `orderBy()`.

Status Validation:
Orders: `all`, `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled`, `refunded`.

Payments: `all`, `pending`, `completed`, `failed`, `refunded`.

Summary and product CSV do not support a status filter and reject it instead of silently ignoring it.

Shop Authorization:
PASS — report routes remain behind the existing Sanctum admin middleware.

Seller Isolation:
N/A — this application has no seller report-export endpoint.

F15 Metric Reconciliation:
PASS — product CSV continues to use `FinancialMetricService`; payment date filters now use the same order-placement date basis as F15.

Malformed Date Test:
PASS — all four exports return 422 field errors.

Reversed Date Test:
PASS — all four exports return a `to` validation error.

Large CSV Test Rows:
1,500 orders plus header

Large CSV Peak Memory:
Bounded by 500 loaded order models per query iteration; the HTTP test harness buffers its asserted output, so it is not a production-stream memory measurement.

Large CSV Duration:
2.24 seconds in the final feature-suite run.

PDF Limit Test:
PASS — 1,001 rows return 422.

PDF Within-Limit Test:
PASS — a two-row PDF renders successfully.

Temporary File Cleanup:
N/A — synchronous exports stream directly and create no application temporary files.

Files Changed:
`backend/app/Http/Requests/AdminReportFilterRequest.php`, `backend/app/Http/Controllers/Api/Admin/AdminReportController.php`, `backend/tests/Feature/Api/ReportExportSafetyTest.php`, `frontend/src/api/errors.ts`, `frontend/src/views/admin/AdminReportsView.vue`, `frontend/src/views/admin/AdminOrdersView.vue`.

Schema Changes:
None

Indexes Added:
None

Config Added:
None

Dependencies Added:
None

Tests Added:
`ReportExportSafetyTest`

Backend Tests:
Passed — 43 tests, 241 assertions: `ReportExportSafetyTest` and `AdminOpsTest`.

Frontend Tests:
N/A — the frontend has no test script configured.

Performance Tests:
Passed — 1,500-row streamed CSV fixture and 1,001-row PDF-cap fixture.

Type Check:
PASS — `vue-tsc -b` as part of `npm run build`.

Production Build:
PASS — `vite build` as part of `npm run build`.

F01 ... F15 Regression:
F15: PASS through `AdminOpsTest` report coverage. F01–F14 were not rerun because F16 changes only report exports and export error display.

Remaining Risks:
CSV delivery still depends on web-server buffering settings outside Laravel. The product CSV intentionally caps aggregation because its F15-exact cross-allocation calculation requires an aggregate map.

Related Follow-Up:
F17 — Dependency Advisories & CI Security Gate

## Before/after validation example

Before, `GET /api/admin/reports/orders.csv?from=not-a-date` reached `Carbon::parse()` and could fail as a server error. It now returns HTTP 422 with a `from` field error before an export stream starts.

## Before/after memory example

Before, 50,000 orders would be hydrated by `get()` before the first CSV byte was written. Now the orders CSV keeps at most one 500-record `lazyById()` page in application memory while it writes each row directly to the response stream.
