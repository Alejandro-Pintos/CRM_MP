<!-- 
  ====================================
  EJEMPLO COMPLETO - OPCIÓN 2
  ====================================
  Dashboard usando Composable Dinámico
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
import { useDashboardTheme } from '@/composables/useDashboardTheme'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, PointElement, LineElement)

// Usar el composable de tema (totalmente reactivo)
const { 
  statCardColors, 
  chartColors, 
  getStatCardStyle,
  getGradient
} = useDashboardTheme()

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
// CONFIGURACIÓN DE GRÁFICOS REACTIVA
// ====================================

// Los colores se actualizan automáticamente cuando cambia el tema
const ventasChartData = computed(() => ({
  labels: stats.value.ventasSeries.map(v => v.period),
  datasets: [
    {
      label: 'Ventas',
      backgroundColor: chartColors.value.line.backgroundColor[0],
      borderColor: chartColors.value.line.borderColor[0],
      borderWidth: 2,
      data: stats.value.ventasSeries.map(v => v.total_neto),
      tension: 0.4,
    },
  ],
}))

const ventasChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: true,
      position: 'top',
      labels: {
        color: chartColors.value.text.color,
        usePointStyle: true,
      }
    },
    title: {
      display: true,
      text: 'Evolución de Ventas',
      color: chartColors.value.text.color,
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
        color: chartColors.value.grid.color,
      },
      ticks: {
        color: chartColors.value.text.color,
        callback: function(value) {
          return '$' + value.toLocaleString('es-AR')
        }
      }
    },
    x: {
      grid: {
        color: chartColors.value.grid.color,
      },
      ticks: {
        color: chartColors.value.text.color,
      }
    }
  }
}))

const clientesChartData = computed(() => ({
  labels: stats.value.topClientes.slice(0, 5).map(c => c.nombre),
  datasets: [
    {
      label: 'Ingresos',
      backgroundColor: chartColors.value.bar.backgroundColor,
      borderWidth: 0,
      data: stats.value.topClientes.slice(0, 5).map(c => c.ingreso_total),
    },
  ],
}))

const clientesChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: {
    legend: { display: false },
    title: {
      display: true,
      text: 'Top 5 Clientes',
      color: chartColors.value.text.color,
      font: { size: 16, weight: 'bold' }
    },
  },
  scales: {
    x: {
      beginAtZero: true,
      grid: {
        color: chartColors.value.grid.color,
      },
      ticks: {
        color: chartColors.value.text.color,
        callback: function(value) {
          return '$' + value.toLocaleString('es-AR')
        }
      }
    },
    y: {
      grid: { display: false },
      ticks: {
        color: chartColors.value.text.color,
      }
    }
  }
}))

const productosChartData = computed(() => ({
  labels: stats.value.topProductos.slice(0, 5).map(p => p.nombre),
  datasets: [
    {
      label: 'Unidades Vendidas',
      backgroundColor: chartColors.value.doughnut.backgroundColor,
      borderWidth: 0,
      data: stats.value.topProductos.slice(0, 5).map(p => p.unidades_vendidas),
    },
  ],
}))

const productosChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'right',
      labels: {
        color: chartColors.value.text.color,
        padding: 15,
        usePointStyle: true,
      }
    },
    title: {
      display: true,
      text: 'Top 5 Productos',
      color: chartColors.value.text.color,
      font: { size: 16, weight: 'bold' }
    },
  }
}))

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

    <!-- Stat Cards usando composable dinámico -->
    <VRow v-if="!loading">
      <VCol
        v-for="(card, index) in statCardColors"
        :key="index"
        cols="12"
        sm="6"
        md="4"
        lg="2"
      >
        <div
          class="stat-card elevation-2"
          :style="getStatCardStyle(index)"
        >
          <div class="stat-card-content">
            <div class="d-flex align-center justify-space-between mb-3">
              <VIcon 
                :icon="card.icon" 
                size="40"
                class="stat-icon"
              />
            </div>
            <div class="text-h4 font-weight-bold mb-1 stat-number">
              {{ 
                index === 4 || index === 5
                  ? formatPrice(stats.totales[['clientes', 'productos', 'proveedores', 'ventas', 'ingresos', 'ticketPromedio'][index]]) 
                  : stats.totales[['clientes', 'productos', 'proveedores', 'ventas', 'ingresos', 'ticketPromedio'][index]] 
              }}
            </div>
            <div class="text-caption text-uppercase stat-label">
              {{ card.name }}
            </div>
          </div>
        </div>
      </VCol>
    </VRow>

    <!-- Charts (colores reactivos) -->
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
   ESTILOS DINÁMICOS
   Los colores vienen del composable
   ==================================== */

.stat-card {
  border-radius: 12px !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.stat-card-content {
  padding: 16px;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
}

.stat-icon {
  opacity: 0.9;
  transition: transform 0.3s ease;
  color: inherit !important;
}

.stat-card:hover .stat-icon {
  transform: scale(1.1) rotate(5deg);
}

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
