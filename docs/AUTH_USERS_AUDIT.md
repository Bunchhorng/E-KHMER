# Authentication & Users Audit

## Executive Summary

The application has a mature Sanctum token-based authentication flow, scoped customer resources, and an address snapshot at checkout. The highest-priority issue found in this audit was an address default-state race: each default mutation used several independent writes. Those operations are now transactionally serialised per user. The account UI now also matches the API rule that a default address may be deleted and that the next address becomes default.

Container test execution was not completed because Docker command approval was declined during this audit. The status below distinguishes inspected code from verified runtime behaviour.

## Authentication

### Status: PARTIAL — HIGH

### Problems Found

- No server-side account status (`active`, `inactive`, or `suspended`) is present in the users schema, so login cannot enforce that business rule.
- Access tokens do not use refresh-token rotation. This is a product/security-design decision and should be designed end-to-end before being added.

### Changes Made

- No authentication changes were necessary in this pass: registration ignores a supplied role, hashes passwords, normalises email, rate-limits sensitive endpoints, sends verification mail, and revokes tokens on reset/logout.
- The Vue store retains the user once and does not issue a `/me` request on every navigation. Its interceptor clears memory and persisted session state after a 401.

### Tests

- Existing `AuthTest` covers registration, duplicate emails, invalid credentials, `/me`, logout revocation, reset flow, verification, role escalation prevention, token expiry for remember-me, and throttling.
- Not run in this audit; see Final Status.

## Users

### Status: PARTIAL — MEDIUM

### Problems Found

- Admin customer management currently provides a paginated searchable customer list and customer detail only. There is no reviewed admin workflow for changing a customer role/status or safely deactivating an account.

### Changes Made

- None. Existing API resources correctly omit passwords and remember tokens.

### Tests

- Existing admin/auth tests cover admin-route access and role protection; runtime execution remains pending.

## Addresses

### Status: IMPLEMENTED — HIGH

### Problems Found

- Creating, updating, deleting, and setting a default address used multiple writes outside a transaction. Concurrent requests could leave more than one default address or incorrectly identify two simultaneous first addresses as default.
- The customer UI blocked deletion of the default address, contradicting the server’s safe promotion rule and adding unnecessary friction.

### Changes Made

- Wrapped all default-affecting address mutations in database transactions.
- Lock the owning user before address changes, including the empty-address case, to serialise all default-address mutations for that customer.
- Lock the targeted address inside the transaction and scope it through the authenticated user.
- Permit deletion of a default address in the UI; the server promotes the most recent remaining address. The UI now prevents duplicate deletion requests and shows an error if deletion fails.
- Added a regression test for deleting a customer’s only default address.

### Tests

- Existing `AddressTest` covers guest protection, owner-only listing/mutation, ignored `user_id`, validation, default selection, promotion, and cross-user IDOR prevention.
- Added `test_deleting_the_only_default_address_leaves_no_default_address`.
- Not run in this audit.

## Business Logic Improvements

- Address ownership is determined from the authenticated user, never from a request `user_id`.
- Checkout persists an address snapshot on the order and shipment, so deleting a saved address does not erase historical shipping information.
- Orders are resolved through the authenticated user’s relationship, preventing order-number IDOR.
- Cart merge hooks run on registration and login.

## User Experience Improvements

- Default-address deletion is now supported consistently with the backend behaviour.
- Deletion uses a per-address in-flight state, preventing double submission.

## Security Improvements

- Default-address invariant is protected against concurrent customer actions with a database transaction and row lock.
- Admin endpoints have both Sanctum authentication and backend admin middleware.
- Auth request throttles are applied to login, registration, password reset, and verification email resend.

## Performance Improvements

- No new query fan-out was introduced. Address default mutations use a short transaction scoped to a single user.
- The admin customer list is paginated; customer order lists are paginated.

## Project Integration

- Public product browsing, cart, and checkout routes are available without route-level authentication.
- Authenticated account routes use route guards; backend ownership checks remain the enforcement layer.
- Guest cart session IDs are attached centrally by the Axios client and are used by the authentication cart-merge flow.

## Git Review

- `backend/.gitignore` and `frontend/.gitignore` ignore `.env` files and common generated artefacts.
- A tracked-file-name scan found no tracked `.env`, PEM, or key file names.
- Working tree contained the prompt document as an untracked file before this audit; it was preserved.

## Code Quality Review

- The backend uses form requests, resources, policies/middleware, and services in the expected Laravel layers.
- Address behaviour remains in its controller because it is small and resource-specific; the transaction and lock make the invariant explicit without adding an unnecessary abstraction.

## Bugs Fixed

- Race condition in default-address state transitions.
- Customer UI incorrectly disallowing deletion of a default address.
- Potential duplicate delete requests from repeated clicks.

## Remaining Issues

- Add a product-approved account-status model and login restriction policy before introducing suspension/deactivation controls.
- Decide whether token rotation is required; implementation needs API and frontend coordination.
- Add a dedicated address detail endpoint if editing via a full address-list fetch becomes a measured performance issue.
- Run the specified backend test suite and frontend type/build checks in Docker once command access is approved.

## Recommended Future Improvements

- Add concurrency/integration coverage that submits competing default-address requests using separate database connections.
- Add explicit customer status filters and safe account-deactivation actions to the admin interface if required by operations.
- Establish a consistent top-level API response envelope only through a planned API-versioning pass; current resource responses are intentionally preserved.

## Final Status

| Area | Status |
| --- | --- |
| Authentication | NEEDS WORK (runtime verification pending; account status absent) |
| Users | NEEDS WORK (no safe admin lifecycle controls) |
| Addresses | PASS (code reviewed; runtime verification pending) |
| Authorization | PASS (code reviewed; runtime verification pending) |
| Business Logic | PASS (address invariant fixed; runtime verification pending) |
| User Experience | PASS (default-address deletion aligned) |
| Security | PASS (code reviewed; runtime verification pending) |
| Performance | NEEDS WORK (no runtime profiling performed) |
| Project Integration | PASS (code reviewed; runtime verification pending) |
| Git Management | PASS (ignore/tracked-name review) |
| Code Quality | PASS (code reviewed) |
| Automated Tests | NEEDS WORK (not run) |

### Summary

- Critical bugs fixed: default-address mutation race.
- Security issues fixed: concurrent address mutations are serialised per owner.
- Business logic improved: exactly one default remains whenever the customer has addresses; deleting the last address leaves none.
- UX improvements: supported default-address deletion and duplicate-submit protection.
- Tests added: one regression case for deletion of the only default address.
- Remaining issue: Docker-based test and build verification requires command approval.
