# Commerce completion and operating guide

Updated: 2026-10-17. This describes implemented functionality and remaining launch gates; it is not a declaration that production has been deployed.

## Decisions

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

## Launch gates and follow-up

- Complete real-account QR scanning, amount/recipient checks, a two-shop purchase, expiry/late payment and a verified refund before opening online checkout to customers. Automated bank tests use mocked responses, not live money.
- Provision Oracle Cloud, DNS and SMTP; configure secrets; run production migrations and off-server backup/alerting. See [Oracle deployment](./ORACLE-CLOUD.md).
- Business owners must approve tax, shipping, return windows, commissions, privacy/terms and data retention. The existing 10% tax calculation is application behavior, not tax advice; help-page copy is a starting point, not legal approval.
- Complete Khmer translation/copy review for the new screens. Several new Khmer entries currently use English fallback text.
- JPEG/PNG/WebP are resized/re-encoded, but no crop editor, separate thumbnail generation or HEIC transcoder is supplied. Use JPEG on phones where HEIC cannot be uploaded.
- The remaining npm development-only advisories need a separately tested Tailwind major-version migration. Do not apply a breaking `npm audit fix --force` on a release branch.
- Review real-device camera/browser accessibility, provider limits, load targets and operational alerts in staging. CI UI tests mock API data; they complement rather than replace integrated staging acceptance.

See [QA report](./QA-REPORT.md) for verification results and [API reference](./API-REFERENCE.md) for the contract additions.
