import { createRouter, createWebHistory } from 'vue-router'
import StoreLayout from '@/layouts/StoreLayout.vue'
import SellerLayout from '@/layouts/SellerLayout.vue'
import AccountLayout from '@/layouts/AccountLayout.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    requiresAuth?: boolean
    admin?: boolean
    guestOnly?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(_to, _from, savedPosition) {
    return savedPosition ?? { top: 0 }
  },
  routes: [
    {
      path: '/',
      component: StoreLayout,
      children: [
        { path: '', name: 'home', component: () => import('@/views/HomeView.vue'), meta: { title: 'Home' } },
        { path: 'shop', name: 'shop', component: () => import('@/views/ShopView.vue'), meta: { title: 'Shop' } },
        { path: 'product/:slug', name: 'product-detail', component: () => import('@/views/ProductDetailView.vue'), meta: { title: 'Product' } },
        { path: 'cart', name: 'cart', component: () => import('@/views/CartView.vue'), meta: { title: 'Cart' } },
        { path: 'checkout', name: 'checkout', component: () => import('@/views/CheckoutView.vue'), meta: { title: 'Checkout' } },
        { path: 'order/success/:orderId', name: 'order-success', component: () => import('@/views/OrderSuccessView.vue'), meta: { title: 'Order Confirmed' } },
        { path: 'order/tracking/:orderId', name: 'order-tracking', component: () => import('@/views/OrderTrackingView.vue'), meta: { title: 'Track Order' } }
      ]
    },
    {
      path: '/seller',
      component: SellerLayout,
      meta: { requiresAuth: true },
      children: [
        { path: 'application', name: 'seller-application', component: () => import('@/views/seller/SellerApplicationView.vue'), meta: { title: 'Become a Seller', requiresAuth: true } },
        { path: '', name: 'seller-dashboard', component: () => import('@/views/seller/SellerDashboardView.vue'), meta: { title: 'Seller Center', requiresAuth: true } },
        { path: 'shops/:id', name: 'seller-shop-dashboard', component: () => import('@/views/seller/SellerDashboardView.vue'), meta: { title: 'Shop Dashboard', requiresAuth: true } },
        { path: 'shops/:id/products', name: 'seller-shop-products', component: () => import('@/views/seller/SellerProductsView.vue'), meta: { title: 'Shop Products', requiresAuth: true } },
        { path: 'shops/:id/products/new', name: 'seller-shop-product-create', component: () => import('@/views/seller/SellerProductFormView.vue'), meta: { title: 'Add Shop Product', requiresAuth: true } },
        { path: 'shops/:id/products/:productId/edit', name: 'seller-shop-product-edit', component: () => import('@/views/seller/SellerProductFormView.vue'), meta: { title: 'Edit Shop Product', requiresAuth: true } },
        { path: 'shops/:id/inventory', name: 'seller-shop-inventory', component: () => import('@/views/seller/SellerInventoryView.vue'), meta: { title: 'Shop Inventory', requiresAuth: true } },
        { path: 'shops/:id/orders', name: 'seller-shop-orders', component: () => import('@/views/seller/SellerOrdersView.vue'), meta: { title: 'Shop Orders', requiresAuth: true } },
        { path: 'shops/:id/orders/:orderId', name: 'seller-shop-order-detail', component: () => import('@/views/seller/SellerOrderDetailView.vue'), meta: { title: 'Shop Order', requiresAuth: true } },
      ]
    },
    {
      path: '/account',
      component: AccountLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'account-dashboard', component: () => import('@/views/account/DashboardView.vue'), meta: { title: 'Dashboard' } },
        { path: 'orders', name: 'account-orders', component: () => import('@/views/account/OrdersView.vue'), meta: { title: 'My Orders' } },
        { path: 'orders/:orderNumber', name: 'account-order-detail', component: () => import('@/views/account/OrderDetailView.vue'), meta: { title: 'Order Detail' } },
        { path: 'wishlist', name: 'account-wishlist', component: () => import('@/views/account/WishlistView.vue'), meta: { title: 'Wishlist' } },
        { path: 'addresses', name: 'account-addresses', component: () => import('@/views/account/AddressesView.vue'), meta: { title: 'Addresses' } },
        { path: 'addresses/new', name: 'account-address-create', component: () => import('@/views/account/AddressFormView.vue'), meta: { title: 'Add Address' } },
        { path: 'addresses/:id/edit', name: 'account-address-edit', component: () => import('@/views/account/AddressFormView.vue'), meta: { title: 'Edit Address' } },
        { path: 'profile', name: 'account-profile', component: () => import('@/views/account/ProfileView.vue'), meta: { title: 'Profile' } },
        { path: 'notifications', name: 'account-notifications', component: () => import('@/views/account/NotificationsView.vue'), meta: { title: 'Notifications' } },
        { path: 'reviews', name: 'account-reviews', component: () => import('@/views/account/ReviewsView.vue'), meta: { title: 'My Reviews' } },
        { path: 'password', name: 'account-password', component: () => import('@/views/account/ChangePasswordView.vue'), meta: { title: 'Change Password' } }
      ]
    },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { requiresAuth: true, admin: true },
      redirect: { name: 'admin-dashboard' },
      children: [
        { path: 'dashboard', name: 'admin-dashboard', component: () => import('@/views/admin/AdminDashboardView.vue'), meta: { title: 'Dashboard' } },
        { path: 'shops', name: 'admin-shops', component: () => import('@/views/admin/AdminShopsView.vue'), meta: { title: 'Marketplace Shops' } },
        { path: 'shop-owners', name: 'admin-shop-owners', component: () => import('@/views/admin/AdminShopMembersView.vue'), props: { role: 'owner' }, meta: { title: 'Shop Owners' } },
        { path: 'shop-owners/new', name: 'admin-shop-owner-create', component: () => import('@/views/admin/AdminShopMemberFormView.vue'), props: { role: 'owner' }, meta: { title: 'Create Shop Owner' } },
        { path: 'shop-owners/:id/edit', name: 'admin-shop-owner-edit', component: () => import('@/views/admin/AdminShopMemberFormView.vue'), props: (route) => ({ role: 'owner', memberId: Number(route.params.id) }), meta: { title: 'Edit Shop Owner' } },
        { path: 'shop-admins', name: 'admin-shop-admins', component: () => import('@/views/admin/AdminShopMembersView.vue'), props: { role: 'manager' }, meta: { title: 'Shop Admins' } },
        { path: 'shop-admins/new', name: 'admin-shop-admin-create', component: () => import('@/views/admin/AdminShopMemberFormView.vue'), props: { role: 'manager' }, meta: { title: 'Create Shop Admin' } },
        { path: 'shop-admins/:id/edit', name: 'admin-shop-admin-edit', component: () => import('@/views/admin/AdminShopMemberFormView.vue'), props: (route) => ({ role: 'manager', memberId: Number(route.params.id) }), meta: { title: 'Edit Shop Admin' } },
        { path: 'shops/new', name: 'admin-shop-create', component: () => import('@/views/admin/AdminShopFormView.vue'), meta: { title: 'Create Shop' } },
        { path: 'shops/:id/edit', name: 'admin-shop-edit', component: () => import('@/views/admin/AdminShopFormView.vue'), meta: { title: 'Edit Shop' } },
        { path: 'products', name: 'admin-products', component: () => import('@/views/admin/AdminProductsView.vue'), meta: { title: 'Products' } },
        { path: 'products/new', name: 'admin-product-create', component: () => import('@/views/admin/AddProductView.vue'), meta: { title: 'Add Product' } },
        { path: 'products/:id/edit', name: 'admin-product-edit', component: () => import('@/views/admin/AddProductView.vue'), meta: { title: 'Edit Product' } },
        { path: 'orders', name: 'admin-orders', component: () => import('@/views/admin/AdminOrdersView.vue'), meta: { title: 'Orders' } },
        { path: 'orders/:id', name: 'admin-order-detail', component: () => import('@/views/admin/AdminOrderDetailView.vue'), meta: { title: 'Order Detail' } },
        { path: 'inventory', name: 'admin-inventory', component: () => import('@/views/admin/AdminInventoryView.vue'), meta: { title: 'Inventory' } },
        { path: 'reports', name: 'admin-reports', component: () => import('@/views/admin/AdminReportsView.vue'), meta: { title: 'Reports' } },
        { path: 'payments', name: 'admin-payments', component: () => import('@/views/admin/AdminPaymentsView.vue'), meta: { title: 'Payments' } },
        { path: 'shipments', name: 'admin-shipments', component: () => import('@/views/admin/AdminShipmentsView.vue'), meta: { title: 'Shipments' } },
        { path: 'notifications', name: 'admin-notifications', component: () => import('@/views/admin/AdminNotificationsView.vue'), meta: { title: 'Notifications' } },
        { path: 'customers', name: 'admin-customers', component: () => import('@/views/admin/AdminCustomersView.vue'), meta: { title: 'Customers' } },
        { path: 'customers/:id', name: 'admin-customer-detail', component: () => import('@/views/admin/AdminCustomerDetailView.vue'), meta: { title: 'Customer Detail' } },
        { path: 'settings', name: 'admin-settings', component: () => import('@/views/admin/AdminSettingsView.vue'), meta: { title: 'Settings' } },
        { path: 'coupons', name: 'admin-coupons', component: () => import('@/views/admin/AdminCouponsView.vue'), meta: { title: 'Coupons' } },
        { path: 'coupons/new', name: 'admin-coupon-create', component: () => import('@/views/admin/CouponFormView.vue'), meta: { title: 'Add Coupon' } },
        { path: 'coupons/:id/edit', name: 'admin-coupon-edit', component: () => import('@/views/admin/CouponFormView.vue'), meta: { title: 'Edit Coupon' } },
        { path: 'reviews', name: 'admin-reviews', component: () => import('@/views/admin/AdminReviewsView.vue'), meta: { title: 'Reviews' } },
        { path: 'categories', name: 'admin-categories', component: () => import('@/views/admin/AdminCategoriesView.vue'), meta: { title: 'Categories' } },
        { path: 'categories/new', name: 'admin-category-create', component: () => import('@/views/admin/CategoryFormView.vue'), meta: { title: 'Add Category' } },
        { path: 'categories/:id/edit', name: 'admin-category-edit', component: () => import('@/views/admin/CategoryFormView.vue'), meta: { title: 'Edit Category' } },
        { path: 'brands', name: 'admin-brands', component: () => import('@/views/admin/AdminBrandsView.vue'), meta: { title: 'Brands' } },
        { path: 'brands/new', name: 'admin-brand-create', component: () => import('@/views/admin/BrandFormView.vue'), meta: { title: 'Add Brand' } },
        { path: 'brands/:id/edit', name: 'admin-brand-edit', component: () => import('@/views/admin/BrandFormView.vue'), meta: { title: 'Edit Brand' } },
        { path: 'shipping', name: 'admin-shipping', component: () => import('@/views/admin/AdminShippingMethodsView.vue'), meta: { title: 'Shipping Methods' } },
        { path: 'shipping/new', name: 'admin-shipping-create', component: () => import('@/views/admin/ShippingMethodFormView.vue'), meta: { title: 'Add Shipping Method' } },
        { path: 'shipping/:id/edit', name: 'admin-shipping-edit', component: () => import('@/views/admin/ShippingMethodFormView.vue'), meta: { title: 'Edit Shipping Method' } }
      ]
    },
    {
      path: '/auth',
      component: AuthLayout,
      meta: { guestOnly: true },
      children: [
        { path: 'login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { title: 'Sign In' } },
        { path: 'register', name: 'register', component: () => import('@/views/auth/RegisterView.vue'), meta: { title: 'Create Account' } },
        { path: 'forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPasswordView.vue'), meta: { title: 'Reset Password' } },
        { path: 'reset-password', name: 'reset-password', component: () => import('@/views/auth/ResetPasswordView.vue'), meta: { title: 'Reset Password', guestOnly: false } },
        { path: 'verify-email', name: 'verify-email', component: () => import('@/views/auth/VerifyEmailView.vue'), meta: { title: 'Verify Email', guestOnly: false } }
      ]
    },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { title: 'Page Not Found' } }
  ]
})

router.beforeEach(async (to) => {
  document.title = to.meta.title ? `${to.meta.title} · E-KHMER` : 'E-KHMER'

  const auth = useAuthStore()

  if (!auth.sessionChecked && auth.token) {
    try {
      await auth.refreshUser()
    } catch {
      if (!auth.token) auth.clearSession()
    } finally {
      auth.sessionChecked = true
    }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.admin && !auth.isAdmin) {
    return { name: 'home' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' }
  }

  return true
})

export default router
