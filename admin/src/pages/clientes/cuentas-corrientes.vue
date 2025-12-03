<script setup>
import { ref, onMounted, computed } from 'vue'
import { getClientes, getCuentaCorriente, registrarPagoCuentaCorriente } from '@/services/clientes'
import { getMetodosPago } from '@/services/metodos-pago'
import { toast } from '@/plugins/toast'
import { apiFetch } from '@/services/api'

const clientes = ref([])
const loading = ref(false)
const error = ref('')
const dialogCuentaCorriente = ref(false)
const selectedCliente = ref(null)
const cuentaCorriente = ref([])
const cuentaCorrienteData = ref(null)
const search = ref('')
const metodosPago = ref([])
const loadingPago = ref(false)

// Formulario de pago
const formPago = ref({
  monto: 0,
  metodo_pago_id: null,
  fecha_pago: new Date().toISOString().split('T')[0],
  observaciones: ''
})

// Filtrar métodos de pago (excluir "Cuenta Corriente")
const metodosPagoFiltrados = computed(() => {
  return metodosPago.value.filter(metodo => 
    metodo.nombre.toLowerCase() !== 'cuenta corriente'
  )
})

// Saldo actual del cliente seleccionado
const saldoActualCliente = computed(() => {
  return cuentaCorrienteData.value?.cliente?.saldo_actual || 0
})

// Crédito disponible del cliente seleccionado
const creditoDisponible = computed(() => {
  const limite = cuentaCorrienteData.value?.cliente?.limite_credito || 0
  const saldo = saldoActualCliente.value
  return limite - saldo
})

// Filtrar clientes que tienen cuenta corriente (saldo_actual != 0 o límite_credito > 0)
const clientesConCuentaCorriente = computed(() => {
  let resultado = clientes.value.filter(cliente => 
    cliente.limite_credito > 0 || cliente.saldo_actual != 0
  )
  
  // Aplicar búsqueda
  if (search.value) {
    const searchLower = search.value.toLowerCase()
    resultado = resultado.filter(cliente => {
      const nombreCompleto = `${cliente.nombre} ${cliente.apellido}`.toLowerCase()
      const email = (cliente.email || '').toLowerCase()
      const cuit = (cliente.cuit_cuil || '').toLowerCase()
      
      return nombreCompleto.includes(searchLower) ||
             email.includes(searchLower) ||
             cuit.includes(searchLower)
    })
  }
  
  return resultado
})

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Cliente', key: 'nombre_completo' },
  { title: 'Email', key: 'email' },
  { title: 'Límite Crédito', key: 'limite_credito' },
  { title: 'Saldo Actual', key: 'saldo_actual' },
  { title: 'Disponible', key: 'disponible' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const headersCuentaCorriente = [
  { title: 'Fecha', key: 'fecha' },
  { title: 'Tipo', key: 'tipo' },
  { title: 'Descripción', key: 'descripcion' },
  { title: 'Debe', key: 'debe' },
  { title: 'Haber', key: 'haber' },
  { title: 'Saldo Movimiento', key: 'monto' },
]

const fetchClientes = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await getClientes()
    if (response.data && Array.isArray(response.data)) {
      clientes.value = response.data
    } else if (Array.isArray(response)) {
      clientes.value = response
    } else {
      clientes.value = []
    }
  } catch (e) {
    const errorMsg = e.message || 'Error al cargar clientes'
    error.value = errorMsg
    toast.error(errorMsg)
  } finally {
    loading.value = false
  }
}

const verCuentaCorriente = async (item) => {
  selectedCliente.value = item
  try {
    const response = await getCuentaCorriente(item.id)
    // Guardar toda la respuesta
    cuentaCorrienteData.value = response
    // Usar la estructura correcta de la respuesta: response.movimientos
    cuentaCorriente.value = response.movimientos || []
    
    // Prellenar el formulario con el saldo total
    formPago.value.monto = response.cliente?.saldo_actual || 0
    
    dialogCuentaCorriente.value = true
  } catch (e) {
    toast.error('Error al cargar cuenta corriente')
    console.error('Error al cargar cuenta corriente:', e)
  }
}

const closeCuentaCorriente = () => {
  dialogCuentaCorriente.value = false
  selectedCliente.value = null
  cuentaCorriente.value = []
  cuentaCorrienteData.value = null
  resetFormPago()
}

