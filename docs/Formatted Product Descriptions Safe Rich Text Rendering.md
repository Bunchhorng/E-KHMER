# E-KHMER Improvement #09 — Formatted Product Descriptions & Safe Rich Text Rendering

Act as a senior **Laravel + Vue.js + TypeScript Marketplace Engineer, Frontend Security Engineer, Content-Sanitization Engineer, UX Engineer, and QA Engineer**.

I have an existing multi-vendor marketplace project called **E-KHMER**.

Previous fixes:

```text
F01 — Shop Coupon Ownership
F02 — Cart Recovery After Variant Deletion
F03 — Discount Cap & Checkout Total Consistency
F04 — COD Collection & Paid-State Lifecycle
F05 — Guest Checkout Policy & Web Flow
F06 — Rating & Price Filters
F07 — Mobile Color Filters
F08 — Async Cart Mutations & Buy Now Errors
```

Do NOT regress those fixes.

Now work ONLY on:

# F09 — Formatted Product Descriptions

---

# 1. CURRENT DEFECT

The seller product editor supports formatted/rich-text descriptions.

Example seller input:

```html
<p>
  <strong>Aurora Wireless Headphones</strong>
</p>

<p>
  Premium wireless headphones with excellent sound quality.
</p>
```

But the customer-facing product page currently displays something similar to:

```text
<p><strong>Aurora Wireless Headphones</strong></p>
<p>Premium wireless headphones...</p>
```

instead of rendering the approved formatting.

This produces poor storefront UX.

---

# 2. SECURITY WARNING

Do NOT solve this by blindly changing:

```vue
{{ product.description }}
```

to:

```vue
<div v-html="product.description"></div>
```

without sanitization.

That can create an XSS vulnerability.

Example malicious seller content:

```html
<img src="x" onerror="alert('XSS')">
```

or:

```html
<script>
  // malicious JavaScript
</script>
```

must NEVER execute on the customer storefront.

---

# 3. PRIMARY GOAL

The correct architecture should be:

```text
Seller Rich Text
      ↓
Allowed Formatting Policy
      ↓
Sanitization
      ↓
Stored / Returned Safe Content
      ↓
Vue Safe Rendering
      ↓
Formatted Product Description
```

The final customer experience should support useful formatting while dangerous markup remains inert or removed.

---

# 4. FIRST INSPECT THE ACTUAL IMPLEMENTATION

Inspect before changing anything.

Focus especially on:

```text
frontend/src/components/RichTextEditor.vue
frontend/src/views/ProductDetailView.vue
```

Also inspect:

```text
frontend/src/views/seller/SellerProductFormView.vue
frontend/src/views/admin/AdminProductsView.vue

frontend product preview components
frontend API/product types
frontend package.json

backend/app/Http/Controllers/Api/Seller/SellerProductController.php
backend/app/Http/Controllers/Api/Admin/AdminProductController.php
backend/app/Services/CatalogService.php
backend/app/Http/Resources/ProductDetailResource.php

Product model
product migrations
seller product request validation
admin product request validation

existing product tests
browser tests
```

Search globally for:

```text
description
v-html
innerHTML
RichTextEditor
sanitize
sanitizer
DOMPurify
HTMLPurifier
strip_tags
```

Do not assume sanitization already exists.

---

# 5. CONFIRM CURRENT DATA FORMAT

Determine exactly what is stored.

Possible:

```text
HTML
Markdown
plain text
JSON editor document
```

Example:

```text
products.description =
"<p><strong>Premium Headphones</strong></p>"
```

Document the actual format.

Do NOT migrate formats unnecessarily.

---

# 6. DETERMINE THE SOURCE OF THE BUG

Confirm whether:

```text
RichTextEditor outputs HTML
```

while:

```vue
{{ product.description }}
```

escapes HTML.

If yes, document that as the root cause.

Do not guess.

---

# 7. DEFINE AN ALLOWED HTML POLICY

Product descriptions do not need arbitrary HTML.

Allow only a restricted formatting subset.

Recommended baseline:

