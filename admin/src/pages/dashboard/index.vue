<script setup>
import { ref, onMounted, computed } from 'vue'
import { getDashboardStats } from '@/services/dashboard'
import { toast } from '@/plugins/toast'
import { Bar, Doughnut, Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  ArcElement,
  PointElement,
  LineElement,
} from 'chart.js'
import { useTheme } from 'vuetify'
import { getStatCardConfig, icons } from '@/config/dashboardTheme'
import { useDashboardTheme } from '@/composables/useDashboardTheme'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, PointElement, LineElement)

// Opción 1: Configuración simple de Vuetify
// Opción 2: Funciones helper para cálculos dinámicos
const theme = useTheme()
const { chartColors, getWeatherIcon } = useDashboardTheme()

const loading = ref(false)
const error = ref('')
const stats = ref({
  totales: {
    clientes: 0,
    productos: 0,
    proveedores: 0,
    ventas: 0,
    ingresos: 0,
    ticketPromedio: 0,
  },
  tendencias: {
    clientes: { porcentaje: '0', up: true },
    productos: { porcentaje: '0', up: true },
    proveedores: { porcentaje: '0', up: true },
    ventas: { porcentaje: '0', up: true },
    ingresos: { porcentaje: '0', up: true },
    ticketPromedio: { porcentaje: '0', up: true },
  },
  ventasSeries: [],
  topClientes: [],
  topProductos: [],
  topProveedores: [],
})

// Configuración de stat cards (Opción 1: Sin hardcode)
const statCards = computed(() => [
  { 
    key: 'clientes', 
    label: 'Total Clientes',
    subtitle: 'Clientes activos',
    icon: icons.stats.clientes,
    trend: `${stats.value.tendencias.clientes.up ? '+' : '-'}${stats.value.tendencias.clientes.porcentaje}%`,
    trendUp: stats.value.tendencias.clientes.up,
    ...getStatCardConfig(0) 
  },
  { 
    key: 'productos', 
    label: 'Productos',
    subtitle: 'En catálogo',
    icon: icons.stats.productos,
    trend: `${stats.value.tendencias.productos.up ? '+' : '-'}${stats.value.tendencias.productos.porcentaje}%`,
    trendUp: stats.value.tendencias.productos.up,
    ...getStatCardConfig(1) 
  },
  { 
    key: 'proveedores', 
    label: 'Proveedores',
    subtitle: 'Activos',
    icon: icons.stats.proveedores,
    trend: `${stats.value.tendencias.proveedores.up ? '+' : ''}${stats.value.tendencias.proveedores.porcentaje}`,
    trendUp: stats.value.tendencias.proveedores.up,
    ...getStatCardConfig(2) 
  },
  { 
    key: 'ventas', 
    label: 'Total Ventas',
    subtitle: 'Este mes',
    icon: icons.stats.ventas,
    trend: `${stats.value.tendencias.ventas.up ? '+' : '-'}${stats.value.tendencias.ventas.porcentaje}%`,
    trendUp: stats.value.tendencias.ventas.up,
    ...getStatCardConfig(3) 
  },
  { 
    key: 'ingresos', 
    label: 'Ingresos',
    subtitle: 'Totales',
    icon: icons.charts.ventas,
    trend: `${stats.value.tendencias.ingresos.up ? '+' : '-'}${stats.value.tendencias.ingresos.porcentaje}%`,
    trendUp: stats.value.tendencias.ingresos.up,
    ...getStatCardConfig(4) 
  },
  { 
    key: 'ticketPromedio', 
    label: 'Ticket Promedio',
    subtitle: 'Por venta',
    icon: icons.stats.pedidos,
    trend: `${stats.value.tendencias.ticketPromedio.up ? '+' : '-'}${stats.value.tendencias.ticketPromedio.porcentaje}%`,
    trendUp: stats.value.tendencias.ticketPromedio.up,
    ...getStatCardConfig(5) 
  }
])

