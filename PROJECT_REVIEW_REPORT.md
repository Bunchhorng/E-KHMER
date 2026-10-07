# E-KHMER Project Review

Reviewed: 7 October 2026  
Scope: repository structure, application architecture, security-sensitive code paths, test coverage, Docker setup, and the Shop Owner administration feature.

## Executive summary

E-KHMER has a sound full-stack foundation: Laravel 13/Sanctum, Vue 3/TypeScript, Docker Compose, and a broad automated backend test suite. The core business domains are well represented (catalog, cart, checkout, inventory, promotions, fulfillment, reviews, shops, and admin operations).

The main risks are operational rather than architectural: development credentials and management tools are exposed through the default Compose file, frontend test coverage is absent, and the newer shop-member management endpoints need transactional handling and dedicated tests before production release.

## What is working well

- Clear separation between `backend`, `frontend`, and Docker infrastructure.
- Laravel conventions are broadly followed, including API resources, dedicated controllers, models, migrations, and route middleware.
- Admin endpoints are grouped behind Sanctum authentication and the `admin` middleware alias.
- The application includes 23 backend test files covering key workflows such as auth, cart, checkout, inventory, admin operations, shops, ownership, and scheduling.
- The frontend uses Vue Composition API, TypeScript, Pinia, Vue Router, Axios, and reusable UI components.
- The scheduler service is documented for inventory-reservation expiry, which protects checkout correctness.
- Shop Owner management supports listing, filtering, create, edit, shop reassignment, status management, and access removal while preserving the underlying user account.

## Findings and recommendations

### High priority

1. Do not use the default Docker credentials outside local development.

   `docker-compose.yml` contains static MySQL/root credentials and exposes MySQL, Redis, and phpMyAdmin ports to the host. Keep this Compose file local-only; use environment-managed secrets, disable phpMyAdmin, and do not publish MySQL/Redis ports in staging or production.

2. Make Shop Owner creation/update transactional.

   `AdminShopMemberController::store()` creates a `users` record and then a `shop_users` record without a database transaction. If the membership insert fails, an orphaned account remains. Wrap the create flow in `DB::transaction()`. Use the same protection for updates that change a shop assignment.

3. Add tests for Shop Owner management.

   Add feature tests for the new list/show/create/update/remove endpoints: admin authorization, validation, duplicate-email behaviour, reassignment conflict, password preservation when blank, status filtering, and removal preserving the user account.

### Medium priority

4. Formalize the “Super Admin” authorization model.

   The current global user roles are `admin` and `customer`; there is no distinct `super_admin` role. If all admins are intended to be super admins, document that explicitly. Otherwise add a role/permission layer and apply it to sensitive activities such as owner removal, payment operations, settings, reports, and account management.

5. Improve Shop Owner lifecycle semantics.

   Membership status currently supports `active` and `suspended`, while “pending” is inferred from the related shop status. This works for dashboard filtering, but it conflates shop approval and owner-account approval. Add an explicit membership/account approval status if owners can be approved independently of their shops.

6. Complete the owner overflow menu.

   The table’s three-dot menu currently provides Edit. For full super-admin management, add explicit actions such as suspend/reactivate, remove shop access (with confirmation), view activity, and password-reset invitation. Destructive operations should use a confirmation modal and audit trail.

7. Add frontend automated tests and quality scripts.

   `frontend/package.json` exposes development/build commands only; no lint or test script is defined. Add ESLint, Vue Testing Library/Vitest, and checks for route authorization, API error handling, forms, and admin actions.

### Low priority

8. Normalize text encoding.

   Several command outputs display mojibake (for example `â€”` and `â€º`). Verify source files are UTF-8 without BOM and ensure editor/Git settings preserve that encoding.

9. Consider pagination limits for management pickers.

   The Shop Owner form loads the default page of shops. If the marketplace grows beyond one page, the shop selector will not expose every shop. Add a searchable async selector or a dedicated endpoint for selectable shops.

