# E-KHMER — Mobile Sticky Header / Search / Category Navigation

Act as a senior **Vue.js + TypeScript + Tailwind CSS Mobile UI Engineer**.

I have an existing E-KHMER responsive storefront.

On smartphone, my current header contains:

```text
Promotion Bar

Main Header
- Hamburger
- E-KHMER Logo
- Language
- Theme
- Cart
- Account

Search Bar

Category Navigation
- All Products
- Explore Shops
- Electronics
- ...
```

I want to improve the mobile UX.

# GOAL

When the user scrolls down, keep this entire block sticky:

```text
┌─────────────────────────────────┐
│ ☰   E-KHMER   🇰🇭  ☀  🛍  👤 │
├─────────────────────────────────┤
│ 🔍 Search products...           │
├─────────────────────────────────┤
│ All Products | Shops | ...      │
└─────────────────────────────────┘
```

The sticky section must remain visible at the top while page content scrolls underneath.

---

# 1. PROMOTION BAR BEHAVIOR

The blue promotion bar:

```text
Free shipping on orders over $100 · Easy 30-day returns
```

should NOT remain permanently sticky.

Preferred behavior:

```text
Page at top:

Promotion Bar
Main Header
Search
Categories
Content


After scrolling:

Main Header
Search
Categories
────────────
Content scrolling underneath
```

The promotion bar should scroll away naturally.

---

# 2. STICKY MOBILE HEADER

Wrap these three sections inside one mobile sticky container:

```text
Main Header
Search Bar
Category Navigation
```

Conceptually:

```vue
<div class="mobile-sticky-header">
    <MobileHeader />
    <MobileSearch />
    <MobileCategoryNav />
</div>
```

Use actual existing components.

Do not duplicate the header.

---

# 3. STICKY CSS

Preferred behavior:

```css
position: sticky;
top: 0;
z-index: high enough;
```

Example Tailwind concept:

```html
<div
  class="
    sticky
    top-0
    z-50
    bg-background
  "
>
```

Adapt to the existing E-KHMER design tokens.

Do NOT blindly copy classes if the project already has semantic theme classes.

---

# 4. IMPORTANT — CHECK STICKY PARENT

Before implementing, inspect parent elements.

`position: sticky` can fail if a parent has:

```css
overflow: hidden;
overflow: auto;
overflow: scroll;
```

or inappropriate transforms.

Inspect:

```text
StorefrontLayout
App
Header wrapper
Main content wrapper
```

Fix only the relevant layout constraint.

Do not break intentional horizontal scrolling.

---

# 5. LIGHT MODE

Sticky header in light mode should use:

```text
solid/light surface
clear bottom borders
high readability
```

It must NOT become transparent enough that product cards show through the text.

Recommended visual hierarchy:

```text
Main Header
white/light surface

Search Row
same or slightly muted surface

Category Nav
same surface

Bottom:
subtle border/shadow
```

---

# 6. DARK MODE

In dark mode use:

```text
dark surface
visible border
clear elevation separation
```

Do not use three noticeably different navy colors unless the design system requires it.

It should look like one unified sticky header.

---

# 7. SCROLL ELEVATION

At the top:

```text
minimal/no shadow
```

After the page starts scrolling:

```text
subtle shadow
or stronger bottom border
```

Example visual effect:

```text
shadow-sm
```

Do not use a large floating shadow.

Optional implementation:

```text
isScrolled = window.scrollY > 4
```

Then:

```text
isScrolled
? shadow/border
: normal
```

Use existing Vue composables if available.

---

# 8. NO LAYOUT JUMP

When sticky behavior activates:

```text
content must NOT jump upward
header height must remain stable
```

Do not switch:

```text
static
→ fixed
```

without reserving space.

Prefer:

```text
position: sticky
```

because it preserves document flow.

---

# 9. HEADER HEIGHT

Keep mobile rows compact.

Target approximately:

```text
Main header:
56–60px

Search:
56–64px including padding

Category navigation:
44–48px
```

Do not let the sticky area consume half of the phone screen.

---

# 10. SEARCH BAR

Search remains fully functional while sticky.

Keep:

```text
search icon
placeholder
clear action if supported
```

The search field must not be clipped.

---

# 11. CATEGORY NAVIGATION

The category row should remain:

```text
horizontal scroll
```

Example:

```text
All Products
Explore Shops
Electronics
Fashion
Beauty
...
```

Do NOT wrap categories into multiple rows.

Use:

```text
overflow-x-auto
whitespace-nowrap
```

or project equivalent.

---

# 12. HIDE CATEGORY SCROLLBAR

Mobile category navigation can horizontally scroll without displaying an ugly scrollbar.

But maintain touch scrolling.

Do not disable scrolling.

---

# 13. ACTIVE CATEGORY

The selected category should remain clear.

Example:

```text
All Products
```

uses:

```text
primary soft background
primary text
rounded-md
```

while other categories stay neutral.

---

# 14. IOS SAFARI SUPPORT

The screenshots show an iPhone/Safari layout.

Test carefully on Safari-style mobile viewport.