const resetFormPago = () => {
  formPago.value = {
    monto: 0,
    metodo_pago_id: null,
    fecha_pago: new Date().toISOString().split('T')[0],
    observaciones: ''
  }
}

const setMontoTotal = () => {
  formPago.value.monto = saldoActualCliente.value
}

const registrarPago = async () => {
  // Validaciones frontend
  if (formPago.value.monto <= 0) {
    toast.error('El monto debe ser mayor a cero')
    return
  }
  
  if (formPago.value.monto > saldoActualCliente.value) {
    toast.error(`El monto no puede ser mayor al saldo actual (${formatPrice(saldoActualCliente.value)})`)
    return
  }
  
  if (!formPago.value.metodo_pago_id) {
    toast.error('Debe seleccionar un método de pago')
    return
  }
  
  loadingPago.value = true
  try {
    const response = await registrarPagoCuentaCorriente(selectedCliente.value.id, formPago.value)
    
    // Actualizar los datos locales
    if (response.movimiento) {
      cuentaCorriente.value.push(response.movimiento)
    }
    
    if (response.cliente) {
      // Actualizar datos del cliente en la respuesta
      cuentaCorrienteData.value.cliente.saldo_actual = response.cliente.saldo_actual
      
      // Actualizar en la lista de clientes también
      const clienteIdx = clientes.value.findIndex(c => c.id === selectedCliente.value.id)
      if (clienteIdx !== -1) {
        clientes.value[clienteIdx].saldo_actual = response.cliente.saldo_actual
      }
      
      // Actualizar selectedCliente
      selectedCliente.value.saldo_actual = response.cliente.saldo_actual
    }
    
    toast.success('Pago registrado exitosamente')
    resetFormPago()
    
    // Recargar la cuenta corriente para tener datos frescos
    await verCuentaCorriente(selectedCliente.value)
  } catch (e) {
    const errorMsg = e.message || 'Error al registrar el pago'
    toast.error(errorMsg)
    console.error('Error al registrar pago:', e)
  } finally {
    loadingPago.value = false
  }
}

