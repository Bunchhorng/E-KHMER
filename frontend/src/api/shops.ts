import apiClient from './client'
import type { CatalogProduct, PaginatedResponse } from './catalog'

export interface PublicShop {
  id: number; name: string; slug: string; description: string | null
  logo: string | null; banner: string | null; phone: string | null; email: string | null
  address_line: string | null; city: string | null; province: string | null; country: string
  products_count: number; rating_avg: number; rating_count: number
}
export const shopsApi = {
  list(params: { q?: string; page?: number } = {}) { return apiClient.get<PaginatedResponse<PublicShop>>('/shops', { params }) },
  get(slug: string) { return apiClient.get<{ data: PublicShop }>(`/shops/${encodeURIComponent(slug)}`) },
  products(slug: string, params: Record<string, string | number | undefined>) { return apiClient.get<PaginatedResponse<CatalogProduct>>(`/shops/${encodeURIComponent(slug)}/products`, { params }) }
}

