<script setup>
import { ref, onMounted, computed } from 'vue'
import { getVentas, deleteVenta, getPagosVenta, createPagoVenta } from '@/services/ventas'
import { getMetodosPago } from '@/services/metodosPago'
import { apiFetch } from '@/services/api'
import { toast } from '@/plugins/toast'
import NumberInput from '@/components/NumberInput.vue'

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
  // Campos para cheques
  numero_cheque: '',
  fecha_cheque: '',
  observaciones_cheque: '',
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
  // Validar que el monto no exceda el saldo pendiente + deuda de cuenta corriente
  const montoAPagar = parseFloat(nuevoPago.value.monto || 0)
  const saldoDisponible = saldoDisponibleParaPagar.value
  
  if (montoAPagar <= 0) {
    toast.error('El monto debe ser mayor a cero')
    return
  }
  
  if (montoAPagar > saldoDisponible) {
    toast.error(`El monto ($ ${montoAPagar.toLocaleString('es-AR')}) excede el saldo disponible ($ ${saldoDisponible.toLocaleString('es-AR')})`)
    return
  }
  
  try {
    await createPagoVenta(selectedVenta.value.id, nuevoPago.value)
    const data = await getPagosVenta(selectedVenta.value.id)
    pagosVenta.value = Array.isArray(data) ? data : (data.data ?? [])
    nuevoPago.value = {
      metodo_pago_id: null,
      monto: 0,
      fecha_pago: new Date().toISOString().split('T')[0],
      numero_cheque: '',
      fecha_cheque: '',
      observaciones_cheque: '',
    }
    toast.success('Pago registrado correctamente')
    await fetchVentas()
  } catch (e) {
    const errorMsg = e.message || 'Error al registrar pago'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

// Marcar cheque como cobrado
const marcarChequeCobrado = async (pago) => {
  try {
    console.log('Marcando cheque como cobrado:', pago.id)
    const response = await apiFetch(`/api/v1/pagos/${pago.id}/estado-cheque`, {
      method: 'PATCH',
      body: {
        estado_cheque: 'cobrado',
        fecha_cobro: new Date().toISOString().split('T')[0],
      },
    })
    console.log('Respuesta:', response)
    
    // Recargar pagos y ventas
    const data = await getPagosVenta(selectedVenta.value.id)
    pagosVenta.value = Array.isArray(data) ? data : (data.data ?? [])
    toast.success('Cheque marcado como cobrado')
    await fetchVentas()
  } catch (e) {
    console.error('Error completo:', e)
    toast.error('Error al actualizar el cheque: ' + (e.message || 'Error desconocido'))
  }
}

// Marcar cheque como rechazado
const marcarChequeRechazado = async (pago) => {
  try {
    await apiFetch(`/api/v1/pagos/${pago.id}/estado-cheque`, {
      method: 'PATCH',
      body: {
        estado_cheque: 'rechazado',
        fecha_cobro: new Date().toISOString().split('T')[0],
        observaciones_cheque: 'Cheque rechazado por el banco',
      },
    })
    
    // Recargar pagos y ventas
    const data = await getPagosVenta(selectedVenta.value.id)
    pagosVenta.value = Array.isArray(data) ? data : (data.data ?? [])
    toast.error('Cheque marcado como rechazado')
    await fetchVentas()
  } catch (e) {
    toast.error('Error al actualizar el cheque: ' + (e.message || 'Error desconocido'))
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
    numero_cheque: '',
    fecha_cheque: '',
    observaciones_cheque: '',
  }
}

// Calcular total pagado (solo pagos reales, sin cuenta corriente)
const totalPagado = computed(() => {
  return pagosVenta.value
    .filter(p => p.metodo_pago?.nombre !== 'Cuenta Corriente')
    .filter(p => !p.estado_cheque || p.estado_cheque === 'cobrado') // Solo cheques cobrados
    .reduce((sum, p) => sum + parseFloat(p.monto || 0), 0)
})

// Calcular total cheques pendientes
const totalChequesPendientes = computed(() => {
  return pagosVenta.value
    .filter(p => p.estado_cheque === 'pendiente')
    .reduce((sum, p) => sum + parseFloat(p.monto || 0), 0)
})

// Calcular total a cuenta corriente
const totalCuentaCorriente = computed(() => {
  return pagosVenta.value
    .filter(p => p.metodo_pago?.nombre === 'Cuenta Corriente')
    .reduce((sum, p) => sum + parseFloat(p.monto || 0), 0)
})

// Calcular saldo actual sin considerar el nuevo pago
const saldoActual = computed(() => {
  const total = parseFloat(selectedVenta.value?.total || 0)
  const pagado = totalPagado.value
  const cuentaCorriente = totalCuentaCorriente.value
  const chequesPendientes = totalChequesPendientes.value
  return total - pagado - cuentaCorriente - chequesPendientes
})

// Saldo disponible para pagar (incluye deuda de cuenta corriente)
const saldoDisponibleParaPagar = computed(() => {
  return saldoActual.value + totalCuentaCorriente.value
})

// Detectar si el método seleccionado es cheque
const metodoPagoSeleccionadoEsCheque = computed(() => {
  if (!nuevoPago.value.metodo_pago_id) return false
  const metodo = metodosPago.value.find(m => m.id === nuevoPago.value.metodo_pago_id)
  return metodo && metodo.nombre.toLowerCase() === 'cheque'
})

// Calcular saldo después de aplicar el nuevo pago (para previsualización)
const saldoDespuesDelPago = computed(() => {
  const saldo = saldoActual.value
  const nuevoMonto = parseFloat(nuevoPago.value.monto || 0)
  return saldo - nuevoMonto
})
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
          <!-- Resumen de la venta -->
          <VRow class="mb-4">
            <VCol cols="12">
              <VCard color="primary" variant="tonal">
                <VCardText>
                  <VRow>
                    <VCol cols="6" md="2">
                      <div class="text-caption">Total Venta</div>
                      <div class="text-h6">{{ formatPrice(selectedVenta?.total || 0) }}</div>
                    </VCol>
                    <VCol cols="6" md="2">
                      <div class="text-caption">Cobrado</div>
                      <div class="text-h6 text-success">{{ formatPrice(totalPagado) }}</div>
                    </VCol>
                    <VCol cols="6" md="2">
                      <div class="text-caption">Cheques ⏳</div>
                      <div class="text-h6 text-warning">{{ formatPrice(totalChequesPendientes) }}</div>
                    </VCol>
                    <VCol cols="6" md="2">
                      <div class="text-caption">Deuda C.C.</div>
                      <div class="text-h6 text-info">{{ formatPrice(totalCuentaCorriente) }}</div>
                    </VCol>
                    <VCol cols="12" md="4">
                      <div class="text-caption">Saldo Pendiente</div>
                      <div class="text-h6" :class="saldoActual > 0 ? 'text-warning' : 'text-success'">
                        {{ formatPrice(Math.max(0, saldoActual)) }}
                      </div>
                      <div class="text-caption text-medium-emphasis" style="font-size: 10px;">
                        (sin asignar)
                      </div>
                    </VCol>
                  </VRow>
                  
                  <!-- Mostrar previsualización si hay monto ingresado -->
                  <VRow v-if="nuevoPago.monto > 0" class="mt-2">
                    <VCol cols="12">
                      <VDivider class="mb-2" />
                      <div class="text-caption text-center">Después de este pago</div>
                      <div class="text-center">
                        <span class="text-body-2">Saldo restante: </span>
                        <span class="font-weight-bold" :class="saldoDespuesDelPago > 0 ? 'text-warning' : 'text-success'">
                          {{ formatPrice(Math.max(0, saldoDespuesDelPago)) }}
                        </span>
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Alerta cuando está totalmente pagado -->
          <VAlert 
            v-if="Math.round(saldoDisponibleParaPagar) <= 0" 
            type="success" 
            variant="tonal"
            class="mb-4"
          >
            ✅ Esta venta está completamente pagada y sin deuda pendiente
          </VAlert>

          <!-- Alerta informativa si hay deuda en cuenta corriente -->
          <VAlert 
            v-else-if="totalCuentaCorriente > 0 && saldoActual <= 0"
            type="info" 
            variant="tonal"
            class="mb-4"
          >
            ℹ️ Hay <strong>{{ formatPrice(totalCuentaCorriente) }}</strong> en cuenta corriente (deuda del cliente). 
            Puede registrar pagos para cancelar esta deuda.
          </VAlert>

          <!-- Alerta si hay saldo sin asignar -->
          <VAlert 
            v-else-if="saldoActual > 0"
            type="warning" 
            variant="tonal"
            class="mb-4"
          >
            ⚠️ Hay <strong>{{ formatPrice(saldoActual) }}</strong> sin asignar. 
            Puede registrar un pago o asignarlo a cuenta corriente.
          </VAlert>

          <!-- Formulario de nuevo pago -->
          <VRow v-if="saldoDisponibleParaPagar > 0" class="mb-4">
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
              <NumberInput
                v-model="nuevoPago.monto"
                label="Monto"
                :decimals="0"
                prefix="$"
              />
              <VBtn 
                v-if="saldoDisponibleParaPagar > 0"
                size="x-small" 
                color="info" 
                variant="text" 
                class="mt-1"
                @click="nuevoPago.monto = Math.round(saldoDisponibleParaPagar)"
              >
                <span v-if="totalCuentaCorriente > 0 && saldoActual <= 0">
                  Pagar deuda: {{ formatPrice(totalCuentaCorriente) }}
                </span>
                <span v-else-if="totalCuentaCorriente > 0 && saldoActual > 0">
                  Pagar todo ({{ formatPrice(saldoDisponibleParaPagar) }})
                </span>
                <span v-else>
                  Pagar saldo completo ({{ formatPrice(saldoActual) }})
                </span>
              </VBtn>
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="nuevoPago.fecha_pago"
                label="Fecha de Pago"
                type="date"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VBtn 
                color="primary" 
                @click="registrarPago" 
                block
                :disabled="!nuevoPago.metodo_pago_id || !nuevoPago.monto || saldoDisponibleParaPagar <= 0"
              >
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
                <th>Detalles</th>
                <th class="text-end">Monto</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pago in pagosVenta" :key="pago.id">
                <td>{{ pago.fecha }}</td>
                <td>{{ pago.metodo_pago?.nombre || 'N/A' }}</td>
                <td>
                  <div v-if="pago.estado_cheque" class="text-caption">
                    <div v-if="pago.numero_cheque">N°: {{ pago.numero_cheque }}</div>
                    <div v-if="pago.fecha_cheque">Fecha: {{ pago.fecha_cheque }}</div>
                    <div v-if="pago.observaciones_cheque" class="text-muted">{{ pago.observaciones_cheque }}</div>
                  </div>
                  <span v-else class="text-muted">-</span>
                </td>
                <td class="text-end">{{ formatPrice(pago.monto) }}</td>
                <td class="text-center">
                  <!-- Cuenta Corriente -->
                  <VChip 
                    v-if="pago.metodo_pago?.nombre === 'Cuenta Corriente'"
                    size="small"
                    color="info"
                    variant="tonal"
                  >
                    Deuda
                  </VChip>
                  <!-- Cheque Pendiente -->
                  <VChip 
                    v-else-if="pago.estado_cheque === 'pendiente'"
                    size="small"
                    color="warning"
                    variant="tonal"
                  >
                    Cheque Pendiente
                  </VChip>
                  <!-- Cheque Cobrado -->
                  <VChip 
                    v-else-if="pago.estado_cheque === 'cobrado'"
                    size="small"
                    color="success"
                    variant="tonal"
                  >
                    Cheque Cobrado
                  </VChip>
                  <!-- Cheque Rechazado -->
                  <VChip 
                    v-else-if="pago.estado_cheque === 'rechazado'"
                    size="small"
                    color="error"
                    variant="tonal"
                  >
                    Cheque Rechazado
                  </VChip>
                  <!-- Pago Normal -->
                  <VChip 
                    v-else
                    size="small"
                    color="success"
                    variant="tonal"
                  >
                    Pagado
                  </VChip>
                </td>
                <td class="text-center">
                  <!-- Botones para cheques pendientes -->
                  <div v-if="pago.estado_cheque === 'pendiente'" class="d-flex gap-1 justify-center">
                    <VBtn
                      size="x-small"
                      color="success"
                      variant="tonal"
                      @click="marcarChequeCobrado(pago)"
                      title="Marcar como cobrado"
                    >
                      ✓ Cobrado
                    </VBtn>
                    <VBtn
                      size="x-small"
                      color="error"
                      variant="tonal"
                      @click="marcarChequeRechazado(pago)"
                      title="Marcar como rechazado"
                    >
                      ✗ Rechazado
                    </VBtn>
                  </div>
                  <span v-else class="text-muted">-</span>
                </td>
              </tr>
              <!-- Resumen de totales -->
              <tr v-if="totalPagado > 0" class="font-weight-bold bg-success-lighten-5">
                <td colspan="3" class="text-end">Total Recibido (cobrado):</td>
                <td class="text-end text-success">{{ formatPrice(totalPagado) }}</td>
                <td colspan="2"></td>
              </tr>
              <tr v-if="totalChequesPendientes > 0" class="font-weight-bold bg-warning-lighten-5">
                <td colspan="3" class="text-end">Cheques Pendientes:</td>
                <td class="text-end text-warning">{{ formatPrice(totalChequesPendientes) }}</td>
                <td colspan="2"></td>
              </tr>
              <tr v-if="totalCuentaCorriente > 0" class="font-weight-bold bg-info-lighten-5">
                <td colspan="3" class="text-end">Deuda en Cuenta Corriente:</td>
                <td class="text-end text-info">{{ formatPrice(totalCuentaCorriente) }}</td>
                <td colspan="2"></td>
              </tr>
              <tr v-if="saldoActual > 0" class="font-weight-bold bg-warning-lighten-5">
                <td colspan="3" class="text-end">Saldo sin asignar:</td>
                <td class="text-end text-warning">{{ formatPrice(saldoActual) }}</td>
                <td colspan="2"></td>
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

