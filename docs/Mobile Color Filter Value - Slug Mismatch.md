# E-KHMER Improvement #07 —&#x20;

# Mobile Color Filter Value / Slug Mismatch

Act as a senior **Laravel + Vue.js + TypeScript Marketplace Engineer, Catalog Filtering Engineer, Responsive UX Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

Previous fixes:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
F03 — Discount Cap & Checkout Total Consistency
F04 — COD Collection & Paid-State Lifecycle
F05 — Guest Checkout Policy & Web Flow
F06 — Rating & Price Filters
```

Do NOT regress those fixes.

Now work ONLY on:

# F07 — Mobile Color Filters

---

# 1. CURRENT DEFECT

The current audit reproduced this mismatch:

```text
Desktop color option:

Display:
Light Blue

Actual filter value:
Light Blue
```

but mobile sends:

```text
light-blue
```

instead of:

```text
Light Blue
```

The backend/catalog expects the actual attribute value.

Result:

```text
Desktop filter works
Mobile filter may fail or return different results
```

This must be fixed.

---

# 2. PRIMARY GOAL

Make mobile and desktop color filtering use the exact same source-of-truth value.

Correct flow:

```text
Color Option
   ↓
value = "Light Blue"
swatch = "#..."
slug = "light-blue" only for display/keys/URLs if intentionally used
   ↓
Desktop Filter
   ↓
Mobile Filter
   ↓
Same API Query
   ↓
Same Results
```

---

# 3. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect:

```text
frontend/src/views/ShopView.vue
frontend/src/components/
frontend/src/api/
frontend/src/stores/
```

Search for:

```text
color
colors
swatch
slug
value
attribute
attribute_values
light-blue
```

Also inspect backend:

```text
backend/app/Services/CatalogService.php
backend/app/Http/Requests/CatalogFilterRequest.php
backend/app/Http/Resources/
```

Determine:

```text
What exact parameter does the backend expect?

colors[]
color[]
attributes[color]
```

Do not change the API unnecessarily.

---

# 4. CONFIRM THE COLOR OBJECT SHAPE

Inspect actual frontend color objects.

Likely concept:

```ts
{
  label: "Light Blue",
  value: "Light Blue",
  slug: "light-blue",
  swatch: "#ADD8E6"
}
```

or similar.

Document the real shape.

Do not guess field names.

---

# 5. IDENTIFY ROOT CAUSE

Determine exactly why mobile uses the wrong field.

Possible example:

```text
Desktop:
selectedColors.push(color.value)

Mobile:
selectedColors.push(color.slug)
```

or:

```text
mobile checkbox :value="color.slug"
```

instead of:

```text
:value="color.value"
```

Confirm the real source.

---

# 6. ONE FILTER VALUE CONTRACT

Define one rule:

```text
API filter value
=
actual attribute value
```

Example:

```text
Light Blue
Dark Green
Rose Gold
Midnight Black
```

Do NOT use slugs for API filtering unless backend intentionally filters by slugs.

The audit indicates current backend expects actual values.

---

# 7. SLUG PURPOSE

Keep slug only where appropriate.

Example:

```text
slug
→ DOM key
→ URL-safe identifier
→ internal display key
```

Do NOT use:

```text
slug
→ API attribute value
```

unless architecture explicitly defines that.

---

# 8. SWATCH PURPOSE

Use:

```text
swatch
```

for color preview.

Example:

```text
Light Blue

●
```

Do not use swatch as the selected value.

Correct concept:

```text
display:
swatch + label

