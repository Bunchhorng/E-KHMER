# E-KHMER Improvement #15 — Report Metric Consistency

Act as a senior **Laravel + Vue.js + MySQL Marketplace Engineer, Financial Reporting Engineer, Accounting Logic Engineer, Analytics Engineer, Data-Consistency Engineer, and QA Engineer**.

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
```

Do NOT regress those fixes.

Now work ONLY on:

# F15 — Report Metric Consistency

---

# 1. CURRENT DEFECT

The audit reproduced this scenario:

```text
Order item value:
$100

Order:
Pending

Payment:
Unpaid
```

Current report output:

```text
Revenue:
$0

Items Revenue:
$100

Units Sold:
1
```

This is internally inconsistent if all three metrics are intended to represent completed/settled sales.

The project must clearly distinguish concepts such as:

```text
Gross Order Demand
Settled Sales
Paid Revenue
Units Ordered
Units Sold
Refunded Revenue
Net Revenue
AOV
```

and use them consistently.

---

# 2. PRIMARY GOAL

Establish one documented financial reporting model.

Preferred conceptual architecture:

```text
Orders
  ↓
Order / Payment / Refund Status Rules
  ↓
Canonical Reporting Query / Metric Service
  ↓
Report Summary Cards
  ↓
Charts
  ↓
Tables
  ↓
CSV Export
  ↓
Financial Analytics
  ↓
Business Forecast Inputs
```

All surfaces must use compatible definitions.

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing code.

Focus especially on:

```text
backend/app/Http/Controllers/Api/Admin/AdminReportController.php
backend/app/Services/Analytics/FinancialMetricService.php
```

Also inspect:

```text
backend/app/Services/Analytics/
backend/app/Services/CheckoutService.php

Order model
ShopOrder model
OrderItem model
Payment model
PaymentTransaction model
Return model
Refund model

admin report resources
report requests
CSV export logic
PDF report logic if metrics are reused

seller reports if they share logic

frontend/src/views/admin/AdminReportsView.vue
frontend report API/types
frontend chart components

business forecasting/history services

existing report tests
financial analytics tests
payment tests
refund tests
COD tests
```

Search globally for:

```text
revenue
items_revenue
units_sold
orders_count
average_order_value
aov
gross
net
profit
refund
paid
settled
payment_status
cancelled
delivered
completed
```

Do NOT assume field meanings from names alone.

---

# 4. MAP EVERY REPORTING SURFACE

Identify where financial metrics appear.

At minimum inspect:

```text
Admin report cards

Revenue charts

Product reports

Payment reports

CSV exports

PDF exports

Seller reports if shared

Dashboard cards

Financial analytics

Profit prediction / forecasting history
```

Create a mapping:

```text
Metric
→ Backend query/service
→ API field
→ Frontend display
```

This prevents fixing one card while leaving exports inconsistent.

---

# 5. DEFINE A METRIC DICTIONARY

Before coding, document every major metric.

At minimum define:

```text
Gross Order Demand
Settled Revenue
Net Revenue
Items Revenue
Units Ordered
Units Sold
Orders Created
Paid Orders
Cancelled Orders
Refunded Amount
Average Order Value
```

Use the actual project terminology where possible.

---

# 6. GROSS ORDER DEMAND

Recommended definition:

```text
Value of valid orders created
before considering payment settlement
```

Example:

```text
Pending unpaid order:
$100
```

May contribute to:

```text
Gross Order Demand = $100
```

but NOT necessarily:

```text
Settled Revenue
```

This metric is useful for demand analysis.

Do not call it `Revenue` if it includes unpaid orders.

---

# 7. SETTLED REVENUE

Recommended definition:

```text
Revenue backed by successful/collected payment
subject to cancellation/refund rules
```

Example:

```text
Pending unpaid order:
$100
```

Expected:

```text
Settled Revenue = $0
```

After valid payment:

```text
Settled Revenue = $100
```

Use actual payment lifecycle.

---

# 8. COD INTERACTION

F04 established COD collection.

Correct reporting concept:

```text
COD order created:
not yet settled

