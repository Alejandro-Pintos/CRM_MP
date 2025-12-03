// src/services/notificaciones.js
import { apiFetch } from './api'

/**
 * Obtener resumen de notificaciones/alertas del usuario actual
 * Devuelve contadores de items que requieren atención
 */
export async function getResumenNotificaciones() {
  try {
    // Hacer llamadas en paralelo para mejor performance
    const [chequesPendientes, pedidosPendientes] = await Promise.all([
      apiFetch('/api/v1/cheques/pendientes', { method: 'GET' }),
      apiFetch('/api/v1/pedidos-pendientes', { method: 'GET' }),
    ])

    // Filtrar cheques próximos a vencer (próximos 7 días)
    const hoy = new Date()
    const proximosDias = new Date()
    proximosDias.setDate(hoy.getDate() + 7)

    let chequesProximosVencer = 0
    if (Array.isArray(chequesPendientes)) {
      chequesProximosVencer = chequesPendientes.filter(cheque => {
        if (!cheque.fecha_vencimiento && !cheque.fecha_cobro) return false
        const fechaVencimiento = new Date(cheque.fecha_vencimiento || cheque.fecha_cobro)
        return fechaVencimiento <= proximosDias && fechaVencimiento >= hoy
      }).length
    }

    // Contar pedidos pendientes
    const pedidosPendientesCount = Array.isArray(pedidosPendientes) 
      ? pedidosPendientes.length 
      : 0

    return {
      cheques_proximos_vencer: chequesProximosVencer,
      pedidos_pendientes: pedidosPendientesCount,
      total_alertas: chequesProximosVencer + pedidosPendientesCount,
    }
  } catch (error) {
    console.error('Error al obtener resumen de notificaciones:', error)
    return {
      cheques_proximos_vencer: 0,
      pedidos_pendientes: 0,
      total_alertas: 0,
    }
  }
}

/**
 * Obtener lista de cheques próximos a vencer
 */
export async function getChequesProximosVencer(dias = 7) {
  try {
    const cheques = await apiFetch('/api/v1/cheques/pendientes', { method: 'GET' })
    
    const hoy = new Date()
    const proximosDias = new Date()
    proximosDias.setDate(hoy.getDate() + dias)

    if (!Array.isArray(cheques)) return []

    return cheques.filter(cheque => {
      if (!cheque.fecha_vencimiento && !cheque.fecha_cobro) return false
      const fechaVencimiento = new Date(cheque.fecha_vencimiento || cheque.fecha_cobro)
      return fechaVencimiento <= proximosDias && fechaVencimiento >= hoy
    })
  } catch (error) {
    console.error('Error al obtener cheques próximos a vencer:', error)
    return []
  }
}
