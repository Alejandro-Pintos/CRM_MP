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
