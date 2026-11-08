# E-KHMER UX/UI Guide

Updated: 2026-11-08

## Purpose

This guide records the current E-KHMER user-experience and interface decisions for the Vue storefront, customer account, seller workspace, and administrator dashboard. Use it when designing a new screen, reviewing an interface change, or performing responsive QA.

The source of truth for UI implementation is `frontend/src/`. This document describes intended behavior; it does not replace the API, architecture, or commerce operating guides.

## Product experience

E-KHMER is a marketplace where customers can discover products and shops, purchase from one or more sellers, select delivery per shop, and pay by cash on delivery or Bakong/KHQR. The interface also supports customers after purchase and gives sellers and platform administrators operational tools.

The experience should feel:

- Clear: prices, stock, delivery choices, order states, and payment status are explicit.
- Trustworthy: payment instructions, order summaries, validation feedback, and status changes are visible before an irreversible action.
- Fast: common actions are one or two steps away; loading, empty, error, and retry states are always understandable.
- Inclusive: controls are keyboard reachable, text remains readable in Khmer and English, and layouts work from narrow phones to large desktop screens.

## Application areas

| Area | Main journeys | Main routes |
| --- | --- | --- |
| Storefront | Browse, search, filter, product selection, cart, checkout | `/`, `/shop`, `/products/:slug`, `/cart`, `/checkout` |
| Public shops | Discover a seller, browse a seller catalogue | `/shops`, `/shops/:slug` |
| Customer account | Profile, addresses, orders, reviews, returns, support | `/account/*` |
| Seller workspace | Shop configuration, products, stock, fulfillment, payments, returns | `/seller/shops/:id/*` |
| Platform administration | Catalogue, shipping, customers, orders, coupons, reports, moderation | `/admin/*` |

## Visual system

### Theme tokens

The frontend uses semantic CSS variables in `frontend/src/style.css`, consumed through Tailwind utility names. Do not introduce page-specific neutral colours when an existing semantic token is suitable.

| Role | Utility | Use |
| --- | --- | --- |
| Page background | `bg-canvas` | Global page canvas |
| Surface | `bg-surface` | Cards, headers, drawers, dialogs |
| Raised/muted surface | `bg-surface-raised`, `bg-surface-muted` | Layered content and low-emphasis panels |
| Primary text | `text-ink` | Headings and primary content |
| Supporting text | `text-text-secondary`, `text-muted` | Descriptions, metadata, helper copy |
| Borders | `border-border-gray`, `border-border-strong` | Dividers and focus hierarchy |
| Brand action | `bg-primary`, `text-primary` | Primary actions and interactive emphasis |

Light mode uses a light canvas and white surface. Dark mode is enabled by the `html.dark` class and changes the same semantic tokens, not individual feature logic. Components must specify dark-only overrides only where a visual treatment deliberately differs from the token value.

### Typography and localisation

- UI language support: English and Khmer.
- Base typography uses Inter; Khmer uses Kantumruy Pro via `html.font-khmer-mode`.
- Khmer line-height is intentionally increased to prevent diacritic clipping.
- Use translation keys for all customer-facing content. Avoid text embedded in templates unless it is an approved technical label awaiting localisation.
- Keep titles brief, descriptions scannable, and action labels verb-led: “Add to cart”, “Save method”, “Continue to payment”.

### Reusable components

| Component/class | Intended use |
| --- | --- |
| `container-app` | Page-width alignment with responsive horizontal gutters |
| `card`, `panel` | Grouped content; cards use the shared border, surface, radius, and shadow |
| `page-header`, `page-actions` | Standard title, description, and action arrangement |
| `btn-primary` | One primary action per decision area |
| `btn-secondary`, `btn-outline`, `btn-ghost`, `btn-danger` | Secondary, low-emphasis, or destructive choices |
| `input`, `select`, `textarea`, `label` | Forms with consistent focus and validation treatment |
| `BaseModal` | Focus-managed dialogs with mobile-height and safe-area handling |
| `EmptyState`, skeletons, error states | Predictable async loading and recovery experience |

## Responsive rules

### Layout behavior

| Range | Expected behavior |
| --- | --- |
| 320–359px | Single-column product lists where two columns would crowd; stacked actions and forms |
| 360–639px | Phone layout: compact header, full-width mobile search, drawers, large touch targets |
| 640–1023px | Two-column content where appropriate; footer and forms can use two columns |
| 1024px+ | Desktop navigation, multi-column content, persistent filter/sidebar layouts |
| 1600px max | Content remains constrained by `--app-content-max-width` to preserve readable line lengths |

Shared rules currently in place:

- Phone controls have a minimum 44px touch height.
- The document clips accidental horizontal overflow rather than allowing a page-wide scroll bar.
- Store navigation, cart drawer, filters, and modal layouts use dynamic viewport units (`dvh`) where necessary.
- Drawers and dialogs preserve room for device safe areas.
- Page action groups stack on narrow screens instead of shrinking controls into an unusable row.
- The footer transitions from one column to two columns at small-tablet width and four columns at desktop width; its newsletter form stacks on narrow phones.

Tables are an exception: operational tables may scroll horizontally where column data must remain comparable. New table screens should explicitly choose one of these patterns:

1. Horizontal scroll with a clear table wrapper and a sensible minimum width.
2. A compact record-card view on phones, while retaining the table on larger screens.
3. Fewer visible columns with a detail view for secondary data.

## Core journey guidance

### Discovery and product selection

- Keep search and category navigation accessible from every storefront page.
- Preserve selected filters in the URL so customers can refresh, share, and return to the same results.
- Distinguish loading, zero-result, and request-error states.
- Show the price and stock of the purchasable variant, not an unavailable or stale option.
- Variant controls must make unavailable combinations clear before customers attempt to add an item.

### Cart and checkout

- Cart state should remain visible without forcing navigation; use the cart drawer for quick confirmation and `/cart` for review.
- Checkout is progressive: contact/address, delivery, payment, then review.
- For multi-shop orders, communicate that delivery and payment selection may apply per shop.
- Disable duplicate submission while placing an order and explain server validation errors near the affected decision.
- Preserve user-entered form data through recoverable failures when it is safe to do so.

### Payment, orders, and support

- Payment state must be server-verified; do not imply success before verification.
- Order status should use a plain-language stepper and show tracking or delivery detail when available.
- Return/refund and support paths must describe eligibility, evidence requirements, and next steps clearly.

### Seller and administrator tools

- Use a visible page title, concise supporting copy, and the dominant action in the page header.
- Keep financial, inventory, and fulfillment mutations explicit and auditable.
- Long forms should be split into sections with descriptive headings.
- On phone-sized screens, prioritise the action needed to complete the job over showing every data column at once.

## Accessibility baseline

- Use semantic heading order and native controls before custom ARIA patterns.
- Every icon-only control needs an accessible label.
- Visible keyboard focus must be retained; do not remove outlines without providing an equivalent focus style.
- Dialogs must trap focus, close with Escape, and restore focus to their trigger. `BaseModal` provides this behavior.
- Images require meaningful alt text; decorative images should have an empty alt value.
- Do not communicate validation, order state, stock, or payment state with colour alone.
- Use sufficient contrast in both theme modes. Footer, input, badge, and divider styling must be tested in light and dark mode.
- Keep interactive hit areas at least 44 by 44 CSS pixels on phones where practical.

## UX/UI review checklist

Before merging a frontend change, verify:

- [ ] English and Khmer text remain readable without clipping.
- [ ] Light and dark mode use correct surface/text contrast.
- [ ] Keyboard focus, tab order, and Escape behavior work for the changed interaction.
- [ ] Loading, empty, error, success, and disabled states are represented where asynchronous work occurs.
- [ ] The route has no horizontal page overflow at 320px, 375px, 390px, 412px, tablet, and desktop widths.
- [ ] Buttons and form controls remain tappable and do not overlap device safe areas.
- [ ] Images preserve aspect ratio and have a useful fallback.
- [ ] Desktop behavior has not regressed when a phone-specific rule is added.
- [ ] `docker compose exec frontend npm run build` passes.

## Current improvement priorities

### Priority 1: complete visual QA

Run a deliberate route matrix at 320, 360, 375, 390, 412, 430, tablet, and desktop widths in both themes and languages. The priority journeys are checkout, Bakong payment, cart, product detail, customer orders, seller product creation, media upload, reports/charts, and administrator shipping/order workflows.

### Priority 2: dense operational views on phones

Several seller/admin tables use horizontal scrolling. For frequently used phone workflows, create a record-card presentation or reduce visible columns and move less-common information into a detail panel.

### Priority 3: real-device verification

Validate on at least one Android and one iPhone, including software keyboard open/close, device rotation, browser toolbar changes, safe-area spacing, camera/file upload, and touch interaction.

### Priority 4: automated visual and overflow regression checks

Extend Playwright coverage with representative no-overflow assertions and screenshot review for storefront, account, seller, and admin routes. Keep targeted mobile filter tests and add checkout/payment coverage.

## Related documents

- [Frontend guide](./FRONTEND.md)
- [Architecture](./ARCHITECTURE.md)
- [Commerce completion guide](./COMMERCE-COMPLETION.md)
- [Smartphone responsive audit](./SMARTPHONE-RESPONSIVE-AUDIT-REPORT.md)
- [QA report](./QA-REPORT.md)
