<!-- 
  ====================================
  EJEMPLO COMPLETO - OPCIÓN 1
  ====================================
  Dashboard usando colores de Vuetify (la más simple)
-->

<script setup>
import { ref, onMounted, computed } from 'vue'
import { getDashboardStats } from '@/services/dashboard'
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
import { getStatCardConfig, chartTheme, icons } from '@/config/dashboardTheme'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, PointElement, LineElement)

const theme = useTheme()
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
  ventasSeries: [],
  topClientes: [],
  topProductos: [],
  topProveedores: [],
})

// Configuración de las 6 stat cards (sin hardcode)
const statCards = [
  { 
    key: 'clientes', 
    label: 'Clientes', 
    icon: icons.stats.clientes,
    ...getStatCardConfig(0) 
  },
  { 
    key: 'productos', 
    label: 'Productos', 
    icon: icons.stats.productos,
    ...getStatCardConfig(1) 
  },
  { 
    key: 'proveedores', 
    label: 'Proveedores', 
    icon: icons.stats.proveedores,
    ...getStatCardConfig(2) 
  },
  { 
    key: 'ventas', 
    label: 'Ventas', 
    icon: icons.stats.ventas,
    ...getStatCardConfig(3) 
  },
  { 
    key: 'ingresos', 
    label: 'Ingresos', 
    icon: icons.stats.stock,
    ...getStatCardConfig(4) 
  },
  { 
    key: 'ticketPromedio', 
    label: 'Ticket Promedio', 
    icon: icons.stats.pedidos,
    ...getStatCardConfig(5) 
  }
]

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const fetchStats = async () => {
  loading.value = true
  error.value = ''
  try {
    const data = await getDashboardStats()
    stats.value = data
  } catch (e) {
    error.value = e.message || 'Error al cargar estadísticas'
  } finally {
    loading.value = false
  }
}

// ====================================
// CONFIGURACIÓN DE GRÁFICOS (sin hardcode)
// ====================================

// Helper para obtener colores del tema actual
const getThemeColors = () => {
  const currentTheme = theme.current.value
  return {
    primary: currentTheme.colors.primary,
    success: currentTheme.colors.success,
    warning: currentTheme.colors.warning,
    error: currentTheme.colors.error,
    info: currentTheme.colors.info,
    surface: currentTheme.colors.surface,
    onSurface: currentTheme.colors['on-surface']
  }
}

// Gráfico de ventas (línea)
const ventasChartData = computed(() => {
  const colors = getThemeColors()
  return {
    labels: stats.value.ventasSeries.map(v => v.period),
    datasets: [
      {
        label: 'Ventas',
        backgroundColor: colors.success + '33', // 20% opacidad
        borderColor: colors.success,
        borderWidth: 2,
        data: stats.value.ventasSeries.map(v => v.total_neto),
        tension: 0.4,
      },
    ],
  }
})

const ventasChartOptions = computed(() => {
  const colors = getThemeColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'top',
        labels: {
          color: colors.onSurface,
          usePointStyle: true,
        }
      },
      title: {
        display: true,
        text: 'Evolución de Ventas',
        color: colors.onSurface,
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
          color: colors.onSurface + '20',
        },
        ticks: {
          color: colors.onSurface,
          callback: function(value) {
            return '$' + value.toLocaleString('es-AR')
          }
        }
      },
      x: {
        grid: {
          color: colors.onSurface + '20',
        },
        ticks: {
          color: colors.onSurface,
        }
      }
    }
  }
})

// Gráfico de clientes (barras)
const clientesChartData = computed(() => {
  const colors = getThemeColors()
  return {
    labels: stats.value.topClientes.slice(0, 5).map(c => c.nombre),
    datasets: [
      {
        label: 'Ingresos',
        backgroundColor: colors.info + '99',
        borderColor: colors.info,
        borderWidth: 1,
        data: stats.value.topClientes.slice(0, 5).map(c => c.ingreso_total),
      },
    ],
  }
})

const clientesChartOptions = computed(() => {
  const colors = getThemeColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
      legend: { display: false },
      title: {
        display: true,
        text: 'Top 5 Clientes',
        color: colors.onSurface,
        font: { size: 16, weight: 'bold' }
      },
    },
    scales: {
      x: {
        beginAtZero: true,
        grid: { color: colors.onSurface + '20' },
        ticks: {
          color: colors.onSurface,
          callback: function(value) {
            return '$' + value.toLocaleString('es-AR')
          }
        }
      },
      y: {
        grid: { display: false },
        ticks: { color: colors.onSurface }
      }
    }
  }
})

