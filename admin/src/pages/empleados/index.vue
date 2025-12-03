<script setup>
import { ref, onMounted, computed } from 'vue'
import { 
  getEmpleados, 
  createEmpleado, 
  updateEmpleado, 
  deleteEmpleado,
  getPagosEmpleado,
  createPagoEmpleado,
  deletePagoEmpleado 
} from '@/services/empleados'
import { getMetodosPago } from '@/services/metodosPago'
import { toast } from '@/plugins/toast'

const empleados = ref([])
const metodosPago = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const dialogDelete = ref(false)
const dialogPagos = ref(false)
const dialogNuevoPago = ref(false)
const editedIndex = ref(-1)
const selectedEmpleado = ref(null)
const pagosEmpleado = ref([])
const resumenPagos = ref({ total_pagos: 0, monto_total: 0 })
const search = ref('')

const editedItem = ref({
  id: null,
  nombre_completo: '',
  documento: '',
  telefono: '',
  email: '',
  direccion: '',
  puesto: '',
  notas: '',
  activo: true,
})

const defaultItem = {
  id: null,
  nombre_completo: '',
  documento: '',
  telefono: '',
  email: '',
  direccion: '',
  puesto: '',
  notas: '',
  activo: true,
}

const nuevoPago = ref({
  fecha_pago: new Date().toISOString().split('T')[0],
  monto: 0,
  metodo_pago_id: null,
  concepto: '',
  observaciones: '',
})

const defaultPago = {
  fecha_pago: new Date().toISOString().split('T')[0],
  monto: 0,
  metodo_pago_id: null,
  concepto: '',
  observaciones: '',
}