Delivered + cash collected:
settled
```

Do NOT treat:

```text
Delivered but COD not collected
```

as paid revenue.

---

# 9. ONLINE PAYMENT

Bakong/KHQR should count as settled only after verified successful payment according to existing payment rules.

Do not count:

```text
pending
expired
failed
unverified
```

as settled revenue.

---

# 10. ORDER STATUS ALONE IS NOT ENOUGH

Avoid:

```text
order.status = delivered
→ revenue
```

without checking payment state where required.

Order lifecycle and payment lifecycle are separate.

---

# 11. PAYMENT STATUS ALONE MAY ALSO BE INSUFFICIENT

Inspect cancellation/refund rules.

Example:

```text
payment paid
order later fully refunded
```

Gross settled sale existed historically, but net revenue should reflect refund.

Define which report metric represents which concept.

---

# 12. CANCELLED UNPAID ORDER

Example:

```text
Order = $100
Payment = unpaid
Status = cancelled
```

Recommended:

```text
Settled Revenue = $0
Units Sold = 0
```

Gross demand may:

```text
include or exclude cancelled demand
```

depending on explicit metric definition.

Document it.

---

# 13. CANCELLED PAID ORDER

If order was paid before cancellation:

determine:

```text
Was payment refunded?
Is refund pending?
```

Do not simply erase financial history.

Metrics may need:

```text
Gross Paid Sales
Refunded Amount
Net Revenue
```

---

# 14. FULL REFUND

Example:

```text
Original paid order:
$100

Refund:
$100
```

Possible metrics:

```text
Gross Settled Revenue = $100
Refunded Amount = $100
Net Revenue = $0
```

This is clearer than pretending the transaction never happened.

Use actual project accounting semantics.

---

# 15. PARTIAL REFUND

Critical.

Example:

```text
Paid:
$100

Refund:
$30
```

Expected financial model may be:

```text
Gross Revenue:
$100

Refunded:
$30

Net Revenue:
$70
```

Define this once and reuse it.

---

# 16. PARTIAL ITEM REFUND

If refund applies to particular items:

```text
Product A:
$60

Product B:
$40

Refund Product B:
$40
```

Product-level report should not continue showing Product B as a fully settled sale if project semantics define net sold units/revenue.

Inspect current refund model.

---

# 17. UNITS ORDERED VS UNITS SOLD

This distinction is central.

Recommended:

```text
Units Ordered
→ quantities in valid created orders

Units Sold
→ quantities belonging to settled sales
```

Then a pending unpaid `$100`, qty 1 order can correctly show:

```text
Units Ordered:
1

Units Sold:
0
```

Do not call pending units "sold" unless explicitly defined that way.

---

# 18. CURRENT BUG REPRODUCTION

Test exactly:

```text
Order item:
qty = 1
subtotal = $100

Payment:
pending/unpaid
```

If primary report is settled-sales reporting:

Expected:

```text
Revenue = $0
Items Revenue = $0
Units Sold = 0
```

Optional demand metrics:

```text
Gross Demand = $100
Units Ordered = 1
```

Do not mix these definitions under ambiguous names.

---

# 19. ITEMS REVENUE

Determine exactly what:

```text
items_revenue
```

means today.

Possible meanings:

```text
sum order item subtotal
sum paid item subtotal
sum after item discounts
sum before tax/shipping
```

Choose and document.

If it represents settled item sales:

pending unpaid items must not contribute.

---

# 20. SHIPPING REVENUE

Decide whether primary `revenue` includes:

```text
shipping fees
```

or product sales only.

Example:

```text
Items:
$100

Shipping:
$5

Tax:
$10

Total:
$115
```

Potential definitions:

```text
Merchandise Revenue = $100
Shipping Revenue = $5
Tax Collected = $10
Order Collected = $115
```

Do not lump them together without naming.

---

# 21. TAX

Tax is usually not business revenue.

Do not automatically treat:

```text
tax collected
```

as profit or merchandise revenue.

However, preserve current business/accounting model unless project requirements specify otherwise.

F15 is about consistency first.

---

# 22. DISCOUNTS

F03 already established final coupon discount correctness.

Report metrics must use persisted order financial snapshots.

Do NOT recalculate historical discounts from current coupon configuration.

---

# 23. PRODUCT REVENUE AFTER DISCOUNT

Define how order-level/shop-level discount is allocated to product metrics.

Possible:

```text
gross item revenue
```

versus:

```text
net item revenue after allocated discounts
```

If both are needed, name both clearly.

Do not let report card use net revenue while CSV uses gross item revenue under the same label.

---

# 24. SHOP-LEVEL DISCOUNT

F01/F03 may allocate discounts per shop order.

Ensure product/shop reports use persisted allocation consistently.

---

# 25. PARENT ORDER VS SHOP ORDER

E-KHMER is multi-vendor.

Avoid double counting.

Example:

```text
Parent Order:
$150

