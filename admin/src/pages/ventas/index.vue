<script setup>
import { ref, onMounted, computed } from 'vue'
import { getVentas, createVenta, updateVenta, deleteVenta, getPagosVenta, createPagoVenta } from '@/services/ventas'
import { getClientes } from '@/services/clientes'
import { getProductos } from '@/services/productos'
import { getMetodosPago } from '@/services/metodosPago'

const ventas = ref([])
const clientes = ref([])
const productos = ref([])
const metodosPago = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const dialogDelete = ref(false)
const dialogPagos = ref(false)
const editedIndex = ref(-1)
const selectedVenta = ref(null)
const pagosVenta = ref([])

const editedItem = ref({
  id: null,
  cliente_id: null,
  fecha: new Date().toISOString().split('T')[0],
  tipo_comprobante: '',
  numero_comprobante: '',
  productos: [],
})

const defaultItem = {
  id: null,
  cliente_id: null,
  fecha: new Date().toISOString().split('T')[0],
  tipo_comprobante: '',
  numero_comprobante: '',
  productos: [],
}

const nuevoPago = ref({
  metodo_pago_id: null,
  monto: 0,
  fecha_pago: new Date().toISOString().split('T')[0],
})

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Cliente', key: 'cliente_nombre', sortable: false },
  { title: 'Fecha', key: 'fecha' },
  { title: 'Total', key: 'total' },
  { title: 'Estado Pago', key: 'estado_pago' },
  { title: 'Comprobante', key: 'numero_comprobante' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const totalCalculado = computed(() => {
  return editedItem.value.productos.reduce((sum, p) => {
    return sum + (p.precio_unitario * p.cantidad)
  }, 0)
})

const fetchVentas = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await getVentas()
    console.log('Ventas response:', response)
    // Manejar respuesta paginada de Laravel
    if (response.data && Array.isArray(response.data)) {
      ventas.value = response.data
    } else if (Array.isArray(response)) {
      ventas.value = response
    } else {
      ventas.value = []
    }
  } catch (e) {
    error.value = e.message || 'Error al cargar ventas'
  } finally {
    loading.value = false
  }
}

const fetchClientes = async () => {
  try {
    const data = await getClientes()
    clientes.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    console.error('Error al cargar clientes:', e)
  }
}

const fetchProductos = async () => {
  try {
    const data = await getProductos()
    productos.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    console.error('Error al cargar productos:', e)
  }
}

const fetchMetodosPago = async () => {
  try {
    const data = await getMetodosPago()
    metodosPago.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    console.error('Error al cargar métodos de pago:', e)
  }
}

const editItem = (item) => {
  console.log('Editando venta:', item)
  editedIndex.value = ventas.value.indexOf(item)
  editedItem.value = Object.assign({}, {
    ...item,
    productos: item.items || []
  })
  dialog.value = true
}

const deleteItem = (item) => {
  console.log('Eliminando venta:', item)
  editedIndex.value = ventas.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialogDelete.value = true
}

const deleteItemConfirm = async () => {
  try {
    await deleteVenta(editedItem.value.id)
    ventas.value.splice(editedIndex.value, 1)
    closeDelete()
  } catch (e) {
    error.value = e.message || 'Error al eliminar venta'
  }
}

const verPagos = async (item) => {
  console.log('Viendo pagos de venta:', item)
  selectedVenta.value = item
  try {
    const data = await getPagosVenta(item.id)
    pagosVenta.value = Array.isArray(data) ? data : (data.data ?? [])
    dialogPagos.value = true
  } catch (e) {
    error.value = e.message || 'Error al cargar pagos'
  }
}

const registrarPago = async () => {
  try {
    await createPagoVenta(selectedVenta.value.id, nuevoPago.value)
    const data = await getPagosVenta(selectedVenta.value.id)
    pagosVenta.value = Array.isArray(data) ? data : (data.data ?? [])
    nuevoPago.value = {
      metodo_pago_id: null,
      monto: 0,
      fecha_pago: new Date().toISOString().split('T')[0],
    }
    await fetchVentas()
  } catch (e) {
    error.value = e.message || 'Error al registrar pago'
  }
}

const close = () => {
  dialog.value = false
  error.value = '' // Limpiar error al cerrar
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
    editedItem.value.total = totalCalculado.value
    
    // Preparar datos para enviar al backend
    const dataToSend = {
      ...editedItem.value,
      items: editedItem.value.productos // Cambiar 'productos' a 'items'
    }
    delete dataToSend.productos // Eliminar el campo productos
    
    if (editedIndex.value > -1) {
      const updated = await updateVenta(editedItem.value.id, dataToSend)
      Object.assign(ventas.value[editedIndex.value], updated)
    } else {
      const created = await createVenta(dataToSend)
      ventas.value.push(created)
    }
    close()
  } catch (e) {
    console.error('Error al guardar venta:', e)
    // Mejorar mensaje de error
    if (e.message.includes('límite de crédito')) {
      error.value = 'El cliente ha superado su límite de crédito. Por favor, verifique el saldo o ajuste el límite.'
    } else if (e.errors?.limite_credito) {
      error.value = e.errors.limite_credito[0] || 'Error de límite de crédito'
    } else {
      error.value = e.message || 'Error al guardar venta'
    }
  }
}

const agregarProducto = () => {
  editedItem.value.productos.push({
    producto_id: null,
    cantidad: 1,
    precio_unitario: 0,
    iva: 21.00,
  })
}

