# F09 — FORMATTED PRODUCT DESCRIPTIONS

Verified: 2026-11-02. Scope stops after F09.

Status:
PASS

Root Cause:
`RichTextEditor` emits HTML from a contenteditable element, while `ProductDetailView` interpolated `product.description` inside a paragraph. Vue correctly escaped those tags, causing literal markup to appear. The generated Key Features list also split raw HTML as text. The editor's custom DOM filter existed only in the browser; product persistence and API output had no trusted sanitizer.

Stored Description Format:
Nullable HTML fragment in the existing `products.description` TEXT column. Historical plain text remains supported as a text-only HTML fragment. A read-only development inspection sampled all 22 current products: 20 plain-text and two empty descriptions, maximum 82 bytes. No development rows were rewritten and no malicious fixture was saved there.

Old Storefront Behavior:
Literal `<p><strong>…</strong></p>` strings appeared in the description and could leak into generated feature text.

New Storefront Behavior:
The reusable `ProductDescription` component renders only sanitized HTML. Approved paragraphs, headings, emphasis, lists, quotes and links render with application typography. Feature text uses a safe text projection. Null/empty/visually empty descriptions produce no blank rich block or empty Key Features section. HTML entities and Khmer render correctly. Long words/URLs wrap without horizontal page overflow.

Sanitization Location:
BOTH

Sanitizer Used:
Backend: Symfony HTML Sanitizer 7.4.22 through `ProductDescriptionSanitizer`, applied to Product model assignments/storage and reads, including public/seller/admin resource output. Frontend: DOMPurify 3.4.17 through one reusable helper shared by editor, preview, storefront and text projection. The frontend allowlist mirrors the backend as defense in depth; there are no per-view sanitizer variants or raw/trusted-admin bypasses. HTML is never compiled as a Vue template.

Allowed Tags:
`p`, `div`, `span`, `br`, `strong`, `b`, `em`, `i`, `u`, `ul`, `ol`, `li`, `h1`, `h2`, `h3`, `h4`, `blockquote`, `a`. Div/span and h1 preserve existing editor structure/legacy capabilities; the current toolbar still offers paragraph/h3. No new code blocks, tables, images, media or editor framework were added.

Allowed Attributes:
Only anchor `href` and `title` from seller input. The sanitizer forces `rel="noopener noreferrer"`. All targets are stripped, so description links use the current tab. Other allowed tags retain no seller attributes.

Dangerous Tags Removed:
Script/style, iframe/object/embed, SVG/MathML, forms/inputs/buttons/textarea/select/option, canvas/video/audio, template/noscript and images. Other unsupported formatting is not promoted to trusted HTML. Product images remain in the dedicated gallery.

Dangerous Attributes Removed:
Event handlers, inline style, arbitrary class/id/name, src/srcdoc, target, data/ARIA attributes and other unapproved attributes. Input rel is replaced with a fixed safe value.

Safe URL Protocols:
HTTP, HTTPS and mailto, plus safe relative/fragment links. JavaScript, data, VBScript and FTP destinations are removed. Entity/control-character obfuscations are covered by tests. Safe attribute serialization may encode characters such as `@` or `=` as entities; browser destinations remain the original decoded URLs.

Legacy Stored Content Protection:
Every Product description read uses the same backend policy; public, seller and super-admin APIs return safe HTML even for a row inserted before F09. Reads do not rewrite that row. Model-based writes sanitize before persistence. Frontend sanitization also protects legacy/unsanitized fixture responses. No bulk cleanup, format migration, indiscriminate entity decoding or schema migration was performed.

RichTextEditor Compatibility:
PASS — existing heading, bold, italic, underline, lists, quote and undo/redo controls remain. Existing clipboard paste stays plain text. Supported output and both allowlists agree. Safe existing HTML loads as formatting; repeated edit/save does not accumulate escaping. The editor now uses DOMPurify rather than its custom sanitizer.

Product Detail Rendering:
PASS — approved rich DOM, safe text features and empty handling on desktop/mobile.

Seller Preview:
PASS — seller form's live full-description preview uses the same component and CSS as the storefront. Creation and repeated edit/save flows verify preview → submission → storefront. The seller list's existing card/short-description preview stays plain text; no extra listing payload or API request was introduced.

Admin Rendering:
PASS — super-admin editing loads legacy rich text through the shared safe editor, with no trusted-admin exception. Actual admin create/update APIs use the same backend storage policy. Full rich-description rendering was not added to product listing cards.

Khmer Content:
PASS — Khmer bold content, mixed English/Khmer/numbers/symbols, entities and repeated saves.

Mobile:
PASS — Chrome desktop and Pixel 7 emulation; long-content checks also use widths 1440, 768 and 390.

