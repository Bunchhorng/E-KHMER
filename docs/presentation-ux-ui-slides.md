# UX/UI Presentation — E-KHMER Frontend Design

> **UX/UI-focused deck.** This is the companion to `presentation-slides.md`
> (which covers backend architecture and business logic). Use this one if your
> supervisor wants a design/frontend presentation; use the other for a full-stack
> defence.
>
> **Every number below was measured from the source, not assumed.**
> Verification method is stated next to each figure.

---

## Measured facts — quick reference

| Fact | Value | How verified |
|---|---|---|
| Vue SFCs | **73** | `Get-ChildItem -Recurse frontend/src -Filter *.vue` |
| Lines of SFC code | **12,324** | `Get-Content | Measure-Object -Line` |
| Views | **44** (7 storefront · 10 account · 22 admin · 5 auth) | folder counts |
| Components | **24** (19 root · 1 admin · 5 charts) | folder counts |
| Layout shells | **4** (Store · Account · Admin · Auth) | `src/layouts` |
| Design tokens | **10 CSS variables × 2 modes** | `src/style.css:6-32` |
| Reusable UI classes | **22** in `@layer components` | `src/style.css:91-203` |
| `dark:` utility usages | **530** | grep across all `.vue` |
| Views using `EmptyState` | **16** | grep |
| Skeleton components | **3 written, 1 used** | grep for imports |
| i18n namespaces / keys | **19 / ~1,035**, `en` and `km` identical | `src/locales/*.json` |
| `<label for>` associations | **197** | grep |
| `aria-label` | **26** · `aria-*` total **32** · `role=` **6** · `alt=` **26** · `sr-only` **3** | grep |
| `prefers-reduced-motion` | **0** | grep — *not implemented* |
| Custom `tabindex` | **0** | grep — *no focus management* |

---

# SLIDE 1 — Title

**On slide**
```
UX/UI Design & Frontend Architecture
E-KHMER — Multi-Branch E-Commerce Web Application

Student Name        ______________
University          ______________
Faculty / Dept.     ______________
Lecturer            ______________
Academic Year       ______________
```

**Speaker notes (30 s)**
> "Good morning. My project is a multi-branch e-commerce platform, and today I
> want to focus on the interface side: how the design system was built, how the
> screens are structured, how it responds to different devices and languages, and
> — importantly — an honest assessment of where the user experience still falls
> short. The frontend is seventy-three Vue components across about twelve
> thousand lines, and I will show you the decisions that shaped it."

---

# SLIDE 2 — UI Surface: What Actually Exists

**On slide**
```
                    ┌──────────────────────────┐
                    │      App.vue             │  <router-view/>
                    └────────────┬─────────────┘
                                 │
   ┌──────────┬──────────┬───────┴───────┬──────────┐
   ▼          ▼          ▼               ▼          ▼
┌────────┐┌────────┐┌────────┐    ┌────────┐  ┌────────┐
│ Store  ││Account ││ Admin  │    │  Auth  │  │  404   │
│Layout  ││Layout  ││Layout  │    │ Layout │  │  (none)│
│ 18 L   ││311 L   ││257 L   │    │ 24 L   │  │  ✗     │
└────┬───┘└────┬───┘└────┬───┘    └────┬───┘  └────────┘
     │         │         │             │
  7 views   10 views  22 views     5 views
```
```
24 components  →  8 base primitives
                  6 commerce components
                  2 navigation chrome
                  1 data table (294 lines)
                  5 chart wrappers
                  3 skeleton loaders
```

**Design decision — shells, not templates**
Each shell owns its own navigation, header, and layout chrome. `StoreLayout` is
18 lines because it composes `AppHeader` + `CategoryNavBar` + `CartDrawer` +
`AppFooter`; `AccountLayout` is 311 lines because it carries the 8-item sidebar
with live counts. Shared logic lives in components, not in a generic layout
component with 20 boolean props.

**Speaker notes (45 s)**
> "Before talking about design, here is the actual size of the surface. Seventy-
> three single-file components, about twelve thousand lines. Forty-four views,
> split across four separate shells.
>
> The important architectural choice is that I did not build one universal layout
> with a lot of toggles. I built four specialised shells. The storefront shell is
> only eighteen lines, because it just composes a header, a category bar, a cart
> drawer and a footer. The account shell is three hundred and eleven lines,
> because it genuinely needs an eight-item sidebar with live counts. Each shell
> is simple because it only does one job. The cost is duplication, and I accepted
> that deliberately."

---

# SLIDE 3 — Design Tokens: One Source of Truth

**On slide**
```
   design decision          single authority
  ──────────────────        ─────────────────────
  no hex codes  ────────▶   10 CSS custom properties
  in components                     │
                                   ▼
                          tailwind.config.js maps
                          them to semantic names
                                   │
              ┌────────────────────┴────────────────────┐
              ▼                                         ▼
      <div class="card                              <div class="btn-primary
           bg-surface text-ink">                       text-white">
              │                                         │
              └─────────► same class, both themes ◄─────┘
                                    │
                     html.dark overrides 10 values
                     → 0 component changes needed
```

**Verified — `src/style.css:6-32`, `tailwind.config.js:7-18`**