// Filtrar empleados por búsqueda
const empleadosFiltrados = computed(() => {
  if (!search.value) return empleados.value
  
  const searchLower = search.value.toLowerCase()
  return empleados.value.filter(empleado => {
    const nombre = (empleado.nombre_completo || '').toLowerCase()
    const documento = (empleado.documento || '').toLowerCase()
    const telefono = (empleado.telefono || '').toLowerCase()
    const puesto = (empleado.puesto || '').toLowerCase()
    
    return nombre.includes(searchLower) ||
           documento.includes(searchLower) ||
           telefono.includes(searchLower) ||
           puesto.includes(searchLower)
  })
})

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre Completo', key: 'nombre_completo' },
  { title: 'Documento', key: 'documento' },
  { title: 'Teléfono', key: 'telefono' },
  { title: 'Puesto', key: 'puesto' },
  { title: 'Estado', key: 'activo' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const headersPagos = [
  { title: 'Fecha', key: 'fecha_pago' },
  { title: 'Concepto', key: 'concepto' },
  { title: 'Monto', key: 'monto' },
  { title: 'Método Pago', key: 'metodo_pago' },
  { title: 'Observaciones', key: 'observaciones' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const conceptosPago = [
  { value: 'sueldo', title: 'Sueldo' },
  { value: 'anticipo', title: 'Anticipo' },
  { value: 'extra', title: 'Extra' },
  { value: 'bono', title: 'Bono' },
  { value: 'aguinaldo', title: 'Aguinaldo' },
  { value: 'otro', title: 'Otro' },
]

const fetchEmpleados = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await getEmpleados()
    if (response.data && Array.isArray(response.data)) {
      empleados.value = response.data
    } else if (Array.isArray(response)) {
      empleados.value = response
    } else {
      empleados.value = []
    }
  } catch (e) {
    const errorMsg = e.message || 'Error al cargar empleados'
    error.value = errorMsg
    toast.error(errorMsg)
  } finally {
    loading.value = false
  }
}

const fetchMetodosPago = async () => {
  try {
    const response = await getMetodosPago()
    if (response.data && Array.isArray(response.data)) {
      metodosPago.value = response.data
    } else if (Array.isArray(response)) {
      metodosPago.value = response
    } else {
      metodosPago.value = []
    }
  } catch (e) {
    console.error('Error al cargar métodos de pago:', e)
  }
}

const editItem = (item) => {
  editedIndex.value = empleados.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialog.value = true
}

const deleteItem = (item) => {
  editedIndex.value = empleados.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialogDelete.value = true
}

const deleteItemConfirm = async () => {
  try {
    await deleteEmpleado(editedItem.value.id)
    toast.success('Empleado eliminado correctamente')
    closeDelete()
    await fetchEmpleados()
  } catch (e) {
    const errorMsg = e.message || 'Error al eliminar empleado'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const verPagos = async (item) => {
  try {
    selectedEmpleado.value = item
    const response = await getPagosEmpleado(item.id)
    
    if (response.data && Array.isArray(response.data)) {
      pagosEmpleado.value = response.data
      resumenPagos.value = response.resumen || { total_pagos: 0, monto_total: 0 }
    } else if (Array.isArray(response)) {
      pagosEmpleado.value = response
      resumenPagos.value = { total_pagos: response.length, monto_total: response.reduce((sum, p) => sum + parseFloat(p.monto || 0), 0) }
    } else {
      pagosEmpleado.value = []
      resumenPagos.value = { total_pagos: 0, monto_total: 0 }
    }
    
    dialogPagos.value = true
  } catch (e) {
    console.error('Error:', e)
    toast.error(e.message || 'Error al cargar pagos del empleado')
  }
}

const abrirDialogNuevoPago = () => {
  nuevoPago.value = Object.assign({}, defaultPago)
  nuevoPago.value.fecha_pago = new Date().toISOString().split('T')[0]
  dialogNuevoPago.value = true
}

const guardarPago = async () => {
  try {
    const dataToSend = {
      ...nuevoPago.value,
      monto: parseFloat(nuevoPago.value.monto) || 0,
    }
    
    await createPagoEmpleado(selectedEmpleado.value.id, dataToSend)
    toast.success('Pago registrado correctamente')
    closeNuevoPago()
    
    // Recargar pagos del empleado
    await verPagos(selectedEmpleado.value)
  } catch (e) {
    const errorMsg = e.message || 'Error al registrar pago'
    toast.error(errorMsg)
  }
}

const eliminarPago = async (pago) => {
  if (!confirm('¿Está seguro de eliminar este pago?')) return
  
  try {
    await deletePagoEmpleado(pago.id)
    toast.success('Pago eliminado correctamente')
    
    // Recargar pagos del empleado
    await verPagos(selectedEmpleado.value)
  } catch (e) {
    const errorMsg = e.message || 'Error al eliminar pago'
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

const closePagos = () => {
  dialogPagos.value = false
  selectedEmpleado.value = null
  pagosEmpleado.value = []
  resumenPagos.value = { total_pagos: 0, monto_total: 0 }
}

const closeNuevoPago = () => {
  dialogNuevoPago.value = false
  nuevoPago.value = Object.assign({}, defaultPago)
}

const save = async () => {
  try {
    const dataToSend = {
      ...editedItem.value,
    }
    
    if (editedIndex.value > -1) {
      const updated = await updateEmpleado(dataToSend.id, dataToSend)
      Object.assign(empleados.value[editedIndex.value], updated)
      toast.success('Empleado actualizado correctamente')
    } else {
      await createEmpleado(dataToSend)
      toast.success('Empleado creado correctamente')
    }
    close()
    await fetchEmpleados()
  } catch (e) {
    const errorMsg = e.message || 'Error al guardar empleado'
    error.value = errorMsg
    toast.error(errorMsg)
  }
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

onMounted(() => {
  fetchEmpleados()
  fetchMetodosPago()
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center flex-wrap ga-4">
          <span class="text-h5">Empleados</span>
          <div class="d-flex ga-2 align-center">
            <VTextField
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Buscar empleados"
              single-line
              hide-details
              density="compact"
              style="min-width: 300px;"
              clearable
            />
            <VBtn color="primary" @click="dialog = true">
              <VIcon start>mdi-plus</VIcon>
              Nuevo Empleado
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
          :items="empleadosFiltrados"
          :loading="loading"
          loading-text="Cargando empleados..."
          no-data-text="No hay empleados registrados"
          class="elevation-1"
        >
          <template #item.activo="{ item }">
            <VChip
              :color="item.activo ? 'success' : 'error'"
              size="small"
            >
              {{ item.activo ? 'Activo' : 'Inactivo' }}
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
                title="Ver pagos"
              >
                <VIcon>ri-money-dollar-circle-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="primary"
                variant="tonal"
                @click="editItem(item)"
                title="Editar empleado"
              >
                <VIcon>ri-pencil-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="deleteItem(item)"
                title="Eliminar empleado"
              >
                <VIcon>ri-delete-bin-6-line</VIcon>
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Dialog para crear/editar empleado -->
    <VDialog v-model="dialog" max-width="700px">
      <VCard>
        <VCardTitle>
          <span class="text-h5">{{ editedIndex === -1 ? 'Nuevo' : 'Editar' }} Empleado</span>
        </VCardTitle>

        <VCardText>
          <VContainer>
            <VRow>
              <VCol cols="12" md="8">
                <VTextField
                  v-model="editedItem.nombre_completo"
                  label="Nombre Completo*"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="editedItem.documento"
                  label="DNI/CUIT*"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.telefono"
                  label="Teléfono"
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
                  v-model="editedItem.puesto"
                  label="Puesto*"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.direccion"
                  label="Dirección"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="editedItem.notas"
                  label="Notas"
                  rows="3"
                />
              </VCol>
              <VCol cols="12">
                <VSwitch
                  v-model="editedItem.activo"
                  label="Empleado Activo"
                  color="primary"
                  hide-details
                />
              </VCol>
            </VRow>
          </VContainer>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="error" variant="text" @click="close">
            Cancelar
          </VBtn>
          <VBtn color="primary" variant="elevated" @click="save">
            Guardar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog para confirmar eliminación -->
    <VDialog v-model="dialogDelete" max-width="500px">
      <VCard>
        <VCardTitle class="text-h5">¿Está seguro de eliminar este empleado?</VCardTitle>
        <VCardText>
          Esta acción no se puede deshacer.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="primary" variant="text" @click="closeDelete">Cancelar</VBtn>
          <VBtn color="error" variant="elevated" @click="deleteItemConfirm">Eliminar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog para ver pagos del empleado -->
    <VDialog v-model="dialogPagos" max-width="1000px">
      <VCard>
        <VCardTitle>
          <div class="d-flex justify-space-between align-center">
            <span class="text-h5">
              Pagos - {{ selectedEmpleado?.nombre_completo }}
            </span>
            <VBtn color="primary" size="small" @click="abrirDialogNuevoPago">
              <VIcon start>mdi-plus</VIcon>
              Registrar Pago
            </VBtn>
          </div>
        </VCardTitle>

        <VCardText>
          <!-- Resumen de pagos -->
          <VRow class="mb-4">
            <VCol cols="12" md="6">
              <VCard color="primary" variant="tonal">
                <VCardText class="d-flex align-center justify-space-between">
                  <div>
                    <div class="text-caption">Total de Pagos</div>
                    <div class="text-h6">{{ resumenPagos.total_pagos }}</div>
                  </div>
                  <VIcon size="40" color="primary">ri-file-list-3-line</VIcon>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="6">
              <VCard color="success" variant="tonal">
                <VCardText class="d-flex align-center justify-space-between">
                  <div>
                    <div class="text-caption">Monto Total</div>
                    <div class="text-h6">{{ formatPrice(resumenPagos.monto_total) }}</div>
                  </div>
                  <VIcon size="40" color="success">ri-money-dollar-circle-line</VIcon>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Tabla de pagos -->
          <VDataTable
            :headers="headersPagos"
            :items="pagosEmpleado"
            no-data-text="No hay pagos registrados"
            class="elevation-1"
          >
            <template #item.fecha_pago="{ item }">
              {{ formatDate(item.fecha_pago) }}
            </template>
            <template #item.monto="{ item }">
              <span class="font-weight-bold text-success">
                {{ formatPrice(item.monto) }}
              </span>
            </template>
            <template #item.metodo_pago="{ item }">
              {{ item.metodo_pago?.nombre || '-' }}
            </template>
            <template #item.observaciones="{ item }">
              {{ item.observaciones || '-' }}
            </template>
            <template #item.actions="{ item }">
              <VBtn
                icon
                size="small"
                color="error"
                variant="text"
                @click="eliminarPago(item)"
                title="Eliminar pago"
              >
                <VIcon>ri-delete-bin-6-line</VIcon>
              </VBtn>
            </template>
          </VDataTable>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="primary" variant="text" @click="closePagos">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog para registrar nuevo pago -->
    <VDialog v-model="dialogNuevoPago" max-width="600px">
      <VCard>
        <VCardTitle>
          <span class="text-h5">Registrar Pago</span>
        </VCardTitle>

        <VCardText>
          <VContainer>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.fecha_pago"
                  label="Fecha de Pago*"
                  type="date"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.monto"
                  label="Monto*"
                  type="number"
                  step="0.01"
                  min="0"
                  prefix="$"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="nuevoPago.concepto"
                  label="Concepto*"
                  :items="conceptosPago"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="nuevoPago.metodo_pago_id"
                  label="Método de Pago"
                  :items="metodosPago"
                  item-title="nombre"
                  item-value="id"
                  clearable
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="nuevoPago.observaciones"
                  label="Observaciones"
                  rows="3"
                />
              </VCol>
            </VRow>
          </VContainer>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="error" variant="text" @click="closeNuevoPago">
            Cancelar
          </VBtn>
          <VBtn color="primary" variant="elevated" @click="guardarPago">
            Guardar Pago
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
