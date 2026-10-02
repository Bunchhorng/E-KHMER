# API Reference (Live)

This reference was generated from the **live Laravel backend** running inside Docker. It documents every route, its request payload, validation rules, and the exact JSON response shape produced by the API Resources.

- **Base URL:** `http://localhost:8000/api`
- **Format:** JSON
- **Auth:** Laravel Sanctum bearer tokens — `Authorization: Bearer <token>`
- **Guest cart/checkout:** requires the `X-Session-Id` header (E.g. a UUID stored in `localStorage`).
- **Errors:** Laravel renders validation errors as `422` with a `message` key, unauthorized as `401`, forbidden as `403`, and not-found as `404` with a `message`.

**Dispatch details** obtained via `artisan route:list --path=api` — **72 routes**.

---

## 1. Notation

| Marker | Meaning |
| --- | --- |
| `Public` | No authentication required |
| `Bearer` | Requires `Authorization: Bearer <token>` |
| `Admin` | Requires `Bearer` **and** the `admin` role |
| `X-Session-Id` | Send this header for guest carts / guest checkout |

---

## 2. Authentication

| Method | Endpoint | Guard | Controller |
| --- | --- | --- | --- |
| POST | `/auth/register` | Public | `AuthController@register` |
| POST | `/auth/login` | Public | `AuthController@login` |
| GET | `/auth/me` | Bearer | `AuthController@me` |
| POST | `/auth/logout` | Bearer | `AuthController@logout` |

### POST /auth/register — Register a new customer

**Request body**
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "newsletter": true
}
```

**Validation rules** (`RegisterRequest`)
| Field | Rules |
| --- | --- |
| `name` | required, string, max:255 |
| `email` | required, email, unique:users,email |
| `password` | required, string, min:8, confirmed |
| `password_confirmation` | required, string |
| `newsletter` | (optional) boolean — stored via `$request->boolean()` |

**Response `201 Created`**
```json
{
  "data": {
    "token": "<plain-text-sanctum-token>",
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "customer",
      "avatar": null,
      "phone": null,
      "newsletter": true,
      "email_verified_at": null
    }
  }
}
```

### POST /auth/login — Log in

**Request body**
```json
{ "email": "jane@example.com", "password": "secret123", "remember": true }
```

**Validation rules** (`LoginRequest`): `email` required+email+max:255, `password` required+string, `remember` nullable+boolean.

Email is trimmed and lowercased before the lookup, so the stored address always matches.

`remember: true` issues a token that expires in 30 days. Omitting it keeps the previous behaviour and issues a token with no expiry.

**Response `200`**
```json
{
  "data": {
    "token": "<plain-text-sanctum-token>",
    "user": {
      "id": 1, "name": "Jane Doe", "email": "jane@example.com",
      "role": "customer", "avatar": null, "phone": null,
      "newsletter": false, "email_verified_at": null
    }
  }
}
```

> Invalid credentials → `422` with `{"message": "The given data was invalid.", "errors": {"email": ["Invalid credentials."]}}`.
>
> Too many attempts → `429` with a `Retry-After` header. Login allows 10 attempts per minute.

### GET /auth/me — Current user

**Response `200`**
```json
{
  "data": {
    "id": 1, "name": "Jane Doe", "email": "jane@example.com",
    "role": "customer", "avatar": null, "phone": null,
    "newsletter": false, "email_verified_at": null
  }
}
```
> Note: `me` returns **bare** `UserResource` (no wrapping `data` object separate from token response — the resource itself is `{"data": {...}}`).

### POST /auth/logout — Revoke current token

**Response `200`**
```json
{ "data": { "message": "Logged out successfully." } }
```

---

## 3. Public Catalog & Lookup

### GET /catalog/products — Filtered product listing (paginated)

Controller: `CatalogController@index` → `CatalogService::filtered()`. Input is validated by
`CatalogFilterRequest`; unknown parameters are ignored and invalid ones return `422`.
Throttled to `120` requests/minute per client.

**Query parameters** (all optional)
| Param | Type | Behavior |
| --- | --- | --- |
| `q` | string (max 120) | Search `name`, `short_description`, `description`, `sku` (LIKE, case-insensitive, `%`/`_` escaped) |
| `category` | string | Filter by category **slug** |
| `brand` | string | Filter by brand **slug** |
| `colors` | string/array | Comma-separated or array; exact attribute value match on the `color` attribute |
| `sizes` | string/array | Comma-separated or array; exact attribute value match on the `size` attribute |
| `min` | number | `price >= min` |
| `max` | number | `price <= max` |
| `rating` | int 1–5 | `rating_avg >= rating` |
| `stock` | 0/1 | Only products with `quantity - reserved_quantity > 0`; `0`/`false` disables the filter |
| `sort` | string | `newest` (default), `price-asc`, `price-desc`, `name-asc`, `name-desc`, `rating`, `popularity`, `featured` |
| `page` | int ≥ 1 | Page number (default 1) |
| `perPage` | int 1–48 | Items per page (default 12). Values above the cap return `422`. |

Attribute filters are **AND across attributes, OR within one attribute**: `colors=Red,Blue&sizes=L`
matches "Red or Blue, in size L". Values are compared exactly (case-insensitive), so `Red` does not
match `Crimson Red`.

Only products that are `is_active` **and** whose owning shop is `active` (or that have no shop)
are returned.

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "classic-tee",
      "name": "Classic Tee",
      "short_description": "A soft cotton tee.",
      "price": 29.99,
      "compare_at_price": 39.99,
      "rating_avg": 4.6,
      "rating_count": 18,
      "is_featured": true,
      "is_active": true,
      "in_stock": true,
      "cover_image": "/storage/products/tee.jpg",
      "variants": [
        {
          "id": 7,
          "sku": "TEE-RED-M",
          "name": "Red / M",
          "price": 29.99,
          "in_stock": true,
          "attributes": [
            { "attribute_slug": "color", "name": "Color", "value": "Red", "swatch_color": "#dc2626" }
          ]
        }
      ],
      "colors": [ { "value": "Red", "name": "Red", "swatch_color": "#dc2626" } ],
      "sizes": [ { "value": "M", "name": "M", "swatch_color": null } ],
      "brand": { "slug": "acme", "name": "Acme" },
      "category": { "slug": "apparel", "name": "Apparel" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 12, "total": 30 }
}
```

