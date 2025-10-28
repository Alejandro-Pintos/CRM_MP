// src/stores/auth.js
import { defineStore } from 'pinia'
import { useApi } from '@/services/api'

const TOKEN_KEY = 'crmmp:token'

// Paths desde .env (con defaults sensatos)
const LOGIN_PATH   = import.meta.env.VITE_LOGIN_PATH   || '/api/login'
const ME_PATH      = import.meta.env.VITE_ME_PATH      || '/api/v1/me'
const LOGOUT_PATH  = import.meta.env.VITE_LOGOUT_PATH  || '/api/v1/logout'
const REFRESH_PATH = import.meta.env.VITE_REFRESH_PATH || '/api/v1/refresh'

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
      const api = useApi()

      // LOGIN: POST /api/login (según tu route:list)
      const res = await api(LOGIN_PATH, {
        method: 'POST',
        body: { email, password },
      })

      // Token flexible: probamos varias llaves comunes
      const token =
        res?.access_token ??
        res?.token ??
        res?.data?.access_token ??
        res?.data?.token

      if (!token)
        throw new Error('El backend no devolvió token en el login')

      this.setToken(token)

      // PERFIL: POST /api/v1/me (tu route:list lo define como POST)
      try {
        this.user = res?.user ?? (await api(ME_PATH, { method: 'POST' }))
      } catch {
        this.user = null
      }

      return true
    },

    async logout() {
      const api = useApi()
      try { await api(LOGOUT_PATH, { method: 'POST' }) } catch {}
      this.user = null
      this.setToken(null)
    },

    async refresh() {
      const api = useApi()
      const res = await api(REFRESH_PATH, { method: 'POST' })
      const token =
        res?.access_token ??
        res?.token ??
        res?.data?.access_token ??
        res?.data?.token
      if (!token) throw new Error('No se pudo refrescar el token')
      this.setToken(token)
      return token
    },
  },
})
