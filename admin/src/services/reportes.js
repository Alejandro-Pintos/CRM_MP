// src/services/reportes.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/reportes'
const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000'

export async function getReporteClientes(params = {}) {
  const queryString = new URLSearchParams(params).toString()
  return await apiFetch(`${BASE_PATH}/clientes${queryString ? `?${queryString}` : ''}`, { method: 'GET' })
}

export async function exportClientesExcel(params = {}) {
  const token = localStorage.getItem('accessToken')
  const queryParams = new URLSearchParams({ ...params, ...(token ? { token } : {}) }).toString()
  window.open(
    `${API}${BASE_PATH}/clientes/export.xlsx${queryParams ? `?${queryParams}` : ''}`,
    '_blank'
  )
}

export async function exportClientesCSV(params = {}) {
  const token = localStorage.getItem('accessToken')
  const queryParams = new URLSearchParams({ ...params, ...(token ? { token } : {}) }).toString()
  const url = `${API}${BASE_PATH}/clientes/export.csv${queryParams ? `?${queryParams}` : ''}`
  console.log('Exportando clientes CSV:', url)
  window.open(url, '_blank')
}

export async function getReporteProductos(params = {}) {
  const queryString = new URLSearchParams(params).toString()
  return await apiFetch(`${BASE_PATH}/productos${queryString ? `?${queryString}` : ''}`, { method: 'GET' })
}

export async function exportProductosExcel(params = {}) {
  const token = localStorage.getItem('accessToken')
  const queryParams = new URLSearchParams({ ...params, ...(token ? { token } : {}) }).toString()
  window.open(
    `${API}${BASE_PATH}/productos/export.xlsx${queryParams ? `?${queryParams}` : ''}`,
    '_blank'
  )
}

export async function exportProductosCSV(params = {}) {
  const token = localStorage.getItem('accessToken')
  const queryParams = new URLSearchParams({ ...params, ...(token ? { token } : {}) }).toString()
  window.open(
    `${API}${BASE_PATH}/productos/export.csv${queryParams ? `?${queryParams}` : ''}`,
    '_blank'
  )
}

export async function getReporteProveedores(params = {}) {
  const queryString = new URLSearchParams(params).toString()
  return await apiFetch(`${BASE_PATH}/proveedores${queryString ? `?${queryString}` : ''}`, { method: 'GET' })
}

export async function exportProveedoresExcel(params = {}) {
  const token = localStorage.getItem('accessToken')
  const queryParams = new URLSearchParams({ ...params, ...(token ? { token } : {}) }).toString()
  window.open(
    `${API}${BASE_PATH}/proveedores/export.xlsx${queryParams ? `?${queryParams}` : ''}`,
    '_blank'
  )
}

export async function exportProveedoresCSV(params = {}) {
  const token = localStorage.getItem('accessToken')
  const queryParams = new URLSearchParams({ ...params, ...(token ? { token } : {}) }).toString()
  window.open(
    `${API}${BASE_PATH}/proveedores/export.csv${queryParams ? `?${queryParams}` : ''}`,
    '_blank'
  )
}

export async function getReporteVentas(params = {}) {
  const queryString = new URLSearchParams(params).toString()
  return await apiFetch(`${BASE_PATH}/ventas${queryString ? `?${queryString}` : ''}`, { method: 'GET' })
}

export async function exportVentasExcel(params = {}) {
  const token = localStorage.getItem('accessToken')
  const queryParams = new URLSearchParams({ ...params, ...(token ? { token } : {}) }).toString()
  window.open(
    `${API}${BASE_PATH}/ventas/export.xlsx${queryParams ? `?${queryParams}` : ''}`,
    '_blank'
  )
}

export async function exportVentasCSV(params = {}) {
  const token = localStorage.getItem('accessToken')
  const queryParams = new URLSearchParams({ ...params, ...(token ? { token } : {}) }).toString()
  window.open(
    `${API}${BASE_PATH}/ventas/export.csv${queryParams ? `?${queryParams}` : ''}`,
    '_blank'
  )
}

export async function exportReporteFull() {
  const token = localStorage.getItem('accessToken')
  window.open(
    `${API}${BASE_PATH}/full/single.xlsx${token ? `?token=${token}` : ''}`,
    '_blank'
  )
}