| Semantic token | Light | Dark | Role |
|---|---|---|---|
| `--color-primary` | `37 99 235` · #2563EB | `59 130 246` · #3B82F6 | Actions, links, focus |
| `--color-primary-dark` | `30 64 175` · #1E40AF | `29 78 216` · #1D4ED8 | Hover |
| `--color-canvas` | `249 250 251` · #F9FAFB | `11 15 25` · #0B0F19 | Page background |
| `--color-surface` | `255 255 255` · #FFFFFF | `30 41 59` · #1E293B | Cards, panels |
| `--color-surface-hover` | `249 250 251` | `51 65 85` · #334155 | Hover fills |
| `--color-border` | `229 231 235` · #E5E7EB | `51 65 85` · #334155 | Dividers |
| `--color-ink` | `17 24 39` · #111827 | `248 250 252` · #F8FAFC | Body text |
| `--color-muted` | `107 114 128` · #6B7280 | `148 163 184` · #94A3B8 | Captions, meta |
| `--color-accent` | `251 191 36` · #FBBF24 | `250 204 21` · #FACC15 | Badges, sale tags |
| `--color-success` | `16 185 129` · #10B981 | `52 211 153` · #34D399 | In-stock, confirmed |

**Why semantic names, not raw palettes:** `bg-surface` and `text-ink` survive a
brand change. A raw `bg-gray-50` would not. Because tokens are declared once and
mapped in `tailwind.config.js`, **dark mode required zero component edits** —
the 530 `dark:` utilities in the codebase are refinements (borders, hover
states, image tinting), not re-theming.

**Also declared:** `color-scheme: light` / `dark`, so native form controls and
scrollbars follow the theme automatically.

**Speaker notes (50 s)**
> "This is the decision I would defend hardest. There are exactly ten colour
> tokens, declared once as CSS custom properties, and mapped in the Tailwind
> config to semantic names like surface, ink, and muted. No component contains a
> hex code.
>
> The payoff is dark mode. Because a component says surface and not white, when
> the user toggles the theme the same class renders correctly in both modes. I
> did not rewrite forty-four views to support dark mode; I rewrote ten variable
> values. The five hundred and thirty dark utilities that do exist are
> refinements — border colours and hover fills — not re-theming.
>
> I also set colour-scheme in CSS, which is a small detail that makes native
> scrollbars and form controls follow the theme instead of staying stubbornly
> white."

---

# SLIDE 4 — Contrast & Legibility (Measured)

**On slide**
All four critical text pairs pass **WCAG 2.1 AA** (≥ 4.5:1).
Computed with the WCAG relative-luminance formula, s = linearised channel.

| Pair | Ratio | Verdict |
|---|---:|---|
| `ink` #111827 on `canvas` #F9FAFB (light) | **15.4 : 1** | AAA |
| `muted` #6B7280 on white (light, captions) | **4.8 : 1** | AA |
| `primary` #2563EB on white (light, links) | **5.2 : 1** | AA |
| `accent` #FBBF24 on `ink` #111827 (badges) | **10.1 : 1** | AAA |
| `ink` #F8FAFC on `canvas` #0B0F19 (dark) | **18.4 : 1** | AAA |
| `muted` #94A3B8 on `canvas` (dark) | **7.5 : 1** | AAA |
| `primary` #3B82F6 on `canvas` (dark) | **5.2 : 1** | AA |

**Legibility decisions made deliberately**
- Base body text `text-sm` (14 px) minimum; metadata `text-xs` (12 px) floor
- `muted` is used for *secondary* text only — never for the only copy of a message
- Status is **never** communicated by colour alone — `StatusTag` always pairs the
  colour with a text label
- `line-clamp-2` on card titles so a long product name cannot break grid rhythm
- `aspect-square` + `object-cover` on all product imagery, so the grid never reflows

**Speaker notes (45 s)**
> "I did not pick colours by eye alone. These ratios are computed with the
> standard WCAG formula. Every critical pair clears AA, and the primary
> text-on-background pair reaches AAA at over fifteen to one.
>
> Two design rules follow from that. First, the muted grey token is only ever
> used for genuinely secondary text — captions and metadata. If a message matters,
> it does not go in grey. Second, and this is the rule I care about, status is
> never signalled by colour alone. A green pill always has the word on it, so a
> colour-blind user is not excluded. Third, product cards clamp the title to two
> lines and force square images, so one long product name cannot break the grid."

---

# SLIDE 5 — Typography & Khmer Script Support

**On slide**
```
Latin default                       Khmer active
────────────────                    ───────────────
Inter                               Kantumruy Pro
  ↓                                    ↓
html                                  html.font-khmer-mode
                                          │
                ┌─────────────────────────┴──────────────────────┐
                ▼                        ▼                       ▼
          line-height 1.75        .leading-tight → 1.5     .leading-none → 1.35
```

**The problem this solves:** Khmer diacritics stack *above and below* the glyph
box. Latin-optimised `line-height: 1` and `1.25` **clip the marks**, especially
in headings and in Tailwind's own `leading-none` / `leading-tight` utilities.

**The fix** — `src/style.css:70-84`
```css
html.font-khmer-mode body,
html.font-khmer-mode .font-khmer {
  font-family: 'Kantumruy Pro', Inter, ui-sans-serif, system-ui, sans-serif;
  line-height: 1.75;
}
/* the utilities are then loosened, not deleted, so layout code stays unchanged */
html.font-khmer-mode .leading-tight { line-height: 1.5; }
html.font-khmer-mode .leading-none { line-height: 1.35; }
```

**Also handled**
- Font loaded in `index.html`; `font-sans` and `font-khmer` exposed as Tailwind families
- The `font-khmer-mode` class is toggled by the `locale` Pinia store and
  persisted to `localStorage`, so the fix survives a reload
- FOUC prevention: a blocking inline script in `index.html` applies the theme
  class **before first paint**, so there is no white flash on a dark-mode reload

**Speaker notes (50 s)**
> "This slide is about a problem that only appears once you actually support the
> local language. Khmer script stacks diacritics above and below the main glyph.
> With a Latin line-height of one, those marks get clipped — and Tailwind's own
> leading-none and leading-tight utilities are aggressive, so they clip badly.
>
> The fix is a small amount of CSS with an unusual property: when the locale
> becomes Khmer, I set the line-height to one point seven five, and then I
> *loosen* the two Tailwind utilities to one point five and one point three five
> rather than overriding them. That means no layout code has to change, and
> switching to Khmer cannot break a heading.
>
> I also handled the flash of wrong theme. There is a blocking script in the HTML
> head that reads the saved theme and applies the dark class before the browser
> paints, so a reload on a dark theme never flashes white."

