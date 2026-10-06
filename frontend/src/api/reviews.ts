import apiClient from './client'

export interface ApiReview {
  id: number
  rating: number
  title: string | null
  body: string | null
  verified: boolean
  status: string
  helpful_count: number
  created_at: string
  is_featured: boolean
  user: { id: number; name: string }
  product?: { id: number; name: string; slug: string }
}

export const reviewsApi = {
  getProductReviews(productId: number) {
    return apiClient.get<{ data: ApiReview[] }>(`/products/${productId}/reviews`)
  },

  listMine() {
    return apiClient.get<{ data: ApiReview[] }>('/account/reviews')
  },

  create(payload: { product_id: number; rating: number; title?: string; body?: string }) {
    return apiClient.post<{ data: ApiReview }>('/reviews', payload)
  },

  update(id: number, payload: { rating?: number; title?: string | null; body?: string | null }) {
    return apiClient.put<{ data: ApiReview }>(`/reviews/${id}`, payload)
  },

  remove(id: number) {
    return apiClient.delete(`/reviews/${id}`)
  }
}
