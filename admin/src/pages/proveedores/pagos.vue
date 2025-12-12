<script setup>
import { ref, onMounted, computed } from 'vue'
import { getProveedores, getResumenCuenta, createPagoProveedor } from '@/services/proveedores'
import { getMetodosPago } from '@/services/metodosPago'
import { createChequeEmitido } from '@/services/chequesEmitidos'
import { toast } from '@/plugins/toast'

const proveedores = ref([])
const proveedorSeleccionado = ref(null)
const resumenCuenta = ref(null)
const metodosPago = ref([])
const loading = ref(false)
const dialogPago = ref(false)

const nuevoPago = ref({
  fecha_pago: new Date().toISOString().split('T')[0],
  monto: 0,
  metodo_pago_id: null,
  referencia: '',
  concepto: '',
  observaciones: '',
  incluye_cheque: false,
  cheque: {
    banco: '',
    numero: '',
    fecha_emision: new Date().toISOString().split('T')[0],
    fecha_vencimiento: null,
  }
})

const proveedoresConSaldo = computed(() => {
  return proveedores.value.filter(p => {
    return p.resumen_cuenta?.saldo && parseFloat(p.resumen_cuenta.saldo) > 0
  })
})

const headers = [
  { title: 'Proveedor', key: 'nombre' },
  { title: 'CUIT', key: 'cuit' },
  { title: 'Saldo Pendiente', key: 'saldo_pendiente' },
  { title: 'Acciones', key: 'actions', sortable: false },
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
    
    // Cargar saldos
    await cargarSaldos()
  } catch (error) {
    toast.error('Error al cargar datos')
  } finally {
    loading.value = false
  }
}

const cargarSaldos = async () => {
  proveedores.value = await Promise.all(
    proveedores.value.map(async (proveedor) => {
      try {
        const response = await getResumenCuenta(proveedor.id)
        return { ...proveedor, resumen_cuenta: response.data }
      } catch (e) {
        return { ...proveedor, resumen_cuenta: { saldo: 0 } }
      }
    })
  )
}

const abrirDialogPago = (proveedor) => {
  proveedorSeleccionado.value = proveedor
  dialogPago.value = true
}

const guardarPago = async () => {
  if (!validarPago()) return
  
  loading.value = true
  try {
    const payload = {
      fecha_pago: nuevoPago.value.fecha_pago,
      monto: parseFloat(nuevoPago.value.monto),
      metodo_pago_id: nuevoPago.value.metodo_pago_id,
      referencia: nuevoPago.value.referencia,
      concepto: nuevoPago.value.concepto,
      observaciones: nuevoPago.value.observaciones,
    }

    // Primero crear el pago
    const responsePago = await createPagoProveedor(proveedorSeleccionado.value.id, payload)
    const pagoCreado = responsePago.data || responsePago

    // Si incluye cheque, crearlo vinculado al pago
    if (nuevoPago.value.incluye_cheque) {
      const chequeData = {
        ...nuevoPago.value.cheque,
        monto: parseFloat(nuevoPago.value.monto),
        pago_proveedor_id: pagoCreado.id,
      }
      await createChequeEmitido(proveedorSeleccionado.value.id, chequeData)
    }

    toast.success('Pago registrado correctamente')
    dialogPago.value = false
    resetFormulario()
    await cargarSaldos()
  } catch (error) {
    toast.error(error.message || 'Error al registrar pago')
  } finally {
    loading.value = false
  }
}

const validarPago = () => {
  if (!nuevoPago.value.monto || nuevoPago.value.monto <= 0) {
    toast.error('El monto debe ser mayor a 0')
    return false
  }
  if (!nuevoPago.value.metodo_pago_id) {
    toast.error('Seleccione un método de pago')
    return false
  }
  if (nuevoPago.value.incluye_cheque) {
    if (!nuevoPago.value.cheque.banco || !nuevoPago.value.cheque.numero) {
      toast.error('Complete los datos del cheque')
      return false
    }
  }
  return true
}

const resetFormulario = () => {
  nuevoPago.value = {
    fecha_pago: new Date().toISOString().split('T')[0],
    monto: 0,
    metodo_pago_id: null,
    referencia: '',
    concepto: '',
    observaciones: '',
    incluye_cheque: false,
    cheque: {
      banco: '',
      numero: '',
      fecha_emision: new Date().toISOString().split('T')[0],
      fecha_vencimiento: null,
    }
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
  <VRow>
    <VCol cols="12">
      <h3 class="text-h5 mb-4">Proveedores con Saldo Pendiente</h3>
      
      <VDataTable
        :headers="headers"
        :items="proveedoresConSaldo"
        :loading="loading"
        loading-text="Cargando..."
        no-data-text="No hay proveedores con saldo pendiente"
        class="elevation-1"
      >
        <template #item.saldo_pendiente="{ item }">
          <VChip color="error" variant="tonal">
            {{ formatPrice(item.resumen_cuenta?.saldo || 0) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            color="primary"
            size="small"
            variant="tonal"
            @click="abrirDialogPago(item)"
          >
            <VIcon icon="mdi-cash-plus" start />
            Registrar Pago
          </VBtn>
        </template>
      </VDataTable>
    </VCol>
  </VRow>

  <!-- Dialog de nuevo pago -->
  <VDialog v-model="dialogPago" max-width="700px" persistent>
    <VCard>
      <VCardTitle>
        <span class="text-h5">Registrar Pago a Proveedor</span>
      </VCardTitle>

      <VCardText>
        <VContainer>
          <VRow>
            <VCol cols="12">
              <VAlert type="info" variant="tonal">
                Proveedor: <strong>{{ proveedorSeleccionado?.nombre }}</strong><br>
                Saldo pendiente: <strong>{{ formatPrice(proveedorSeleccionado?.resumen_cuenta?.saldo || 0) }}</strong>
              </VAlert>
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="nuevoPago.fecha_pago"
                label="Fecha de Pago"
                type="date"
                required
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model.number="nuevoPago.monto"
                label="Monto"
                type="number"
                step="0.01"
                prefix="$"
                required
              />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect
                v-model="nuevoPago.metodo_pago_id"
                :items="metodosPago"
                item-title="nombre"
                item-value="id"
                label="Método de Pago"
                required
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="nuevoPago.referencia"
                label="Referencia (Nº Factura/Comprobante)"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="nuevoPago.concepto"
                :items="conceptosPago"
                label="Concepto"
                required
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="nuevoPago.incluye_cheque"
                label="Pago con Cheque Emitido"
                color="primary"
                hide-details
              />
            </VCol>

            <!-- Sección de cheque (condicional) -->
            <template v-if="nuevoPago.incluye_cheque">
              <VCol cols="12">
                <VDivider class="my-2" />
                <h4 class="text-subtitle-1 mb-2">Datos del Cheque Emitido</h4>
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.cheque.banco"
                  label="Banco"
                  required
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.cheque.numero"
                  label="Número de Cheque"
                  required
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.cheque.fecha_emision"
                  label="Fecha de Emisión"
                  type="date"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="nuevoPago.cheque.fecha_vencimiento"
                  label="Fecha de Vencimiento"
                  type="date"
                />
              </VCol>
            </template>

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
        <VBtn color="grey" variant="text" @click="dialogPago = false; resetFormulario()">
          Cancelar
        </VBtn>
        <VBtn color="primary" @click="guardarPago" :loading="loading">
          Guardar Pago
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