`colors` / `sizes` summarise the active variants so a card can render swatches without walking the
variant list.

### GET /catalog/featured — Featured products

`CatalogController@featured`. Query `limit` (default 8, max 24; out-of-range values return `422`).
Throttled to `120` requests/minute. Returns `ProductResource` **collection**.

**Response `200`**
```json
{ "data": [ { "id": 1, "...": "product shape" } ] }
```

### GET /catalog/facets — Facets for the filter sidebar

`CatalogController@facets` → returns no-arg JSON. Counts only include products a shopper can reach,
so the facet numbers match the listing totals. Throttled to `120` requests/minute.
```json
{
  "data": {
    "brands": [ { "slug": "acme", "name": "Acme", "count": 12 } ],
    "categories": [ { "slug": "apparel", "name": "Apparel", "count": 30 } ],
    "colors": [ { "slug": "blue", "value": "Blue", "name": "Blue", "swatch_color": "#2563eb", "count": 5 } ],
    "sizes": [ { "slug": "l", "value": "L", "name": "L", "swatch_color": null, "count": 8 } ],
    "attributes": [
      {
        "slug": "color",
        "name": "Color",
        "type": "color",
        "values": [ { "slug": "blue", "value": "Blue", "name": "Blue", "swatch_color": "#2563eb", "count": 5 } ]
      }
    ],
    "price_range": { "min": 4.5, "max": 349.0 },
    "max_per_page": 48
  }
}
```

`colors` and `sizes` are retained for the existing swatch UI; `attributes` lists **every** attribute
with `is_filterable = true` so the sidebar can be built generically. Send the `value` field (not
`slug`) back as `colors` / `sizes` — the filter matches on the exact attribute value.

### POST /catalog/variants/resolve — Dynamic variant filtering

`CatalogController@resolveVariants`. Maps a set of attribute value ids to the concrete variants that
satisfy **all** of them, with live stock. Throttled to `120` requests/minute.

**Request body**
```json
{ "attribute_values": [3, 11], "product_id": 1 }
```
| Field | Rules |
| --- | --- |
| `attribute_values` | required, array, 1–10 entries, each an integer ≥ 1 |
| `product_id` | optional; scopes the resolution to one product |

**Response `200`**
```json
{
  "data": [
    {
      "variant_id": 42,
      "product_id": 1,
      "sku": "TEE-RED-M",
      "name": "Red / M",
      "price": 29.99,
      "available_quantity": 4,
      "in_stock": true,
      "attributes": [
        { "attribute": "color", "value": "Red", "swatch_color": "#dc2626" }
      ]
    }
  ]
}
```

Variants of unpublished products, unpublished variants and soft-deleted rows are excluded.
`422` when `attribute_values` is missing or empty.

### GET /catalog/products/{slug} — Product detail

`CatalogController@show` → `ProductDetailResource`. Route constrained to `[A-Za-z0-9-]+`. 404 if not found or inactive.

**Response `200`** (full shape)
```json
{
  "data": {
    "id": 1,
    "slug": "classic-tee",
    "name": "Classic Tee",
    "short_description": "A soft cotton tee.",
    "description": "Long description...",
    "sku": "ACME-TEE",
    "weight": 0.2,
    "meta_title": null,
    "meta_description": null,
    "price": 29.99,
    "compare_at_price": 39.99,
    "rating_avg": 4.6,
    "rating_count": 18,
    "is_featured": true,
    "is_active": true,
    "in_stock": true,
    "cover_image": "/storage/products/tee.jpg",
    "brand": { "slug": "acme", "name": "Acme" },
    "category": { "slug": "apparel", "name": "Apparel" },
    "gallery": [
      { "id": 3, "image_path": "/storage/products/tee-1.jpg", "alt_text": "Front" }
    ],
    "attributes": [
      {
        "id": 2, "name": "Color", "slug": "color", "type": "text",
        "values": [ { "id": 7, "value": "Blue", "swatch_color": "#0000ff" } ]
      },
      {
        "id": 3, "name": "Size", "slug": "size", "type": "text",
        "values": [ { "id": 9, "value": "L", "swatch_color": null } ]
      }
    ],
    "variants": [
      {
        "id": 11, "sku": "ACME-TEE-BL-L", "name": "Blue / L",
        "price": 29.99, "compare_at_price": null,
        "is_default": true, "is_active": true,
        "available_quantity": 24, "in_stock": true,
        "attributes": [
          { "attribute_slug": "color", "name": "Color", "value": "Blue" },
          { "attribute_slug": "size", "name": "Size", "value": "L" }
        ]
      }
    ]
  }
}
```

