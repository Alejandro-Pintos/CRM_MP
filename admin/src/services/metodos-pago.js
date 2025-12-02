// src/services/metodos-pago.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/metodos-pago'

export async function getMetodosPago() {
  return await apiFetch(BASE_PATH, { method: 'GET' })
}
