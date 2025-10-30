// src/services/proveedores.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/proveedores'

export async function getProveedores() {
  return await apiFetch(`${BASE_PATH}?per_page=all`, { method: 'GET' })
}

export async function getProveedor(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'GET' })
}

export async function createProveedor(data) {
  return await apiFetch(BASE_PATH, {
    method: 'POST',
    body: data
  })
}

export async function updateProveedor(id, data) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'PUT',
    body: data
  })
}

export async function deleteProveedor(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'DELETE'
  })
}