### GET /categories — Category tree

`CategoryController@index` → active root categories, nested `children` (recursive, only active). Returns `CategoryResource` collection.
```json
{ "data": [ { "id": 1, "name": "Apparel", "slug": "apparel", "description": null, "image": null, "sort_order": 1, "is_active": true, "parent_id": null, "children": [ { "id": 4, "name": "T-Shirts", "slug": "t-shirts", "...": "...", "parent_id": 1, "children": [] } ] } ] }
```

### GET /brands — Brand list

`BrandController@index`. `BrandResource` collection.
```json
{ "data": [ { "id": 1, "name": "Acme", "slug": "acme", "description": null, "logo": null, "is_active": true } ] }
```

### GET /attributes — Attributes + values for variant filtering

`AttributeController@index` → `AttributeResource` collection with nested `values`.
```json
{ "data": [ { "id": 2, "name": "Color", "slug": "color", "type": "text", "is_filterable": true, "values": [ { "id": 7, "value": "Blue", "swatch_color": "#0000ff" } ] } ] }
```

### GET /shipping-methods — Active shipping methods

`ShippingMethodController@index` → only `is_active=true`, ordered by `price`.
```json
{ "data": [ { "id": 1, "name": "Standard", "code": "standard", "description": "3-5 days", "price": 5.0, "estimated_days_min": 3, "estimated_days_max": 5, "is_active": true } ] }
```

### GET /products/{product}/reviews — Approved reviews for a product

`ReviewController@index`. Route constrained to numeric `product`. Only `status=approved`, newest first.

**Response `200`**
```json
{ "data": [ { "id": 1, "rating": 5, "title": "Great", "body": "Love it.", "verified": true, "status": "approved", "helpful_count": 2, "created_at": "2026-09-01T10:00:00Z", "is_featured": false, "user": { "id": 4, "name": "Jane" } } ] }
```

### POST /coupons/validate — Validate a coupon code

`CouponController@validate` → `CouponResource` with `subtotal` additional field. **Public** (guest coupon checks are supported because `$request->user()` may be null).

**Request body** (`CouponValidateRequest`)
```json
{ "code": "SAVE10", "subtotal": 120.0 }
```

**Validation:** `code` required string, `subtotal` required numeric min:0.

**Response `200`** (`CouponResource`)
```json
{
  "data": {
    "code": "SAVE10",
    "type": "percentage",
    "value": 10,
    "min_order_amount": 50,
    "max_discount_amount": 20,
    "valid": true,
    "discount_amount": 12,
    "message": "10% off"
  },
  "subtotal": 120
}
```
> When invalid, `valid: false` and `message: "This coupon is not currently valid."`. Business validation lives in `CouponService::validate()` (expiry → usage limit → min order amount).

---

## 4. Cart (guest or authenticated)

**Guest carts** require header `X-Session-Id`. For guests, hitting cart endpoints without this header returns `422: {"errors": {"session": ["A session identifier is required for guest carts."]}}`.

All cart responses return `CartResource`.

### GET /cart — Current cart

**Response `200`**
```json
{
  "data": {
    "id": 5,
    "items": [
      {
        "id": 12,
        "quantity": 2,
        "variant": {
          "id": 11, "sku": "ACME-TEE-BL-L", "name": "Blue / L",
          "price": 29.99, "compare_at_price": null, "in_stock": true,
          "product": { "id": 1, "slug": "classic-tee", "name": "Classic Tee", "cover_image": "/storage/products/tee.jpg" }
        }
      }
    ],
    "totals": { "subtotal": 59.98, "discount_amount": 0, "tax_amount": 6.0, "total": 65.98, "items_count": 2 }
  }
}
```
> Tax is a flat `10%` of subtotal. `discount_amount` is always `0` in cart (coupons applied at checkout).

### POST /cart — Add item

**Request body** (`CartAddRequest`)
```json
{ "product_variant_id": 11, "quantity": 1 }
```

**Validation:** `product_variant_id` required integer exists:product_variants,id; `quantity` integer min:1 max:99 (defaults to 1).

**Behavior** (`CartService::add`): caps quantity by available stock; throws `422` if variant inactive or out of stock. Adding an existing variant merges quantities (capped at available).

**Response:** `CartResource` (same shape as GET /cart).

