<template>
  <VSnackbar
    v-for="toast in toasts"
    :key="toast.id"
    v-model="toast.visible"
    :color="getColor(toast.type)"
    location="top right"
    :timeout="3000"
    @update:model-value="!$event && removeToast(toast.id)"
  >
    <div class="d-flex align-center">
      <VIcon :icon="getIcon(toast.type)" start />
      {{ toast.message }}
    </div>
    
    <template #actions>
      <VBtn
        variant="text"
        icon="mdi-close"
        @click="removeToast(toast.id)"
      />
    </template>
  </VSnackbar>
</template>

<script setup>
import { toasts, removeToast } from '@/plugins/toast'

const getColor = (type) => {
  const colors = {
    success: 'success',
    error: 'error',
    warning: 'warning',
    info: 'info',
  }
  return colors[type] || 'info'
}

const getIcon = (type) => {
  const icons = {
    success: 'mdi-check-circle',
    error: 'mdi-alert-circle',
    warning: 'mdi-alert',
    info: 'mdi-information',
  }
  return icons[type] || 'mdi-information'
}
</script>
