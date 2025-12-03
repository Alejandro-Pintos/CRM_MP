/**
 * ====================================
 * PLUGIN DE TEMA AVANZADO
 * ====================================
 * 
 * OPCIÓN 3: Sistema de CSS-in-JS con tokens de tema
 * 
 * Este plugin proporciona un sistema completo de theming con:
 * - Tokens de diseño semánticos
 * - Generación dinámica de estilos
 * - Soporte para temas personalizados
 * - Integración profunda con Vuetify
 * 
 * Ventajas:
 * - Máxima flexibilidad
 * - Sistema de diseño completo
 * - Fácil creación de temas personalizados
 * - Consistencia visual garantizada
 */

import { computed, reactive } from 'vue'

/**
 * ====================================
 * TOKENS DE DISEÑO
 * ====================================
 */
export const designTokens = {
  // Espaciado base (múltiplos de 4px)
  spacing: {
    xs: '4px',
    sm: '8px',
    md: '16px',
    lg: '24px',
    xl: '32px',
    xxl: '48px'
  },

  // Radio de bordes
  borderRadius: {
    none: '0',
    sm: '4px',
    md: '8px',
    lg: '12px',
    xl: '16px',
    full: '9999px'
  },

  // Sombras
  elevation: {
    0: 'none',
    1: '0 2px 4px rgba(0,0,0,0.1)',
    2: '0 4px 8px rgba(0,0,0,0.12)',
    3: '0 8px 16px rgba(0,0,0,0.14)',
    4: '0 12px 24px rgba(0,0,0,0.16)',
    5: '0 16px 32px rgba(0,0,0,0.18)'
  },

  // Tipografía
  typography: {
    fontFamily: {
      primary: '"Public Sans", sans-serif',
      monospace: '"Fira Code", monospace'
    },
    fontSize: {
      xs: '0.75rem',    // 12px
      sm: '0.875rem',   // 14px
      base: '1rem',     // 16px
      lg: '1.125rem',   // 18px
      xl: '1.25rem',    // 20px
      '2xl': '1.5rem',  // 24px
      '3xl': '1.875rem' // 30px
    },
    fontWeight: {
      light: 300,
      normal: 400,
      medium: 500,
      semibold: 600,
      bold: 700
    },
    lineHeight: {
      tight: 1.2,
      normal: 1.5,
      relaxed: 1.75
    }
  },

  // Transiciones
  transition: {
    duration: {
      fast: '150ms',
      normal: '300ms',
      slow: '500ms'
    },
    timing: {
      linear: 'linear',
      ease: 'ease',
      easeIn: 'cubic-bezier(0.4, 0, 1, 1)',
      easeOut: 'cubic-bezier(0, 0, 0.2, 1)',
      easeInOut: 'cubic-bezier(0.4, 0, 0.2, 1)'
    }
  },

  // Opacidad
  opacity: {
    disabled: 0.38,
    hover: 0.04,
    selected: 0.08,
    activated: 0.12,
    focus: 0.12,
    dragged: 0.16
  }
}

/**
 * ====================================
 * TEMA DEL DASHBOARD
 * ====================================
 */
export class DashboardTheme {
  constructor(vuetifyTheme) {
    this.vuetifyTheme = vuetifyTheme
    this.customColors = reactive({
      statCards: [],
      charts: {},
      weather: {}
    })
    
    this.initialize()
  }

  /**
   * Inicializar tema
   */
  initialize() {
    this.updateStatCards()
    this.updateChartColors()
    this.updateWeatherColors()
  }

