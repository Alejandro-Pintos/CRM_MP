// src/services/clientes.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/clientes'

export async function getClientes() {
  return await apiFetch(BASE_PATH, { method: 'GET' })
}

export async function getCliente(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'GET' })
}

export async function createCliente(data) {
  return await apiFetch(BASE_PATH, {
    method: 'POST',
    body: data
  })
}

export async function updateCliente(id, data) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'PUT',
    body: data
  })
}

export async function deleteCliente(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'DELETE'
  })
}

export async function getCuentaCorriente(id) {
  return await apiFetch(`${BASE_PATH}/${id}/cuenta-corriente`, { method: 'GET' })
}