```text
p
br

strong
b

em
i

u

ul
ol
li

h2
h3
h4

blockquote

a
```

Optional if genuinely used by the current editor:

```text
code
pre
hr
```

Do NOT automatically allow:

```text
script
iframe
object
embed
form
input
button
style
svg
canvas
video
audio
```

unless a deliberate security review approves them.

---

# 8. ALLOWED ATTRIBUTES

Keep attributes minimal.

Example:

For:

```html
<a>
```

possibly allow:

```text
href
title
target
rel
```

Do not allow:

```text
onclick
onerror
onload
style
srcdoc
```

or arbitrary event handlers.

---

# 9. LINK SECURITY

If seller descriptions support links:

```html
<a href="...">
```

allow only safe protocols.

Recommended:

```text
https:
http:
mailto:
```

if required.

Reject dangerous protocols such as:

```text
javascript:
data:
vbscript:
```

Example malicious input:

```html
<a href="javascript:alert(1)">
  Click
</a>
```

must not execute.

---

# 10. TARGET="_BLANK"

If links can open new tabs:

```html
target="_blank"
```

ensure safe relationship attributes such as:

```text
rel="noopener noreferrer"
```

according to project conventions.

---

# 11. INLINE STYLES

Recommended Version 1:

```text
Do NOT allow arbitrary style=""
```

Seller HTML like:

```html
<p style="position:fixed;top:0;...">
```

can damage the storefront UX.

Use application CSS classes instead.

---

# 12. CSS CLASSES

Do not allow arbitrary seller-defined:

```html
class="..."
```

unless deliberately required.

The storefront should control presentation.

---

# 13. SANITIZATION STRATEGY

Inspect current dependencies first.

Choose one clear strategy.

Preferred defense:

```text
Server-side sanitization
+
Safe frontend rendering
```

If an established HTML sanitizer already exists, reuse it.

Do NOT write a sanitizer with regular expressions.

Example of what NOT to do:

```php
preg_replace('/<script.*?>.*?<\/script>/', '', $html);
```

HTML sanitization is more complicated than regex removal.

---

# 14. BACKEND SANITIZATION

Prefer sanitizing seller-controlled rich text at a trusted boundary.

Possible flow:

```text
Seller Request
    ↓
Validation
    ↓
HTML Sanitizer
    ↓
Allowed HTML
    ↓
Database
```

This prevents unsafe content from being reused elsewhere.

However, inspect existing stored data before changing persistence behavior.

---

# 15. OUTPUT SANITIZATION

Even if input is sanitized, consider defense-in-depth at output where appropriate.

Do not repeatedly mutate data differently in multiple endpoints.

Use one shared policy.

---

# 16. EXISTING UNSAFE DATA

Important:

The database may already contain old descriptions.

Example:

```text
Existing Product A
description contains unsafe HTML
```

If sanitization is applied only to future saves:

```text
old unsafe content
```

could remain dangerous.

Determine a safe handling strategy.

Possible:

```text
sanitize on render/API response
+
sanitize on new save/update
```

This can protect old and new records.

Document the approach.

---

# 17. DO NOT SILENTLY DESTROY LEGITIMATE CONTENT

Example existing description:

```html
<p>
  <strong>Features</strong>
</p>

<ul>
  <li>Bluetooth 5.3</li>
  <li>40-hour battery</li>
</ul>
```

should remain formatted.

Sanitization should remove dangerous content, not all markup.

---

# 18. PRODUCT DETAIL RENDERING

After content is proven sanitized, render it as HTML.

Concept:

```vue
<div
  class="product-description"
  v-html="safeDescription"
/>
```

Only use `v-html` with trusted sanitized output.

Never bind raw seller content directly.

---

# 19. CENTRALIZE SANITIZATION

Avoid:

```text
sanitize in ProductDetailView
sanitize differently in preview
sanitize differently in admin
sanitize differently in API
```

Use one clear reusable policy/helper/service.

Backend concept:

```text
RichTextSanitizer
```

Frontend concept if needed:

```text
sanitizeHtml()
```