  /**
   * Actualizar colores de stat cards
   */
  updateStatCards() {
    const theme = this.vuetifyTheme.current.value
    
    this.customColors.statCards = [
      {
        id: 'ventas',
        label: 'Ventas Totales',
        icon: 'mdi-currency-usd',
        color: theme.colors.primary,
        gradient: this.createGradient(theme.colors.primary, -15),
        textColor: this.getContrastText(theme.colors.primary)
      },
      {
        id: 'productos',
        label: 'Productos',
        icon: 'mdi-package-variant',
        color: theme.colors.success,
        gradient: this.createGradient(theme.colors.success, -15),
        textColor: this.getContrastText(theme.colors.success)
      },
      {
        id: 'clientes',
        label: 'Clientes',
        icon: 'mdi-account-group',
        color: theme.colors.info,
        gradient: this.createGradient(theme.colors.info, -15),
        textColor: this.getContrastText(theme.colors.info)
      },
      {
        id: 'proveedores',
        label: 'Proveedores',
        icon: 'mdi-truck',
        color: theme.colors.warning,
        gradient: this.createGradient(theme.colors.warning, -15),
        textColor: this.getContrastText(theme.colors.warning)
      },
      {
        id: 'pedidos',
        label: 'Pedidos',
        icon: 'mdi-cart',
        color: theme.colors.error,
        gradient: this.createGradient(theme.colors.error, -15),
        textColor: this.getContrastText(theme.colors.error)
      },
      {
        id: 'stock',
        label: 'En Stock',
        icon: 'mdi-warehouse',
        color: theme.colors.secondary,
        gradient: this.createGradient(theme.colors.secondary, -15),
        textColor: this.getContrastText(theme.colors.secondary)
      }
    ]
  }

  /**
   * Actualizar colores de gráficos
   */
  updateChartColors() {
    const theme = this.vuetifyTheme.current.value
    
    this.customColors.charts = {
      line: {
        borderColor: [
          theme.colors.primary,
          theme.colors.success,
          theme.colors.warning,
          theme.colors.error
        ],
        backgroundColor: [
          this.hexToRgba(theme.colors.primary, 0.1),
          this.hexToRgba(theme.colors.success, 0.1),
          this.hexToRgba(theme.colors.warning, 0.1),
          this.hexToRgba(theme.colors.error, 0.1)
        ]
      },
      bar: {
        backgroundColor: [
          theme.colors.info,
          theme.colors.success,
          theme.colors.warning,
          theme.colors.error
        ]
      },
      doughnut: {
        backgroundColor: [
          theme.colors.primary,
          theme.colors.success,
          theme.colors.info,
          theme.colors.warning,
          theme.colors.error,
          theme.colors.secondary
        ]
      },
      grid: {
        color: this.hexToRgba(theme.colors['on-surface'], 0.1)
      },
      text: {
        color: theme.colors['on-surface']
      }
    }
  }

  /**
   * Actualizar colores de clima
   */
  updateWeatherColors() {
    const theme = this.vuetifyTheme.current.value
    
    this.customColors.weather = {
      card: {
        normal: theme.colors.info,
        warning: theme.colors.warning,
        error: theme.colors.error
      },
      forecast: {
        selected: theme.colors.primary,
        normal: theme.colors.surface,
        border: theme.colors['surface-variant']
      },
      chips: {
        tempMax: theme.colors.error,
        tempMin: theme.colors.info,
        humidity: theme.colors.success,
        rain: theme.colors.primary
      }
    }
  }

  /**
   * Crear gradiente CSS
   */
  createGradient(baseColor, adjustment = -15, angle = 135) {
    const endColor = this.adjustBrightness(baseColor, adjustment)
    return `linear-gradient(${angle}deg, ${baseColor} 0%, ${endColor} 100%)`
  }