const eliminarProducto = (index) => {
  editedItem.value.productos.splice(index, 1)
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const getEstadoColor = (estado) => {
  const colores = {
    pendiente: 'warning',
    parcial: 'info',
    pagado: 'success',
    cancelado: 'error',
  }
  return colores[estado] || 'default'
}

onMounted(async () => {
  await fetchVentas()
  await fetchClientes()
  await fetchProductos()
  await fetchMetodosPago()
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center">
          <span class="text-h5">Ventas</span>
          <VBtn color="primary" @click="dialog = true">
            Nueva Venta
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" dismissible @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VDataTable
          :headers="headers"
          :items="ventas"
          :loading="loading"
          loading-text="Cargando ventas..."
          no-data-text="No hay ventas registradas"
          class="elevation-1"
        >
          <template #item.cliente_nombre="{ item }">
            {{ item.cliente ? `${item.cliente.nombre} ${item.cliente.apellido}` : 'N/A' }}
          </template>
          <template #item.total="{ item }">
            {{ formatPrice(item.total) }}
          </template>
          <template #item.estado_pago="{ item }">
            <VChip :color="getEstadoColor(item.estado_pago)" size="small">
              {{ item.estado_pago }}
            </VChip>
          </template>
          <template #item.actions="{ item }">
            <div class="d-flex ga-2">
              <VBtn
                icon
                size="small"
                color="success"
                variant="tonal"
                @click="verPagos(item)"
                title="Ver pagos"
              >
                <VIcon>mdi-cash-multiple</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="primary"
                variant="tonal"
                @click="editItem(item)"
                title="Editar venta"
              >
                <VIcon>mdi-pencil</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="deleteItem(item)"
                title="Eliminar venta"
              >
                <VIcon>mdi-delete</VIcon>
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Dialog para crear/editar venta -->
    <VDialog v-model="dialog" max-width="800px">
      <VCard>
        <VCardTitle>
          <span class="text-h5">{{ editedIndex === -1 ? 'Nueva' : 'Editar' }} Venta</span>
        </VCardTitle>

        <VCardText>
          <VAlert v-if="error" type="error" class="mb-4" closable @click:close="error = ''">
            {{ error }}
          </VAlert>
          
          <VContainer>
            <VRow>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="editedItem.cliente_id"
                  :items="clientes"
                  item-title="nombre"
                  item-value="id"
                  label="Cliente*"
                  required
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="editedItem.fecha"
                  label="Fecha*"
                  type="date"
                  required
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="editedItem.estado_pago"
                  :items="['pendiente', 'parcial', 'pagado', 'cancelado']"
                  label="Estado Pago*"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="editedItem.tipo_comprobante"
                  :items="['Factura A', 'Factura B', 'Factura C', 'Recibo', 'Presupuesto']"
                  label="Tipo Comprobante"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="editedItem.numero_comprobante"
                  label="Nro. Comprobante"
                />
              </VCol>
            </VRow>

            <VDivider class="my-4" />
            
            <div class="d-flex justify-space-between align-center mb-4">
              <h3>Productos</h3>
              <VBtn size="small" color="primary" @click="agregarProducto">
                Agregar Producto
              </VBtn>
            </div>

            <VRow v-for="(prod, index) in editedItem.productos" :key="index" class="mb-2">
              <VCol cols="12" md="5">
                <VSelect
                  v-model="prod.producto_id"
                  :items="productos"
                  item-title="nombre"
                  item-value="id"
                  label="Producto"
                  @update:model-value="prod.precio_unitario = productos.find(p => p.id === prod.producto_id)?.precio_unitario || 0; prod.iva = productos.find(p => p.id === prod.producto_id)?.iva || 21.00"
                />
              </VCol>
              <VCol cols="12" md="2">
                <VTextField
                  v-model.number="prod.cantidad"
                  label="Cantidad"
                  type="number"
                  step="0.01"
                  min="0.01"
                />
              </VCol>
              <VCol cols="12" md="2">
                <VTextField
                  v-model.number="prod.precio_unitario"
                  label="Precio Unit."
                  type="number"
                  step="0.01"
                />
              </VCol>
              <VCol cols="12" md="2">
                <VTextField
                  v-model.number="prod.iva"
                  label="IVA %"
                  type="number"
                  step="0.01"
                />
              </VCol>
              <VCol cols="12" md="1">
                <VBtn icon size="small" color="error" @click="eliminarProducto(index)">
                  <VIcon>mdi-delete</VIcon>
                </VBtn>
              </VCol>
            </VRow>

            <VDivider class="my-4" />
            
            <div class="text-h6 text-right">
              Total: {{ formatPrice(totalCalculado) }}
            </div>
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
          ¿Está seguro de eliminar esta venta?
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

    <!-- Dialog de pagos -->
    <VDialog v-model="dialogPagos" max-width="700px">
      <VCard>
        <VCardTitle>
          <span class="text-h5">Pagos de Venta #{{ selectedVenta?.id }}</span>
        </VCardTitle>

        <VCardText>
          <VRow class="mb-4">
            <VCol cols="12" md="4">
              <VSelect
                v-model="nuevoPago.metodo_pago_id"
                :items="metodosPago"
                item-title="nombre"
                item-value="id"
                label="Método de Pago"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model.number="nuevoPago.monto"
                label="Monto"
                type="number"
                step="0.01"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="nuevoPago.fecha"
                label="Fecha"
                type="date"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VBtn color="primary" @click="registrarPago">
                Registrar
              </VBtn>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <h3 class="mb-2">Historial de Pagos</h3>
          <VTable v-if="pagosVenta.length > 0" density="compact">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Método</th>
                <th>Monto</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pago in pagosVenta" :key="pago.id">
                <td>{{ pago.fecha }}</td>
                <td>{{ pago.metodo_pago?.nombre || 'N/A' }}</td>
                <td>{{ formatPrice(pago.monto) }}</td>
              </tr>
            </tbody>
          </VTable>
          <p v-else class="text-center text-disabled">No hay pagos registrados</p>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="dialogPagos = false">
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

