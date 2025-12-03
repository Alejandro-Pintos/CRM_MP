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
      }
    } catch (e) {
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
      this.user = user
      if (user) {
        localStorage.setItem('userData', JSON.stringify(user))
      } else {
        localStorage.removeItem('userData')
      }
    },

    async login({ email, password }) {
      // ✅ USAR el servicio de auth.js que ya funciona correctamente
      const res = await apiLogin({ email, password })

      const token = res?.access_token ?? res?.token
      if (!token) throw new Error('El backend no devolvió token en el login')

      this.setToken(token)

      // Obtener perfil completo del usuario
      // Obtener perfil completo del usuario
      try {
        const userData = await getMe()
        // getMe() ya extrae el data y lo guarda en localStorage
        this.setUser(userData)
      } catch (error) {
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