  /**
   * Ajustar brillo de color
   */
  adjustBrightness(hex, percent) {
    const num = parseInt(hex.replace('#', ''), 16)
    const amt = Math.round(2.55 * percent)
    const R = Math.min(255, Math.max(0, (num >> 16) + amt))
    const G = Math.min(255, Math.max(0, (num >> 8 & 0x00FF) + amt))
    const B = Math.min(255, Math.max(0, (num & 0x0000FF) + amt))
    return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1)
  }

  /**
   * Convertir hex a rgba
   */
  hexToRgba(hex, alpha = 1) {
    const num = parseInt(hex.replace('#', ''), 16)
    const r = (num >> 16) & 255
    const g = (num >> 8) & 255
    const b = num & 255
    return `rgba(${r}, ${g}, ${b}, ${alpha})`
  }

  /**
   * Obtener color de texto con contraste
   */
  getContrastText(backgroundColor) {
    const hex = backgroundColor.replace('#', '')
    const r = parseInt(hex.substr(0, 2), 16)
    const g = parseInt(hex.substr(2, 2), 16)
    const b = parseInt(hex.substr(4, 2), 16)
    
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
    
    return luminance > 0.5 ? '#000000' : '#FFFFFF'
  }

  /**
   * Obtener configuración de stat card por ID
   */
  getStatCard(id) {
    return this.customColors.statCards.find(card => card.id === id)
  }

  /**
   * Obtener configuración de stat card por índice
   */
  getStatCardByIndex(index) {
    return this.customColors.statCards[index % this.customColors.statCards.length]
  }

  /**
   * Generar estilo CSS para stat card
   */
  getStatCardStyle(index) {
    const card = this.getStatCardByIndex(index)
    return {
      background: card.gradient,
      color: card.textColor,
      borderRadius: designTokens.borderRadius.lg,
      boxShadow: designTokens.elevation[2],
      transition: `all ${designTokens.transition.duration.normal} ${designTokens.transition.timing.easeInOut}`
    }
  }

  /**
   * Obtener configuración de Chart.js
   */
  getChartOptions(type = 'line') {
    const chartColors = this.customColors.charts[type]
    const gridColor = this.customColors.charts.grid.color
    const textColor = this.customColors.charts.text.color

    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            color: textColor,
            font: {
              family: designTokens.typography.fontFamily.primary,
              size: 12
            }
          }
        }
      },
      scales: type !== 'doughnut' ? {
        x: {
          grid: {
            color: gridColor
          },
          ticks: {
            color: textColor
          }
        },
        y: {
          grid: {
            color: gridColor
          },
          ticks: {
            color: textColor
          }
        }
      } : undefined
    }
  }
}

/**
 * ====================================
 * COMPOSABLE PARA USAR EL TEMA
 * ====================================
 */
export function useAdvancedTheme(vuetifyTheme) {
  const dashboardTheme = new DashboardTheme(vuetifyTheme)

  return {
    theme: dashboardTheme,
    tokens: designTokens,
    
    // Shortcuts
    getStatCardStyle: (index) => dashboardTheme.getStatCardStyle(index),
    getStatCard: (id) => dashboardTheme.getStatCard(id),
    getChartOptions: (type) => dashboardTheme.getChartOptions(type),
    
    // Colors
    statCardColors: computed(() => dashboardTheme.customColors.statCards),
    chartColors: computed(() => dashboardTheme.customColors.charts),
    weatherColors: computed(() => dashboardTheme.customColors.weather)
  }
}

/**
 * ====================================
 * PLUGIN DE INSTALACIÓN (OPCIONAL)
 * ====================================
 * 
 * Si quieres usar esto como plugin, en main.js:
 * 
 * import advancedThemePlugin, { DashboardTheme } from '@/plugins/advancedTheme'
 * import { useTheme } from 'vuetify'
 * 
 * const vuetifyTheme = useTheme()
 * const dashboardTheme = new DashboardTheme(vuetifyTheme)
 * app.use(advancedThemePlugin, { dashboardTheme })
 */
export const advancedThemePlugin = {
  install(app, options = {}) {
    // Registrar tema global
    app.config.globalProperties.$dashboardTheme = options.dashboardTheme
    app.config.globalProperties.$designTokens = designTokens
    
    // Proveer a toda la app
    app.provide('dashboardTheme', options.dashboardTheme)
    app.provide('designTokens', designTokens)
  }
}

// No exportar default para evitar que se cargue automáticamente
// export default advancedThemePlugin