Shop A:
$100

Shop B:
$50
```

Marketplace revenue must be:

```text
$150
```

not:

```text
Parent $150
+
Shop A $100
+
Shop B $50
=
$300
```

Choose one aggregation level for each report.

---

# 26. SELLER REPORTS

Seller metrics must only use:

```text
seller's authorized shop orders/items
```

Do not accidentally aggregate parent order value belonging to other shops.

Preserve shop isolation.

---

# 27. SUPER ADMIN REPORTS

Super Admin marketplace reports may aggregate all shops.

Still avoid double-counting parent and child values.

---

# 28. CURRENCY

The project currently uses USD storefront/reporting behavior.

Do not introduce KHR conversion in F15 unless current reports already support separate currencies.

If analytics supports USD/KHR separately, preserve that separation.

Never add currencies together without conversion policy.

---

# 29. SETTLEMENT DATE VS ORDER DATE

Important.

A report for:

```text
Revenue in October
```

could mean:

```text
orders created in October
```

or:

```text
payments settled in October
```

Define it.

Recommended:

```text
Gross Demand
→ order creation date

Settled Revenue
→ settlement/payment date
```

if the project supports those semantics.

But inspect current report behavior first.

---

# 30. COD SETTLEMENT DATE

Example:

```text
Order created:
October 30

COD collected:
November 2
```

If report is settled revenue by settlement date:

```text
October settled revenue:
$0

November settled revenue:
includes COD sale
```

Do not arbitrarily assign to creation date unless metric definition says so.

---

# 31. ONLINE PAYMENT DATE

Similarly:

```text
Order created 23:59
Payment verified after midnight
```

settled-sales period should follow documented timestamp semantics.

---

# 32. REFUND DATE

Define refund period behavior.

Example:

```text
Sale:
October $100

Refund:
November $30
```

Possible monthly view:

```text
October Gross Revenue:
$100

November Refunds:
$30

November Net Adjustment:
-$30
```

Do not silently rewrite historical October totals unless the intended report is "current net lifetime state."

Document semantics.

---

# 33. AOV — AVERAGE ORDER VALUE

The audit explicitly requires defining the AOV denominator.

Potential formula:

```text
AOV =
settled revenue / paid orders
```

Recommended if AOV means realized sales.

Do NOT use:

```text
revenue / all created orders
```

while including unpaid/cancelled orders in denominator.

---

# 34. AOV EXAMPLE

Orders:

```text
Order A:
Paid $100

Order B:
Paid $50

Order C:
Unpaid $200
```

If settled AOV:

```text
Revenue:
$150

Paid Orders:
2

AOV:
$75
```

Not:

```text
$50
```

from dividing by all 3 orders.

---

# 35. FULL REFUND AND AOV

Define whether a fully refunded order remains in:

```text
paid order count
```

for gross AOV versus net AOV.

Do not guess.

If necessary expose:

```text
Gross AOV
Net AOV
```

but avoid metric proliferation unless UI requires it.

---

# 36. PARTIAL REFUND AND AOV

If AOV uses net revenue:

```text
partially refunded order
```

may remain denominator `1` while numerator is reduced.

Document.

---

# 37. ORDER COUNT

Do not label:

```text
all created orders
```

as:

```text
Sales
```

if unpaid/cancelled orders are included.

Use explicit names such as:

```text
Orders Created
Paid Orders
Completed Sales
```

according to chosen definitions.

---

# 38. PRODUCT UNITS

Product ranking such as:

```text
Top Selling Products
```

should use settled-sale quantities if "selling" means actual sales.

Pending unpaid orders should not make a product appear best-selling.

---

# 39. PRODUCT REVENUE

Similarly:

```text
Top Products by Revenue
```

must use consistent paid/refund criteria.

---

# 40. PAYMENT REPORT

Payment report should reconcile with settled revenue.

Concept:

```text
successful payment collections
-
refunds
=
net collected cash
```

subject to actual payment model.

---

# 41. COD PAYMENT REPORT

After F04:

```text
COD pending:
not settled

