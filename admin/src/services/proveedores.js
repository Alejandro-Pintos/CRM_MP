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

/**
 * Obtener resumen de estado de cuenta de un proveedor
 * @param {number} proveedorId
 * @returns {Promise}
 */
export async function getResumenCuenta(proveedorId) {
  return await apiFetch(`${BASE_PATH}/${proveedorId}/cuenta/resumen`, {
    method: 'GET'
  })
}

/**
 * Obtener movimientos de cuenta corriente de un proveedor
 * @param {number} proveedorId
 * @param {Object} params - Parámetros de filtrado (from, to)
 * @returns {Promise}
 */
export async function getMovimientosCuenta(proveedorId, params = {}) {
  const queryParams = new URLSearchParams()
  
  if (params.from) queryParams.append('from', params.from)
  if (params.to) queryParams.append('to', params.to)

  const queryString = queryParams.toString()
  return await apiFetch(`${BASE_PATH}/${proveedorId}/cuenta/movimientos${queryString ? `?${queryString}` : ''}`, {
    method: 'GET'
  })
}

/**
 * Obtener pagos de un proveedor
 * @param {number} proveedorId
 * @param {Object} params - Parámetros de filtrado (fecha_desde, fecha_hasta)
 * @returns {Promise}
 */
export async function getPagosProveedor(proveedorId, params = {}) {
  const queryParams = new URLSearchParams()
  
  if (params.fecha_desde) queryParams.append('fecha_desde', params.fecha_desde)
  if (params.fecha_hasta) queryParams.append('fecha_hasta', params.fecha_hasta)

  const queryString = queryParams.toString()
  return await apiFetch(`${BASE_PATH}/${proveedorId}/pagos${queryString ? `?${queryString}` : ''}`, {
    method: 'GET'
  })
}

/**
 * Registrar un pago a un proveedor
 * @param {number} proveedorId
 * @param {Object} data - Datos del pago (fecha_pago, monto, metodo_pago_id, referencia, concepto, observaciones)
 * @returns {Promise}
 */
export async function createPagoProveedor(proveedorId, data) {
  return await apiFetch(`${BASE_PATH}/${proveedorId}/pagos`, {
    method: 'POST',
    body: data
  })
}

/**
 * Eliminar un pago de proveedor
 * @param {number} pagoId
 * @returns {Promise}
 */
export async function deletePagoProveedor(pagoId) {
  return await apiFetch(`/api/v1/pagos-proveedores/${pagoId}`, {
    method: 'DELETE'
  })
}