submitted:
value
```

---

# 9. DESKTOP / MOBILE CONSISTENCY

For the same option:

```text
Light Blue
```

Desktop must send:

```text
colors[]=Light Blue
```

Mobile must send:

```text
colors[]=Light Blue
```

Not:

```text
colors[]=light-blue
```

---

# 10. MULTI-WORD COLORS

Test explicitly:

```text
Light Blue
Dark Green
Rose Gold
Midnight Blue
Space Gray
```

These are the most likely to fail when slug/value are confused.

---

# 11. SINGLE-WORD COLORS

Also test:

```text
Black
White
Red
Blue
```

These may hide the bug because:

```text
blue
```

can look similar to:

```text
Blue
```

Case and exact values still matter.

---

# 12. CASE SENSITIVITY

Determine whether backend matching is:

```text
case-sensitive
```

or:

```text
case-insensitive
```

Do not depend on accidental DB collation behavior.

Frontend should submit the canonical actual value.

---

# 13. URL QUERY STATE

If color selections are stored in URL:

```text
?colors[]=Light%20Blue
```

that is acceptable.

If existing router serializes differently, preserve expected behavior.

Do not convert:

```text
Light Blue
```

into:

```text
light-blue
```

unless backend accepts slugs.

---

# 14. FILTER CHIPS

If active filters show chips, display:

```text
Light Blue ×
```

not:

```text
light-blue ×
```

Use user-friendly label/value.

---

# 15. MOBILE FILTER DRAWER

Inspect the mobile filter drawer/sheet.

Ensure:

```text
checked state
selected state
query state
clear action
apply action
```

all use the same canonical value.

---

# 16. DESKTOP FILTER PANEL

Do not change working desktop behavior unnecessarily.

Use desktop as reference only if it is already correct.

---

# 17. CLEAR FILTER

When user clears:

```text
Light Blue
```

remove exactly:

```text
"Light Blue"
```

from selected values.

Do not try to remove:

```text
"light-blue"
```

from an array that stores actual values.

---

# 18. MULTIPLE COLORS

Test:

```text
Light Blue
+
Black
```

Expected query contains both actual values.

Example:

```text
colors[]=Light Blue
colors[]=Black
```

Results should match products having those colors according to existing OR/AND semantics.

Do not change filter semantics.

---

# 19. FILTER SEMANTICS

Confirm whether multiple colors mean:

```text
OR
```

or:

```text
AND
```

For product color filters, likely:

```text
Light Blue OR Black
```

but inspect actual CatalogService logic.

Do NOT change this behavior in F07.

---

# 20. PRODUCT OPTION SOURCE

Confirm colors come from actual product/variant attribute values.

Do not maintain a hardcoded frontend list if API already provides color metadata.

If existing API provides:

```text
value
slug
swatch
```

use it consistently.

---

# 21. SWATCH FALLBACK

If a color has no swatch:

```text
value = "Patterned"
swatch = null
```

UI should still show:

```text
Patterned
```

Do not hide the option.

This is a UX improvement only if needed.

---

# 22. INVALID SWATCH

Do not allow invalid swatch strings to break mobile rendering.

Use existing safe styling rules.

Do not make this task into a complete color-system redesign.

---

# 23. API VALIDATION

Backend should validate color filters safely.

If current API accepts strings:

```text
colors.*
→ string
```

keep it.

Do not constrain to slugs if actual values are used.

---

# 24. UNKNOWN COLOR VALUE

Request:

```text
colors[]=NotARealColor
```

should:

```text
return zero results
```

or a clean validation response according to existing design.

It must not cause a 500.

---

# 25. SPECIAL CHARACTERS

Test values like:

```text
Black/White
Blue & Gray
Off-White
```

if actual catalog supports them.

Ensure URL encoding does not corrupt values.

---

# 26. ATTRIBUTE VALUE IDs

Inspect whether backend can more reliably filter using:

```text
attribute_value_id
```

Do NOT migrate to IDs in F07 unless current architecture already uses them.

The goal is fixing current value mismatch, not redesigning filtering.

---

# 27. RESPONSE FILTER METADATA

If API returns available colors, ensure structure is consistent.

Example:

```json
{
  "value": "Light Blue",
  "slug": "light-blue",
  "swatch": "#ADD8E6"
}
```

Frontend should use:

```text
value → filtering
slug → key
swatch → preview
```

---

# 28. SHOP STOREFRONT

Test color filtering on:

```text
main marketplace
```

and:

```text
public shop page
```

if both use the same filter component/service.

Do not fix one while leaving the other inconsistent.

---

# 29. CATEGORY FILTER COMPOSITION

Test:

```text
Category = Clothing
Color = Light Blue
```

Both filters should remain active.

Do not reset category when changing mobile color.

---

# 30. BRAND FILTER COMPOSITION

Test:

```text
Brand = Example Brand
Color = Light Blue
```

Results must use both filters.

---

# 31. PRICE / RATING REGRESSION

F06 was just fixed.

Test:

```text
Rating 4.5+
Price $100–$500
Color Light Blue
```

All filters should compose correctly.

---

# 32. PAGINATION

Changing mobile color should reset:

```text
page = 1
```

but preserve other active filters.

---

# 33. APPLY BUTTON

If mobile uses an "Apply Filters" button:

```text
select Light Blue
→ local mobile state updates
→ Apply
→ canonical query emitted
```

Do not submit slug during the apply step.

---

# 34. CANCEL / CLOSE

If user opens mobile filters, changes selections, then cancels:

```text
current applied filters
```

should remain unchanged according to existing UX.

Do not introduce inconsistent temporary state.

---

# 35. SYNCHRONIZATION AFTER DESKTOP/MOBILE RESIZE

If responsive layout switches between desktop/mobile:

```text
selected color values
```

should stay consistent.

Do not maintain separate incompatible color arrays.

Prefer one shared filter state.

---

# 36. FRONTEND STATE

Avoid:

```text
desktopSelectedColors
mobileSelectedColorSlugs
```

unless absolutely necessary.

Prefer:

```text
selectedColors: string[]
```

containing canonical values.

---

# 37. TYPESCRIPT

Define a proper type.

Example concept:

```ts
type ColorFilterOption = {
  value: string
  slug?: string
  swatch?: string | null
}
```

Adapt to existing code.

Do not use:

```text
any
```

to hide mismatches.

---

# 38. BACKEND QUERY

Verify CatalogService receives:

```text
["Light Blue", "Black"]
```

and correctly matches actual attribute values.

Do not add frontend-only workarounds that still fail server-side.

---

# 39. PERFORMANCE

Color filters should remain server-side.

Do not fetch every product and filter colors in Vue.

Preserve pagination.

---

# 40. AUTOMATED BACKEND TESTS

Add/update tests:

## Test 1

```text
colors[]=Light Blue
```

returns matching product.

## Test 2

```text
colors[]=light-blue
```

behaves according to actual API contract.

If slug is not supported:

```text
should not silently be treated as Light Blue
```

unless backend intentionally normalizes it.

## Test 3

```text
Light Blue + Black
```

returns expected combined results.

## Test 4

Multi-word value works.

## Test 5

Unknown value does not crash.

---

# 41. FRONTEND TESTS

Add tests for:

```text
mobile uses c.value
not c.slug