### PUT /cart/items/{cartItem} — Update item quantity

**Request body** (`CartUpdateRequest`)
```json
{ "quantity": 3 }
```

**Validation:** `quantity` required integer min:1 max:99. Setting `quantity <= 0` deletes the item. Request caps to available stock.

**Response:** `CartResource`.

### DELETE /cart/items/{cartItem} — Remove item

**Response:** `CartResource`.

### DELETE /cart — Clear cart

**Response:** `CartResource`.

### GET /cart/totals — Cart totals only

`CartController@totals` → computed by `CartService::totals()`:
```json
{ "data": { "items_count": 2, "subtotal": 59.98, "tax_amount": 6.0, "discount_applicable": 0, "total": 65.98 } }
```

---

## 5. Checkout (begin / confirm / cancel)

Checkout endpoints resolve the cart and apply an **inventory reservation lock** (~15 min window). Guest checkout also requires `X-Session-Id`.

### POST /checkout — Begin checkout (reserve + create pending order)

`CheckoutController@begin` → `CheckoutService::begin()`. `CheckoutRequest` validation:

**Request body**
```json
{
  "shipping_method_id": 1,
  "payment_method": "card",
  "coupon_code": "SAVE10",
  "email": "jane@example.com",
  "note": "Please deliver after 5pm",
  "address_id": 3,
  "address": {
    "full_name": "Jane Doe",
    "phone": "+855 12 345 678",
    "address_line1": "123 Main St",
    "address_line2": "Apt 4B",
    "city": "Phnom Penh",
    "state": "PP",
    "postal_code": "12000",
    "country": "KH"
  }
}
```

**Validation rules** (`CheckoutRequest`)
| Field | Rules |
| --- | --- |
| `shipping_method_id` | required integer exists:shipping_methods,id |
| `payment_method` | required string in:card,cod (defaults `card`) |
| `coupon_code` | nullable string |
| `email` | nullable email |
| `note` | nullable string max:1000 |
| `address_id` | nullable integer exists:addresses,id |
| `address` (array) | nullable |
| `address.full_name` | required_without:address_id string max:255 |
| `address.address_line1` | required_without:address_id string |
| `address.city` | required_without:address_id string |
| `address.state` | required_without:address_id string |
| `address.postal_code` | required_without:address_id string max:20 |
| `address.address_line2` | nullable string |
| `address.phone` | nullable string max:30 |
| `address.country` | nullable string max:100 (default `US`) |

**Behavior:**
- Resolves address from `address_id` (must belong to user) or inline `address`.
- Validates coupon via `CouponService` if provided.
- Reserves stock via `InventoryService::reserveMany()`; on failure → `422 insufficient stock`.
- Creates `Order` (status `pending`), `OrderItem` rows (price/title/variant snapshot), `Payment` (pending), `Shipment` (pending).
- Applies coupon usage record if discount > 0.
- Total = subtotal − discount + tax(10% of discounted subtotal) + shipping.

**Response `200`** — `OrderResource` with `reservation_expires_at` added:
```json
{
  "data": { "...order shape (see below)..." },
  "reservation_expires_at": "2026-09-01T10:15:00Z"
}
```

### POST /checkout/{orderNumber}/confirm — Confirm payment, deduct stock

`CheckoutController@confirm` → `CheckoutService::confirm()`.

**Request body** (optional)
```json
{ "transaction_id": "px_12345" }
```

**Behavior:** requires order `payment_status=unpaid` and `status=pending`; otherwise → `422 Order already settled`. Permanently **deducts** reserved stock, marks payment `completed` (auto-generates transaction id if none), records a `payment_transaction` (capture/success), sets order `payment_status=paid` and `status=confirmed`, and clears the (authenticated) user's cart.

> Ownership: for authenticated users the order must belong to them; guests must match `X-Session-Id` → else `404 Order not found`.

**Response:** `OrderResource`.

### POST /checkout/{orderNumber}/cancel — Cancel, release stock

`CheckoutController@cancel` → `CheckoutService::release()`. Only cancels unpaid pending orders; sets `status=cancelled` and appends "reservation released" to the note. `OrderResource`.

---

## 6. Customer (Authenticated)

All routes in this section are guarded by `auth:sanctum`.

### Orders

#### GET /orders — List customer's orders

`OrderController@index` → `OrderService::listFor($user, $status)`. Optional query `status`.

**Response `200`** — `OrderListResource` collection + meta
```json
{
  "data": [
    {
      "order_number": "ORD-2026-0001",
      "status": "confirmed",
      "payment_status": "paid",
      "total": 65.98,
      "placed_at": "2026-09-01T10:00:00Z",
      "items_count": 2
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
}
```

#### GET /orders/{orderNumber} — Order detail

`OrderController@show` → `OrderResource` (must belong to user, else 404).