const formatPrice = (value) => {
  // Formatear números grandes con K (miles) y M (millones)
  if (value >= 1000000) {
    return `$ ${(value / 1000000).toFixed(1)}M`
  } else if (value >= 1000) {
    return `$ ${(value / 1000).toFixed(1)}K`
  }
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const formatStats = (value) => {
  // Para números normales de estadísticas (clientes, productos, etc)
  if (value >= 1000000) {
    return `${(value / 1000000).toFixed(1)}M`
  } else if (value >= 1000) {
    return `${(value / 1000).toFixed(1)}K`
  }
  return value.toLocaleString('es-AR')
}

const fetchStats = async () => {
  loading.value = true
  error.value = ''
  try {
    const data = await getDashboardStats()
    stats.value = data
  } catch (e) {
    console.error('[Dashboard] Error cargando estadísticas:', e)
    
    let errorMsg = 'Error al cargar estadísticas del dashboard'
    
    // Mensajes específicos según el tipo de error
    if (e.isNotJSON) {
      errorMsg = `Error del servidor: El backend devolvió HTML en lugar de JSON. Esto indica un error 500 o una configuración incorrecta. Por favor contacta al administrador del sistema.`
    } else if (e.isNetworkError) {
      errorMsg = `No se pudo conectar al servidor. Verifica tu conexión a Internet y que el backend esté funcionando.`
    } else if (e.status === 401) {
      errorMsg = 'Sesión expirada. Redirigiendo al login...'
    } else if (e.status === 403) {
      errorMsg = 'No tienes permisos para ver el dashboard'
    } else if (e.message) {
      errorMsg = e.message
    }
    
    error.value = errorMsg
    toast.error(errorMsg)
    
    // Log adicional para debugging
    if (e.isNotJSON) {
      console.error('[Dashboard] Content-Type recibido:', e.contentType)
      console.error('[Dashboard] Respuesta (primeros 500 chars):', e.responseText?.substring(0, 500))
    }
  } finally {
    loading.value = false
  }
}

// Configuración para gráfico de ventas por período (Línea)
// Opción 2: Colores dinámicos del tema
const ventasChartData = computed(() => ({
  labels: stats.value.ventasSeries.map(v => v.period),
  datasets: [
    {
      label: 'Ventas',
      backgroundColor: chartColors.value.line[1] + '33', // Success con 20% opacidad
      borderColor: chartColors.value.line[1],
      borderWidth: 2,
      data: stats.value.ventasSeries.map(v => v.total_neto),
      tension: 0.4,
    },
  ],
}))

const ventasChartOptions = computed(() => {
  const currentTheme = theme.current.value
  const textColor = currentTheme.colors['on-surface']
  const gridColor = textColor + '20'
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'top',
        labels: {
          color: textColor,
          usePointStyle: true,
        }
      },
      title: {
        display: true,
        text: 'Evolución de Ventas',
        color: textColor,
        font: {
          size: 16,
          weight: 'bold'
        }
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: gridColor,
        },
        ticks: {
          color: textColor,
          callback: function(value) {
            return '$' + value.toLocaleString('es-AR')
          }
        }
      },
      x: {
        grid: {
          color: gridColor,
        },
        ticks: {
          color: textColor,
        }
      }
    }
  }
})

// Configuración para Top Clientes (Barras Horizontales)
const clientesChartData = computed(() => ({
  labels: stats.value.topClientes.slice(0, 5).map(c => c.nombre),
  datasets: [
    {
      label: 'Ingresos',
      backgroundColor: chartColors.value.bar,
      borderWidth: 0,
      data: stats.value.topClientes.slice(0, 5).map(c => c.ingreso_total),
    },
  ],
}))

const clientesChartOptions = computed(() => {
  const currentTheme = theme.current.value
  const textColor = currentTheme.colors['on-surface']
  const gridColor = textColor + '20'
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
      legend: {
        display: false,
      },
      title: {
        display: true,
        text: 'Top 5 Clientes',
        color: textColor,
        font: {
          size: 16,
          weight: 'bold'
        }
      },
    },
    scales: {
      x: {
        beginAtZero: true,
        grid: {
          color: gridColor,
        },
        ticks: {
          color: textColor,
          callback: function(value) {
            return '$' + value.toLocaleString('es-AR')
          }
        }
      },
      y: {
        grid: {
          display: false,
        },
        ticks: {
          color: textColor,
        }
      }
    }
  }
})

// Configuración para Top Productos (Torta)
const productosChartData = computed(() => ({
  labels: stats.value.topProductos.slice(0, 5).map(p => p.nombre),
  datasets: [
    {
      label: 'Ingresos',
      backgroundColor: chartColors.value.doughnut,
      borderWidth: 0,
      data: stats.value.topProductos.slice(0, 5).map(p => p.ingreso_total),
    },
  ],
}))