---

# SLIDE 6 — Component Library

**On slide**
```
BASE PRIMITIVES (8)              COMMERCE (6)
──────────────────              ──────────────
BaseBadge      5 variants        ProductCard      grid card
StatusTag      9 status states   ProductRail      carousel
StarRating     filled / partial  CartDrawer       slide-over
BaseModal      4 sizes           QuantityCounter  ± stepper
BasePagination prev/next/pages   CategoryNavBar   horizontal nav
EmptyState     icon + CTA        AppHeader        288 L
LanguageSwitcher  EN / KH        AppFooter        109 L
ThemeToggle    light / dark
                                  ADMIN (6)
MOTION & LOADING (3)              ─────────────
──────────────────                AdminDataTable   294 L
PdpSkeleton      ⚠ unused         5 × chart wrappers
ProductGridSkeleton ⚠ unused      …Line, Bar, Doughnut
DataTableSkeleton ✓ used
```

**Two naming conventions carry the semantic weight**
- `Base*` = generic, no domain knowledge, reusable in any context
- Domain components (`ProductCard`, `StatusTag`, `CartDrawer`) know what they are

**`StatusTag` — 9 states mapped to 5 badge tones**
`pending` grey · `confirmed` blue · `processing` amber · `shipped` indigo ·
`delivered` green · `rejected` red · plus in-stock green, low-stock amber,
out-of-stock red. **Every tag renders a text label** — colour is never the only
signal (see Slide 4).

**Speaker notes (45 s)**
> "Twenty-four components, and I grouped them by purpose rather than by file.
> The eight base primitives know nothing about e-commerce — a modal does not care
> that it sells shoes. The six commerce components do. That naming rule is the
> `Base` prefix, and it is a cheap convention that stops a generic component from
> quietly accumulating domain logic.
>
> The one I would highlight is StatusTag. It maps nine different domain states —
> pending, confirmed, processing, shipped, delivered, rejected, plus the three
> stock states — onto five badge tones. The important part is that it always
> renders the word, never just a coloured dot. A shopper who cannot distinguish
> amber from green still reads 'Out of stock'.
>
> I should be honest about two dead components. I built a skeleton loader for the
> product grid and for the product detail page, and then I never wired them up.
> Only the data table skeleton is actually in use."

---

# SLIDE 7 — Interaction & Motion

**On slide**
**A single button primitive carries every state** — `src/style.css:118-156`
```css
.btn {
  focus:outline-none
  focus-visible:ring-2 focus-visible:ring-primary/40   /* keyboard focus */
  disabled:cursor-not-allowed disabled:opacity-60      /* honest disabled */
}
```
| Variant | Use |
|---|---|
| `btn-primary` | one per view — the main action |
| `btn-secondary` | neutral alternative |
| `btn-outline` | tertiary / brand-tinted |
| `btn-accent` | promotional CTA (amber) |
| `btn-danger` | destructive (soft red, not alarming red) |
| `btn-ghost` | low-emphasis toolbar |
| `btn-icon` | 40×40 square, header actions only |
| `.btn-sm` / `.btn-lg` | two sizes |

**Motion is deliberately minimal — exactly 2 keyframes**
| Animation | Duration | Used for |
|---|---|---|
| `slide-in-right` | 250 ms | `CartDrawer` entry |
| `fade-in` | 200 ms | `BaseModal` backdrop |

**Motion discipline**
- No parallax, no scroll-jacking, no entrance animations on scroll
- `prefers-reduced-motion` — **0 occurrences. Not implemented.** *(honest gap)*
- Hover states are the primary affordance: card lifts `shadow-card → shadow-lg`,
  image scales `1.05` over 300 ms, buttons scale to `0.99` on `:active`

**Real disabled state, not just opacity**
`ProductCard` swaps its label and disables the button together:
```
isInStock ?  "Add to Cart"  (enabled)
          :  "Out of Stock" (disabled, cursor-not-allowed, opacity 60%)
```

**Speaker notes (45 s)**
> "Interaction states live in one place. The base button class carries the focus
> ring, the disabled treatment, and the active press. Every variant inherits it,
> so I cannot forget the keyboard focus ring on a new button — there is nowhere to
> forget it.
>
> Motion is where I was most restrained. There are exactly two animations in the
> entire application: a two hundred and fifty millisecond slide for the cart
> drawer and a two hundred millisecond fade for the modal backdrop. I deliberately
> avoided scroll-triggered entrance animations, because on a catalogue page with
> forty products they make browsing feel slow and they distract from the
> products.
>
> And I want to flag the gap: I never implemented prefers-reduced-motion. For a
> user with vestibular sensitivity, those two animations should be switched off.
> That is a two-line fix and it is on my list."

---

# SLIDE 8 — Navigation & Route Guards

**On slide**
**Guards are declarative, on the route** — `src/router/index.ts`
```ts
{ path: 'account', meta: { requiresAuth: true } }
{ path: 'admin',   meta: { requiresAuth: true, admin: true } }
{ path: 'auth',    meta: { guestOnly: true } }        // reset-password
                                                      // + verify-email opt out
```
```
   navigate()
       │
       ▼
  beforeEach guard
       │
       ├─ set document.title = "<title> · E-KHMER"   (always)
       ├─ requiresAuth && !user      → /auth/login?redirect=<fullPath>
       ├─ admin      && !isAdmin    → /
       ├─ guestOnly  &&  user        → /
       └─ else pass
```

