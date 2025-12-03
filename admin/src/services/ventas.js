// src/services/ventas.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/ventas'

export async function getVentas() {
  return await apiFetch(`${BASE_PATH}?per_page=all`, { method: 'GET' })
}

export async function getVenta(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'GET' })
}

export async function createVenta(data) {
  return await apiFetch(BASE_PATH, {
    method: 'POST',
    body: data
  })
}

export async function updateVenta(id, data) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'PUT',
    body: data
  })
}

export async function deleteVenta(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'DELETE' })
}

export async function getPagosVenta(ventaId) {
  return await apiFetch(`${BASE_PATH}/${ventaId}/pagos`, { method: 'GET' })
}

export async function getResumenPagosVenta(ventaId) {
  return await apiFetch(`${BASE_PATH}/${ventaId}/pagos/resumen`, { method: 'GET' })
}

export async function createPagoVenta(ventaId, data) {
  return await apiFetch(`${BASE_PATH}/${ventaId}/pagos`, {
    method: 'POST',
    body: data
  })
}