COD collected:
settled
```

Report accordingly.

---

# 42. FAILED PAYMENT

Failed payments must contribute:

```text
$0
```

to settled revenue.

They may still appear in payment failure counts.

---

# 43. EXPIRED PAYMENT

Expired payment:

```text
$0 settled revenue
```

unless a verified late payment later transitions successfully.

Preserve existing payment state rules.

---

# 44. PARTIAL PAYMENT

If partial payment is supported:

define whether:

```text
settled revenue
```

includes actually collected partial amount or only fully paid order amount.

Do not invent behavior.

Inspect existing Payment model.

---

# 45. REFUND STATUS

Differentiate:

```text
refund requested
refund approved
refund processing
refund completed
```

Only actual completed refund should normally reduce realized net cash.

Use existing lifecycle.

---

# 46. CANONICAL REPORTING SERVICE

Avoid duplicating criteria in:

```text
AdminReportController
FinancialMetricService
CSV export
DashboardController
SellerReportController
```

Preferred:

```text
ReportingMetricService
```

or shared query scopes/helpers.

Do not create unnecessary abstraction if FinancialMetricService can be extended cleanly.

---

# 47. QUERY SCOPES

Useful concepts may include:

```text
settled()
paid()
nonCancelled()
refunded()
```

but use current model architecture.

Central goal:

```text
same criteria everywhere
```

---

# 48. NO FRONTEND CALCULATION OF FINANCIAL METRICS

Vue should not recompute:

```text
revenue
AOV
units sold
refund totals
```

from raw rows if backend already provides report metrics.

Backend is source of truth.

---

# 49. REPORT SUMMARY CARDS

Inspect every card.

Example desired explicit labels:

```text
Settled Revenue
Paid Orders
Units Sold
Refunded Amount
Average Order Value
```

If UI currently just says:

```text
Revenue
```

document what that means.

---

# 50. CHARTS

Chart series must use same semantic definitions as cards.

Bad:

```text
Card:
Paid revenue

Chart:
All order totals
```

unless clearly labeled:

```text
Gross Demand
```

---

# 51. CSV EXPORT

CSV must reconcile with the screen for same filters and metric definitions.

Example:

```text
Date Range:
2026-10-01 → 2026-10-31

