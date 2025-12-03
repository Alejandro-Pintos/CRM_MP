<script setup>
import { ref, onMounted, computed } from 'vue'
import { apiFetch } from '@/services/api'
import { toast } from '@/plugins/toast'

const cheques = ref([])
const historial = ref([])
const resumen = ref({
  total: 0,
  vencidos: 0,
  proximos_a_vencer: 0,
  sin_fecha: 0,
  monto_total: 0,
})
const loading = ref(false)
const search = ref('')
const filtroEstado = ref('todos') // todos, vencidos, alertas, normales
const tabActual = ref('pendientes') // pendientes, historial

// Modal de edición
const dialogEditar = ref(false)
const chequeEditando = ref(null)
const datosEdicion = ref({
  numero_cheque: '',
  fecha_cheque: '',
  fecha_cobro: '',
  observaciones_cheque: '',
})

// Diálogos de confirmación
const dialogCobrar = ref(false)
const chequeCobrar = ref(null)
const dialogRechazar = ref(false)
const chequeRechazar = ref(null)
const motivoRechazo = ref('')

const headers = [
  { title: 'Nº Cheque', key: 'numero_cheque', align: 'start' },
  { title: 'Cliente', key: 'cliente.nombre' },
  { title: 'Venta', key: 'venta_id', align: 'center' },
  { title: 'Monto', key: 'monto', align: 'end' },
  { title: 'Fecha Emisión', key: 'fecha_cheque', align: 'center' },
  { title: 'Fecha Cobro', key: 'fecha_cobro', align: 'center' },
  { title: 'Días Restantes', key: 'dias_restantes', align: 'center' },
  { title: 'Estado', key: 'estado_alerta', align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const headersHistorial = [
  { title: 'Nº Cheque', key: 'numero_cheque', align: 'start' },
  { title: 'Cliente', key: 'cliente.nombre' },
  { title: 'Venta', key: 'venta_id', align: 'center' },
  { title: 'Monto', key: 'monto', align: 'end' },
  { title: 'Fecha Emisión', key: 'fecha_cheque', align: 'center' },
  { title: 'Fecha Vencimiento', key: 'fecha_cobro', align: 'center' },
  { title: 'Fecha Procesado', key: 'fecha_procesado', align: 'center' },
  { title: 'Estado', key: 'estado_cheque', align: 'center' },
]

const fetchCheques = async () => {
  loading.value = true
  try {
    const response = await apiFetch('/api/v1/cheques/pendientes')
    console.log('Cheques response:', response)
    cheques.value = response.cheques || []
    resumen.value = response.resumen || {
      total: 0,
      vencidos: 0,
      proximos_a_vencer: 0,
      sin_fecha: 0,
      monto_total: 0,
    }
  } catch (e) {
    console.error('Error al cargar cheques:', e)
    toast.error('Error al cargar cheques: ' + (e.message || 'Error desconocido'))
    // Inicializar con datos vacíos en caso de error
    cheques.value = []
    resumen.value = {
      total: 0,
      vencidos: 0,
      proximos_a_vencer: 0,
      sin_fecha: 0,
      monto_total: 0,
    }
  } finally {
    loading.value = false
  }
}

const fetchHistorial = async () => {
  loading.value = true
  try {
    const response = await apiFetch('/api/v1/cheques/historial')
  console.log('Historial response raw:', response)
  // Mostrar conteos para detectar discrepancias entre backend y frontend
  console.log('Historial counts: total (backend) =', response.total, 'cheques.length (frontend) =', (response.cheques || []).length)
  historial.value = response.cheques || []
    // Log detallado para debugging: mostrar solo campos relevantes y estructura esperada
    try {
      console.log('Historial parsed:', historial.value.map(h => ({
        id: h.id,
        numero_cheque: h.numero_cheque,
        venta_id: h.venta_id,
        monto: h.monto,
        fecha_cheque: h.fecha_cheque,
        fecha_cobro: h.fecha_cobro,
        fecha_procesado: h.fecha_procesado,
        estado_cheque: h.estado_cheque,
        cliente: h.cliente ? { id: h.cliente.id, nombre: h.cliente.nombre } : null
      })))
    } catch (logErr) {
      console.warn('Error formateando historial para log:', logErr)
      console.log('Historial raw value:', historial.value)
    }
  } catch (e) {
    console.error('Error al cargar historial:', e)
    toast.error('Error al cargar historial: ' + (e.message || 'Error desconocido'))
    historial.value = []
  } finally {
    loading.value = false
  }
}

const chequesFiltrados = computed(() => {
  let resultado = cheques.value

  // Filtro por estado
  if (filtroEstado.value === 'vencidos') {
    resultado = resultado.filter(c => c.vencido)
  } else if (filtroEstado.value === 'alertas') {
    resultado = resultado.filter(c => c.proximo_a_vencer)
  } else if (filtroEstado.value === 'normales') {
    resultado = resultado.filter(c => !c.vencido && !c.proximo_a_vencer && c.estado_alerta !== 'sin_fecha')
  } else if (filtroEstado.value === 'sin_fecha') {
    resultado = resultado.filter(c => c.estado_alerta === 'sin_fecha')
  }

  // Búsqueda
  if (search.value) {
    const searchLower = search.value.toLowerCase()
    resultado = resultado.filter(c => 
      c.numero_cheque?.toLowerCase().includes(searchLower) ||
      c.cliente.nombre.toLowerCase().includes(searchLower)
    )
  }

  return resultado
})

const historialFiltrado = computed(() => {
  let resultado = historial.value

  // Búsqueda
  if (search.value) {
    const searchLower = search.value.toLowerCase()
    resultado = resultado.filter(c => 
      c.numero_cheque?.toLowerCase().includes(searchLower) ||
      c.cliente.nombre.toLowerCase().includes(searchLower) ||
      c.estado_cheque?.toLowerCase().includes(searchLower)
    )
  }

  return resultado
})

const marcarCobrado = async (cheque) => {
  chequeCobrar.value = cheque
  dialogCobrar.value = true
}

const confirmarCobrado = async () => {
  if (!chequeCobrar.value) return

  loading.value = true
  try {
    const response = await apiFetch(`/api/v1/cheques/${chequeCobrar.value.id}/cobrar`, {
      method: 'POST',
      body: {
        fecha_cobro: new Date().toISOString().split('T')[0],
      }
    })
    
    toast.success('Cheque marcado como cobrado')
    
    // Actualizar en las listas
    const index = cheques.value.findIndex(c => c.id === chequeCobrar.value.id)
    if (index !== -1) {
      cheques.value.splice(index, 1)
    }
    
    await fetchCheques()
    await fetchHistorial()
  } catch (e) {
    toast.error('Error al cobrar cheque: ' + (e.message || 'Error desconocido'))
  } finally {
    loading.value = false
    dialogCobrar.value = false
    chequeCobrar.value = null
  }
}

const marcarRechazado = async (cheque) => {
  chequeRechazar.value = cheque
  motivoRechazo.value = ''
  dialogRechazar.value = true
}

const confirmarRechazado = async () => {
  if (!chequeRechazar.value) return
  
  if (!motivoRechazo.value.trim()) {
    toast.warning('Debe ingresar un motivo de rechazo')
    return
  }

  loading.value = true
  try {
    const response = await apiFetch(`/api/v1/cheques/${chequeRechazar.value.id}/rechazar`, {
      method: 'POST',
      body: {
        motivo_rechazo: motivoRechazo.value,
      }
    })
    
    toast.success('Cheque marcado como rechazado')
    
    // Quitar de la lista de pendientes
    const index = cheques.value.findIndex(c => c.id === chequeRechazar.value.id)
    if (index !== -1) {
      cheques.value.splice(index, 1)
    }
    
    await fetchCheques()
    await fetchHistorial()
  } catch (e) {
    toast.error('Error al rechazar cheque: ' + (e.message || 'Error desconocido'))
  } finally {
    loading.value = false
    dialogRechazar.value = false
    chequeRechazar.value = null
    motivoRechazo.value = ''
  }
}

const abrirEdicion = (cheque) => {
  chequeEditando.value = cheque
  datosEdicion.value = {
    numero_cheque: cheque.numero_cheque || '',
    fecha_cheque: cheque.fecha_cheque || new Date().toISOString().split('T')[0],
    fecha_cobro: cheque.fecha_cobro || '', // fecha_vencimiento en el backend
    observaciones_cheque: cheque.observaciones_cheque || '',
  }
  dialogEditar.value = true
}

const guardarEdicion = async () => {
  if (!datosEdicion.value.numero_cheque || !datosEdicion.value.fecha_cheque) {
    toast.warning('El número y fecha del cheque son requeridos')
    return
  }

  loading.value = true
  try {
    const response = await apiFetch(`/api/v1/cheques/${chequeEditando.value.id}`, {
      method: 'PATCH',
      body: {
        numero: datosEdicion.value.numero_cheque,
        fecha_emision: datosEdicion.value.fecha_cheque,
        fecha_vencimiento: datosEdicion.value.fecha_cobro || null,
        observaciones: datosEdicion.value.observaciones_cheque || null,
      }
    })
    
    toast.success('Datos del cheque actualizados')
    dialogEditar.value = false
    
    // Actualizar el cheque en la lista sin recargar todo
    const index = cheques.value.findIndex(c => c.id === chequeEditando.value.id)
    if (index !== -1) {
      cheques.value[index] = response
    }
    
    await fetchCheques() // Recargar para actualizar resumen
  } catch (e) {
    toast.error('Error al actualizar cheque: ' + (e.message || 'Error desconocido'))
  } finally {
    loading.value = false
  }
}

const cancelarEdicion = () => {
  dialogEditar.value = false
  chequeEditando.value = null
  datosEdicion.value = {
    numero_cheque: '',
    fecha_cheque: '',
    fecha_cobro: '',
    observaciones_cheque: '',
  }
}

const getEstadoColor = (estado) => {
  const colors = {
    'vencido': 'error',
    'alerta': 'warning',
    'normal': 'success',
    'sin_fecha': 'info'
  }
  return colors[estado] || 'secondary'
}

const getEstadoLabel = (estado) => {
  const labels = {
    'vencido': 'Vencido',
    'alerta': 'Próximo a vencer',
    'normal': 'Normal',
    'sin_fecha': 'Sin fecha definida'
  }
  return labels[estado] || estado
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('es-AR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
}

const formatDateTime = (datetime) => {
  return new Date(datetime).toLocaleString('es-AR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getEstadoChequeColor = (estado) => {
  const colors = {
    'pendiente': 'warning',
    'cobrado': 'success',
    'rechazado': 'error'
  }
  return colors[estado] || 'secondary'
}

onMounted(() => {
  fetchCheques()
  fetchHistorial()
})
</script>

<template>
  <div class="pa-6">
    <!-- Header con título y botones -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">Seguimiento de Cheques</h1>
        <p class="text-body-2 text-medium-emphasis">Monitoreo y gestión de cheques pendientes</p>
      </div>
      <div class="d-flex ga-2">
        <VBtn
          color="primary"
          prepend-icon="ri-refresh-line"
          @click="fetchCheques"
          :loading="loading"
        >
          Actualizar
        </VBtn>
      </div>
    </div>

    <!-- Resumen de cheques con iconos -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard 
          class="pa-4" 
          :class="{ 'border-primary': filtroEstado === 'todos' }"
          hover
          @click="filtroEstado = 'todos'"
          style="cursor: pointer;"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis mb-1">Total Cheques</div>
              <div class="text-h4 font-weight-bold mb-1">{{ resumen.total || 0 }}</div>
              <div class="text-caption text-success">{{ formatPrice(resumen.monto_total || 0) }}</div>
            </div>
            <VAvatar color="primary" variant="tonal" size="48">
              <VIcon size="28">ri-bank-card-line</VIcon>
            </VAvatar>
          </div>
        </VCard>
      </VCol>
      
      <VCol cols="12" sm="6" md="3">
        <VCard 
          class="pa-4"
          :class="{ 'border-error': filtroEstado === 'vencidos' }"
          hover
          @click="filtroEstado = 'vencidos'"
          style="cursor: pointer;"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis mb-1">Vencidos</div>
              <div class="text-h4 font-weight-bold text-error">{{ resumen.vencidos || 0 }}</div>
              <div class="text-caption">Requieren atención</div>
            </div>
            <VAvatar color="error" variant="tonal" size="48">
              <VIcon size="28">ri-alert-line</VIcon>
            </VAvatar>
          </div>
        </VCard>
      </VCol>
      
      <VCol cols="12" sm="6" md="3">
        <VCard 
          class="pa-4"
          :class="{ 'border-warning': filtroEstado === 'alertas' }"
          hover
          @click="filtroEstado = 'alertas'"
          style="cursor: pointer;"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis mb-1">Próximos a vencer</div>
              <div class="text-h4 font-weight-bold text-warning">{{ resumen.proximos_a_vencer || 0 }}</div>
              <div class="text-caption">En 7 días o menos</div>
            </div>
            <VAvatar color="warning" variant="tonal" size="48">
              <VIcon size="28">ri-time-line</VIcon>
            </VAvatar>
          </div>
        </VCard>
      </VCol>
      
      <VCol cols="12" sm="6" md="3">
        <VCard 
          class="pa-4"
          :class="{ 'border-info': filtroEstado === 'sin_fecha' }"
          hover
          @click="filtroEstado = 'sin_fecha'"
          style="cursor: pointer;"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis mb-1">Sin fecha</div>
              <div class="text-h4 font-weight-bold text-info">{{ resumen.sin_fecha || 0 }}</div>
              <div class="text-caption">Requieren fecha</div>
            </div>
            <VAvatar color="info" variant="tonal" size="48">
              <VIcon size="28">ri-question-line</VIcon>
            </VAvatar>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de cheques con pestañas -->
    <VCard>
      <VTabs v-model="tabActual" bg-color="primary" dark>
        <VTab value="pendientes">
          <VIcon class="mr-2">ri-time-line</VIcon>
          Cheques Pendientes
          <VChip class="ml-2" color="white" size="small">{{ cheques.length }}</VChip>
        </VTab>
        <VTab value="historial">
          <VIcon class="mr-2">ri-history-line</VIcon>
          Historial
          <VChip class="ml-2" color="white" size="small">{{ historial.length }}</VChip>
        </VTab>
      </VTabs>

      <VCardTitle class="d-flex justify-space-between align-center py-4">
        <div>
          <span class="text-h6">{{ tabActual === 'pendientes' ? 'Cheques Pendientes' : 'Historial de Cheques' }}</span>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ tabActual === 'pendientes' ? `${chequesFiltrados.length} de ${cheques.length}` : `${historialFiltrado.length} de ${historial.length}` }} cheques
          </div>
        </div>
        <VTextField
          v-model="search"
          prepend-inner-icon="ri-search-line"
          label="Buscar cheque o cliente"
          single-line
          hide-details
          density="compact"
          variant="outlined"
          style="max-width: 300px;"
          clearable
        />
      </VCardTitle>

      <VDivider />

      <VWindow v-model="tabActual">
        <!-- Tab: Cheques Pendientes -->
        <VWindowItem value="pendientes">
          <!-- Filtros para pendientes -->
          <VCardText class="pb-0">
            <VChipGroup v-model="filtroEstado" column>
              <VChip value="todos" variant="outlined">Todos</VChip>
              <VChip value="vencidos" color="error" variant="outlined">Vencidos</VChip>
              <VChip value="alertas" color="warning" variant="outlined">Próximos a vencer</VChip>
              <VChip value="normales" color="success" variant="outlined">Normales</VChip>
              <VChip value="sin_fecha" color="info" variant="outlined">Sin fecha</VChip>
            </VChipGroup>
          </VCardText>

          <VDivider />

          <VCardText class="pa-0">
        <!-- Estado vacío mejorado -->
        <div v-if="!loading && cheques.length === 0" class="text-center py-16">
          <VAvatar color="grey-lighten-3" size="80" class="mb-4">
            <VIcon size="48" color="grey">ri-bank-card-line</VIcon>
          </VAvatar>
          <h3 class="text-h6 mb-2">No hay cheques pendientes</h3>
          <p class="text-body-2 text-medium-emphasis">
            Los cheques que recibas aparecerán aquí para su seguimiento.
          </p>
        </div>

        <!-- Estado vacío con filtro -->
        <div v-else-if="!loading && chequesFiltrados.length === 0" class="text-center py-16">
          <VAvatar color="grey-lighten-3" size="80" class="mb-4">
            <VIcon size="48" color="grey">ri-filter-line</VIcon>
          </VAvatar>
          <h3 class="text-h6 mb-2">No se encontraron resultados</h3>
          <p class="text-body-2 text-medium-emphasis mb-4">
            {{ search ? 'Intenta con otros términos de búsqueda' : 'No hay cheques en esta categoría' }}
          </p>
          <VBtn 
            v-if="search || filtroEstado !== 'todos'" 
            variant="outlined" 
            @click="search = ''; filtroEstado = 'todos'"
          >
            Limpiar filtros
          </VBtn>
        </div>

        <!-- Tabla -->
        <VDataTable
          v-else
          :headers="headers"
          :items="chequesFiltrados"
          :loading="loading"
          loading-text="Cargando cheques..."
          items-per-page="10"
          class="text-no-wrap"
        >
          <!-- Número de cheque con icono -->
          <template #item.numero_cheque="{ item }">
            <div class="d-flex align-center ga-2">
              <VIcon size="20" color="primary">ri-bank-card-line</VIcon>
              <span class="font-weight-medium">{{ item.numero_cheque || 'Sin número' }}</span>
            </div>
          </template>

          <!-- Cliente con icono -->
          <template #item.cliente.nombre="{ item }">
            <div class="d-flex align-center ga-2">
              <VAvatar size="32" color="primary" variant="tonal">
                <VIcon size="18">ri-user-line</VIcon>
              </VAvatar>
              <span>{{ item.cliente?.nombre || 'Sin cliente' }}</span>
            </div>
          </template>

          <!-- Venta ID con enlace -->
          <template #item.venta_id="{ item }">
            <VChip size="small" variant="tonal" color="secondary">
              #{{ item.venta_id }}
            </VChip>
          </template>

          <!-- Monto destacado -->
          <template #item.monto="{ item }">
            <span class="font-weight-bold text-primary">{{ formatPrice(item.monto) }}</span>
          </template>

          <!-- Fecha de Emisión -->
          <template #item.fecha_cheque="{ item }">
            <div class="text-center">
              <span v-if="item.fecha_cheque">{{ formatDate(item.fecha_cheque) }}</span>
              <span v-else class="text-medium-emphasis">Sin fecha</span>
            </div>
          </template>

          <!-- Fecha de Cobro (vencimiento) -->
          <template #item.fecha_cobro="{ item }">
            <div class="text-center">
              <div class="font-weight-medium">
                {{ item.fecha_cobro ? formatDate(item.fecha_cobro) : 'Sin fecha' }}
              </div>
              <div v-if="item.observaciones" class="text-caption text-medium-emphasis mt-1">{{ item.observaciones }}</div>
            </div>
          </template>

          <!-- Días restantes con indicador visual -->
          <template #item.dias_restantes="{ item }">
            <VChip 
              v-if="item.dias_restantes !== null"
              :color="item.vencido ? 'error' : (item.proximo_a_vencer ? 'warning' : 'success')" 
              size="small"
              :prepend-icon="item.vencido ? 'ri-close-circle-line' : (item.proximo_a_vencer ? 'ri-alarm-warning-line' : 'ri-time-line')"
            >
              {{ item.vencido ? `${Math.abs(item.dias_restantes)}d vencido` : `${item.dias_restantes} días` }}
            </VChip>
            <VChip v-else color="info" size="small" prepend-icon="ri-question-line">
              Sin fecha
            </VChip>
          </template>

          <!-- Estado con chip mejorado -->
          <template #item.estado_alerta="{ item }">
            <VChip 
              :color="getEstadoColor(item.estado_alerta)" 
              size="small" 
              variant="flat"
            >
              {{ getEstadoLabel(item.estado_alerta) }}
            </VChip>
          </template>

          <!-- Acciones con tooltips mejorados -->
          <template #item.actions="{ item }">
            <div class="d-flex ga-1 justify-center">
              <VBtn
                icon
                size="small"
                color="info"
                variant="tonal"
                @click="abrirEdicion(item)"
              >
                <VIcon>ri-edit-line</VIcon>
                <VTooltip activator="parent" location="top">
                  Editar datos del cheque
                </VTooltip>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="success"
                variant="tonal"
                @click="marcarCobrado(item)"
              >
                <VIcon>ri-check-line</VIcon>
                <VTooltip activator="parent" location="top">
                  <div class="text-center">
                    <div class="font-weight-bold">Marcar como Cobrado</div>
                    <div class="text-caption">El cheque fue efectivizado</div>
                  </div>
                </VTooltip>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="marcarRechazado(item)"
              >
                <VIcon>ri-close-line</VIcon>
                <VTooltip activator="parent" location="top">
                  <div class="text-center">
                    <div class="font-weight-bold">Marcar como Rechazado</div>
                    <div class="text-caption">El cheque fue rechazado</div>
                  </div>
                </VTooltip>
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VWindowItem>

    <!-- Tab: Historial de Cheques -->
    <VWindowItem value="historial">
      <VCardText class="pa-0">
        <!-- Estado vacío -->
        <div v-if="!loading && historial.length === 0" class="text-center py-16">
          <VAvatar color="grey-lighten-3" size="80" class="mb-4">
            <VIcon size="48" color="grey">ri-history-line</VIcon>
          </VAvatar>
          <h3 class="text-h6 mb-2">No hay historial de cheques</h3>
          <p class="text-body-2 text-medium-emphasis">
            Los cheques procesados (cobrados/rechazados) aparecerán aquí.
          </p>
        </div>

        <!-- Tabla de historial -->
        <VDataTable
          v-else
          :headers="headersHistorial"
          :items="historialFiltrado"
          :loading="loading"
          loading-text="Cargando historial..."
          items-per-page="15"
          class="text-no-wrap"
        >
          <!-- Número de cheque -->
          <template #item.numero_cheque="{ item }">
            <div class="d-flex align-center ga-2">
              <VIcon size="20" color="grey">ri-bank-card-line</VIcon>
              <span class="font-weight-medium">{{ item.numero_cheque || 'Sin número' }}</span>
            </div>
          </template>

          <!-- Cliente -->
          <template #item.cliente.nombre="{ item }">
            <div class="font-weight-medium">{{ item.cliente.nombre }}</div>
          </template>

          <!-- Monto -->
          <template #item.monto="{ item }">
            <span class="font-weight-bold text-success">{{ formatPrice(item.monto) }}</span>
          </template>

          <!-- Fechas -->
          <template #item.fecha_cheque="{ item }">
            <span v-if="item.fecha_cheque">{{ formatDate(item.fecha_cheque) }}</span>
            <span v-else class="text-medium-emphasis">Sin fecha</span>
          </template>

          <template #item.fecha_cobro="{ item }">
            <span v-if="item.fecha_cobro">{{ formatDate(item.fecha_cobro) }}</span>
            <span v-else class="text-medium-emphasis">-</span>
          </template>

          <template #item.fecha_procesado="{ item }">
            <div class="text-caption">{{ formatDateTime(item.fecha_procesado) }}</div>
          </template>

          <!-- Estado del cheque -->
          <template #item.estado_cheque="{ item }">
            <VChip 
              :color="getEstadoChequeColor(item.estado_cheque)" 
              size="small"
              variant="flat"
            >
              <VIcon v-if="item.estado_cheque === 'cobrado'" size="16" class="mr-1">ri-check-line</VIcon>
              <VIcon v-else-if="item.estado_cheque === 'rechazado'" size="16" class="mr-1">ri-close-line</VIcon>
              <VIcon v-else size="16" class="mr-1">ri-time-line</VIcon>
              {{ item.estado_cheque === 'cobrado' ? 'Cobrado' : (item.estado_cheque === 'rechazado' ? 'Rechazado' : 'Pendiente') }}
            </VChip>
          </template>
        </VDataTable>
      </VCardText>
    </VWindowItem>
  </VWindow>
</VCard>

    <!-- Modal de Edición de Cheque -->
    <VDialog v-model="dialogEditar" max-width="600" persistent>
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center bg-info pa-4">
          <div class="d-flex align-center ga-2">
            <VIcon color="white" size="28">ri-edit-line</VIcon>
            <span class="text-h6 text-white">Editar Datos del Cheque</span>
          </div>
          <VBtn
            icon
            variant="text"
            color="white"
            size="small"
            @click="cancelarEdicion"
          >
            <VIcon>ri-close-line</VIcon>
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-6">
          <VAlert v-if="chequeEditando" type="info" variant="tonal" class="mb-4">
            <div class="d-flex align-center ga-2">
              <VIcon>ri-information-line</VIcon>
              <div>
                <div class="font-weight-bold">Venta #{{ chequeEditando.venta_id }}</div>
                <div class="text-caption">Cliente: {{ chequeEditando.cliente?.nombre }}</div>
                <div class="text-caption">Monto: {{ formatPrice(chequeEditando.monto) }}</div>
              </div>
            </div>
          </VAlert>

          <VRow>
            <!-- Número de Cheque -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model="datosEdicion.numero_cheque"
                label="Número de Cheque *"
                prepend-inner-icon="ri-hashtag"
                placeholder="Ej: 00112233"
                variant="outlined"
                :rules="[v => !!v || 'Número requerido']"
              />
            </VCol>

            <!-- Fecha de Emisión -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model="datosEdicion.fecha_cheque"
                label="Fecha de Emisión *"
                type="date"
                prepend-inner-icon="ri-calendar-line"
                variant="outlined"
                :rules="[v => !!v || 'Fecha requerida']"
              />
            </VCol>

            <!-- Fecha de Cobro -->
            <VCol cols="12">
              <VTextField
                v-model="datosEdicion.fecha_cobro"
                label="Fecha de Cobro Estimada"
                type="date"
                prepend-inner-icon="ri-calendar-check-line"
                variant="outlined"
                hint="Fecha estimada en que se podrá cobrar el cheque"
                persistent-hint
              />
            </VCol>

            <!-- Observaciones -->
            <VCol cols="12">
              <VTextarea
                v-model="datosEdicion.observaciones_cheque"
                label="Observaciones"
                prepend-inner-icon="ri-file-text-line"
                placeholder="Información adicional sobre el cheque..."
                variant="outlined"
                rows="3"
                auto-grow
              />
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            color="secondary"
            @click="cancelarEdicion"
            :disabled="loading"
          >
            <VIcon class="mr-2">ri-close-line</VIcon>
            Cancelar
          </VBtn>
          <VBtn
            color="info"
            @click="guardarEdicion"
            :loading="loading"
            :disabled="!datosEdicion.numero_cheque || !datosEdicion.fecha_cheque"
          >
            <VIcon class="mr-2">ri-save-line</VIcon>
            Guardar Cambios
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo de confirmación para cobrar cheque -->
    <VDialog v-model="dialogCobrar" max-width="500px">
      <VCard>
        <VCardTitle class="text-h5">
          ¿Confirmar cobro de cheque?
        </VCardTitle>
        <VCardText>
          <p>¿Está seguro de marcar el cheque <strong>#{{ chequeCobrar?.numero_cheque }}</strong> como cobrado?</p>
          <p class="text-caption mt-2">Esta acción actualizará el estado del cheque a "Cobrado".</p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="dialogCobrar = false">
            Cancelar
          </VBtn>
          <VBtn color="success" variant="text" @click="confirmarCobrado" :loading="loading">
            Confirmar Cobro
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo de confirmación para rechazar cheque -->
    <VDialog v-model="dialogRechazar" max-width="500px">
      <VCard>
        <VCardTitle class="text-h5">
          ¿Confirmar rechazo de cheque?
        </VCardTitle>
        <VCardText>
          <p class="mb-4">Está a punto de marcar el cheque <strong>#{{ chequeRechazar?.numero_cheque }}</strong> como rechazado.</p>
          <VTextarea
            v-model="motivoRechazo"
            label="Motivo del rechazo *"
            placeholder="Ingrese el motivo por el cual se rechaza el cheque"
            rows="3"
            variant="outlined"
            :rules="[v => !!v || 'El motivo es requerido']"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="dialogRechazar = false">
            Cancelar
          </VBtn>
          <VBtn 
            color="error" 
            variant="text" 
            @click="confirmarRechazado" 
            :loading="loading"
            :disabled="!motivoRechazo.trim()"
          >
            Confirmar Rechazo
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.border-primary {
  border: 2px solid rgb(var(--v-theme-primary)) !important;
}
.border-error {
  border: 2px solid rgb(var(--v-theme-error)) !important;
}
.border-warning {
  border: 2px solid rgb(var(--v-theme-warning)) !important;
}
.border-success {
  border: 2px solid rgb(var(--v-theme-success)) !important;
}
</style>
