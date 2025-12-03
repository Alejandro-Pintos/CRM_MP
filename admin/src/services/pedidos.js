// src/services/pedidos.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/pedidos'

export async function getPedidos(params = {}) {
  // Agregar per_page=all por defecto si no se especifica
  const finalParams = { per_page: 'all', ...params }
  const queryString = new URLSearchParams(finalParams).toString()
  return await apiFetch(`${BASE_PATH}?${queryString}`, { method: 'GET' })
}

export async function getPedido(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'GET' })
}

export async function createPedido(data) {
  return await apiFetch(BASE_PATH, { 
    method: 'POST', 
    body: data  // NO hacer JSON.stringify aquí, apiFetch lo hace
  })
}

export async function updatePedido(id, data) {
  return await apiFetch(`${BASE_PATH}/${id}`, { 
    method: 'PUT', 
    body: data  // NO hacer JSON.stringify aquí, apiFetch lo hace
  })
}

export async function deletePedido(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'DELETE' })
}

export async function getPedidosPendientes(clienteId = null) {
  const params = clienteId ? `?cliente_id=${clienteId}` : ''
  return await apiFetch(`/api/v1/pedidos-pendientes${params}`, { method: 'GET' })
}

export async function asociarVenta(pedidoId, ventaId) {
  return await apiFetch(`${BASE_PATH}/${pedidoId}/asociar-venta`, {
    method: 'POST',
    body: JSON.stringify({ venta_id: ventaId })
  })
}

export async function getClima(params = {}) {
  // Soporta tanto { ciudad: 'nombre' } como { lat: number, lon: number }
  const queryString = new URLSearchParams(params).toString()
  return await apiFetch(`/api/v1/clima?${queryString}`, { method: 'GET' })
}

export async function getClimaLocal() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('La geolocalización no está soportada por tu navegador'))
      return
    }

    navigator.geolocation.getCurrentPosition(
      async (position) => {
        try {
          const data = await getClima({
            lat: position.coords.latitude,
            lon: position.coords.longitude
          })
          resolve(data)
        } catch (error) {
          reject(error)
        }
      },
      (error) => {
        let errorMessage = 'No se pudo obtener la ubicación'
        switch (error.code) {
          case error.PERMISSION_DENIED:
            errorMessage = 'Permiso de ubicación denegado. Por favor, habilita el acceso a la ubicación.'
            break
          case error.POSITION_UNAVAILABLE:
            errorMessage = 'Información de ubicación no disponible.'
            break
          case error.TIMEOUT:
            errorMessage = 'Tiempo de espera agotado para obtener ubicación.'
            break
        }
        reject(new Error(errorMessage))
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000 // Cache de 5 minutos
      }
    )
  })
}
