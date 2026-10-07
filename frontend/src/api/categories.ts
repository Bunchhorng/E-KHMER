import apiClient from './client'

export interface ApiCategory {
  id: number
  name: string
  slug: string
  description: string | null
  image: string | null
  sort_order: number
  is_active: boolean
  parent_id: number | null
  children?: ApiCategory[]
}

export const categoriesApi = {
  getTree() {
    return apiClient.get<{ data: ApiCategory[] }>('/categories')
  },
  list() {
    return this.getTree()
  }
}