Screen Settled Revenue:
$10,000
```

Corresponding CSV rows/summary should reconcile to:

```text
$10,000
```

where representing same metric.

---

# 52. F16 SCOPE BOUNDARY

Do NOT start F16.

F16 will handle:

```text
export date validation
streaming/chunking
memory limits
large PDF behavior
```

For F15, only ensure exported metric values/criteria are correct.

---

# 53. PDF REPORT

If PDF reports show same KPIs:

make their financial definitions consistent.

Do not optimize PDF memory in F15.

---

# 54. FINANCIAL ANALYTICS

The project has:

```text
FinancialMetricService
```

and business forecasting.

Historical analytics must use the same revenue/profit semantics.

Do not let:

```text
Admin Revenue Report
```

say `$100`

while:

```text
Financial Analytics Revenue
```

says `$0`

for the same scope unless they intentionally represent different named metrics.

---

# 55. BUSINESS FORECAST INPUTS

Forecasts depend on historical data.

If forecast metric is:

```text
revenue
```

define whether it means:

```text
settled/net revenue
```

or gross demand.

Do not train forecasts on unpaid orders while report cards call revenue paid-only.

---

# 56. PROFIT

Profit should use the same eligible revenue basis as historical analytics.

Concept:

```text
Net Revenue
- COGS
- Expenses
= Profit
```

Use current implemented formulas.

Do not redesign N01–N09 broadly.

---

# 57. COGS

If an item is unpaid/cancelled:

determine whether it should contribute COGS.

Usually settled-sale COGS should correspond to recognized sold units.

Do not count COGS for an order that never became a sale unless separate demand/cancellation analytics intentionally do so.

---

# 58. REFUND COGS

If returned inventory is restocked:

profit analytics may need different COGS reversal behavior.

Inspect current implementation.

Do not invent accounting treatment without source support.

Document any remaining limitation.

---

# 59. EXPENSES

Business expenses are separate from order settlement.

Do not change expense ledger semantics merely to fix report revenue.

---

# 60. DATE FILTERS

Use current validated date filtering behavior for report summary.

Do not alter parsing rules in F15 unless needed for consistent metric queries.

F16 handles export validation gaps.

---

# 61. STATUS FILTERS

If report UI filters:

```text
payment status
order status
shop
product
date range
```

summary cards, charts, tables, and exports should apply the same relevant filters.

---

# 62. PAYMENT FILTER

Example:

```text
Payment Method = COD
```

Revenue card should show settled COD revenue under same filter.

Do not accidentally include Bakong.

---

# 63. SHOP FILTER

Super Admin selects:

```text
Shop A
```

All metrics should be Shop A-only.

No parent-order amount belonging to Shop B.

---

# 64. PRODUCT FILTER

Product-level report should filter items.

Do not use parent order total as product revenue.

---

# 65. CANCELLED ITEM

If order/item is cancelled:

do not count as settled units/revenue.

---

# 66. PARTIAL ORDER CANCELLATION

If individual items can be cancelled:

report only valid settled items according to actual architecture.

Do not assume whole-order cancellation.

---

# 67. RETURNED ITEM

Define:

```text
Units Sold
```

after a completed return.

Possible metrics:

```text
Gross Units Sold
Returned Units
Net Units Sold
```

If existing UI only has `Units Sold`, choose and document whether it is gross or net.

---

# 68. RECONCILIATION EQUATIONS

Document equations where applicable.

Example:

```text
Gross Settled Revenue
-
Completed Refunds
=
Net Revenue
```

and:

```text
AOV
=
Net Revenue / Eligible Paid Orders
```

or the chosen actual formula.

---

# 69. MONEY PRECISION

Use database decimals / existing money helpers.

Do not aggregate using unsafe binary floating point.

Example:

```text
19.99
+
29.99
```

must reconcile exactly according to persisted precision.

---

# 70. ROUNDING

Define when rounding occurs.

Preferred:

```text
aggregate precise persisted monetary values
→ format at display boundary
```

Do not round each row differently across CSV/chart/card unless required.

---

# 71. MULTI-SHOP DISCOUNT ALLOCATION

Preserve F03.

Marketplace parent:

```text
discount = sum(shop discounts)
```

Seller report must use only shop-specific allocated discount.

---

# 72. SHIPPING ALLOCATION

Seller report should use seller's shop-order shipping amount if shipping revenue is reported.

Do not allocate other shops' delivery fees.

---

# 73. TAX ALLOCATION

Same principle if tax is shop/order allocated.

Do not double count parent + child tax.

---

# 74. REPRODUCTION TEST — PENDING UNPAID

Critical F15 test:

```text
Order:
$100

Payment:
pending

Item:
qty 1
```

Expected settled metrics:

```text
Revenue:
0

Items Revenue:
0

Units Sold:
0
```

If demand metrics exist:

```text
Gross Demand:
100

Units Ordered:
1
```

---

# 75. PAID ORDER TEST

```text
Order:
$100

Payment:
paid

qty:
1
```

Expected:

```text
Settled Revenue:
$100

Units Sold:
1

Paid Orders:
1
```

subject to tax/shipping definition.

---

# 76. CANCELLED UNPAID TEST

```text
$100
unpaid
cancelled
```

Expected:

```text
Settled Revenue:
0

Units Sold:
0
```

---

# 77. FAILED PAYMENT TEST

```text
$100
payment failed
```

Expected settled sales:

```text
0
```

---

# 78. EXPIRED PAYMENT TEST

```text
$100
payment expired
```

Expected settled sales:

```text
0
```

---

# 79. COD PENDING TEST

```text
COD
delivered or in transit
cash not collected
```

Expected settled revenue according to F04:

```text
0
```

until collection is recorded.

---

# 80. COD COLLECTED TEST

After collection:

```text
Settled Revenue:
includes order

