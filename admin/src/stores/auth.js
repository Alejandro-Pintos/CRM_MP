// src/stores/auth.js
import { defineStore } from 'pinia'
import { login as apiLogin, getMe } from '@/services/auth' // ✅ Usar el servicio correcto

const TOKEN_KEY = 'crmmp:token'

export const useAuthStore = defineStore('auth', {
  state: () => {
    const token = localStorage.getItem(TOKEN_KEY) || null
    const userDataStr = localStorage.getItem('userData')
    let user = null
    
    try {
      if (userDataStr) {
        user = JSON.parse(userDataStr)
        console.log('✅ Usuario cargado desde localStorage:', user)
      }
    } catch (e) {
      console.error('❌ Error al parsear userData:', e)
      localStorage.removeItem('userData')
    }
    
    return {
      token,
      user,
    }
  },
  getters: {
    isAuthenticated: s => !!s.token,
  },
  actions: {
    setToken(token) {
      this.token = token
      if (token) localStorage.setItem(TOKEN_KEY, token)
      else localStorage.removeItem(TOKEN_KEY)
    },

    setUser(user) {
      console.log('📝 setUser llamado con:', user)
      this.user = user
      if (user) {
        localStorage.setItem('userData', JSON.stringify(user))
        console.log('✅ Usuario guardado en localStorage')
      } else {
        localStorage.removeItem('userData')
        console.log('🗑️ Usuario eliminado de localStorage')
      }
    },

    async login({ email, password }) {
      // ✅ USAR el servicio de auth.js que ya funciona correctamente
      const res = await apiLogin({ email, password })

      const token = res?.access_token ?? res?.token
      if (!token) throw new Error('El backend no devolvió token en el login')

      this.setToken(token)

      // Obtener perfil completo del usuario
      try {
        const response = await getMe()
        // Laravel Resources envuelven la respuesta en { data: {...} }
        this.setUser(response?.data ?? response?.user ?? response)
      } catch (error) {
        console.warn('No se pudo cargar el perfil del usuario:', error)
        this.setUser(null)
      }

      return true
    },

    async logout() {
      const { logout: apiLogout } = await import('@/services/auth')
      await apiLogout()
      this.setUser(null)
      this.setToken(null)
    },
  },
})
