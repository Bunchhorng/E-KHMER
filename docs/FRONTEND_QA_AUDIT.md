# Frontend, Customer Website & QA Audit

Date: 2026-10-06

## Completed customer-flow work

- Product pages retrieve approved reviews, gate writing reviews behind sign-in, and surface server validation failures.
- Customers can now edit or delete their own reviews from **Account → Reviews**. Editing an approved review returns it to moderation and removes its old score from the public aggregate until it is approved again.
- The missing `POST /account/notifications/all/read` route was added, matching the existing customer UI request.
- The orders list has a visible, retryable request failure state.
- Order confirmation and tracking no longer fabricate a delivery date from the order date. They show a confirmed delivery timestamp only when the shipment has one.
- Receipt-download failures are visible to the customer.
- Unmatched client-side routes show a dedicated 404 screen instead of silently sending customers to the home page.

## API contract checks

| Flow | Result |
| --- | --- |
| Auth / account access | Protected account routes redirect unauthenticated visitors to sign-in. |
| Catalog / product detail | Uses catalog and approved-review endpoints; unavailable product errors render the existing empty state. |
| Cart / checkout | Uses the centralized Axios client and server totals. |
| Orders / receipt / tracking | Supports authenticated and guest order reads; receipt stays authenticated. |
| Review ownership | Update and delete are ownership-scoped server-side; another customer receives 404. |
| Notifications | Individual read, mark-all-read, and delete endpoints are available under the authenticated account scope. |

## Verification status

- `git diff --check`: passed.
- Laravel feature test added for customer review deletion and rating recalculation.
- Docker-based Laravel and Vue build/test commands were not run because Docker execution permission was declined in this workspace. Browser, accessibility, mobile-layout, and payment-provider QA remain to be executed in the running containers.

## Remaining product decisions

- Review-image uploads need a dedicated customer-facing media policy and storage lifecycle before enabling them; existing image uploads are administrative only.
- Shipment ETA is not supplied by the API. Add an explicit carrier ETA field before labeling any future date as an estimate.