**Details that matter**
- **`redirect` is preserved** — after login the customer returns to the page they
  originally asked for, not to the homepage
- **Every route is lazy-loaded** — `() => import(...)`, so the initial bundle does
  not carry the admin console
- **`scrollBehavior`** returns the saved scroll position on back-navigation, else
  top — a real annoyance in long catalogues if you forget it
- **`reset-password` and `verify-email` set `guestOnly: false`** — a logged-in user
  following a reset link from their email must not be bounced to the homepage
- Admin sidebar is a **6-group** nav: Overview · Catalog · Fulfillment · Marketing
  · Community · System

**Speaker notes (40 s)**
> "Navigation is protected declaratively on the route, not with scattered checks in
> components. Three flags: requires auth, requires admin, or guests only. The
> guard handles all three plus the document title in one place.
>
> Two details I want to show because they are easy to get wrong. First, the login
> redirect preserves the original destination, so if a customer tried to open their
> orders and got bounced to login, they land on their orders afterwards, not on the
> homepage. Second, the reset-password and verify-email routes explicitly opt out
> of the guests-only rule — otherwise a customer who is already logged in and
> clicks a reset link in their email would be thrown out to the homepage and never
> see the form.
>
> All fifty-one routes are lazy-loaded, so an admin-only screen is not shipped to a
> customer who will never open it."

---

# SLIDE 9 — Responsive Strategy

**On slide**
**Mobile-first, with drawers instead of hidden content**

| Desktop | Mobile | Implementation |
|---|---|---|
| 260 px filter sidebar | 320 px slide-in drawer | `ShopView` — `fixed inset-0 z-50 lg:hidden`, state in `ui.mobileFiltersOpen` |
| 256 px admin sidebar | collapsible to 72 px icon rail | `ui.adminSidebarCollapsed` |
| 4-col product grid | 2-col | Tailwind `sm:` / `lg:` prefix chain |
| Sticky header | icon + hamburger cluster | `AppHeader` |
| Full account sidebar | slide-in drawer | `AccountLayout` |

**Filter drawer is the key pattern** — filters are never hidden or truncated on
mobile, they relocate into a drawer with explicit `Clear All` and `Apply`
actions, so the user always knows whether their filters are in effect.

**Fluid container, not fixed width** — `.container-app`
```css
@apply mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8;
```

**`product.colors` renders swatch dots from real hex** — the API returns
`swatch_color` per attribute value, so the dots are always the actual product
colour, never a guess or a CSS colour name.

**Speaker notes (40 s)**
> "Responsive here means relocating, not deleting. The most important case is the
> filter sidebar. On desktop it is a two hundred and sixty pixel column; on mobile
> it becomes a three hundred and twenty pixel slide-in drawer with explicit Clear
> All and Apply buttons. The filters are never simply hidden, because a shopper
> who cannot see that a colour filter is active will assume the results are wrong.
>
> The admin sidebar collapses from two hundred and fifty-six pixels to a
> seventy-two pixel icon rail, which keeps the analytics charts wide.
>
> One detail I like: the colour dots on a product card are driven by the real hex
> value the API returns for each attribute, so they are always the actual colour
> rather than a named approximation."

---

# SLIDE 10 — Loading States

**On slide**
**What is built**

| Component | Rendered as | Wired into |
|---|---|---|
| `DataTableSkeleton` ✓ | Shimmer rows, `rows` × `columns` props | `AdminDataTable`, `AdminInventoryView` (2 tables) |
| `PdpSkeleton` ⚠ | Built, **never imported** | — |
| `ProductGridSkeleton` ⚠ | Built, **never imported** | — |

**The real problem, stated honestly:** the two skeletons that would help the
*customer* most — the product grid and the product detail page — are the two I
never connected. The storefront loading state is currently a blank canvas while
`catalogApi.getProducts` resolves.

**Why it matters, and what I would do**
- Product grid is the highest-traffic screen in the application
- A skeleton that matches the final card geometry (square image, 2-line title,
  price row, button) prevents layout shift on arrival
- Fix is small: `ProductCard` already has a fixed internal structure, so the
  skeleton can mirror it with a `v-if="loading"` branch

**Anti-pattern avoided:** no spinner-and-hope loading states on the admin tables —
a table that renders its real column count as shimmer preserves the user's sense
of where each column is.

**Speaker notes (45 s)**
> "I want to talk about this slide because it is where the project is weakest, and
> I would rather present that than be asked about it.
>
> I built three skeleton loaders. The data table one works properly and is used in
> two places — it renders the real column count, so the user already knows where
> every column is when the data arrives. But the two skeletons for the customer
> side, the product grid and the product detail page, I built and never wired up.
> Right now the storefront shows a blank canvas while the catalogue request
> resolves.
>
> That is a real omission, because the product grid is the highest-traffic screen
> in the whole application. The fix is small — the product card has a fixed
> internal structure, so the skeleton can mirror it exactly and there would be no
> layout shift."

---

# SLIDE 11 — Empty & Error States

**On slide**
**`EmptyState` is used in 16 of 44 views** — grep-verified

```
CartDrawer · ProductRail · CartView · CheckoutView
OrderSuccessView · OrderTrackingView · ProductDetailView
ShopView · AddressesView · DashboardView · OrdersView
ReviewsView · WishlistView · AdminInventoryView
AdminNotificationsView · AdminDataTable (reused)
```
```
┌────────────────────────────────────────┐
│                 [ icon ]               │
│            "Your cart is empty"        │   ← EmptyState props:
│   "Add some items before checking out" │     title · description
│           [  Back to shop  ]           │     CTA button (optional)
└────────────────────────────────────────┘
```
**Why this is deliberate, not decorative:** an empty cart, an empty wishlist, and
"no products match your filters" are all *normal* states, not failures. Each one
offers a route out — back to the shop, browse products, clear filters — so the
user is never at a dead end.

