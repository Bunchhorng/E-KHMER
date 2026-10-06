import apiClient from './client'

export interface AccountDashboard {
  user: {
    id: number
    name: string
    email: string
    role: string
    avatar: string | null
    phone: string | null
    newsletter: boolean
    email_verified_at: string | null
  }
  orders_count: number
  reviews_count: number
  wishlist_count: number
}

export interface ApiNotification {
  id: string
  type: string
  title: string
  message: string
  read_at: string | null
  created_at: string
}

export const accountApi = {
  getDashboard() {
    return apiClient.get<AccountDashboard>('/account/dashboard')
  },

  updateProfile(payload: { name?: string; phone?: string; newsletter?: boolean }) {
    return apiClient.put<AccountDashboard['user']>('/account/profile', payload)
  },

  uploadAvatar(file: File) {
    const formData = new FormData()
    formData.append('image', file)
    return apiClient.post<AccountDashboard['user']>('/account/avatar', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  changePassword(payload: { current_password: string; password: string; password_confirmation: string }) {
    return apiClient.post<{ data: { message: string } }>('/account/password', payload)
  },

  getNotifications() {
    return apiClient.get<{ data: ApiNotification[] }>('/account/notifications')
  },

  markNotificationRead(id: string) {
    return apiClient.post<{ data: { message: string } }>(`/account/notifications/${id}/read`)
  },

  markAllNotificationsRead() {
    return apiClient.post<{ data: { message: string } }>('/account/notifications/all/read')
  },

  deleteNotification(id: string) {
    return apiClient.delete(`/account/notifications/${id}`)
  }
}
