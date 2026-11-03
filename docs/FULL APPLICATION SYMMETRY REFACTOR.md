# E-KHMER — GLOBAL UI/UX AUDIT + COMPLETE DESIGN SYSTEM + FULL APPLICATION SYMMETRY REFACTOR

Act as a senior **UI/UX Architect, Design System Engineer, Vue.js + TypeScript Engineer, Tailwind CSS Expert, Responsive Design Engineer, Accessibility Engineer, E-Commerce Product Designer, and Frontend QA Lead**.

I have an existing multi-vendor e-commerce marketplace called:

# E-KHMER

Technology:

```text
Frontend:
Vue.js
TypeScript
Tailwind CSS

Backend:
Laravel

Database:
MySQL

Cache:
Redis

Architecture:
Multi-vendor / Multi-shop

Roles:
Customer
Shop Owner
Shop Manager/Admin
Super Admin
```

The screenshots I provided are only examples of my current UI.

# IMPORTANT

Do NOT assume the screenshots represent all pages.

I have many more:

```text
routes
pages
forms
tables
dashboards
dialogs
modals
drawers
filters
customer screens
seller screens
admin screens
authentication screens
checkout/payment screens
account screens
settings screens
reports
error pages
empty states
responsive states
```

Your job is to discover ALL of them from the source code and make the entire project visually consistent.

---

# 1. MAIN OBJECTIVE

Take my existing UI and improve it into:

# ONE COMPLETE E-KHMER DESIGN SYSTEM

Everything must feel like it was created from one professional template.

The entire application should be:

```text
Symmetrical
Consistent
Modern
Professional
Premium
Clean
Responsive
Accessible
Khmer-friendly
English-friendly
Light-mode complete
Dark-mode complete
Reusable
Maintainable
```

The goal is NOT to redesign only the screenshots.

The goal is:

# EVERY UI — EVERY PAGE — EVERY COMPONENT — EVERYWHERE

---

# 2. DO NOT LIMIT YOUR AUDIT TO KNOWN PAGES

Before changing UI, inspect the complete frontend project.

Search:

```text
frontend/src/router/
frontend/src/views/
frontend/src/pages/
frontend/src/components/
frontend/src/layouts/
frontend/src/stores/
frontend/src/composables/
frontend/src/styles/
frontend/src/assets/
```

Also inspect route definitions, sidebar menus, navigation menus, dynamic route imports, modal components, nested layouts, and shared components.

Search globally for:

```text
.vue
router
routes
component
modal
drawer
dialog
table
form
dashboard
card
input
select
button
badge
tabs
toast
dropdown
pagination
skeleton
empty
error
loading
```

Do not rely only on filenames I mention.

---

# 3. BUILD A COMPLETE UI INVENTORY FIRST

Before redesigning anything, produce an internal inventory of ALL existing UI.

Classify each screen into:

```text
PUBLIC / STOREFRONT

AUTHENTICATION

CUSTOMER ACCOUNT

SELLER / SHOP MANAGEMENT

SUPER ADMIN

SHARED COMPONENTS

SYSTEM / ERROR / UTILITY
```

---

# 4. DISCOVER ALL ROUTES

Read the Vue router.

Create a complete route matrix internally:

```text
Route
Page Component
Layout
Role
Current UI pattern
Theme support
Responsive status
Shared components
Problems found
```

Examples may include, but are NOT limited to:

```text
/
 /shop
 /shops
 /products/:slug
 /categories
 /search
 /cart
 /checkout
 /payment
 /order-success

/login
/register
/forgot-password
/reset-password
/verify-email

/account
/account/orders
/account/orders/:id
/account/wishlist
/account/reviews
/account/returns
/account/addresses
/account/profile
/account/password
/account/notifications
/account/support

/seller/*
/seller/shops/*
/seller/products/*
/seller/orders/*
/seller/inventory/*
/seller/coupons/*
/seller/reports/*
/seller/settings/*

/admin/*
/admin/dashboard
/admin/shops
/admin/users
/admin/products
/admin/orders
/admin/payments
/admin/returns
/admin/reviews
/admin/coupons
/admin/reports
/admin/settings
/admin/notifications
/admin/audit-logs
```

Do NOT assume this list is complete.

Discover the actual routes.

---

# 5. UI COVERAGE MUST BE SOURCE-BASED

After reading the source, create a list like:

```text
TOTAL ROUTES FOUND:
XX

PUBLIC:
XX

CUSTOMER:
XX

SELLER:
XX

ADMIN:
XX

AUTH:
XX

UTILITY:
XX
```

Every route must be assigned a redesign/review status.

No page should be silently skipped.

---

# 6. ALSO DISCOVER HIDDEN UI

Not every interface has a route.

Find:

```text
Modals
Drawers
Popovers
Dropdowns
Tabs
Tooltips
Confirmation dialogs
Image preview dialogs
Product variant dialogs
Payment dialogs
Refund dialogs
Order actions
Bulk-action menus
Filters
Date pickers
Upload components
Notifications
Toasts
Context menus
Search results
Autocomplete
```