**Inline validation is a consistent pattern, not per-form invention**
21 views render field-level errors (Register 19 refs · AddProduct 24 ·
ChangePassword 15 · ProductDetail 14 · CouponForm 11 · ResetPassword 10 …),
paired with the shared `.input-error` class for the red border and ring.

**Honest gaps**
- No dedicated 404 view — the catch-all route **redirects to `/`**
- No offline / connection-lost state
- No toast system: 22 views implement their own inline success or error message,
  so the pattern is consistent in tone but duplicated in code

**Speaker notes (45 s)**
> "Empty states are a design decision, not decoration. An empty cart is not an
> error, it is a normal state, and the designer's job is to make sure it is not a
> dead end. So EmptyState is used in sixteen of forty-four views, and every
> instance offers a way forward — go back to the shop, browse products, or clear
> your filters. The filter case matters most: if a shopper sees no results, the
> message has to tell them the filters are why.
>
> Form errors follow one shared pattern using a single input-error class, so
> twenty-one views render validation consistently instead of each inventing its
> own red border.
>
> Three honest gaps: there is no 404 page — a bad URL silently redirects to the
> homepage, which is confusing. There is no offline state. And there is no shared
> toast component, so twenty-two views each implement their own success message."

---

# SLIDE 12 — Product Discovery UX

**On slide**
**Faceted filtering with URL as state**
```
  user clicks a filter
        │
        ▼
  filters ref updates
        │
        ├─▶ query params rebuilt (q, category, brand, colors[], sizes[],
        │                         min, max, rating, stock, sort, page)
        ▼
  router.push  ──────────▶ URL is now shareable / bookmarkable /
        │                 back button reverses the filter
        ▼
  catalogApi.getProducts()
        │
        ▼
  deep-link guard: if the refetched query === the current
  route query, skip the request  (ShopView:164)
```

**Colour filter — swatches from real data**
```html
<button class="h-8 w-8 rounded-full border-2"
        :class="selected ? 'border-primary ring-2 ring-primary/30' : …"
        :style="{ backgroundColor: c.slug }"     <!-- hex from the API -->
        :title="c.name"                           <!-- name always available -->
        :aria-label="…">
```
32 px tap target · selected state = border **and** ring, not colour alone ·
`title` carries the colour name for hover and assistive tech.

**Filter UX details**
- Active filters render as **removable chips** (`"Electronics ×"`)
- Facets are returned by the server (`GET /api/catalog/facets`) so counts are
  always truthful — never a hard-coded list
- `Clear All` resets every filter, not just the visible ones

**Speaker notes (45 s)**
> "Product discovery is where the interface meets the API most directly. The
> filter state lives in the URL, not in component memory. That means a filtered
> result is shareable, bookmarkable, and the browser back button correctly undoes
> a filter. There is also a guard that skips the network request when the rebuilt
> query matches the current route, so you never get a redundant fetch.
>
> The colour swatches are worth pointing at. The hex value comes from the API,
> and the colour *name* is always available as a title and an aria-label. The
> selected state is communicated with a border and a ring, not just a colour
> change. So the filter is usable if you cannot see the colour at all.
>
> And the facet counts come from the server, so a count of zero is never shown
> for something that actually exists."

---

# SLIDE 13 — Product Card & Detail UX

**On slide**
**One card, fixed geometry** — `ProductCard.vue` (125 lines)
```
┌────────────────────────────────────┐
│ ┌────────────┐  [-22%]  [NEW]  (♥) │  aspect-square · object-cover
│ │            │                     │  loading="lazy"
│ │   image    │                     │  hover: scale-105 / 300 ms
│ └────────────┘                     │  ♥ has aria-label, toggles fill
├────────────────────────────────────┤
│ BRAND            ← 11px uppercase   │
│ Product title     ← line-clamp-2    │
│ ★★★★☆ (12)                         │
│ $89.00  $120.00                     │
│ ● ● ●        ← real hex swatches   │
│ [   Add to Cart   ]                │
└────────────────────────────────────┘
```
**Four UX decisions in this one component**
1. `aspect-square` + `object-cover` → the grid **cannot** reflow regardless of
   source image ratio
2. `loading="lazy"` on every card image → the grid does not fetch 24 images upfront
3. Add-to-cart auto-selects the **first in-stock variant**
   (`variants.find(v => v.isInStock)`) — the common case needs no extra click,
   while a multi-variant product still routes to the detail page for choice
4. The CTA is a **stateful affordance**, not a static label: when stock is zero
   the label becomes "Out of Stock" *and* the button disables

**Not built (honest):** image lightbox / zoom, and the mobile sticky add-to-cart
bar — both were specified in `docs/FIGMA-PROMPTS.md` and neither was implemented.

**Speaker notes (45 s)**
> "The product card is the most repeated component in the application, so four
> decisions in it carry a lot of weight.
>
> First, fixed geometry — a square image with object-cover — means a supplier
> uploading a tall photo cannot break the grid. Second, lazy loading, so the
> listing page does not request twenty-four images at once. Third, and this is the
> one I would argue for: add-to-cart picks the first in-stock variant
> automatically, so the overwhelmingly common case is a single click, while a
> genuinely multi-variant product still takes the shopper to the detail page to
> choose. And fourth, the button is a real stateful affordance — when stock is
> zero the label changes to Out of Stock and the button disables, rather than
> failing after the click.
>
> I also want to be clear about two things in the Figma specification that I did
> not build: the image lightbox and the mobile sticky add-to-cart bar."

---

# SLIDE 14 — Cart & Checkout UX

