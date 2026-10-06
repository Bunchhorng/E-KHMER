# E-KHMER Role Route Map

Base URL for local frontend development: `http://localhost:5174`

## Super Admin

The current global `admin` role controls Super Admin access.

| Page | Route | Status |
| --- | --- | --- |
| Admin area | `/admin` | Available |
| Dashboard | `/admin/dashboard` | Available |
| Products | `/admin/products` | Available |
| Add product | `/admin/products/new` | Available |
| Categories | `/admin/categories` | Available |
| Brands | `/admin/brands` | Available |
| Shops | `/admin/shops` | Available |
| Orders | `/admin/orders` | Available |
| Inventory | `/admin/inventory` | Available |
| Payments | `/admin/payments` | Available |
| Shipments | `/admin/shipments` | Available |
| Shipping methods | `/admin/shipping` | Available |
| Coupons | `/admin/coupons` | Available |
| Customers | `/admin/customers` | Available |
| Reviews | `/admin/reviews` | Available |
| Notifications | `/admin/notifications` | Available |
| Reports | `/admin/reports` | Available |
| Settings | `/admin/settings` | Available |

## Customer

| Page | Route | Status |
| --- | --- | --- |
| Home | `/` | Available |
| Product catalog | `/shop` | Available |
| Product detail | `/product/{slug}` | Available |
| Cart | `/cart` | Available |
| Checkout | `/checkout` | Available |
| Order confirmation | `/order/success/{orderNumber}` | Available |
| Order tracking | `/order/tracking/{orderNumber}` | Available |
| Sign in | `/auth/login` | Available |
| Register | `/auth/register` | Available |
| Account dashboard | `/account` | Requires sign-in |
| My orders | `/account/orders` | Requires sign-in |
| Wishlist | `/account/wishlist` | Requires sign-in |
| Addresses | `/account/addresses` | Requires sign-in |
| Profile | `/account/profile` | Requires sign-in |
| Notifications | `/account/notifications` | Requires sign-in |
| Reviews | `/account/reviews` | Requires sign-in |

## Public Shops

The backend shop APIs are available. These frontend pages are the next marketplace UI work.

| Page | Route | Status |
| --- | --- | --- |
| Shop directory | `/shops` | Planned frontend route |
| Shop storefront | `/shops/{shopSlug}` | Planned frontend route |

## Shop Owner and Shop Admin

Shop membership is already stored in `shop_users` with roles such as `owner`, `manager`, and `staff`. Dedicated seller pages and route guards still need implementation.

| Page | Route | Status |
| --- | --- | --- |
| Seller dashboard | `/seller` | Planned |
| My products | `/seller/products` | Planned |
| Product editor | `/seller/products/new` | Planned |
| Shop orders | `/seller/orders` | Planned |
| Inventory | `/seller/inventory` | Planned |
| Shipments | `/seller/shipments` | Planned |
| Coupons | `/seller/coupons` | Planned |
| Reviews | `/seller/reviews` | Planned |
| Staff management | `/seller/staff` | Planned |
| Shop settings | `/seller/settings` | Planned |

## Access rules

- `/admin/*` requires authenticated global admin access.
- `/account/*` requires customer authentication.
- Future `/seller/*` routes must require an active `shop_users` membership and scope all data to the authorized shop.
- Public shop pages must expose active shops and active products only.
