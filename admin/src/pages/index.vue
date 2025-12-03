<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const userName = computed(() => {
  if (authStore.user) {
    return authStore.user.nombre || authStore.user.name || 'Usuario'
  }
  return 'Usuario'
})

const modules = [
  {
    title: 'Ventas',
    description: 'Registrar ventas, consultar historial y gestionar formas de pago.',
    icon: 'mdi-cart',
    color: 'primary',
    route: { name: 'ventas' }
  },
  {
    title: 'Pagos',
    description: 'Gestión de cobros, pagos a proveedores y cheques.',
    icon: 'mdi-cash-multiple',
    color: 'success',
    route: { name: 'pagos' }
  },
  {
    title: 'Pedidos',
    description: 'Control de pedidos pendientes y entregas.',
    icon: 'mdi-package-variant',
    color: 'warning',
    route: { name: 'pedidos' }
  },
  {
    title: 'Clientes',
    description: 'Alta y gestión de clientes, cuentas corrientes y estados de cuenta.',
    icon: 'mdi-account-group',
    color: 'info',
    route: { name: 'clientes' }
  },
  {
    title: 'Productos',
    description: 'Catálogo de productos, precios y stock disponible.',
    icon: 'mdi-package',
    color: 'secondary',
    route: { name: 'productos' }
  },
  {
    title: 'Proveedores',
    description: 'Proveedores, cuentas corrientes y cheques emitidos.',
    icon: 'mdi-truck',
    color: 'primary',
    route: { name: 'proveedores' }
  },
  {
    title: 'Empleados',
    description: 'Gestión de usuarios internos y permisos de acceso.',
    icon: 'mdi-account-tie',
    color: 'error',
    route: { name: 'empleados' }
  },
  {
    title: 'Reportes',
    description: 'Reportes de ventas, productos, clientes y proveedores con filtros por fecha.',
    icon: 'mdi-chart-bar',
    color: 'success',
    route: { name: 'reportes' }
  }
]

const navigateToModule = (route) => {
  router.push(route)
}

const navigateToManual = () => {
  router.push({ name: 'manual-usuario' })
}
</script>

<template>
  <div>
    <!-- Encabezado de Bienvenida -->
    <VCard class="mb-6">
      <VCardText class="text-center py-8">
        <h1 class="text-h3 font-weight-bold mb-3">
          Bienvenido, {{ userName }}
        </h1>
        <p class="text-h6 text-medium-emphasis">
          Este panel centraliza la gestión de ventas, stock, pagos y reportes de Maderas Pani
        </p>
      </VCardText>
    </VCard>

    <!-- Sección Manual de Usuario (Destacada) -->
    <VCard 
      class="mb-6"
      color="primary"
      variant="tonal"
    >
      <VCardText class="d-flex align-center justify-space-between flex-wrap">
        <div class="d-flex align-center">
          <VIcon
            icon="mdi-book-open-page-variant"
            size="48"
            class="me-4"
          />
          <div>
            <h3 class="text-h5 font-weight-bold mb-1">
              Manual de Usuario
            </h3>
            <p class="text-body-1 mb-0">
              Consulta la guía completa paso a paso para utilizar el E.R.P MADERAS PANI
            </p>
          </div>
        </div>
        <VBtn
          color="primary"
          variant="elevated"
          size="large"
          class="mt-3 mt-sm-0"
          @click="navigateToManual"
        >
          <VIcon
            icon="mdi-arrow-right"
            start
          />
          Ver Manual de Usuario
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Resumen del Sistema -->
    <div class="mb-4">
      <h2 class="text-h4 font-weight-bold mb-6">
        Módulos del Sistema
      </h2>
      
      <VRow>
        <VCol
          v-for="module in modules"
          :key="module.title"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <VCard
            class="h-100"
            hover
          >
            <VCardText class="d-flex flex-column h-100">
              <div class="d-flex align-center mb-3">
                <VIcon
                  :icon="module.icon"
                  :color="module.color"
                  size="32"
                  class="me-3"
                />
                <h3 class="text-h6 font-weight-bold">
                  {{ module.title }}
                </h3>
              </div>
              
              <p class="text-body-2 text-medium-emphasis mb-4 flex-grow-1">
                {{ module.description }}
              </p>

              <VBtn
                :color="module.color"
                variant="tonal"
                block
                @click="navigateToModule(module.route)"
              >
                Ir al módulo
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.h-100 {
  height: 100%;
}
</style>

