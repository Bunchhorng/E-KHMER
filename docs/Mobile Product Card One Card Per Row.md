# E-KHMER — Mobile Product Card: One Card Per Row

Act as a senior **Vue.js + TypeScript + Tailwind CSS Responsive E-Commerce UI Engineer**.

I want to improve the E-KHMER product grid specifically for smartphones.

## CURRENT PROBLEM

Currently the smartphone product listing uses:

```text
2 product cards per row
```

This creates cards that are too narrow.

Problems visible on mobile:

```text
Product titles become cramped
Long Khmer/English names wrap badly
Prices overflow
Large prices can be clipped
Ratings become compressed
Add to Cart buttons become too small
Product images become narrow
Cards do not feel premium
Content alignment becomes inconsistent
```

Example current layout:

```text
┌────────────┐ ┌────────────┐
│ Product A  │ │ Product B  │
│            │ │            │
│ image      │ │ image      │
│            │ │            │
│ title      │ │ title      │
│ price      │ │ price      │
│ Add Cart   │ │ Add Cart   │
└────────────┘ └────────────┘
```

I do NOT want this layout on smartphones.

---

# GOAL

For smartphone devices:

# ONE PRODUCT CARD PER ROW

Expected:

```text
┌──────────────────────────────┐
│                              │
│        Product Image         │
│                              │
├──────────────────────────────┤
│ Shop / Brand                 │
│ Product Name                 │
│ ★★★★☆ (12)                  │
│ $59.99   $71.99              │
│                              │
│      🛒 Add to Cart          │
└──────────────────────────────┘

┌──────────────────────────────┐
│         Next Product         │
└──────────────────────────────┘
```

Each product gets the full usable mobile width.

---

# 1. RESPONSIVE GRID

Change the product grid to something conceptually equivalent to:

```html
grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5
```

BUT inspect the project's actual breakpoints first.

Target behavior:

```text
320px → 1 card

360px → 1 card

375px → 1 card

390px → 1 card

393px → 1 card

412px → 1 card

430px → 1 card

Tablet → 2 cards

Large Tablet → 2–3 cards

Laptop → 4 cards

Desktop → 4–5 cards

Large Desktop → 5–6 cards
```

Smartphone must remain exactly:

```text
1 PRODUCT PER ROW
```

---

# 2. DO NOT FIX ONLY `/shop`

Find the shared product-grid component.

This rule must apply consistently anywhere normal product cards appear:

```text
Homepage product sections

Shop Products

Search Results

Category Products

Brand Products

Shop Detail

Recommended Products

New Arrivals

Popular Products

Flash Deals

Wishlist if it uses ProductCard

Related Products
```

Do not copy responsive classes into every page.

Create/fix the shared:

```text
ProductGrid
```

component or shared grid utility.

---

# 3. KEEP ONE GLOBAL PRODUCT CARD

Continue using the existing standardized:

```text
ProductCard
```

Do not create:

```text
MobileProductCard.vue
DesktopProductCard.vue
```

unless there is a strong technical reason.

Prefer one responsive ProductCard.

---

# 4. MOBILE CARD WIDTH

The mobile card should use:

```text
width: 100%
```

inside the page container.

Example:

```text
Screen width:
390px

Page padding:
16px + 16px

Product card:
358px
```

This gives enough room for:

```text
image
title
rating
price
button
```

---

# 5. CARD IMAGE

Use a consistent product image ratio.

Recommended:

```text
aspect-ratio: 4 / 3
```

or:

```text
1 / 1
```

depending on the current E-KHMER design system.

For the full-width mobile card, I recommend:

```text
4:3
```

for a more compact marketplace experience.

Do not allow the original image size to control card height.

---

# 6. IMAGE BEHAVIOR

Use the project's chosen standard:

```text
object-cover
```

or:

```text
object-contain
```

consistently.

The image must:

```text
fill card width
keep ratio
not stretch
not distort
```

---

# 7. BROKEN IMAGE FALLBACK

If image is:

```text
404
null
missing
invalid
```

show the E-KHMER product placeholder.

Do not show:

```text
broken image icon
raw alt text
empty gray block
```

---

# 8. WISHLIST BUTTON

Keep wishlist icon:

```text
top-right
```

Recommended size:

```text
40–44px
```

for touch accessibility.

Do not use tiny desktop-sized controls.

---

# 9. DISCOUNT BADGE

Keep discount badge:

```text
top-left
```

Use E-KHMER yellow accent consistently.

Example:

```text
-17%
```

---

# 10. CARD CONTENT PADDING

Mobile card:

