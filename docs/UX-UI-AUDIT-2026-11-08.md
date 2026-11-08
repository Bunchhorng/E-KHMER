# E-KHMER UX/UI Audit — Mobile Storefront Stage

Updated: 2026-11-08

## Scope

This source-based audit covers the Vue frontend, the UX/UI guide, the current storefront layout, and the supplied footer screenshot. It starts the staged redesign requested for E-KHMER; it is not a claim that every route has received visual-device acceptance.

## Strengths retained

- Semantic colour tokens already support light and dark themes.
- Khmer typography has a dedicated font mode and safer line height.
- The storefront includes a mobile search row, menu drawer, cart drawer, filter sheet, dynamic viewport sizing, and safe-area support.
- Commerce correctness is appropriately kept in existing Pinia stores and backend APIs; UI changes do not change checkout, payment, or authentication rules.
- Product cards, loading states, and reusable form/button primitives give the project a good consistency baseline.

## Gaps identified

| Priority | Finding | Impact | Planned response |
| --- | --- | --- | --- |
| P0 | Storefront lacks persistent mobile wayfinding after the header scrolls away. | Customers need extra taps to reach categories, shops, cart, and account. | Add a fixed five-item mobile bottom navigation with cart badge and a category sheet. |
| P0 | Product grids render a single card per row on normal phone widths. | The catalog feels less like a high-density mobile commerce app and increases scrolling. | Use two columns from 360px, retaining one column below 360px. |
| P1 | Homepage feature sections are desktop-spaced on phones. | Hero, categories, and flash deal use more vertical space than necessary. | Compact phone spacing; make categories horizontally scannable and flash-deal content denser. |
| P1 | Footer light-mode surface/text contrast was inconsistent. | Readability and visual cohesion suffer in light theme. | Corrected in the shared footer before this stage. |
| P1 | Seller/admin data tables rely on horizontal scrolling. | Dense operational workflows are harder on phone-sized screens. | Stage 2: route-specific record cards or reduced-column views. |
| P1 | Checkout/payment/device matrix remains unverified. | Responsive bugs could still affect conversion. | Stage 3: visual QA plus targeted Playwright coverage. |

## Stage 1 delivered

- Added `MobileBottomNavigation` to the storefront only, visible below the desktop breakpoint.
- Included Home, Categories, Shops, Cart, and Account destinations; cart count is read from the existing cart store.
- Added an accessible category bottom sheet using the existing category API and existing `/shop?category=` route contract.
- Kept the navigation hidden during checkout, payment, success, and tracking flows to avoid distracting from transactional tasks.
- Reserved page-bottom space for the fixed navigation and device safe area.
- Updated the storefront homepage for compact phone hero/benefit/deal spacing and horizontally scrollable category cards.
- Updated shared product grids to use two columns at standard phone widths and a single column below 360px.

## Next stages

1. Improve mobile search/filter results, product detail, cart, checkout, and account flows with task-specific mobile hierarchy.
2. Build responsive seller/admin table alternatives and compact action patterns.
3. Run light/dark and Khmer/English visual QA at 320, 375, 390, 412, tablet, and desktop widths; then add browser regression tests.