These must also use the same design system.

---

# 7. DISCOVER ALL UI STATES

For every major page/component inspect:

```text
Normal
Hover
Focus
Active
Selected
Disabled
Loading
Saving
Success
Warning
Error
Empty
Offline/network failure
Unauthorized
Forbidden
Not found
```

All states need consistent styling.

---

# 8. SCREENSHOTS ARE STYLE REFERENCES

Use my current screenshots to understand the existing E-KHMER direction:

```text
Dark navy
Royal blue
Blue accent
Warm yellow promotional accent
Khmer-first commerce identity
Structured dashboard layout
Modern card-based administration UI
```

Keep the identity.

Improve it.

Do NOT discard the existing brand.

---

# 9. DESIGN DIRECTION

Use:

# MODERN PREMIUM CAMBODIAN COMMERCE

Personality:

```text
Trustworthy
Professional
Modern
Premium
Clean
Structured
Technology-oriented
Commerce-focused
Khmer-first
International-ready
```

Avoid:

```text
excessive gradients
excessive glassmorphism
rainbow dashboards
oversized rounded cards
random shadows
random colors
template inconsistency
```

---

# 10. CREATE DESIGN TOKENS FIRST

Do NOT begin page-by-page redesign immediately.

Create centralized tokens.

Recommended semantic tokens:

```text
background

surface
surface-raised
surface-muted
surface-hover
surface-active

border
border-strong
border-focus

text-primary
text-secondary
text-muted
text-disabled

primary
primary-hover
primary-active
primary-soft

accent

success
warning
danger
info
```

---

# 11. LIGHT MODE MUST BE FIRST-CLASS

Create a real light theme.

Do not just invert dark colors.

Light hierarchy:

```text
App background
↓
Surface
↓
Raised surface
↓
Interactive hover
```

Use:

```text
soft neutral page background
white cards
subtle borders
dark readable text
E-KHMER blue actions
careful accent yellow
```

---

# 12. DARK MODE MUST BE FIRST-CLASS

Dark hierarchy:

```text
App background
Sidebar
Card
Nested card
Input
Hover
Selected
```

These surfaces must be visually distinguishable.

Do not make every dark element the same navy.

---

# 13. LIGHT/DARK PARITY

Every component must be checked in:

```text
Dark Khmer
Dark English
Light Khmer
Light English
```

This applies to:

```text
buttons
forms
tables
modals
dropdowns
cards
product cards
charts
sidebars
navigation
toasts
badges
empty states
```

---

# 14. CREATE ONE SPACING SYSTEM

Use one scale:

```text
4
8
12
16
20
24
32
40
48
64
80
96
```

No random spacing.

---

# 15. GLOBAL PAGE ALIGNMENT

All pages must follow predictable horizontal alignment.

Public/storefront:

```text
same max-width
same left edge
same right edge
same responsive padding
```

Dashboard:

```text
same content padding
same page-header alignment
same grid alignment
```

---

# 16. SYMMETRY IS A CORE REQUIREMENT

Every page must be visually balanced.

Check:

```text
equal card heights
equal column widths
aligned headings
aligned card bottoms
consistent gaps
consistent image areas
consistent button placement
consistent icon boxes
consistent form row heights
```

---

# 17. CREATE A GLOBAL TYPOGRAPHY SYSTEM

Create:

```text
Display
H1
H2
H3
H4
Body Large
Body
Body Small
Caption
Label
Overline
```

Use one hierarchy throughout.

---

# 18. KHMER TYPOGRAPHY

Optimize specifically for Khmer.

Review:

```text
font family
font weight
line height
heading spacing
button height
table row height
label spacing
input vertical centering
```

Khmer text must never look cramped.

---

# 19. ENGLISH TYPOGRAPHY

English and Khmer must remain visually equivalent.

Switching language must not:

```text
break grids
overflow buttons
change card height
break sidebar
shift navigation
```

---

# 20. CREATE ONE UI COMPONENT LIBRARY

Audit existing shared components and create/refactor reusable primitives.

At minimum:

```text
AppButton
AppIconButton

AppCard
AppPanel
AppStatCard

AppInput
AppTextarea
AppSelect
AppCheckbox
AppRadio
AppSwitch
AppDatePicker

AppBadge
AppStatusBadge

AppAvatar

AppModal
AppConfirmDialog
AppDrawer

AppDropdown
AppPopover
AppTooltip

AppTabs

AppTable
AppPagination

AppBreadcrumb

AppPageHeader
AppSectionHeader

AppSearchInput

AppFilterBar

AppEmptyState
AppErrorState
AppSkeleton
AppSpinner

AppToast
AppAlert

AppFileUpload
AppImageUpload

AppDivider
```

Do not duplicate primitive UI across pages.

---

# 21. COMPONENT API CONSISTENCY

Reusable components should support consistent props.

Example:

```text
variant
size
loading
disabled
icon
fullWidth
```

Do not create:

```text
BlueButton
BigButton
AdminButton
SellerButton
ProductButton
```

as separate visual systems.

Use one Button system.

---

