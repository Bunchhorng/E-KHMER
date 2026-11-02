# Commerce completion and operating guide

Updated: 2026-11-02. This describes implemented functionality and remaining launch gates; it is not a declaration that production has been deployed.

## Decisions

- Guest checkout: **SUPPORTED**. See the F05 policy below. Customer accounts are optional for checkout.
- Online payments use Bakong/KHQR. Each seller receives their own payment directly.
- There is no platform custody, payout wallet, or automatic seller settlement. Existing commission fields are accounting metadata, not an actual bank deduction.
- Checkout remains USD. KHR requires an explicit business decision, currency-aware amounts, pricing and checkout changes; KHQR encoding alone does not enable a second storefront currency.
- Hosting target: Oracle Cloud Infrastructure, using the production Docker stack.
- Production email provider is undecided. SMTP configuration is supplied, but delivery requires real SMTP credentials and a verified sending domain.

## Implemented areas

| Area | Implementation |
| --- | --- |
| Seller shop settings | Public name, description, contact/address, logo/banner, bank recipient and per-shop delivery methods. Only an owner or super administrator can change bank details. |
| Public shops | Active-shop directory, public profiles, published products, shop-scoped search, sorting and stock filter. Public resources exclude bank/commission/private settings. |
| Seller products | Simple products and color/size/custom-option variants, manufacturer barcodes, private purchase cost and selling price, SKU/active state, initial inventory, existing-gallery variant images. Barcode entry supports full-frame on-device camera scanning with native detection / background-worker fallback, optional autofocus/light controls, typing or keyboard-wedge USB/Bluetooth scanners; shop-scoped product/stock search accepts barcodes. Camera access requires HTTPS/localhost and permission, fills only the selected field and does not auto-save. Existing stock changes use the inventory ledger, not a product edit. |
| Photos | File upload, browser/device camera, gallery cover/reorder/removal; JPEG/PNG/WebP optimization, EXIF orientation and re-encoding. Animated GIFs are preserved. Input limits: 5 MB each, 10 MB combined new product images, 20 gallery images, 12.5 MP. |
| Multi-shop checkout | One delivery selection and fee per shop, server-side ownership validation, inventory reservation, snapshotted allocation totals, optional retry-safe checkout key. |
| Bakong | Persistent, expiring dynamic QR per shop allocation; authenticated bank verification; exact amount/currency/recipient validation; unique transaction references; order confirmation only after all shops are paid. |
| Payment exceptions | Partial payments, expiry/release, late-payment reconciliation, seller receipt/refund ledger. Failed or missing gateway responses do not claim success. |
| Returns/refunds | Delivered-item eligibility and quantity checks, configurable return window, private evidence, approval/receipt/refund state machine, explicit restock decision and audited refunds. Cash refunds require a receipt reference; Bakong refunds require verified transfer proof. |
| Support | Persisted guest/customer requests, private customer inbox, administrator replies and queued email. |
| Newsletter/help | Consent-based persisted subscription, unsubscribe link, queued welcome email and linked help/informational pages. No mass-marketing campaign engine is included. |
| Operations | Queue worker, reservation/payment scheduler, service heartbeats, HTTPS reverse proxy, daily database/media backups, isolated restore-check profile and Docker-based CI/browser tests. |

Customer auth/profile/addresses, catalog filters, cart, wishlist, order tracking, verified reviews and the catalog/inventory/orders/coupons/reporting administration remain in place.

## URLs

Use `http://localhost:5174` locally, or your configured HTTPS domain in production.

| Screen | Route |
| --- | --- |
| Seller workspace/shop chooser | `/seller` |
| Seller overview | `/seller/shops/:id` |
| Shop settings and delivery | `/seller/shops/:id/settings` |
| Product list / new product | `/seller/shops/:id/products` / `/seller/shops/:id/products/new` |
| Seller payments / returns | `/seller/shops/:id/payments` / `/seller/shops/:id/returns` |
| Public directory / shop | `/shops` / `/shops/:slug` |
| Customer returns / support | `/account/returns` / `/account/support` |
| Contact support | `/support` |
| Admin returns / support | `/admin/returns` / `/admin/support` |
| Bakong checkout | `/checkout/:orderNumber/payment` |