Units Sold:
includes items
```

---

# 81. FULL REFUND TEST

```text
Paid:
$100

Completed refund:
$100
```

Verify:

```text
Gross Revenue
Refunded Amount
Net Revenue
```

according to documented formula.

---

# 82. PARTIAL REFUND TEST

```text
Paid:
$100

Completed refund:
$30
```

Expected if using net model:

```text
Gross:
$100

Refunded:
$30

Net:
$70
```

---

# 83. REFUND PENDING TEST

If refund is only requested/pending:

do not reduce realized net cash until business rules say refund is completed.

---

# 84. MULTI-SHOP TEST

Parent:

```text
Shop A:
$100 paid

Shop B:
$50 unpaid
```

Marketplace settled revenue:

```text
$100
```

Seller A:

```text
$100
```

Seller B:

```text
$0
```

No double counting.

---

# 85. MIXED PAYMENT TEST

If supported:

```text
Shop A Bakong paid
Shop B COD pending
```

settled revenue should include only paid component.

---

# 86. AOV TEST

Fixtures:

```text
Paid A:
$100

Paid B:
$50

Unpaid C:
$200
```

Expected settled AOV if chosen:

```text
$75
```

---

# 87. UNITS SOLD TEST

Fixtures:

```text
Paid item qty 2

Unpaid item qty 5

Cancelled item qty 3
```

Expected settled units sold:

```text
2
```

---

# 88. TOP PRODUCT TEST

Product A:

```text
10 unpaid units
```

Product B:

```text
3 paid units
```

If chart is:

```text
Top Selling Products
```

Product B should rank above A.

---

# 89. CARD/CSV RECONCILIATION TEST

Run identical filters.

Assert:

```text
screen revenue
=
CSV equivalent revenue sum
```

for same metric.

---

# 90. CARD/ANALYTICS RECONCILIATION TEST

For same date/currency/definition:

```text
Admin report settled revenue
=
FinancialMetricService settled revenue
```

unless intentionally different.

If different:

labels must explicitly explain why.

---

# 91. PAYMENT RECONCILIATION

For settled-sales report:

```text
Revenue
```

should reconcile with successful collected payment amounts adjusted by refund policy.

Document any legitimate differences caused by:

```text
tax
shipping
fees
```

---

# 92. REPORT LABELS

Rename ambiguous UI labels if necessary.

Possible:

```text
Revenue
→ Settled Revenue

Sales
→ Paid Orders

Items Sold
→ Settled Units Sold
```

Only do this when needed for clarity.

---

# 93. OPTIONAL DEMAND METRICS

If stakeholders need pending order visibility:

add/use explicitly named:

```text
Gross Order Demand
Pending Order Value
Units Ordered
```

Do NOT contaminate settled-sales metrics.

---

# 94. DO NOT DELETE USEFUL PENDING DATA

The fix is not:

```text
ignore all pending orders everywhere
```

Pending orders are useful operational data.

They simply must not be mislabeled as settled sales.

---

# 95. CENTRALIZED STATUS POLICY

Avoid hardcoded arrays everywhere:

```php
['paid', 'completed', ...]
```

in multiple controllers.

Use shared scopes/constants/service logic where practical.

---

# 96. ENUMS / CONSTANTS

If project has payment/order enums:

reuse them.

Do not compare magic strings inconsistently.

---

# 97. DATABASE QUERY EFFICIENCY

Use aggregate queries.

Avoid loading every order into PHP just to calculate:

```text
SUM
COUNT
AVG
```

where SQL can do it safely.

---

# 98. N+1

Product/shop report queries must avoid N+1 payment/refund lookups.

Inspect query plan where needed.

---

# 99. INDEX REVIEW

Review existing indexes for report filters:

```text
orders.created_at
orders.status
orders.payment_status
shop_orders.shop_id
payments.status
payments.paid_at
refunds.status
refunds.completed_at
order_items.product_id
```

Only add indexes when justified.

---

# 100. HISTORICAL SNAPSHOTS

Use persisted:

```text
order item price
unit cost
discount allocation
```

for historical reports.

Do not use current Product price/cost.

---

# 101. PRODUCT DELETED LATER

Historical sale should remain in reports even if product is later deleted.

Use order snapshots.

---

# 102. SHOP DEACTIVATED LATER

Historical marketplace reporting should preserve past valid settled sales.

Do not erase history because current shop is inactive.

---

# 103. BACKEND TESTS

Add focused reporting tests for:

```text
pending unpaid
paid
cancelled
failed payment
expired payment
COD pending
COD collected
full refund
partial refund
multi-shop mixed settlement
AOV denominator
units sold
product revenue
```

---

# 104. FINANCIAL ANALYTICS TESTS

Verify:

```text
revenue
profit
loss
orders
AOV
```

use compatible definitions.

Do not alter forecasting model algorithms unless input definitions require correction.

---

# 105. FRONTEND TESTS

Verify report UI renders:

```text
correct values
correct labels
correct empty state
correct refund values
correct AOV
```

No client-side recomputation mismatch.

---

# 106. BROWSER / E2E — CRITICAL F15 REPRODUCTION

Create:

```text
pending unpaid order
item value $100
qty 1
```

Open reports.

Verify:

```text
Settled Revenue:
$0