**`OrderResource` full shape:**
```json
{
  "data": {
    "order_number": "ORD-2026-0001",
    "status": "confirmed",
    "payment_status": "paid",
    "subtotal": 59.98,
    "discount_amount": 6.0,
    "tax_amount": 5.4,
    "shipping_amount": 5.0,
    "total": 64.38,
    "currency": "USD",
    "shipping_address": { "full_name": "Jane Doe", "phone": "...", "address_line1": "...", "address_line2": null, "city": "Phnom Penh", "state": "PP", "postal_code": "12000", "country": "KH" },
    "billing_address": { "...": "same snapshot" },
    "email": "jane@example.com",
    "phone": "+855 12 345 678",
    "customer_name": "Jane Doe",
    "note": null,
    "coupon_code": "SAVE10",
    "placed_at": "2026-09-01T10:00:00Z",
    "items": [
      { "id": 1, "product_id": 1, "product_variant_id": 11, "product_name": "Classic Tee", "variant_label": "Color: Blue, Size: L", "sku": "ACME-TEE-BL-L", "image_path": "/storage/products/tee.jpg", "unit_price": 29.99, "quantity": 2, "line_total": 59.98 }
    ],
    "payment": { "id": 1, "method": "card", "status": "paid", "transaction_id": "px_12345", "amount": 64.38, "paid_at": "2026-09-01T10:01:00Z" },
    "shipment": { "tracking_number": null, "carrier": null, "status": "pending", "shipped_at": null, "delivered_at": null, "address_snapshot": "{\"full_name\":\"...\"}" }
  }
}
```

#### POST /orders/{orderNumber}/cancel — Cancel an order

`OrderController@cancel` → `OrderService::cancelOwn()`. `OrderResource`.

### Wishlist

#### GET /wishlist — List wishlist products

`WishlistController@index` → `ProductResource` collection (active products only, with brand/category/images/variants.inventory).
```json
{ "data": [ { "id": 1, "...": "product shape" } ] }
```

#### POST /wishlist — Add product

**Request body:** `{ "product_id": 1 }` (required integer exists:products,id). Creates the wishlist if needed, `firstOrCreate`s the item.

**Response:** array of wishlist product ids
```json
{ "data": [1, 4, 7] }
```

#### DELETE /wishlist/{product} — Remove product

Route constrained to numeric `product`. **Response:** array of wishlist product ids (same as above).

### Addresses

#### GET /addresses — List addresses

`AddressController@index` → `AddressResource` collection, ordered default-first then newest.
```json
{ "data": [ { "id": 3, "label": "Home", "full_name": "Jane Doe", "phone": "+855 12 345 678", "address_line1": "123 Main St", "address_line2": "Apt 4B", "city": "Phnom Penh", "state": "PP", "postal_code": "12000", "country": "KH", "is_default": true } ] }
```

#### POST /addresses — Create address

**Request body** (`AddressRequest`)
```json
{
  "label": "Home",
  "full_name": "Jane Doe",
  "phone": "+855 12 345 678",
  "address_line1": "123 Main St",
  "address_line2": "Apt 4B",
  "city": "Phnom Penh",
  "state": "PP",
  "postal_code": "12000",
  "country": "KH",
  "is_default": true
}
```

**Validation:** `label` nullable max:50; `full_name` required max:255; `phone` nullable max:30; `address_line1` required max:255; `address_line2` nullable max:255; `city` required max:120; `state` required max:120; `postal_code` required max:20; `country` nullable max:100; `is_default` nullable boolean.

**Behavior:** the user's first address always becomes the default, whatever `is_default` was sent. Afterwards `is_default: true` moves the flag and unsets the previous default. A supplied `user_id` is ignored, and another user's address id returns `404`. **Response `201`** `AddressResource`.

#### PUT /addresses/{address} — Update address

Same fields as create. Setting `is_default: true` unsets the others. Clearing the flag on the current default promotes the user's most recent remaining address, so exactly one default always exists (none, once the last address is deleted). `AddressResource`.

#### DELETE /addresses/{address} — Delete address

**Response `200`**
```json
{ "data": { "message": "Address deleted." } }
```
If the deleted address was the default, the next remaining address becomes default.

#### POST /addresses/{address}/default — Set as default

`AddressController@setDefault`. Unsets others and sets this one. `AddressResource`.

### Reviews

#### POST /reviews — Create a review (verified purchase required)

`ReviewController@store` → `ReviewService::store()`.

**Request body** (`ReviewStoreRequest`)
```json
{ "product_id": 1, "rating": 5, "title": "Great product", "body": "Highly recommend." }
```

**Validation:** `product_id` required integer exists:products,id; `rating` required integer between:1,5; `title` nullable max:120; `body` nullable max:3000.

**Business rules (enforced):**
1. User must have a **Delivered** order containing the product, else → `422: {"product_id": ["You can only review products you have purchased and received."]}`.
2. Duplicate review for the same product → `422: {"product_id": ["You have already reviewed this product."]}`.
3. New review is created with `status=pending`, `verified=true`.

**Response `201`** `ReviewResource`.

#### PUT /reviews/{review} — Update own review

`ReviewController@update`. Only owner (else 404). Fields `rating`, `title`, `body` optional. `ReviewResource`.

### Account & Profile

#### GET /account/dashboard — Account summary

