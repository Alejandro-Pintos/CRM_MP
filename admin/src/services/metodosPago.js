// src/services/metodosPago.js
import { apiFetch } from './api'

export async function getMetodosPago() {
  return await apiFetch('/api/v1/metodos-pago', { method: 'GET' })
}
