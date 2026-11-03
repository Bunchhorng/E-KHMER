# F15 — Report Metric Consistency

## Metric dictionary

| Metric | Definition |
| --- | --- |
| Gross Order Demand | Total of non-cancelled, non-refunded orders by placement date, whether paid or not. |
| Settled Revenue | Verified online payment amount or successful COD collection, before completed refunds. |
| Net Revenue | Settled Revenue less completed payment refunds. |
| Items Revenue | Settled merchandise subtotal less persisted allocation discounts and completed refund allocation. It excludes tax and shipping. |
| Units Ordered | Item quantities on non-cancelled, non-refunded orders. |
| Units Sold | Quantities in settled allocations, less completed returned quantities; a fully refunded allocation contributes zero. |
| Orders Created | All orders in the placement-date range. |
| Paid Orders | Distinct parent orders with at least one settled allocation. |
| Cancelled Orders | Orders whose status is cancelled. |
| Refunded Amount | Completed refund amount allocated to the settled order/shop allocation. |
| AOV | Net Revenue divided by Paid Orders. |

All report and financial-history dates use order placement time. This preserves the existing historic-placement reporting behavior: later collections and refunds restate the original order period. The report keeps tax and shipping in settled/net collection totals, while FinancialMetricService forecast revenue uses Items Revenue because tax and shipping are not trading revenue.

Online sales require a completed or refunded payment record. COD sales require a successful collection transaction for the relevant shop order. Pending, failed, expired, and uncollected COD payments contribute no settled revenue, item revenue, or sold units. A payment refund is a completed-refund record: pending return workflows do not reduce these metrics.

The canonical implementation is FinancialMetricService. It allocates multi-shop totals and refunds per shop order, so marketplace totals do not add parent orders and shop allocations together. Product CSVs, report cards, report charts, and financial analytics use its settled allocation policy.

## Reconciliation

Net Revenue = Settled Revenue - Refunded Amount

AOV = Net Revenue / Paid Orders

For a pending $100 order with one item, Gross Order Demand is $100 and Units Ordered is 1; Settled Revenue, Items Revenue, and Units Sold are all 0. Once a verified payment succeeds, each settled metric becomes $100/$100/1.