But prefer not to duplicate policies unless defense-in-depth requires it.

---

# 20. SELLER EDITOR

Preserve current seller editing experience.

Seller should still be able to format:

```text
Bold
Italic
Lists
Headings
Links
```

according to actual editor capabilities.

Do not remove working toolbar functions unless they produce unsupported markup.

---

# 21. EDITOR / SANITIZER ALIGNMENT

Critical rule:

```text
Editor allowed formatting
=
Sanitizer allowed formatting
```

Bad UX:

```text
Seller clicks Heading
Editor produces <h2>
Sanitizer deletes <h2>
```

Inspect actual RichTextEditor output.

Make allowed policy match intentionally supported editor features.

---

# 22. PRODUCT PREVIEW

If seller has product preview:

```text
Preview
```

it should render the same safe formatting customers will see.

Do not let seller preview show formatting that storefront later strips.

---

# 23. ADMIN PRODUCT VIEW

Admin product detail/edit screens should also use safe rendering.

Do not introduce unsafe `v-html` into admin screens.

Privileged users are still vulnerable to stored XSS.

---

# 24. PRODUCT CARDS

Product cards normally should NOT render full rich HTML.

If cards use descriptions:

```text
strip formatting
→ plain-text excerpt
```

Example:

HTML:

```html
<p><strong>Premium</strong> headphones</p>
```

Card:

```text
Premium headphones
```

Do not render large rich-text blocks inside cards.

---

# 25. META DESCRIPTION / SEO

If product description is reused for SEO:

```text
meta description
```

convert it to safe plain text.

Do not insert raw HTML into:

```html
<meta content="">
```

---

# 26. SEARCH INDEXING

If description is searchable:

```text
search should use meaningful text
```

not necessarily raw HTML tags.

Do not redesign search in F09 unless current description indexing is visibly broken.

---

# 27. EMPTY DESCRIPTION

Handle:

```text
null
""
"<p></p>"
```

cleanly.

Do not show:

```text
undefined
null
<p></p>
```

to customers.

---

# 28. WHITESPACE

Normalize presentation so rich text does not create huge accidental gaps.

Use storefront CSS.

Do not destructively remove legitimate paragraph spacing from stored content.

---

# 29. RESPONSIVE DESIGN

Description content should work on:

```text
desktop
tablet
mobile
```

Long text must wrap properly.

No horizontal overflow from seller content.

---

# 30. TYPOGRAPHY

Use E-KHMER storefront typography.

Suggested content styling:

```text
clear paragraph spacing
readable line height
strong headings
proper list indentation
consistent link styling
```

Avoid seller-controlled font families or arbitrary colors unless deliberately supported.

---

# 31. DARK MODE

If storefront supports dark mode, rendered rich text must remain readable.

Do not store:

```text
color: black
```

from editor markup if that makes text invisible in dark mode.

Prefer application theme styling.

---

# 32. IMAGES INSIDE DESCRIPTION

Inspect whether RichTextEditor allows embedded images.

If not:

```text
do not add them in F09.
```

Product images already have a dedicated gallery.

If embedded images already exist, evaluate:

```text
safe src URLs
upload ownership
dimensions
responsive styling
```

but do not expand scope unnecessarily.

---

# 33. XSS TEST — SCRIPT

Input:

```html
<p>Safe text</p>
<script>alert('xss')</script>
```

Expected:

```text
Safe text renders.
Script removed/inert.
No JavaScript executes.
```

---

# 34. XSS TEST — EVENT HANDLER

Input:

```html
<img src="x" onerror="alert('xss')">
```

Expected:

```text
onerror removed
```

or element removed according to policy.

No execution.

---

# 35. XSS TEST — JAVASCRIPT URL

Input:

```html
<a href="javascript:alert('xss')">
  Click me
</a>
```

Expected:

```text
dangerous href removed/rejected
```

No execution.

---

# 36. XSS TEST — SVG

Input:

```html
<svg onload="alert('xss')"></svg>
```

Expected:

```text
removed/inert
```

---

# 37. XSS TEST — IFRAME