# 22. STANDARDIZE BUTTONS EVERYWHERE

Variants:

```text
Primary
Secondary
Outline
Ghost
Danger
Success
```

Sizes:

```text
sm
md
lg
```

Same:

```text
height
radius
padding
font weight
icon size
focus
loading
disabled
```

across ALL pages.

---

# 23. STANDARDIZE FORM CONTROLS EVERYWHERE

All inputs/selects/textareas should have identical:

```text
label
height
radius
border
background
focus
placeholder
helper
error
disabled
```

Do not let seller forms look different from admin forms.

---

# 24. STANDARDIZE CARDS

Define card variants:

```text
Default Card
Interactive Card
Stat Card
Product Card
Media Card
Form Section Card
Marketing Card
```

Same fundamental:

```text
radius
border
surface
padding
shadow
```

---

# 25. STANDARDIZE TABLES EVERYWHERE

All data tables should share:

```text
header
row
border
hover
selection
actions
empty
loading
pagination
```

Use for:

```text
Products
Orders
Users
Customers
Payments
Returns
Refunds
Coupons
Inventory
Reports
Audit Logs
Notifications
```

---

# 26. STANDARDIZE STATUS COLORS

Define globally.

Example:

```text
ACTIVE
green

PAID
green

COMPLETED
green

PENDING
amber

PROCESSING
blue

DRAFT
gray

FAILED
red

CANCELLED
red/muted

REFUNDED
purple or appropriate semantic color
```

Same status must never appear in different colors on different pages.

---

# 27. STANDARDIZE PAGE HEADERS

Every dashboard/list/detail page should use:

```text
Breadcrumb optional

Title
Description

Primary Action
Secondary Action
```

Same spacing everywhere.

---

# 28. STANDARDIZE SECTION HEADERS

Use:

```text
Eyebrow optional
Section title
Description
Right-side action
```

same pattern across storefront/dashboard.

---

# 29. STANDARDIZE FILTERS

Create one:

```text
FilterToolbar
```

Use consistently for:

```text
Products
Orders
Users
Reports
Payments
Returns
Coupons
Inventory
Reviews
```

Support:

```text
Search
Status
Date
Category
Shop
Sort
Reset
```

---

# 30. STANDARDIZE EMPTY STATES

Every empty page must show:

```text
icon/illustration
title
description
optional CTA
```

No unexplained blank space.

---

# 31. STANDARDIZE LOADING STATES

Use:

```text
Page skeleton
Card skeleton
Product skeleton
Table skeleton
```

Do not show a tiny spinner inside an otherwise empty large page.

---

# 32. STANDARDIZE ERROR STATES

API error should show:

```text
clear title
friendly message
retry action
```

Never raw:

```text
AxiosError
undefined
500 Internal Server Error
```

---

# 33. STANDARDIZE MODALS

All modals should share:

```text
header
title
description
body
footer
close
backdrop
radius
shadow
padding
```

Sizes:

```text
sm
md
lg
xl
fullscreen/mobile
```

---

# 34. STANDARDIZE CONFIRM DIALOGS

Use one dialog for:

```text
delete
cancel
refund
disable
remove
logout
dangerous settings
```

No browser `confirm()`.

---

# 35. STANDARDIZE DRAWERS

Use common drawer for:

```text
mobile navigation
mobile filters
cart
notifications
details
```

---

# 36. STANDARDIZE TOASTS

Same placement and appearance for:

```text
success
error
warning
info
```

---

# 37. GLOBAL IMAGE SYSTEM

The screenshots clearly show broken/missing product images in some cards.

Create reusable:

```text
AppImage
ProductImage
AvatarImage
```

with:

```text
loading
success
error fallback
aspect ratio
object fit
lazy loading
```

---

# 38. NEVER SHOW BROKEN IMAGE ICONS

If source fails:

show:

```text
E-KHMER placeholder
product icon
category icon
shop initials
avatar fallback
```

depending on context.

---

# 39. STOREFRONT AUDIT

Discover and redesign ALL public commerce pages.

Not limited to homepage.

Include actual routes found for:

```text
Home
Shop
Shops
Shop Detail
Category
Brand
Search
Product Detail
Wishlist entry points
Cart
Checkout
Payment
Order Success
FAQ
Shipping
Returns Policy
Privacy
Terms
Support
Newsletter
```

---

# 40. HOMEPAGE

Audit ALL sections dynamically.

Do not assume only sections shown in screenshots.

Discover current sections from source.

Potential:

```text
Promotion bar
Header
Search
Category navigation
Hero
Trust features
Categories
Flash deals
Featured products
Recommended products
New arrivals
Popular products
Featured shops
Brands
Promotional banner
Testimonials
Newsletter
Footer
```

Standardize all of them.

---

# 41. PRODUCT CARD GLOBAL REFACTOR

There must be only ONE visual product-card system unless a clearly different compact variant is required.

Variants could be:

```text
ProductCard
ProductCardCompact
ProductCardHorizontal
```

but all share same tokens.

---

# 42. PRODUCT CARD SYMMETRY

