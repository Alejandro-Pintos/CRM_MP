import { apiFetch } from './api'

/**
 * Enviar presupuesto por email
 */
export const enviarPresupuestoEmail = async (datos) => {
  const response = await apiFetch('/presupuestos/enviar-email', {
    method: 'POST',
    body: JSON.stringify(datos)
  })
  return response
}