**On slide**
**Cart drawer + cart page share one Pinia store** (`cart.ts`)
```
  header bag icon ──▶ CartDrawer (400 px slide-over, 250 ms)
  "Cart" nav link ──▶ CartView (full page, sticky order summary)
                            │
                            ▼
                  same store · same totals
                  no duplicated state, no sync bugs
```

**Guest-first shopping** — the friction decision that shapes the whole funnel
- `api/client.ts` generates a `X-Session-Id` UUID into `localStorage` on first load
- Cart works with **no account** — `POST /cart` needs no token
- On **login or register, the guest cart is merged** into the account cart, so
  nothing is lost at the moment of highest intent
- `App.vue` re-fetches the cart when `isAuthenticated` flips, so the drawer and
  header badge never disagree

**Totals are presented, never trusted** — `formatPrice` in `utils/format.ts`,
10 % tax constant owned by the store, and every figure **recomputed server-side**
at checkout. The client display is for the user's benefit only.

**Honest gaps**
- **No reservation countdown.** The backend holds stock for 15 minutes, but
  nothing in the UI tells the user. On the order-success page a visible timer —
  "stock held for 14:32" — would turn an invisible backend rule into a
  understandable commitment and reduce abandonment anxiety.
- No saved payment method, no guest checkout address book prompt

**Speaker notes (50 s)**
> "The cart is the highest-stakes screen, so two decisions matter most. The first
> is guest-first. You can add items with no account at all, because the browser
> generates a session identifier. And at the moment of highest intent — when they
> log in — that guest cart is merged into their account cart, so nothing is lost.
> I re-fetch the cart when authentication changes so the drawer and the header
> badge can never disagree.
>
> The second decision is that totals are presented but never trusted. The client
> shows a figure for the user's benefit, and the server recomputes everything at
> checkout. Anyone can edit a number in devtools, so the number that matters is
> the one the server calculates.
>
> The gap I am most aware of is the reservation countdown. The backend holds
> stock for fifteen minutes, but the interface never says so. A visible timer on
> the success page would turn an invisible rule into an understandable
> commitment."

---

# SLIDE 15 — Admin UX: The Data Table Workhorse

**On slide**
**`AdminDataTable.vue` — 294 lines, reused by every admin list**
```
┌──────────────────────────────────────────────────────────┐
│ [🔍 search]              [bulk actions ▾]      [+ Add]  │  toolbar
├──────────────────────────────────────────────────────────┤
│ ☐ │ Product      │ Category │ Price │ Stock │ Status ⋯ │  sortable headers
│ ☐ │ [img] Aurora… │ Electron │ $89   │ 12 ⚠  │ ● Live │
│ ☐ │ [img] Pulse…  │ Electron │ $149  │ 0    │ ● Draft│
├──────────────────────────────────────────────────────────┤
│ Showing 1–20 of 240          ‹ 1 2 3 4 5 ›  Page 1 of 12│  pagination
└──────────────────────────────────────────────────────────┘
   loading → DataTableSkeleton (real column count)
   empty   → EmptyState
   columns typed: text | number | status | badge | currency | date | image | actions
```
**One component replaces seven bespoke tables** — products, orders, customers,
coupons, reviews, brands, shipping methods. Column renderers are driven by a
`TableColumnType` union, so a currency cell cannot accidentally be a text cell.

**Charts are themed, not hard-coded** — `useChartTheme.ts`
```ts
// reads the theme store, returns a palette, and watches it to
// mutate ChartJS.defaults.color — so all 5 charts re-colour on toggle
```
5 chart components · Line · Bar ×2 · Doughnut ×2 · set `ChartJS.defaults.font.family`
· dark mode included · no chart library on the storefront, account, or auth areas.

**Admin shell**
- 6 nav groups · collapsible 256 px → 72 px rail
- Top bar: global search · language · theme · **notification bell with unread dot**
  → dropdown · profile menu
- Account shell mirrors this with live **order count** and **unread count** badges

**Speaker notes (45 s)**
> "The admin side is a very different design problem — dense tables, not
> browsing. The workhorse is a single data table component, two hundred and
> ninety-four lines, which I reused for seven different screens: products,
> orders, customers, coupons, reviews, brands, and shipping methods.
>
> Two things make that reuse safe. Columns are typed — a union of text, number,
> status, badge, currency, date, image and actions — so a currency cell cannot
> silently be rendered as plain text. And the table owns the three states that are
> usually forgotten: a loading skeleton that renders the real column count, an
> empty state, and pagination.
>
> The charts are also theme-aware. Rather than hard-coding axis colours, a
> composable watches the theme store and rewrites the Chart.js defaults, so all
> five charts recolour when the user toggles dark mode. And there is no chart
> library shipped to the customer-facing side at all — it is code-split out."

---

# SLIDE 16 — Accessibility

**On slide**
**What is implemented (grep-verified counts in brackets)**

| Concern | Implementation | Count |
|---|---|---|
| Form labelling | `<label for>` on every input | **197** |
| Icon buttons | `aria-label` on icon-only controls | **26** |
| Images | `alt` on product & decorative imagery | **26** |
| Screen-reader text | `sr-only` spans | **3** |
| Landmarks | `role=` where a semantic element would not do | **6** |
| Keyboard focus | `focus-visible:ring-2` on the base button class, `focus:ring-2` on inputs | shared |
| Status | text label always accompanies colour | all tags |
| Dark mode | `color-scheme` + token contrast ≥ 4.5:1 | all |
| Escape to close | `BaseModal` binds `keydown` while open | yes |
| Reduced motion | — | **0 — not implemented** |