All cards in same grid:

```text
same image height
same title space
same rating row
same price row
same CTA position
same height
```

Use line clamps.

---

# 43. CATEGORY CARDS

All category cards:

```text
same image ratio
same height
same icon
same title location
same count location
same hover
```

---

# 44. SHOP CARDS

If shops are listed:

create consistent:

```text
ShopCard
```

with:

```text
logo
name
rating
product count
verification/status if public
CTA
```

---

# 45. STORE HEADER

Create a unified responsive header system.

Desktop:

```text
Promotion bar
Logo
Search
Language
Theme
Wishlist
Cart
Account
Category navigation
```

Mobile:

```text
Menu
Logo
Actions
Search row
```

---

# 46. CUSTOMER ACCOUNT

Discover ALL customer routes.

Refactor all into one account system.

Use:

```text
AccountLayout
AccountSidebar
AccountMobileNavigation
AccountPageHeader
```

---

# 47. CUSTOMER PAGES

Ensure visual consistency across:

```text
Dashboard
Orders
Order Detail
Addresses
Wishlist
Reviews
Returns
Profile
Change Password
Notifications
Support
```

and every other discovered customer screen.

---

# 48. AUTHENTICATION

Refactor all auth screens:

```text
Login
Register
Forgot Password
Reset Password
Verify Email
```

into one:

```text
AuthLayout
```

Same:

```text
card
logo
heading
form
button
links
background
light/dark
```

---

# 49. SELLER / SHOP MANAGEMENT

Discover all `/seller` routes.

Do NOT only redesign the pages in my screenshots.

Every seller page must use the same:

```text
DashboardLayout
Sidebar
Header
PageHeader
Card
Table
Form
Filter
Modal
```

---

# 50. SELLER PAGES

Audit all discovered seller functionality, including likely:

```text
Dashboard
Shop selection
Shop management
Products
Product Create
Product Edit
Variants
Inventory
Orders
Order Detail
Fulfillment
Coupons
Returns
Reviews
Reports
Exports
Notifications
Settings
Users/Staff if supported
```

Use actual source as authority.

---

# 51. ADMIN

Discover every `/admin` route.

All admin screens must use exactly the same system.

Likely:

```text
Dashboard
Shops
Users
Customers
Products
Categories
Brands
Orders
Payments
COD
Returns
Refunds
Reviews
Coupons
Reports
Exports
Notifications
Audit Logs
Settings
Support
```

Again: actual source defines final list.

---

# 52. ADMIN + SELLER SHARE ONE SHELL

Use:

```text
DashboardLayout
```

for both.

Role determines:

```text
menu
permission
shop context
actions
```

not visual architecture.

---

# 53. DASHBOARD SIDEBAR

One standard:

```text
Brand
User
Shop selector where applicable
Navigation sections
Utilities
Logout
```

Same width.

Same icon size.

Same spacing.

Same active state.

---

# 54. DASHBOARD HEADER

One standard:

```text
Menu toggle
Context title
Shop context
Notifications
Language
Theme
Account
```

Admin and seller must match.

---

# 55. STAT CARDS

All stat cards share:

```text
height
padding
radius
icon box
title
value
secondary metric
```

Color differences only for semantics.

---

# 56. CHARTS

If project contains charts:

standardize:

```text
card
header
legend
tooltip
grid
empty state
color palette
height
```

Support both themes.

---

# 57. DASHBOARD FORMS

All create/edit forms should use common:

```text
FormPageLayout
FormSectionCard
FormActions
StickySummaryPanel
```

where appropriate.

---

# 58. PRODUCT CREATE/EDIT

Use my current page as a reference, but standardize the entire form.

Desktop:

```text
Main form
+
Right summary/checklist
```

Tablet/mobile:

```text
single column
```

Ensure every form section uses same card pattern.

---

# 59. OTHER FORMS

Apply same form pattern to:

```text
Shop
Category
Brand
Coupon
User
Inventory
Settings
Delivery Method
Refund
Return
```

and all forms found in source.

---

# 60. DETAIL PAGES

Create common:

```text
DetailPageLayout
```

for:

```text
Order Detail
Product Detail admin side
Shop Detail
User Detail
Return Detail
Payment Detail
```

Pattern:

```text
Header
Summary cards
Main information
Side information
Tabs
Action area
```

---

# 61. SETTINGS PAGES

Create consistent settings template:

```text
Settings navigation
Section heading
Form
Save bar
```

Use for:

```text
Store
Payments
Notifications
General
Security
Shipping
```

if they exist.

---

# 62. REPORT PAGES

Standardize:

```text
PageHeader
Date/filter toolbar
Summary metrics
Charts
Table
Export actions
```

No custom report style separate from dashboards.

---

# 63. ERROR PAGES

Find/create consistent:

```text
403
404
419 if applicable
429
500
Maintenance
Offline
```

Use E-KHMER branding.

---

# 64. MOBILE BOTTOM UI

If current project uses mobile-specific bottom navigation:

standardize it.

If it does not exist:

do not add it merely for decoration unless UX clearly benefits.

