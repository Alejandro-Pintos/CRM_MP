<script setup>
import { ref, onMounted, computed } from 'vue'
import {
  getReporteClientes,
  getReporteProductos,
  getReporteProveedores,
  getReporteVentas,
  exportClientesExcel,
  exportClientesCSV,
  exportProductosExcel,
  exportProductosCSV,
  exportProveedoresExcel,
  exportProveedoresCSV,
  exportVentasExcel,
  exportVentasCSV,
} from '@/services/reportes'

const loading = ref(false)
const error = ref('')
const activeTab = ref('clientes')

// Filtros de período
const fechaDesde = ref('')
const fechaHasta = ref('')

// Establecer fechas por defecto (último mes)
const hoy = new Date()
const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
fechaDesde.value = primerDiaMes.toISOString().split('T')[0]
fechaHasta.value = hoy.toISOString().split('T')[0]

const reporteClientes = ref([])
const reporteProductos = ref([])
const reporteProveedores = ref([])
const reporteVentas = ref([])

const headersClientes = [
  { title: 'ID', key: 'cliente_id', width: 70 },
  { title: 'Nombre', key: 'nombre', width: 120 },
  { title: 'Apellido', key: 'apellido', width: 120 },
  { title: 'Email', key: 'email', width: 180 },
  { title: 'Teléfono', key: 'telefono', width: 120 },
  { title: 'CUIT/CUIL', key: 'cuit_cuil', width: 110 },
  { title: 'Estado', key: 'estado', width: 90 },
  { title: '# Compras', key: 'compras', width: 100 },
  { title: 'Total Compras', key: 'ingreso_total', width: 130 },
  { title: 'Saldo CC', key: 'saldo_actual', width: 120 },
  { title: 'Límite Crédito', key: 'limite_credito', width: 130 },
]

const headersProductos = [
  { title: 'ID', key: 'producto_id', width: 70 },
  { title: 'Código', key: 'codigo', width: 100 },
  { title: 'Nombre', key: 'nombre', width: 180 },
  { title: 'Proveedor', key: 'proveedor_nombre', width: 150 },
  { title: 'Precio Venta', key: 'precio_venta', width: 120 },
  { title: 'Precio Compra', key: 'precio_compra', width: 120 },
  { title: 'Estado', key: 'estado', width: 90 },
  { title: 'Cant. Vendida', key: 'cantidad_total', width: 110 },
  { title: 'Ingreso Total', key: 'ingreso_total', width: 130 },
]

const headersProveedores = [
  { title: 'ID', key: 'proveedor_id', width: 80 },
  { title: 'Nombre', key: 'nombre', width: 200 },
  { title: 'CUIT', key: 'cuit', width: 120 },
  { title: 'Teléfono', key: 'telefono', width: 120 },
  { title: 'Email', key: 'email', width: 180 },
  { title: 'Estado', key: 'estado', width: 100 },
  { title: '# Compras', key: 'cantidad_compras', width: 110 },
  { title: 'Total Compras', key: 'total_compras', width: 140 },
  { title: '# Pagos', key: 'cantidad_pagos', width: 100 },
  { title: 'Total Pagos', key: 'total_pagos', width: 140 },
  { title: 'Saldo', key: 'saldo', width: 130 },
  { title: '# Productos', key: 'cantidad_productos', width: 120 },
  { title: 'Ingreso Ventas', key: 'ingreso_ventas', width: 150 },
]

const headersVentas = [
  { title: 'Período', key: 'period', width: 120 },
  { title: '# Ventas', key: 'ventas_count', width: 100 },
  { title: 'Total Neto', key: 'total_neto', width: 130 },
  { title: 'Ticket Prom.', key: 'ticket_promedio', width: 120 },
  { title: 'Clientes', key: 'clientes_unicos', width: 100 },
  { title: 'Productos', key: 'productos_vendidos', width: 100 },
  { title: 'Pagado', key: 'pagado', width: 90 },
  { title: 'Pendiente', key: 'pendiente', width: 90 },
  { title: 'Parcial', key: 'parcial', width: 90 },
]

