// src/services/productos.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/productos'

export async function getProductos() {
  return await apiFetch(BASE_PATH, { method: 'GET' })
}

export async function getProducto(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'GET' })
}

export async function createProducto(data) {
  return await apiFetch(BASE_PATH, {
    method: 'POST',
    body: data
  })
}

export async function updateProducto(id, data) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'PUT',
    body: data
  })
}

export async function deleteProducto(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'DELETE'
  })
}
