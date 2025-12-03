/**
 * ====================================
 * COMPOSABLE DE TEMA DINÁMICO
 * ====================================
 * 
 * OPCIÓN 2: Composable para gestión dinámica de temas
 * 
 * Este composable proporciona acceso programático a los colores del tema
 * de Vuetify y permite generar variaciones dinámicas.
 * 
 * Ventajas:
 * - Totalmente reactivo
 * - Calcula colores en tiempo real
 * - Soporta modo claro/oscuro automáticamente
 * - Permite personalización por componente
 */

import { computed } from 'vue'
import { useTheme } from 'vuetify'

export function useDashboardTheme() {
  const theme = useTheme()

  /**
   * Obtener el color actual del tema
   */
  const getThemeColor = (colorName) => {
    return computed(() => {
      const currentTheme = theme.current.value
      return currentTheme.colors[colorName]
    })
  }

  /**
   * Colores para las stat cards (sin hardcode)
   */
  const statCardColors = computed(() => [
    {
      name: 'Ventas',
      color: theme.current.value.colors.primary,
      gradient: {
        start: theme.current.value.colors.primary,
        end: adjustColorBrightness(theme.current.value.colors.primary, -20)
      },
      icon: 'mdi-currency-usd',
      lightText: isColorDark(theme.current.value.colors.primary)
    },
    {
      name: 'Productos',
      color: theme.current.value.colors.success,
      gradient: {
        start: theme.current.value.colors.success,
        end: adjustColorBrightness(theme.current.value.colors.success, -20)
      },
      icon: 'mdi-package-variant',
      lightText: isColorDark(theme.current.value.colors.success)
    },
    {
      name: 'Clientes',
      color: theme.current.value.colors.info,
      gradient: {
        start: theme.current.value.colors.info,
        end: adjustColorBrightness(theme.current.value.colors.info, -20)
      },
      icon: 'mdi-account-group',
      lightText: isColorDark(theme.current.value.colors.info)
    },
    {
      name: 'Proveedores',
      color: theme.current.value.colors.warning,
      gradient: {
        start: theme.current.value.colors.warning,
        end: adjustColorBrightness(theme.current.value.colors.warning, -20)
      },
      icon: 'mdi-truck',
      lightText: isColorDark(theme.current.value.colors.warning)
    },
    {
      name: 'Pedidos',
      color: theme.current.value.colors.error,
      gradient: {
        start: theme.current.value.colors.error,
        end: adjustColorBrightness(theme.current.value.colors.error, -20)
      },
      icon: 'mdi-cart',
      lightText: isColorDark(theme.current.value.colors.error)
    },
    {
      name: 'Stock',
      color: theme.current.value.colors.secondary,
      gradient: {
        start: theme.current.value.colors.secondary,
        end: adjustColorBrightness(theme.current.value.colors.secondary, -20)
      },
      icon: 'mdi-warehouse',
      lightText: isColorDark(theme.current.value.colors.secondary)
    }
  ])

  /**
   * Colores para gráficos (Chart.js)
   */
  const chartColors = computed(() => ({
    line: [
      theme.current.value.colors.primary,
      theme.current.value.colors.success,
      theme.current.value.colors.warning,
      theme.current.value.colors.error
    ],
    bar: [
      theme.current.value.colors.info,
      theme.current.value.colors.success,
      theme.current.value.colors.warning,
      theme.current.value.colors.error
    ],
    doughnut: [
      theme.current.value.colors.primary,
      theme.current.value.colors.success,
      theme.current.value.colors.info,
      theme.current.value.colors.warning,
      theme.current.value.colors.error,
      theme.current.value.colors.secondary
    ],
    background: theme.current.value.colors.surface,
    text: theme.current.value.colors['on-surface'],
    grid: theme.current.value.colors['surface-variant']
  }))

  /**
   * Configuración de clima con colores del tema
   */
  const weatherConfig = computed(() => ({
    card: {
      normal: theme.current.value.colors.info,
      warning: theme.current.value.colors.warning,
      error: theme.current.value.colors.error
    },
    forecast: {
      selected: theme.current.value.colors.primary,
      normal: theme.current.value.colors.surface
    },
    chips: {
      tempMax: theme.current.value.colors.error,
      tempMin: theme.current.value.colors.info,
      humidity: theme.current.value.colors.success,
      rain: theme.current.value.colors.primary
    }
  }))

  /**
   * Iconos de clima (sin hardcode de emojis)
   */
  const weatherIcons = {
    clear: { icon: 'mdi-weather-sunny', color: 'warning' },
    clouds: { icon: 'mdi-weather-cloudy', color: 'info' },
    rain: { icon: 'mdi-weather-rainy', color: 'primary' },
    drizzle: { icon: 'mdi-weather-partly-rainy', color: 'info' },
    thunderstorm: { icon: 'mdi-weather-lightning', color: 'error' },
    snow: { icon: 'mdi-weather-snowy', color: 'info' },
    mist: { icon: 'mdi-weather-fog', color: 'secondary' },
    default: { icon: 'mdi-weather-partly-cloudy', color: 'info' }
  }

  /**
   * Obtener icono de clima basado en el estado
   */
  const getWeatherIcon = (estado) => {
    if (!estado) return weatherIcons.default

    const estadoLower = estado.toLowerCase()
    
    if (estadoLower.includes('clear') || estadoLower.includes('soleado')) {
      return weatherIcons.clear
    }
    if (estadoLower.includes('cloud') || estadoLower.includes('nublado')) {
      return weatherIcons.clouds
    }
    if (estadoLower.includes('rain') || estadoLower.includes('lluvia')) {
      return weatherIcons.rain
    }
    if (estadoLower.includes('drizzle') || estadoLower.includes('llovizna')) {
      return weatherIcons.drizzle
    }
    if (estadoLower.includes('thunder') || estadoLower.includes('tormenta')) {
      return weatherIcons.thunderstorm
    }
    if (estadoLower.includes('snow') || estadoLower.includes('nieve')) {
      return weatherIcons.snow
    }
    if (estadoLower.includes('mist') || estadoLower.includes('niebla')) {
      return weatherIcons.mist
    }
    
    return weatherIcons.default
  }

  /**
   * Generar gradiente CSS a partir de dos colores
   */
  const getGradient = (colorStart, colorEnd, angle = 135) => {
    return `linear-gradient(${angle}deg, ${colorStart} 0%, ${colorEnd} 100%)`
  }

  /**
   * Obtener estilo de stat card por índice
   */
  const getStatCardStyle = (index) => {
    const card = statCardColors.value[index % statCardColors.value.length]
    return {
      background: getGradient(card.gradient.start, card.gradient.end),
      color: card.lightText ? '#FFFFFF' : '#000000'
    }
  }

  return {
    theme,
    getThemeColor,
    statCardColors,
    chartColors,
    weatherConfig,
    weatherIcons,
    getWeatherIcon,
    getGradient,
    getStatCardStyle
  }
}