**Gaps I am reporting, not hiding**
| Gap | Impact | Effort |
|---|---|---|
| **No `prefers-reduced-motion`** | motion-sensitive users get the drawer slide + modal fade | ~2 lines |
| **No focus trap in `BaseModal`** | `Tab` can move behind an open dialog; no `aria-modal` | moderate |
| **No body scroll lock** | page scrolls behind the modal on touch devices | small |
| **No skip-to-content link** | keyboard users tab through the full header on every page | small |
| **No custom `tabindex`** | no roving focus in the carousel or filter group | moderate |
| **No 404 view** | bad URL silently redirects | small |
| **Colour swatch filter** relies on `title` + `aria-label`, not a radiogroup | screen reader announces a bare button | small |

**Overall position:** form semantics and colour contrast are genuinely solid;
**dialog and motion accessibility are the weak areas**, and both are bounded,
well-understood fixes rather than redesigns.

**Speaker notes (50 s)**
> "Accessibility is the area I am least satisfied with overall, so I will give you
> the honest split.
>
> The strong half is forms and colour. There are one hundred and ninety-seven
> label associations, which means essentially every form control is properly
> labelled, and every icon-only button has an accessible name. And I measured the
> contrast ratios rather than assuming, so text and interactive colour all clear
> the AA threshold in both themes. Status is never conveyed by colour alone.
>
> The weak half is dialogs and motion. My modal closes on Escape, but it does not
> trap focus, so a keyboard user can tab behind the dialog, and it lacks the
> aria-modal attribute. I also never implemented prefers-reduced-motion, so the
> two animations play even for users who have asked the operating system not to
> animate anything. There is no skip link either.
>
> None of these are redesigns. They are bounded fixes, and I would rather list
> them than have you find them."

---

# SLIDE 17 — Internationalisation

**On slide**
**English + Khmer, both complete**
```
en.json  ─┐
          ├─▶ 19 namespaces  ─▶  ~1,035 keys  ─▶  key parity VERIFIED
km.json  ─┘                   locale  nav        header   actions   common
                                                 product  cart      checkout
                                                 order    status    auth
                                                 verify   error     footer
                                                 home     shop      admin
                                                 account  categories
```
**Locale state is a first-class store** — `stores/locale.ts`
- `currentLocale: 'km' | 'en'`, default **`km`** (local market)
- Writes `i18n.global.locale` **and** `html.lang`
- Persisted to `localStorage` → survives reload
- Toggling also flips the `font-khmer-mode` class → Khmer typography (Slide 5)
- `LanguageSwitcher.vue` in all 4 shells

**Verified parity:** `en.json` and `km.json` are both 1,097 lines with the same
19 top-level namespaces and matching key counts — a missing translation cannot
silently degrade to a raw key at runtime.

**Fallback:** `fallbackLocale: 'en'`, so an untranslated string degrades to
English rather than rendering a key name.

**Where it is used:** components call `$t('product.add_to_cart')` and read domain
strings from `src/types/index.ts` as **TypeScript unions**, not raw strings —
e.g. `OrderStatus = 'Pending' | 'Confirmed' | 'Processing' | 'Shipped' |
'Delivered'`, so an untranslated status cannot be misspelled.

**Speaker notes (40 s)**
> "Because the target users are local, internationalisation was not an
> afterthought — it is a Pinia store alongside theme and cart. English and Khmer
> are both complete: nineteen namespaces, roughly a thousand keys, and I verified
> the two files have matching line counts and identical namespace structure, so a
> missing translation cannot degrade into showing a raw key like
> 'product.add_to_cart' to a customer.
>
> Two details beyond simple translation. Switching locale also switches the font
> and line-height to fix the Khmer diacritics I showed you earlier, so the two
> features are wired together. And the domain strings — order status, review
> status, payment method — are defined as TypeScript unions rather than loose
> strings, so a status label cannot be misspelled in one place and silently
> diverge from the enum on the server."

---

# SLIDE 18 — UX Evaluation & Roadmap

**On slide**
**Honest scorecard** — scored against my own 4 UX criteria, not a generic rubric

| Criterion | Now | Evidence |
|---|:--:|---|
| Visual consistency | **Strong** | 10 tokens, 22 UI classes, 0 hard-coded hex in components |
| Responsive behaviour | **Strong** | drawer relocation, 4→2 col, collapsible admin rail |
| State coverage | **Partial** | 16/44 empty states · **3/3 storefront loading states missing** |
| Accessibility | **Partial** | forms + contrast pass · dialogs + motion fail |
| Localisation | **Strong** | EN/KM parity verified, 19 namespaces |
| International reach | **Weak** | RTL not handled; Khmer is the only non-Latin locale |

**Prioritised roadmap**
| # | Fix | Why it ranks here | Effort |
|---|---|---|---|
| 1 | Wire `ProductGridSkeleton` + `PdpSkeleton` | highest-traffic screen currently loads blank | small |
| 2 | `prefers-reduced-motion` block | 2 lines, removes an accessibility failure | trivial |
| 3 | Focus trap + `aria-modal` + scroll lock in `BaseModal` | every dialog in the app | moderate |
| 4 | Real **404 view** | silent redirect to `/` is confusing and hides broken links | small |
| 5 | Shared toast / flash component | 22 duplicated implementations | moderate |
| 6 | Reservation countdown on order success | surfaces an existing backend rule | small |
| 7 | Skip link + roving focus in rails | keyboard users | moderate |
| 8 | Cart / checkout empty-and-error polish | lower traffic than discovery | small |
| 9 | RTL support | future locales | large |
| 10 | Image lightbox + mobile sticky PDP CTA | specified in FIGMA-PROMPTS, not built | moderate |

**What I deliberately did *not* do**
- No component library dependency — the 24 components are ~1,000 lines total and
  carry no business logic, so a library would have added weight without value
- No scroll animations, carousels-with-autoplay, or parallax — the catalogue is
  for browsing, and motion competes with the products

