# E-KHMER Improvement #16 — Export Validation & Bounded Memory

Act as a senior **Laravel + Vue.js + MySQL Reporting Engineer, Export Pipeline Engineer, Performance Engineer, Validation Engineer, Memory-Optimization Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

Previous fixes:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
F03 — Discount Cap & Checkout Total Consistency
F04 — COD Collection & Paid-State Lifecycle
F05 — Guest Checkout Policy & Web Flow
F06 — Rating & Price Filters
F07 — Mobile Color Filters
F08 — Async Cart Mutations & Buy Now Errors
F09 — Formatted Product Descriptions
F10 — Review Aggregate Maintenance
F11 — Low-Stock Notification Lifecycle
F12 — Duplicate Coupon Validation
F13 — Checkout & Reservation Abuse Protection
F14 — Order Number Collision Handling
F15 — Report Metric Consistency
```

Do NOT regress those fixes.

Now work ONLY on:

# F16 — Export Validation & Bounded Memory

---

# 1. CURRENT DEFECTS

The audit identified two export problems.

## Problem A — Export Validation Does Not Match Report Validation

Example:

```text
Summary report:
validates date range

CSV/PDF export:
directly parses date strings
```

Malformed values such as:

```text
from=not-a-date
to=2026-99-99
```

may cause:

```text
Carbon parsing exception
500 response
unexpected query behavior
```

instead of:

```text
422 validation response
```

---

## Problem B — Export Loads Entire Dataset Into Memory

Current export paths may effectively do:

```php
$rows = $query->get();
```

then:

```text
build entire CSV/PDF
```

This does not scale safely.

Example:

```text
100 rows
→ fine

10,000 rows
→ larger memory

100,000 rows
→ risk of memory exhaustion
```

CSV should be streamed/chunked.

Large PDF exports require a bounded strategy.

---

# 2. PRIMARY GOAL

Create one safe export pipeline:

```text
Export Request
      ↓
Validated Filters
      ↓
Canonical F15 Reporting Query
      ↓
Authorization
      ↓
Export Strategy
      ↓

CSV:
stream/chunk rows

PDF:
bounded synchronous export
OR queued export for large datasets
      ↓
Safe Download
```

The same filters and financial semantics used by F15 must remain authoritative.

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing anything.

Focus especially on:

```text
backend/app/Http/Controllers/Api/Admin/AdminReportController.php
```

Also inspect:

```text
backend/app/Http/Requests/
backend/app/Services/Analytics/
backend/app/Services/Reporting/
backend/app/Exports/

report routes
CSV generation helpers
PDF generation service/library
temporary file handling
storage configuration

Order
ShopOrder
OrderItem
Payment
Refund

frontend/src/views/admin/AdminReportsView.vue
frontend report API client
frontend download helpers

existing report tests
export tests
authorization tests
```

Search for:

```text
export
csv
pdf
download
streamDownload
response()->stream
fputcsv
chunk
chunkById
cursor
lazy
lazyById
get()
collect()
all()
Carbon::parse
parseDate
from
to
start_date
end_date
status
sort
order
```

Do not assume exact method names.

---

# 4. MAP EVERY EXPORT ENDPOINT

Identify all report export endpoints.

Potential:

```text
GET /api/admin/reports/export/csv
GET /api/admin/reports/export/pdf
GET /api/admin/reports/orders/export
GET /api/admin/reports/products/export
GET /api/admin/reports/payments/export
```

Document:

```text
endpoint
format
filters
authorization
query source
memory strategy
```

Do not fix only one CSV endpoint while leaving others unsafe.

---

# 5. REUSE F15 METRIC DEFINITIONS

F15 established definitions for:

```text
settled revenue
gross demand
units sold
paid orders
refunds
AOV
```

F16 must NOT create separate financial logic.

Correct:

```text
Report Screen
        ↓
Canonical reporting query

CSV
        ↓
same canonical query

PDF
        ↓
