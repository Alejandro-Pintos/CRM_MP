<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { getProveedores, getResumenCuenta, createPagoProveedor } from '@/services/proveedores'
import { getMetodosPago } from '@/services/metodosPago'
import { toast } from '@/plugins/toast'

const proveedores = ref([])
const proveedorSeleccionado = ref(null)
const metodosPago = ref([])
const loading = ref(false)
const dialogPago = ref(false)

const nuevoPago = ref({
  fecha_pago: new Date().toISOString().split('T')[0],
  monto: '',
  metodo_pago_id: null,
  referencia: '',
  concepto: '',
  observaciones: '',
  // Datos del cheque
  banco_cheque: '',
  numero_cheque: '',
  fecha_emision_cheque: new Date().toISOString().split('T')[0],
  fecha_vencimiento_cheque: '',
  observaciones_cheque: '',
})

const proveedoresConSaldo = computed(() => {
  return proveedores.value.filter(p => {
    return p.resumen_cuenta?.saldo && parseFloat(p.resumen_cuenta.saldo) > 0
  })
})

// Computed para saber si el pago es con cheque
// Busca el método "Cheque" dinámicamente (no hardcodea el ID)
const esPagoCheque = computed(() => {
  const metodoPagoId = Number(nuevoPago.value.metodo_pago_id)
  if (!metodoPagoId) return false
  
  const metodoSeleccionado = metodosPago.value.find(m => m.id === metodoPagoId)
  const esCheque = metodoSeleccionado?.nombre === 'Cheque'
  
  // 🔍 DEBUG: Log cada vez que cambia
  console.log('🔄 esPagoCheque computed:', {
    metodoPagoId,
    metodoNombre: metodoSeleccionado?.nombre,
    esCheque
  })
  
  return esCheque
})

