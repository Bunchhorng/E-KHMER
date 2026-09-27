import axios from 'axios'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  timeout: 15000,
  headers: { Accept: 'application/json' }
})

const SESSION_KEY = 'ekhmer_session_id'
const TOKEN_KEY = 'access_token'
const REFRESH_TOKEN_KEY = 'refresh_token'
const USER_KEY = 'ekhmer_user'

/**
 * Emitted when the API rejects our token. The auth store subscribes to this in
 * main.ts so the in-memory session is dropped in lockstep with localStorage —
 * clearing only localStorage would leave `auth.isAuthenticated` / `auth.isAdmin`
 * true and keep admin chrome on screen for an unauthenticated visitor.
 */
export const UNAUTHORIZED_EVENT = 'auth:unauthorized'

function getSessionId(): string {
  let sid = localStorage.getItem(SESSION_KEY)
  if (!sid) {
    sid = typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : `sess-${Date.now()}-${Math.random().toString(36).slice(2)}`
    localStorage.setItem(SESSION_KEY, sid)
  }
  return sid
}

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  config.headers['X-Session-Id'] = getSessionId()
  return config
})

let redirecting = false

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(REFRESH_TOKEN_KEY)
      localStorage.removeItem(USER_KEY)

      window.dispatchEvent(new CustomEvent(UNAUTHORIZED_EVENT))

      // Bounce to login unless we are already on a guest-only auth screen, and
      // preserve the attempted path so the user lands back where they were.
      if (!redirecting && typeof window !== 'undefined' && !window.location.pathname.startsWith('/auth')) {
        redirecting = true
        const redirect = encodeURIComponent(window.location.pathname + window.location.search)
        window.location.assign(`/auth/login?redirect=${redirect}`)
      }
    }

    return Promise.reject(error)
  }
)

export default apiClient
