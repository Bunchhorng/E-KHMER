<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShippingMethodController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\Admin\AdminBrandController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminCouponController;
use App\Http\Controllers\Api\Admin\AdminCustomerController;
use App\Http\Controllers\Api\Admin\AdminInventoryController;
use App\Http\Controllers\Api\Admin\AdminMediaController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminShippingMethodController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminShipmentController;
use App\Http\Controllers\Api\Admin\AdminShopController;
use App\Http\Controllers\Api\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Throttled because these are the endpoints worth brute forcing or flooding:
// credential stuffing on login, and abuse of the mail-sending reset/verify
// routes. 429 responses carry Retry-After / X-RateLimit-* headers.
Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware('throttle:3,1');
});

Route::prefix('catalog')->group(function () {
    Route::get('products', [CatalogController::class, 'index'])->middleware('throttle:120,1');
    Route::get('featured', [CatalogController::class, 'featured'])->middleware('throttle:120,1');
    Route::get('facets', [CatalogController::class, 'facets'])->middleware('throttle:120,1');
    Route::post('variants/resolve', [CatalogController::class, 'resolveVariants'])->middleware('throttle:120,1');
    Route::get('products/{slug}', [CatalogController::class, 'show'])
        ->where('slug', '[A-Za-z0-9-]+')
        ->middleware('throttle:120,1');
});

Route::get('categories', [CategoryController::class, 'index']);
Route::get('brands', [BrandController::class, 'index']);
Route::get('attributes', [AttributeController::class, 'index']);
Route::get('shipping-methods', [ShippingMethodController::class, 'index']);
Route::get('products/{product}/reviews', [ReviewController::class, 'index'])
    ->whereNumber('product');
Route::post('coupons/validate', [CouponController::class, 'validate']);

// Public branch (shop) pages
Route::prefix('shops')->group(function () {
    Route::get('/', [ShopController::class, 'index']);
    Route::get('{shop:slug}', [ShopController::class, 'show']);
    Route::get('{shop:slug}/products', [ShopController::class, 'products']);
});

Route::get('orders/guest/{orderNumber}', [OrderController::class, 'guest'])
    ->where('orderNumber', '[A-Za-z0-9-]+');

Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/', [CartController::class, 'add']);
    Route::put('/items/{cartItem}', [CartController::class, 'update'])->whereNumber('cartItem');
    Route::delete('/items/{cartItem}', [CartController::class, 'remove'])->whereNumber('cartItem');
    Route::delete('/', [CartController::class, 'clear']);
    Route::get('/totals', [CartController::class, 'totals']);
});