Input:

```html
<iframe src="https://evil.example"></iframe>
```

Recommended:

```text
removed
```

unless iframe support is explicitly approved.

---

# 38. NORMAL FORMATTING TEST

Input:

```html
<h3>Features</h3>

<ul>
  <li>Bluetooth 5.3</li>
  <li>40-hour battery</li>
</ul>

<p>
  Includes a <strong>2-year warranty</strong>.
</p>
```

Expected:

```text
Heading formatted
Bullets formatted
Bold formatted
```

No literal tags displayed.

---

# 39. LINK TEST

Input:

```html
<p>
  Visit
  <a href="https://example.com">
    manufacturer website
  </a>
</p>
```

If links are allowed:

```text
safe HTTPS link works
```

Unsafe protocols do not.

---

# 40. BACKEND VALIDATION

Description should remain subject to:

```text
nullable/string
maximum length
```

or existing limits.

Do not allow unlimited HTML payloads.

Inspect current DB field capacity.

---

# 41. DATABASE TYPE

Do not change the database column merely because HTML is stored.

If current:

```text
TEXT
LONGTEXT
```

is sufficient, leave it alone.

Only add migration if there is a real capacity/schema requirement.

---

# 42. API CONTRACT

Product API should return a clearly defined description format.

Example:

```json
{
  "description": "<p><strong>Premium</strong> headphones</p>"
}
```

If this field is sanitized HTML, document it.

Do not create ambiguous:

```text
sometimes HTML
sometimes plain text
```

responses.

---

# 43. OPTIONAL DESCRIPTION TEXT FIELD

Do NOT add:

```text
description_html
description_text
description_raw
description_safe
```

all at once unless there is a real architecture need.

Keep contract simple.

---

# 44. SERVER-SIDE ESCAPING

Understand distinction:

```text
Escaping
≠
Sanitization
```

Escaping causes literal HTML display.

Sanitization permits approved HTML safely.

The bug exists because rich text needs safe rendering rather than plain interpolation.

---

# 45. TRUST BOUNDARY

Treat:

```text
Seller
```

as untrusted input.

Even though sellers are authenticated:

```text
seller content must still be sanitized.
```

A compromised seller account must not become stored XSS against customers/admins.

---

# 46. SHOP ISOLATION

F09 should not change product ownership/authorization.

Seller can edit descriptions only for products they are authorized to manage.

Do not weaken existing shop policies.

---

# 47. ADMIN OVERRIDE

If Super Admin can edit any product:

same sanitation rules apply.

Do not create a "trusted admin HTML" bypass unless intentionally required.

---

# 48. BACKEND TESTS

Add/update tests for at least:

## Test 1 — Normal paragraph

```html
<p>Hello World</p>
```

Preserved safely.

---

## Test 2 — Bold

```html
<strong>Premium</strong>
```

Preserved.

---

## Test 3 — Lists

```html
<ul>
  <li>A</li>
  <li>B</li>
</ul>
```

Preserved.

---

## Test 4 — Script

```html
<script>alert(1)</script>
```

Removed/inert.

---

## Test 5 — Event handler

```html
<img onerror="alert(1)">
```

Dangerous handler removed.

---

## Test 6 — JavaScript URL

```html
<a href="javascript:alert(1)">
```

Dangerous URL removed/rejected.

---

## Test 7 — Seller update

Saved description follows sanitation policy.

---

## Test 8 — Admin update

Same policy applies.

---

## Test 9 — Old unsafe content

If defense-in-depth output sanitization is implemented:

legacy unsafe markup is harmless.

---

# 49. FRONTEND TESTS

Test ProductDetailView.

Input:

```html
<p><strong>Premium Headphones</strong></p>
```

Expected DOM:

```text
p element exists
strong element exists
```

Do NOT expect:

```text
"<p><strong>..."
```

as visible text.

---

# 50. FRONTEND XSS TEST

Mount/render malicious description:

```html
<img src="x" onerror="window.__xss = true">
```

Verify:

```text
window.__xss
```

does not become true.

