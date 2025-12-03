<script setup>
import { ref, onMounted, computed } from 'vue'
import { 
  getProveedores, 
  createProveedor, 
  updateProveedor, 
  deleteProveedor,
  getResumenCuenta,
  getMovimientosCuenta,
  createPagoProveedor
} from '@/services/proveedores'
import { getMetodosPago } from '@/services/metodosPago'
import { toast } from '@/plugins/toast'

const proveedores = ref([])
const proveedoresConEstado = ref([])
const metodosPago = ref([])
const loading = ref(false)
const loadingEstadoCuenta = ref(false)
const error = ref('')
const dialog = ref(false)
const dialogDelete = ref(false)
const dialogEstadoCuenta = ref(false)
const dialogNuevoPago = ref(false)
const editedIndex = ref(-1)
const selectedProveedor = ref(null)
const resumenCuenta = ref(null)
const movimientosCuenta = ref([])
const resumenMovimientos = ref({})
const search = ref('')

const editedItem = ref({
  id: null,
  nombre: '',
  cuit: '',
  direccion: '',
  telefono: '',
  email: '',
  estado: 'activo',
})

const defaultItem = {
  id: null,
  nombre: '',
  cuit: '',
  direccion: '',
  telefono: '',
  email: '',
  estado: 'activo',
}

const nuevoPago = ref({
  fecha_pago: new Date().toISOString().split('T')[0],
  monto: 0,
  metodo_pago_id: null,
  referencia: '',
  concepto: '',
  observaciones: '',
})

const defaultPago = {
  fecha_pago: new Date().toISOString().split('T')[0],
  monto: 0,
  metodo_pago_id: null,
  referencia: '',
  concepto: '',
  observaciones: '',
}

