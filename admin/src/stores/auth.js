// src/stores/auth.js
import { defineStore } from 'pinia'
import { login as apiLogin, getMe } from '@/services/auth' // ✅ Usar el servicio correcto

const TOKEN_KEY = 'crmmp:token'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(TOKEN_KEY) || null,
    user: null,
  }),
  getters: {
    isAuthenticated: s => !!s.token,
  },
  actions: {
    setToken(token) {
      this.token = token
      if (token) localStorage.setItem(TOKEN_KEY, token)
      else localStorage.removeItem(TOKEN_KEY)
    },

    async login({ email, password }) {
      // ✅ USAR el servicio de auth.js que ya funciona correctamente
      const res = await apiLogin({ email, password })

      const token = res?.access_token ?? res?.token
      if (!token) throw new Error('El backend no devolvió token en el login')

      this.setToken(token)

      // Obtener perfil
      try {
        this.user = res?.user ?? (await getMe())
      } catch {
        this.user = null
      }

      return true
    },

    async logout() {
      const { logout: apiLogout } = await import('@/services/auth')
      await apiLogout()
      this.user = null
      this.setToken(null)
    },
  },
})
