# E-KHMER — Mobile Sticky Storefront Header

## Status

PASS — implementation, production build, and automated browser checks pass.

| Check | Result |
| --- | --- |
| Shared component updated | `StoreLayout` |
| Promotion bar | SCROLLS |
| Main header | STICKY on mobile |
| Search | STICKY on mobile |
| Category navigation | STICKY on mobile |
| Sticky strategy | CSS `position: sticky` |
| Top offset / z-index | `top: 0` / `z-40` |
| Scroll elevation | YES — subtle border and shadow after 4 px |
| Layout jump | NONE — sticky stays in document flow |
| Horizontal overflow | NONE at 320, 360, 375, 390, 412, and 430 px |
| Category horizontal scroll | PASS |
| Search | PASS — remains visible and usable after scrolling |
| Mobile drawer / cart / filter drawer layering | PASS — existing `z-50` overlays sit above the `z-40` sticky group |
| Search dropdown | N/A — the current storefront search navigates instead of presenting suggestions |
| Desktop regression | PASS — 8 catalog browser tests |
| TypeScript / production build | PASS |
| Browser tests | PASS |

## Implementation

- Moved the announcement into `PromotionBar`, outside the sticky group, so it naturally scrolls away.
- Wrapped the existing `AppHeader` (including its mobile search row) and `CategoryNavBar` once in the shared storefront layout; no header was duplicated.
- The wrapper uses a solid semantic `bg-surface`, token-based borders, and a lightweight passive scroll listener only to add elevation.
- Desktop retains its existing header behavior via responsive styles; the mobile wrapper becomes layout-transparent at the desktop breakpoint.
- Category links retain one horizontal, touch-scrollable row with the shared hidden-scrollbar utility.

## Verification

```text
docker compose exec -T frontend npm run build
✓ vue-tsc -b && vite build

docker compose --profile test run --rm browser-tests npx playwright test tests/e2e/mobile-sticky-storefront-header.spec.ts --project=mobile
✓ 1 passed

docker compose --profile test run --rm browser-tests npx playwright test tests/e2e/catalog-filters.spec.ts --project=desktop
✓ 8 passed
```

The mobile test checks the promotion bar at the top, the shared sticky block after scrolling, the sticky search and category row, category horizontal scrolling, and the six specified narrow widths.

## Files Changed

- `frontend/src/components/PromotionBar.vue`
- `frontend/src/components/AppHeader.vue`
- `frontend/src/components/CategoryNavBar.vue`
- `frontend/src/layouts/StoreLayout.vue`
- `frontend/tests/e2e/mobile-sticky-storefront-header.spec.ts`

## Remaining Manual Coverage

Automated Chromium coverage is complete. A physical iOS Safari device session was not available here, so final release QA should visually check Safari's dynamic browser chrome and Khmer labels in both themes.
