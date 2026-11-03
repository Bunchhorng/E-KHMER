# E-KHMER — Smartphone Responsive Audit & Improvement

Act as a senior **Mobile UI/UX Designer, Responsive Web Engineer, Vue.js + TypeScript Engineer, Tailwind CSS Expert, Accessibility Engineer, and E-Commerce QA Engineer**.

I have an existing multi-vendor marketplace called:

# E-KHMER

The global UI/UX design system is already completed.

Now work ONLY on:

# SMARTPHONE RESPONSIVE IMPROVEMENT

The goal is to inspect the complete frontend and make every page work properly on real smartphone screen sizes.

Do NOT redesign the desktop UI unless a mobile fix requires a shared component change.

Do NOT change backend business logic.

Do NOT remove features.

---

# 1. PRIMARY GOAL

Make E-KHMER feel intentionally designed for smartphones, not just “desktop compressed into a small screen.”

Every mobile screen should be:

```text
Readable
Touch-friendly
Fast
Clean
Symmetrical
Easy to navigate
No overflow
No clipped content
No tiny controls
No broken tables
No unusable forms
```

Both:

```text
Light mode
Dark mode
```

and:

```text
Khmer
English
```

must work correctly.

---

# 2. DO NOT CHECK ONLY THE HOMEPAGE

Inspect ALL routes from the actual Vue router.

Audit all smartphone UI in:

```text
Public / Storefront
Authentication
Customer Account
Seller
Super Admin
System / Error pages
```

Also inspect:

```text
Modals
Drawers
Dropdowns
Popovers
Tables
Filters
Forms
Image uploaders
Charts
Date pickers
Toasts
Confirmation dialogs
```

Do not rely only on screenshots or known pages.

---

# 3. BUILD A MOBILE ROUTE INVENTORY FIRST

Read the router and create an internal checklist:

```text
Route
Role
Page
Mobile Layout
Overflow
Touch Targets
Forms
Tables
Modal/Drawer
Dark
Light
Khmer
English
Status
```

Every route must end with:

```text
PASS
FIXED
NEEDS WORK
```

No silent omissions.

---

# 4. TEST REAL SMARTPHONE WIDTHS

At minimum test:

```text
320px
360px
375px
390px
393px
412px
430px
```

Also useful:

```text
480px
```

Do not validate only at Tailwind `sm`.

---

# 5. TEST COMMON DEVICE HEIGHTS

Also consider vertical space:

```text
568px
667px
740px
812px
844px
852px
896px
932px
```

This matters for:

```text
drawers
modals
checkout
sticky actions
forms
```

---

# 6. PORTRAIT FIRST

Primary smartphone design target:

```text
Portrait
```

Also test landscape for:

```text
tables
checkout
product gallery
admin/seller forms
```

where practical.

---

# 7. NO PAGE-LEVEL HORIZONTAL SCROLL

At smartphone widths there should be no accidental page-level:

```text
overflow-x
```

Allowed exceptions:

```text
horizontal category scroller
data table container
tabs
carousel
```

These must be intentional.

---

# 8. VIEWPORT

Verify correct viewport configuration.

Expected:

```html
<meta name="viewport" content="width=device-width, initial-scale=1" />
```

Do not rely on browser scaling to hide responsive problems.

---

# 9. MOBILE CONTAINER SYSTEM

Use consistent smartphone padding.

Recommended:

```text
320–390:
16px

390–430:
16–20px
```

Do not let one page use:

```text
8px
```

while another uses:

```text
32px
```

without reason.

---

# 10. MOBILE SPACING

Reduce desktop spacing thoughtfully.

Examples:

```text
Desktop section gap:
64–96

Mobile:
32–48
```

Dashboard:

```text
Desktop:
24–32

Mobile:
16–20
```

Do not simply multiply everything down.

---

# 11. MOBILE TYPOGRAPHY

Ensure headings do not dominate the screen.

Recommended general behavior:

```text
Desktop H1:
36–48

Mobile H1:
28–32
```

Body:

```text
14–16
```

Do not use unreadably small text.

---

# 12. KHMER MOBILE TYPOGRAPHY

Khmer requires careful vertical space.

Check:

```text
line-height
button labels
navbar
filters
badges
table text
forms
```