Items Revenue:
$0
or explicitly named demand value if intentionally different

Units Sold:
0
```

No contradictory sales labels.

---

# 107. BROWSER — PAID TRANSITION

Change payment through valid lifecycle:

```text
pending
→ paid
```

Reload report.

Expected metrics update consistently.

---

# 108. BROWSER — COD TRANSITION

Use F04:

```text
COD pending
→ record collection
```

Report changes from:

```text
unsettled
```

to:

```text
settled
```

correctly.

---

# 109. BROWSER — REFUND

If existing E2E fixtures support it:

```text
paid $100
→ refund $30
```

verify net/refund metrics.

---

# 110. CACHE INVALIDATION

If reports/analytics are cached:

payment/refund transitions must invalidate or refresh relevant cache.

Do not let corrected DB logic remain invisible due to stale cache.

---

# 111. F01 REGRESSION

Coupon ownership unaffected.

---

# 112. F02 REGRESSION

Cart recovery unaffected.

---

# 113. F03 REGRESSION

Critical:

```text
persisted totals
discount caps
shop allocations
```

remain correct.

---

# 114. F04 REGRESSION

Critical:

```text
COD collected state
```

must drive settled revenue correctly.

---

# 115. F05 REGRESSION

Guest checkout orders should report according to payment state just like authenticated orders.

---

# 116. F06 REGRESSION

Catalog filters unaffected.

---

# 117. F07 REGRESSION

Mobile color filters unaffected.

---

# 118. F08 REGRESSION

Async checkout handling unaffected.

---

# 119. F09 REGRESSION

Product descriptions unaffected.

---

# 120. F10 REGRESSION

Review aggregates unaffected.

---

# 121. F11 REGRESSION

Low-stock notifications unaffected.

---

# 122. F12 REGRESSION

Coupon validation unaffected.

---

# 123. F13 REGRESSION

Blocked abusive checkout attempts must not appear in financial reports because they must not create orders/reservations.

---

# 124. F14 REGRESSION

Order-number retry must not duplicate orders or report values.

Forced collision followed by success should count exactly one sale/order.

---

# 125. DOCUMENT METRIC DEFINITIONS

Add/update documentation with a table like:

```text
Metric:
Settled Revenue

Definition:
Successfully collected revenue adjusted by completed refunds

Includes:
Verified online payments
Collected COD

Excludes:
Pending payments
Failed payments
Expired payments
Cancelled unpaid orders
```

Do the same for:

```text
Gross Demand
Units Sold
Paid Orders
Refunds
AOV
```

---

# 126. DO NOT START F16

Only solve F15.

Do NOT start:

```text
CSV streaming
PDF size limits
export memory optimization
export date validation
```

Those belong to:

```text
F16 — Export Validation & Bounded Memory
```

---

# 127. DOCKER ONLY

Use project Docker environment for:

```text
PHP
Composer
MySQL
Redis if reporting cache requires it
Node
tests
build
```

Do not wipe development data.

---

# 128. TYPE CHECK

Run frontend TypeScript checks.

Do not bypass financial report types with:

```text
any
@ts-ignore
```

---

# 129. PRODUCTION BUILD

Run frontend production build.

Must pass.

---

# 130. DEFINITION OF DONE

Do not mark F15 complete until:

```text
[ ] Metric dictionary is documented

