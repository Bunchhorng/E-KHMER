import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import i18n from '@/plugins/i18n'
import { useThemeStore } from './stores/theme'
import { useLocaleStore } from './stores/locale'
import { useAuthStore } from './stores/auth'
import { UNAUTHORIZED_EVENT } from '@/api/client'
import './style.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(i18n)

useThemeStore(pinia).initialize()
useLocaleStore(pinia).initialize()

// Keep the in-memory session in step with the tokens the API client drops on a
// 401. Without this, isAuthenticated/isAdmin stay true and admin routes remain
// reachable in the UI even though every request is rejected.
window.addEventListener(UNAUTHORIZED_EVENT, () => {
  useAuthStore(pinia).clearSession()
})

app.mount('#app')