Long Khmer strings must wrap naturally.

Do not clip Khmer glyphs.

---

# 13. MOBILE TOUCH TARGETS

Interactive elements should generally have at least:

```text
44 × 44px
```

especially:

```text
menu
cart
wishlist
close
theme
language
filter
quantity controls
pagination
table actions
```

Avoid tiny icon buttons.

---

# 14. MOBILE HEADER

Create a smartphone-specific header layout if needed.

Recommended:

```text
Row 1:
Menu
Logo
Cart / Account actions

Row 2:
Full-width search
```

Do not force desktop:

```text
logo + huge search + 6 icons
```

onto one phone row.

---

# 15. PROMOTION BAR

Current top promo bar should:

```text
remain readable
not overflow
not force excessive height
```

Options:

```text
single-line truncate
horizontal ticker
compact two-line version
```

Use the simplest stable UX.

---

# 16. CATEGORY NAVIGATION

On phone:

```text
horizontal scroll
```

is acceptable.

Use:

```text
scroll-snap optional
clear active item
comfortable padding
```

Do not squeeze 10 categories into one row.

---

# 17. MOBILE NAVIGATION DRAWER

Use a proper drawer for:

```text
categories
account links
support links
seller/admin navigation
```

Drawer must support:

```text
close button
backdrop
scroll
focus
body scroll lock
```

---

# 18. HOMEPAGE HERO

Mobile hero should usually become:

```text
single column
```

Order:

```text
Badge
Headline
Description
CTA buttons
Trust metrics
Visual
```

Avoid overlapping floating cards on small screens.

---

# 19. HERO BUTTONS

On narrow phones:

```text
stack vertically
```

or use two balanced buttons when width allows.

No clipped CTA text.

---

# 20. HERO VISUAL

Do not keep oversized desktop composition.

Mobile should use:

```text
one main image/visual
minimal decorations
```

Prevent overlap with text.

---

# 21. TRUST FEATURES

Desktop 4-column row should become:

```text
2×2
```

or:

```text
horizontal scroll
```

on mobile.

Do not create four tiny columns.

---

# 22. CATEGORY GRID

Recommended smartphone:

```text
2 columns
```

At 320px, allow:

```text
1–2 columns
```

depending on content.

Cards must remain readable.

---

# 23. PRODUCT GRID

Critical.

Recommended:

```text
360–430px:
2 columns
```

if cards remain readable.

At very narrow width:

```text
1 column
```

is acceptable.

Do not make product cards too narrow.

---

# 24. MOBILE PRODUCT CARD

Create a compact mobile variant if necessary.

Keep:

```text
Image
Brand/Shop
Title
Rating
Price
CTA
```

But reduce:

```text
padding
font sizes
decorative whitespace
```

not important information.

---

# 25. PRODUCT CARD CTA

On small cards avoid overly long CTA text.

Possible:

```text
Add
```

with cart icon if localization and accessibility remain clear.

Or keep full-width button if card width permits.

---

# 26. PRODUCT TITLE

Clamp consistently:

```text
2 lines
```

Long Khmer and English titles must not increase card height.

---

# 27. PRODUCT IMAGES

Use fixed aspect ratio.

No card should become tall because of portrait image.

Broken image fallback must remain balanced.

---

# 28. PRODUCT DETAIL

Mobile product detail should become:

```text
Gallery
Product info
Price
Variants
Quantity
CTA
Delivery/info
Description
Reviews
```

single-column.

---

# 29. PRODUCT GALLERY

Use:

```text
swipeable main image
thumbnail strip
```

if current functionality supports it.

Thumbnails should be touch-friendly.

---

# 30. STICKY PRODUCT CTA

Consider sticky bottom CTA for:

```text
Add to Cart
Buy Now
```

if current UX benefits from it.

Do not cover content.

Respect mobile safe areas.

---

# 31. MOBILE FILTERS

Storefront filters should NOT occupy permanent sidebar space.

Use:

```text
Filter button
→ bottom sheet / drawer
```

Allow:

```text
Apply
Reset
Close
```

Filters should preserve state.

---

# 32. FILTER DRAWER

Mobile filter drawer should:

```text
scroll internally
keep Apply actions reachable
```

Consider sticky footer:

```text
Reset
Apply
```

---

# 33. SORT

Use compact:

```text
Sort button
```

or select/dropdown.

Do not put a giant desktop toolbar on phone.

---

# 34. CART

Mobile cart should clearly group:

```text
Shop
Product
Variant
Price
Quantity
Remove
```

Avoid squeezing everything into one row.

Use stacked card layout.

---

# 35. CART QUANTITY CONTROLS

Make:

```text
minus
quantity
plus
```

large enough for touch.

No tiny 20px controls.

---

# 36. CART SUMMARY

On mobile:

```text
summary below items
```

or sticky bottom summary/checkout action.

Do not use narrow side column.

---

# 37. CHECKOUT

Checkout must become a clean single-column flow.

Sections:

```text
Contact
Address
Shipping
Coupon
Payment
Order Summary
```

Avoid side-by-side desktop cards.

---

# 38. CHECKOUT STEPS

If multi-step:

use mobile-friendly progress indicator.

Do not display long desktop step labels squeezed horizontally.

Possible:

```text
Step 2 of 4
```

plus compact progress bar.

---

# 39. ORDER SUMMARY

On phone:

```text
collapsible
```

can be useful for long checkout.

But total and primary action must remain visible.

---

# 40. CHECKOUT CTA

Primary action should be:

```text
full width
```

and easy to reach.

Consider sticky bottom action if it does not obstruct content.

---

# 41. PAYMENT UI

Bakong/KHQR:

ensure QR fits the viewport.

Do not crop QR.

Use:

```text
max-width: 100%
```

and sufficient quiet zone.

---

# 42. ORDER SUCCESS

Use centered mobile layout.

Show:

```text
Success
Order number
Summary
Track order
Continue shopping
```

No oversized desktop card.

---

# 43. AUTH PAGES

Login/register/reset pages should fit without horizontal scroll.

Recommended:

```text
full-width card
16px outer margin
```

Avoid desktop two-column marketing layout on very small screens unless it collapses well.

---

# 44. AUTH INPUTS

Use:

```text
full width
44–48px height
```

Ensure password reveal button has proper touch size.

---

# 45. CUSTOMER ACCOUNT NAVIGATION

Desktop sidebar should become:

```text
mobile drawer
tabs
or compact account menu
```

Do not keep 260px sidebar visible.

---

# 46. ACCOUNT DASHBOARD

Stat cards:

```text
1 column
```

or:

```text
2 columns
```

depending on width.

Values must remain readable.

---

# 47. CUSTOMER ORDERS

Do not force complex desktop table.

Use:

```text
order cards
```

on mobile if more usable.

Each card:

```text
Order number
Date
Status
Total
Items
View
```

---

# 48. ORDER DETAIL

Use stacked information cards.

Keep:

```text
status
payment
shipping
items
totals
tracking
actions
```

clearly separated.

---

# 49. CUSTOMER ADDRESS CARDS

Use full-width cards.

Actions:

```text
Edit
Delete
Set Default
```

should wrap cleanly.

---

# 50. WISHLIST

Use mobile product grid/card system.

---

# 51. RETURNS

Use card-based status display if desktop table is dense.

---

# 52. SELLER / ADMIN SIDEBAR

On smartphone:

```text
hidden by default
```

open via hamburger drawer.

Do not compress sidebar permanently.

---

# 53. MOBILE DASHBOARD HEADER

Recommended:

```text
Menu
Page title / Shop context
Notifications
Profile
```

Move secondary actions into dropdown if crowded.

---

# 54. DASHBOARD PAGE HEADER

Stack:

```text
Title
Description
Actions
```

on mobile.

Actions may become:

```text
full width
```

or horizontally scroll only when appropriate.

---

# 55. STAT CARDS — SELLER/ADMIN

Phone:

```text
1 column
```

At 390–430:

```text
2 columns
```

only if each remains readable.

Do not cram four cards in one row.

---

# 56. DASHBOARD TABLES

Critical.

For every table decide between:

```text
A. horizontal scroll
B. mobile record cards
C. reduced-column table + details drawer
```

Do not use one strategy blindly.

---

# 57. TABLE SCROLL

If horizontal scrolling is used:

```text
sticky first column optional
clear scroll affordance
no page overflow
```

