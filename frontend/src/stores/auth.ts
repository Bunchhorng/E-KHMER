import { defineStore } from 'pinia'
import apiClient from '@/api/client'

const TOKEN_KEY = 'access_token'
const REFRESH_TOKEN_KEY = 'refresh_token'
const USER_KEY = 'ekhmer_user'

export interface AuthUser {
  id: number
  name: string
  email: string
  phone?: string | null
  role: 'customer' | 'admin' | 'super_admin'
  managed_shops?: { id: number; name: string; slug: string; role: 'owner' | 'manager' }[]
  avatar?: string | null
  newsletter?: boolean
  email_verified?: boolean
  email_verified_at?: string | null
  created_at?: string | null
}

function loadUser(): AuthUser | null {
  try {
    if (!localStorage.getItem(TOKEN_KEY)) return null
    const raw = localStorage.getItem(USER_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as Partial<AuthUser>
    if (!parsed?.id || !parsed?.email) return null
    return parsed as AuthUser
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: loadUser() as AuthUser | null,
    sessionChecked: false,
    loading: false
  }),

  getters: {
    isAuthenticated: (state): boolean => Boolean(state.user),
    isAdmin: (state): boolean => state.user?.role === 'super_admin',
    isSeller: (state): boolean => Boolean(state.user?.managed_shops?.length),
    token: (): string | null => localStorage.getItem(TOKEN_KEY)
  },

  actions: {
    /**
     * Drop the local session without hitting the API. Used by the 401 response
     * interceptor so a rejected token can never leave a stale in-memory user
     * behind (which would keep isAuthenticated/isAdmin true).
     */
    clearSession() {
      this.user = null
      this.sessionChecked = true
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(REFRESH_TOKEN_KEY)
      localStorage.removeItem(USER_KEY)
    },

    async login(email: string, password: string, remember = false) {
      this.loading = true
      try {
        const { data } = await apiClient.post('/auth/login', { email, password, remember })
        const responseData = data.data ?? data
        localStorage.setItem(TOKEN_KEY, responseData.token ?? responseData.access_token)
        this.user = responseData.user as AuthUser
        this.sessionChecked = true
        localStorage.setItem(USER_KEY, JSON.stringify(this.user))
        return this.user
      } finally {
        this.loading = false
      }
    },

    async register(payload: { name: string; email: string; password: string; password_confirmation?: string; newsletter?: boolean }) {
      this.loading = true
      try {
        const { data } = await apiClient.post('/auth/register', payload)
        const responseData = data.data ?? data
        localStorage.setItem(TOKEN_KEY, responseData.token ?? responseData.access_token)
        this.user = responseData.user as AuthUser
        this.sessionChecked = true
        localStorage.setItem(USER_KEY, JSON.stringify(this.user))
        return this.user
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await apiClient.post('/auth/logout')
      } catch {
        /* ignore network errors on logout */
      }
      this.clearSession()
    },

    async refreshUser() {
      const { data } = await apiClient.get('/auth/me')
      const responseData = data.data ?? data
      this.user = responseData as AuthUser
      this.sessionChecked = true
      localStorage.setItem(USER_KEY, JSON.stringify(this.user))
      return this.user
    },

    async sendVerificationEmail() {
      const { data } = await apiClient.post('/auth/email/verification-notification')
      return (data.data ?? data).message as string
    }
  }
})