/**
 * ====================================
 * FUNCIONES HELPER PARA MANIPULACIÓN DE COLORES
 * ====================================
 */

/**
 * Ajustar brillo de un color hexadecimal
 * @param {string} color - Color en formato hex (#RRGGBB)
 * @param {number} percent - Porcentaje de ajuste (-100 a 100)
 * @returns {string} Color ajustado en formato hex
 */
function adjustColorBrightness(color, percent) {
  const num = parseInt(color.replace('#', ''), 16)
  const amt = Math.round(2.55 * percent)
  const R = Math.min(255, Math.max(0, (num >> 16) + amt))
  const G = Math.min(255, Math.max(0, (num >> 8 & 0x00FF) + amt))
  const B = Math.min(255, Math.max(0, (num & 0x0000FF) + amt))
  return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1).toUpperCase()
}

/**
 * Determinar si un color es oscuro (necesita texto blanco)
 * @param {string} color - Color en formato hex (#RRGGBB)
 * @returns {boolean} true si el color es oscuro
 */
function isColorDark(color) {
  const hex = color.replace('#', '')
  const r = parseInt(hex.substr(0, 2), 16)
  const g = parseInt(hex.substr(2, 2), 16)
  const b = parseInt(hex.substr(4, 2), 16)
  
  // Fórmula de luminancia relativa (WCAG)
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
  
  return luminance < 0.5
}

/**
 * Convertir color hex a RGB
 * @param {string} hex - Color en formato hex (#RRGGBB)
 * @returns {object} {r, g, b}
 */
function hexToRgb(hex) {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
  return result ? {
    r: parseInt(result[1], 16),
    g: parseInt(result[2], 16),
    b: parseInt(result[3], 16)
  } : null
}

/**
 * Convertir RGB a hex
 * @param {number} r - Componente rojo (0-255)
 * @param {number} g - Componente verde (0-255)
 * @param {number} b - Componente azul (0-255)
 * @returns {string} Color en formato hex
 */
function rgbToHex(r, g, b) {
  return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase()
}

export {
  adjustColorBrightness,
  isColorDark,
  hexToRgb,
  rgbToHex
}