Table container handles overflow, not whole page.

---

# 58. TABLE ACTIONS

On mobile:

replace multiple icon buttons with:

```text
More
⋮
```

dropdown where useful.

Avoid five tiny buttons per row.

---

# 59. FILTER TOOLBAR — DASHBOARD

Desktop toolbar should collapse on mobile:

```text
Search
Filter button
Sort
```

Advanced filters in drawer/bottom sheet.

---

# 60. FORMS — ADMIN/SELLER

Every multi-column form becomes:

```text
1 column
```

on phone.

Example:

```text
SKU
Barcode
```

must stack.

---

# 61. LONG PRODUCT CREATE FORM

Mobile product form needs:

```text
single column
logical sections
sticky save action optional
```

Right-side completion checklist should become:

```text
collapsible panel
or bottom drawer
```

not remain side-by-side.

---

# 62. IMAGE UPLOAD

On phone:

```text
full width upload area
```

Preview grid:

```text
2–3 columns
```

depending on width.

Touch-friendly delete/reorder controls.

---

# 63. VARIANT EDITING

Variant matrices can become difficult on phone.

Use:

```text
variant cards
accordion
drawer editor
```

rather than huge horizontal table where possible.

Preserve all functionality.

---

# 64. MODALS ON PHONE

Modal should adapt to:

```text
almost full width
```

or:

```text
bottom sheet
```

for action-oriented UI.

Never exceed viewport.

---

# 65. MODAL HEIGHT

Use:

```text
max-height: calc(100dvh - safe margins)
```

with internal scroll.

Actions remain reachable.

---

# 66. MOBILE KEYBOARD

Forms must remain usable when software keyboard opens.

Avoid fixed elements covering the active input.

---

# 67. `100vh` ISSUES

Prefer modern:

```text
dvh
svh
```

where appropriate for full-height mobile layouts.

Avoid browser-address-bar jump problems.

---

# 68. SAFE AREA

Support devices with notches/home indicators.

Use safe-area padding where sticky/fixed UI touches screen edges.

Concept:

```css
env(safe-area-inset-bottom)
```

where appropriate.

---

# 69. STICKY BOTTOM ACTIONS

For:

```text
checkout
product CTA
long forms
```

ensure sticky actions account for:

```text
safe area
keyboard
bottom nav if present
```

---

# 70. DROPDOWNS

Mobile dropdowns must stay inside viewport.

Do not render off-screen.

Use drawer/select pattern when dropdown content is large.

---

# 71. DATE PICKERS

Ensure calendar fits narrow screens.

Use native/compact alternative if current picker is unusable on mobile.

---

# 72. TOASTS

Mobile toast width:

```text
viewport - 24/32px
```

No offscreen notifications.

---

# 73. CHARTS

Charts on smartphone should:

```text
resize
hide unnecessary legend details
keep labels readable
```

Avoid 1000px fixed widths.

---

# 74. ADMIN DASHBOARD CHARTS

Stack charts vertically.

Do not place multiple charts side-by-side on phone.

---

# 75. BADGES

Badges should wrap when needed.

Do not force row overflow.

---

# 76. LONG EMAIL / ORDER NUMBER

Use:

```text
truncate
break-all only when necessary
copy button
```

Avoid horizontal overflow.

---

# 77. SEARCH RESULTS

Search suggestions/dropdowns should use full mobile width where appropriate.

Do not create a narrow desktop popover.

---

# 78. FOOTER

Desktop footer columns should stack.

Recommended mobile:

```text
Brand
Accordion link groups
Newsletter
Payment badges
Copyright
```

---

# 79. NEWSLETTER

Email input + button:

```text
stack vertically
```

on narrow phone if needed.

---

# 80. LIGHT MODE MOBILE

Audit all mobile screens in light mode.

Check:

```text
surface separation
input borders
sticky footer
drawers
modals
tables
cards
```

---

# 81. DARK MODE MOBILE

Repeat in dark mode.

Avoid low-contrast navy-on-navy cards.

---

# 82. LANGUAGE TEST — KHMER

At each major width test Khmer.

Important:

```text
navbar
buttons
sidebar drawer
product cards
checkout
tables/cards
forms
dialogs
```