---

# 65. RESPONSIVE AUDIT ALL ROUTES

Do not test only a sample page.

Every route should be tested at representative widths.

Minimum:

```text
390
768
1024
1280
1440
1920
```

---

# 66. ROUTE-BY-ROUTE VISUAL QA

For each route verify:

```text
Header
Container
Spacing
Typography
Card
Inputs
Buttons
Tables
Theme
Responsive
Khmer
English
Loading
Empty
Error
```

---

# 67. PAGE SYMMETRY MATRIX

For every route create internal checks:

```text
Outer padding:
PASS/FAIL

Heading alignment:
PASS/FAIL

Grid symmetry:
PASS/FAIL

Card height:
PASS/FAIL

Button consistency:
PASS/FAIL

Input consistency:
PASS/FAIL

Light mode:
PASS/FAIL

Dark mode:
PASS/FAIL

Mobile:
PASS/FAIL
```

Fix failures.

---

# 68. REMOVE DUPLICATED STYLE PATTERNS

Search for repeated long Tailwind utility groups.

Example:

```text
rounded-xl border ...
bg...
px...
py...
```

If repeated widely:

extract shared component/style.

Do not over-abstract one-off layout.

---

# 69. REMOVE RANDOM COLORS

Search for:

```text
#
rgb(
rgba(
text-blue-
bg-blue-
slate-
gray-
```

Review inconsistent page-specific colors.

Move visual decisions into tokens/components where appropriate.

---

# 70. REMOVE RANDOM RADII

Search:

```text
rounded-sm
rounded-md
rounded-lg
rounded-xl
rounded-2xl
rounded-3xl
```

Standardize usage.

---

# 71. REMOVE RANDOM SHADOWS

Standardize shadow use.

Do not let different developers/pages create different depths.

---

# 72. ICON CONSISTENCY

Identify icon libraries currently used.

Standardize on existing preferred library.

Do not mix styles unnecessarily.

---

# 73. ICON SIZE SYSTEM

Use:

```text
14
16
20
24
32
```

based on context.

---

# 74. CONTENT WIDTH

Forms should not unnecessarily stretch full 1600px width.

Use sensible content width.

Tables/dashboards can use more width.

---

# 75. LARGE SCREEN QA

At:

```text
1920px+
```

content should not look lost.

Do not create huge empty spaces like some current homepage sections.

Use proper max-width and responsive grids.

---

# 76. SMALL SCREEN QA

At:

```text
360–390px
```

there must be:

```text
no clipped buttons
no overlapping text
no unusable table
no offscreen modal
no tiny interaction
```

---

# 77. ACCESSIBILITY

Audit all reusable components for:

```text
keyboard navigation
focus
labels
aria
contrast
disabled state
modal focus
dropdown keyboard support
error association
```

---

# 78. FOCUS SYSTEM

Use one focus-ring design everywhere.

Light/dark compatible.

---

# 79. CLICK TARGETS

Aim for roughly:

```text
44x44
```

for important mobile actions.

---

# 80. MOTION

Create one motion standard:

```text
fast:
150ms

normal:
200ms

slow:
300ms
```

Use for:

```text
hover
dropdown
drawer
modal
toast
```

No unnecessary animation.

---

# 81. DESIGN TOKENS

Centralize:

```text
color
spacing
radius
shadow
typography
z-index
motion
container
```

Document them.

---

# 82. Z-INDEX SYSTEM

Define:

```text
base
sticky
dropdown
header
drawer
modal
toast
tooltip
```

Avoid random:

```text
z-9999
```

everywhere.

---

# 83. DARK/LIGHT CHARTS

Charts must update:

```text
axis
grid
tooltip
labels
legend
```

with theme.

---

# 84. THEME PERSISTENCE

Theme should survive reload.

Prevent theme flash where possible.

---

# 85. LANGUAGE PERSISTENCE

Language should survive reload.

Theme + locale must work together.

---

# 86. BROKEN CONTENT

Fix visual behavior when:

```text
image missing
shop logo missing
avatar missing
product deleted
no review
no stock
no notification
no data
```

Use polished fallbacks.

---

# 87. LONG CONTENT

Test very long:

```text
product title
Khmer category
shop name
customer name
email
SKU
order number
```

No layout break.

---

# 88. OVERFLOW RULES

Use:

```text
truncate
line-clamp
wrap
overflow-x-auto
```

intentionally.

No accidental overflow.

---

# 89. SCROLLBARS

Standardize custom scrollbar only if the project already styles them.

Make:

```text
visible
thin
accessible
theme-aware
```

---

# 90. NAVIGATION STATES

Every menu should clearly show:

```text
default
hover
active
focus
disabled
```

---

# 91. RESPONSIVE SIDEBAR

Desktop:

```text
expanded/collapsible
```

Mobile:

```text
drawer
```

Do not shrink desktop sidebar into unusable tiny menu.

---

# 92. BREADCRUMBS

Use consistent breadcrumbs for deep management pages.

Examples:

```text
Products / Create

Orders / EK-0001

Shops / Shop A / Settings
```

