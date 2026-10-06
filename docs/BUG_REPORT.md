# Customer Website Bug Report

Date: 2026-10-06

## Fixed

| Severity | Issue | Resolution |
| --- | --- | --- |
| High | “Mark all as read” in the customer notifications screen called an endpoint that did not exist. | Added the authenticated `POST /account/notifications/all/read` route. |
| High | Customers had no UI or API endpoint to remove an accidental review. | Added owned-review delete endpoint, account UI action, and rating aggregate recalculation. |
| High | Updating an approved review could leave its published rating in the product aggregate while the review content changed. | Edited approved reviews move back to `pending`; product rating is recalculated immediately. |
| Medium | Customers could not clear an existing review title or body. | Update service now preserves explicit `null` values for nullable text fields. |
| Medium | Tracking invented a delivery date by adding five days to order placement. | Removed fabricated dates; only actual shipment delivery timestamps are displayed. |
| Medium | Order-list and receipt-download failures were silent. | Added retryable list error UI and receipt error feedback. |
| Low | Unknown URLs silently redirected to home. | Added a dedicated 404 route and screen. |

## Open / verification required

| Severity | Finding | Next action |
| --- | --- | --- |
| Medium | Review images are not yet supported by the customer review data model or endpoint. | Define image constraints, add review-media schema/storage policy, then add upload and moderation UI. |
| Medium | The API has no carrier ETA field. | Add a real ETA to shipment data before showing an estimated delivery date. |
| Medium | End-to-end browser and container test suite is unverified. | Run Docker Compose, Laravel feature tests, and `frontend npm run build`; complete manual responsive/accessibility checks. |