---

# 83. LANGUAGE TEST — ENGLISH

Repeat.

Do not optimize mobile layout only for one locale.

---

# 84. ORIENTATION CHANGE

Where practical test:

```text
portrait → landscape → portrait
```

No broken drawer/modal/sidebar state.

---

# 85. TOUCH INTERACTION

Test actual click/tap areas.

No hover-only functionality.

Anything important on hover must have touch equivalent.

---

# 86. HOVER STATES

Use hover as enhancement only.

Do not hide required actions until hover on phone.

---

# 87. DOUBLE TAP / DUPLICATE SUBMIT

Buttons with API calls must preserve existing loading/disabled behavior.

Responsive refactor must not break F08/F13 protections.

---

# 88. MOBILE PERFORMANCE

Avoid rendering huge off-screen desktop components merely hidden by CSS if expensive.

Prefer efficient responsive components where necessary.

---

# 89. IMAGE PERFORMANCE

Use:

```text
responsive images
lazy loading
correct dimensions
```

where existing infrastructure supports it.

Large desktop images should not unnecessarily load at full resolution on mobile.

---

# 90. SCROLL PERFORMANCE

Avoid:

```text
heavy fixed backgrounds
too many backdrop-blurs
large shadow layers
```

on mobile if they cause jank.

---

# 91. MOBILE NAV SCROLL LOCK

When drawer/modal is open:

background should not scroll unintentionally.

---

# 92. RESTORE SCROLL

Closing drawer/modal should preserve page position.

---

# 93. ACCESSIBILITY

Mobile audit must include:

```text
labels
focus
screen-reader names
touch target
contrast
error messages
modal focus
```

---

# 94. ZOOM

Do not disable user zoom.

Avoid:

```text
user-scalable=no
maximum-scale=1
```

unless absolutely required.

---

# 95. INPUT FONT SIZE ON IOS

Avoid font sizes below:

```text
16px
```

for important form inputs if it triggers unwanted iOS zoom.

Review actual design.

---

# 96. MOBILE BROWSER SUPPORT

At minimum test Chromium mobile emulation.

If available also check:

```text
WebKit / Safari-like
```

because iPhone layout behavior differs.

---

# 97. DEVICE EMULATION

Use representative devices such as:

```text
iPhone SE
iPhone 13 / 14
iPhone 15 Pro Max
Pixel 7
Galaxy S23/S24 class
```

Do not rely only on custom viewport widths.

---

# 98. REAL DEVICE TEST

If actual smartphone is available:

test:

```text
navigation
scroll
drawer
product
cart
checkout
seller/admin
forms
```

If not:

report:

```text
REAL DEVICE:
NOT VERIFIED
```

Do not claim emulator equals real-device acceptance.

---

# 99. RESPONSIVE SCREENSHOT AUDIT

Capture screenshots of representative smartphone pages:

```text
Home
Product listing
Product detail
Cart
Checkout
Login
Customer Orders
Seller Dashboard
Seller Product Form
Admin Dashboard
Admin Table
Modal
Filter Drawer
```

for:

```text
390px Dark
390px Light
430px Dark
430px Light
```

---

# 100. VISUAL SYMMETRY CHECK

For every smartphone screen verify:

```text
consistent left/right padding

cards aligned

buttons same height

inputs same height

section headings aligned

icons centered

no random margins

no clipped border radius
```

---

# 101. MOBILE EMPTY STATES

Empty state should fit without huge unused space.

Use compact layout.

---

# 102. MOBILE ERROR STATES

Error + Retry button should remain visible without excessive scrolling.

---

# 103. MOBILE LOADING

Skeletons should match mobile layout.

Do not render desktop-sized skeleton grid.

---

# 104. BREAKPOINT AUDIT

Search code for:

```text
sm:
md:
lg:
xl:
2xl:
```

Review inconsistent breakpoint usage.

Do not mechanically replace all breakpoints.

Align common patterns.

---

# 105. HARD-CODED WIDTH AUDIT

Search for:

```text
w-[...]
min-w-[...]
max-w-[...]
fixed pixel width
```

Identify widths that break phones.

---

# 106. HARD-CODED HEIGHT AUDIT

Review fixed heights that cause:

```text
clipping
overflow
large whitespace
```