---

# 93. STICKY ACTIONS

For long forms:

consider standardized sticky action area:

```text
Save Draft
Preview
Publish
```

only where helpful.

---

# 94. FORM COMPLETION

If current product form uses progress checklist:

generalize styling, not necessarily functionality.

Keep visual consistency.

---

# 95. CONFIRMATIONS

Every destructive action must use consistent danger confirmation.

Examples:

```text
Delete Product
Delete Coupon
Cancel Order
Refund
Disable User
Suspend Shop
```

---

# 96. PRODUCT MEDIA

Standardize:

```text
upload box
preview
primary badge
remove
reorder
progress
error
```

---

# 97. AVATARS

Use common Avatar component:

```text
image
initials fallback
sizes
status optional
```

---

# 98. BADGES

Do not build arbitrary badges on every page.

Use standard component.

---

# 99. PILLS / CHIPS

Create common component for:

```text
category
filter
attribute
tag
selected option
```

---

# 100. TABS

All tabs should share:

```text
height
indicator
spacing
responsive overflow
```

---

# 101. PAGINATION

One pagination system.

Use in:

```text
storefront
admin
seller
customer lists
```

---

# 102. DATE RANGE PICKERS

If multiple report/order pages use date filters:

share one component.

---

# 103. SEARCH INPUTS

Use one SearchInput.

Possible variants:

```text
global
table
compact
```

same design language.

---

# 104. NOTIFICATIONS UI

Customer/admin/seller notification interfaces should share foundational styles.

---

# 105. SUPPORT UI

Support forms/inbox should follow account/dashboard layouts.

No isolated visual system.

---

# 106. CHECKOUT DESIGN

Checkout should share storefront design but use a focused transactional template.

Standardize:

```text
step indicator
address card
shipping card
payment card
order summary
coupon
validation
CTA
```

---

# 107. CART DESIGN

Standardize:

```text
cart item
shop grouping
quantity controls
price
remove
subtotal
summary
```

Use equal spacing.

---

# 108. PAYMENT PAGES

COD/Bakong/KHQR pages should share checkout visual system.

---

# 109. ORDER SUCCESS

Use polished:

```text
success icon
order number
summary
next actions
tracking link
```

Light/dark compatible.

---

# 110. RETURNS

Customer and seller/admin return screens should share semantic status colors and structure.

---

# 111. REPORTS

Use same metric-card component as dashboard where appropriate.

Do not create completely new KPI cards.

---

# 112. CHART COLOR PALETTE

Create fixed palette.

Do not generate random chart colors.

---

# 113. UI CONSISTENCY TEST

After refactor compare:

```text
Storefront button
Customer button
Seller button
Admin button
```

They should clearly belong to same brand.

Same for:

```text
cards
inputs
badges
tables
modals
```

---

# 114. DO NOT REMOVE UNIQUE CONTEXT

Consistency does NOT mean every page looks identical.

Storefront may feel more commercial.

Admin may feel more data-oriented.

Seller may feel operational.

But they must share:

```text
tokens
typography
components
spacing
radii
colors
interaction language
```

---

# 115. REFACTOR ORDER

Use this order:

```text
PHASE 1
Complete UI inventory

PHASE 2
Design tokens

PHASE 3
Primitive components

PHASE 4
Layouts

PHASE 5
Storefront

PHASE 6
Authentication

PHASE 7
Customer account

PHASE 8
Seller

PHASE 9
Admin

PHASE 10
System/error states

PHASE 11
Light/dark audit

PHASE 12
Khmer/English audit

PHASE 13
Responsive audit

PHASE 14
Visual regression

PHASE 15
Functional regression
```

---

# 116. DO NOT CHANGE EVERYTHING IN ONE COMMIT

Refactor by subsystem/components.

Keep functionality testable.

---

# 117. DO NOT CHANGE BUSINESS LOGIC

Do NOT change:

```text
authentication
authorization
checkout calculations
payments
inventory
coupons
orders
returns
reviews
reports
```

except if UI integration requires a small safe correction.

This task is primarily UI/UX.

---

# 118. DO NOT DELETE WORKING FEATURES

If a page looks difficult:

redesign it.

Do not remove it.

---

# 119. DO NOT HIDE INFORMATION

Better hierarchy does not mean removing business information.

Organize it.

---

# 120. TEST AFTER EACH PHASE

Run relevant:

```text
TypeScript
unit/component tests
production build
browser tests
```

after significant refactor.

---

# 121. FINAL SOURCE INVENTORY

At the end report:

```text
Total routes discovered:
XX

Total pages reviewed:
XX

Total layouts:
XX

Total shared components:
XX

Total UI components refactored:
XX

New reusable components:
XX
```

Use actual numbers.

---

# 122. FINAL PAGE COVERAGE TABLE

Report every major route/page:

```text
Page
Role
Old Pattern
New Template
Light
Dark
Mobile
Desktop
Status
```

No unexplained omissions.

---

# 123. FINAL COMPONENT COVERAGE

Report:

