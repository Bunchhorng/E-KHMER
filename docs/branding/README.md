# E-KHMER visual branding concept

**Direction: Market Gate.** A welcoming Cambodian marketplace with a clear, dependable product experience. The new mark combines a shopping bag, a stepped architectural silhouette, and an open gold gateway. It evolves the project's blue-and-gold identity into a simpler shape that works across the storefront, seller tools, and platform administration.

Open [the visual presentation](index.html) in a browser to review all five concepts together.

## Deliverables

| File | Purpose |
| --- | --- |
| [logo-primary-concept.png](logo-primary-concept.png) | Horizontal logo with transparent background; navy wordmark for light surfaces |
| [app-icon-concept.png](app-icon-concept.png) | Square cobalt icon with a white-and-gold version of the mark |
| [splash-screen-concept.png](splash-screen-concept.png) | Portrait launch-screen direction |
| [desktop-storefront-concept.png](desktop-storefront-concept.png) | Customer discovery, search, hero, and product-card direction |
| [desktop-admin-concept.png](desktop-admin-concept.png) | Platform overview, attention queues, and reporting direction |
| [tokens.json](tokens.json) | Proposed colors, spacing, typography, and interaction values |
| [PROMPTS.md](PROMPTS.md) | Exact prompts used with the built-in imagegen tool |

These are raster concept assets and a design handoff. Mockup dates, products, shops, metrics, and promotional copy are illustrative. The existing application has not been restyled by this package.

## Identity and logo

The bag represents commerce; the open gateway represents discovery and access; the stepped silhouette gives the mark a local architectural character. Blue carries the product identity, navy anchors text, and gold adds warmth.

Use the horizontal logo on the desktop header, sign-in pages, documents, and communications. Use the symbol on app launch surfaces and in compact navigation. Keep the spelling **E-KHMER** consistent in both languages; translate surrounding interface copy rather than redrawing the name as body text.

- Keep clear space of at least one quarter of the symbol's height around the lockup.
- At small sizes, use the symbol instead of squeezing the full wordmark. A simplified favicon derivative should be drawn and inspected at 16px and 32px.
- Use the supplied navy wordmark on light surfaces. A white wordmark variant belongs in the vector handoff before dark-header use.
- Preserve the symbol's proportions. Avoid stretching, rotating, adding outlines, or putting it on busy photographs.
- Flatten the small tonal variations in the generated artwork during vector production; the target identity uses solid fills.

## Color system

| Role | Color | Use |
| --- | --- | --- |
| Brand blue | `#2563EB` | Main actions, selected navigation, links, chart emphasis |
| Deep navy | `#0B1739` | Wordmark, headings, primary text |
| Market gold | `#F2B544` | Small identity accents, highlights; pair with navy text |
| Canvas | `#F8FAFC` | Page background |
| Surface | `#FFFFFF` | Cards, menus, forms |
| Muted text | `#64748B` | Secondary labels on white |
| Border | `#E2E8F0` | Quiet separation between surfaces |

Use neutral surfaces for most of a screen. Blue should identify the most useful action; gold should be a supporting accent rather than another primary action color. Keep green, amber, and red for business statuses and combine each with a readable label or icon.

Calculated solid-color contrast: white on blue **5.17:1**, navy on gold **9.59:1**, navy on white **17.56:1**, and muted text on white **4.76:1**. White on gold is only **1.83:1**, so gold buttons should use navy text. Check actual rendered states, borders, focus indicators, and opacity separately.

Dark mode can retain the project's current slate system: canvas `#0B0F19`, surface `#1E293B`, border `#334155`, text `#F8FAFC`, primary `#3B82F6`, accent `#FACC15`. Treat dark mode as its own token set; validate text and button foregrounds rather than simply inverting colors.

## Typography and bilingual layout

Continue the project's existing **Inter** for English and **Kantumruy Pro** for Khmer. Use medium and semibold weights for navigation and controls, and bold sparingly for headings and totals.

| Element | Suggested size / line height |
| --- | --- |
| Storefront hero | 44–56px / 1.15 English; allow extra height for Khmer |
| Page heading | 28–32px / 1.4 |
| Section heading | 20–24px / 1.5 |
| Body and form controls | 16–18px / 1.5 English, 1.75 Khmer |
| Supporting labels | 14px minimum; allow wrapping |

