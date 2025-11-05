<script setup>
import { ref, onMounted, computed } from 'vue'
import { getClientes, getCuentaCorriente } from '@/services/clientes'
import { toast } from '@/plugins/toast'

const clientes = ref([])
const loading = ref(false)
const error = ref('')
const dialogCuentaCorriente = ref(false)
const selectedCliente = ref(null)
const cuentaCorriente = ref([])
const search = ref('')

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
    cuentaCorriente.value = Array.isArray(response) ? response : (response.data ?? [])
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
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const getDisponible = (cliente) => {
  return (cliente.limite_credito || 0) - (cliente.saldo_actual || 0)
}

const getSaldoColor = (saldo) => {
  if (saldo < 0) return 'error'
  if (saldo === 0) return 'success'
  return 'warning'
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

onMounted(fetchClientes)
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center flex-wrap ga-4">
          <span class="text-h5">Cuentas Corrientes</span>
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
                  <div class="text-h6">{{ formatPrice(selectedCliente?.limite_credito || 0) }}</div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard variant="tonal" :color="getSaldoColor(selectedCliente?.saldo_actual || 0)">
                <VCardText>
                  <div class="text-caption">Saldo Actual</div>
                  <div class="text-h6">{{ formatPrice(selectedCliente?.saldo_actual || 0) }}</div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard variant="tonal" color="success">
                <VCardText>
                  <div class="text-caption">Crédito Disponible</div>
                  <div class="text-h6">{{ formatPrice(getDisponible(selectedCliente || {})) }}</div>
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
              <VChip :color="getTipoColor(item.tipo)" size="small" variant="tonal">
                {{ getTipoLabel(item.tipo) }}
              </VChip>
            </template>

            <template #item.debe="{ item }">
              <span v-if="parseFloat(item.monto) > 0" class="text-error font-weight-bold">
                {{ formatPrice(Math.abs(item.monto)) }}
              </span>
              <span v-else class="text-grey">-</span>
            </template>

            <template #item.haber="{ item }">
              <span v-if="parseFloat(item.monto) < 0" class="text-success font-weight-bold">
                {{ formatPrice(Math.abs(item.monto)) }}
              </span>
              <span v-else class="text-grey">-</span>
            </template>

            <template #item.monto="{ item }">
              <VChip 
                :color="parseFloat(item.saldo_acumulado) > 0 ? 'error' : parseFloat(item.saldo_acumulado) < 0 ? 'success' : 'secondary'" 
                size="small"
              >
                {{ formatPrice(item.saldo_acumulado) }}
              </VChip>
            </template>
          </VDataTable>
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
