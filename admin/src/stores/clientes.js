// src/stores/clientes.js
import { defineStore } from 'pinia'
import { useApi } from '@/services/api'

function extractList(payload) {
  // Soporta tanto paginado { data: [] } como array directo
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.items)) return payload.items
  return []
}

export const useClientesStore = defineStore('clientes', {
  state: () => ({
    list: [],
    one: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchList() {
      this.loading = true; this.error = null
      try {
        const api = useApi()
        const res = await api('/api/v1/clientes', { method: 'GET' })
        this.list = extractList(res)
      } catch (e) {
        this.error = e?.message || 'No se pudieron cargar los clientes'
      } finally {
        this.loading = false
      }
    },

    async fetchOne(id) {
      this.loading = true; this.error = null
      try {
        const api = useApi()
        this.one = await api(`/api/v1/clientes/${id}`, { method: 'GET' })
        return this.one
      } catch (e) {
        this.error = e?.message || 'No se pudo cargar el cliente'
        throw e
      } finally {
        this.loading = false
      }
    },

    async create(payload) {
      const api = useApi()
      return await api('/api/v1/clientes', { method: 'POST', body: payload })
    },

    async update(id, payload) {
      const api = useApi()
      return await api(`/api/v1/clientes/${id}`, { method: 'PUT', body: payload })
    },

    async remove(id) {
      const api = useApi()
      return await api(`/api/v1/clientes/${id}`, { method: 'DELETE' })
    },
  },
})