// Gráfico de productos (dona)
const productosChartData = computed(() => {
  const colors = getThemeColors()
  const themeColors = [
    colors.primary,
    colors.success,
    colors.info,
    colors.warning,
    colors.error
  ]
  
  return {
    labels: stats.value.topProductos.slice(0, 5).map(p => p.nombre),
    datasets: [
      {
        label: 'Unidades Vendidas',
        backgroundColor: themeColors,
        borderWidth: 0,
        data: stats.value.topProductos.slice(0, 5).map(p => p.unidades_vendidas),
      },
    ],
  }
})

const productosChartOptions = computed(() => {
  const colors = getThemeColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'right',
        labels: {
          color: colors.onSurface,
          padding: 15,
          usePointStyle: true,
        }
      },
      title: {
        display: true,
        text: 'Top 5 Productos',
        color: colors.onSurface,
        font: { size: 16, weight: 'bold' }
      },
    }
  }
})

onMounted(() => {
  fetchStats()
})
</script>

<template>
  <div>
    <!-- Header -->
    <VRow class="mb-6">
      <VCol cols="12">
        <div class="d-flex justify-space-between align-center">
          <div>
            <h1 class="text-h4 font-weight-bold mb-1">Dashboard</h1>
            <p class="text-body-2 text-medium-emphasis">
              Panel de control y análisis de datos
            </p>
          </div>
          <VBtn
            color="primary"
            prepend-icon="mdi-refresh"
            @click="fetchStats"
            :loading="loading"
          >
            Actualizar
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <!-- Loading & Error States -->
    <VRow v-if="loading" class="mt-4">
      <VCol cols="12" class="text-center">
        <VProgressCircular indeterminate color="primary" size="64" />
        <p class="mt-4 text-medium-emphasis">Cargando estadísticas...</p>
      </VCol>
    </VRow>

    <VAlert v-if="error" type="error" variant="tonal" class="mb-6">
      {{ error }}
    </VAlert>

    <!-- Stat Cards (sin hardcode de colores) -->
    <VRow v-if="!loading">
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
          variant="elevated"
          class="stat-card"
          elevation="2"
        >
          <VCardText class="pa-4">
            <div class="d-flex align-center justify-space-between mb-3">
              <VIcon 
                :icon="card.icon" 
                size="40"
                class="stat-icon"
              />
            </div>
            <div class="text-h4 font-weight-bold mb-1 stat-number">
              {{ 
                ['ingresos', 'ticketPromedio'].includes(card.key) 
                  ? formatPrice(stats.totales[card.key]) 
                  : stats.totales[card.key] 
              }}
            </div>
            <div class="text-caption text-uppercase stat-label">
              {{ card.label }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Charts -->
    <VRow v-if="!loading" class="mt-6">
      <!-- Ventas Chart -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardText>
            <div style="height: 300px">
              <Line :data="ventasChartData" :options="ventasChartOptions" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Clientes Chart -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardText>
            <div style="height: 300px">
              <Bar :data="clientesChartData" :options="clientesChartOptions" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Productos Chart -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardText>
            <div style="height: 300px">
              <Doughnut :data="productosChartData" :options="productosChartOptions" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Top Clientes Table -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>
            <VIcon start>mdi-account-star</VIcon>
            Top Clientes
          </VCardTitle>
          <VCardText>
            <VTable>
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
   ==================================== */

/* Tarjeta base con transición suave */
.stat-card {
  border-radius: 12px !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

/* Efecto hover */
.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
}

/* Ícono con animación */
.stat-icon {
  opacity: 0.9;
  transition: transform 0.3s ease;
}

.stat-card:hover .stat-icon {
  transform: scale(1.1) rotate(5deg);
}

/* Texto heredando colores del tema */
.stat-number {
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  letter-spacing: -0.5px;
  line-height: 1;
}

.stat-label {
  opacity: 0.9;
  font-weight: 600;
  letter-spacing: 1px;
}

/* Animación de entrada */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.stat-card {
  animation: fadeInUp 0.6s ease-out;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }
.stat-card:nth-child(5) { animation-delay: 0.5s; }
.stat-card:nth-child(6) { animation-delay: 0.6s; }

/* Responsive */
@media (max-width: 600px) {
  .stat-icon {
    font-size: 32px !important;
  }
  
  .stat-number {
    font-size: 2rem !important;
  }
  
  .stat-card:hover {
    transform: translateY(-2px);
  }
}
</style>
