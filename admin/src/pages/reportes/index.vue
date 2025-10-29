<script setup>
import { ref, onMounted } from 'vue'
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

const reporteClientes = ref([])
const reporteProductos = ref([])
const reporteProveedores = ref([])
const reporteVentas = ref([])

const headersClientes = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Email', key: 'email' },
  { title: 'Teléfono', key: 'telefono' },
  { title: 'Dirección', key: 'direccion' },
]

const headersProductos = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Categoría', key: 'categoria' },
  { title: 'Precio', key: 'precio' },
  { title: 'Stock', key: 'stock' },
]

const headersProveedores = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Email', key: 'email' },
  { title: 'Teléfono', key: 'telefono' },
  { title: 'CUIT', key: 'cuit' },
]

const headersVentas = [
  { title: 'ID', key: 'id' },
  { title: 'Cliente', key: 'cliente.nombre' },
  { title: 'Fecha', key: 'fecha' },
  { title: 'Total', key: 'total' },
  { title: 'Estado', key: 'estado' },
]

const fetchReportes = async () => {
  loading.value = true
  error.value = ''
  try {
    const [clientes, productos, proveedores, ventas] = await Promise.all([
      getReporteClientes(),
      getReporteProductos(),
      getReporteProveedores(),
      getReporteVentas(),
    ])
    
    reporteClientes.value = Array.isArray(clientes) ? clientes : (clientes.data ?? [])
    reporteProductos.value = Array.isArray(productos) ? productos : (productos.data ?? [])
    reporteProveedores.value = Array.isArray(proveedores) ? proveedores : (proveedores.data ?? [])
    reporteVentas.value = Array.isArray(ventas) ? ventas : (ventas.data ?? [])
  } catch (e) {
    error.value = e.message || 'Error al cargar reportes'
  } finally {
    loading.value = false
  }
}

const exportar = (tipo, formato) => {
  try {
    const exportFunctions = {
      clientes: {
        excel: exportClientesExcel,
        csv: exportClientesCSV,
      },
      productos: {
        excel: exportProductosExcel,
        csv: exportProductosCSV,
      },
      proveedores: {
        excel: exportProveedoresExcel,
        csv: exportProveedoresCSV,
      },
      ventas: {
        excel: exportVentasExcel,
        csv: exportVentasCSV,
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
        <div class="d-flex justify-space-between align-center">
          <span class="text-h5">Reportes</span>
          <VBtn color="primary" @click="fetchReportes" :loading="loading">
            <VIcon start>mdi-refresh</VIcon>
            Actualizar
          </VBtn>
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
            />
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
              <template #item.precio="{ item }">
                {{ formatPrice(item.precio) }}
              </template>
              <template #item.stock="{ item }">
                <VChip :color="item.stock > 10 ? 'success' : item.stock > 0 ? 'warning' : 'error'" size="small">
                  {{ item.stock }}
                </VChip>
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
            />
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
              <template #item.total="{ item }">
                {{ formatPrice(item.total) }}
              </template>
              <template #item.estado="{ item }">
                <VChip :color="getEstadoColor(item.estado)" size="small">
                  {{ item.estado }}
                </VChip>
              </template>
            </VDataTable>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </div>
</template>

