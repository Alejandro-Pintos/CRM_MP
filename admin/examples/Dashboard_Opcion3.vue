<!-- 
  ====================================
  EJEMPLO COMPLETO - OPCIÓN 3
  ====================================
  Dashboard usando Sistema Avanzado de Theming
-->

<script setup>
import { ref, onMounted, computed, inject } from 'vue'
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
import { useAdvancedTheme } from '@/composables/useAdvancedTheme'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, PointElement, LineElement)

// Usar el sistema avanzado de theming
const vuetifyTheme = useTheme()
const { 
  theme, 
  tokens, 
  getStatCardStyle, 
  getStatCard,
  getChartOptions,
  statCardColors,
  chartColors
} = useAdvancedTheme(vuetifyTheme)

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
// CONFIGURACIÓN DE GRÁFICOS CON TEMA AVANZADO
// ====================================

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

const ventasChartOptions = computed(() => {
  const baseOptions = getChartOptions('line')
  return {
    ...baseOptions,
    plugins: {
      ...baseOptions.plugins,
      title: {
        ...baseOptions.plugins.title,
        text: 'Evolución de Ventas'
      }
    },
    scales: {
      ...baseOptions.scales,
      y: {
        ...baseOptions.scales.y,
        ticks: {
          ...baseOptions.scales.y.ticks,
          callback: function(value) {
            return '$' + value.toLocaleString('es-AR')
          }
        }
      }
    }
  }
})

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

const clientesChartOptions = computed(() => {
  const baseOptions = getChartOptions('bar')
  return {
    ...baseOptions,
    indexAxis: 'y',
    plugins: {
      ...baseOptions.plugins,
      title: {
        ...baseOptions.plugins.title,
        text: 'Top 5 Clientes'
      },
      legend: { display: false }
    },
    scales: {
      x: {
        ...baseOptions.scales.x,
        ticks: {
          ...baseOptions.scales.x.ticks,
          callback: function(value) {
            return '$' + value.toLocaleString('es-AR')
          }
        }
      },
      y: {
        ...baseOptions.scales.y,
        grid: { display: false }
      }
    }
  }
})

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

const productosChartOptions = computed(() => {
  const baseOptions = getChartOptions('doughnut')
  return {
    ...baseOptions,
    plugins: {
      ...baseOptions.plugins,
      title: {
        ...baseOptions.plugins.title,
        text: 'Top 5 Productos'
      },
      legend: {
        position: 'right',
        labels: {
          ...baseOptions.plugins.legend.labels,
          padding: 15,
          usePointStyle: true
        }
      }
    }
  }
})

// Mapeo de IDs a keys de stats
const statMapping = ['clientes', 'productos', 'proveedores', 'ventas', 'ingresos', 'ticketPromedio']

onMounted(() => {
  fetchStats()
})
</script>

<template>
  <div>
    <!-- Header con design tokens -->
    <VRow class="mb-6">
      <VCol cols="12">
        <div 
          class="d-flex justify-space-between align-center" 
          :style="{ 
            padding: tokens.spacing.md,
            borderRadius: tokens.borderRadius.lg
          }"
        >
          <div>
            <h1 
              class="font-weight-bold mb-1" 
              :style="{ 
                fontSize: tokens.typography.fontSize['3xl'],
                fontFamily: tokens.typography.fontFamily.primary
              }"
            >
              Dashboard
            </h1>
            <p 
              class="text-medium-emphasis"
              :style="{ fontSize: tokens.typography.fontSize.sm }"
            >
              Panel de control y análisis de datos
            </p>
          </div>
          <VBtn
            color="primary"
            prepend-icon="mdi-refresh"
            @click="fetchStats"
            :loading="loading"
            :style="{ borderRadius: tokens.borderRadius.md }"
          >
            Actualizar
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <!-- Loading State -->
    <VRow v-if="loading" class="mt-4">
      <VCol cols="12" class="text-center">
        <VProgressCircular indeterminate color="primary" size="64" />
        <p 
          class="mt-4 text-medium-emphasis"
          :style="{ fontSize: tokens.typography.fontSize.sm }"
        >
          Cargando estadísticas...
        </p>
      </VCol>
    </VRow>

    <VAlert 
      v-if="error" 
      type="error" 
      variant="tonal" 
      class="mb-6"
      :style="{ borderRadius: tokens.borderRadius.md }"
    >
      {{ error }}
    </VAlert>

    <!-- Stat Cards con sistema avanzado de theming -->
    <VRow v-if="!loading">
      <VCol
        v-for="(card, index) in statCardColors"
        :key="card.id"
        cols="12"
        sm="6"
        md="4"
        lg="2"
      >
        <div
          class="stat-card"
          :style="{
            ...getStatCardStyle(index),
            padding: tokens.spacing.md
          }"
        >
          <div class="d-flex align-center justify-space-between mb-3">
            <VIcon 
              :icon="card.icon" 
              size="40"
              class="stat-icon"
              :style="{ color: card.textColor }"
            />
          </div>
          <div 
            class="font-weight-bold mb-1 stat-number"
            :style="{ 
              fontSize: tokens.typography.fontSize['2xl'],
              color: card.textColor,
              fontFamily: tokens.typography.fontFamily.primary
            }"
          >
            {{ 
              index === 4 || index === 5
                ? formatPrice(stats.totales[statMapping[index]]) 
                : stats.totales[statMapping[index]] 
            }}
          </div>
          <div 
            class="text-uppercase stat-label"
            :style="{ 
              fontSize: tokens.typography.fontSize.xs,
              color: card.textColor,
              opacity: tokens.opacity.activated,
              letterSpacing: '1px',
              fontWeight: tokens.typography.fontWeight.semibold
            }"
          >
            {{ card.label }}
          </div>
        </div>
      </VCol>
    </VRow>

    <!-- Charts con opciones automáticas -->
    <VRow v-if="!loading" :style="{ marginTop: tokens.spacing.xl }">
      <!-- Ventas Chart -->
      <VCol cols="12" md="6">
        <VCard :style="{ borderRadius: tokens.borderRadius.lg }">
          <VCardText>
            <div style="height: 300px">
              <Line :data="ventasChartData" :options="ventasChartOptions" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Clientes Chart -->
      <VCol cols="12" md="6">
        <VCard :style="{ borderRadius: tokens.borderRadius.lg }">
          <VCardText>
            <div style="height: 300px">
              <Bar :data="clientesChartData" :options="clientesChartOptions" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Productos Chart -->
      <VCol cols="12" md="6">
        <VCard :style="{ borderRadius: tokens.borderRadius.lg }">
          <VCardText>
            <div style="height: 300px">
              <Doughnut :data="productosChartData" :options="productosChartOptions" />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Top Clientes Table -->
      <VCol cols="12" md="6">
        <VCard :style="{ borderRadius: tokens.borderRadius.lg }">
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
   ESTILOS MÍNIMOS
   La mayoría vienen de design tokens
   ==================================== */

.stat-card {
  position: relative;
  overflow: hidden;
  cursor: pointer;
}

.stat-card:hover {
  transform: translateY(-4px);
}

.stat-icon {
  opacity: 0.9;
  transition: transform 0.3s ease;
}

.stat-card:hover .stat-icon {
  transform: scale(1.1) rotate(5deg);
}

.stat-number {
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  letter-spacing: -0.5px;
  line-height: 1;
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
  .stat-card:hover {
    transform: translateY(-2px);
  }
}
</style>