same canonical query
```

Only export mechanics differ.

---

# 6. ONE VALIDATION CONTRACT

Summary, CSV, and PDF should use the same filter rules where the filters are equivalent.

Avoid:

```text
summary:
validated

CSV:
manual request()->query()

PDF:
Carbon::parse directly
```

Preferred:

```text
ReportFilterRequest
```

or shared validator/service.

---

# 7. VALIDATE DATE FORMAT

Determine current accepted format.

Likely:

```text
YYYY-MM-DD
```

or ISO date/datetime.

Use the actual report UI contract.

Examples that should fail cleanly:

```text
abc
2026-13-01
2026-02-31
11/99/2026
```

Expected:

```text
422
```

not:

```text
500
```

---

# 8. VALIDATE DATE ORDER

Example:

```text
from:
2026-11-30

to:
2026-11-01
```

Expected:

```text
422
```

Message concept:

```text
The end date must be on or after the start date.
```

Do not silently reverse the dates.

---

# 9. VALIDATE MAXIMUM EXPORT SPAN

Define a reasonable maximum synchronous export period if needed.

Example:

```text
CSV:
up to 12 months synchronously

PDF:
up to 90 days synchronously
```

Do NOT blindly use these numbers.

Inspect expected report volume.

Choose practical bounds and document them.

---

# 10. WHY DATE-SPAN LIMITS MATTER

Example:

```text
from=2015-01-01
to=2026-12-31
```

could trigger:

```text
millions of rows
large DB query
large memory
long HTTP request
```

A bounded synchronous path prevents accidental or abusive resource exhaustion.

---

# 11. STATUS VALIDATION

Validate:

```text
order_status
payment_status
refund_status
```

against actual allowed values.

Do not let arbitrary strings feed report query logic.

Example:

```text
payment_status=hacked
```

should return:

```text
422
```

or project-consistent empty-result behavior if intentionally designed.

Prefer explicit validation.

---

# 12. SORT VALIDATION

If export accepts:

```text
sort_by
sort_direction
```

whitelist values.

Example allowed:

```text
created_at
order_number
total
paid_at
```

Do NOT directly use arbitrary request field in:

```php
orderBy($request->sort_by)
```

without validation.

---

# 13. SORT DIRECTION

Allow only:

```text
asc
desc
```

Reject anything else.

---

# 14. SHOP FILTER

If Super Admin exports:

```text
shop_id
```

validate:

```text
integer/UUID according to schema
exists
authorized scope
```

Seller must not export another shop's data.

---

# 15. PRODUCT FILTER

Validate product IDs.

For seller-scoped report:

product must belong to authorized shop.

Do not trust raw product ID alone.

---

# 16. PAYMENT METHOD FILTER

Whitelist:

```text
COD
Bakong
KHQR
```

or actual methods defined by the project.

Do not allow arbitrary values.

---

# 17. CURRENCY FILTER

Use actual supported currencies.

Do not introduce new conversion semantics in F16.

---

# 18. PAGINATION PARAMS

Export should normally ignore UI pagination:

```text
page
per_page
```

unless export intentionally means "current page only."

Document behavior.

Do not accidentally export only page 1 because query reuse retained pagination.

---

# 19. SUMMARY FILTER EQUIVALENCE

Given:

```text
date range
shop
payment status
order status
product
```

the export must use the same filter semantics as the screen.

Test exact equivalence.

---

# 20. MALFORMED DATE MUST RETURN 422

Critical audit reproduction:

```text
GET /reports/export/csv?from=abc
```

Expected:

```text
422
```

with field error.

Not:

```text
500
Carbon exception
```

---

# 21. PDF MALFORMED DATE

Same for PDF.

Do not validate CSV but leave PDF unsafe.

---

# 22. CSV STREAMING

Do NOT build entire CSV in memory.

Preferred Laravel concept:

```php
return response()->streamDownload(function () use ($query) {
    $handle = fopen('php://output', 'w');

    fputcsv($handle, $headers);

    // iterate safely

    fclose($handle);
}, $filename);
```

Adapt to actual project.

---

# 23. CHUNKED QUERYING

Possible approaches:

```text
chunkById()
lazyById()
cursor()
```

Choose based on query structure.

Do NOT blindly use `cursor()` if relationships cause N+1 or query limitations.

---

# 24. PREFER CHUNKBYID / LAZYBYID WHERE SAFE

For large stable exports:

```text
lazyById()
```

or:

```text
chunkById()
```

can keep memory bounded.

Ensure the query has a stable unique ID ordering.

---

# 25. DO NOT USE OFFSET PAGINATION FOR HUGE EXPORTS

Avoid:

```text
OFFSET 100000
```

loops where possible.

Keyset/chunk-by-ID is safer for large datasets.

---

# 26. COMPLEX REPORT QUERIES

If F15 report query uses:

```text
GROUP BY
aggregates
joins
```

simple `chunkById()` may not be directly valid.

Inspect the query.

Possible solutions:

```text
chunk on base order IDs
then fetch related report rows

