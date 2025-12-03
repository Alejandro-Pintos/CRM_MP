<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getUsers, deleteUser } from '@/services/users'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

// Estado
const usuarios = ref([])
const loading = ref(false)
const searchQuery = ref('')
const selectedRol = ref('')
const showDeleteDialog = ref(false)
const usuarioToDelete = ref(null)

// Paginación
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(15)
const totalUsuarios = ref(0)

// Headers de la tabla
const headers = [
  { title: 'Nombre', key: 'nombre', sortable: true },
  { title: 'Email', key: 'email', sortable: true },
  { title: 'Roles', key: 'roles', sortable: false },
  { title: 'Creado', key: 'created_at', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
]

// Computed
const filteredUsuarios = computed(() => {
  let result = usuarios.value

  // Filtro por búsqueda
  if (searchQuery.value) {
    const search = searchQuery.value.toLowerCase()
    result = result.filter(u => 
      u.nombre?.toLowerCase().includes(search) || 
      u.email?.toLowerCase().includes(search)
    )
  }

  // Filtro por rol
  if (selectedRol.value) {
    result = result.filter(u => 
      u.roles?.some(r => r.toLowerCase() === selectedRol.value.toLowerCase())
    )
  }

  return result
})

const rolesDisponibles = computed(() => {
  const roles = new Set()
  usuarios.value.forEach(u => {
    u.roles?.forEach(r => roles.add(r))
  })
  return Array.from(roles)
})

const canManageUsers = computed(() => {
  return authStore.user?.permissions?.includes('users.manage') ||
         authStore.user?.roles?.some(r => r.toLowerCase() === 'admin' || r.toLowerCase() === 'superadmin')
})

// Métodos
async function cargarUsuarios() {
  loading.value = true
  try {
    const response = await getUsers({
      page: currentPage.value,
      per_page: perPage.value,
    })
    
    // Laravel API Resource Collection
    usuarios.value = response.data || []
    
    // Datos de paginación
    if (response.meta) {
      totalPages.value = response.meta.last_page
      totalUsuarios.value = response.meta.total
    }
  } catch (error) {
    console.error('Error al cargar usuarios:', error)
    usuarios.value = []
  } finally {
    loading.value = false
  }
}

function formatearFecha(fecha) {
  if (!fecha) return '-'
  try {
    const date = new Date(fecha)
    if (isNaN(date.getTime())) return '-'
    return date.toLocaleDateString('es-ES', { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    })
  } catch {
    return '-'
  }
}

function formatearRoles(roles) {
  if (!roles || !roles.length) return 'Sin roles'
  return roles.join(', ')
}

function getRolColor(rol) {
  const colorMap = {
    admin: 'error',
    superadmin: 'purple',
    vendedor: 'primary',
    operador: 'info',
  }
  return colorMap[rol?.toLowerCase()] || 'default'
}

function confirmarEliminar(usuario) {
  usuarioToDelete.value = usuario
  showDeleteDialog.value = true
}

async function eliminarUsuario() {
  if (!usuarioToDelete.value) return
  
  try {
    await deleteUser(usuarioToDelete.value.id)
    await cargarUsuarios()
    showDeleteDialog.value = false
    usuarioToDelete.value = null
  } catch (error) {
    console.error('Error al eliminar usuario:', error)
    alert('Error al eliminar el usuario')
  }
}

function crearNuevoUsuario() {
  router.push({ name: 'usuarios-nuevo' })
}

function editarUsuario(usuario) {
  router.push({ name: 'usuarios-editar', params: { id: usuario.id } })
}

// Lifecycle
onMounted(() => {
  if (!canManageUsers.value) {
    router.push({ name: 'dashboard' })
    return
  }
  cargarUsuarios()
})
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon
              icon="ri-group-line"
              size="28"
              class="me-3"
            />
            <div>
              <div class="text-h5">
                Gestión de Usuarios
              </div>
              <div class="text-caption text-disabled">
                Administra los usuarios del sistema
              </div>
            </div>
          </div>
          <VBtn
            color="primary"
            prepend-icon="ri-user-add-line"
            @click="crearNuevoUsuario"
          >
            Nuevo Usuario
          </VBtn>
        </VCardTitle>
      </VCardItem>
    </VCard>

    <!-- Filtros -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="searchQuery"
              placeholder="Buscar por nombre o email..."
              prepend-inner-icon="ri-search-line"
              clearable
              density="compact"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-model="selectedRol"
              :items="rolesDisponibles"
              placeholder="Filtrar por rol"
              prepend-inner-icon="ri-shield-user-line"
              clearable
              density="compact"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-center"
          >
            <VBtn
              variant="text"
              color="primary"
              @click="cargarUsuarios"
            >
              <VIcon icon="ri-refresh-line" />
              Actualizar
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tabla de usuarios -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="filteredUsuarios"
        :loading="loading"
        :items-per-page="perPage"
        hide-default-footer
      >
        <!-- Nombre -->
        <template #item.nombre="{ item }">
          <div class="d-flex align-center py-2">
            <VAvatar
              color="primary"
              size="36"
              class="me-3"
            >
              <span class="text-sm">{{ item.nombre?.charAt(0).toUpperCase() }}</span>
            </VAvatar>
            <div>
              <div class="font-weight-medium">
                {{ item.nombre }}
              </div>
            </div>
          </div>
        </template>

        <!-- Roles -->
        <template #item.roles="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <VChip
              v-for="rol in item.roles"
              :key="rol"
              :color="getRolColor(rol)"
              size="small"
              variant="tonal"
            >
              {{ rol }}
            </VChip>
            <VChip
              v-if="!item.roles || !item.roles.length"
              size="small"
              color="default"
            >
              Sin roles
            </VChip>
          </div>
        </template>

        <!-- Fecha creación -->
        <template #item.created_at="{ item }">
          <span class="text-body-2 text-disabled">
            {{ formatearFecha(item.created_at) }}
          </span>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-2 justify-end">
            <VBtn
              icon
              size="small"
              variant="text"
              color="primary"
              @click="editarUsuario(item)"
            >
              <VIcon
                icon="ri-edit-line"
                size="20"
              />
              <VTooltip
                activator="parent"
                location="top"
              >
                Editar
              </VTooltip>
            </VBtn>
            <VBtn
              icon
              size="small"
              variant="text"
              color="error"
              :disabled="item.id === authStore.user?.id"
              @click="confirmarEliminar(item)"
            >
              <VIcon
                icon="ri-delete-bin-line"
                size="20"
              />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ item.id === authStore.user?.id ? 'No puedes eliminar tu cuenta' : 'Eliminar' }}
              </VTooltip>
            </VBtn>
          </div>
        </template>

        <!-- Estado de carga -->
        <template #loading>
          <VSkeletonLoader type="table-row@10" />
        </template>

        <!-- Sin datos -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="ri-user-line"
              size="48"
              color="disabled"
              class="mb-4"
            />
            <div class="text-h6 text-disabled">
              No se encontraron usuarios
            </div>
          </div>
        </template>
      </VDataTable>

      <!-- Paginación -->
      <VDivider />
      <VCardText>
        <VRow class="align-center">
          <VCol
            cols="12"
            md="6"
          >
            <div class="text-body-2 text-disabled">
              Mostrando {{ filteredUsuarios.length }} de {{ totalUsuarios }} usuarios
            </div>
          </VCol>
          <VCol
            cols="12"
            md="6"
            class="d-flex justify-end"
          >
            <VPagination
              v-model="currentPage"
              :length="totalPages"
              :total-visible="5"
              @update:model-value="cargarUsuarios"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Dialog de confirmación de eliminación -->
    <VDialog
      v-model="showDeleteDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="d-flex align-center">
          <VIcon
            icon="ri-alert-line"
            color="error"
            class="me-2"
          />
          Confirmar eliminación
        </VCardTitle>
        <VCardText>
          <p class="mb-0">
            ¿Estás seguro de que deseas eliminar al usuario
            <strong>{{ usuarioToDelete?.nombre }}</strong>?
          </p>
          <p class="text-caption text-error mt-2">
            Esta acción no se puede deshacer.
          </p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="default"
            variant="text"
            @click="showDeleteDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            variant="flat"
            @click="eliminarUsuario"
          >
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