Dark Mode:
PASS — theme-owned paragraphs/headings/lists/blockquote/link styles; seller font/color declarations are stripped.

XSS Tests:
PASS — scripts, event handlers, dangerous/obfuscated link schemes, SVG/MathML, iframe/object/embed, forms/style/template and malformed nesting. Browser fixtures assert no description flag execution, unexpected dialog or uncaught page error and no requests to the controlled hostile embed host. API/security data is isolated in SQLite tests or intercepted browser responses, not persisted in development data.

Files Changed:

| File | Change |
| --- | --- |
| `backend/app/Services/ProductDescriptionSanitizer.php` | New reusable allowlist, URL policy and storage-capacity protection. |
| `backend/app/Models/Product.php` | Apply shared policy on description assignment and reads. |
| `backend/app/Providers/AppServiceProvider.php` | Reuse sanitizer configuration through a singleton. |
| `backend/app/Http/Requests/AdminProductRequest.php` | Bound UTF-8 bytes alongside existing description validation; seller request inherits it. |
| `backend/app/Http/Resources/ProductDetailResource.php` | Document the shared safe-output contract. |
| `backend/composer.json`, `backend/composer.lock` | Add reviewed Symfony sanitizer version/constraint. |
| `backend/tests/Unit/ProductDescriptionSanitizerTest.php` | Formatting, security, links, entities/Unicode and idempotence tests. |
| `backend/tests/Feature/Api/ProductDescriptionTest.php` | Seller/admin persistence, legacy reads, repeated saves, limits and ownership tests. |
| `frontend/src/utils/productDescription.ts` | One DOMPurify policy and prepared HTML/plain-text projection. |
| `frontend/src/components/ProductDescription.vue` | Computed safe rich renderer with empty handling. |
| `frontend/src/styles/productDescription.css` | Shared storefront/editor/preview typography, lists, wrapping and dark links. |
| `frontend/src/components/RichTextEditor.vue` | Replace custom filter with shared DOMPurify helper, preserving toolbar/paste. |
| `frontend/src/views/ProductDetailView.vue` | Safe rich component, text-only features and long-content wrapping. |
| `frontend/src/views/seller/SellerProductFormView.vue` | Matching full-description preview and cached checklist text. |
| `frontend/src/api/catalog.ts` | Document description as an allowlisted HTML fragment. |
| `frontend/package.json`, `frontend/package-lock.json` | Add exact reviewed DOMPurify version and its type dependency. |
| `frontend/tests/e2e/product-descriptions.spec.ts` | Rendered DOM, XSS, editor/preview, round-trip and responsive/theme coverage. |
| `docs/API-REFERENCE.md` | HTML/attribute/URL and validation contract. |
| `docs/COMMERCE-COMPLETION.md` | F09 operation, policy and boundaries. |
| `docs/F09-PRODUCT-DESCRIPTIONS-REPORT.md` | This report and verification details. |

22 implementation/test/dependency/documentation files. The supplied `Formatted Product Descriptions Safe Rich Text Rendering.md` was left unchanged.

Dependencies Added:
`symfony/html-sanitizer` 7.4.22 (`^7.4.22`) and `dompurify` 3.4.17 (exact), plus transitive `@types/trusted-types` 2.0.7. No unrelated installed package was upgraded. Symfony's HTML5 parser/URI dependencies were already present.

