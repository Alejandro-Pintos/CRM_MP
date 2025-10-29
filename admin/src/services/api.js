// src/services/api.js
const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000'

export async function apiFetch(path, { method = 'GET', body, headers = {} } = {}) {
  const token = localStorage.getItem('accessToken')
  const res = await fetch(`${API}${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  })
  if (!res.ok) {
    const text = await res.text().catch(() => '')
    throw new Error(`${method} ${path} failed (HTTP ${res.status}) ${text || ''}`.trim())
  }
  // algunas rutas pueden devolver 204
  return res.status === 204 ? null : res.json()
}

// Re-exportar useApi desde composables para compatibilidad
export { useApi } from '@/composables/useApi'