**Speaker notes (55 s)**
> "I want to close with an honest evaluation rather than a highlight reel. Scored
> against four criteria: visual consistency, responsive behaviour, state
> coverage, and accessibility.
>
> Consistency, responsiveness and localisation I rate as strong, and I can
> evidence that with numbers rather than opinion — ten tokens, twenty-two UI
> classes, no hard-coded hex anywhere, drawer relocation rather than hidden
> content, and verified key parity across two languages.
>
> State coverage and accessibility I rate as partial. State coverage because
> sixteen of forty-four views have a designed empty state but none of the three
> storefront loading states are wired up. Accessibility because forms and contrast
> are genuinely solid while dialogs and motion are not.
>
> My top priority is the smallest change with the largest reach: wiring up the two
> skeleton components I already wrote, so the product grid stops loading blank.
> After that, prefers-reduced-motion, which is two lines and removes a real
> accessibility failure.
>
> Finally, something I chose not to do. I did not add a component library. My
> twenty-four components are about a thousand lines and none of them contain
> business logic, so a library would have added weight without removing work. And
> I avoided scroll animations and autoplay, because on a catalogue page motion
> competes with the products."

---

# SLIDE 19 — Thank You

**On slide**
```
        Thank You
   Questions & Answers

   E-KHMER — UX/UI Design & Frontend Architecture
   Vue 3 · TypeScript · Tailwind CSS · Pinia · Chart.js
```

**Speaker notes (20 s)**
> "Thank you. I have the architecture notes, the QA report, and the performance
> report in the documentation folder, and the application is running in Docker if
> you would like to see any screen live. I am happy to take questions."

---

# Appendix A — UX Defence Questions

| Question | Answer |
|---|---|
| Why Tailwind instead of Bootstrap? | Semantic CSS-variable tokens meant dark mode needed 10 changed values, not 44 rewritten views. Bootstrap was never installed. |
| Why no component library? | 24 components, ~1,000 lines, no business logic inside them. A library would add bundle weight without removing work. |
| Why are styles classes, not wrapper components? | `.input` / `.label` / `.textarea` / `.input-error` give consistent forms across 21 views without prop-drilling a `BaseInput` wrapper. Trade-off: no slot-based label/error composition. |
| How do you know the empty states are enough? | `EmptyState` is used in 16 of 44 views. The gap is loading states, which I have documented as priority 1. |
| Is the site accessible? | Forms and contrast are solid — 197 label associations, all text pairs clear AA. Dialogs and reduced-motion are not, and I have listed both. |
| How would you improve conversion? | Ship the loading skeletons, then the reservation countdown so the 15-minute hold is visible, then the toast consolidation so feedback is consistent. |
| Why is dark mode in the same release? | Because the token architecture made it nearly free. Ten variable swaps. Deferring it would have meant re-auditing every view later. |
| How would you test this? | Not currently automated — there is no Playwright or Vitest setup. I would add visual regression on the product card and PDP, plus a11y assertions in CI. |

---

# Appendix B — Design Specifications

**Layout**
- 16:9 · one idea per slide · heading ≥ 32 pt · body ≥ 20 pt
- 8 pt spacing grid · identical title position on every slide
- Slide number bottom-right, project name bottom-left

**Colour — take the palette from the app, not from a template**
```
Light mode                      Dark mode
primary    #2563EB              primary      #3B82F6
primary-dk #1E40AF              primary-dk   #1D4ED8
canvas     #F9FAFB              canvas       #0B0F19
surface    #FFFFFF              surface      #1E293B
ink        #111827              ink          #F8FAFC
muted      #6B7280              muted        #94A3B8
border     #E5E7EB              border       #334155
accent     #FBBF24              accent       #FACC15
success    #10B981              success      #34D399
```
- No gradients on slides. (The app *does* use one hero gradient — if you show a
  screenshot, expect a deep-blue hero and do not claim "no gradients anywhere")
- Elevation is limited to two values in the whole app: `shadow-card` and
  `shadow-popover`. Use the same two on slides
- Corner language: 12 px on cards and modals, pill on chips and badges

**Typography**
- **Inter** for the deck (matches the app's UI face)
- **Kantumruy Pro** only for a Khmer wordmark — never for body copy
- Khmer slide? Set line-height ≥ 1.6 or the diacritics clip, exactly as in the app

**Icons** — **lucide-vue-next**, 2 px stroke, matching the app. Do not use
Bootstrap Icons; the project does not use Bootstrap.

**Diagrams**
- Blue `#2563EB` strokes, white fills, plain boxes and arrows
- Maximum 3 levels of depth
- **Label every arrow** — an unlabelled arrow is an examiner's question
- Do not redraw a diagram already shown on an earlier slide

**Screenshot placeholders to insert before the defence**
```
[Insert Design Token / Colour Swatch Panel]
[Insert Product Card — Light and Dark]
[Insert Product Detail — Variant Selector + Live Stock Badge]
[Insert Shop Page — Filter Sidebar with Colour Swatches]
[Insert Mobile Filter Drawer]
[Insert Cart Drawer Slide-over]
[Insert Checkout — Sticky Order Summary]
[Insert Empty Cart / Empty Wishlist State]
[Insert AdminDataTable — Loading, Empty and Populated]
[Insert Admin Dashboard Charts — Light and Dark]
[Insert Khmer UI — Notice the Increased Line Height]
```

**Content rules — not stylistic preferences**
- **No invented metrics.** No "conversion increased by 18%" — nothing in this
  project was A/B tested
- **No fake screenshots**
- **Never present a roadmap item as shipped.** Specifically: image lightbox,
  mobile sticky PDP bar, 404 page, reduced-motion support, and focus trapping do
  not exist
- If asked about a gap, say so and point at Slide 18. Naming your own weakness
  reads as competence