The dependency review used the [Symfony 7.4 sanitizer documentation](https://symfony.com/doc/7.4/html_sanitizer.html), [Symfony 7.4 support information](https://symfony.com/releases/7.4), and [DOMPurify releases](https://github.com/cure53/DOMPurify/releases) / [security policy](https://github.com/cure53/DOMPurify/security). Docker Composer/npm audits reported no advisory for the selected sanitizers. Existing unrelated findings remain: symfony/mime and symfony/yaml in Composer, and eight npm package entries involving existing Vue I18n, Vite, follow-redirects and selector-parser dependencies. Those remain F17; no forced audit fix was applied.

Schema Changes:
None. TEXT capacity is sufficient with validation. Requests retain nullable/string/max:60000 and now also enforce 60,000 UTF-8 bytes. Sanitized output above 65,535 bytes returns 422 without truncation; this also covers entity expansion. No new description fields, search index changes, product-ownership changes or development data reset.

Tests Added:
44 backend cases (35 unit + nine API cases, 186 assertions): all supported formatting; hostile tag/attribute/URL inputs; safe links; UTF-8/entities/idempotence; seller/admin writes; legacy public/seller/admin reads without rewriting; repeated saves; invalid/oversized UTF-8 or expanded output; and shop isolation. Nineteen new browser scenarios on both desktop and mobile (38 cases): formatted DOM, six hostile fixture classes, links, four empty states, mixed/legacy text, width checks, dark mode, seller preview/round trips, creation, toolbar compatibility and safe admin editing. The existing seller-product-form suite adds 12 editor/layout regressions.

Backend Tests:
Passed — 441 relevant F01–F09/catalog/cart/checkout/payment/auth and seller/admin product tests, 2,998 assertions. Tests use isolated SQLite and mocked external integrations.

Frontend Tests:
Passed — final combined run: 210 cases (105 desktop + 105 mobile), retries disabled, comprising 38 new F09 cases, 12 existing editor/layout cases and all 160 F01–F08 commerce cases. The focused 50-case description/editor run also passed.

Browser Tests:
Passed — 210/210 in the final run, no skips or retries, 4.9 minutes. Browser APIs are mocked; separate Laravel tests exercise actual request validation, persistence and resource responses. The first development run exposed first-load Vite dependency optimization/reload and a toolbar test that attempted to add bold to an already-bold heading; corrected tests and the subsequent focused/final runs passed with retries disabled.

Type Check:
PASS — Docker vue-tsc -b and the final production build.

Production Build:
PASS — Docker npm run build (vue-tsc -b && vite build). All seven changed PHP files passed Pint.

F01 Regression:
PASS — coupon ownership/isolation backend tests and shop-coupons browser suite.

F02 Regression:
PASS — cart recovery/merge backend tests and cart-recovery browser suite.

F03 Regression:
PASS — checkout quote/payment totals backend tests and checkout-totals browser suite.

F04 Regression:
PASS — COD lifecycle/collection backend tests and cod-collection browser suite.

F05 Regression:
PASS — guest/auth/address/payment backend coverage and guest-checkout browser suite.

F06 Regression:
PASS — CatalogFiltersTest and desktop/mobile catalog-filters browser suite.

F07 Regression:
PASS — CatalogColorsTest and desktop/mobile catalog-colors browser suite.

F08 Regression:
PASS — cart mutation/backend validation coverage and all 44 desktop/mobile async mutation cases, including Buy Now failures and navigation ordering.

Remaining Risks:
Browser coverage uses mocked APIs and mobile emulation; integrated staging/physical-device acceptance remains separate. Both runtime allowlists must stay aligned when adding editor features. Unsupported embedded media and arbitrary visual styling are deliberately removed. Previously escaped literal markup is not globally decoded. Existing unrelated dependency advisories remain F17.

Related Follow-Up:
F10 — Review Aggregate Maintenance. Not started; stop after F09 for review.

Before/after example:

```text
BEFORE
Stored: <p><strong>Premium Headphones</strong></p>
Customer saw: literal <p><strong>Premium Headphones</strong></p>

AFTER
Customer sees: Premium Headphones, bold inside a paragraph
Headings/lists/quotes/links: approved formatting renders
Malicious <script>alert('xss')</script>: removed, no execution
Legacy unsafe rows: sanitized on API output without bulk rewriting
```

Verification commands (all application runtimes in Docker):

```sh
docker compose exec -T app php artisan test --compact --filter 'ProductDescription|CatalogTest|CatalogFiltersTest|CatalogColorsTest|ShopTest|CategoryTest|ShopCouponOwnershipTest|CartRecoveryTest|CartMutationTest|CartTest|Checkout|CouponTest|CodCollectionTest|BakongPaymentTest|AuthTest|NotificationTest|MarketplaceFlowTest|AdminTest|AdminMediaTest|AdminOpsTest|SellerProductPhotosTest|AdminInventoryTest'
docker compose run --rm browser-tests npx playwright test tests/e2e/product-descriptions.spec.ts tests/e2e/seller-product-form.spec.ts tests/e2e/cart-mutations.spec.ts tests/e2e/catalog-colors.spec.ts tests/e2e/catalog-filters.spec.ts tests/e2e/shop-coupons.spec.ts tests/e2e/cart-recovery.spec.ts tests/e2e/checkout-totals.spec.ts tests/e2e/cod-collection.spec.ts tests/e2e/guest-checkout.spec.ts --output=/tmp/f09-final-results --reporter=line --retries=0
docker compose exec -T frontend npm run build
docker compose exec -T app vendor/bin/pint --test app/Models/Product.php app/Providers/AppServiceProvider.php app/Services/ProductDescriptionSanitizer.php app/Http/Requests/AdminProductRequest.php app/Http/Resources/ProductDetailResource.php tests/Unit/ProductDescriptionSanitizerTest.php tests/Feature/Api/ProductDescriptionTest.php
```

PHP formatting and git diff --check passed. API workers were reloaded with the tested policy. No production deployment, credentials change or commit is included. Stop after F09.
