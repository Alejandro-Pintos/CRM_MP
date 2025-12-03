// src/composables/useAuth.js
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { login as apiLogin, logout as apiLogout, getMe } from '@/services/auth'

const user = ref(JSON.parse(localStorage.getItem('userData') || 'null'))
const token = ref(localStorage.getItem('accessToken') || null)
const loading = ref(false)
const error = ref(null)

export function useAuth() {
  const router = useRouter()

  const login = async (email, password) => {
    loading.value = true
    error.value = null
    try {
      const res = await apiLogin({ email, password })
      token.value = localStorage.getItem('accessToken')
      user.value = res?.user ?? await getMe().catch(() => null)
      // 🔁 redirección post login (ajusta a la ruta que exista en tu app)
      router.replace({ name: 'dashboard' })
    } catch (e) {
      error.value = e?.message || 'Error al iniciar sesión'
      throw e
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    await apiLogout()
    user.value = null
    token.value = null
    router.replace({ name: 'login' })
  }

  return { user, token, loading, error, login, logout }
}
