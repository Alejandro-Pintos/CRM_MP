/**
 * ====================================
 * CONFIGURACIÓN DE TEMA DEL DASHBOARD
 * ====================================
 * 
 * Este archivo centraliza todos los valores personalizables del dashboard.
 * No hay valores hardcodeados en los componentes, todo se define aquí.
 * 
 * OPCIÓN 1: Usando colores predefinidos de Vuetify
 * OPCIÓN 2: Usando composables dinámicos
 * OPCIÓN 3: Usando CSS-in-JS con theme tokens
 */

// =======================
// OPCIÓN 1: COLORES DE VUETIFY
// =======================
// Usa los colores ya definidos en src/plugins/vuetify/theme.js
export const statCardTheme = {
  cards: [
    {
      color: 'info',           // Azul para clientes (confianza, profesional)
      variant: 'tonal',
      gradientStart: 'info',
      gradientEnd: 'info-darken-1',
      textColor: 'white'
    },
    {
      color: 'success',        // Verde para productos (inventario, disponibilidad)
      variant: 'tonal',
      gradientStart: 'success',
      gradientEnd: 'success-darken-1',
      textColor: 'white'
    },
    {
      color: 'secondary',      // Gris para proveedores (neutral, corporativo)
      variant: 'tonal',
      gradientStart: 'secondary',
      gradientEnd: 'secondary-darken-1',
      textColor: 'white'
    },
    {
      color: 'primary',        // Azul principal para ventas (importante)
      variant: 'tonal',
      gradientStart: 'primary',
      gradientEnd: 'primary-darken-1',
      textColor: 'white'
    },
    {
      color: 'success',        // Verde para ingresos (dinero, ganancias)
      variant: 'elevated',
      gradientStart: 'success',
      gradientEnd: 'success-darken-1',
      textColor: 'white'
    },
    {
      color: 'warning',        // Naranja para ticket promedio (atención, métrica)
      variant: 'tonal',
      gradientStart: 'warning',
      gradientEnd: 'warning-darken-1',
      textColor: 'white'
    }
  ]
}

// =======================
// CONFIGURACIÓN DE GRÁFICOS
// =======================
export const chartTheme = {
  // Colores para las líneas de gráficos
  lineColors: [
    'rgb(var(--v-theme-primary))',
    'rgb(var(--v-theme-success))',
    'rgb(var(--v-theme-warning))',
    'rgb(var(--v-theme-error))'
  ],
  
  // Colores para barras
  barColors: [
    'rgb(var(--v-theme-info))',
    'rgb(var(--v-theme-success))',
    'rgb(var(--v-theme-warning))',
    'rgb(var(--v-theme-error))'
  ],
  
  // Colores para gráfico de torta/dona
  doughnutColors: [
    'rgb(var(--v-theme-primary))',
    'rgb(var(--v-theme-success))',
    'rgb(var(--v-theme-info))',
    'rgb(var(--v-theme-warning))',
    'rgb(var(--v-theme-error))',
    'rgb(var(--v-theme-secondary))'
  ],
  
  // Configuración de grid y texto
  grid: {
    color: 'rgb(var(--v-border-color))',
    borderColor: 'rgb(var(--v-border-color))'
  },
  
  text: {
    color: 'rgb(var(--v-theme-on-surface))'
  }
}

// =======================
// ICONOS Y SÍMBOLOS
// =======================
export const icons = {
  stats: {
    ventas: 'mdi-chart-line-variant',
    productos: 'mdi-package-variant-closed',
    clientes: 'mdi-account-group-outline',
    proveedores: 'mdi-truck-delivery-outline',
    pedidos: 'mdi-cart-variant',
    stock: 'mdi-archive-outline'
  },
  
  charts: {
    ventas: 'mdi-trending-up',
    productos: 'mdi-chart-bar',
    categorias: 'mdi-chart-donut',
    tendencias: 'mdi-chart-timeline-variant'
  },
  
  weather: {
    clear: '☀️',
    clouds: '☁️',
    rain: '🌧️',
    snow: '❄️',
    thunderstorm: '⛈️',
    drizzle: '🌦️',
    mist: '🌫️',
    default: '🌤️'
  }
}

// =======================
// MAPEO DE ICONOS DE CLIMA
// =======================
export const weatherIconMap = {
  'soleado': icons.weather.clear,
  'clear': icons.weather.clear,
  'parcialmente nublado': icons.weather.clouds,
  'clouds': icons.weather.clouds,
  'nublado': icons.weather.clouds,
  'lluvia': icons.weather.rain,
  'rain': icons.weather.rain,
  'llovizna': icons.weather.drizzle,
  'drizzle': icons.weather.drizzle,
  'tormenta': icons.weather.thunderstorm,
  'thunderstorm': icons.weather.thunderstorm,
  'nieve': icons.weather.snow,
  'snow': icons.weather.snow,
  'niebla': icons.weather.mist,
  'mist': icons.weather.mist
}

// =======================
// ANIMACIONES Y TRANSICIONES
// =======================
export const animations = {
  duration: {
    fast: 150,
    normal: 300,
    slow: 500
  },
  
  easing: {
    standard: 'cubic-bezier(0.4, 0.0, 0.2, 1)',
    decelerate: 'cubic-bezier(0.0, 0.0, 0.2, 1)',
    accelerate: 'cubic-bezier(0.4, 0.0, 1, 1)'
  }
}

// =======================
// ESPACIADO Y LAYOUT
// =======================
export const layout = {
  cardPadding: 4,
  sectionGap: 6,
  chartHeight: {
    small: 250,
    medium: 350,
    large: 450
  }
}

// =======================
// FUNCIÓN HELPER PARA OBTENER COLOR DE STAT CARD
// =======================
export function getStatCardConfig(index) {
  const cardIndex = index % statCardTheme.cards.length
  return statCardTheme.cards[cardIndex]
}

// =======================
// FUNCIÓN HELPER PARA OBTENER ICONO DE CLIMA
// =======================
export function getWeatherIcon(estado) {
  if (!estado) return icons.weather.default
  
  const estadoLower = estado.toLowerCase()
  
  for (const [key, icon] of Object.entries(weatherIconMap)) {
    if (estadoLower.includes(key)) {
      return icon
    }
  }
  
  return icons.weather.default
}

export default {
  statCardTheme,
  chartTheme,
  icons,
  weatherIconMap,
  animations,
  layout,
  getStatCardConfig,
  getWeatherIcon
}
