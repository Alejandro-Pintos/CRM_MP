// src/services/api.js
const API = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'

export async function apiFetch(path, { method = 'GET', body, headers = {} } = {}) {
  const token = localStorage.getItem('accessToken')
  
  let res
  try {
    res = await fetch(`${API}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...headers,
      },
      ...(body ? { body: JSON.stringify(body) } : {}),
    })
  } catch (networkError) {
    // Error de red (CORS, servidor caído, sin internet, etc.)
    console.error('[apiFetch] NetworkError:', networkError)
    console.error('[apiFetch] URL:', `${API}${path}`)
    console.error('[apiFetch] Method:', method)
    console.error('[apiFetch] Headers:', { Accept: 'application/json', ...(token ? { Authorization: 'Bearer ***' } : {}) })
    
    const error = new Error(`Error de conexión: No se pudo conectar al servidor (${API}). Verifica que el backend esté corriendo.`)
    error.isNetworkError = true
    error.originalError = networkError
    throw error
  }
  
  // Log de la respuesta para debugging
  console.log('[apiFetch] Response received:', {
    url: `${API}${path}`,
    status: res.status,
    statusText: res.statusText,
    ok: res.ok,
    headers: {
      'content-type': res.headers.get('content-type'),
      'access-control-allow-origin': res.headers.get('access-control-allow-origin'),
    }
  })
  
  if (!res.ok) {
    // Si es 401 (No autenticado), limpiar sesión y redirigir al login
    if (res.status === 401) {
      console.warn('[apiFetch] 401 Unauthorized - Limpiando sesión')
      
      localStorage.removeItem('accessToken')
      localStorage.removeItem('userData')
      localStorage.removeItem('sessionStartTime')
      localStorage.removeItem('lastActivity')
      
      // Solo redirigir si NO estamos en login
      if (window.location.pathname !== '/login') {
        console.log('[apiFetch] Redirigiendo a /login')
        
        // Crear error antes de redirigir
        const error = new Error('Sesión expirada. Por favor, inicia sesión nuevamente.')
        error.status = 401
        error.requiresLogin = true
        
        // Redirigir al login
        setTimeout(() => {
          window.location.href = '/login'
        }, 100)
        
        throw error
      }
      
      // Si ya estamos en login, solo lanzar error sin mensaje
      const error = new Error('No autorizado')
      error.status = 401
      error.requiresLogin = true
      throw error
    }
    
    // Parsear respuesta de error
    console.log('[apiFetch] Parsing error response...')
    let errorData
    try {
      errorData = await res.json()
      console.log('[apiFetch] Error data (JSON):', errorData)
    } catch (parseError) {
      console.error('[apiFetch] Failed to parse error response as JSON:', parseError)
      const textBody = await res.text().catch(() => 'Error desconocido')
      console.log('[apiFetch] Error data (TEXT):', textBody)
      errorData = { message: textBody }
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
    
    console.error('[apiFetch] Throwing HTTP error:', {
      status: error.status,
      message: errorMessage,
      errors: error.errors,
      data: errorData
    })
    
    throw error
  }
  
  // algunas rutas pueden devolver 204
  return res.status === 204 ? null : res.json()
}

// Re-exportar useApi desde composables para compatibilidad
export { useApi } from '@/composables/useApi'