The settings screen has a **View my shop** link so owners do not need to guess their slug. Shop IDs and slugs are different; use the URL shown by the application.

## Direct-payment operation

1. A shop owner saves the real Bakong account ID and English QR merchant name/city in Settings. Managers cannot redirect funds by changing these fields.
2. Configure `BAKONG_API_TOKEN` on the server. Never put it into `VITE_*`, browser storage, source code or chat.
3. Checkout reserves stock for `ORDER_RESERVATION_MINUTES` (15 by default). Buyers pay each displayed shop QR separately. Changing shop settings does not change an already-issued payment's recipient snapshot.
4. **Check payment** asks the server to verify the bank response. The server also reconciles unsettled records every minute. There is no client-asserted paid flag and no assumed callback/webhook.
5. The parent order confirms and stock is deducted only when every allocation is verified. If part was paid and the window expires, inventory is released and collected transfers become **refund required**. These are not silently marked refunded.
6. The seller copies the exact refund memo shown in the application into their bank transfer description, sends the refund, then supplies the full transfer hash in Payments (cancelled payments) or Returns (delivered goods). Recipient, sender, amount, currency, hash and order-specific memo must match. This prevents an unrelated historical transfer being used as refund proof. The application verifies and records it; it does not initiate a bank transfer.

Reconciliation currently scans pending records from the last seven days. Operators must investigate older/unmatched transfers with the bank and support; the job is not a substitute for daily bank reconciliation. Monitor token expiry and renew the server credential through NBC's process.