database cursor

temporary aggregated subquery
```

Use the simplest correct architecture.

---

# 27. NO N+1 IN CSV

Streaming is not enough if every row performs:

```text
1 order query
+ payment query
+ shop query
+ item query
```

For 50,000 rows this becomes disastrous.

Inspect eager loading / joins.

Avoid N+1.

---

# 28. CSV HEADER

Write header once.

Example:

```text
Order Number
Date
Shop
Payment Method
Payment Status
Settled Revenue
Refunded Amount
Net Revenue
```

Use F15 definitions.

---

# 29. CSV FORMULAS — SECURITY

Important spreadsheet injection risk.

Values beginning with:

```text
=
+
-
@
```

can be interpreted as formulas by spreadsheet applications.

Examples:

```text
=HYPERLINK(...)
+SUM(...)
```

If seller/customer-controlled fields are exported:

sanitize them for CSV spreadsheet safety.

---

# 30. CSV INJECTION DEFENSE

Possible approach:

prefix dangerous cells with:

```text
'
```

or use an existing safe CSV helper.

Do NOT corrupt genuine numeric columns.

Apply protection to untrusted string fields such as:

```text
customer name
product name
shop name
address text
SKU if user-controllable
```

Document behavior.

---

# 31. CSV ENCODING

Use UTF-8.

Ensure Khmer exports correctly.

Test:

```text
ផលិតផល
អតិថិជន
ហាង
```

No mojibake.

---

# 32. UTF-8 BOM

Determine whether current Excel compatibility requires BOM.

Do not add/remove it blindly.

Test existing consumer expectations.

---

# 33. CSV QUOTING

Use proper CSV writer such as:

```text
fputcsv
```

Do NOT manually concatenate:

```php
$value1 . ',' . $value2
```

because fields may contain:

```text
commas
quotes
newlines
Khmer text
```

---

# 34. STREAM FAILURE

If DB error occurs after streaming begins:

HTTP status may already be sent.

Prevent avoidable validation/query failures before streaming.

Log unexpected mid-stream failure.

Do not expose stack trace in downloaded content.

---

# 35. CSV FILENAME

Use safe deterministic filename.

Example:

```text
e-khmer-report-2026-11-01-to-2026-11-30.csv
```

Do not use unsanitized request values directly in filename.

---

# 36. CONTENT TYPE

Return correct headers:

```text
text/csv
Content-Disposition: attachment
```

using framework helpers.

---

# 37. PDF IS DIFFERENT FROM CSV

Most PDF libraries render the entire HTML/document structure in memory.

Do NOT pretend PDF can always scale like streamed CSV.

Create an explicit bounded strategy.

---

# 38. PDF SYNCHRONOUS LIMIT

Define a maximum row count or data volume for synchronous PDF.

Example concept:

```text
if rows <= PDF_SYNC_LIMIT:
    generate directly
else:
    reject or queue