on smartphones.

---

# 107. ABSOLUTE POSITIONING AUDIT

Search:

```text
absolute
fixed
```

especially homepage hero/product overlays.

Verify they do not overlap on phone.

---

# 108. TABLE MIN-WIDTH AUDIT

If using:

```text
min-w-[1200px]
```

ensure it is inside an intentional scroll container.

---

# 109. MOBILE FORM ERROR TEXT

Errors should wrap below input.

No clipped single-line error.

---

# 110. MOBILE BUTTON TEXT

Long Khmer CTA text should:

```text
wrap safely
```

or use a concise translation.

Do not shrink font to unreadable size.

---

# 111. RESPONSIVE COMPONENT STRATEGY

Prefer responsive primitives rather than page-level hacks.

Example:

```text
AppPageHeader
```

should know how to stack actions on mobile.

Likewise:

```text
FilterToolbar
StatGrid
FormActions
```

---

# 112. DO NOT ADD MOBILE CSS HACKS PAGE BY PAGE

Avoid hundreds of one-off:

```text
@media
```

fixes.

Improve shared components first.

---

# 113. REFACTOR ORDER

Use:

```text
Phase 1
Mobile route inventory

Phase 2
Global responsive primitives

Phase 3
Storefront

Phase 4
Cart/Checkout

Phase 5
Auth/Customer

Phase 6
Seller

Phase 7
Admin

Phase 8
Modal/Drawer/Table/Form audit

Phase 9
Light/Dark

Phase 10
Khmer/English

Phase 11
Device matrix

Phase 12
Regression
```

---

# 114. FUNCTIONAL REGRESSION

Responsive changes must not break:

```text
F01–F18
authentication
cart
checkout
payments
inventory
orders
returns
reviews
reports
exports
```

---

# 115. TESTING

Run:

```text
TypeScript type check
frontend tests
production build
mobile browser tests
desktop regression tests
```

Do not only test phone and accidentally break desktop.

---

# 116. DESKTOP REGRESSION

After mobile improvements, verify key desktop pages remain correct.

Do not replace desktop layout globally with mobile layout.

---

# 117. DEFINITION OF DONE

Do not mark smartphone responsive work complete until:

```text
[ ] All routes discovered

[ ] All routes reviewed on smartphone

[ ] 320px tested

[ ] 360px tested

[ ] 375px tested

[ ] 390px tested

[ ] 412px tested

[ ] 430px tested

[ ] No accidental page horizontal overflow

[ ] Header responsive

[ ] Search responsive

[ ] Navigation drawer works

[ ] Homepage hero responsive

[ ] Categories responsive

[ ] Product cards responsive

[ ] Product detail responsive

[ ] Mobile filter drawer works

[ ] Cart responsive

[ ] Checkout responsive

[ ] Payment pages responsive

[ ] Auth responsive

[ ] Customer account responsive

[ ] Seller responsive

[ ] Admin responsive

[ ] Tables mobile strategy implemented

[ ] Forms stack correctly

[ ] Product form works on mobile

[ ] Image uploader works on mobile

[ ] Modals fit viewport

[ ] Drawers fit viewport

[ ] Dropdowns stay on screen

[ ] Toasts stay on screen

[ ] Charts responsive

[ ] Footer responsive

[ ] Touch targets acceptable

[ ] Sticky actions respect safe area

[ ] Software keyboard does not block important forms

[ ] Light mode passes

[ ] Dark mode passes

[ ] Khmer passes

[ ] English passes

[ ] Portrait passes

[ ] Landscape smoke passes

[ ] Loading states responsive

[ ] Empty states responsive

[ ] Error states responsive

[ ] Broken images remain symmetrical

[ ] Long text does not break layout

[ ] No hover-only required actions

[ ] TypeScript passes

[ ] Production build passes

[ ] Mobile browser tests pass

[ ] Desktop regression passes

[ ] Business logic remains unchanged
```

---

# 118. FINAL REPORT

At completion provide:

