// src/services/productos.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/productos'

export async function getProductos() {
  const response = await apiFetch(`${BASE_PATH}?per_page=all`, { method: 'GET' })
  return response.data || response
}

export async function getProducto(id) {
  const response = await apiFetch(`${BASE_PATH}/${id}`, { method: 'GET' })
  return response.data || response
}

export async function createProducto(data) {
  const response = await apiFetch(BASE_PATH, {
    method: 'POST',
    body: data
  })
  return response.data || response
}

export async function updateProducto(id, data) {
  const response = await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'PUT',
    body: data
  })
  return response.data || response
}

export async function deleteProducto(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'DELETE'
  })
}