`AccountController@profile`. Returns counts + user:
```json
{
  "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com", "role": "customer", "avatar": null, "phone": null, "newsletter": false, "email_verified_at": null },
  "orders_count": 5,
  "reviews_count": 2,
  "wishlist_count": 3
}
```

#### PUT /account/profile — Update profile

**Request body** (`UpdateProfileRequest`)
```json
{ "name": "Jane Doe", "phone": "+855 12 345 678", "newsletter": true }
```
**Validation:** `name` required max:255; `phone` nullable max:30; `newsletter` nullable boolean. **Response:** `UserResource`.

#### POST /account/password — Change password

**Request body** (`ChangePasswordRequest`)
```json
{ "current_password": "oldPass1", "password": "newPass1", "password_confirmation": "newPass1" }
```
**Validation:** `current_password` required; `password` required min:8 confirmed; `password_confirmation` required.

**Behavior:** wrong current password → `422: {"current_password": ["The current password is incorrect."]}`. On success revokes all **other** tokens (current session stays).

**Response `200`**
```json
{ "data": { "message": "Password updated successfully." } }
```

#### GET /account/notifications — List notifications

`AccountController@notifications` → latest 50.
```json
{ "data": [ { "id": "8f8b...", "type": "App\\Notifications\\OrderStatusChanged", "title": "Order shipped", "message": "Your order has shipped.", "read_at": null, "created_at": "2026-09-01T12:00:00Z" } ] }
```

#### POST /account/notifications/{notification}/read — Mark notification read

`AccountController@markRead`. Special value `{notification} = 'all'` marks all as read.
```json
{ "data": { "message": "Notification marked as read." } }
```
(Or `"All notifications marked as read."` for `all`.)

---

## 7. Admin (Authenticated + `admin` role)

All routes guarded by `auth:sanctum` **and** the `admin` middleware. Non-admins get `403`.

### Dashboard & Reports

#### GET /admin/dashboard/overview — KPIs

`DashboardController@overview`. Optional query `days` (default 30).

**Response `200`**
```json
{
  "data": {
    "metrics": {
      "total_revenue": 15420.5,
      "orders_count": 120,
      "customers_count": 64,
      "pending_orders": 5,
      "low_stock_products": 3
    },
    "revenue_trend": [
      { "date": "2026-08-03", "revenue": 500.25 }
    ],
    "status_distribution": [
      { "status": "pending", "count": 5 },
      { "status": "confirmed", "count": 12 }
    ],
    "sales_by_category": [
      { "id": 1, "name": "Apparel", "slug": "apparel", "revenue": 8000.0, "order_count": 40 }
    ]
  }
}
```

#### GET /admin/reports/orders.csv — Export orders as CSV

`AdminReportController@ordersCsv` → streaming CSV download. Optional query params `status`, `from` (date), `to` (date).

**CSV columns:** `order_number, placed_at, customer_name, email, status, payment_status, subtotal, discount_amount, tax_amount, shipping_amount, total, coupon_code`

### Products

#### GET /admin/products — List products

`AdminProductController@index`. Eager loads brand/category/images/variants.inventory. Paginated (15).

**Query params:** `q` (searches name/SKU), `category_id`, `brand_id`, `stock_status` (`all|in|out`).

**Response:** `{ "data": [ProductResource], "meta": {...} }`

#### POST /admin/products — Create product (with variants)

`AdminProductController@store` → `AdminProductRequest`.

**Request body**
```json
{
  "name": "Classic Tee",
  "slug": "classic-tee",
  "category_id": 1,
  "brand_id": 1,
  "short_description": "A soft tee",
  "description": "Full description",
  "price": 29.99,
  "compare_at_price": 39.99,
  "sku": "ACME-TEE",
  "weight": 0.2,
  "is_featured": true,
  "is_active": true,
  "variants": [
    {
      "name": "Blue / L",
      "sku": "ACME-TEE-BL-L",
      "price": 29.99,
      "compare_at_price": null,
      "is_active": true,
      "quantity": 50,
      "attributes": [
        { "attribute": "Color", "value": "Blue" },
        { "attribute": "Size", "value": "L" }
      ]
    }
  ]
}
```

**Validation:** `name` required on POST. `slug` is normalised with `Str::slug` and de-duplicated
against the unique index (a repeat becomes `classic-tee-1`); a name that slugifies to nothing gets a
generated `product-<random>` slug. `sku` must be unique across `products` — a clash returns `422`.
`short_description` is capped at 255 and `description` at 60000, matching the column widths. Variant
SKUs must be unique **per branch** (`product_variants_shop_sku_unique`) and no two variants may
share the same attribute combination — both return `422` on `variants`. `images` entries must
already be attached to this product or point at a file that exists under `images/products/` on the
public disk; anything else is rejected with `422` on `images.<index>` instead of being attached or
deleted. The whole write (product, variants, inventory, images) runs in one transaction.

**Response `201`** — `ProductDetailResource`.

#### GET /admin/products/{product} — Product detail

`AdminProductController@show` → `ProductDetailResource` (includes inventory).

#### PUT /admin/products/{product} — Update product