```text
16px
```

recommended.

Use existing design-system spacing tokens.

---

# 11. SHOP / BRAND

Display:

```text
TRAILGUARD
```

as small secondary/primary-blue metadata.

Do not let brand text dominate the product name.

---

# 12. PRODUCT NAME

Allow:

```text
maximum 2 lines
```

Example:

```text
Eu qui id veritatis deleniti sit
```

Use:

```text
line-clamp-2
```

or equivalent.

Do not allow long names to make one card much taller.

---

# 13. RATING ROW

Keep:

```text
★★★★☆ (12)
```

on one clear row where possible.

If no reviews:

```text
☆☆☆☆☆ (0)
```

should still align correctly.

---

# 14. PRICE — IMPORTANT

The screenshot currently shows a price similar to:

```text
$96,700,274.00
```

which becomes clipped in the 2-column card.

With one card per row it gets much more space.

Still make price layout resilient.

Use:

```text
flex-wrap
min-width: 0
```

appropriately.

Do not let price leave the card.

---

# 15. VERY LARGE PRICE

Test:

```text
$4.00

$399.99

$10,000.00

$1,000,000.00

$96,700,274.00
```

All must remain inside the card.

Do NOT solve this by shrinking text to unreadable size.

Allow wrapping where necessary.

---

# 16. OLD PRICE

If discounted:

```text
$59.99   $71.99
```

Old price should be:

```text
muted
strikethrough
```

and wrap safely.

---

# 17. ADD TO CART

Mobile card button should be:

```text
width: 100%
```

Recommended:

```text
44–48px height
```

Structure:

```text
🛒 Add to Cart
```

Centered.

---

# 18. BUTTON POSITION

Cards should keep CTA visually consistent.

Use card structure:

```text
display: flex
flex-direction: column
```

and:

```text
margin-top: auto
```

for CTA section where appropriate.

---

# 19. CARD HEIGHT

Cards should have predictable structure.

However, with one card per row:

do NOT artificially make every card extremely tall.

Use:

```text
natural but controlled height
```

with title line clamp.

---

# 20. MOBILE GAP

Between cards:

```text
16px
```

or design-system equivalent.

Example:

```text
Product
↓ 16px
Product
↓ 16px
Product
```

---

# 21. PAGE SIDE PADDING

Keep approximately:

```text
16px
```

on smartphone.

Product cards should not touch screen edges.

---

# 22. FILTER BAR

Keep current mobile:

```text
Filters
Featured
```

toolbar above the grid.

Once grid becomes 1-column, make sure filter bar width aligns exactly with product cards.

Example:

```text
┌──────────────────────────┐
│ Filters        Featured  │
└──────────────────────────┘

16px gap

┌──────────────────────────┐
│ Product                  │
└──────────────────────────┘
```

Same left/right edges.

---

# 23. GRID ALIGNMENT

These must share exactly the same container:

```text
Breadcrumb
Shop Products heading
Product count
Filter toolbar
Product cards
Pagination
```

No inconsistent horizontal offsets.

---

# 24. TABLET

Do not keep one column unnecessarily on tablets.

Recommended:

```text
>= 640px or project breakpoint:
2 columns
```

Test readability first.

---

# 25. LANDSCAPE PHONE

Landscape phones may have enough width for:

```text
2 cards
```

but only if actual viewport breakpoint allows it cleanly.

Portrait smartphone requirement remains:

```text
1 card per row
```

---

# 26. HOMEPAGE

For normal ProductGrid sections on smartphone:

also use:

```text
1 card per row
```

unless a section is explicitly designed as:

```text
horizontal product carousel
```

If carousel:

keep carousel behavior.

Do not turn intentional carousels into a vertical list.

---

# 27. RELATED PRODUCTS

On product detail:

preferred mobile behavior may be:

```text
horizontal carousel
```

or:

```text
1 card per row
```

Follow existing section design.

But normal grid views must use one column.

---

# 28. WISHLIST

If Wishlist uses normal ProductGrid:

use one column on smartphone.

---

# 29. SEARCH RESULTS

Search results:

```text
1 card per row
```

on phone.

---

# 30. CATEGORY RESULTS

Category results:

```text
1 card per row
```

on phone.

---

# 31. SHOP PRODUCT RESULTS

The screenshot page:

```text
Shop Products
```

must definitely become:

```text
1 card per row
```

on smartphone.

---

# 32. LIGHT MODE

Verify:

```text
white/light card
visible border
subtle shadow
readable price
readable rating
```

No blending into page background.

---

# 33. DARK MODE

