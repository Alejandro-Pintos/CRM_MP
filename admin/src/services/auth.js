// src/services/auth.js
import { apiFetch } from './api'

export async function login(payloadOrEmail, password) {
  const body = typeof payloadOrEmail === 'object'
    ? payloadOrEmail
    : { email: payloadOrEmail, password }

  const data = await apiFetch('/api/login', {
    method: 'POST',
    body, // <-- objeto, no string
  })

  const token = data?.access_token || data?.token || data?.data?.token
  if (token) localStorage.setItem('accessToken', token)
  if (data?.user) localStorage.setItem('userData', JSON.stringify(data.user))

  return data
}

export async function logout() {
  try {
    await apiFetch('/api/v1/logout', { method: 'POST' })
  } catch (e) {
    console.error('Logout remoto falló (se ignora):', e)
  } finally {
    localStorage.removeItem('accessToken')
    localStorage.removeItem('userData')
  }
}

export async function getMe() {
  // Tu backend expone POST /api/v1/me según route:list
  const data = await apiFetch('/api/v1/me', { method: 'POST' })
  if (data) localStorage.setItem('userData', JSON.stringify(data))
  return data
}

/**
 * Actualizar datos básicos del perfil (nombre, email)
 */
export async function updateProfile(profileData) {
  const data = await apiFetch('/api/v1/profile', {
    method: 'PUT',
    body: profileData,
  })
  
  // Actualizar localStorage con los nuevos datos
  if (data?.data) {
    localStorage.setItem('userData', JSON.stringify(data.data))
  }
  
  return data
}

/**
 * Cambiar contraseña del usuario autenticado
 */
export async function updatePassword(passwordData) {
  return await apiFetch('/api/v1/profile/password', {
    method: 'PUT',
    body: passwordData,
  })
}

/**
 * Actualizar avatar del perfil
 */
export async function updateAvatar(file) {
  const formData = new FormData()
  formData.append('avatar', file)
  
  // Para FormData, no usar apiFetch directamente porque necesita configuración especial
  const token = localStorage.getItem('accessToken')
  const API = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  
  const res = await fetch(`${API}/api/v1/profile/avatar`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData, // NO añadir Content-Type, fetch lo hace automáticamente para FormData
  })
  
  if (!res.ok) {
    const error = await res.json().catch(() => ({ message: 'Error al subir avatar' }))
    throw new Error(error.message || 'Error al subir avatar')
  }
  
  const data = await res.json()
  
  // Actualizar localStorage con la nueva URL del avatar
  if (data?.data) {
    localStorage.setItem('userData', JSON.stringify(data.data))
  }
  
  return data
}
