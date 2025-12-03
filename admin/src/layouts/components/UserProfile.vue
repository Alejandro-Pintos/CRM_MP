<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useAuthStore } from '@/stores/auth'
import { getResumenNotificaciones } from '@/services/notificaciones'
import avatar1 from '@images/avatars/avatar-1.png'

const router = useRouter()
const authStore = useAuthStore()

// Estado de notificaciones
const notificaciones = ref({
  cheques_proximos_vencer: 0,
  pedidos_pendientes: 0,
  total_alertas: 0,
})

// Cargar notificaciones al montar
onMounted(async () => {
  try {
    notificaciones.value = await getResumenNotificaciones()
  } catch (error) {
    console.error('Error al cargar notificaciones:', error)
  }
})

// Datos del usuario autenticado
const currentUser = computed(() => {
  const user = authStore.user
  
  // Extraer rol (puede ser string o objeto)
  let role = 'Usuario'
  if (user?.roles && user.roles.length > 0) {
    const firstRole = user.roles[0]
    role = typeof firstRole === 'string' ? firstRole : firstRole?.name || 'Usuario'
  }
  
  return {
    name: user?.nombre || 'Usuario',
    role: role,
    avatar: user?.avatar || avatar1,
  }
})

// Menú de usuario con opciones relevantes para el CRM
const userProfileList = computed(() => {
  const items = [
    { type: 'divider' },
    
    // Mi Perfil - NUEVO
    {
      type: 'navItem',
      icon: 'ri-user-3-line',
      title: 'Mi Perfil',
      to: { name: 'perfil' },
    },
    
    { type: 'divider' },
    
    // Alertas y notificaciones
    {
      type: 'navItem',
      icon: 'ri-notification-3-line',
      title: 'Alertas',
      to: { name: 'pagos-cheques' },
      chipsProps: notificaciones.value.total_alertas > 0 ? {
        color: 'error',
        text: notificaciones.value.total_alertas.toString(),
        size: 'small',
      } : null,
      badge: notificaciones.value.total_alertas,
    },
    
    { type: 'divider' },
    
    // Cheques próximos a vencer
    {
      type: 'navItem',
      icon: 'ri-file-list-3-line',
      title: 'Cheques a vencer',
      to: { name: 'pagos-cheques' },
      chipsProps: notificaciones.value.cheques_proximos_vencer > 0 ? {
        color: 'warning',
        text: notificaciones.value.cheques_proximos_vencer.toString(),
        size: 'small',
      } : null,
    },
    
    // Pedidos pendientes
    {
      type: 'navItem',
      icon: 'ri-truck-line',
      title: 'Pedidos pendientes',
      to: { name: 'pedidos' },
      chipsProps: notificaciones.value.pedidos_pendientes > 0 ? {
        color: 'info',
        text: notificaciones.value.pedidos_pendientes.toString(),
        size: 'small',
      } : null,
    },
  ]
  
  return items.filter(item => item !== null)
})

// Helper para verificar permisos (simplificado)
function hasPermission(permission) {
  const user = authStore.user
  if (!user) return false
  
  // Admin tiene todos los permisos
  if (user.roles?.includes('admin')) return true
  
  // Verificar si el usuario tiene el permiso específico
  return user.permissions?.includes(permission) || false
}

// Manejo de logout
async function handleLogout() {
  try {
    await authStore.logout()
    router.push({ name: 'login' })
  } catch (error) {
    console.error('Error al cerrar sesión:', error)
    // Forzar logout local aunque falle el backend
    authStore.setToken(null)
    authStore.setUser(null)
    router.push({ name: 'login' })
  }
}
</script>

<template>
  <VBadge
    :content="notificaciones.total_alertas > 0 ? notificaciones.total_alertas : undefined"
    :model-value="notificaciones.total_alertas > 0"
    color="error"
    bordered
    location="top right"
    offset-x="5"
    offset-y="5"
    class="user-profile-badge"
  >
    <VAvatar
      class="cursor-pointer"
      size="38"
      color="primary"
    >
      <VImg 
        :src="currentUser.avatar" 
        alt="Avatar de usuario"
      />

      <!-- SECTION Menu de Usuario -->
      <VMenu
        activator="parent"
        width="280"
        location="bottom end"
        offset="18px"
        :close-on-content-click="false"
      >
        <VList class="py-0">
          <!-- Header con info del usuario -->
          <VListItem class="px-4 py-3 bg-light">
            <div class="d-flex gap-x-3 align-center">
              <VAvatar
                size="40"
                color="primary"
              >
                <VImg 
                  :src="currentUser.avatar" 
                  alt="Avatar"
                />
              </VAvatar>

              <div class="flex-grow-1">
                <div class="text-body-1 font-weight-semibold text-high-emphasis">
                  {{ currentUser.name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  <VChip
                    size="x-small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ currentUser.role }}
                  </VChip>
                </div>
              </div>
            </div>
          </VListItem>

          <VDivider />

          <!-- Opciones del menú con scroll -->
          <PerfectScrollbar 
            :options="{ wheelPropagation: false }"
            style="max-height: 400px;"
          >
            <template
              v-for="(item, index) in userProfileList"
              :key="index"
            >
              <VListItem
                v-if="item.type === 'navItem'"
                :to="item.to"
                class="px-4"
                link
              >
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="22"
                    class="me-2"
                  />
                </template>

                <VListItemTitle class="text-body-2">
                  {{ item.title }}
                </VListItemTitle>

                <template
                  v-if="item.chipsProps"
                  #append
                >
                  <VChip
                    v-bind="item.chipsProps"
                    variant="flat"
                  />
                </template>
              </VListItem>

              <VDivider
                v-else-if="item.type === 'divider'"
                class="my-1"
              />
            </template>
          </PerfectScrollbar>

          <VDivider />

          <!-- Botón de Logout siempre visible -->
          <VListItem class="px-4 py-3">
            <VBtn
              block
              color="error"
              variant="flat"
              size="small"
              prepend-icon="ri-logout-box-r-line"
              @click="handleLogout"
            >
              Cerrar Sesión
            </VBtn>
          </VListItem>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>

<style lang="scss" scoped>
.user-profile-badge {
  :deep(.v-badge__badge) {
    font-size: 0.625rem;
    font-weight: 600;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
  }
}

.bg-light {
  background-color: rgb(var(--v-theme-surface));
}

// Mejoras de accesibilidad y UX
.v-list-item {
  transition: background-color 0.2s ease;
  
  &:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.05);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
}

// Animación suave para el badge de notificaciones
.user-profile-badge :deep(.v-badge__badge) {
  animation: pulse-badge 2s ease-in-out infinite;
}

@keyframes pulse-badge {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}
</style>
