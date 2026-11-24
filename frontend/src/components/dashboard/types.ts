import type { Component } from 'vue'
import type { RouteLocationRaw } from 'vue-router'

export interface DashboardNavItem {
  id: string
  label: string
  to: RouteLocationRaw
  icon: Component
  active: boolean
}

export interface DashboardNavGroup {
  id?: string
  label?: string
  icon?: Component
  items: DashboardNavItem[]
}

export type DashboardTone = 'blue' | 'violet' | 'amber' | 'green' | 'rose'