mobile displays c.swatch

desktop uses same canonical value

clear removes correct value

multiple color selection works

URL query contains actual values
```

---

# 42. BROWSER / E2E REGRESSION

Create a test reproducing F07.

Fixture:

```text
Product A:
Light Blue

Product B:
Black
```

Mobile viewport:

```text
open filters
select Light Blue
apply
```

Intercept/inspect request.

Expected:

```text
colors[]=Light Blue
```

NOT:

```text
colors[]=light-blue
```

Verify:

```text
Product A visible
Product B hidden
```

Then run same filter in desktop viewport.

Results must match.

---

# 43. MOBILE SWATCH TEST

Verify mobile displays the API-provided swatch.

Example:

```text
Light Blue
● #ADD8E6
```

Do not use a hardcoded generic blue if actual swatch exists.

---

# 44. F01 REGRESSION

Verify coupon behavior remains unaffected.

---

# 45. F02 REGRESSION

Verify cart behavior remains unaffected.

---

# 46. F03 REGRESSION

Verify checkout totals remain unaffected.

---

# 47. F04 REGRESSION

Verify COD lifecycle remains unaffected.

---

# 48. F05 REGRESSION

Verify chosen guest/account checkout policy remains unaffected.

---

# 49. F06 REGRESSION

Critical:

```text
rating filter works
price range works
```

with color selected.

Example:

```text
Color: Light Blue
Rating: 4.5+
Price: $100–$500
```

must produce one valid combined query.

---

# 50. TYPE CHECK

Run existing TypeScript type check.

No:

```text
any
@ts-ignore
```

unless clearly justified.

---

# 51. PRODUCTION BUILD

Run frontend production build.

No broken imports/runtime compile issues.

---

# 52. DOCKER ONLY

Use project Docker containers for:

```text
PHP
Composer
Node
tests
build
```

Do not wipe development data.

---

# 53. DO NOT START F08

Only solve F07.

Do not begin:

```text
Buy Now async/cart mutation fixes
```

That is F08.

---

# 54. DEFINITION OF DONE

Do not mark F07 complete until:

```text
[ ] Mobile submits actual color value

[ ] Mobile does not submit slug accidentally

[ ] Desktop and mobile queries match

[ ] Multi-word colors work

[ ] Multiple colors work

[ ] Swatch displays correctly

[ ] Clear/remove uses canonical value

[ ] URL state is correct

[ ] Category/brand filters compose

[ ] F06 rating/price filters compose

[ ] Pagination resets correctly

[ ] Main marketplace works

[ ] Public shop page works if applicable

[ ] Backend tests pass

[ ] Frontend tests pass

[ ] Mobile browser regression passes

[ ] Desktop comparison passes

[ ] Type check passes

[ ] Production build passes

[ ] F01–F06 regressions pass
```

---

# 55. FINAL REPORT

At completion report exactly:

```text
F07 — MOBILE COLOR FILTERS

Status:
PASS / NEEDS WORK

Root Cause:

Old Mobile Value:

Correct Canonical Value:

Desktop Behavior:

Mobile Behavior After Fix:

Color Object Shape:

Filter API Contract:

Multi-Word Colors:
PASS / FAIL

Multiple Colors:
PASS / FAIL

Swatch Rendering:
PASS / FAIL

Marketplace Filter:
PASS / FAIL

Shop Storefront Filter:
PASS / FAIL / N/A

Rating + Price + Color Composition:
PASS / FAIL

Files Changed:

Schema Changes:
None / List

Tests Added:

Backend Tests:
Passed / Failed

Frontend Tests:
Passed / Failed

Browser Tests:
Passed / Failed

Type Check:
PASS / FAIL

Production Build:
PASS / FAIL

F01 Regression:
PASS / FAIL

F02 Regression:
PASS / FAIL

F03 Regression:
PASS / FAIL

F04 Regression:
PASS / FAIL

F05 Regression:
PASS / FAIL

F06 Regression:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F08 — Async Cart Mutations & Buy Now Errors
```

Also show a before/after example:

```text
BEFORE

Color:
Light Blue

Mobile sent:
light-blue

Backend expected:
Light Blue

Result:
Wrong / empty filtering


AFTER

Color:
Light Blue

Desktop sends:
Light Blue

Mobile sends:
Light Blue

Swatch:
Uses API-provided color

Result:
Same products on desktop and mobile
```

Stop after F07.

Do NOT automatically begin F08.

I will review the result first.