Use test infrastructure safely.

---

# 51. BROWSER / E2E TEST — NORMAL DESCRIPTION

Open a product containing:

```html
<h3>Features</h3>
<ul>
  <li>Bluetooth</li>
</ul>
```

Verify:

```text
Features rendered as heading

Bluetooth rendered as list item

raw <h3> text not visible
```

---

# 52. BROWSER / E2E SECURITY TEST

Use controlled malicious fixture.

Verify:

```text
script does not execute

event handlers do not execute

page remains usable
```

Do not put actual malicious payloads into production/development persistent data unless isolated test fixtures are used.

---

# 53. SELLER EDIT → STOREFRONT TEST

Scenario:

```text
Seller creates product

Description:
Heading
Paragraph
Bold
List

Save
↓
Customer opens product
```

Expected:

```text
same intended formatting
```

with dangerous markup removed.

---

# 54. EDIT EXISTING PRODUCT

Open existing HTML description in editor.

Ensure:

```text
existing formatting loads properly
```

and saving does not convert it into:

```text
escaped HTML entities
```

such as:

```text
&lt;p&gt;
```

unless that is the editor's expected format.

---

# 55. DOUBLE-ESCAPING

Prevent:

```text
<p>Hello</p>
```

becoming:

```text
&lt;p&gt;Hello&lt;/p&gt;
```

and then:

```text
&amp;lt;p&amp;gt;
```

after multiple edits.

Test repeated:

```text
edit
save
edit
save
```

---

# 56. HTML ENTITY TEST

Description:

```text
Tom &amp; Jerry
```

should display:

```text
Tom & Jerry
```

correctly.

Avoid double encoding.

---

# 57. UNICODE / KHMER

Test Khmer content.

Example:

```html
<p>
  <strong>ផលិតផលគុណភាពខ្ពស់</strong>
</p>
```

Expected:

```text
Khmer renders correctly.
```

Do not break UTF-8.

---

# 58. MIXED LANGUAGE

Test:

```text
English
Khmer
Numbers
Symbols
```

Rich text should handle all safely.

---

# 59. COPY / PASTE FROM WORD OR WEB

Rich-text editors may receive messy HTML.

Example:

```text
style attributes
span tags
font tags
```

Sanitizer should normalize/remove unsupported formatting without breaking the page.

Do not attempt perfect Microsoft Word HTML preservation.

---

# 60. PRODUCT DESCRIPTION CSS

Create/reuse a dedicated style scope such as:

```text
product-description
```

Style allowed tags consistently.

Examples:

```text
p
h2/h3/h4
ul/ol
blockquote
a
```

Do not allow rich content to break the surrounding layout.

---

# 61. LONG URL / LONG WORD

Test:

```text
verylongstringwithoutspaces...
```

Use:

```text
overflow-wrap
word-break
```

where necessary.

No horizontal page overflow.

---

# 62. RESPONSIVE LISTS

Ensure:

```text
ul
ol
```

have proper indentation on mobile.

Do not let bullets disappear because of global CSS resets/Tailwind preflight.

---

# 63. DARK MODE REGRESSION

If E-KHMER storefront supports dark mode:

verify:

```text
paragraph
heading
link
list
blockquote
```

remain readable.

---

# 64. PERFORMANCE

Do not sanitize an enormous description repeatedly on every reactive render unnecessarily.

Use:

```text
computed
API sanitization
cached result
```

as appropriate.

Keep implementation simple.

---

# 65. DEPENDENCY REVIEW

If adding a sanitizer dependency:

Check:

```text
actively maintained
appropriate for Vue/browser or Laravel
minimal required scope
current security advisories
```

Do not add a large editor framework just for sanitization.

Also remember the project already has outstanding dependency-security work under F17.

---

# 66. CONTENT SECURITY POLICY

Do not rely on CSP alone to fix unsafe HTML.

CSP is defense-in-depth.

The rich text itself must still be sanitized.

---

# 67. NO UNSAFE BYPASS

Do not use:

```text
trustHtml()
markSafe()
raw()
```

