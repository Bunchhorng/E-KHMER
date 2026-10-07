import apiClient from './client'

export interface ApiBrand {
  id: number
  name: string
  slug: string
  description: string | null
  logo: string | null
  is_active: boolean
}

export const brandsApi = {
  getAll() {
    return apiClient.get<{ data: ApiBrand[] }>('/brands')
  },
  list() {
    return this.getAll()
  }
}
