// src/services/empleados.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/empleados'

/**
 * Obtener todos los empleados
 * @param {Object} params - Parámetros de filtrado (q, activo, per_page)
 * @returns {Promise}
 */
export async function getEmpleados(params = {}) {
  const queryParams = new URLSearchParams()
  
  if (params.q) queryParams.append('q', params.q)
  if (params.activo !== undefined) queryParams.append('activo', params.activo)
  queryParams.append('per_page', params.per_page || 'all')

  const queryString = queryParams.toString()
  return await apiFetch(`${BASE_PATH}${queryString ? `?${queryString}` : ''}`, { method: 'GET' })
}

/**
 * Obtener un empleado por ID
 * @param {number} id
 * @returns {Promise}
 */
export async function getEmpleado(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'GET' })
}

/**
 * Crear un nuevo empleado
 * @param {Object} data - Datos del empleado
 * @returns {Promise}
 */
export async function createEmpleado(data) {
  return await apiFetch(BASE_PATH, {
    method: 'POST',
    body: data
  })
}

/**
 * Actualizar un empleado
 * @param {number} id
 * @param {Object} data - Datos del empleado a actualizar
 * @returns {Promise}
 */
export async function updateEmpleado(id, data) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'PUT',
    body: data
  })
}

/**
 * Eliminar un empleado (soft delete)
 * @param {number} id
 * @returns {Promise}
 */
export async function deleteEmpleado(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'DELETE'
  })
}

/**
 * Obtener pagos de un empleado
 * @param {number} empleadoId
 * @param {Object} params - Parámetros de filtrado (fecha_desde, fecha_hasta)
 * @returns {Promise}
 */
export async function getPagosEmpleado(empleadoId, params = {}) {
  const queryParams = new URLSearchParams()
  
  if (params.fecha_desde) queryParams.append('fecha_desde', params.fecha_desde)
  if (params.fecha_hasta) queryParams.append('fecha_hasta', params.fecha_hasta)

  const queryString = queryParams.toString()
  return await apiFetch(`${BASE_PATH}/${empleadoId}/pagos${queryString ? `?${queryString}` : ''}`, { 
    method: 'GET' 
  })
}

/**
 * Registrar un pago a un empleado
 * @param {number} empleadoId
 * @param {Object} data - Datos del pago (fecha_pago, monto, metodo_pago_id, concepto, observaciones)
 * @returns {Promise}
 */
export async function createPagoEmpleado(empleadoId, data) {
  return await apiFetch(`${BASE_PATH}/${empleadoId}/pagos`, {
    method: 'POST',
    body: data
  })
}

/**
 * Eliminar un pago de empleado
 * @param {number} pagoId
 * @returns {Promise}
 */
export async function deletePagoEmpleado(pagoId) {
  return await apiFetch(`/api/v1/pagos-empleados/${pagoId}`, {
    method: 'DELETE'
  })
}
