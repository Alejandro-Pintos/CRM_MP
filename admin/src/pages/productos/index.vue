<script setup>
import { ref, onMounted, computed } from 'vue'
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
const search = ref('')

const editedItem = ref({
  id: null,
  codigo: '',
  nombre: '',
  descripcion: '',
  unidad_medida: 'u',
  precio_compra: 0,
  precio_venta: 0,
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
  precio_compra: 0,
  precio_venta: 0,
  precio: 0,
  iva: 21.00,
  estado: 'activo',
  proveedor_id: null,
}

// Filtrar productos por búsqueda
const productosFiltrados = computed(() => {
  if (!search.value) return productos.value
  
  const searchLower = search.value.toLowerCase()
  return productos.value.filter(producto => {
    const codigo = (producto.codigo || '').toLowerCase()
    const nombre = (producto.nombre || '').toLowerCase()
    const descripcion = (producto.descripcion || '').toLowerCase()
    
    return codigo.includes(searchLower) ||
           nombre.includes(searchLower) ||
           descripcion.includes(searchLower)
  })
})

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Código', key: 'codigo' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Unidad', key: 'unidad_medida' },
  { title: 'P. Compra', key: 'precio_compra' },
  { title: 'P. Venta', key: 'precio_venta' },
  { title: 'IVA %', key: 'iva' },
  { title: 'P. Total (c/IVA)', key: 'precio_total' },
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
    toast.success('Producto eliminado correctamente')
    closeDelete()
    await fetchProductos() // Refrescar lista después de eliminar
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
    // Calcular precio automáticamente como precio_venta + IVA
    editedItem.value.precio = parseFloat(editedItem.value.precio_venta || 0) * (1 + parseFloat(editedItem.value.iva || 0) / 100)
    
    if (editedIndex.value > -1) {
      const updated = await updateProducto(editedItem.value.id, editedItem.value)
      Object.assign(productos.value[editedIndex.value], updated)
      toast.success('Producto actualizado correctamente')
    } else {
      await createProducto(editedItem.value)
      toast.success('Producto creado correctamente')
    }
    close()
    await fetchProductos() // Refrescar lista siempre
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
        <div class="d-flex justify-space-between align-center flex-wrap ga-4">
          <span class="text-h5">Productos</span>
          <div class="d-flex ga-2 align-center">
            <VTextField
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Buscar productos"
              single-line
              hide-details
              density="compact"
              style="min-width: 300px;"
              clearable
            />
            <VBtn color="primary" @click="dialog = true">
              <VIcon start>mdi-plus</VIcon>
              Nuevo Producto
            </VBtn>
          </div>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" dismissible @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VDataTable
          :headers="headers"
          :items="productosFiltrados"
          :loading="loading"
          loading-text="Cargando productos..."
          no-data-text="No hay productos registrados"
          class="elevation-1"
        >
          <template #item.precio_compra="{ item }">
            {{ item.precio_compra != null && item.precio_compra > 0 ? formatPrice(item.precio_compra) : '-' }}
          </template>
          <template #item.precio_venta="{ item }">
            {{ item.precio_venta != null && item.precio_venta > 0 ? formatPrice(item.precio_venta) : '-' }}
          </template>
          <template #item.precio_total="{ item }">
            <span class="text-success font-weight-bold">
              {{ formatPrice(item.precio_total) }}
            </span>
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
                  v-model.number="editedItem.precio_compra"
                  label="Precio Compra*"
                  type="number"
                  step="0.01"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="editedItem.precio_venta"
                  label="Precio Venta*"
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
                <VTextField
                  :model-value="((editedItem.precio_venta || 0) * (1 + (editedItem.iva || 0) / 100)).toFixed(2)"
                  label="Precio Total (c/IVA)"
                  type="text"
                  readonly
                  disabled
                  hint="Se calcula automáticamente: Precio Venta + IVA"
                  persistent-hint
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