```text
E-KHMER — SMARTPHONE RESPONSIVE FINAL REPORT

=====================================
1. MOBILE COVERAGE
=====================================

Routes Discovered:

Routes Tested:

Pages Fixed:

Components Fixed:

Remaining Pages:


=====================================
2. DEVICE WIDTHS
=====================================

320px:
PASS / FAIL

360px:
PASS / FAIL

375px:
PASS / FAIL

390px:
PASS / FAIL

393px:
PASS / FAIL

412px:
PASS / FAIL

430px:
PASS / FAIL


=====================================
3. STOREFRONT
=====================================

Header:
PASS / FAIL

Navigation:
PASS / FAIL

Search:
PASS / FAIL

Hero:
PASS / FAIL

Categories:
PASS / FAIL

Product Grid:
PASS / FAIL

Product Card:
PASS / FAIL

Product Detail:
PASS / FAIL

Filters:
PASS / FAIL

Cart:
PASS / FAIL

Checkout:
PASS / FAIL

Payment:
PASS / FAIL

Footer:
PASS / FAIL


=====================================
4. AUTH / CUSTOMER
=====================================

Login:
PASS / FAIL

Register:
PASS / FAIL

Customer Dashboard:
PASS / FAIL

Orders:
PASS / FAIL

Order Detail:
PASS / FAIL

Wishlist:
PASS / FAIL

Addresses:
PASS / FAIL

Returns:
PASS / FAIL

Profile:
PASS / FAIL

Notifications:
PASS / FAIL


=====================================
5. SELLER
=====================================

Dashboard:
PASS / FAIL

Products:
PASS / FAIL

Product Create/Edit:
PASS / FAIL

Inventory:
PASS / FAIL

Orders:
PASS / FAIL

Coupons:
PASS / FAIL

Reports:
PASS / FAIL

Settings:
PASS / FAIL


=====================================
6. ADMIN
=====================================

Dashboard:
PASS / FAIL

Shops:
PASS / FAIL

Users:
PASS / FAIL

Products:
PASS / FAIL

Orders:
PASS / FAIL

Payments:
PASS / FAIL

Returns:
PASS / FAIL

Reports:
PASS / FAIL

Settings:
PASS / FAIL


=====================================
7. MOBILE COMPONENTS
=====================================

Drawer:
PASS / FAIL

Modal:
PASS / FAIL

Dropdown:
PASS / FAIL

Filter Sheet:
PASS / FAIL

Forms:
PASS / FAIL

Tables:
PASS / FAIL

Date Pickers:
PASS / FAIL

Uploads:
PASS / FAIL

Charts:
PASS / FAIL

Toasts:
PASS / FAIL


=====================================
8. QUALITY
=====================================

Horizontal Overflow:
NONE / LIST

Touch Targets:
PASS / FAIL

Safe Areas:
PASS / FAIL

Keyboard Handling:
PASS / FAIL

Long Khmer:
PASS / FAIL

Long English:
PASS / FAIL

Broken Images:
PASS / FAIL

Accessibility Smoke:
PASS / FAIL


=====================================
9. THEMES
=====================================

Mobile Light:
PASS / FAIL

Mobile Dark:
PASS / FAIL


=====================================
10. ORIENTATION
=====================================

Portrait:
PASS / FAIL

Landscape:
PASS / FAIL / NOT TESTED


=====================================
11. REAL DEVICE
=====================================

Android:
PASS / FAIL / NOT VERIFIED

iPhone:
PASS / FAIL / NOT VERIFIED


=====================================
12. TECHNICAL
=====================================

TypeScript:
PASS / FAIL

Frontend Tests:
PASS / FAIL

Production Build:
PASS / FAIL

Mobile Browser Tests:
PASS / FAIL

Desktop Regression:
PASS / FAIL


=====================================
13. FILES
=====================================

Files Changed:

Responsive Components Refactored:

Pages Refactored:


=====================================
14. REMAINING MOBILE ISSUES
=====================================

List


=====================================
15. FINAL DECISION
=====================================

SMARTPHONE RESPONSIVE READY:
YES / NO
```

# FINAL REQUIREMENT

Do not stop after making the homepage look good on a phone.

Continue until:

```text
every route
every page
every form
every table
every modal
every drawer
every checkout step
every seller screen
every admin screen
```

has been audited for smartphone use.

The final result should feel like:

# E-KHMER MOBILE

not:

# E-KHMER DESKTOP SQUEEZED INTO A PHONE