10. Add audit logging for privileged actions.

    Record the actor, target, action, before/after status, timestamp, and reason for shop approvals, membership changes, removals, refunds, and settings updates.

## Suggested release checklist

- [ ] Move production secrets out of `docker-compose.yml` and restrict infrastructure ports.
- [ ] Wrap Shop Owner create/update flows in database transactions.
- [ ] Add Shop Owner endpoint and UI tests.
- [ ] Decide whether `admin` is synonymous with `super_admin`; enforce the decision in middleware/policies.
- [ ] Add confirmation, audit logging, and notifications for sensitive admin actions.
- [ ] Add frontend linting and test scripts.
- [ ] Run `docker compose exec app php artisan test` and `docker compose exec frontend npm run build` before release.

## Recommended marketplace model

The project should use two authorization layers rather than put every capability in the global `users.role` field.

| Actor | Global account role | Shop membership | Access |
| --- | --- | --- | --- |
| Super Admin | `super_admin` (rename/migrate the current platform `admin`) | None required | All shops, users, catalog, orders, payments, reports, settings, approvals, and audits. |
| Shop Owner | `customer` | `shop_users.role_in_shop = owner` | All management functions for every shop where the membership is active. |
| Shop Manager | `customer` | `shop_users.role_in_shop = manager` | Shop-scoped management, with a permission set that the owner/Super Admin can limit. |
| Shop Staff | `customer` | `shop_users.role_in_shop = staff` | Narrow tasks such as fulfilment and inventory, without owner/payment settings. |
| Customer | `customer` | None required | Browse, cart, checkout, orders, addresses, wishlist, reviews, and profile. |

This preserves the existing design where one person can be both a customer and the owner of one or more shops. A `shop_owner` global role should not be required: ownership belongs to a specific shop membership.

### Required multi-shop improvements

1. **Shop switcher and explicit shop context.**

   The existing seller dashboard selects the first active owner/manager membership. Replace this with a `/seller/shops` selector and routes such as `/seller/shops/{shop}/dashboard`, `/products`, `/orders`, and `/inventory`. Persist the selected shop only after validating the active membership on the server.

2. **Server-side shop scoping.**

   Every seller endpoint must resolve the shop from the route, then authorize `owner`, `manager`, or `staff` membership before any query. Never trust a `shop_id` sent in an unscoped payload. Super Admin may bypass this policy deliberately.

3. **Seller feature set.**

   Each shop dashboard should provide shop-scoped KPIs, product/variant CRUD, stock adjustments, orders/shipments, shop profile, team members, coupons, and sales reports. Owner-only actions should include team roles, payment/payout configuration, and shop settings.

4. **Super Admin feature set.**

   Keep the current platform dashboard, add shop approval/rejection, owner/member management, a shop impersonation/view mode with a prominent banner, global catalog governance, payment/refund oversight, fraud/review moderation, audit logs, and platform-wide reports.

5. **Customer experience.**

   Keep a unified storefront; product cards and checkout should display the selling shop. Define the multi-shop cart rule explicitly: either split the cart/order by shop (recommended for independent shipping/inventory) or restrict each cart to one shop.

### Implementation order

1. Add a `super_admin` role (or formally rename/document the existing `admin` role) and add tests for its middleware.
2. Build `ShopMembershipPolicy`/middleware and a seller route group with `{shop}` route model binding.
3. Build the shop selector and shop-scoped seller dashboard.
4. Scope seller product, inventory, order, and shipment endpoints and add ownership tests for cross-shop access denial.
5. Add team management, audit logging, approval workflow, and admin impersonation/view mode.
6. Decide and implement the multi-shop cart/order model before exposing multi-vendor checkout.

## Verification performed

- Repository and dependency manifests inspected.
- Route/middleware and Shop Owner controller inspected.
- Test inventory reviewed: 23 backend test files and no frontend test files detected.
- `git diff --check` was run during the current working session and reported no whitespace errors.

Docker-based test/build execution was not performed in this review because Docker access was previously declined in this session.

