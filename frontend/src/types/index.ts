export interface Category {
  id: string
  name: string
  slug: string
  icon?: string
  image?: string
  children?: Category[]
}

export interface Brand {
  id: string
  name: string
  slug?: string
}

export interface ProductImage {
  id: string
  url: string
  alt?: string
}

export interface ProductVariantAttribute {
  name: string
  value: string
}

export interface ProductVariant {
  id: string
  sku: string
  attributes: ProductVariantAttribute[]
  price: number
  compareAtPrice: number | null
  stockQuantity: number
  isInStock: boolean
  imageId: string | null
}

export interface Specification {
  label: string
  value: string
}

export interface Product {
  id: string
  slug: string
  title: string
  brand: Brand
  category: Category
  sku: string
  description: string
  specifications: Specification[]
  price: number
  compareAtPrice: number | null
  discountPercent: number | null
  rating: number
  reviewCount: number
  images: ProductImage[]
  variants: ProductVariant[]
  stockQuantity: number
  isInStock: boolean
  isNew: boolean
  isBestSeller: boolean
  isFeatured: boolean
  colors: string[]
  sizes: string[]
}

export interface CartVariantSelection {
  variantId: string
  attributes: ProductVariantAttribute[]
  sku: string
}

export interface CartItem {
  shopId?: number | null
  shopName?: string | null
  id: string
  productId: string
  slug: string
  title: string
  brand: string
  image: string
  unitPrice: number
  quantity: number
  variant: CartVariantSelection | null
  variantName?: string
}

export interface Coupon {
  id: string
  code: string
  type: 'percentage' | 'fixed'
  value: number
  minOrderAmount: number
  description: string
}

export interface Address {
  id: string
  label: string
  fullName: string
  line1: string
  line2?: string
  city: string
  state: string
  postalCode: string
  country: string
  phone: string
  isDefault: boolean
}

export interface ShippingMethod {
  id: string
  name: string
  description: string
  etaDays: number
  price: number
}

export type PaymentMethod = 'cod' | 'card' | 'bank' | 'gateway' | 'bakong'

export interface OrderItem {
  id: string
  productId: string
  title: string
  brand: string
  image: string
  unitPrice: number
  quantity: number
  variant: CartVariantSelection | null
}

export type OrderStatus =
  | 'Pending'
  | 'Confirmed'
  | 'Processing'
  | 'Shipped'
  | 'Delivered'

export interface TrackingEvent {
  status: OrderStatus
  at: string
}

export interface Order {
  id: string
  number: string
  items: OrderItem[]
  subtotal: number
  discount: number
  shipping: number
  tax: number
  total: number
  status: OrderStatus
  placedAt: string
  estimatedDelivery: string
  trackingEvents: TrackingEvent[]
  shippingAddress: Address
  paymentMethod: PaymentMethod
}

export type ReviewStatus = 'approved' | 'pending' | 'rejected'

export interface Review {
  id: string
  productId: string
  author: string
  rating: number
  title: string
  body: string
  date: string
  verified: boolean
  status: ReviewStatus
}

export interface Testimonial {
  id: string
  name: string
  role: string
  avatar: string
  quote: string
  rating: number
}

export type NotificationType = 'order' | 'promo' | 'system'

export interface AppNotification {
  id: string
  type: NotificationType
  title: string
  message: string
  date: string
  read: boolean
}

/* ---------- Admin / table ---------- */
export type TableColumnType =
  | 'text'
  | 'number'
  | 'status'
  | 'badge'
  | 'currency'
  | 'date'
  | 'image'
  | 'actions'

export interface TableColumn {
  key: string
  label: string
  type?: TableColumnType
  sortable?: boolean
  width?: string
}

export type TableRow = Record<string, unknown>

export interface Customer {
  id: string
  name: string
  email: string
  phone?: string
  orders: number
  totalSpent: number
  status: 'active' | 'inactive'
  joinedAt: string
  avatar?: string
}

export interface AdminCoupon {
  id: string
  code: string
  type: 'percentage' | 'fixed'
  value: number
  minOrderAmount: number
  usageLimit: number
  usedCount: number
  expiresAt: string
  status: 'active' | 'expired' | 'draft'
}

export interface TimerangePoint {
  label: string
  revenue: number
  orders: number
}

export interface CategorySales {
  category: string
  sales: number
}

export interface OrderStatusCount {
  status: OrderStatus | 'Cancelled'
  count: number
}
