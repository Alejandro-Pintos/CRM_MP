<script setup>
import { ref, onMounted } from 'vue'
import { getChequesEmitidos, debitarCheque, anularCheque, deleteChequeEmitido } from '@/services/chequesEmitidos'
import { getProveedores } from '@/services/proveedores'
import { toast } from '@/plugins/toast'

const cheques = ref([])
const proveedores = ref([])
const loading = ref(false)
const resumen = ref({})

// Filtros
const filtros = ref({
  estado: 'todos',
  proveedor_id: null,
  fecha_desde: null,
  fecha_hasta: null,
})

const headers = [
  { title: 'ID', key: 'id', width: 80 },
  { title: 'Proveedor', key: 'proveedor.nombre' },
  { title: 'Banco', key: 'banco' },
  { title: 'Número', key: 'numero' },
  { title: 'Monto', key: 'monto' },
  { title: 'F. Emisión', key: 'fecha_emision' },
  { title: 'F. Vencimiento', key: 'fecha_vencimiento' },
  { title: 'Estado', key: 'estado' },
  { title: 'Acciones', key: 'actions', sortable: false, width: 180 },
]

const estadosDisponibles = [
  { value: 'todos', title: 'Todos' },
  { value: 'pendiente', title: 'Pendientes' },
  { value: 'debitado', title: 'Debitados' },
  { value: 'anulado', title: 'Anulados' },
]

onMounted(async () => {
  await Promise.all([cargarCheques(), cargarProveedores()])
})

const cargarCheques = async () => {
  loading.value = true
  try {
    const response = await getChequesEmitidos(filtros.value)
    
    // Manejar diferentes estructuras de respuesta
    if (response.data) {
      cheques.value = Array.isArray(response.data) ? response.data : []
      resumen.value = response.resumen || {
        total: 0,
        monto_total: 0,
        pendientes: 0,
        debitados: 0,
        anulados: 0
      }
    } else {
      cheques.value = []
      resumen.value = {
        total: 0,
        monto_total: 0,
        pendientes: 0,
        debitados: 0,
        anulados: 0
      }
    }
  } catch (error) {
    console.error('Error al cargar cheques emitidos:', error)
    cheques.value = []
    resumen.value = {
      total: 0,
      monto_total: 0,
      pendientes: 0,
      debitados: 0,
      anulados: 0
    }
    // Solo mostrar toast si es un error real de red/servidor
    if (error.response?.status >= 500 || !error.response) {
      toast.error('Error al cargar cheques emitidos')
    }
  } finally {
    loading.value = false
  }
}

const cargarProveedores = async () => {
  try {
    const data = await getProveedores()
    proveedores.value = Array.isArray(data) ? data : (data.data || [])
  } catch (error) {
    console.error('Error al cargar proveedores:', error)
  }
}

const aplicarFiltros = async () => {
  await cargarCheques()
}

const limpiarFiltros = () => {
  filtros.value = {
    estado: 'todos',
    proveedor_id: null,
    fecha_desde: null,
    fecha_hasta: null,
  }
  cargarCheques()
}

const marcarDebitado = async (cheque) => {
  if (!confirm(`¿Marcar cheque ${cheque.numero} como debitado?`)) return
  
  try {
    await debitarCheque(cheque.id)
    toast.success('Cheque marcado como debitado')
    await cargarCheques()
  } catch (error) {
    toast.error('Error al actualizar cheque')
  }
}

const marcarAnulado = async (cheque) => {
  const motivo = prompt('Motivo de anulación:')
  if (!motivo) return
  
  try {
    await anularCheque(cheque.id, motivo)
    toast.success('Cheque anulado')
    await cargarCheques()
  } catch (error) {
    toast.error('Error al anular cheque')
  }
}

const eliminarCheque = async (cheque) => {
  if (!confirm(`¿Eliminar cheque ${cheque.numero}? Esta acción no se puede deshacer.`)) return
  
  try {
    await deleteChequeEmitido(cheque.id)
    toast.success('Cheque eliminado')
    await cargarCheques()
  } catch (error) {
    toast.error(error.message || 'Error al eliminar cheque')
  }
}