on seller input merely to make formatting work.

Safe rendering must be earned through sanitization.

---

# 68. F01 REGRESSION

Coupon behavior unaffected.

---

# 69. F02 REGRESSION

Cart recovery unaffected.

---

# 70. F03 REGRESSION

Checkout totals unaffected.

---

# 71. F04 REGRESSION

COD lifecycle unaffected.

---

# 72. F05 REGRESSION

Guest/account checkout policy unaffected.

---

# 73. F06 REGRESSION

Rating and price filters unaffected.

---

# 74. F07 REGRESSION

Color filtering unaffected.

---

# 75. F08 REGRESSION

Critical recent regression:

```text
Buy Now error handling
Add to Cart async handling
```

must remain correct.

---

# 76. TYPE CHECK

Run existing frontend TypeScript checks.

No:

```text
any
@ts-ignore
```

just to bypass sanitized-content typing.

---

# 77. PRODUCTION BUILD

Run frontend production build.

Must pass.

---

# 78. DOCKER ONLY

Use project Docker containers for:

```text
PHP
Composer
Node
tests
build
```

according to current project conventions.

Do not wipe important development data.

---

# 79. DO NOT START F10

Only solve F09.

Do not begin review aggregate maintenance.

That is:

```text
F10 — Review Aggregate Maintenance
```

---

# 80. DEFINITION OF DONE

Do not mark F09 complete until:

```text
[ ] Rich text no longer displays literal HTML tags

[ ] Approved formatting renders correctly

[ ] Seller input is treated as untrusted

[ ] Script tags cannot execute

[ ] Event-handler attributes cannot execute

[ ] javascript: links cannot execute

[ ] iframe/embed/object content follows safe policy

[ ] Editor formatting matches sanitizer policy

[ ] Seller preview matches storefront behavior

[ ] Admin view remains safe

[ ] Existing descriptions are protected

[ ] Product cards use safe plain-text excerpts where applicable

[ ] Empty descriptions render cleanly

[ ] HTML is not double escaped

[ ] Repeated edit/save does not corrupt HTML

[ ] Khmer text renders correctly

[ ] Mobile layout works

[ ] Dark mode works if supported

[ ] Backend tests pass

[ ] Frontend tests pass

[ ] XSS security tests pass

[ ] Browser regression passes

[ ] Type check passes

[ ] Production build passes

[ ] F01–F08 regressions pass
```

---

# 81. FINAL REPORT

At completion report exactly:

```text
F09 — FORMATTED PRODUCT DESCRIPTIONS

Status:
PASS / NEEDS WORK

Root Cause:

Stored Description Format:

Old Storefront Behavior:

New Storefront Behavior:

Sanitization Location:
BACKEND / FRONTEND / BOTH

Sanitizer Used:

Allowed Tags:

Allowed Attributes:

Dangerous Tags Removed:

Dangerous Attributes Removed:

Safe URL Protocols:

Legacy Stored Content Protection:

RichTextEditor Compatibility:
PASS / FAIL

Product Detail Rendering:
PASS / FAIL

Seller Preview:
PASS / FAIL / N/A

Admin Rendering:
PASS / FAIL / N/A

Khmer Content:
PASS / FAIL

Mobile:
PASS / FAIL

Dark Mode:
PASS / FAIL / N/A

XSS Tests:
PASS / FAIL

Files Changed:

Dependencies Added:
None / List

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

F07 Regression:
PASS / FAIL

F08 Regression:
PASS / FAIL

Remaining Risks:

Related Follow-Up:
F10 — Review Aggregate Maintenance
```

Also provide a before/after example:

```text
BEFORE

Stored:

<p>
  <strong>Premium Headphones</strong>
</p>

Customer saw:

<p><strong>Premium Headphones</strong></p>


AFTER

Customer sees:

Premium Headphones

with the intended bold/paragraph formatting.


Malicious seller input:

<script>alert('xss')</script>

Result:

Removed / inert
No JavaScript execution
```

Stop after F09.

Do NOT automatically begin F10.

I will review the result first.