`AdminProductController@update`. If `variants` present, `syncVariants` runs (creates/updates matching
by id or SKU, deletes unreferenced) inside a transaction. SKUs of the variants being rewritten are
parked on temporary values first, so two SKUs can be swapped within one product without tripping the
unique index. Slug de-duplicated if changed. Moving a product to another branch is rejected with
`422` on `shop_id` if the destination already stocks one of its SKUs. `ProductDetailResource`.

#### PATCH /admin/products — Bulk status

`AdminProductController@updateStatus`. Body `{ "ids": [1,2], "is_active": false }`. Authorizes each
product individually, then reports the number of rows actually written in `data.updated`.

#### DELETE /admin/products/{product} — Delete product

**Response `200`** `{ "data": { "message": "Product deleted." } }`

### Categories

#### GET /admin/categories — Category tree

`AdminCategoryController@index` → root categories with `products_count` and nested `children` (recursive, with counts). `CategoryResource` collection. (Note: admin version includes all categories, regardless of `is_active`.)

#### POST /admin/categories — Create

**Request body** (`AdminCategoryRequest`)
```json
{ "parent_id": 1, "name": "New Category", "slug": "new-category", "description": null, "image": null, "is_active": true, "sort_order": 1 }
```
**Validation:** `parent_id` nullable exists; `name` required max:255; slug auto-generated; `image` nullable string; `is_active` nullable boolean; `sort_order` nullable integer. **Response `201`** `CategoryResource`.

#### PUT /admin/categories/{category} — Update

Same fields. `CategoryResource`.

#### DELETE /admin/categories/{category} — Delete

Fails with `422` if it has sub-categories:
```json
{ "message": "Contains sub-categories; delete those first." }
```
Else `{ "data": { "message": "Category deleted." } }`.

### Brands

`AdminBrandController`:
- **GET /admin/brands** — list with `products_count`, ordered by name. `BrandResource` collection.
- **POST /admin/brands** — body (`AdminBrandRequest`): `name` required max:255, `slug` auto, `description` nullable, `logo` nullable, `is_active` nullable. **201** `BrandResource`.
- **PUT /admin/brands/{brand}** — same fields. `BrandResource`.
- **DELETE /admin/brands/{brand}** — `{ "data": { "message": "Brand deleted." } }`.

### Shipping Methods

`AdminShippingMethodController`:
- **GET /admin/shipping-methods** — all methods ordered by price (includes inactive). `ShippingMethodResource` collection.
- **POST /admin/shipping-methods** — (`AdminShippingMethodRequest`): `name` required max:255, `code` required max:50 (auto from name), `description` nullable, `price` required numeric min:0, `estimated_days_min`/`max` nullable integer min:0, `is_active` nullable. **201**.
- **PUT /admin/shipping-methods/{method}** — same fields.
- **DELETE /admin/shipping-methods/{method}** — deleted message.

### Orders (fulfillment pipeline)

#### GET /admin/orders — List all orders

`AdminOrderController@index`. Eager loads `items`, `user`, `items_count`. Paginated (15).

**Query params:** `status` (e.g. `pending`, `confirmed`, ...; `all` default), `q` (search order_number, customer_name, email).

**Response:** `{ "data": [OrderListResource], "meta": {...} }`

#### GET /admin/orders/{order} — Order detail

`AdminOrderController@show` → `OrderResource` (items, payment, shipments).

#### PUT /admin/orders/{order}/transition — Advance state machine

`AdminOrderController@transition` → `OrderService::transition($order, $status)`.

**Request body** (`OrderTransitionRequest`)
```json
{ "status": "processing" }
```
**Validation:** `status` required in:`pending,confirmed,processing,shipped,delivered,cancelled,refunded`. Invalid progression/path is rejected server-side. **Response:** `OrderResource`.

### Coupons

`AdminCouponController`:

- **GET /admin/coupons** — paginated list. `{ "data": [CouponResource], "meta": {...} }`.
- **POST /admin/coupons** — (`AdminCouponRequest`) uppercase code auto-applied.
  ```json
  { "code": "SAVE10", "type": "percentage", "value": 10, "min_order_amount": 50, "max_discount_amount": 20, "usage_limit": 100, "per_user_limit": 1, "starts_at": "2026-09-01", "expires_at": "2026-12-31", "is_active": true }
  ```
  **Validation:** `code` required max:50; `type` required in:percentage,fixed; `value` required numeric min:0; `min_order_amount`/`max_discount_amount` nullable numeric min:0; `usage_limit`/`per_user_limit` nullable integer min:0; `starts_at`/`expires_at` nullable date; `is_active` nullable boolean. **201** `CouponResource`.
- **PUT /admin/coupons/{coupon}** — same fields.
- **DELETE /admin/coupons/{coupon}** — deleted message.

### Review Moderation

`AdminReviewController`:
- **GET /admin/reviews** — list all (incl. pending), eager loads `user`, `product`. Query `status` filter. Paginated. `ReviewResource` collection.
- **POST /admin/reviews/{review}/approve** — `ReviewService::approve()` sets status approved and **recalculates** product `rating_avg`/`rating_count`. `ReviewResource`.
- **POST /admin/reviews/{review}/reject** — sets status rejected. `ReviewResource`.