const headers = [
  { title: 'Proveedor', key: 'nombre', sortable: true },
  { title: 'CUIT', key: 'cuit' },
  { title: 'Saldo Pendiente', key: 'saldo_pendiente', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const conceptosPago = [
  { value: 'pago_factura', title: 'Pago de Factura' },
  { value: 'anticipo', title: 'Anticipo' },
  { value: 'cancelacion_deuda', title: 'Cancelación de Deuda' },
  { value: 'devolucion', title: 'Devolución' },
  { value: 'otro', title: 'Otro' },
]

onMounted(async () => {
  await cargarDatos()
})

const cargarDatos = async () => {
  loading.value = true
  try {
    const [dataProveedores, dataMetodos] = await Promise.all([
      getProveedores(),
      getMetodosPago()
    ])
    
    proveedores.value = Array.isArray(dataProveedores) ? dataProveedores : (dataProveedores.data || [])
    
    // Filtrar: Los proveedores NO usan Cuenta Corriente (solo clientes)
    const metodos = Array.isArray(dataMetodos) ? dataMetodos : (dataMetodos.data || [])
    metodosPago.value = metodos.filter(m => m.nombre !== 'Cuenta Corriente')
    
    // 🔍 DEBUG: Verificar que se cargaron los métodos
    console.log('✅ Métodos de pago cargados:', metodosPago.value)
    console.log('📋 Total métodos disponibles:', metodosPago.value.length)
    
    // Cargar saldos
    await cargarSaldos()
  } catch (error) {
    console.error('❌ Error al cargar datos:', error)
    toast.error('Error al cargar datos: ' + error.message)
  } finally {
    loading.value = false
  }
}

const cargarSaldos = async () => {
  proveedores.value = await Promise.all(
    proveedores.value.map(async (proveedor) => {
      try {
        const response = await getResumenCuenta(proveedor.id)
        console.log(`📊 Proveedor ${proveedor.nombre}:`, response.data)
        return { ...proveedor, resumen_cuenta: response.data }
      } catch (e) {
        console.error(`❌ Error cargando saldo de ${proveedor.nombre}:`, e)
        return { ...proveedor, resumen_cuenta: { saldo: 0 } }
      }
    })
  )
  
  console.log('📋 Total proveedores cargados:', proveedores.value.length)
  console.log('💰 Proveedores con saldo > 0:', proveedoresConSaldo.value.length)
  
  if (proveedoresConSaldo.value.length === 0) {
    console.warn('⚠️ NO HAY PROVEEDORES CON SALDO PENDIENTE (deuda)')
    console.log('Tip: Saldo > 0 significa que le debemos al proveedor')
  }
}

const abrirDialogPago = (proveedor) => {
  proveedorSeleccionado.value = proveedor
  resetFormulario()
  dialogPago.value = true
}

// 🔥 WATCH CRÍTICO: Detecta cambio de método de pago
watch(
  () => nuevoPago.value.metodo_pago_id,
  (nuevoMetodoId, viejoMetodoId) => {
    console.log('🔄 WATCH: Método de pago cambió', {
      anterior: viejoMetodoId,
      nuevo: nuevoMetodoId,
      esCheque: esPagoCheque.value
    })
    
    // Si cambió de Cheque a otro método, limpiar campos
    if (viejoMetodoId && !esPagoCheque.value) {
      console.log('🧹 Limpiando campos de cheque (ya no es cheque)')
      nuevoPago.value.banco_cheque = ''
      nuevoPago.value.numero_cheque = ''
      nuevoPago.value.fecha_emision_cheque = new Date().toISOString().split('T')[0]
      nuevoPago.value.fecha_vencimiento_cheque = ''
      nuevoPago.value.observaciones_cheque = ''
    }
    
    // Si cambió a Cheque, setear fecha por defecto
    if (esPagoCheque.value && !nuevoPago.value.fecha_emision_cheque) {
      nuevoPago.value.fecha_emision_cheque = new Date().toISOString().split('T')[0]
      console.log('📅 Fecha de emisión de cheque seteada por defecto')
    }
  }
)

const guardarPago = async () => {
  if (!validarPago()) return
  
  loading.value = true
  try {
    const payload = {
      fecha_pago: nuevoPago.value.fecha_pago,
      monto: parseFloat(nuevoPago.value.monto),
      metodo_pago_id: Number(nuevoPago.value.metodo_pago_id),
      referencia: nuevoPago.value.referencia || null,
      concepto: nuevoPago.value.concepto,
      observaciones: nuevoPago.value.observaciones || null,
    }

    // Si el método es cheque, agregar los datos del cheque
    if (esPagoCheque.value) {
      payload.banco_cheque = nuevoPago.value.banco_cheque.trim()
      payload.numero_cheque = nuevoPago.value.numero_cheque.trim()
      payload.fecha_emision_cheque = nuevoPago.value.fecha_emision_cheque
      payload.fecha_vencimiento_cheque = nuevoPago.value.fecha_vencimiento_cheque || null
      payload.observaciones_cheque = nuevoPago.value.observaciones_cheque?.trim() || null
      
      console.log('💰 Payload con datos de cheque:', payload)
    } else {
      console.log('💰 Payload sin cheque:', payload)
    }

    const response = await createPagoProveedor(proveedorSeleccionado.value.id, payload)
    console.log('✅ Pago registrado exitosamente:', response)

    toast.success('Pago registrado correctamente')
    dialogPago.value = false
    resetFormulario()
    await cargarSaldos()
  } catch (error) {
    console.error('❌ Error al guardar pago:', error)
    
    // Mostrar errores de validación específicos
    if (error.response?.data?.errors) {
      const errores = Object.values(error.response.data.errors).flat()
      toast.error(errores.join(' | '))
    } else {
      toast.error(error.message || 'Error al registrar pago')
    }
  } finally {
    loading.value = false
  }
}

const validarPago = () => {
  // Validaciones básicas
  if (!nuevoPago.value.monto || nuevoPago.value.monto <= 0) {
    toast.error('El monto debe ser mayor a 0')
    return false
  }
  if (!nuevoPago.value.metodo_pago_id) {
    toast.error('Seleccione un método de pago')
    return false
  }
  if (!nuevoPago.value.concepto) {
    toast.error('Seleccione un concepto')
    return false
  }
  
  // Validaciones específicas para Cheque
  if (esPagoCheque.value) {
    console.log('⚠️ Validando campos de cheque:', nuevoPago.value)
    
    if (!nuevoPago.value.banco_cheque?.trim()) {
      toast.error('El banco del cheque es obligatorio')
      return false
    }
    if (!nuevoPago.value.numero_cheque?.trim()) {
      toast.error('El número del cheque es obligatorio')
      return false
    }
    if (!nuevoPago.value.fecha_emision_cheque) {
      toast.error('La fecha de emisión del cheque es obligatoria')
      return false
    }
    
    console.log('✅ Validación de cheque exitosa')
  }
  
  return true
}

const resetFormulario = () => {
  nuevoPago.value = {
    fecha_pago: new Date().toISOString().split('T')[0],
    monto: '',
    metodo_pago_id: null,
    referencia: '',
    concepto: '',
    observaciones: '',
    banco_cheque: '',
    numero_cheque: '',
    fecha_emision_cheque: new Date().toISOString().split('T')[0],
    fecha_vencimiento_cheque: '',
    observaciones_cheque: '',
  }
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value || 0)
}
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between">
      <div class="d-flex align-center gap-3">
        <VIcon icon="mdi-cash-multiple" size="32" color="primary" />
        <div>
          <h3 class="text-h5">Gestión de Pagos a Proveedores</h3>
          <p class="text-caption text-medium-emphasis">Registre los pagos realizados a proveedores con saldo pendiente</p>
        </div>
      </div>
    </VCardTitle>

    <VCardText>
      <!-- Alert informativo si no hay datos -->
      <VAlert v-if="!loading && proveedoresConSaldo.length === 0" color="info" variant="tonal" class="mb-4">
        <div class="d-flex align-center">
          <VIcon icon="mdi-information" size="32" class="mr-3" />
          <div>
            <div class="text-h6 font-weight-bold">No hay proveedores con saldo pendiente</div>
            <div class="text-caption mt-1">
              Solo se muestran proveedores con deuda activa (saldo > $0).
              Si realizó compras recientemente, verifique que estén registradas correctamente.
            </div>
          </div>
        </div>
      </VAlert>

      <VDataTable
        :headers="headers"
        :items="proveedoresConSaldo"
        :loading="loading"
        loading-text="Cargando proveedores..."
        no-data-text="No hay proveedores con saldo pendiente"
        class="elevation-1"
        :items-per-page="10"
      >
        <template #item.nombre="{ item }">
          <div class="d-flex align-center py-2">
            <VAvatar color="primary" variant="tonal" size="40" class="mr-3">
              <span class="text-sm font-weight-semibold">{{ item.nombre.substring(0, 2).toUpperCase() }}</span>
            </VAvatar>
            <div>
              <div class="font-weight-semibold">{{ item.nombre }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.email || '-' }}</div>
            </div>
          </div>
        </template>

        <template #item.saldo_pendiente="{ item }">
          <VChip color="error" variant="flat" size="large">
            <VIcon icon="mdi-alert-circle" start />
            {{ formatPrice(item.resumen_cuenta?.saldo || 0) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="mdi-cash-plus"
            @click="abrirDialogPago(item)"
          >
            Registrar Pago
          </VBtn>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>

  <!-- Dialog de registro de pago -->
  <VDialog v-model="dialogPago" max-width="900px" persistent scrollable>
    <VCard>
      <VCardTitle class="bg-primary text-white">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon icon="mdi-cash-register" size="28" class="mr-2" />
            <span class="text-h5">Registrar Pago a Proveedor</span>
          </div>
          <VBtn
            icon="mdi-close"
            variant="text"
            size="small"
            color="white"
            @click="dialogPago = false"
          />
        </div>
      </VCardTitle>

      <VCardText class="pt-6">
        <!-- Información del proveedor -->
        <VAlert color="info" variant="tonal" prominent class="mb-6">
          <VRow>
            <VCol cols="12" md="6">
              <div class="text-caption">Proveedor</div>
              <div class="text-h6 font-weight-bold">{{ proveedorSeleccionado?.nombre }}</div>
              <div class="text-caption">CUIT: {{ proveedorSeleccionado?.cuit }}</div>
            </VCol>
            <VCol cols="12" md="6" class="text-right">
              <div class="text-caption">Saldo Pendiente</div>
              <div class="text-h5 font-weight-bold text-error">
                {{ formatPrice(proveedorSeleccionado?.resumen_cuenta?.saldo || 0) }}
              </div>
            </VCol>
          </VRow>
        </VAlert>

        <!-- Formulario -->
        <VForm>
          <VRow>
            <!-- Sección: Datos del Pago -->
            <VCol cols="12">
              <div class="text-h6 mb-3">
                <VIcon icon="mdi-information" class="mr-2" />
                Datos del Pago
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="nuevoPago.fecha_pago"
                label="Fecha de Pago *"
                type="date"
                variant="outlined"
                prepend-inner-icon="mdi-calendar"
                required
                :rules="[v => !!v || 'La fecha es requerida']"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="nuevoPago.monto"
                label="Monto *"
                type="number"
                step="0.01"
                prefix="$"
                variant="outlined"
                prepend-inner-icon="mdi-currency-usd"
                required
                :rules="[
                  v => !!v || 'El monto es requerido',
                  v => v > 0 || 'El monto debe ser mayor a 0'
                ]"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect
                v-model="nuevoPago.concepto"
                :items="conceptosPago"
                label="Concepto *"
                variant="outlined"
                prepend-inner-icon="mdi-text"
                required
                :rules="[v => !!v || 'El concepto es requerido']"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect
                v-model.number="nuevoPago.metodo_pago_id"
                :items="metodosPago"
                item-title="nombre"
                item-value="id"
                label="Método de Pago *"
                variant="outlined"
                prepend-inner-icon="mdi-credit-card"
                required
                :rules="[v => !!v || 'El método de pago es requerido']"
                @update:model-value="(val) => console.log('🔄 Método de pago cambió a:', val)"
              />
            </VCol>

            <!-- 🔍 DEBUG VISUAL: Mostrar estado actual -->
            <VCol cols="12" v-if="nuevoPago.metodo_pago_id">
              <VAlert
                :color="esPagoCheque ? 'warning' : 'info'"
                variant="tonal"
                density="compact"
              >
                <strong>Debug:</strong> Método seleccionado ID: {{ nuevoPago.metodo_pago_id }} |
                Es Cheque: {{ esPagoCheque ? 'SÍ ✅' : 'NO ❌' }}
              </VAlert>
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="nuevoPago.referencia"
                label="Referencia (Nº Factura/Comprobante)"
                variant="outlined"
                prepend-inner-icon="mdi-file-document"
                placeholder="Ej: Factura #123, Orden de Compra #456"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="nuevoPago.observaciones"
                label="Observaciones del Pago"
                variant="outlined"
                rows="2"
                prepend-inner-icon="mdi-note-text"
              />
            </VCol>

            <!-- Sección: Datos del Cheque (SOLO VISIBLE SI ES CHEQUE) -->
            <template v-if="esPagoCheque">
              <VCol cols="12">
                <VDivider class="my-2" />
              </VCol>

              <VCol cols="12">
                <VAlert color="warning" variant="tonal" prominent>
                  <div class="d-flex align-center">
                    <VIcon icon="mdi-checkbook" size="32" class="mr-3" />
                    <div>
                      <div class="text-h6 font-weight-bold">Datos del Cheque Emitido</div>
                      <div class="text-caption">Complete la información del cheque que se emitirá al proveedor</div>
                    </div>
                  </div>
                </VAlert>
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.banco_cheque"
                  label="Banco *"
                  placeholder="Ej: Banco Nación"
                  variant="outlined"
                  prepend-inner-icon="mdi-bank"
                  required
                  :rules="[v => !!v || 'El banco es requerido para cheques']"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.numero_cheque"
                  label="Número de Cheque *"
                  placeholder="Ej: 12345678"
                  variant="outlined"
                  prepend-inner-icon="mdi-numeric"
                  required
                  :rules="[v => !!v || 'El número de cheque es requerido']"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.fecha_emision_cheque"
                  label="Fecha de Emisión del Cheque *"
                  type="date"
                  variant="outlined"
                  prepend-inner-icon="mdi-calendar"
                  required
                  :rules="[v => !!v || 'La fecha de emisión es requerida']"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.fecha_vencimiento_cheque"
                  label="Fecha de Vencimiento del Cheque"
                  type="date"
                  variant="outlined"
                  prepend-inner-icon="mdi-calendar-clock"
                  hint="Opcional - Para cheques diferidos"
                  persistent-hint
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="nuevoPago.observaciones_cheque"
                  label="Observaciones del Cheque"
                  placeholder="Observaciones adicionales sobre el cheque..."
                  rows="2"
                  variant="outlined"
                  prepend-inner-icon="mdi-note"
                />
              </VCol>
            </template>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          color="grey"
          variant="outlined"
          @click="dialogPago = false; resetFormulario()"
          :disabled="loading"
        >
          <VIcon icon="mdi-close" start />
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="guardarPago"
          :loading="loading"
        >
          <VIcon icon="mdi-check" start />
          Guardar Pago
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.v-card-title {
  padding: 1.5rem;
}
</style>
