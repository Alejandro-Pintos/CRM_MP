// admin/src/services/users.js
import { apiFetch } from './api'

const API_BASE = '/api/v1/users'

/**
 * Obtener lista paginada de usuarios
 */
export async function getUsers(params = {}) {
  const queryString = new URLSearchParams(params).toString()
  const url = queryString ? `${API_BASE}?${queryString}` : API_BASE
  return await apiFetch(url)
}

/**
 * Obtener un usuario por ID
 */
export async function getUser(id) {
  return await apiFetch(`${API_BASE}/${id}`)
}

/**
 * Crear un nuevo usuario
 */
export async function createUser(userData) {
  return await apiFetch(API_BASE, {
    method: 'POST',
    body: userData,
  })
}

/**
 * Actualizar un usuario existente
 */
export async function updateUser(id, userData) {
  return await apiFetch(`${API_BASE}/${id}`, {
    method: 'PUT',
    body: userData,
  })
}

/**
 * Eliminar un usuario
 */
export async function deleteUser(id) {
  return await apiFetch(`${API_BASE}/${id}`, {
    method: 'DELETE',
  })
}