```

Do not use an arbitrary huge number.

Benchmark current library.

---

# 39. PDF OPTION A — CAP

Simple acceptable strategy:

```text
PDF maximum:
5,000 rows
```

If exceeded:

```text
422 / 413 / project-consistent response

"PDF export is too large. Narrow the date range or use CSV."
```

Use a sensible actual threshold.

---

# 40. PDF OPTION B — QUEUE

If current project already has queue infrastructure and downloadable exports:

large PDF may be queued.

Flow:

```text
Request PDF
      ↓
validate
      ↓
estimate rows
      ↓
large
      ↓
queue export
      ↓
return 202
      ↓
notification/download when ready
```

Do not build a major export-job system if none exists and a documented cap solves F16 adequately.

---

# 41. DO NOT CLAIM BACKGROUND PROCESSING WITHOUT IMPLEMENTATION

If choosing queue strategy:

actually implement:

```text
job
status
storage
authorization
cleanup
```

Otherwise use synchronous cap.

---

# 42. PDF ROW ESTIMATE

Use efficient:

```text
COUNT
```

or equivalent before loading data.

Do not:

```text
$query->get()->count()
```

which defeats the memory goal.

---

# 43. PDF TEMPLATE

Do not put enormous image assets or full product galleries in report PDFs.

Keep report template lightweight.

---

# 44. PDF IMAGES

If logo is required:

use efficient local/static asset.

Do not fetch remote images per row.

---

# 45. PDF PAGE BREAKS

Large-but-allowed PDF should have sane:

```text
page headers
table headers
page breaks
```

Do not redesign document styling extensively.

---

# 46. PDF EMPTY REPORT

If zero rows:

generate:

```text
No data for selected filters.
```

or project-consistent behavior.

Do not crash template.

---

# 47. MEMORY BUDGET

Define an agreed memory target.

For example:

```text
CSV export:
memory remains approximately flat as row count increases

