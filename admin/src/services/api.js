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
    // Si es 401 (No autenticado), limpiar sesión y redirigir al login
    if (res.status === 401) {
      localStorage.removeItem('accessToken')
      localStorage.removeItem('userData')
      localStorage.removeItem('sessionStartTime')
      localStorage.removeItem('lastActivity')
      
      // Redirigir al login
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
      
      throw new Error('Sesión expirada. Por favor, inicia sesión nuevamente.')
    }
    
    // Parsear respuesta de error
    let errorData
    try {
      errorData = await res.json()
    } catch {
      errorData = { message: await res.text().catch(() => 'Error desconocido') }
    }
    
    // Formatear mensaje de error
    let errorMessage = errorData.message || 'Error en la solicitud'
    
    // Si hay errores de validación, formatearlos
    if (errorData.errors && typeof errorData.errors === 'object') {
      const errorsArray = Object.values(errorData.errors).flat()
      if (errorsArray.length > 0) {
        errorMessage = errorsArray.join('. ')
      }
    }
    
    // Crear error con información adicional
    const error = new Error(errorMessage)
    error.status = res.status
    error.errors = errorData.errors || {}
    error.data = errorData
    
    throw error
  }
  
  // algunas rutas pueden devolver 204
  return res.status === 204 ? null : res.json()
}

// Re-exportar useApi desde composables para compatibilidad
export { useApi } from '@/composables/useApi'