const productosChartOptions = computed(() => {
  const currentTheme = theme.current.value
  const textColor = currentTheme.colors['on-surface']
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          color: textColor,
          usePointStyle: true,
        }
      },
      title: {
        display: true,
        text: 'Top 5 Productos por Ingresos',
        color: textColor,
        font: {
          size: 16,
          weight: 'bold'
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return context.label + ': $' + context.parsed.toLocaleString('es-AR')
          }
        }
      }
    },
  }
})

// Configuración para Top Proveedores (Torta)
const proveedoresChartData = computed(() => ({
  labels: stats.value.topProveedores.slice(0, 5).map(p => p.nombre),
  datasets: [
    {
      label: 'Participación',
      backgroundColor: chartColors.value.doughnut,
      borderWidth: 0,
      data: stats.value.topProveedores.slice(0, 5).map(p => p.participacion),
    },
  ],
}))

const proveedoresChartOptions = computed(() => {
  const currentTheme = theme.current.value
  const textColor = currentTheme.colors['on-surface']
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          color: textColor,
          usePointStyle: true,
        }
      },
      title: {
        display: true,
        text: 'Participación de Proveedores',
        color: textColor,
        font: {
          size: 16,
          weight: 'bold'
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return context.label + ': ' + context.parsed + '%'
          }
        }
      }
    },
  }
})

onMounted(fetchStats)
</script>

<template>
  <div class="pa-6">
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <h2 class="text-h4">Panel de Control</h2>
      <VBtn color="primary" @click="fetchStats" :loading="loading">
        <VIcon start>mdi-refresh</VIcon>
        Actualizar
      </VBtn>
    </div>

    <VAlert v-if="error" type="error" dismissible @click:close="error = ''" class="mb-4">
      {{ error }}
    </VAlert>

    <!-- Tarjetas de Estadísticas (sin hardcode) -->
    <VRow class="mb-6">
      <VCol
        v-for="(card, index) in statCards"
        :key="card.key"
        cols="12"
        sm="6"
        md="4"
        lg="2"
      >
        <VCard 
          :color="card.color"
          :variant="card.variant"
          class="stat-card" 
          elevation="3"
        >
          <VCardText class="pa-6">
            <div class="d-flex justify-space-between align-start mb-5">
              <VIcon size="48" class="stat-icon">{{ card.icon }}</VIcon>
              <VChip
                v-if="card.trend"
                :color="card.trendUp ? 'success' : 'error'"
                size="small"
                variant="flat"
                class="stat-trend"
              >
                <VIcon 
                  start 
                  size="12"
                  :icon="card.trendUp ? 'mdi-trending-up' : 'mdi-trending-down'"
                />
                {{ card.trend }}
              </VChip>
            </div>
            
            <div class="stat-content">
              <div class="text-overline stat-subtitle mb-2">
                {{ card.subtitle }}
              </div>
              <div class="text-h4 font-weight-bold stat-number mb-2">
                {{ 
                  ['ingresos', 'ticketPromedio'].includes(card.key) 
                    ? formatPrice(stats.totales[card.key]) 
                    : formatStats(stats.totales[card.key])
                }}
              </div>
              <div class="text-body-2 stat-label">
                {{ card.label }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Gráficos -->
    <VRow>
      <!-- Gráfico de Evolución de Ventas -->
      <VCol cols="12" lg="12">
        <VCard>
          <VCardText>
            <div style="height: 300px;">
              <Line v-if="!loading && ventasChartData.labels.length > 0" :data="ventasChartData" :options="ventasChartOptions" />
              <div v-else-if="loading" class="d-flex justify-center align-center" style="height: 100%;">
                <VProgressCircular indeterminate color="primary" />
              </div>
              <div v-else class="d-flex justify-center align-center text-disabled" style="height: 100%;">
                No hay datos de ventas disponibles
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Gráfico Top Clientes -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardText>
            <div style="height: 300px;">
              <Bar v-if="!loading && clientesChartData.labels.length > 0" :data="clientesChartData" :options="clientesChartOptions" />
              <div v-else-if="loading" class="d-flex justify-center align-center" style="height: 100%;">
                <VProgressCircular indeterminate color="primary" />
              </div>
              <div v-else class="d-flex justify-center align-center text-disabled" style="height: 100%;">
                No hay datos de clientes disponibles
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Gráfico Top Productos (Torta) -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardText>
            <div style="height: 300px;">
              <Doughnut v-if="!loading && productosChartData.labels.length > 0" :data="productosChartData" :options="productosChartOptions" />
              <div v-else-if="loading" class="d-flex justify-center align-center" style="height: 100%;">
                <VProgressCircular indeterminate color="primary" />
              </div>
              <div v-else class="d-flex justify-center align-center text-disabled" style="height: 100%;">
                No hay datos de productos disponibles
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Gráfico Top Proveedores (Torta) -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardText>
            <div style="height: 300px;">
              <Doughnut v-if="!loading && proveedoresChartData.labels.length > 0" :data="proveedoresChartData" :options="proveedoresChartOptions" />
              <div v-else-if="loading" class="d-flex justify-center align-center" style="height: 100%;">
                <VProgressCircular indeterminate color="primary" />
              </div>
              <div v-else class="d-flex justify-center align-center text-disabled" style="height: 100%;">
                No hay datos de proveedores disponibles
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Tabla Top Clientes -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>Top 5 Clientes por Ingresos</VCardTitle>
          <VCardText>
            <VTable density="compact">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th class="text-right">Compras</th>
                  <th class="text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cliente in stats.topClientes.slice(0, 5)" :key="cliente.cliente_id">
                  <td>{{ cliente.nombre }}</td>
                  <td class="text-right">{{ cliente.compras }}</td>
                  <td class="text-right">{{ formatPrice(cliente.ingreso_total) }}</td>
                </tr>
                <tr v-if="stats.topClientes.length === 0">
                  <td colspan="3" class="text-center text-disabled">No hay datos disponibles</td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
/* ====================================
   ESTILOS SIN HARDCODE
   Los colores vienen del tema de Vuetify
   Se adaptan automáticamente a modo claro/oscuro
   ==================================== */

/* Estilos para las tarjetas de estadísticas */
.stat-card {
  border-radius: 12px !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
  border: none !important;
  cursor: pointer;
}

/* Overlay sutil para profundidad */
.stat-card::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
  opacity: 0;
  transition: opacity 0.5s ease;
  pointer-events: none;
}

