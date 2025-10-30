<script setup>
import { ref, onMounted } from 'vue'
import { getProductos, createProducto, updateProducto, deleteProducto } from '@/services/productos'
import { getProveedores } from '@/services/proveedores'
import { toast } from '@/plugins/toast'

const productos = ref([])
const proveedores = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const dialogDelete = ref(false)
const editedIndex = ref(-1)
const editedItem = ref({
  id: null,
  codigo: '',
  nombre: '',
  descripcion: '',
  unidad_medida: 'u',
  precio: 0,
  iva: 21.00,
  estado: 'activo',
  proveedor_id: null,
})
const defaultItem = {
  id: null,
  codigo: '',
  nombre: '',
  descripcion: '',
  unidad_medida: 'u',
  precio: 0,
  iva: 21.00,
  estado: 'activo',
  proveedor_id: null,
}

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Código', key: 'codigo' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Unidad', key: 'unidad_medida' },
  { title: 'Precio', key: 'precio' },
  { title: 'IVA %', key: 'iva' },
  { title: 'Estado', key: 'estado' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const fetchProductos = async () => {
  loading.value = true
  error.value = ''
  try {
    const data = await getProductos()
    productos.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    const errorMsg = e.message || 'Error al cargar productos'
    error.value = errorMsg
    toast.error(errorMsg)
  } finally {
    loading.value = false
  }
}

const fetchProveedores = async () => {
  try {
    const data = await getProveedores()
    proveedores.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    console.error('Error al cargar proveedores:', e)
  }
}

const editItem = (item) => {
  editedIndex.value = productos.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialog.value = true
}

const deleteItem = (item) => {
  editedIndex.value = productos.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialogDelete.value = true
}

const deleteItemConfirm = async () => {
  try {
    await deleteProducto(editedItem.value.id)
    productos.value.splice(editedIndex.value, 1)
    toast.success('Producto eliminado correctamente')
    closeDelete()
  } catch (e) {
    const errorMsg = e.message || 'Error al eliminar producto'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const close = () => {
  dialog.value = false
  error.value = ''
  setTimeout(() => {
    editedItem.value = Object.assign({}, defaultItem)
    editedIndex.value = -1
  }, 300)
}

const closeDelete = () => {
  dialogDelete.value = false
  error.value = ''
  setTimeout(() => {
    editedItem.value = Object.assign({}, defaultItem)
    editedIndex.value = -1
  }, 300)
}

const save = async () => {
  try {
    if (editedIndex.value > -1) {
      const updated = await updateProducto(editedItem.value.id, editedItem.value)
      Object.assign(productos.value[editedIndex.value], updated)
      toast.success('Producto actualizado correctamente')
    } else {
      await createProducto(editedItem.value)
      toast.success('Producto creado correctamente')
      await fetchProductos() // Refrescar lista para obtener el nuevo producto
    }
    close()
  } catch (e) {
    const errorMsg = e.message || 'Error al guardar producto'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

onMounted(async () => {
  await fetchProductos()
  await fetchProveedores()
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center">
          <span class="text-h5">Productos</span>
          <VBtn color="primary" @click="dialog = true">
            Nuevo Producto
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" dismissible @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VDataTable
          :headers="headers"
          :items="productos"
          :loading="loading"
          loading-text="Cargando productos..."
          no-data-text="No hay productos registrados"
          class="elevation-1"
        >
          <template #item.precio="{ item }">
            {{ formatPrice(item.precio) }}
          </template>
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
                title="Editar producto"
              >
                <VIcon>ri-pencil-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="deleteItem(item)"
                title="Eliminar producto"
              >
                <VIcon>ri-delete-bin-6-line</VIcon>
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
          <span class="text-h5">{{ editedIndex === -1 ? 'Nuevo' : 'Editar' }} Producto</span>
        </VCardTitle>

        <VCardText>
          <VContainer>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.codigo"
                  label="Código/SKU*"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.nombre"
                  label="Nombre*"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="editedItem.descripcion"
                  label="Descripción"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="editedItem.proveedor_id"
                  :items="proveedores"
                  item-title="nombre"
                  item-value="id"
                  label="Proveedor"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="editedItem.precio"
                  label="Precio Unitario*"
                  type="number"
                  step="0.01"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="editedItem.iva"
                  label="IVA %*"
                  type="number"
                  step="0.01"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="editedItem.unidad_medida"
                  :items="['u', 'kg', 'm', 'm2', 'm3', 'l']"
                  label="Unidad de Medida*"
                  required
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
          ¿Está seguro de eliminar este producto?
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