// Filtrar proveedores por búsqueda
const proveedoresFiltrados = computed(() => {
  if (!search.value) return proveedoresConEstado.value
  
  const searchLower = search.value.toLowerCase()
  return proveedoresConEstado.value.filter(proveedor => {
    const nombre = (proveedor.nombre || '').toLowerCase()
    const cuit = (proveedor.cuit || '').toLowerCase()
    const email = (proveedor.email || '').toLowerCase()
    const telefono = (proveedor.telefono || '').toLowerCase()
    
    return nombre.includes(searchLower) ||
           cuit.includes(searchLower) ||
           email.includes(searchLower) ||
           telefono.includes(searchLower)
  })
})

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'CUIT', key: 'cuit' },
  { title: 'Email', key: 'email' },
  { title: 'Teléfono', key: 'telefono' },
  { title: 'Estado Cuenta', key: 'estado_cuenta' },
  { title: 'Estado', key: 'estado' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const headersMovimientos = [
  { title: 'Fecha', key: 'fecha' },
  { title: 'Tipo', key: 'tipo_texto' },
  { title: 'Descripción', key: 'descripcion' },
  { title: 'Débito', key: 'debito' },
  { title: 'Crédito', key: 'credito' },
  { title: 'Saldo', key: 'saldo_acumulado' },
]

const conceptosPago = [
  { value: 'pago_factura', title: 'Pago de Factura' },
  { value: 'anticipo', title: 'Anticipo' },
  { value: 'cancelacion_deuda', title: 'Cancelación de Deuda' },
  { value: 'devolucion', title: 'Devolución' },
  { value: 'otro', title: 'Otro' },
]

const fetchProveedores = async () => {
  loading.value = true
  error.value = ''
  try {
    const data = await getProveedores()
    proveedores.value = Array.isArray(data) ? data : (data.data ?? [])
    
    // Cargar estado de cuenta para cada proveedor
    await cargarEstadosCuenta()
  } catch (e) {
    const errorMsg = e.message || 'Error al cargar proveedores'
    error.value = errorMsg
    toast.error(errorMsg)
  } finally {
    loading.value = false
  }
}

const cargarEstadosCuenta = async () => {
  try {
    const proveedoresConDatos = await Promise.all(
      proveedores.value.map(async (proveedor) => {
        try {
          const response = await getResumenCuenta(proveedor.id)
          const resumen = response.data || {}
          
          return {
            ...proveedor,
            resumen_cuenta: resumen
          }
        } catch (e) {
          console.error(`Error al cargar estado de ${proveedor.nombre}:`, e)
          return {
            ...proveedor,
            resumen_cuenta: { estado: 'error', saldo: 0 }
          }
        }
      })
    )
    
    proveedoresConEstado.value = proveedoresConDatos
  } catch (e) {
    console.error('Error al cargar estados de cuenta:', e)
    proveedoresConEstado.value = proveedores.value
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
  editedIndex.value = proveedoresConEstado.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialog.value = true
}

const deleteItem = (item) => {
  editedIndex.value = proveedoresConEstado.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialogDelete.value = true
}

const deleteItemConfirm = async () => {
  try {
    await deleteProveedor(editedItem.value.id)
    toast.success('Proveedor eliminado correctamente')
    closeDelete()
    await fetchProveedores()
  } catch (e) {
    const errorMsg = e.message || 'Error al eliminar proveedor'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const verEstadoCuenta = async (item) => {
  try {
    loadingEstadoCuenta.value = true
    selectedProveedor.value = item
    
    // Cargar resumen
    const resumenResponse = await getResumenCuenta(item.id)
    resumenCuenta.value = resumenResponse.data || {}
    
    // Cargar movimientos
    const movimientosResponse = await getMovimientosCuenta(item.id)
    movimientosCuenta.value = movimientosResponse.data || []
    resumenMovimientos.value = movimientosResponse.resumen || {}
    
    dialogEstadoCuenta.value = true
  } catch (e) {
    console.error('Error:', e)
    toast.error(e.message || 'Error al cargar estado de cuenta')
  } finally {
    loadingEstadoCuenta.value = false
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
    
    await createPagoProveedor(selectedProveedor.value.id, dataToSend)
    toast.success('Pago registrado correctamente')
    closeNuevoPago()
    
    // Recargar estado de cuenta
    await verEstadoCuenta(selectedProveedor.value)
    // Recargar lista de proveedores para actualizar badges
    await fetchProveedores()
  } catch (e) {
    const errorMsg = e.message || 'Error al registrar pago'
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

const closeEstadoCuenta = () => {
  dialogEstadoCuenta.value = false
  selectedProveedor.value = null
  resumenCuenta.value = null
  movimientosCuenta.value = []
}

const closeNuevoPago = () => {
  dialogNuevoPago.value = false
  nuevoPago.value = Object.assign({}, defaultPago)
}

const save = async () => {
  try {
    if (editedIndex.value > -1) {
      const updated = await updateProveedor(editedItem.value.id, editedItem.value)
      Object.assign(proveedoresConEstado.value[editedIndex.value], updated)
      toast.success('Proveedor actualizado correctamente')
    } else {
      await createProveedor(editedItem.value)
      toast.success('Proveedor creado correctamente')
    }
    close()
    await fetchProveedores()
  } catch (e) {
    const errorMsg = e.message || 'Error al guardar proveedor'
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

const getEstadoCuentaBadge = (resumen) => {
  if (!resumen) return { color: 'grey', text: 'Sin datos', icon: 'ri-question-line' }
  
  if (resumen.estado === 'deuda') {
    return {
      color: 'error',
      text: `Deuda: ${formatPrice(resumen.saldo_absoluto)}`,
      icon: 'ri-arrow-up-line'
    }
  } else if (resumen.estado === 'saldo_a_favor') {
    return {
      color: 'success',
      text: `A favor: ${formatPrice(resumen.saldo_absoluto)}`,
      icon: 'ri-arrow-down-line'
    }
  } else {
    return {
      color: 'info',
      text: 'Al día',
      icon: 'ri-check-line'
    }
  }
}

onMounted(() => {
  fetchProveedores()
  fetchMetodosPago()
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center flex-wrap ga-4">
          <span class="text-h5">Proveedores</span>
          <div class="d-flex ga-2 align-center">
            <VTextField
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Buscar proveedores"
              single-line
              hide-details
              density="compact"
              style="min-width: 300px;"
              clearable
            />
            <VBtn color="primary" @click="dialog = true">
              <VIcon start>mdi-plus</VIcon>
              Nuevo Proveedor
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
          :items="proveedoresFiltrados"
          :loading="loading"
          loading-text="Cargando proveedores..."
          no-data-text="No hay proveedores registrados"
          class="elevation-1"
        >
          <template #item.estado_cuenta="{ item }">
            <VChip
              v-if="item.resumen_cuenta"
              :color="getEstadoCuentaBadge(item.resumen_cuenta).color"
              size="small"
              :prepend-icon="getEstadoCuentaBadge(item.resumen_cuenta).icon"
            >
              {{ getEstadoCuentaBadge(item.resumen_cuenta).text }}
            </VChip>
            <VChip v-else color="grey" size="small">
              Sin datos
            </VChip>
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
                color="info"
                variant="tonal"
                @click="verEstadoCuenta(item)"
                title="Ver estado de cuenta"
              >
                <VIcon>ri-file-list-3-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="primary"
                variant="tonal"
                @click="editItem(item)"
                title="Editar proveedor"
              >
                <VIcon>ri-pencil-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="deleteItem(item)"
                title="Eliminar proveedor"
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
          <span class="text-h5">{{ editedIndex === -1 ? 'Nuevo' : 'Editar' }} Proveedor</span>
        </VCardTitle>

        <VCardText>
          <VContainer>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.nombre"
                  label="Nombre*"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.cuit"
                  label="CUIT*"
                  required
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
                  v-model="editedItem.telefono"
                  label="Teléfono"
                />
              </VCol>
              <VCol cols="12" md="8">
                <VTextField
                  v-model="editedItem.direccion"
                  label="Dirección"
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
        <VCardTitle class="text-h5">
          ¿Está seguro de eliminar este proveedor?
        </VCardTitle>
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

    <!-- Dialog de Estado de Cuenta -->
    <VDialog v-model="dialogEstadoCuenta" max-width="1200px" scrollable>
      <VCard>
        <VCardTitle>
          <div class="d-flex justify-space-between align-center">
            <span class="text-h5">
              Estado de Cuenta - {{ selectedProveedor?.nombre }}
            </span>
            <VBtn color="primary" size="small" @click="abrirDialogNuevoPago">
              <VIcon start>mdi-plus</VIcon>
              Registrar Pago
            </VBtn>
          </div>
        </VCardTitle>

        <VCardText v-if="loadingEstadoCuenta">
          <div class="text-center py-8">
            <VProgressCircular indeterminate color="primary" />
            <p class="mt-4">Cargando estado de cuenta...</p>
          </div>
        </VCardText>

        <VCardText v-else>
          <!-- Resumen de cuenta -->
          <VRow class="mb-4">
            <VCol cols="12" md="4">
              <VCard color="error" variant="tonal">
                <VCardText class="d-flex align-center justify-space-between">
                  <div>
                    <div class="text-caption">Total Compras</div>
                    <div class="text-h6">{{ formatPrice(resumenCuenta?.total_compras || 0) }}</div>
                  </div>
                  <VIcon size="40" color="error">ri-shopping-cart-line</VIcon>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard color="success" variant="tonal">
                <VCardText class="d-flex align-center justify-space-between">
                  <div>
                    <div class="text-caption">Total Pagos</div>
                    <div class="text-h6">{{ formatPrice(resumenCuenta?.total_pagos || 0) }}</div>
                  </div>
                  <VIcon size="40" color="success">ri-money-dollar-circle-line</VIcon>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard 
                :color="resumenCuenta?.estado === 'deuda' ? 'error' : (resumenCuenta?.estado === 'saldo_a_favor' ? 'success' : 'info')" 
                variant="tonal"
              >
                <VCardText class="d-flex align-center justify-space-between">
                  <div>
                    <div class="text-caption">
                      {{ resumenCuenta?.estado === 'deuda' ? 'Deuda' : (resumenCuenta?.estado === 'saldo_a_favor' ? 'Saldo a Favor' : 'Saldo') }}
                    </div>
                    <div class="text-h6">{{ formatPrice(resumenCuenta?.saldo_absoluto || 0) }}</div>
                  </div>
                  <VIcon 
                    size="40" 
                    :color="resumenCuenta?.estado === 'deuda' ? 'error' : (resumenCuenta?.estado === 'saldo_a_favor' ? 'success' : 'info')"
                  >
                    {{ resumenCuenta?.estado === 'deuda' ? 'ri-arrow-up-circle-line' : (resumenCuenta?.estado === 'saldo_a_favor' ? 'ri-arrow-down-circle-line' : 'ri-checkbox-circle-line') }}
                  </VIcon>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Tabla de movimientos -->
          <VDataTable
            :headers="headersMovimientos"
            :items="movimientosCuenta"
            no-data-text="No hay movimientos registrados"
            class="elevation-1"
          >
            <template #item.fecha="{ item }">
              {{ formatDate(item.fecha) }}
            </template>
            <template #item.tipo_texto="{ item }">
              <VChip 
                :color="item.tipo === 'COMPRA' ? 'error' : 'success'" 
                size="small"
              >
                {{ item.tipo_texto }}
              </VChip>
            </template>
            <template #item.debito="{ item }">
              <span v-if="item.debito > 0" class="font-weight-bold text-error">
                {{ formatPrice(item.debito) }}
              </span>
              <span v-else>-</span>
            </template>
            <template #item.credito="{ item }">
              <span v-if="item.credito > 0" class="font-weight-bold text-success">
                {{ formatPrice(item.credito) }}
              </span>
              <span v-else>-</span>
            </template>
            <template #item.saldo_acumulado="{ item }">
              <span 
                class="font-weight-bold"
                :class="item.saldo_acumulado > 0 ? 'text-error' : (item.saldo_acumulado < 0 ? 'text-success' : '')"
              >
                {{ formatPrice(item.saldo_acumulado) }}
              </span>
            </template>
          </VDataTable>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="primary" variant="text" @click="closeEstadoCuenta">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog para registrar nuevo pago -->
    <VDialog v-model="dialogNuevoPago" max-width="600px">
      <VCard>
        <VCardTitle>
          <span class="text-h5">Registrar Pago a Proveedor</span>
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
                <VTextField
                  v-model="nuevoPago.referencia"
                  label="Referencia"
                  placeholder="Ej: Factura #123, Orden de Compra #456"
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