PDF:
must remain below configured safe process limit
```

Measure actual behavior where practical.

Do not only say "optimized."

---

# 48. MEMORY TESTING

Test:

```text
1,000 rows
10,000 rows
50,000 rows
```

for CSV if practical.

Record:

```text
peak memory
execution time
file size
```

Do not create unrealistic production-sized fixtures if test environment cannot handle them.

---

# 49. CSV EXPECTED MEMORY BEHAVIOR

Streaming should show:

```text
memory 1k rows
≈
memory 50k rows
```

within reasonable overhead.

Not linear growth with dataset size.

---

# 50. PDF MEMORY TEST

Benchmark at allowed maximum.

Confirm it completes below process memory/time limit.

If not:

reduce cap.

---

# 51. TIME BUDGET

Define practical synchronous request duration.

Avoid:

```text
3-minute HTTP export
```

if infrastructure times out earlier.

Use actual project/runtime constraints.

---

# 52. DATABASE TIMEOUT

Do not globally increase DB timeout just to make a bad export query finish.

Optimize query/bounds instead.

---

# 53. HTTP TIMEOUT

Do not blindly set:

```text
max_execution_time = 0
```

for exports.

Bounded work is safer.

---

# 54. AUTHORIZATION

Only authorized admin/seller roles may export.

Do not let:

```text
customer
guest
other shop owner
```

download sensitive reports.

---

# 55. SELLER SHOP ISOLATION

If seller report export exists:

Shop A user must only export Shop A data.

Do NOT accept:

```text
shop_id=ShopB
```

from frontend as trusted authority.

---

# 56. SUPER ADMIN

Super Admin may export marketplace-wide or selected-shop reports according to current authorization.

Preserve this.

---

# 57. SENSITIVE DATA

Review exported columns.

Do not export:

```text
passwords
tokens
session IDs
payment secrets
internal keys
```

No unnecessary PII.

---

# 58. CUSTOMER PII

If report legitimately needs:

```text
name
email
phone
address
```

include only required fields.

Do not expand data exposure in F16.

---

# 59. URL QUERY SIZE

Do not encode huge object structures in export query string.

Use current simple filters.

---

# 60. SQL INJECTION

Use query builder / bindings.

Do not concatenate:

```text
sort
status
shop IDs
dates
```

into raw SQL without safe whitelisting/binding.

---

# 61. EXPORT SERVICE

Avoid putting every detail in controller.

Preferred:

```text
ReportFilterValidator
ReportQueryService
CsvReportExporter
PdfReportExporter
```

or equivalent current architecture.

Do not over-abstract a small codebase.

---

# 62. FILTER DTO

If project uses DTOs, one normalized filter object can be shared:

```text
from
to
shop
status
payment method
sort
```

Summary/export then use the same values.

---

# 63. DATE NORMALIZATION

After validation:

convert to normalized boundaries once.

Example:

```text
startOfDay
endOfDay
```

according to existing report timezone semantics.

Do not parse repeatedly in many methods.

---

# 64. TIMEZONE

Use same timezone as F15 report semantics.

Do not let CSV and screen differ because one uses UTC and another application timezone.

---

# 65. INCLUSIVE BOUNDARIES

Define:

```text
from = inclusive
to = inclusive
```

if that's existing behavior.

Example:

```text
2026-11-03
```

should include full selected day according to timezone.

---

# 66. INVALID RANGE SPAN

Example:

```text
from=2026-01-01
to=2030-01-01
```

If exceeds synchronous export limit:

return clean validation error.

---

# 67. DEFAULT RANGE

If export is called without dates:

determine existing default.

Do not accidentally export all historical data if summary defaults to:

```text
last 30 days
```

Use the same default.

---

# 68. SCREEN/EXPORT DEFAULT PARITY

Example:

Admin opens reports.

Screen shows:

```text
Last 30 days
```

Click Export without changing filters.

Export should represent:

```text
same 30 days
```

not all-time.

---

# 69. FILTER STATE FROM FRONTEND

Ensure export button uses current:

```text
date range
shop
status
payment method
product
```

not stale initial values.

---

# 70. FRONTEND ERROR HANDLING

If export returns:

```text
422
```

show field/general message.

Example:

```text
The selected date range is too large for PDF export.
```

Do not download a JSON error as:

```text
report.pdf
```

---

# 71. 429 HANDLING

If exports are throttled by current system:

show existing normalized error.

Do not implement new export abuse throttling unless needed.

---

# 72. PDF TOO LARGE UX

Example:

```text
This PDF would contain 28,000 rows.

Please narrow the date range or export CSV instead.
```

Actionable UX is better than generic failure.

---

# 73. CSV LARGE EXPORT UX

If CSV streams successfully:

do not artificially impose tiny limits.

CSV is intended for larger datasets.

---

# 74. DOWNLOAD BUTTON STATE

During request:

```text
Export CSV
→ Preparing...
```

Avoid duplicate simultaneous downloads from double-click.

Reuse F08 async patterns.

---

# 75. DO NOT FREEZE ENTIRE PAGE

If possible, disable only export button.

Reports should remain usable.

---

# 76. BROWSER DOWNLOAD

Verify file is actually downloaded with:

```text
correct filename
correct extension
non-zero file size
```

---

# 77. CSV CONTENT TEST

Open/read generated CSV in test.

Verify:

```text
header
row count
values
quotes
UTF-8
```

---

# 78. PDF CONTENT TEST

Verify PDF or generated intermediate contains:

```text
report title
date range
expected summary
expected rows
```

Do not require visual pixel-perfect test unless existing suite supports it.

---

# 79. F15 RECONCILIATION

Critical.

Fixture:

```text
Pending unpaid:
$100