Integration references: [NBC Bakong Open API](https://api-bakong.nbc.gov.kh/Bakong%20Open%20API%20Docs%20v1.0.1.pdf) and [NBC KHQR SDK documentation](https://bakong.nbc.gov.kh/download/KHQR/integration/KHQR%20SDK%20Document.pdf).

## F05 guest checkout policy (2026-11-02)

Guest checkout: **SUPPORTED**.

Inspection confirmed that guest carts, login/registration cart merging, inline order addresses, guest order lookup and session-protected COD/Bakong checkout already exist. Account-only reviews, returns and saved addresses do not require checkout itself to require an account. F05 therefore completes the existing shared `CheckoutService` flow.

Customer flow: Guest → Product → Cart → Checkout → Contact → Address → Delivery per shop → Payment → Review → Order → Confirmation → Track Order in the same browser.

- Guests enter email, recipient/full name, delivery phone and the existing address fields (street, city, province/state, postal code, country; second street line optional). The order and shipments store snapshots; no user or saved-address row is created. An existing account's email is accepted without identifying or linking that account.
- Signed-in customers retain saved/default address selection, adding and editing addresses. Guest checkout never requests the authenticated address API. Choosing sign-in or registration preserves the existing guest cart merge and returns to checkout with saved addresses.
- Order ownership remains `user_id = null` plus the existing payment provider's guest-session hash. The browser sends its cryptographically random `X-Session-Id` secret to order lookup, confirmation, cancellation and Bakong endpoints. Order numbers and numeric IDs alone never grant access. Confirmation and tracking display the public order reference. The secret is absent from URLs and API resources.
- Guest access is tied to the checkout browser. Keeping the order number without that browser session does not grant access. Lost browser sessions use the existing public support form; support must verify ownership before disclosing private order data. F05 does not implement cross-device magic links or automatic account claiming. Signing in later does not claim a previously placed guest order.
- COD is supported: confirmation/fulfillment stay unpaid until an authorized seller/admin records collection after delivery. Bakong/KHQR is supported with the same session-protected QR/status endpoints and server verification as account checkout. Server token and every shop's recipient configuration are required; unavailable configuration returns a clear instruction to choose COD. Legacy API payment methods retain their existing sandbox/production restrictions.
- Each shop retains its delivery selection, fee, allocation, coupon scope and payment totals. Financial authority, inventory locking/reservation/expiry, stale-item recovery and owner-scoped idempotency remain shared. Duplicate UI submissions are disabled; the persisted idempotency signature is a digest without contact/address data.
- Coupons with `per_user_limit` require sign-in, including when a guest uses an existing account email. A coupon explicitly configured with `per_user_limit: null` allows guests without a per-customer limit; all shop, eligibility, minimum spend, date, global usage and discount cap rules still apply. Admin coupon editing exposes this choice. The migration makes that existing nullable API contract persistable and preserves every existing numeric limit/default.
- Confirmation and shipping/status emails resolve the snapshotted guest email through anonymous mail notifications. Guests receive no database inbox notification. Mail links require the original browser session and explain that constraint. Account receipts remain account-only; the guest confirmation does not offer a receipt action that would force login. The confirmation displays contact email without asserting delivery succeeded.
- Verified reviews and self-service returns remain account-only. Guests can contact `/support` with their order reference for post-purchase help. An email match never establishes order ownership.
- Contact details stay in component memory until the order snapshot; they are not persisted in local/session storage. The existing browser session secret is retained for secure guest order access. Checkout/quote/guest-lookup throttles complement existing Bakong limits and idempotency. Broader abuse quotas remain F13.
- Apply `2026_11_02_000002_allow_coupons_without_per_customer_limits.php` with `docker compose exec app php artisan migrate`. No order/address schema changes are needed. Automated tests use isolated SQLite and mocked bank/browser responses; real SMTP/deliverability remains O16 and live bank acceptance remains an existing launch gate.

## F06 rating and price filter policy (2026-11-02)

- The existing query contract remains `rating`, `min`, `max` on `/api/catalog/products` and public shop product listings. Ratings accept numeric thresholds from 1 through 5, including fractions; the UI retains 4.5+, 4.0+ and 3.5+. Filtering compares `products.rating_avg >= rating` at its stored two-decimal precision and requires `rating_count > 0`. It never rounds 4.49 into a 4.5 match. Without a rating filter, unrated products remain visible. Approved-review aggregate maintenance stays with the existing review service; F10 is separate.
- Prices are finite nonnegative numbers in USD, including zero and decimals. `max < min` returns 422 with a clear validation message. Selling price is the selected active, available variant's `price`, falling back to `products.price` when the variant price is null. Crossed-out `compare_at_price` and private purchase cost never determine the filter.
- A product matches a price window when **one purchasable variant** meets both bounds and selected attribute values. The card advertises the lowest matching available variant price; its first available add-to-cart variant has that price. Price sorting uses the same advertised value. Existing imported products without variants retain their base-price listing/filter behavior, but do not contribute purchasable price metadata. Products with no available variant remain visible without a price/stock filter; they do not match an active price window.
- Both marketplace and shop responses add numeric `meta.price_range: { min, max }`; null bounds mean no purchasable prices. The existing facets endpoint retains `data.price_range` and can validate the same filters. Bounds use active/public products in active shops (or platform products), active non-deleted variants and stock remaining after reservations. Hidden, deleted, inactive, unconfigured and fully reserved variants cannot inflate bounds.
- Bounds follow current search, category (including descendants), brand, rating, attributes and shop, but exclude current price bounds and pagination. Customers can therefore widen a price window again. Category/brand/attribute facet counts keep their existing semantics; F06 scopes price metadata without redesigning all facet counts.
- Desktop and mobile share rating/price controls; public shop pages reuse them with shop-scoped metadata. Decimal number inputs and sliders stay synchronized. Inputs debounce for 250 ms and commit on change/blur; invalid bounds stay visible without swapping values. Explicit URL/input prices may extend beyond catalog bounds. Clear filters restores actual metadata bounds. URL history, refresh, filter composition and page reset are preserved. Latest-response guards protect products and metadata; stale acknowledgements cannot overwrite the next price edit. Loading, empty results, metadata failures and product failures have distinct states with retry.
- MySQL EXPLAIN confirmed use of the existing `(product_id, is_active)` variant index, inventory variant/shop index and shop primary key; the aggregate also uses an existing product visibility index. The small development listing uses a product scan/filesort for derived-price ordering. No new index or schema migration is justified by that plan. A regression test checks that listing query count stays constant as the number of products grows; production-scale timing remains a staging exercise.
- F06 originally preserved the mobile color control; F07 corrects its value/slug mismatch as documented below.

## F07 canonical color filter policy (2026-11-02)

- Desktop and the mobile filter sheet share `CatalogColorFilter` and one `filters.colors: string[]`. The component maps the actual API facet `{ slug, value, name, swatch_color, count }` to `{ slug, value, name, swatch, count }`. `value` is the actual attribute value, with `name` retained as the existing compatibility fallback. The slug is metadata, never the selected or submitted value. Both layouts submit `Light Blue`, not `light-blue`.
- Each option displays its name and API-provided valid CSS swatch. Missing/invalid swatches use the existing common-name palette or neutral `#cbd5e1`; the visible label keeps custom colors such as Patterned selectable. Selection, deselection, active chips and chip removal all use the canonical value.
- Page URLs retain `colors=Light+Blue&colors=Black`; product API calls use `colors[]=Light+Blue&colors[]=Black`. Refresh, browser history and responsive layout changes restore the same selection. Color changes reset pagination and preserve category, brand, search, sizes, rating, price, stock and sorting. Clear all removes the URL state and selections.
- The existing mobile sheet applies each change immediately. Apply, Close and backdrop dismissal close the sheet and retain the already-applied selection; there is no staged draft or Cancel action. F07 preserves that behavior and shares it with the desktop control.
- Catalog matching remains server-side and explicitly case-insensitive with lowercase comparison, rather than depending on MySQL collation. Multi-word names and punctuation stay intact; slugs are not translated. Multiple colors remain OR within the color attribute, with existing AND composition across attributes and other filters. Unknown values return zero results. Pagination, variant/stock eligibility and F06 price metadata retain their existing rules.
- API validation now bounds colors to at most 50 string entries of at most 255 characters, rejecting malformed nested/non-string values with 422. Existing single-string and CSV inputs are normalized before validation for compatibility. There are no route, schema, index or matching-semantics changes.
- Public shop pages have no existing color picker. They now forward canonical color/size URL arrays to the existing shop-scoped products API and preserve them through other filter edits. Backend and browser tests cover that path; no new shop picker is introduced.
- See [F07 verification report](./F07-MOBILE-COLOR-FILTERS-REPORT.md) for desktop/mobile coverage and F01–F06 regression results. F08 async cart mutations are documented below.

## F08 async cart mutation policy (2026-11-02)

- Inspection found that Buy Now already awaited the cart store's boolean result, while Add to Cart still opened the drawer immediately. Buy Now lacked pending protection and did not await/catch router navigation. Listing cards dispatched unawaited mutations through parent events. The existing global cart error banner and API message helper were already present and are reused.
- All cart mutations retain `Promise<boolean>`: confirmed API response applied → true; rejected/cancelled mutation → false. Product detail snapshots the selected variant and quantity, validates the selection, enters a pending state, awaits the add, and only then opens the drawer or awaits checkout navigation. Failure retains the product page and restores controls. A blocked/failed navigation leaves the legitimate cart addition intact and offers View Cart. A late response after leaving detail cannot pull the customer back into checkout.
- Product detail's Add to Cart and Buy Now buttons share a pending guard, expose `disabled`/`aria-busy`, and show Adding/Preparing checkout. Options and quantity cannot change during that request. The existing page uses one responsive set of purchase buttons; there is no separate sticky mobile implementation to diverge.
- `ProductCard` owns and awaits its add operation everywhere it is used: marketplace, home/rails, public shop and account wishlist/dashboard. It preserves the existing first-available-variant behavior. It emits `added-to-cart` only after confirmation, allowing wishlist callers to remove the saved product only on success. Pending state is shared by variant across repeated cards; success feedback never appears before confirmation.
- Pending store keys track add-by-variant, update/remove-by-item, and clear separately. Repeated pending keys return false without another request. Full-cart reads and writes are serialized per store to preserve response ordering; other cards/rows can queue their own operations. The affected row's quantity/remove controls disable while pending; clear and checkout retain cart-wide guards. Store state is pessimistic: failed quantity/remove/clear operations retain confirmed items/counts; quote refresh retains backend authority for totals and coupon eligibility.
- Laravel validation/business messages use the existing extraction helper and global accessible cart banner. Network/timeout messages are actionable; unexpected 5xx responses show the translated action fallback without server internals. 401/419 mutation failures show session recovery. Guest cart 401s without an Authorization header stay in the guest flow, while rejected authenticated/protected requests retain existing auth handling. Intentional Axios cancellation returns false without a false mutation error banner. No automatic mutation retry is introduced.
- Backend messages now distinguish unavailable variants, products, shops and stock. Live stock remains authoritative; over-quantity requests are rejected rather than reduced. Same-variant additions continue merging quantities, existing cart lines remain, stale rows retain F02 recovery, and checkout still uses F03 server quotes and F05 optional accounts. There are no endpoint, payload, schema, reservation, payment or coupon policy changes.
- See [F08 verification report](./F08-ASYNC-CART-MUTATIONS-REPORT.md). F09 formatted product descriptions are documented below.

## F09 product description policy (2026-11-02)

- `RichTextEditor` outputs HTML; `products.description` is nullable TEXT. The storefront previously interpolated this HTML as escaped text, including fragments in the generated Key Features list. The editor had a custom browser-only filter but there was no trusted backend sanitizer. A read-only development sample contained 20 plain-text descriptions and two empty values; no sample row was rewritten.
- Product model assignments now sanitize through the shared `ProductDescriptionSanitizer` service before storage. Reads apply the same policy, protecting legacy rows in public, seller and super-admin responses without a migration or bulk rewrite. There is no trusted-admin HTML bypass. Server sanitization uses Symfony HTML Sanitizer 7.4.22, with a minimum dependency constraint of `^7.4.22`; policy configuration is reused through a singleton service.
- The editor, seller form's live description preview, storefront renderer and text projection share one browser helper using DOMPurify 3.4.17. Its allowlist mirrors the backend policy as defense in depth, including for legacy or unsanitized API fixtures. The reusable `ProductDescription` component is the only rich-text `v-html` sink and always prepares sanitized content first. It caches HTML/text through a computed value; seller checklist text also caches separately from unrelated form inputs. Plain text projections sanitize before DOM parsing and never compile content as Vue templates.
- Formatting allows paragraphs/line breaks, the editor's structural div/span markup, bold/italic/underline, lists, headings h1–h4 and blockquotes. h1 is retained for existing editor content; the toolbar still offers its existing paragraph/h3 choices. Anchors may retain href/title, receive fixed `rel="noopener noreferrer"`, and use HTTP/HTTPS/mailto or safe relative/fragment URLs. Targets are removed, so links stay in the current tab. Scripts, active embeds, forms/controls, SVG/MathML and description images/media are removed; product images remain in the dedicated gallery. Arbitrary styles/classes/IDs, handlers and active URL schemes cannot reach rich rendering.
- Existing toolbar controls and plain-text clipboard paste remain. Safe stored HTML loads without double escaping, and repeated edits/saves preserve formatting and entities. The form preview renders the same full-description component as customers. The seller list's existing short/card preview continues displaying plain summary text; no full-description field or extra listing API fetch was added. The admin editor uses the same sanitizer and typography. Product cards do not render full descriptions; generated product-detail features now use a sanitized plain-text projection. No rich HTML is inserted into SEO fields, and search indexing is not redesigned.
- Empty/null/visually empty descriptions produce no empty rich block or Key Features heading. Application-owned typography provides paragraph spacing, visible list markers, headings, quotes and links. Long words/URLs wrap on desktop/tablet/mobile; styles inherit the theme, including dark mode. Unsafe seller font/color/layout declarations are stripped. Escaped literal content is never indiscriminately decoded into executable HTML.
- Requests retain the 60,000-character limit and now enforce a 60,000-byte UTF-8 limit. Sanitized storage output must remain within the existing TEXT capacity of 65,535 bytes; expansion over the limit returns 422 rather than truncating content or producing a database error. No schema, ownership, cart, coupon, checkout, payment or filter behavior changes are included.
- Dependency review checked official Symfony/DOMPurify documentation, maintenance/security pages and Docker Composer/npm audits. Neither selected sanitizer had a reported audit advisory. Existing advisories remain outside F09: two Composer packages (symfony/mime, symfony/yaml) and eight npm package entries (including existing Vue I18n, Vite, follow-redirects and selector-parser dependencies). Broader dependency remediation remains F17; no unrelated dependency update or forced audit fix was applied.
- See [F09 verification report](./F09-PRODUCT-DESCRIPTIONS-REPORT.md). Review aggregate maintenance is covered below.

## F10 review aggregate policy (2026-11-02)

- `rating_count` counts approved reviews; `rating_avg` is their average rounded to two decimal places. Pending, rejected and hard-deleted reviews do not contribute. Empty approved sets store `0.00` / `0`. Reviews have no soft delete or restore workflow; archived products remain safe to moderate.
- Customer creation/edit/deletion, administrator approval/rejection/deletion and repair operations use the same `ReviewService` formula. Every supported mutation locks only its product first, then reloads/locks the review; state and aggregate commit or roll back together. Database notification writes are included. Three transaction attempts cover deadlocks. Aggregate locking reads avoid stale MySQL repeatable-read snapshots. Seeder aggregate arithmetic is replaced by the same service; normal request paths have no observer or duplicate recalculation.
- Approved customer edits still reset to pending. Delivered purchase, one review per user/product, ownership and existing moderation authorization remain intact. Review text, rating content, images/media and seller powers are unchanged.
- The admin page can reject approved reviews, view rejected reviews and approve them again. A failed request retains its row; bulk selection never reports total success after any failed request. Existing bulk behavior uses individual requests, without a new bulk endpoint. Refreshed product details, cards, shop profiles, catalog filters and rating/popularity order use updated database aggregates. No product/catalog rating cache exists to invalidate.
- Audit/repair: `docker compose exec -T app php artisan reviews:recalculate-ratings --dry-run [--product=123]`; omit `--dry-run` to repair. The command includes archived products, writes only mismatches and reports counts. On 2026-11-02 the development dry run scanned 22 products and found zero mismatches; production data was not inspected or changed. No automatic write-mode repair, reseed or schema change was performed.
- See [F10 verification report](./F10-REVIEW-AGGREGATES-REPORT.md). F11 low-stock notification lifecycle has not been started.

## Launch gates and follow-up

- Complete real-account QR scanning, amount/recipient checks, a two-shop purchase, expiry/late payment and a verified refund before opening online checkout to customers. Automated bank tests use mocked responses, not live money.
- Provision Oracle Cloud, DNS and SMTP; configure secrets; run production migrations and off-server backup/alerting. See [Oracle deployment](./ORACLE-CLOUD.md).
- Business owners must approve tax, shipping, return windows, commissions, privacy/terms and data retention. The existing 10% tax calculation is application behavior, not tax advice; help-page copy is a starting point, not legal approval.
- Complete Khmer translation/copy review for the new screens. Several new Khmer entries currently use English fallback text.
- JPEG/PNG/WebP are resized/re-encoded, but no crop editor, separate thumbnail generation or HEIC transcoder is supplied. Use JPEG on phones where HEIC cannot be uploaded.
- The remaining npm development-only advisories need a separately tested Tailwind major-version migration. Do not apply a breaking `npm audit fix --force` on a release branch.
- Review real-device camera/browser accessibility, provider limits, load targets and operational alerts in staging. CI UI tests mock API data; they complement rather than replace integrated staging acceptance.

See [QA report](./QA-REPORT.md) for verification results and [API reference](./API-REFERENCE.md) for the contract additions.