const fetchMetodosPago = async () => {
  try {
    const response = await getMetodosPago()
    metodosPago.value = Array.isArray(response) ? response : (response.data || [])
  } catch (e) {
    console.error('Error al cargar métodos de pago:', e)
  }
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const getDisponible = (cliente) => {
  // Crédito disponible = Límite - Saldo Actual (deuda)
  const limite = parseFloat(cliente.limite_credito) || 0
  const saldo = parseFloat(cliente.saldo_actual) || 0
  return limite - saldo
}

const getSaldoColor = (saldo) => {
  const saldoNum = parseFloat(saldo) || 0
  if (saldoNum === 0) return 'success'  // Sin deuda (verde)
  return 'error'  // Tiene deuda (rojo)
}

const getSaldoDisplay = (saldo) => {
  // Mostrar el valor absoluto de la deuda (si es negativo, mostrarlo positivo)
  const saldoNum = parseFloat(saldo) || 0
  return Math.abs(saldoNum)
}

const getTipoColor = (tipo) => {
  const colors = {
    'venta': 'error',
    'pago': 'success',
    'ajuste': 'info',
    'nota_credito': 'warning'
  }
  return colors[tipo] || 'secondary'
}

const getTipoLabel = (tipo) => {
  const labels = {
    'venta': 'Venta',
    'pago': 'Pago',
    'ajuste': 'Ajuste',
    'nota_credito': 'Nota de Crédito'
  }
  return labels[tipo] || tipo
}

// Computed para calcular saldo acumulado
const movimientosConSaldo = computed(() => {
  let saldoAcumulado = 0
  return cuentaCorriente.value.map(movimiento => {
    saldoAcumulado += parseFloat(movimiento.monto || 0)
    return {
      ...movimiento,
      saldo_acumulado: saldoAcumulado
    }
  })
})

const recalcularSaldos = async () => {
  loading.value = true
  try {
    const response = await apiFetch('/api/v1/cuentas-corrientes/recalcular', {
      method: 'POST'
    })
    
    toast.success(`Recalculación completada: ${response.actualizados} clientes actualizados`)
    
    // Recargar clientes
    await fetchClientes()
  } catch (e) {
    toast.error('Error al recalcular saldos: ' + (e.message || 'Error desconocido'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchClientes()
  fetchMetodosPago()
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center flex-wrap ga-4">
          <span class="text-h5">Cuentas Corrientes</span>
          <div class="d-flex ga-2 align-center">
            <!-- Botón de recalcular saldos removido - los saldos se calculan automáticamente -->
            <VTextField
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Buscar clientes"
              single-line
              hide-details
              density="compact"
              style="min-width: 300px;"
              clearable
            />
          </div>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" dismissible @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VAlert v-if="clientesConCuentaCorriente.length === 0 && !loading" type="info" class="mb-4">
          No hay clientes con cuenta corriente activa. Solo se muestran clientes con límite de crédito asignado o saldo pendiente.
        </VAlert>

        <VDataTable
          :headers="headers"
          :items="clientesConCuentaCorriente"
          :loading="loading"
          loading-text="Cargando cuentas corrientes..."
          no-data-text="No hay cuentas corrientes"
          class="elevation-1"
        >
          <template #item.nombre_completo="{ item }">
            {{ item.nombre }} {{ item.apellido }}
          </template>

          <template #item.limite_credito="{ item }">
            {{ formatPrice(item.limite_credito) }}
          </template>

          <template #item.saldo_actual="{ item }">
            <VChip :color="getSaldoColor(item.saldo_actual)" size="small">
              {{ formatPrice(item.saldo_actual) }}
              <span v-if="parseFloat(item.saldo_actual) > 0" class="ml-1">(Deuda)</span>
            </VChip>
          </template>

          <template #item.disponible="{ item }">
            <VChip :color="getDisponible(item) >= 0 ? 'success' : 'error'" size="small">
              {{ formatPrice(getDisponible(item)) }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <VBtn
              icon
              size="small"
              variant="text"
              @click="verCuentaCorriente(item)"
            >
              <VIcon>ri-file-list-3-line</VIcon>
              <VTooltip activator="parent" location="top">
                Ver Movimientos
              </VTooltip>
            </VBtn>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Diálogo Cuenta Corriente -->
    <VDialog v-model="dialogCuentaCorriente" max-width="900px" scrollable>
      <VCard>
        <VCardTitle>
          <div class="d-flex justify-space-between align-center">
            <span>Cuenta Corriente - {{ selectedCliente?.nombre }} {{ selectedCliente?.apellido }}</span>
            <VBtn icon variant="text" @click="closeCuentaCorriente">
              <VIcon>ri-close-line</VIcon>
            </VBtn>
          </div>
        </VCardTitle>

        <VCardText>
          <VRow class="mb-4">
            <VCol cols="12" md="4">
              <VCard variant="tonal" color="info">
                <VCardText>
                  <div class="text-caption">Límite de Crédito</div>
                  <div class="text-h6">{{ formatPrice(cuentaCorrienteData?.cliente?.limite_credito || 0) }}</div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard variant="tonal" :color="getSaldoColor(saldoActualCliente)">
                <VCardText>
                  <div class="text-caption">Saldo Actual (Deuda)</div>
                  <div class="text-h6">{{ formatPrice(saldoActualCliente) }}</div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard variant="tonal" color="success">
                <VCardText>
                  <div class="text-caption">Crédito Disponible</div>
                  <div class="text-h6">{{ formatPrice(creditoDisponible) }}</div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <VDataTable
            :headers="headersCuentaCorriente"
            :items="movimientosConSaldo"
            :items-per-page="15"
            no-data-text="No hay movimientos registrados"
            class="elevation-1"
            density="compact"
            show-expand
          >
            <template #item.fecha="{ item }">
              {{ new Date(item.fecha).toLocaleDateString('es-AR', { 
                year: 'numeric', 
                month: '2-digit', 
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
              }) }}
            </template>

            <template #item.tipo="{ item }">
              <VChip 
                :color="item.tipo === 'venta' ? 'primary' : 'success'" 
                size="small" 
                variant="flat"
              >
                {{ item.tipo === 'venta' ? 'Venta' : 'Pago' }}
              </VChip>
            </template>

            <template #item.descripcion="{ item }">
              <div>
                <div class="font-weight-medium">{{ item.descripcion }}</div>
                <div v-if="item.detalles && item.detalles.length > 0" class="text-caption text-medium-emphasis">
                  {{ item.detalles.length }} producto(s) - Click para expandir
                </div>
              </div>
            </template>

            <template #item.debe="{ item }">
              <span v-if="item.debe > 0" class="text-error font-weight-bold">
                {{ formatPrice(item.debe) }}
              </span>
              <span v-else class="text-grey">-</span>
            </template>

            <template #item.haber="{ item }">
              <span v-if="item.haber > 0" class="text-success font-weight-bold">
                {{ formatPrice(item.haber) }}
              </span>
              <span v-else class="text-grey">-</span>
            </template>

            <template #item.monto="{ item }">
              <VChip 
                :color="item.saldo_acumulado > 0 ? 'error' : item.saldo_acumulado < 0 ? 'success' : 'secondary'" 
                size="small"
              >
                {{ formatPrice(item.saldo_acumulado) }}
              </VChip>
            </template>
            
            <template #expanded-row="{ item, columns }">
              <tr v-if="item.detalles && item.detalles.length > 0">
                <td :colspan="columns.length" class="pa-4 bg-grey-lighten-4">
                  <div class="text-subtitle-2 mb-2">Detalles de la venta:</div>
                  <VTable density="compact">
                    <thead>
                      <tr>
                        <th>Producto</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Precio Unit.</th>
                        <th class="text-right">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(detalle, idx) in item.detalles" :key="idx">
                        <td>{{ detalle.producto }}</td>
                        <td class="text-right">{{ detalle.cantidad }}</td>
                        <td class="text-right">{{ formatPrice(detalle.precio_unitario) }}</td>
                        <td class="text-right font-weight-bold">{{ formatPrice(detalle.subtotal) }}</td>
                      </tr>
                    </tbody>
                  </VTable>
                </td>
              </tr>
            </template>
          </VDataTable>

          <!-- Sección: Registrar Pago a Cuenta Corriente -->
          <VCard class="mt-6" v-if="saldoActualCliente > 0" variant="tonal" color="success">
            <VCardTitle class="d-flex align-center">
              <VIcon class="mr-2">ri-money-dollar-circle-line</VIcon>
              Registrar Pago a Cuenta Corriente
            </VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model.number="formPago.monto"
                    label="Monto a pagar"
                    type="number"
                    step="0.01"
                    min="0"
                    :max="saldoActualCliente"
                    prepend-inner-icon="ri-money-dollar-circle-line"
                    :hint="`Saldo actual: ${formatPrice(saldoActualCliente)}`"
                    persistent-hint
                    :error-messages="formPago.monto > saldoActualCliente ? 'El monto no puede ser mayor al saldo actual' : ''"
                  >
                    <template #append>
                      <VBtn
                        size="small"
                        variant="text"
                        color="primary"
                        @click="setMontoTotal"
                      >
                        Pagar total
                      </VBtn>
                    </template>
                  </VTextField>
                </VCol>
                
                <VCol cols="12" md="6">
                  <VSelect
                    v-model="formPago.metodo_pago_id"
                    :items="metodosPagoFiltrados"
                    item-title="nombre"
                    item-value="id"
                    label="Método de pago"
                    prepend-inner-icon="ri-bank-card-line"
                    hint="No se puede pagar con Cuenta Corriente"
                    persistent-hint
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="formPago.fecha_pago"
                    label="Fecha de pago"
                    type="date"
                    prepend-inner-icon="ri-calendar-line"
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="formPago.observaciones"
                    label="Observaciones (opcional)"
                    prepend-inner-icon="ri-message-2-line"
                    placeholder="Ej: Pago parcial de deuda"
                  />
                </VCol>

                <VCol cols="12">
                  <div class="d-flex justify-end ga-2">
                    <VBtn
                      color="success"
                      :loading="loadingPago"
                      :disabled="!formPago.metodo_pago_id || formPago.monto <= 0 || formPago.monto > saldoActualCliente"
                      @click="registrarPago"
                      prepend-icon="ri-check-line"
                    >
                      Registrar pago de {{ formatPrice(formPago.monto) }}
                    </VBtn>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <VAlert v-else-if="saldoActualCliente === 0" type="success" class="mt-6">
            <div class="d-flex align-center">
              <VIcon class="mr-2">ri-check-line</VIcon>
              <span>El cliente no tiene deuda pendiente en cuenta corriente</span>
            </div>
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="primary" variant="text" @click="closeCuentaCorriente">
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
