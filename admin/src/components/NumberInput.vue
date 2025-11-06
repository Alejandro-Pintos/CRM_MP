<script setup>
import { ref, watch, computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null
  },
  decimals: {
    type: Number,
    default: 2
  },
  thousandsSeparator: {
    type: String,
    default: '.'
  },
  decimalSeparator: {
    type: String,
    default: ','
  },
  prefix: {
    type: String,
    default: ''
  },
  suffix: {
    type: String,
    default: ''
  },
  // Todas las props de VTextField
  label: String,
  placeholder: String,
  hint: String,
  persistentHint: Boolean,
  density: String,
  variant: String,
  prependInnerIcon: String,
  appendInnerIcon: String,
  disabled: Boolean,
  readonly: Boolean,
  rules: Array,
  errorMessages: [String, Array],
  clearable: Boolean,
  hideDetails: [Boolean, String],
})

const emit = defineEmits(['update:modelValue'])

const displayValue = ref('')
const cursorPosition = ref(0)
const inputRef = ref(null)

// Formatear número mientras se escribe (sin decimales completos)
const formatWhileTyping = (value, includeDecimals = false) => {
  if (!value && value !== 0) return ''

  let cleanValue = value.toString()
  
  // Remover todo excepto números y separador decimal
  cleanValue = cleanValue.replace(new RegExp(`[^0-9${props.decimalSeparator}]`, 'g'), '')

  // Separar parte entera y decimal
  const parts = cleanValue.split(props.decimalSeparator)
  let integerPart = parts[0] || '0'
  let decimalPart = parts[1] || ''

  // Agregar separadores de miles a la parte entera
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, props.thousandsSeparator)

  // Construir número formateado
  let formatted = props.prefix + formattedInteger

  // Agregar parte decimal si existe
  if (parts.length > 1) {
    formatted += props.decimalSeparator + decimalPart
  }

  formatted += props.suffix

  return formatted
}

// Formatear número completo (con decimales fijos)
const formatNumber = (value) => {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(numValue)) return ''

  // Separar parte entera y decimal
  const parts = numValue.toFixed(props.decimals).split('.')
  const integerPart = parts[0]
  const decimalPart = parts[1]

  // Agregar separadores de miles
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, props.thousandsSeparator)

  // Construir número formateado
  let formatted = formattedInteger
  if (props.decimals > 0 && decimalPart) {
    formatted += props.decimalSeparator + decimalPart
  }

  return props.prefix + formatted + props.suffix
}

// Parsear número desde string formateado
const parseNumber = (formattedValue) => {
  if (!formattedValue) return null

  // Remover prefijo y sufijo
  let cleanValue = formattedValue
  if (props.prefix) cleanValue = cleanValue.replace(props.prefix, '')
  if (props.suffix) cleanValue = cleanValue.replace(props.suffix, '')

  // Remover separadores de miles
  cleanValue = cleanValue.replace(new RegExp('\\' + props.thousandsSeparator, 'g'), '')
  
  // Reemplazar separador decimal con punto
  cleanValue = cleanValue.replace(props.decimalSeparator, '.')

  // Permitir solo números y punto decimal
  cleanValue = cleanValue.replace(/[^\d.-]/g, '')

  const numValue = parseFloat(cleanValue)
  return isNaN(numValue) ? null : numValue
}

// Manejar input del usuario
const handleInput = (event) => {
  const input = event.target
  const inputValue = input.value
  
  // Guardar posición del cursor antes del formateo
  const oldCursorPos = input.selectionStart
  const oldLength = inputValue.length
  
  // Parsear y formatear
  const numValue = parseNumber(inputValue)
  
  if (numValue !== null) {
    emit('update:modelValue', numValue)
    
    // Formatear mientras escribe
    const formatted = formatWhileTyping(inputValue)
    displayValue.value = formatted
    
    // Restaurar cursor después del formateo
    setTimeout(() => {
      if (input === document.activeElement) {
        const newLength = formatted.length
        const lengthDiff = newLength - oldLength
        const newCursorPos = oldCursorPos + lengthDiff
        input.setSelectionRange(newCursorPos, newCursorPos)
      }
    }, 0)
  } else if (inputValue === '' || inputValue === props.prefix) {
    emit('update:modelValue', null)
    displayValue.value = ''
  }
}

// Manejar focus
const handleFocus = (event) => {
  // No hacer nada especial en focus, mantener formato
}

// Manejar blur (cuando pierde el foco)
const handleBlur = () => {
  // Formatear con decimales completos al perder el foco
  if (props.modelValue !== null && props.modelValue !== undefined) {
    displayValue.value = formatNumber(props.modelValue)
  } else {
    displayValue.value = ''
  }
}

// Watch para sincronizar cuando cambia el modelo externamente
watch(() => props.modelValue, (newValue) => {
  // Solo actualizar si el input no tiene el foco
  if (document.activeElement !== inputRef.value?.$el?.querySelector('input')) {
    displayValue.value = formatNumber(newValue)
  }
}, { immediate: true })

// Limpiar valor
const handleClear = () => {
  displayValue.value = ''
  emit('update:modelValue', null)
}
</script>

<template>
  <VTextField
    ref="inputRef"
    :model-value="displayValue"
    :label="label"
    :placeholder="placeholder"
    :hint="hint"
    :persistent-hint="persistentHint"
    :density="density"
    :variant="variant"
    :prepend-inner-icon="prependInnerIcon"
    :append-inner-icon="appendInnerIcon"
    :disabled="disabled"
    :readonly="readonly"
    :rules="rules"
    :error-messages="errorMessages"
    :clearable="clearable"
    :hide-details="hideDetails"
    @input="handleInput"
    @focus="handleFocus"
    @blur="handleBlur"
    @click:clear="handleClear"
  />
</template>
