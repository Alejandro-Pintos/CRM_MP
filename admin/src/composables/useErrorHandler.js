// src/composables/useErrorHandler.js
import { ref } from 'vue'

export function useErrorHandler() {
  const error = ref('')
  const loading = ref(false)

  /**
   * Ejecuta una función asíncrona con manejo de errores
   * @param {Function} fn - Función asíncrona a ejecutar
   * @param {Object} options - Opciones
   * @param {string} options.successMessage - Mensaje de éxito (opcional)
   * @param {string} options.errorMessage - Mensaje de error personalizado (opcional)
   * @param {Function} options.onSuccess - Callback al completar exitosamente
   * @param {Function} options.onError - Callback al fallar
   * @returns {Promise<any>} - Resultado de la función
   */
  const execute = async (fn, options = {}) => {
    const {
      successMessage = null,
      errorMessage = 'Ha ocurrido un error',
      onSuccess = null,
      onError = null,
    } = options

    loading.value = true
    error.value = ''

    try {
      const result = await fn()
      
      if (successMessage) {
        // Aquí podrías integrar un sistema de notificaciones toast
        console.log('✓', successMessage)
      }
      
      if (onSuccess) {
        onSuccess(result)
      }
      
      return result
    } catch (e) {
      const errorMsg = parseError(e, errorMessage)
      error.value = errorMsg
      
      if (onError) {
        onError(e)
      }
      
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Parsea un error y retorna un mensaje legible
   * @param {Error|string} err - Error a parsear
   * @param {string} defaultMessage - Mensaje por defecto
   * @returns {string} - Mensaje de error formateado
   */
  const parseError = (err, defaultMessage = 'Error desconocido') => {
    if (typeof err === 'string') {
      return err
    }

    // Errores de red
    if (err.message && err.message.includes('Failed to fetch')) {
      return 'No se pudo conectar con el servidor. Verifica tu conexión.'
    }

    // Errores HTTP
    if (err.message && err.message.includes('HTTP 401')) {
      return 'No autorizado. Por favor inicia sesión nuevamente.'
    }

    if (err.message && err.message.includes('HTTP 403')) {
      return 'No tienes permisos para realizar esta acción.'
    }

    if (err.message && err.message.includes('HTTP 404')) {
      return 'Recurso no encontrado.'
    }

    if (err.message && err.message.includes('HTTP 422')) {
      return 'Error de validación. Verifica los datos ingresados.'
    }

    if (err.message && err.message.includes('HTTP 500')) {
      return 'Error del servidor. Por favor intenta más tarde.'
    }

    // Intentar extraer mensaje del error
    if (err.response?.data?.message) {
      return err.response.data.message
    }

    if (err.message) {
      return err.message
    }

    return defaultMessage
  }

  /**
   * Limpia el error
   */
  const clearError = () => {
    error.value = ''
  }

  return {
    error,
    loading,
    execute,
    parseError,
    clearError,
  }
}
