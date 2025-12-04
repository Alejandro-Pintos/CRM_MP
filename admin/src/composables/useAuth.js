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
      console.log('[useAuth] Iniciando login para:', email)
      const res = await apiLogin({ email, password })
      console.log('[useAuth] Login exitoso, respuesta:', res)
      
      token.value = localStorage.getItem('accessToken')
      console.log('[useAuth] Token guardado:', token.value ? '✓' : '✗')
      
      // Obtener datos del usuario
      if (res?.user) {
        user.value = res.user
        console.log('[useAuth] Usuario desde respuesta:', user.value)
      } else {
        console.log('[useAuth] Obteniendo usuario desde /me...')
        user.value = await getMe().catch(() => null)
        console.log('[useAuth] Usuario desde /me:', user.value)
      }
      
      console.log('[useAuth] Redirigiendo a dashboard...')
      // Redirección post login
      router.replace({ name: 'dashboard' })
    } catch (e) {
      console.error('[useAuth] Error en login:', e)
      
      // Mensajes de error más específicos
      let errorMessage = 'Error al iniciar sesión'
      
      if (e.status === 401 || e.status === 422) {
        errorMessage = 'Email o contraseña incorrectos'
      } else if (e.isNetworkError) {
        errorMessage = 'No se pudo conectar al servidor. Verifica tu conexión a Internet.'
      } else if (e.isNotJSON) {
        errorMessage = 'Error del servidor: El backend no está respondiendo correctamente. Contacta al administrador.'
      } else if (e.message) {
        errorMessage = e.message
      }
      
      error.value = errorMessage
      throw new Error(errorMessage)
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