.stat-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: 0 16px 32px rgba(0, 0, 0, 0.25) !important;
}

.stat-card:hover::before {
  opacity: 1;
  animation: shimmer 2s infinite;
}

@keyframes shimmer {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Ícono directo - sin wrapper */
.stat-icon {
  opacity: 0.95;
  filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.2));
  transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
  transform: scale(1.15) rotate(-8deg);
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
}

/* Asegurar contraste en temas tonal */
.v-card--variant-tonal .stat-icon,
.v-card--variant-tonal .stat-subtitle,
.v-card--variant-tonal .stat-number,
.v-card--variant-tonal .stat-label {
  color: rgb(var(--v-theme-on-surface)) !important;
  text-shadow: none !important;
}

/* Mantener colores blancos en elevated */
.v-card--variant-elevated .stat-icon,
.v-card--variant-elevated .stat-subtitle,
.v-card--variant-elevated .stat-number,
.v-card--variant-elevated .stat-label {
  color: white !important;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

/* Trend chip */
.stat-trend {
  font-weight: 600;
  font-size: 0.75rem;
  height: 24px;
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

/* Contenido de la stat card */
.stat-content {
  position: relative;
  z-index: 1;
}

/* Subtítulo */
.stat-subtitle {
  font-weight: 600;
  letter-spacing: 0.5px;
  font-size: 0.7rem;
  opacity: 0.85;
}

/* Número de estadística */
.stat-number {
  letter-spacing: -0.5px;
  line-height: 1.2;
  font-size: 1.5rem !important;
  font-weight: 700 !important;
  white-space: nowrap;
  word-break: break-all;
}

/* Etiqueta de estadística */
.stat-label {
  font-weight: 500;
  line-height: 1.3;
  opacity: 0.95;
}

/* Animación de entrada */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.stat-card {
  animation: fadeInUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }
.stat-card:nth-child(5) { animation-delay: 0.25s; }
.stat-card:nth-child(6) { animation-delay: 0.3s; }

/* Efecto de brillo al pasar el mouse */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.85; }
}

.stat-card:hover .stat-number {
  animation: pulse 2s infinite;
}

/* Responsive - ajustes para móviles */
@media (max-width: 600px) {
  .stat-icon {
    font-size: 40px !important;
  }
  
  .stat-number {
    font-size: 1.5rem !important;
  }
  
  .stat-card:hover {
    transform: translateY(-4px) scale(1.01);
  }
  
  .stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(-5deg);
  }
}

/* Ajustes para tablets */
@media (min-width: 601px) and (max-width: 960px) {
  .stat-number {
    font-size: 1.6rem !important;
  }
}
</style>
