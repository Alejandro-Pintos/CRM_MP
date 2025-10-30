// src/plugins/toast.js
import { ref } from 'vue'

// Estado global de notificaciones
export const toasts = ref([])

let toastIdCounter = 0

/**
 * Muestra una notificación toast
 * @param {Object} options - Opciones de la notificación
 * @param {string} options.message - Mensaje a mostrar
 * @param {string} options.type - Tipo: 'success', 'error', 'warning', 'info'
 * @param {number} options.duration - Duración en ms (default: 3000)
 */
export function showToast({ message, type = 'info', duration = 3000 }) {
  const id = ++toastIdCounter
  
  const toast = {
    id,
    message,
    type,
    visible: true,
  }
  
  toasts.value.push(toast)
  
  if (duration > 0) {
    setTimeout(() => {
      removeToast(id)
    }, duration)
  }
  
  return id
}

/**
 * Elimina una notificación
 * @param {number} id - ID de la notificación
 */
export function removeToast(id) {
  const index = toasts.value.findIndex(t => t.id === id)
  if (index > -1) {
    toasts.value.splice(index, 1)
  }
}

/**
 * Helpers para tipos específicos
 */
export const toast = {
  success: (message, duration) => showToast({ message, type: 'success', duration }),
  error: (message, duration) => showToast({ message, type: 'error', duration }),
  warning: (message, duration) => showToast({ message, type: 'warning', duration }),
  info: (message, duration) => showToast({ message, type: 'info', duration }),
}
