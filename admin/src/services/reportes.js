// src/services/reportes.js
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/reportes'
const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000'

export async function getReporteClientes() {
  return await apiFetch(`${BASE_PATH}/clientes`, { method: 'GET' })
}

export async function exportClientesExcel() {
  const token = localStorage.getItem('accessToken')
  window.open(
    `${API}${BASE_PATH}/clientes/export.xlsx${token ? `?token=${token}` : ''}`,
    '_blank'
  )
}

export async function exportClientesCSV() {
  const token = localStorage.getItem('accessToken')
  const url = `${API}${BASE_PATH}/clientes/export.csv${token ? `?token=${token}` : ''}`
  console.log('Exportando clientes CSV:', url)
  window.open(url, '_blank')
}

export async function getReporteProductos() {
  return await apiFetch(`${BASE_PATH}/productos`, { method: 'GET' })
}

export async function exportProductosExcel() {
  const token = localStorage.getItem('accessToken')
  window.open(
    `${API}${BASE_PATH}/productos/export.xlsx${token ? `?token=${token}` : ''}`,
    '_blank'
  )
}

export async function exportProductosCSV() {
  const token = localStorage.getItem('accessToken')
  window.open(
    `${API}${BASE_PATH}/productos/export.csv${token ? `?token=${token}` : ''}`,
    '_blank'
  )
}

export async function getReporteProveedores() {
  return await apiFetch(`${BASE_PATH}/proveedores`, { method: 'GET' })
}

export async function exportProveedoresExcel() {
  const token = localStorage.getItem('accessToken')
  window.open(
    `${API}${BASE_PATH}/proveedores/export.xlsx${token ? `?token=${token}` : ''}`,
    '_blank'
  )
}

export async function exportProveedoresCSV() {
  const token = localStorage.getItem('accessToken')
  window.open(
    `${API}${BASE_PATH}/proveedores/export.csv${token ? `?token=${token}` : ''}`,
    '_blank'
  )
}

export async function getReporteVentas() {
  return await apiFetch(`${BASE_PATH}/ventas`, { method: 'GET' })
}

export async function exportVentasExcel() {
  const token = localStorage.getItem('accessToken')
  window.open(
    `${API}${BASE_PATH}/ventas/export.xlsx${token ? `?token=${token}` : ''}`,
    '_blank'
  )
}

export async function exportVentasCSV() {
  const token = localStorage.getItem('accessToken')
  window.open(
    `${API}${BASE_PATH}/ventas/export.csv${token ? `?token=${token}` : ''}`,
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