const fetchReportes = async () => {
  loading.value = true
  error.value = ''
  try {
    // Construir parámetros de período con los nombres correctos que espera el backend
    const params = {}
    if (fechaDesde.value) params.from = fechaDesde.value
    if (fechaHasta.value) params.to = fechaHasta.value
    
    const [clientes, productos, proveedores, ventas] = await Promise.all([
      getReporteClientes(params),
      getReporteProductos(params),
      getReporteProveedores(params),
      getReporteVentas(params),
    ])
    
    reporteClientes.value = Array.isArray(clientes) ? clientes : (clientes.data ?? [])
    reporteProductos.value = Array.isArray(productos) ? productos : (productos.data ?? [])
    reporteProveedores.value = Array.isArray(proveedores) ? proveedores : (proveedores.data ?? [])
    // Ventas devuelve un objeto con series
    reporteVentas.value = ventas.series ?? (Array.isArray(ventas) ? ventas : (ventas.data ?? []))
  } catch (e) {
    error.value = e.message || 'Error al cargar reportes'
  } finally {
    loading.value = false
  }
}

const exportar = (tipo, formato) => {
  try {
    // Construir parámetros de período con los nombres correctos
    const params = {}
    if (fechaDesde.value) params.from = fechaDesde.value
    if (fechaHasta.value) params.to = fechaHasta.value
    
    const exportFunctions = {
      clientes: {
        excel: () => exportClientesExcel(params),
        csv: () => exportClientesCSV(params),
      },
      productos: {
        excel: () => exportProductosExcel(params),
        csv: () => exportProductosCSV(params),
      },
      proveedores: {
        excel: () => exportProveedoresExcel(params),
        csv: () => exportProveedoresCSV(params),
      },
      ventas: {
        excel: () => exportVentasExcel(params),
        csv: () => exportVentasCSV(params),
      },
    }
    
    const exportFunction = exportFunctions[tipo]?.[formato]
    if (exportFunction) {
      exportFunction()
    } else {
      error.value = 'Función de exportación no encontrada'
    }
  } catch (e) {
    error.value = e.message || 'Error al exportar'
  }
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const getEstadoColor = (estado) => {
  const colores = {
    pendiente: 'warning',
    pagado: 'success',
    cancelado: 'error',
  }
  return colores[estado] || 'default'
}

onMounted(fetchReportes)
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center flex-wrap ga-4">
          <span class="text-h5">Reportes</span>
          <div class="d-flex ga-2 align-center flex-wrap">
            <VTextField
              v-model="fechaDesde"
              label="Desde"
              type="date"
              density="compact"
              hide-details
              style="max-width: 160px;"
            />
            <VTextField
              v-model="fechaHasta"
              label="Hasta"
              type="date"
              density="compact"
              hide-details
              style="max-width: 160px;"
            />
            <VBtn color="primary" @click="fetchReportes" :loading="loading">
              <VIcon start>mdi-filter</VIcon>
              Aplicar Filtros
            </VBtn>
            <VBtn color="secondary" variant="tonal" @click="fetchReportes" :loading="loading">
              <VIcon start>mdi-refresh</VIcon>
              Actualizar
            </VBtn>
          </div>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" dismissible @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VTabs v-model="activeTab" color="primary" class="mb-4">
          <VTab value="clientes">Clientes</VTab>
          <VTab value="productos">Productos</VTab>
          <VTab value="proveedores">Proveedores</VTab>
          <VTab value="ventas">Ventas</VTab>
        </VTabs>

        <VWindow v-model="activeTab">
          <!-- Reporte de Clientes -->
          <VWindowItem value="clientes">
            <div class="mb-4">
              <VBtn color="success" class="me-2" @click="exportar('clientes', 'excel')">
                <VIcon start>mdi-file-excel</VIcon>
                Exportar Excel
              </VBtn>
              <VBtn color="info" @click="exportar('clientes', 'csv')">
                <VIcon start>mdi-file-delimited</VIcon>
                Exportar CSV
              </VBtn>
            </div>

            <VDataTable
              :headers="headersClientes"
              :items="reporteClientes"
              :loading="loading"
              loading-text="Cargando reporte..."
              no-data-text="No hay datos disponibles"
              class="elevation-1"
              density="comfortable"
            >
              <template #item.estado="{ item }">
                <VChip :color="item.estado === 'activo' ? 'success' : 'error'" size="small">
                  {{ item.estado }}
                </VChip>
              </template>
              <template #item.ingreso_total="{ item }">
                {{ formatPrice(item.ingreso_total) }}
              </template>
              <template #item.saldo_actual="{ item }">
                <VChip 
                  :color="item.saldo_actual > 0 ? 'error' : item.saldo_actual < 0 ? 'success' : 'default'" 
                  size="small"
                >
                  {{ formatPrice(item.saldo_actual) }}
                </VChip>
              </template>
              <template #item.limite_credito="{ item }">
                {{ formatPrice(item.limite_credito) }}
              </template>
            </VDataTable>
          </VWindowItem>

          <!-- Reporte de Productos -->
          <VWindowItem value="productos">
            <div class="mb-4">
              <VBtn color="success" class="me-2" @click="exportar('productos', 'excel')">
                <VIcon start>mdi-file-excel</VIcon>
                Exportar Excel
              </VBtn>
              <VBtn color="info" @click="exportar('productos', 'csv')">
                <VIcon start>mdi-file-delimited</VIcon>
                Exportar CSV
              </VBtn>
            </div>

            <VDataTable
              :headers="headersProductos"
              :items="reporteProductos"
              :loading="loading"
              loading-text="Cargando reporte..."
              no-data-text="No hay datos disponibles"
              class="elevation-1"
              density="comfortable"
            >
              <template #item.proveedor_nombre="{ item }">
                {{ item.proveedor_nombre || 'Sin proveedor' }}
              </template>
              <template #item.precio_venta="{ item }">
                {{ formatPrice(item.precio_venta) }}
              </template>
              <template #item.precio_compra="{ item }">
                {{ formatPrice(item.precio_compra) }}
              </template>
              <template #item.estado="{ item }">
                <VChip :color="item.estado === 'activo' ? 'success' : 'error'" size="small">
                  {{ item.estado }}
                </VChip>
              </template>
              <template #item.ingreso_total="{ item }">
                {{ formatPrice(item.ingreso_total) }}
              </template>
            </VDataTable>
          </VWindowItem>

          <!-- Reporte de Proveedores -->
          <VWindowItem value="proveedores">
            <div class="mb-4">
              <VBtn color="success" class="me-2" @click="exportar('proveedores', 'excel')">
                <VIcon start>mdi-file-excel</VIcon>
                Exportar Excel
              </VBtn>
              <VBtn color="info" @click="exportar('proveedores', 'csv')">
                <VIcon start>mdi-file-delimited</VIcon>
                Exportar CSV
              </VBtn>
            </div>

            <VDataTable
              :headers="headersProveedores"
              :items="reporteProveedores"
              :loading="loading"
              loading-text="Cargando reporte..."
              no-data-text="No hay datos disponibles"
              class="elevation-1"
              density="comfortable"
            >
              <template #item.estado="{ item }">
                <VChip :color="item.estado === 'activo' ? 'success' : 'error'" size="small">
                  {{ item.estado }}
                </VChip>
              </template>
              <template #item.total_compras="{ item }">
                {{ formatPrice(item.total_compras) }}
              </template>
              <template #item.total_pagos="{ item }">
                {{ formatPrice(item.total_pagos) }}
              </template>
              <template #item.saldo="{ item }">
                <VChip 
                  :color="item.saldo > 0 ? 'error' : item.saldo < 0 ? 'success' : 'default'" 
                  size="small"
                >
                  {{ formatPrice(item.saldo) }}
                </VChip>
              </template>
              <template #item.ingreso_ventas="{ item }">
                {{ formatPrice(item.ingreso_ventas) }}
              </template>
            </VDataTable>
          </VWindowItem>

          <!-- Reporte de Ventas -->
          <VWindowItem value="ventas">
            <div class="mb-4">
              <VBtn color="success" class="me-2" @click="exportar('ventas', 'excel')">
                <VIcon start>mdi-file-excel</VIcon>
                Exportar Excel
              </VBtn>
              <VBtn color="info" @click="exportar('ventas', 'csv')">
                <VIcon start>mdi-file-delimited</VIcon>
                Exportar CSV
              </VBtn>
            </div>

            <VDataTable
              :headers="headersVentas"
              :items="reporteVentas"
              :loading="loading"
              loading-text="Cargando reporte..."
              no-data-text="No hay datos disponibles"
              class="elevation-1"
              density="comfortable"
            >
              <template #item.total_neto="{ item }">
                {{ formatPrice(item.total_neto) }}
              </template>
              <template #item.ticket_promedio="{ item }">
                {{ formatPrice(item.ticket_promedio) }}
              </template>
              <template #item.pagado="{ item }">
                <VChip color="success" size="small" v-if="item.pagado > 0">
                  {{ item.pagado }}
                </VChip>
                <span v-else class="text-grey">0</span>
              </template>
              <template #item.pendiente="{ item }">
                <VChip color="error" size="small" v-if="item.pendiente > 0">
                  {{ item.pendiente }}
                </VChip>
                <span v-else class="text-grey">0</span>
              </template>
              <template #item.parcial="{ item }">
                <VChip color="warning" size="small" v-if="item.parcial > 0">
                  {{ item.parcial }}
                </VChip>
                <span v-else class="text-grey">0</span>
              </template>
            </VDataTable>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </div>
</template>