Verify card uses:

```text
dark surface
visible border
clear image/content separation
```

and preserves the same dimensions.

---

# 34. KHMER

Test Khmer product names.

Allow:

```text
2 lines
```

without clipping Khmer glyphs.

Button text must fit.

---

# 35. ENGLISH

Test long English names.

No overflow.

---

# 36. RESPONSIVE TEST

Test:

```text
320px
360px
375px
390px
393px
412px
430px
```

Expected for ALL:

```text
1 card per row
```

---

# 37. SCREENSHOT VALIDATION

Capture mobile screenshots for:

```text
320px
390px
430px
```

Verify visually:

```text
one card only
full usable width
proper padding
no clipping
no price overflow
```

---

# 38. NO HORIZONTAL OVERFLOW

Critical:

```text
document.scrollWidth
```

must not exceed viewport due to product cards.

Test very large prices and long product names.

---

# 39. DO NOT USE HORIZONTAL CARD SCROLL

For normal Shop Product grid:

do NOT solve it with:

```text
horizontal scrolling cards
```

I explicitly want:

# ONE VERTICAL PRODUCT CARD PER ROW

---

# 40. DO NOT MAKE CARD TOO LARGE

One-column does not mean giant card.

Keep image height balanced.

For example:

```text
390px viewport
358px card width

Image:
~240–280px height
```

depending on chosen aspect ratio.

Avoid an image taking 500px vertically.

---

# 41. OPTIONAL MOBILE CARD LAYOUT IMPROVEMENT

If one-column cards become too tall, you may consider a compact horizontal product-card variant:

```text
┌───────────┬──────────────────┐
│           │ Brand            │
│   Image   │ Product Name     │
│           │ ★★★★☆           │
│           │ $59.99           │
│           │ [Add to Cart]    │
└───────────┴──────────────────┘
```

BUT:

do NOT switch to this automatically.

Default preference:

# FULL-WIDTH VERTICAL CARD

because it matches the existing E-KHMER ProductCard design.

---

# 42. DO NOT BREAK DESKTOP

Desktop must retain appropriate multi-column grid.

Do not turn every breakpoint into one column.

---

# 43. DO NOT CHANGE PRODUCT LOGIC

Do not change:

```text
pricing
discount calculation
wishlist
ratings
cart
variants
stock
shop ownership
```

Only improve responsive presentation.

---

# 44. SHARED FIX

Implement in:

```text
ProductGrid
```

and/or shared layout/component.

Do not add:

```text
grid-cols-1
```

individually to 15 pages unless architecture truly requires it.

---

# 45. DEFINITION OF DONE

Do not mark complete until:

```text
[ ] Smartphone uses exactly one normal ProductCard per row

[ ] 320px = 1 column

[ ] 360px = 1 column

[ ] 375px = 1 column

[ ] 390px = 1 column

[ ] 393px = 1 column

[ ] 412px = 1 column

[ ] 430px = 1 column

[ ] Tablet transitions correctly

[ ] Desktop remains multi-column

[ ] Product card uses full available mobile width

[ ] Image aspect ratio consistent

[ ] Wishlist button touch-friendly

[ ] Discount badge aligned

[ ] Product title clamps correctly

[ ] Ratings align correctly

[ ] Large price does not overflow

[ ] Old price wraps safely

[ ] Add to Cart full-width and touch-friendly

[ ] Filter toolbar aligns with cards

[ ] Card margins consistent

[ ] Card gap consistent

[ ] Shop Products fixed

[ ] Search Results fixed

[ ] Category Products fixed

[ ] Shared ProductGrid fixed

[ ] Other normal product grids reviewed

[ ] Intentional carousels remain carousels

[ ] Light mode passes

[ ] Dark mode passes

[ ] Khmer passes

[ ] English passes

[ ] No horizontal overflow

[ ] Desktop regression passes

[ ] TypeScript passes

[ ] Production build passes

[ ] Browser tests pass
```

# FINAL TARGET

Change this:

```text
PHONE

┌──────────┐ ┌──────────┐
│ Product  │ │ Product  │
└──────────┘ └──────────┘
```

into:

```text
PHONE

┌─────────────────────────┐
│        Product A        │
│                         │
│        Full Width       │
│                         │
│       Add to Cart       │
└─────────────────────────┘

┌─────────────────────────┐
│        Product B        │
│                         │
│        Full Width       │
│                         │
│       Add to Cart       │
└─────────────────────────┘
```

Smartphone requirement:

# ONE PRODUCT CARD PER ROW.

Tablet and desktop can progressively return to multiple columns.