Keep the existing Khmer line-height safeguards. Avoid fixed-height text containers that clip vowel signs or stacked marks. Reserve room for translated labels and validate both locales on narrow screens. Monetary totals use tabular numerals where available and retain the project's USD behavior.

The language control displays the **destination language**: Khmer content shows the UK flag, and English content shows the Cambodia flag. A localized tooltip and accessible name explain the action. The button changes language on activation and keeps the saved preference.

## App icon

The square cobalt background creates a clear launcher silhouette. The white bag and gold gateway retain the identity without a wordmark. Keep a generous safe area around the mark, and let the destination platform apply its mask instead of baking rounded corners into the artwork.

The supplied PNG is an icon concept master, not a complete platform export package. Rebuild the vector mark first, then export the required launcher, adaptive/maskable, favicon, and install-icon variants. Inspect each at its actual displayed size.

## Splash screen

Use a pale canvas with a vertically stacked mark and wordmark near the optical center. Keep decoration faint, with the gateway motif in a corner. The launch experience should feel calm and quick.

The loading bar in the mockup illustrates placement. In implementation, reflect real loading progress or use an indeterminate indicator; never add an artificial launch delay. Localize the marketplace subtitle and footer if retained. Keep marketing copy optional and respect reduced-motion preferences. Build the screen with native layout or application components so it adapts across aspect ratios.

## Desktop UI direction

### Customer storefront

Lead with a compact brand header, useful search, category navigation, and a single clear hero action. Show products on neutral backgrounds with consistent image ratios. Product cards use a flex column and a bottom action area so price and Add to cart controls align even when descriptions vary. Keep seller attribution and stock information easy to scan.

Use a centered content area up to the project's existing 1600px maximum. At desktop widths, use four product columns where titles and controls fit comfortably. Reduce columns as space narrows instead of squeezing text. Keep the hero copy separate from the product photography and use only approved product images and promotional claims in production. The mockup's buyer-protection wording requires a real approved policy before use.

### Super-admin and seller tools

Use a stable sidebar, restrained top bar, and a clear page title. Keep platform health and actionable queues prominent. Route shop approvals, payment exceptions, moderation, and support into their existing workflows. Detailed merchandising analysis belongs on Reports. Seller screens use the same visual system with navigation appropriate to the user's role.

The admin concept explores active-shop and pending-shop summary cards. Bind every displayed metric to an available authorized API field before implementation; the artwork does not add backend endpoints. Match payment labels to the actual payment state rather than assuming every exception is a failure.

Retain the existing role permissions, shipping switch persistence and pending state, deliberate admin-correction controls, and meaningful status labels. Visual styling should not change their business behavior.

### Shared components

- Use a 4px spacing base, with common gaps of 8, 12, 16, 24, and 32px.
- Cards use a 12px radius, a thin border, and very light shadow only when elevation helps.
- Primary controls use blue; secondary controls use a surface with a border. Keep interactive targets at least 44px high.
- Use the existing Lucide icon family, usually at 20px, with consistent stroke weight.
- Provide visible keyboard focus, labeled form fields, loading and error states, and an explicit empty state.
- Make status text and shape understandable without relying on color alone.
- Keep animation brief, approximately 150–200ms, and honor reduced motion.

## Implementation handoff

1. Review the direction with the team, including a Khmer-speaking reviewer for the identity and localized layouts.
2. Rebuild the selected symbol and wordmark as clean SVG masters. Prepare horizontal, stacked, symbol-only, monochrome, and reversed variants. Raster artwork is a visual reference, not a substitute for that final vector work.
3. Export platform-specific icon sizes and reconstruct the splash with responsive layout.
4. Map the proposed tokens to `frontend/src/style.css` and the existing Tailwind variables. Scope the rollout by page or component.
5. Apply the shared header, navigation, buttons, and cards, then storefront and dashboard layouts. Preserve existing API and authorization behavior.
6. Review English and Khmer, light and dark modes, desktop and mobile, keyboard navigation, actual-size icons, and long product titles before release.

Generated using the built-in imagegen tool. All five selected images are saved beside this guide; exact generation prompts are recorded in [PROMPTS.md](PROMPTS.md).