Paid:
$50
```

If F15 settled report says:

```text
Settled Revenue = $50
```

CSV and PDF must reflect:

```text
$50
```

for same metric/filter.

---

# 80. PARTIAL REFUND EXPORT

If F15 net revenue includes completed refund:

CSV/PDF must use same refund treatment.

---

# 81. COD EXPORT

Collected COD included.

Uncollected COD excluded from settled revenue according to F15.

---

# 82. MULTI-SHOP

Marketplace export must not double count:

```text
parent
+
shop orders
```

Preserve F15.

---

# 83. HISTORICAL SNAPSHOT

Exports should use:

```text
order item snapshots
```

not current product names/prices where historical accuracy requires snapshots.

---

# 84. PRODUCT DELETED

Historical export remains valid if product later deleted.

---

# 85. SHOP DELETED/INACTIVE

Historical marketplace report remains valid according to F15.

---

# 86. CSV FORMULA INJECTION TEST

Create customer/product string:

```text
=1+1
```

Export.

Expected spreadsheet-safe representation.

No formula execution when opened.

---

# 87. CSV COMMA TEST

Value:

```text
Phnom Penh, Cambodia
```

must remain one column.

---

# 88. CSV QUOTE TEST

Value:

```text
12" Laptop
```

must escape properly.

---

# 89. CSV NEWLINE TEST

Value containing newline must not break column structure.

---

# 90. KHMER TEST

Value:

```text
អាវយឺតពណ៌ខៀវ
```

must export correctly.

---

# 91. LARGE CSV FEATURE TEST

Generate a large isolated fixture.

Export.

Verify:

```text
all expected rows produced
```

without using:

```text
->get()
```

on entire result where avoidable.

---

# 92. QUERY COUNT TEST

Ensure large export does not produce:

```text
N rows × multiple queries
```

due to N+1.

---

# 93. MEMORY INSTRUMENTATION

Where practical use:

```php
memory_get_peak_usage(true)
```

in dedicated test/benchmark—not production response—to compare old/new behavior.

Document observed results.

---

# 94. PDF LIMIT TEST

Create fixture exceeding threshold.

Request PDF.

Expected:

```text
controlled error
```

or:

```text
queued job
```

depending implementation.

Not:

```text
PHP memory exhausted
```

---

# 95. PDF WITHIN LIMIT TEST

Create fixture below threshold.

Expected:

```text
200
valid PDF
```

---

# 96. MALFORMED DATE TEST

CSV:

```text
from=abc
```

Expected:

```text
422
```

PDF:

```text
from=abc
```

Expected:

```text
422
```

---

# 97. REVERSED DATE TEST

```text
from=2026-11-30
to=2026-11-01
```

Expected:

```text
422
```

for both formats.

---

# 98. INVALID STATUS TEST

```text
status=invalid-status
```

Expected:

```text
422
```

---

# 99. INVALID SORT TEST

```text
sort_by=DROP TABLE
```

Expected:

```text
422
```

No query injection.

---

# 100. UNAUTHORIZED EXPORT TEST

Customer:

```text
GET /admin/reports/export
```

Expected:

```text
403
```

or existing auth status.

---

# 101. WRONG SHOP EXPORT TEST

Shop A manager requests Shop B export.

Expected:

```text
403 / filtered to own shop
```

according to existing API design.

Never leak Shop B data.

---

# 102. TEMP FILE CLEANUP

If PDF uses temporary files:

clean them after response/job.

Do not accumulate:

```text
/tmp/report-*.html
/tmp/report-*.pdf
```

indefinitely.

---

# 103. QUEUED EXPORT CLEANUP

If queue strategy is implemented:

define retention for generated files.

Example concept:

```text
24 hours
```

Use project conventions.

---

# 104. QUEUED EXPORT AUTHORIZATION

Download URL/token must be owner-scoped.

Never expose predictable public file path for sensitive report.

---

# 105. STORAGE

Use existing Laravel storage abstraction.

Do not hardcode Windows/Linux paths.

---

# 106. FAILURE CLEANUP

If PDF generation fails:

remove partial temporary files.

---

# 107. RESPONSE BUFFERING

For streamed CSV:

avoid accidental output buffering that recreates full file in memory.

Inspect middleware/server behavior where practical.

---

# 108. NGINX

Do not make broad Nginx changes unless current buffering prevents streaming.

If needed, document minimal header/config behavior.

Do not tune unrelated server settings.

---

# 109. GZIP

Do not enable compression changes merely for F16 unless current infrastructure already does.

---

# 110. REPORT CACHE

If F15 report data is cached:

exports should still use correct canonical data.

Do not export stale cached values unexpectedly.

---

# 111. CACHE MEMORY

Do not cache massive full export datasets in Redis.

Caching report summary is different from caching 100k export rows.

---

# 112. OBSERVABILITY

Log:

```text
export type
row count
duration
success/failure
```

where current logging supports it.

Do NOT log report contents or sensitive PII.

---

# 113. METRICS

Optional if infrastructure exists:

```text
export_duration
export_rows
export_failure
```

Do not build new monitoring platform in F16.

---

# 114. F01 REGRESSION

Coupon ownership unaffected.

---

# 115. F02 REGRESSION

Cart recovery unaffected.

---

# 116. F03 REGRESSION

Financial totals unaffected.

---

# 117. F04 REGRESSION

COD lifecycle unaffected.

---

# 118. F05 REGRESSION

Guest checkout unaffected.

---

# 119. F06 REGRESSION

Catalog filters unaffected.

---

# 120. F07 REGRESSION

Mobile color filter unaffected.

---

# 121. F08 REGRESSION

Async frontend handling remains safe.

---

# 122. F09 REGRESSION

Rich descriptions unaffected.

---

# 123. F10 REGRESSION

Review aggregates unaffected.

---

# 124. F11 REGRESSION

Low-stock lifecycle unaffected.

---

# 125. F12 REGRESSION

Coupon validation unaffected.

---

# 126. F13 REGRESSION

Checkout abuse controls unaffected.

---

# 127. F14 REGRESSION

Order-number collision handling unaffected.

---

# 128. F15 REGRESSION

Critical:

```text
all export metrics
```

must preserve F15 definitions.

No reverting pending orders back into "sales."

---

# 129. BACKEND TESTS

Run focused:

```text
report validation tests
CSV export tests
PDF export tests
authorization tests
F15 reconciliation tests
large-dataset export tests
```

Then relevant broader backend suite.

---

# 130. FRONTEND TESTS

Test:

```text
current filters passed to export
422 rendered properly
PDF-too-large message
button loading/re-enable
```

where frontend changes are needed.

---

# 131. TYPE CHECK

Run TypeScript type check.

No:

```text
any
@ts-ignore
```

just to bypass export errors/blob types.

---

# 132. PRODUCTION BUILD

Run frontend production build.

Must pass.

---

# 133. DOCKER ONLY

Use project Docker environment for:

```text
PHP
Composer
MySQL
Redis if relevant
Node
tests
build
```

Do not wipe development data.

---

# 134. REALISTIC PERFORMANCE VALIDATION

Do not test only:

```text
10 rows
```

and declare bounded-memory complete.

Use a realistically large fixture.

Document:

```text
row count
peak memory
duration
```

---

# 135. DO NOT START F17

Only solve F16.

Do NOT start dependency upgrades or CI security work.

That is:

```text
F17 — Dependency Advisories & CI Security Gate
```

---

# 136. DEFINITION OF DONE

Do not mark F16 complete until:

```text
[ ] CSV and PDF use validated filter input

