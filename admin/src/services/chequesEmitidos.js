// src/services/chequesEmitidos.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/cheques-emitidos'

/**
 * Listar todos los cheques emitidos con filtros
 * @param {Object} params - Filtros: estado, proveedor_id, fecha_desde, fecha_hasta
 * @returns {Promise}
 */
export async function getChequesEmitidos(params = {}) {
  const queryParams = new URLSearchParams()
  
  if (params.estado && params.estado !== 'todos') {
    queryParams.append('estado', params.estado)
  }
  if (params.proveedor_id) {
    queryParams.append('proveedor_id', params.proveedor_id)
  }
  if (params.fecha_desde) {
    queryParams.append('fecha_desde', params.fecha_desde)
  }
  if (params.fecha_hasta) {
    queryParams.append('fecha_hasta', params.fecha_hasta)
  }

  const queryString = queryParams.toString()
  return await apiFetch(`${BASE_PATH}${queryString ? `?${queryString}` : ''}`, {
    method: 'GET'
  })
}

/**
 * Obtener cheques emitidos de un proveedor específico
 * @param {number} proveedorId
 * @returns {Promise}
 */
export async function getChequesByProveedor(proveedorId) {
  return await apiFetch(`/api/v1/proveedores/${proveedorId}/cheques-emitidos`, {
    method: 'GET'
  })
}

/**
 * Obtener detalle de un cheque emitido
 * @param {number} chequeId
 * @returns {Promise}
 */
export async function getChequeEmitido(chequeId) {
  return await apiFetch(`${BASE_PATH}/${chequeId}`, {
    method: 'GET'
  })
}

/**
 * Crear un cheque emitido
 * @param {number} proveedorId
 * @param {Object} data - Datos del cheque: banco, numero, monto, fecha_emision, fecha_vencimiento, observaciones
 * @returns {Promise}
 */
export async function createChequeEmitido(proveedorId, data) {
  return await apiFetch(`/api/v1/proveedores/${proveedorId}/cheques-emitidos`, {
    method: 'POST',
    body: data
  })
}

/**
 * Actualizar cheque emitido (solo si está pendiente)
 * @param {number} chequeId
 * @param {Object} data - Datos a actualizar: banco, numero, fecha_emision, fecha_vencimiento, observaciones
 * @returns {Promise}
 */
export async function updateChequeEmitido(chequeId, data) {
  return await apiFetch(`${BASE_PATH}/${chequeId}`, {
    method: 'PATCH',
    body: data
  })
}

/**
 * Marcar cheque como debitado (cobrado por el proveedor)
 * @param {number} chequeId
 * @returns {Promise}
 */
export async function debitarCheque(chequeId) {
  return await apiFetch(`${BASE_PATH}/${chequeId}/debitar`, {
    method: 'POST'
  })
}

/**
 * Anular cheque emitido
 * @param {number} chequeId
 * @param {string} motivo - Motivo de la anulación
 * @returns {Promise}
 */
export async function anularCheque(chequeId, motivo) {
  return await apiFetch(`${BASE_PATH}/${chequeId}/anular`, {
    method: 'POST',
    body: { motivo }
  })
}

/**
 * Eliminar cheque emitido (solo si está pendiente y no vinculado a pago)
 * @param {number} chequeId
 * @returns {Promise}
 */
export async function deleteChequeEmitido(chequeId) {
  return await apiFetch(`${BASE_PATH}/${chequeId}`, {
    method: 'DELETE'
  })
}
