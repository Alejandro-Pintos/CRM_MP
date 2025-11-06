import { ref, watch } from 'vue'

/**
 * Composable para formatear números con separadores de miles
 * @param {Ref} modelValue - Valor numérico del modelo
 * @param {Object} options - Opciones de formateo
 * @returns {Object} - displayValue y métodos de formateo
 */
export function useNumberFormat(modelValue, options = {}) {
  const {
    decimals = 2,
    thousandsSeparator = '.',
    decimalSeparator = ',',
    prefix = '',
    suffix = '',
  } = options

  const displayValue = ref('')

  // Formatear número para mostrar
  const formatNumber = (value) => {
    if (value === null || value === undefined || value === '') {
      return ''
    }

    const numValue = parseFloat(value)
    if (isNaN(numValue)) return ''

    // Separar parte entera y decimal
    const parts = numValue.toFixed(decimals).split('.')
    const integerPart = parts[0]
    const decimalPart = parts[1]

    // Agregar separadores de miles
    const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSeparator)

    // Construir número formateado
    let formatted = formattedInteger
    if (decimals > 0 && decimalPart) {
      formatted += decimalSeparator + decimalPart
    }

    return prefix + formatted + suffix
  }

  // Parsear número desde string formateado
  const parseNumber = (formattedValue) => {
    if (!formattedValue) return null

    // Remover prefijo y sufijo
    let cleanValue = formattedValue
    if (prefix) cleanValue = cleanValue.replace(prefix, '')
    if (suffix) cleanValue = cleanValue.replace(suffix, '')

    // Remover separadores de miles
    cleanValue = cleanValue.replace(new RegExp('\\' + thousandsSeparator, 'g'), '')
    
    // Reemplazar separador decimal con punto
    cleanValue = cleanValue.replace(decimalSeparator, '.')

    const numValue = parseFloat(cleanValue)
    return isNaN(numValue) ? null : numValue
  }

  // Manejar input del usuario
  const handleInput = (event) => {
    const inputValue = event.target.value
    
    // Permitir solo números, separadores y prefijo/sufijo
    const allowedChars = new RegExp(`[0-9${thousandsSeparator}${decimalSeparator}${prefix}${suffix}]`, 'g')
    const cleaned = inputValue.match(allowedChars)?.join('') || ''

    const numValue = parseNumber(cleaned)
    
    if (numValue !== null) {
      modelValue.value = numValue
      displayValue.value = formatNumber(numValue)
    } else if (cleaned === '' || cleaned === prefix) {
      modelValue.value = null
      displayValue.value = ''
    }
  }

  // Manejar blur (cuando pierde el foco)
  const handleBlur = () => {
    if (modelValue.value !== null && modelValue.value !== undefined) {
      displayValue.value = formatNumber(modelValue.value)
    }
  }

  // Watch para sincronizar cuando cambia el modelo externamente
  watch(() => modelValue.value, (newValue) => {
    displayValue.value = formatNumber(newValue)
  }, { immediate: true })

  return {
    displayValue,
    formatNumber,
    parseNumber,
    handleInput,
    handleBlur,
  }
}

/**
 * Directiva para formatear números automáticamente
 */
export const vNumberFormat = {
  mounted(el, binding) {
    const input = el.querySelector('input') || el
    const options = binding.value || {}

    const {
      decimals = 2,
      thousandsSeparator = '.',
      decimalSeparator = ',',
    } = options

    const formatNumber = (value) => {
      if (!value) return ''
      
      const numValue = parseFloat(value)
      if (isNaN(numValue)) return value

      const parts = numValue.toFixed(decimals).split('.')
      const integerPart = parts[0]
      const decimalPart = parts[1]

      const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSeparator)
      
      let formatted = formattedInteger
      if (decimals > 0 && decimalPart) {
        formatted += decimalSeparator + decimalPart
      }

      return formatted
    }

    const parseNumber = (formattedValue) => {
      if (!formattedValue) return ''
      
      let cleanValue = formattedValue.replace(new RegExp('\\' + thousandsSeparator, 'g'), '')
      cleanValue = cleanValue.replace(decimalSeparator, '.')
      
      return cleanValue
    }

    input.addEventListener('blur', (e) => {
      const value = parseNumber(e.target.value)
      if (value) {
        e.target.value = formatNumber(value)
        // Disparar evento input para actualizar v-model
        e.target.dispatchEvent(new Event('input', { bubbles: true }))
      }
    })

    input.addEventListener('focus', (e) => {
      const value = parseNumber(e.target.value)
      e.target.value = value
    })
  }
}