[ ] Summary/export filter semantics match

[ ] Malformed date returns 422

[ ] Impossible date returns 422

[ ] Reversed date range returns 422

[ ] Export span limit is defined where needed

[ ] Status values are validated

[ ] Sort field is whitelisted

[ ] Sort direction is validated

[ ] Shop authorization is enforced

[ ] Seller export is shop-isolated

[ ] F15 metric definitions are preserved

[ ] CSV no longer loads full export into memory

[ ] CSV streams/chunks safely

[ ] CSV does not create N+1 queries

[ ] UTF-8/Khmer exports correctly

[ ] CSV quoting works

[ ] CSV formula injection is mitigated

[ ] PDF synchronous limit is defined

[ ] Large PDF is capped or actually queued

[ ] Large PDF does not exhaust PHP memory

[ ] PDF within allowed size succeeds

[ ] Temporary files are cleaned

[ ] Frontend handles validation errors correctly

[ ] Export button recovers from failure

[ ] Large CSV performance is tested

[ ] Peak memory is measured or otherwise credibly bounded

[ ] CSV reconciles with F15 report values

[ ] PDF reconciles with F15 report values

[ ] Backend tests pass

[ ] Frontend tests pass

[ ] Type check passes

[ ] Production build passes

[ ] F01–F15 regressions pass
```

---

# 137. FINAL REPORT

At completion report exactly:

```text
F16 — EXPORT VALIDATION & BOUNDED MEMORY