const getEstadoColor = (estado) => {
  const colores = {
    pendiente: 'warning',
    debitado: 'success',
    anulado: 'error',
  }
  return colores[estado] || 'default'
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value || 0)
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('es-AR')
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <h3 class="text-h5 mb-4">
        <VIcon icon="mdi-checkbook" class="mr-2" />
        Cheques Emitidos
      </h3>
    </VCol>

    <!-- Filtros -->
    <VCol cols="12">
      <VCard class="mb-4">
        <VCardText>
          <VRow>
            <VCol cols="12" md="3">
              <VSelect
                v-model="filtros.estado"
                :items="estadosDisponibles"
                label="Estado"
                placeholder="Seleccione un estado"
                hide-details
              />
            </VCol>

            <VCol cols="12" md="3">
              <VAutocomplete
                v-model="filtros.proveedor_id"
                :items="proveedores"
                item-title="nombre"
                item-value="id"
                label="Proveedor"
                placeholder="Todos los proveedores"
                clearable
                hide-details
              />
            </VCol>

            <VCol cols="12" md="2">
              <VTextField
                v-model="filtros.fecha_desde"
                label="Desde"
                type="date"
                hide-details
                placeholder="Fecha inicio"
              />
            </VCol>

            <VCol cols="12" md="2">
              <VTextField
                v-model="filtros.fecha_hasta"
                label="Hasta"
                type="date"
                hide-details
                placeholder="Fecha fin"
              />
            </VCol>

            <VCol cols="12" md="2">
              <VBtn 
                color="primary" 
                @click="aplicarFiltros" 
                block
                :loading="loading"
                :disabled="loading"
              >
                <VIcon icon="mdi-filter" start />
                Filtrar
              </VBtn>
            </VCol>

            <VCol cols="12" md="1" class="d-flex align-center">
              <VTooltip text="Limpiar filtros">
                <template #activator="{ props }">
                  <VBtn 
                    variant="tonal" 
                    @click="limpiarFiltros"
                    :disabled="loading"
                    v-bind="props"
                    icon
                  >
                    <VIcon icon="mdi-filter-remove" />
                  </VBtn>
                </template>
              </VTooltip>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Resumen -->
    <VCol cols="12">
      <VRow>
        <VCol cols="12" md="3">
          <VCard>
            <VCardText>
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption text-medium-emphasis">Total Cheques</div>
                  <div class="text-h4 mt-1">{{ resumen.total || 0 }}</div>
                </div>
                <VIcon icon="mdi-checkbook" size="40" color="primary" class="opacity-50" />
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" md="3">
          <VCard color="warning" variant="tonal">
            <VCardText>
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption">Pendientes</div>
                  <div class="text-h4 mt-1">{{ resumen.pendientes || 0 }}</div>
                </div>
                <VIcon icon="mdi-clock-outline" size="40" class="opacity-50" />
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" md="3">
          <VCard color="success" variant="tonal">
            <VCardText>
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption">Debitados</div>
                  <div class="text-h4 mt-1">{{ resumen.debitados || 0 }}</div>
                </div>
                <VIcon icon="mdi-check-circle-outline" size="40" class="opacity-50" />
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" md="3">
          <VCard color="primary" variant="tonal">
            <VCardText>
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-caption">Monto Total</div>
                  <div class="text-h5 mt-1">{{ formatPrice(resumen.monto_total || 0) }}</div>
                </div>
                <VIcon icon="mdi-currency-usd" size="40" class="opacity-50" />
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCol>

    <!-- Tabla -->
    <VCol cols="12">
      <VDataTable
        :headers="headers"
        :items="cheques"
        :loading="loading"
        loading-text="Cargando cheques..."
        no-data-text="No hay cheques emitidos registrados"
      >
        <template #item.monto="{ item }">
          {{ formatPrice(item.monto) }}
        </template>

        <template #item.fecha_emision="{ item }">
          {{ formatDate(item.fecha_emision) }}
        </template>

        <template #item.fecha_vencimiento="{ item }">
          {{ formatDate(item.fecha_vencimiento) }}
        </template>

        <template #item.estado="{ item }">
          <VChip :color="getEstadoColor(item.estado)" size="small">
            {{ item.estado }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <VTooltip v-if="item.estado === 'pendiente'" text="Marcar como debitado">
              <template #activator="{ props }">
                <VBtn
                  size="small"
                  color="success"
                  variant="tonal"
                  @click="marcarDebitado(item)"
                  v-bind="props"
                >
                  <VIcon icon="mdi-check" />
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip v-if="item.estado === 'pendiente'" text="Anular cheque">
              <template #activator="{ props }">
                <VBtn
                  size="small"
                  color="error"
                  variant="tonal"
                  @click="marcarAnulado(item)"
                  v-bind="props"
                >
                  <VIcon icon="mdi-close" />
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip v-if="item.estado === 'pendiente' && !item.pago_proveedor_id" text="Eliminar cheque">
              <template #activator="{ props }">
                <VBtn
                  size="small"
                  color="grey"
                  variant="text"
                  @click="eliminarCheque(item)"
                  v-bind="props"
                >
                  <VIcon icon="mdi-delete" />
                </VBtn>
              </template>
            </VTooltip>

            <VChip v-if="item.estado !== 'pendiente'" size="small" variant="flat" color="grey-lighten-2">
              Sin acciones
            </VChip>
          </div>
        </template>
      </VDataTable>
    </VCol>
  </VRow>
</template>
