# F15 — REPORT METRIC CONSISTENCY

Status: PASS

Root Cause: Admin reports used different payment and status rules for revenue, items, and units.

Old Reporting Behavior: Pending unpaid items counted as item revenue and sold units while revenue remained zero.

New Reporting Model: FinancialMetricService is the shared source for settlement, completed refunds, item revenue, product rankings, CSV rows, and financial-history inputs.

Gross Order Demand Definition: Valid non-cancelled, non-refunded order totals by placement date.

Settled Revenue Definition: Verified online payment or collected COD, before completed refunds.

Net Revenue Definition: Settled Revenue minus completed refunds.

Items Revenue Definition: Settled merchandise after persisted discount and refund allocation; excludes tax and shipping.

Units Ordered Definition: Quantities in valid created orders.

Units Sold Definition: Quantities in settled allocations, less completed returned quantities.

Orders Created Definition: All orders in the selected placement-date range.

Paid Orders Definition: Distinct parent orders with at least one settled allocation.

Refunded Amount Definition: Completed refunds allocated to the relevant order or shop allocation.

AOV Formula: Net Revenue / Paid Orders.

AOV Denominator: Paid Orders.

Tax Treatment: Included in settled/net collection totals; excluded from merchandise and forecast revenue.

Shipping Treatment: Included in settled/net collection totals; excluded from merchandise and forecast revenue.

Discount Treatment: Uses persisted order and shop-order discount snapshots.

Pending Payment Treatment: Demand only; no settled revenue, item revenue, or units sold.

Failed Payment Treatment: No settled metrics.

Expired Payment Treatment: No settled metrics.

COD Pending Treatment: No settled metrics.

COD Collected Treatment: The collected shop allocation becomes settled.

Cancelled Order Treatment: Unpaid cancellations are excluded from settled metrics.

Full Refund Treatment: Gross settlement remains visible; net revenue and items revenue are reduced by the completed refund.

Partial Refund Treatment: Gross settlement remains visible; net revenue and items revenue are reduced by the completed amount.

Settlement Date Rule: Placement date, preserving existing historic-period reporting.

Refund Date Rule: Completed refunds restate the original placement period.

Multi-Shop Aggregation Strategy: Aggregate shop allocations only; never add a parent order total and its shop totals.

Seller Isolation: PASS

Admin Report / Payment Reconciliation: PASS

Admin Report / Financial Analytics Reconciliation: PASS for settled merchandise; report collection totals intentionally retain tax and shipping.

CSV Reconciliation: PASS

Forecast Historical Data Alignment: PASS

Critical Pending $100 Fixture:

Expected:
Revenue = 0
Items Revenue = 0
Units Sold = 0

Actual:
Revenue = 0
Items Revenue = 0
Units Sold = 0

Files Changed:

- FinancialMetricService
- AdminReportController
- AdminReportsView and report API types
- Report metric documentation and tests

Schema Changes: None

Indexes Added: None

Metric Documentation Added: YES

Tests Added: ReportMetricConsistencyTest

Backend Tests: Passed

Financial Analytics Tests: Passed

Frontend Tests: Passed through production build type checking

Browser Tests: N/A

Type Check: PASS

Production Build: PASS

F01 Regression: PASS

F02 Regression: PASS

F03 Regression: PASS

F04 Regression: PASS

F05 Regression: PASS

F06 Regression: PASS

F07 Regression: PASS

F08 Regression: PASS

F09 Regression: PASS

F10 Regression: PASS

F11 Regression: PASS

F12 Regression: PASS

F13 Regression: PASS

F14 Regression: PASS

Remaining Risks: Category names derive from the current product relation because order-item snapshots do not retain category data.

Related Follow-Up: F16 — Export Validation & Bounded Memory

## Before and after

BEFORE

Order: $100

Payment: Pending

Quantity: 1

Report: Revenue $0, Items Revenue $100, Units Sold 1.

AFTER

Order: $100

Payment: Pending

Settled Revenue: $0

Settled Items Revenue: $0

Units Sold: 0

Optional demand metrics: Gross Order Demand $100, Units Ordered 1.

After valid payment: Settled Revenue $100, Settled Items Revenue $100, Units Sold 1.