[ ] Gross demand and settled sales are distinguished

[ ] Pending unpaid orders do not count as settled revenue

[ ] Pending unpaid items do not count as settled units sold

[ ] Cancelled unpaid orders do not count as settled sales

[ ] Failed payments do not count

[ ] Expired payments do not count

[ ] Collected COD counts correctly

[ ] Uncollected COD does not count as settled revenue

[ ] Full refund behavior is defined

[ ] Partial refund behavior is defined

[ ] Net revenue formula is defined

[ ] Items revenue definition is explicit

[ ] Units sold definition is explicit

[ ] Orders count definition is explicit

[ ] AOV numerator is defined

[ ] AOV denominator is defined

[ ] Multi-shop aggregation does not double count

[ ] Seller reports remain shop-isolated

[ ] Product reports use settled criteria

[ ] Top-selling products use correct units/revenue

[ ] Cards and charts use compatible definitions

[ ] CSV values reconcile with screen for same metrics

[ ] Payment reports reconcile

[ ] FinancialMetricService reconciles

[ ] Forecast history uses correct revenue semantics

[ ] Historical snapshots are used

[ ] Monetary precision is safe

[ ] Cache invalidation works if applicable

[ ] Critical pending-$100 reproduction passes

[ ] Paid transition test passes

[ ] COD transition test passes

[ ] Refund tests pass

[ ] Backend tests pass

[ ] Frontend tests pass

[ ] Browser regression passes

[ ] Type check passes

[ ] Production build passes

[ ] F01–F14 regressions pass
```

---

# 131. FINAL REPORT

At completion report exactly:

```text
F15 — REPORT METRIC CONSISTENCY

Status:
PASS / NEEDS WORK

Root Cause:

Old Reporting Behavior:

New Reporting Model:

Gross Order Demand Definition:

Settled Revenue Definition:

Net Revenue Definition:

Items Revenue Definition:

Units Ordered Definition:

Units Sold Definition:

Orders Created Definition:

Paid Orders Definition:

Refunded Amount Definition:

AOV Formula:

AOV Denominator:

Tax Treatment:

Shipping Treatment:

Discount Treatment:

Pending Payment Treatment:

Failed Payment Treatment:

Expired Payment Treatment:

COD Pending Treatment:

COD Collected Treatment:

Cancelled Order Treatment:

Full Refund Treatment:

Partial Refund Treatment:

Settlement Date Rule:

Refund Date Rule:

Multi-Shop Aggregation Strategy:

Seller Isolation:
PASS / FAIL

Admin Report / Payment Reconciliation:
PASS / FAIL

Admin Report / Financial Analytics Reconciliation:
PASS / FAIL

CSV Reconciliation:
PASS / FAIL

Forecast Historical Data Alignment:
PASS / FAIL

Critical Pending $100 Fixture:

Expected:
Revenue = ...
Items Revenue = ...
Units Sold = ...

Actual:
Revenue = ...
Items Revenue = ...
Units Sold = ...

Files Changed:

Schema Changes:
None / List

Indexes Added:
None / List

Metric Documentation Added:
YES / NO

Tests Added:

Backend Tests:
Passed / Failed

Financial Analytics Tests:
Passed / Failed

Frontend Tests:
Passed / Failed

Browser Tests:
Passed / Failed / N/A

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

Remaining Risks:

Related Follow-Up:
F16 — Export Validation & Bounded Memory
```

Also provide this before/after example:

```text
BEFORE

Order:
$100

Payment:
Pending

Quantity:
1

Report:

Revenue:
$0

Items Revenue:
$100

Units Sold:
1

Problem:
The same transaction is treated as unpaid in one metric
but as a completed sale in other metrics.


AFTER

Order:
$100

Payment:
Pending

Settled Revenue:
$0

Settled Items Revenue:
$0

Units Sold:
0

Optional demand metrics:

Gross Order Demand:
$100

Units Ordered:
1


After valid payment:

Settled Revenue:
$100

Settled Items Revenue:
$100

Units Sold:
1
```

Stop after F15.

Do NOT automatically begin F16.

I will review the result first.