Status:
PASS / NEEDS WORK

Root Causes:

Old CSV Flow:

New CSV Flow:

Old PDF Flow:

New PDF Flow:

Shared Validation:
YES / NO

Accepted Date Format:

Date Range Rule:

Maximum CSV Span:
Value / None

Maximum PDF Span:
Value / None

PDF Row Limit:
Value / None

Large PDF Strategy:
CAP / QUEUE / OTHER

CSV Iteration Strategy:
chunkById / lazyById / cursor / other

Full Dataset ->get() Removed:
YES / NO / N/A

CSV Formula Injection Protection:
YES / NO

UTF-8 / Khmer:
PASS / FAIL

Sort Whitelist:
List

Status Validation:
List

Shop Authorization:
PASS / FAIL

Seller Isolation:
PASS / FAIL / N/A

F15 Metric Reconciliation:
PASS / FAIL

Malformed Date Test:
PASS / FAIL

Reversed Date Test:
PASS / FAIL

Large CSV Test Rows:

Large CSV Peak Memory:

Large CSV Duration:

PDF Limit Test:
PASS / FAIL

PDF Within-Limit Test:
PASS / FAIL

Temporary File Cleanup:
PASS / FAIL / N/A

Files Changed:

Schema Changes:
None / List

Indexes Added:
None / List

Config Added:
None / List

Dependencies Added:
None / List

Tests Added:

Backend Tests:
Passed / Failed

Frontend Tests:
Passed / Failed / N/A

Performance Tests:
Passed / Failed

Type Check:
PASS / FAIL

Production Build:
PASS / FAIL

F01 Regression:
PASS / FAIL

F02 Regression:
PASS / FAIL

F03 Regression:
PASS / FAIL

F04 Regression:
PASS / FAIL

F05 Regression:
PASS / FAIL

F06 Regression:
PASS / FAIL

F07 Regression:
PASS / FAIL

F08 Regression:
PASS / FAIL

F09 Regression:
PASS / FAIL

F10 Regression:
PASS / FAIL

F11 Regression:
PASS / FAIL

F12 Regression:
PASS / FAIL

F13 Regression:
PASS / FAIL

F14 Regression:
PASS / FAIL

F15 Regression:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F17 — Dependency Advisories & CI Security Gate
```

Also provide this before/after example:

```text
BEFORE

Request:

GET /reports/export/csv?from=abc

Backend:

Carbon::parse("abc")

Result:

500 / parsing exception


AFTER

Request:

GET /reports/export/csv?from=abc

Validation:

FAIL

Response:

422

errors.from:
"The from date must be a valid date."
```

And the memory example:

```text
BEFORE

100,000 report rows

$query->get()

→ all rows loaded
→ CSV built
→ high memory growth


AFTER

100,000 report rows

validated query
→ lazy/chunk iteration
→ write one chunk/row to output
→ release memory
→ continue

Result:

memory remains bounded
while file size grows
```

Stop after F16.

Do NOT automatically begin F17.

I will review the result first.