Route::prefix('checkout')->group(function () {
    Route::post('/', [CheckoutController::class, 'begin']);
    Route::post('{orderNumber}/confirm', [CheckoutController::class, 'confirm'])
        ->where('orderNumber', '[A-Za-z0-9-]+');
    Route::post('{orderNumber}/cancel', [CheckoutController::class, 'cancel'])
        ->where('orderNumber', '[A-Za-z0-9-]+');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{orderNumber}', [OrderController::class, 'show'])
        ->where('orderNumber', '[A-Za-z0-9-]+');
    Route::get('orders/{orderNumber}/receipt', [OrderController::class, 'receipt'])
        ->where('orderNumber', '[A-Za-z0-9-]+');
    Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])
        ->where('orderNumber', '[A-Za-z0-9-]+');

    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist', [WishlistController::class, 'add']);
    Route::delete('wishlist/{product}', [WishlistController::class, 'remove'])->whereNumber('product');

    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::put('addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
    Route::post('addresses/{address}/default', [AddressController::class, 'setDefault']);

    Route::post('reviews', [ReviewController::class, 'store']);
    Route::put('reviews/{review}', [ReviewController::class, 'update']);

    Route::get('account/dashboard', [AccountController::class, 'profile']);
    Route::put('account/profile', [AccountController::class, 'updateProfile']);
    Route::post('account/avatar', [AccountController::class, 'updateAvatar']);
    Route::post('account/password', [AccountController::class, 'changePassword']);
    Route::get('account/reviews', [AccountController::class, 'reviews']);
    Route::get('account/notifications', [AccountController::class, 'notifications']);
    Route::post('account/notifications/{notification}/read', [AccountController::class, 'markRead']);
});

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::get('dashboard/overview', [DashboardController::class, 'overview']);

        Route::get('shops', [AdminShopController::class, 'index']);
        Route::post('shops', [AdminShopController::class, 'store']);
        Route::get('shops/{shop}', [AdminShopController::class, 'show']);
        Route::put('shops/{shop}', [AdminShopController::class, 'update']);
        Route::patch('shops/{shop}/status', [AdminShopController::class, 'updateStatus']);
        Route::delete('shops/{shop}', [AdminShopController::class, 'destroy']);

        Route::get('products', [AdminProductController::class, 'index']);
        Route::post('products', [AdminProductController::class, 'store']);
        Route::get('products/{product}', [AdminProductController::class, 'show']);
        Route::put('products/{product}', [AdminProductController::class, 'update']);
        Route::delete('products/{product}', [AdminProductController::class, 'destroy']);
        Route::patch('products', [AdminProductController::class, 'updateStatus']);
        Route::post('products/{product}/restore', [AdminProductController::class, 'restore']);
        Route::post('products/{product}/images', [AdminMediaController::class, 'storeProductImage']);
        Route::delete('products/{product}/images/{image}', [AdminMediaController::class, 'destroyProductImage']);

        Route::post('uploads/image', [AdminMediaController::class, 'uploadImage']);

        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);
        Route::post('categories/{category}/image', [AdminMediaController::class, 'uploadCategoryImage']);

        Route::get('brands', [AdminBrandController::class, 'index']);
        Route::post('brands', [AdminBrandController::class, 'store']);
        Route::put('brands/{brand}', [AdminBrandController::class, 'update']);
        Route::delete('brands/{brand}', [AdminBrandController::class, 'destroy']);
        Route::post('brands/{brand}/logo', [AdminMediaController::class, 'uploadBrandLogo']);

        Route::get('shipping-methods', [AdminShippingMethodController::class, 'index']);
        Route::post('shipping-methods', [AdminShippingMethodController::class, 'store']);
        Route::put('shipping-methods/{method}', [AdminShippingMethodController::class, 'update']);
        Route::delete('shipping-methods/{method}', [AdminShippingMethodController::class, 'destroy']);

        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show']);
        Route::get('orders/{order}/receipt', [AdminOrderController::class, 'receipt']);
        Route::put('orders/{order}/transition', [AdminOrderController::class, 'transition']);

        Route::get('inventory', [AdminInventoryController::class, 'index']);
        Route::get('inventory/{inventory}/transactions', [AdminInventoryController::class, 'transactions'])
            ->whereNumber('inventory');

        Route::get('coupons', [AdminCouponController::class, 'index']);
        Route::post('coupons', [AdminCouponController::class, 'store']);
        Route::put('coupons/{coupon}', [AdminCouponController::class, 'update']);
        Route::delete('coupons/{coupon}', [AdminCouponController::class, 'destroy']);

        Route::get('reviews', [AdminReviewController::class, 'index']);
        Route::post('reviews/{review}/approve', [AdminReviewController::class, 'approve']);
        Route::post('reviews/{review}/reject', [AdminReviewController::class, 'reject']);
        Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy']);

        Route::get('settings', [SettingsController::class, 'show']);
        Route::put('settings', [SettingsController::class, 'update']);

        Route::get('customers', [AdminCustomerController::class, 'index']);
        Route::get('customers/{user}', [AdminCustomerController::class, 'show']);

        Route::get('reports/orders.csv', [AdminReportController::class, 'ordersCsv']);
        Route::get('reports/orders.pdf', [AdminReportController::class, 'ordersPdf']);
        Route::get('reports/products.csv', [AdminReportController::class, 'productsCsv']);
        Route::get('reports/payments.csv', [AdminReportController::class, 'paymentsCsv']);
        Route::get('reports/summary', [AdminReportController::class, 'summary']);

        Route::get('payments', [AdminPaymentController::class, 'index']);
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show']);

        Route::get('shipments', [AdminShipmentController::class, 'index']);
        Route::get('shipments/{shipment}', [AdminShipmentController::class, 'show']);
        Route::put('shipments/{shipment}', [AdminShipmentController::class, 'update']);

        Route::get('notifications', [AdminNotificationController::class, 'index']);
        Route::get('notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
        Route::post('notifications/{notification}/read', [AdminNotificationController::class, 'markRead']);
        Route::delete('notifications/{notification}', [AdminNotificationController::class, 'destroy']);
    });
