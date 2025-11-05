<script setup>
import { ref, onMounted, computed } from 'vue'
import { getVentas, deleteVenta, getPagosVenta, createPagoVenta } from '@/services/ventas'
import { getMetodosPago } from '@/services/metodosPago'
import { toast } from '@/plugins/toast'

const ventas = ref([])
const metodosPago = ref([])
const loading = ref(false)
const error = ref('')
const dialogDelete = ref(false)
const dialogPagos = ref(false)
const selectedVenta = ref(null)
const pagosVenta = ref([])
const search = ref('')

const nuevoPago = ref({
  metodo_pago_id: null,
  monto: 0,
  fecha_pago: new Date().toISOString().split('T')[0],
})

// Filtrar ventas por búsqueda
const ventasFiltradas = computed(() => {
  if (!search.value) return ventas.value
  
  const searchLower = search.value.toLowerCase()
  return ventas.value.filter(venta => {
    const cliente = (venta.cliente_nombre || '').toLowerCase()
    const id = String(venta.id || '')
    const comprobante = (venta.numero_comprobante || '').toLowerCase()
    
    return cliente.includes(searchLower) ||
           id.includes(searchLower) ||
           comprobante.includes(searchLower)
  })
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
    const errorMsg = e.message || 'Error al cargar ventas'
    error.value = errorMsg
    toast.error(errorMsg)
  } finally {
    loading.value = false
  }
}

const fetchMetodosPago = async () => {
  try {
    const data = await getMetodosPago()
    metodosPago.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    toast.error('Error al cargar métodos de pago')
    console.error('Error al cargar métodos de pago:', e)
  }
}

const deleteItem = (item) => {
  selectedVenta.value = item
  dialogDelete.value = true
}

const deleteItemConfirm = async () => {
  try {
    await deleteVenta(selectedVenta.value.id)
    await fetchVentas()
    toast.success('Venta eliminada correctamente')
    closeDelete()
  } catch (e) {
    const errorMsg = e.message || 'Error al eliminar venta'
    error.value = errorMsg
    toast.error(errorMsg)
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
    const errorMsg = e.message || 'Error al cargar pagos'
    error.value = errorMsg
    toast.error(errorMsg)
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
    toast.success('Pago registrado correctamente')
    await fetchVentas()
  } catch (e) {
    const errorMsg = e.message || 'Error al registrar pago'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const closeDelete = () => {
  dialogDelete.value = false
  error.value = ''
  selectedVenta.value = null
}

const closePagos = () => {
  dialogPagos.value = false
  selectedVenta.value = null
  pagosVenta.value = []
  nuevoPago.value = {
    metodo_pago_id: null,
    monto: 0,
    fecha_pago: new Date().toISOString().split('T')[0],
  }
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
  await fetchMetodosPago()
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center flex-wrap ga-2">
          <span class="text-h5">Historial de Ventas</span>
          <VTextField
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            label="Buscar por cliente, ID o comprobante"
            single-line
            hide-details
            density="compact"
            style="min-width: 300px;"
            clearable
          />
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" dismissible @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VDataTable
          :headers="headers"
          :items="ventasFiltradas"
          :loading="loading"
          loading-text="Cargando ventas..."
          no-data-text="No hay ventas registradas"
          class="elevation-1"
        >
          <template #item.cliente_nombre="{ item }">
            {{ item.cliente_nombre || 'N/A' }}
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
                color="info"
                variant="tonal"
                @click="verPagos(item)"
                title="Ver y gestionar pagos"
              >
                <VIcon>ri-money-dollar-circle-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="deleteItem(item)"
                title="Eliminar venta"
              >
                <VIcon>ri-delete-bin-6-line</VIcon>
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Dialog para confirmar eliminación -->
    <VDialog v-model="dialogDelete" max-width="500px">
      <VCard>
        <VCardTitle class="text-h5">
          ¿Está seguro de eliminar esta venta?
        </VCardTitle>
        <VCardText>
          <p>Venta #{{ selectedVenta?.id }} - Cliente: {{ selectedVenta?.cliente_nombre }}</p>
          <p><strong>Total: {{ formatPrice(selectedVenta?.total || 0) }}</strong></p>
        </VCardText>
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
                v-model="nuevoPago.fecha_pago"
                label="Fecha de Pago"
                type="date"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VBtn color="primary" @click="registrarPago" block>
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
                <th class="text-end">Monto</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pago in pagosVenta" :key="pago.id">
                <td>{{ pago.fecha }}</td>
                <td>{{ pago.metodo_pago?.nombre || 'N/A' }}</td>
                <td class="text-end">{{ formatPrice(pago.monto) }}</td>
              </tr>
              <tr class="font-weight-bold">
                <td colspan="2" class="text-end">Total Pagado:</td>
                <td class="text-end text-primary">
                  {{ formatPrice(pagosVenta.reduce((sum, p) => sum + parseFloat(p.monto), 0)) }}
                </td>
              </tr>
            </tbody>
          </VTable>
          <VAlert v-else type="info" variant="tonal">
            No hay pagos registrados para esta venta
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="closePagos">
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

