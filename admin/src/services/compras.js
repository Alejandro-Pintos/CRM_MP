// src/services/compras.js
import { apiFetch } from './api'

/**
 * Obtener compras de un proveedor
 * @param {number} proveedorId 
 * @param {Object} filters - { fecha_desde, fecha_hasta, estado }
 */
export async function getComprasProveedor(proveedorId, filters = {}) {
  const params = new URLSearchParams()
  if (filters.fecha_desde) params.append('fecha_desde', filters.fecha_desde)
  if (filters.fecha_hasta) params.append('fecha_hasta', filters.fecha_hasta)
  if (filters.estado) params.append('estado', filters.estado)
  
  const query = params.toString() ? `?${params.toString()}` : ''
  return await apiFetch(`/api/v1/proveedores/${proveedorId}/compras${query}`, { method: 'GET' })
}

/**
 * Crear una nueva compra para un proveedor
 * @param {number} proveedorId 
 * @param {Object} compraData 
 */
export async function createCompraProveedor(proveedorId, compraData) {
  return await apiFetch(`/api/v1/proveedores/${proveedorId}/compras`, {
    method: 'POST',
    body: JSON.stringify(compraData)
  })
}

/**
 * Obtener detalle de una compra
 * @param {number} compraId 
 */
export async function getCompra(compraId) {
  return await apiFetch(`/api/v1/compras/${compraId}`, { method: 'GET' })
}

/**
 * Anular una compra
 * @param {number} compraId 
 */
export async function deleteCompra(compraId) {
  return await apiFetch(`/api/v1/compras/${compraId}`, { method: 'DELETE' })
}