### Customers

`AdminCustomerController`:
- **GET /admin/customers** — customers only (`role=customer`). Query `q` searches name/email/phone. Paginated. `UserResource` collection.
- **GET /admin/customers/{user}** — detail with order stats:
  ```json
  { "user": { "...": "UserResource" }, "orders_count": 5, "lifetime_spend": 495.5 }
  ```

---

## 8. Enums / Constants (from `App\Models`)

### Order status (`Order`)
`pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled`, `refunded`

Lifecycle progression enforced by `OrderService`:
```
Pending → Confirmed → Processing → Shipped → Delivered
```

### Payment status (`Order`, `Payment`)
- Order `payment_status`: `unpaid`, `paid`, `refunded`, `failed`
- Payment `status`: `pending`, `completed`

### Review status (`Review`)
`pending`, `approved`, `rejected`

### Shipment status (`Shipment`)
`pending` (plus carrier/tracking handling on ship)

### User role (`User`)
`customer`, `admin`

---

## 9. Request/Validation Reference

| Request class | Endpoint(s) | Key rules |
| --- | --- | --- |
| `LoginRequest` | auth/login | email required+email+max:255, password required+string, remember nullable+boolean |
| `RegisterRequest` | auth/register | name required+max:120, email unique+max:255, password min:8+max:255 confirmed, newsletter nullable+boolean |
| `CartAddRequest` | POST /cart | product_variant_id required+exists, quantity 1–99 |
| `CartUpdateRequest` | PUT /cart/items/{id} | quantity required 1–99 |
| `CheckoutRequest` | POST /checkout | shipping_method_id, payment_method in:card,cod, address rules |
| `CouponValidateRequest` | POST /coupons/validate | code, subtotal min:0 |
| `AddressRequest` | /addresses* | address fields + is_default |
| `ReviewStoreRequest` | POST /reviews | product_id, rating 1–5, title max:120, body max:3000 |
| `ReviewUpdateRequest` | PUT /reviews/{id} | optional rating/title/body |
| `UpdateProfileRequest` | PUT /account/profile | name, phone, newsletter |
| `ChangePasswordRequest` | POST /account/password | current_password, password min:8 confirmed |
| `OrderTransitionRequest` | PUT /admin/orders/{id}/transition | status in pending..refunded |
| `AdminProductRequest` | /admin/products | product + variants array |
| `CatalogFilterRequest` | /catalog/products | whitelists and bounds every catalog query parameter |
| `AdminCategoryRequest` | /admin/categories | name, slug, parent, is_active, sort_order |
| `AdminBrandRequest` | /admin/brands | name, slug, description, logo, is_active |
| `AdminShippingMethodRequest` | /admin/shipping-methods | name, code, price, estimated days |
| `AdminCouponRequest` | /admin/coupons | code, type, value, limits, dates |

---

## 10. API Resources (JSON shape providers)

| Resource | Shapes |
| --- | --- |
| `UserResource` | auth, account, admin customers |
| `ProductResource` | catalog listing, featured, wishlist, admin products |
| `ProductDetailResource` | product detail: gallery, attributes, variants |
| `CartResource` / `CartItemResource` | cart + line items |
| `OrderResource` / `OrderListResource` / `OrderItemResource` | order detail/list/line items |
| `AddressResource` | customer addresses |
| `ReviewResource` | public + admin reviews |
| `CouponResource` | coupon validate + admin coupons |
| `CategoryResource` | category tree |
| `BrandResource` | brands |
| `AttributeResource` / `AttributeValueResource` | variant attribute facets |
| `ShippingMethodResource` | shipping methods |

---

## 11. Controller Map

| Controller | Responsibilities |
| --- | --- |
| `AuthController` | register, login, me, logout |
| `CatalogController` | index, show, featured, facets, resolveVariants |
| `CartController` | index, add, update, remove, clear, totals |
| `CheckoutController` | begin, confirm, cancel |
| `OrderController` | customer order list/show/cancel |
| `WishlistController` | index, add, remove |
| `AddressController` | CRUD + default |
| `ReviewController` | public index, store, update |
| `AccountController` | profile, updateProfile, changePassword, notifications, markRead |
| `CouponController` | validate |
| `ShippingMethodController` | public index |
| `AttributeController` | public index |
| `CategoryController` / `BrandController` | public lookup |
| `Admin\DashboardController` | overview |
| `Admin\AdminProductController` | product CRUD + variants |
| `Admin\AdminCategoryController` | category CRUD |
| `Admin\AdminBrandController` | brand CRUD |
| `Admin\AdminShippingMethodController` | shipping CRUD |
| `Admin\AdminOrderController` | orders + transition |
| `Admin\AdminCouponController` | coupon CRUD |
| `Admin\AdminReviewController` | moderation |
| `Admin\AdminCustomerController` | customer list/detail |
| `Admin\AdminReportController` | orders.csv export |
