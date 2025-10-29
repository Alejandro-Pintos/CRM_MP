<script setup>
import { ref, onMounted } from 'vue'
import { getProveedores, createProveedor, updateProveedor, deleteProveedor } from '@/services/proveedores'

const proveedores = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const dialogDelete = ref(false)
const editedIndex = ref(-1)
const editedItem = ref({
  id: null,
  nombre: '',
  cuit: '',
  direccion: '',
  telefono: '',
  email: '',
  estado: 'activo',
})
const defaultItem = {
  id: null,
  nombre: '',
  cuit: '',
  direccion: '',
  telefono: '',
  email: '',
  estado: 'activo',
}

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'CUIT', key: 'cuit' },
  { title: 'Email', key: 'email' },
  { title: 'Teléfono', key: 'telefono' },
  { title: 'Estado', key: 'estado' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const fetchProveedores = async () => {
  loading.value = true
  error.value = ''
  try {
    const data = await getProveedores()
    proveedores.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    error.value = e.message || 'Error al cargar proveedores'
  } finally {
    loading.value = false
  }
}

const editItem = (item) => {
  editedIndex.value = proveedores.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialog.value = true
}

const deleteItem = (item) => {
  editedIndex.value = proveedores.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialogDelete.value = true
}

const deleteItemConfirm = async () => {
  try {
    await deleteProveedor(editedItem.value.id)
    proveedores.value.splice(editedIndex.value, 1)
    closeDelete()
  } catch (e) {
    error.value = e.message || 'Error al eliminar proveedor'
  }
}

const close = () => {
  dialog.value = false
  setTimeout(() => {
    editedItem.value = Object.assign({}, defaultItem)
    editedIndex.value = -1
  }, 300)
}

const closeDelete = () => {
  dialogDelete.value = false
  setTimeout(() => {
    editedItem.value = Object.assign({}, defaultItem)
    editedIndex.value = -1
  }, 300)
}

const save = async () => {
  try {
    if (editedIndex.value > -1) {
      const updated = await updateProveedor(editedItem.value.id, editedItem.value)
      Object.assign(proveedores.value[editedIndex.value], updated)
    } else {
      const created = await createProveedor(editedItem.value)
      proveedores.value.push(created)
    }
    close()
  } catch (e) {
    error.value = e.message || 'Error al guardar proveedor'
  }
}

onMounted(fetchProveedores)
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center">
          <span class="text-h5">Proveedores</span>
          <VBtn color="primary" @click="dialog = true">
            Nuevo Proveedor
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" dismissible @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VDataTable
          :headers="headers"
          :items="proveedores"
          :loading="loading"
          loading-text="Cargando proveedores..."
          no-data-text="No hay proveedores registrados"
          class="elevation-1"
        >
          <template #item.estado="{ item }">
            <VChip :color="item.estado === 'activo' ? 'success' : 'error'" size="small">
              {{ item.estado }}
            </VChip>
          </template>
          <template #item.actions="{ item }">
            <div class="d-flex ga-2">
              <VBtn
                icon
                size="small"
                color="primary"
                variant="tonal"
                @click="editItem(item)"
                title="Editar proveedor"
              >
                <VIcon>mdi-pencil</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="deleteItem(item)"
                title="Eliminar proveedor"
              >
                <VIcon>mdi-delete</VIcon>
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Dialog para crear/editar -->
    <VDialog v-model="dialog" max-width="600px">
      <VCard>
        <VCardTitle>
          <span class="text-h5">{{ editedIndex === -1 ? 'Nuevo' : 'Editar' }} Proveedor</span>
        </VCardTitle>

        <VCardText>
          <VContainer>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.nombre"
                  label="Nombre*"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.cuit"
                  label="CUIT*"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.email"
                  label="Email"
                  type="email"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.telefono"
                  label="Teléfono"
                />
              </VCol>
              <VCol cols="12" md="8">
                <VTextField
                  v-model="editedItem.direccion"
                  label="Dirección"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="editedItem.estado"
                  :items="['activo', 'inactivo']"
                  label="Estado*"
                  required
                />
              </VCol>
            </VRow>
          </VContainer>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="close">
            Cancelar
          </VBtn>
          <VBtn color="primary" variant="text" @click="save">
            Guardar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog para confirmar eliminación -->
    <VDialog v-model="dialogDelete" max-width="500px">
      <VCard>
        <VCardTitle class="text-h5">
          ¿Está seguro de eliminar este proveedor?
        </VCardTitle>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="closeDelete">
            Cancelar
          </VBtn>
          <VBtn color="error" variant="text" @click="deleteItemConfirm">
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