```text
Buttons
Inputs
Selects
Checkboxes
Cards
Product Cards
Stat Cards
Tables
Badges
Tabs
Modals
Drawers
Dropdowns
Toasts
Pagination
Filters
Skeletons
Empty states
Error states
Uploads
Images
```

Status:

```text
STANDARDIZED
PARTIAL
NOT USED
```

---

# 124. FINAL THEME MATRIX

Test:

```text
                LIGHT    DARK
Storefront      PASS     PASS
Auth            PASS     PASS
Customer        PASS     PASS
Seller          PASS     PASS
Admin           PASS     PASS
Modals          PASS     PASS
Tables          PASS     PASS
Forms           PASS     PASS
```

---

# 125. FINAL LANGUAGE MATRIX

Test:

```text
                KHMER    ENGLISH
Storefront      PASS     PASS
Auth            PASS     PASS
Customer        PASS     PASS
Seller          PASS     PASS
Admin           PASS     PASS
```

---

# 126. FINAL RESPONSIVE MATRIX

Test:

```text
390px
768px
1024px
1280px
1440px
1920px
```

for representative screens from every application area.

---

# 127. VISUAL REGRESSION SCREENSHOTS

Capture representative before/after screenshots for:

```text
Homepage
Product listing
Product detail
Cart
Checkout
Customer account
Seller dashboard
Seller products
Product create/edit
Admin dashboard
Admin table
Admin form
Modal
Mobile navigation
Light mode
Dark mode
```

---

# 128. QUALITY TARGET

When the refactor is complete, a user should not be able to say:

```text
"This page looks like another template."
```

Instead every screen should communicate:

# E-KHMER

---

# 129. FINAL DESIGN PRINCIPLE

Storefront:

```text
Commerce-focused
visual
engaging
```

Customer account:

```text
simple
friendly
organized
```

Seller:

```text
operational
efficient
data-aware
```

Admin:

```text
professional
information-dense
controlled
```

But all share one visual language.

---

# 130. DEFINITION OF DONE

Do not mark this project-wide UI refactor complete until:

```text
[ ] All routes discovered

[ ] All pages inventoried

[ ] Hidden modal/drawer/dialog UI inventoried

[ ] Design tokens centralized

[ ] Light theme complete

[ ] Dark theme complete

[ ] Khmer complete visually

[ ] English complete visually

[ ] Storefront standardized

[ ] Auth standardized

[ ] Customer account standardized

[ ] Seller standardized

[ ] Admin standardized

[ ] System/error pages standardized

[ ] Shared dashboard shell implemented

[ ] Storefront layout standardized

[ ] Account layout standardized

[ ] Auth layout standardized

[ ] Buttons standardized

[ ] Inputs standardized

[ ] Selects standardized

[ ] Textareas standardized

[ ] Cards standardized

[ ] Tables standardized

[ ] Product cards standardized

[ ] Category cards standardized

[ ] Shop cards standardized

[ ] Stat cards standardized

[ ] Badges standardized

[ ] Filters standardized

[ ] Search standardized

[ ] Pagination standardized

[ ] Tabs standardized

[ ] Modals standardized

[ ] Confirmation dialogs standardized

[ ] Drawers standardized

[ ] Dropdowns standardized

[ ] Toasts standardized

[ ] Tooltips standardized

[ ] Empty states standardized

[ ] Error states standardized

[ ] Loading states standardized

[ ] Skeletons standardized

[ ] Image fallback standardized

[ ] Upload components standardized

[ ] Forms use consistent templates

[ ] Detail pages use consistent templates

[ ] Settings pages use consistent templates

[ ] Report pages use consistent templates

[ ] Checkout/cart/payment visually standardized

[ ] Responsive behavior tested

[ ] Long Khmer tested

[ ] Long English tested

[ ] Missing images tested

[ ] Empty data tested

[ ] Loading tested

[ ] API error tested

[ ] Keyboard focus tested

[ ] Contrast checked

[ ] No page-level accidental horizontal overflow

[ ] No random design-system-breaking colors

[ ] No unnecessary duplicate UI primitives

[ ] TypeScript passes

[ ] Production build passes

[ ] Browser tests pass

[ ] Existing business functionality remains working
```

---

# 131. FINAL REPORT

At completion provide:

```text
E-KHMER — COMPLETE UI/UX SYSTEM AUDIT

========================================
1. UI INVENTORY
========================================

Routes Discovered:

Pages Discovered:

Public Pages:

Auth Pages:

Customer Pages:

Seller Pages:

Admin Pages:

System Pages:

Hidden UI Components:


========================================
2. DESIGN SYSTEM
========================================

Colors:
PASS / NEEDS WORK

Typography:
PASS / NEEDS WORK

Spacing:
PASS / NEEDS WORK

Radius:
PASS / NEEDS WORK

Borders:
PASS / NEEDS WORK

Shadows:
PASS / NEEDS WORK

Motion:
PASS / NEEDS WORK

Z-Index:
PASS / NEEDS WORK


========================================
3. THEMES
========================================

Light Mode:
PASS / NEEDS WORK

Dark Mode:
PASS / NEEDS WORK

Theme Persistence:
PASS / NEEDS WORK

Theme Flash:
PASS / NEEDS WORK


========================================
4. LANGUAGES
========================================

Khmer:
PASS / NEEDS WORK

English:
PASS / NEEDS WORK

Overflow:
PASS / NEEDS WORK


========================================
5. STOREFRONT
========================================

Header:
PASS / FAIL

Navigation:
PASS / FAIL

Home:
PASS / FAIL

Categories:
PASS / FAIL

Products:
PASS / FAIL

Product Detail:
PASS / FAIL

Shop:
PASS / FAIL

Cart:
PASS / FAIL

Checkout:
PASS / FAIL

Payment:
PASS / FAIL

Footer:
PASS / FAIL


========================================
6. AUTH
========================================

Login:
PASS / FAIL

Register:
PASS / FAIL

Forgot Password:
PASS / FAIL

Reset Password:
PASS / FAIL

Verification:
PASS / FAIL


========================================
7. CUSTOMER
========================================

Dashboard:
PASS / FAIL

Orders:
PASS / FAIL

Addresses:
PASS / FAIL

Wishlist:
PASS / FAIL

Reviews:
PASS / FAIL

Returns:
PASS / FAIL

Profile:
PASS / FAIL

Notifications:
PASS / FAIL

Support:
PASS / FAIL


========================================
8. SELLER
========================================

Dashboard:
PASS / FAIL

Products:
PASS / FAIL

Product Form:
PASS / FAIL

Inventory:
PASS / FAIL

Orders:
PASS / FAIL

Coupons:
PASS / FAIL

Returns:
PASS / FAIL

Reports:
PASS / FAIL

Settings:
PASS / FAIL

Other discovered screens:
List


========================================
9. ADMIN
========================================

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

Coupons:
PASS / FAIL

Reviews:
PASS / FAIL

Reports:
PASS / FAIL

Exports:
PASS / FAIL

Notifications:
PASS / FAIL

Settings:
PASS / FAIL

Audit Logs:
PASS / FAIL

Other discovered screens:
List


========================================
10. GLOBAL COMPONENTS
========================================

Buttons:
PASS / FAIL

Inputs:
PASS / FAIL

Cards:
PASS / FAIL

Product Cards:
PASS / FAIL

Tables:
PASS / FAIL

Badges:
PASS / FAIL

Tabs:
PASS / FAIL

Filters:
PASS / FAIL

Pagination:
PASS / FAIL

Modals:
PASS / FAIL

Drawers:
PASS / FAIL

Dropdowns:
PASS / FAIL

Toasts:
PASS / FAIL

Images:
PASS / FAIL

Uploads:
PASS / FAIL

Empty States:
PASS / FAIL

Error States:
PASS / FAIL

Loading/Skeleton:
PASS / FAIL


========================================
11. RESPONSIVE
========================================

390:
PASS / FAIL

768:
PASS / FAIL

1024:
PASS / FAIL

1280:
PASS / FAIL

1440:
PASS / FAIL

1920:
PASS / FAIL


========================================
12. SYMMETRY
========================================

Page Alignment:
PASS / FAIL

Card Heights:
PASS / FAIL

Grid Alignment:
PASS / FAIL

Spacing:
PASS / FAIL

Typography:
PASS / FAIL

Buttons:
PASS / FAIL

Forms:
PASS / FAIL

Tables:
PASS / FAIL


========================================
13. QUALITY
========================================

Accessibility Smoke:
PASS / FAIL

Keyboard:
PASS / FAIL

Focus:
PASS / FAIL

Contrast:
PASS / FAIL

Broken Images:
PASS / FAIL

Long Text:
PASS / FAIL

Horizontal Overflow:
PASS / FAIL


========================================
14. TECHNICAL
========================================

TypeScript:
PASS / FAIL

Production Build:
PASS / FAIL

Browser Tests:
PASS / FAIL

Business Logic Regression:
PASS / FAIL


========================================
15. FILES
========================================

Files Changed:

Components Created:

Components Refactored:

Layouts Created/Refactored:

Pages Refactored:


========================================
16. REMAINING UI ISSUES
========================================

List remaining items.


========================================
17. FINAL UI DECISION
========================================

ONE E-KHMER DESIGN SYSTEM:
PASS / NEEDS WORK
```

---

# FINAL INSTRUCTION

The screenshots I supplied are examples, not the complete scope.

Do not stop when those screenshot pages look better.

Continue inspecting the frontend until:

```text
every route
every page
every layout
every form
every table
every card
every modal
every drawer
every dropdown
every loading state
every empty state
every error state
every responsive state
every light/dark state
```

has been reviewed and aligned to the same E-KHMER design system.

The final goal is:

# ONE BRAND
# ONE TEMPLATE SYSTEM
# ONE COMPONENT SYSTEM
# ONE SPACING SYSTEM
# ONE TYPOGRAPHY SYSTEM
# ONE LIGHT/DARK SYSTEM
# ONE RESPONSIVE SYSTEM

across the entire E-KHMER application.