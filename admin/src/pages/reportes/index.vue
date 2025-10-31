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
  { title: 'ID Cliente', key: 'cliente_id' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Total Compras', key: 'compras' },
  { title: 'Ingreso Total', key: 'ingreso_total' },
]

const headersProductos = [
  { title: 'ID Producto', key: 'producto_id' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Cantidad Vendida', key: 'cantidad_total' },
  { title: 'Ingreso Total', key: 'ingreso_total' },
]

const headersProveedores = [
  { title: 'ID Proveedor', key: 'proveedor_id' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Cantidad Total', key: 'cantidad_total' },
  { title: 'Ingreso Total', key: 'ingreso_total' },
  { title: 'Participación %', key: 'participacion' },
]

const headersVentas = [
  { title: 'Período', key: 'period' },
  { title: 'Total Ventas', key: 'ventas_count' },
  { title: 'Total Neto', key: 'total_neto' },
]

const fetchReportes = async () => {
  loading.value = true
  error.value = ''
  try {
    // Construir parámetros de período si están definidos
    const params = {}
    if (fechaDesde.value) params.fecha_desde = fechaDesde.value
    if (fechaHasta.value) params.fecha_hasta = fechaHasta.value
    
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
    // Construir parámetros de período
    const params = {}
    if (fechaDesde.value) params.fecha_desde = fechaDesde.value
    if (fechaHasta.value) params.fecha_hasta = fechaHasta.value
    
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
              <template #item.ingreso_total="{ item }">
                {{ formatPrice(item.ingreso_total) }}
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
              <template #item.ingreso_total="{ item }">
                {{ formatPrice(item.ingreso_total) }}
              </template>
              <template #item.participacion="{ item }">
                <VChip color="primary" size="small">
                  {{ item.participacion }}%
                </VChip>
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
            </VDataTable>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </div>
</template>