Check:

```text
notch
safe area
browser top bar
dynamic browser chrome
```

Do NOT hardcode a fake iPhone status-bar height.

The webpage should start below the browser viewport automatically.

If the application runs as PWA/fullscreen and safe-area support already exists, preserve:

```css
env(safe-area-inset-top)
```

appropriately.

---

# 15. DO NOT USE `100vh` FOR HEADER

Avoid mobile Safari issues.

If viewport calculations are needed elsewhere, prefer modern:

```text
dvh
svh
```

where supported.

Sticky header itself should not need viewport-height calculations.

---

# 16. Z-INDEX SYSTEM

Use the existing design-system z-index tokens.

Recommended layering:

```text
Content
↓
Sticky Header
↓
Dropdown
↓
Drawer
↓
Modal
↓
Toast
```

For example:

```text
sticky header:
z-40 / z-50

mobile navigation drawer:
higher

modal:
higher still
```

Do not use random:

```text
z-[999999]
```

---

# 17. DRAWER INTERACTION

When hamburger menu opens:

```text
drawer must display above sticky header
```

Header must not overlap the drawer.

---

# 18. SEARCH DROPDOWN

If search suggestions appear:

```text
they must display above page content
```

and below/within the appropriate sticky header layer.

Do not let category navigation cover search suggestions.

---

# 19. ACCOUNT / CART DROPDOWNS

If these open popovers:

ensure they are not clipped by:

```text
overflow-hidden
```

on the sticky wrapper.

Use portal/teleport if the project already uses it.

---

# 20. DO NOT STICK DESKTOP UNLESS INTENDED

This task is primarily smartphone UX.

Recommended:

```text
mobile:
sticky

tablet/desktop:
preserve existing header behavior
```

unless desktop already intentionally uses sticky navigation.

Use responsive classes appropriately.

---

# 21. RESPONSIVE TEST WIDTHS

Test at:

```text
320px
360px
375px
390px
393px
412px
430px
```

Especially:

```text
390px
430px
```

because those are the current main mobile targets.

---

# 22. LONG KHMER TEXT

Test Khmer category names.

Navigation must remain horizontal and not break sticky header height.

Do not allow long Khmer labels to create:

```text
2-line category tabs
```

unless intentionally designed.

---

# 23. ENGLISH

Test English:

```text
All Products
Explore shops
Electronics
Fashion
Groceries
```

No overflow except horizontal category scrolling.

---

# 24. SCROLL TEST

Test this exact flow:

```text
Open Shop page

At top:
Promotion bar visible
Header visible
Search visible
Categories visible

Scroll down 100px
→ promotion bar starts disappearing

Continue scrolling
→ promotion bar fully disappears

Header + Search + Categories
remain attached to viewport top

Product cards scroll underneath
```

---

# 25. SCROLL BACK TO TOP

When scrolling back:

```text
promotion bar returns naturally
```

Do not duplicate it or create strange blank spacing.

---

# 26. FAST SCROLL

Test fast scrolling.

Sticky header should:

```text
not flicker
not jump
not detach
```

---

# 27. PRODUCT GRID

Sticky header must not cover the first row of product content.

Because sticky remains in normal document flow, initial layout should remain correct.

Verify:

```text
breadcrumb
Shop Products heading
filter toolbar
product grid
```

remain fully reachable.

---

# 28. ANCHOR / ROUTE SCROLL

If route navigation restores scroll position:

sticky header must not cause incorrect offset.

Inspect existing router scroll behavior.

---

# 29. FILTER DRAWER

Opening Filters while sticky header exists:

```text
filter drawer
```

must appear above it.

---

# 30. MOBILE MENU

Opening hamburger:

```text
navigation drawer
```

must appear above:

```text
header
search
categories
```

---

# 31. THEME SWITCH

Switch:

```text
Light → Dark
Dark → Light
```

while scrolled.

Sticky header should immediately update without:

```text
white flash
incorrect borders
transparent background
```

---

# 32. LANGUAGE SWITCH

Switch Khmer/English while scrolled.

Header should remain sticky and stable.

No height jump caused by text.

---

# 33. ACCESSIBILITY

Sticky header must not harm:

```text
keyboard navigation
screen reader labels
focus
zoom
```

Do not trap focus.

---

# 34. PERFORMANCE

Do NOT attach heavy scroll handlers.

If scroll elevation is required:

use:

```text
passive listener
IntersectionObserver
existing Vue composable
```

or a lightweight equivalent.

Sticky positioning itself should be CSS-based.

---

# 35. OPTIONAL IMPROVEMENT — COMPACT STICKY STATE

Only if it improves the UX without making implementation fragile:

When user scrolls down significantly:

```text
Main Header:
remain normal/compact

Search:
remain

Categories:
remain
```

Do NOT hide required navigation.

A complex animated shrinking header is NOT required.

Prefer stable behavior.

---

# 36. DO NOT IMPLEMENT THIS

Do NOT:

```text
make the promotion bar sticky

hide search when scrolling

hide categories when scrolling

make header transparent

use fixed positioning without handling layout

add scroll-jacking

add excessive animation

change storefront business logic
```

---

# 37. EXPECTED RESULT

### At top

```text
┌─────────────────────────────────┐
│ Free shipping over $100...      │
├─────────────────────────────────┤
│ ☰ E-KHMER 🇰🇭 ☀ 🛍 👤         │
├─────────────────────────────────┤
│ 🔍 Search products...           │
├─────────────────────────────────┤
│ All Products Shops Electronics  │
└─────────────────────────────────┘

Home / Shop

Shop Products
...
```

### After scrolling

```text
┌─────────────────────────────────┐
│ ☰ E-KHMER 🇰🇭 ☀ 🛍 👤         │
├─────────────────────────────────┤
│ 🔍 Search products...           │
├─────────────────────────────────┤
│ All Products Shops Electronics  │
└─────────────────────────────────┘
↑ STICKY

        Products scrolling
              ↓
```

This is the exact target.

---

# 38. TEST DARK MODE

Verify sticky behavior on:

```text
Shop
Search Results
Category Listing
Home
```

in dark mode.

---

# 39. TEST LIGHT MODE

Repeat in light mode.

---

# 40. DO NOT LIMIT FIX TO `/shop`

The sticky mobile storefront header is a global storefront component.

If the same storefront header appears on:

```text
Home
Shop
Shop Detail
Search
Category
Product Detail
Cart
```

use the shared component/layout.

Do not copy sticky CSS into every route.

---

# 41. CHECK CHECKOUT

Checkout may intentionally use a reduced/focused header.

Do NOT force full:

```text
header + search + categories
```

onto checkout if its existing UX intentionally uses a focused checkout header.

Same for payment pages.

---

# 42. IMPLEMENT THROUGH STOREFRONT LAYOUT

Prefer fixing the shared layout/header architecture:

```text
StorefrontLayout
    PromotionBar

    StickyMobileStorefrontHeader
        MainHeader
        Search
        CategoryNav

    RouterView
    Footer
```

Adapt to actual project structure.

---

# 43. DEFINITION OF DONE

Do not mark complete until:

```text
[ ] Promotion bar scrolls away

[ ] Main mobile header remains sticky

[ ] Search remains sticky

[ ] Category navigation remains sticky

[ ] All 3 behave as one unified block

[ ] No layout jump

[ ] No product content hidden underneath

[ ] No page horizontal overflow

[ ] Categories still horizontally scroll

[ ] Search works

[ ] Hamburger drawer works

[ ] Cart works

[ ] Account action works

[ ] Language switching works

[ ] Theme switching works

[ ] Filter drawer appears above sticky header

[ ] Search dropdown appears correctly

[ ] Mobile menu appears above header

[ ] Light mode works

[ ] Dark mode works

[ ] Khmer works

[ ] English works

[ ] 320px works

[ ] 360px works

[ ] 375px works

[ ] 390px works

[ ] 412px works

[ ] 430px works

[ ] iPhone/Safari-style viewport tested

[ ] Android/Chromium-style viewport tested

[ ] Fast scrolling does not flicker

[ ] Scroll-to-top restores promo bar correctly

[ ] Desktop regression passes

[ ] TypeScript passes

[ ] Production build passes

[ ] Browser tests pass
```

---

# 44. FINAL REPORT

Report:

```text
E-KHMER — MOBILE STICKY STOREFRONT HEADER

Status:
PASS / NEEDS WORK

Shared Component Updated:

Promotion Bar:
SCROLLS / STICKY

Main Header:
STICKY / FAIL

Search:
STICKY / FAIL

Category Navigation:
STICKY / FAIL

Sticky Strategy:
CSS sticky / Other

Top Offset:

Z-Index:

Scroll Elevation:
YES / NO

Layout Jump:
NONE / FOUND

Horizontal Overflow:
NONE / FOUND

Category Horizontal Scroll:
PASS / FAIL

Search:
PASS / FAIL

Mobile Drawer:
PASS / FAIL

Filter Drawer Layering:
PASS / FAIL

Search Dropdown Layering:
PASS / FAIL

Theme Switch:
PASS / FAIL

Language Switch:
PASS / FAIL

Light Mode:
PASS / FAIL

Dark Mode:
PASS / FAIL

Khmer:
PASS / FAIL

English:
PASS / FAIL

320px:
PASS / FAIL

360px:
PASS / FAIL

375px:
PASS / FAIL

390px:
PASS / FAIL

412px:
PASS / FAIL

430px:
PASS / FAIL

Desktop Regression:
PASS / FAIL

TypeScript:
PASS / FAIL

Production Build:
PASS / FAIL

Browser Tests:
PASS / FAIL

Files Changed:

Remaining Issues:
```

# FINAL TARGET

The mobile experience should behave like a real marketplace application:

```text
Promotion message
→ scrolls away

E-KHMER navigation
Search
Categories
→ remain accessible while browsing products
```

Do not stop after making it visually sticky.

Verify all interactions continue to work while the header is stuck to the top.