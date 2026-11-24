import type { Component } from 'vue'
import {
  Bell,
  Boxes,
  ChartNoAxesCombined,
  CreditCard,
  FileBarChart,
  FolderTree,
  LayoutDashboard,
  MessageSquare,
  PackageCheck,
  Settings,
  Ship,
  ShoppingCart,
  Store,
  Tags,
  TicketPercent,
  Truck,
  Users,
  Warehouse
} from 'lucide-vue-next'

export const adminNavGroupIds = ['overview', 'catalog', 'fulfillment', 'marketing', 'community', 'system'] as const
export type AdminNavGroupId = (typeof adminNavGroupIds)[number]

export const adminNavIconKeys = [
  'dashboard',
  'analytics',
  'store',
  'users',
  'products',
  'categories',
  'brands',
  'orders',
  'inventory',
  'payments',
  'shipments',
  'reports',
  'returns',
  'support',
  'shipping',
  'coupons',
  'reviews',
  'notifications',
  'settings'
] as const
export type AdminNavIconKey = (typeof adminNavIconKeys)[number]

export interface AdminNavRouteMeta {
  group: AdminNavGroupId
  /** A visible top-level navigation item when labelKey and icon are provided. */
  labelKey?: string
  icon?: AdminNavIconKey
  order?: number
  /** The visible top-level route that represents this nested route. */
  parent?: string
}

export interface AdminNavGroupDefinition {
  id: AdminNavGroupId
  titleKey: string
  icon: Component
}

export const adminNavGroups: AdminNavGroupDefinition[] = [
  { id: 'overview', titleKey: 'admin.nav.group_overview', icon: LayoutDashboard },
  { id: 'catalog', titleKey: 'admin.nav.group_catalog', icon: Boxes },
  { id: 'fulfillment', titleKey: 'admin.nav.group_fulfillment', icon: ShoppingCart },
  { id: 'marketing', titleKey: 'admin.nav.group_marketing', icon: TicketPercent },
  { id: 'community', titleKey: 'admin.nav.group_community', icon: Users },
  { id: 'system', titleKey: 'admin.nav.group_system', icon: Settings }
]

export const adminNavIcons: Record<AdminNavIconKey, Component> = {
  dashboard: LayoutDashboard,
  analytics: ChartNoAxesCombined,
  store: Store,
  users: Users,
  products: Boxes,
  categories: FolderTree,
  brands: Tags,
  orders: ShoppingCart,
  inventory: Warehouse,
  payments: CreditCard,
  shipments: Ship,
  reports: FileBarChart,
  returns: PackageCheck,
  support: MessageSquare,
  shipping: Truck,
  coupons: TicketPercent,
  reviews: MessageSquare,
  notifications: Bell,
  settings: Settings
}
