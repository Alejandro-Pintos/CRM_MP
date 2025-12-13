<script setup>
import { ref, onMounted, computed } from 'vue'
import { getProveedores } from '@/services/proveedores'
import { createCompraProveedor } from '@/services/compras'
import { toast } from '@/plugins/toast'

const proveedores = ref([])
const proveedorSeleccionado = ref(null)
const loading = ref(false)
const dialogCompra = ref(false)

const nuevaCompra = ref({
  fecha_compra: new Date().toISOString().split('T')[0],
  estado: 'pendiente',
  observaciones: '',
  detalles: [
    {
      descripcion: '',
      cantidad: 1,
      precio_unitario: 0,
      descuento_item: 0,
      impuesto_porcentaje: 0,
      subtotal: 0
    }
  ]
})

const headers = [
  { title: 'Proveedor', key: 'nombre', sortable: true },
  { title: 'CUIT', key: 'cuit' },
  { title: 'Email', key: 'email' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const estadosCompra = [
  { value: 'pendiente', title: 'Pendiente de Pago' },
  { value: 'pagado', title: 'Pagado' },
]

// Computed para calcular totales
const subtotalCompra = computed(() => {
  return nuevaCompra.value.detalles.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0)
})

const totalImpuestos = computed(() => {
  return nuevaCompra.value.detalles.reduce((sum, item) => {
    const subtotal = parseFloat(item.subtotal) || 0
    const impuesto = (subtotal * (parseFloat(item.impuesto_porcentaje) || 0)) / 100
    return sum + impuesto
  }, 0)
})

const montoTotal = computed(() => {
  return subtotalCompra.value + totalImpuestos.value
})

onMounted(async () => {
  await cargarProveedores()
})

const cargarProveedores = async () => {
  loading.value = true
  try {
    const data = await getProveedores()
    proveedores.value = Array.isArray(data) ? data : (data.data || [])
    console.log('✅ Proveedores cargados:', proveedores.value.length)
  } catch (error) {
    console.error('❌ Error al cargar proveedores:', error)
    toast.error('Error al cargar proveedores')
  } finally {
    loading.value = false
  }
}

const abrirDialogCompra = (proveedor) => {
  proveedorSeleccionado.value = proveedor
  resetFormulario()
  dialogCompra.value = true
}

const agregarItem = () => {
  // Validar que el último item tenga datos antes de agregar uno nuevo
  const ultimoItem = nuevaCompra.value.detalles[nuevaCompra.value.detalles.length - 1]
  
  if (!ultimoItem.descripcion?.trim()) {
    toast.error('Complete la descripción del item actual antes de agregar uno nuevo')
    return
  }
  
  if (parseFloat(ultimoItem.cantidad) <= 0) {
    toast.error('Ingrese una cantidad válida en el item actual antes de agregar uno nuevo')
    return
  }
  
  if (parseFloat(ultimoItem.precio_unitario) <= 0) {
    toast.error('Ingrese un precio válido en el item actual antes de agregar uno nuevo')
    return
  }
  
  // Si todo está bien, agregar nuevo item
  nuevaCompra.value.detalles.push({
    descripcion: '',
    cantidad: 1,
    precio_unitario: 0,
    descuento_item: 0,
    impuesto_porcentaje: 0,
    subtotal: 0
  })
}

const eliminarItem = (index) => {
  if (nuevaCompra.value.detalles.length > 1) {
    nuevaCompra.value.detalles.splice(index, 1)
  } else {
    toast.error('Debe haber al menos un item')
  }
}

const calcularSubtotalItem = (item) => {
  const cantidad = parseFloat(item.cantidad) || 0
  const precioUnitario = parseFloat(item.precio_unitario) || 0
  const descuento = parseFloat(item.descuento_item) || 0
  
  item.subtotal = (cantidad * precioUnitario) - descuento
}

const guardarCompra = async () => {
  if (!validarCompra()) return
  
  loading.value = true
  try {
    // Calcular subtotales antes de enviar
    nuevaCompra.value.detalles.forEach(item => {
      calcularSubtotalItem(item)
    })
    
    const payload = {
      fecha_compra: nuevaCompra.value.fecha_compra,
      estado: nuevaCompra.value.estado,
      observaciones: nuevaCompra.value.observaciones || null,
      subtotal: subtotalCompra.value,
      descuento_global: 0,
      impuestos_total: totalImpuestos.value,
      monto_total: montoTotal.value,
      detalles: nuevaCompra.value.detalles.map(item => ({
        descripcion: item.descripcion,
        cantidad: parseFloat(item.cantidad),
        precio_unitario: parseFloat(item.precio_unitario),
        descuento_item: parseFloat(item.descuento_item) || 0,
        impuesto_porcentaje: parseFloat(item.impuesto_porcentaje) || 0,
        impuesto_monto: (parseFloat(item.subtotal) * (parseFloat(item.impuesto_porcentaje) || 0)) / 100,
        subtotal: parseFloat(item.subtotal)
      }))
    }

    console.log('💰 Payload de compra:', payload)
    
    await createCompraProveedor(proveedorSeleccionado.value.id, payload)

    toast.success('Compra registrada correctamente')
    dialogCompra.value = false
    resetFormulario()
  } catch (error) {
    console.error('❌ Error al guardar compra:', error)
    
    if (error.response?.data?.errors) {
      const errores = Object.values(error.response.data.errors).flat()
      toast.error(errores.join(' | '))
    } else {
      toast.error(error.message || 'Error al registrar compra')
    }
  } finally {
    loading.value = false
  }
}

const validarCompra = () => {
  // Validar fecha
  if (!nuevaCompra.value.fecha_compra) {
    toast.error('La fecha de compra es obligatoria')
    return false
  }
  
  // Validar que haya items
  if (nuevaCompra.value.detalles.length === 0) {
    toast.error('Debe agregar al menos un item a la compra')
    return false
  }
  
  // Validar cada item en detalle
  for (let i = 0; i < nuevaCompra.value.detalles.length; i++) {
    const item = nuevaCompra.value.detalles[i]
    const itemNum = i + 1
    
    // Validar descripción (obligatorio)
    if (!item.descripcion?.trim()) {
      toast.error(`Item #${itemNum}: Debe especificar qué se compró (descripción obligatoria)`)
      return false
    }
    
    // Validar cantidad
    if (!item.cantidad || parseFloat(item.cantidad) <= 0) {
      toast.error(`Item #${itemNum}: La cantidad debe ser mayor a 0`)
      return false
    }
    
    // Validar precio unitario
    if (!item.precio_unitario || parseFloat(item.precio_unitario) <= 0) {
      toast.error(`Item #${itemNum}: El precio unitario debe ser mayor a 0`)
      return false
    }
    
    // Validar que el subtotal sea positivo
    if (parseFloat(item.subtotal) <= 0) {
      toast.error(`Item #${itemNum}: El subtotal debe ser mayor a 0. Verifique cantidad y precio.`)
      return false
    }
  }
  
  // Validar monto total
  if (montoTotal.value <= 0) {
    toast.error('El monto total de la compra debe ser mayor a 0')
    return false
  }
  
  console.log('✅ Validación de compra exitosa')
  return true
}

// Validar si el formulario tiene errores (para deshabilitar botón)
const tieneErrores = computed(() => {
  if (!nuevaCompra.value.fecha_compra) return true
  if (nuevaCompra.value.detalles.length === 0) return true
  if (montoTotal.value <= 0) return true
  
  return nuevaCompra.value.detalles.some(item => {
    return !item.descripcion?.trim() || 
           parseFloat(item.cantidad) <= 0 || 
           parseFloat(item.precio_unitario) <= 0
  })
})

const resetFormulario = () => {
  nuevaCompra.value = {
    fecha_compra: new Date().toISOString().split('T')[0],
    estado: 'pendiente',
    observaciones: '',
    detalles: [
      {
        descripcion: '',
        cantidad: 1,
        precio_unitario: 0,
        descuento_item: 0,
        impuesto_porcentaje: 0,
        subtotal: 0
      }
    ]
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
        <VIcon icon="mdi-cart-plus" size="32" color="primary" />
        <div>
          <h3 class="text-h5">Registro de Compras a Proveedores</h3>
          <p class="text-caption text-medium-emphasis">Registre las compras realizadas a sus proveedores</p>
        </div>
      </div>
    </VCardTitle>

    <VCardText>
      <VDataTable
        :headers="headers"
        :items="proveedores"
        :loading="loading"
        loading-text="Cargando proveedores..."
        no-data-text="No hay proveedores registrados"
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

        <template #item.actions="{ item }">
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="mdi-cart-plus"
            @click="abrirDialogCompra(item)"
          >
            Registrar Compra
          </VBtn>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>

  <!-- Dialog de registro de compra -->
  <VDialog v-model="dialogCompra" max-width="1200px" persistent scrollable>
    <VCard>
      <VCardTitle class="bg-primary text-white">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon icon="mdi-cart-plus" size="28" class="mr-2" />
            <span class="text-h5">Registrar Compra a Proveedor</span>
          </div>
          <VBtn
            icon="mdi-close"
            variant="text"
            size="small"
            color="white"
            @click="dialogCompra = false"
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
              <div class="text-caption">Monto Total</div>
              <div class="text-h5 font-weight-bold text-primary">
                {{ formatPrice(montoTotal) }}
              </div>
            </VCol>
          </VRow>
        </VAlert>

        <!-- Formulario -->
        <VForm>
          <VRow>
            <!-- Sección: Datos de la Compra -->
            <VCol cols="12">
              <div class="text-h6 mb-3">
                <VIcon icon="mdi-information" class="mr-2" />
                Datos de la Compra
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="nuevaCompra.fecha_compra"
                label="Fecha de Compra *"
                type="date"
                variant="outlined"
                prepend-inner-icon="mdi-calendar"
                required
              />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect
                v-model="nuevaCompra.estado"
                :items="estadosCompra"
                label="Estado *"
                variant="outlined"
                prepend-inner-icon="mdi-check-circle"
                required
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="nuevaCompra.observaciones"
                label="Observaciones"
                variant="outlined"
                rows="2"
                prepend-inner-icon="mdi-note-text"
              />
            </VCol>

            <!-- Sección: Items de la Compra -->
            <VCol cols="12">
              <VDivider class="my-2" />
            </VCol>

            <VCol cols="12">
              <div class="d-flex justify-space-between align-center mb-3">
                <div class="text-h6">
                  <VIcon icon="mdi-format-list-bulleted" class="mr-2" />
                  Items de la Compra
                </div>
                <VBtn
                  color="primary"
                  variant="tonal"
                  prepend-icon="mdi-plus"
                  @click="agregarItem"
                >
                  Agregar Item
                </VBtn>
              </div>
            </VCol>

            <!-- Lista de items -->
            <VCol cols="12" v-for="(item, index) in nuevaCompra.detalles" :key="index">
              <VCard variant="outlined" class="pa-4">
                <div class="d-flex justify-space-between align-center mb-3">
                  <div class="text-subtitle-1 font-weight-bold">Item #{{ index + 1 }}</div>
                  <VBtn
                    icon="mdi-delete"
                    size="small"
                    color="error"
                    variant="text"
                    @click="eliminarItem(index)"
                    :disabled="nuevaCompra.detalles.length === 1"
                  />
                </div>

                <VRow>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="item.descripcion"
                      label="Descripción (¿Qué se compró?) *"
                      variant="outlined"
                      prepend-inner-icon="mdi-text"
                      placeholder="Ej: Madera de pino, Tornillos, Pintura blanca..."
                      required
                      :rules="[v => !!v?.trim() || 'Debe especificar qué se compró']"
                      :error="!item.descripcion?.trim() && item.descripcion !== ''"
                    />
                  </VCol>

                  <VCol cols="12" md="3">
                    <VTextField
                      v-model.number="item.cantidad"
                      label="Cantidad *"
                      type="number"
                      step="0.01"
                      min="0.01"
                      variant="outlined"
                      prepend-inner-icon="mdi-numeric"
                      required
                      :rules="[v => v > 0 || 'Debe ser mayor a 0']"
                      :error="parseFloat(item.cantidad) <= 0"
                      @input="calcularSubtotalItem(item)"
                    />
                  </VCol>

                  <VCol cols="12" md="3">
                    <VTextField
                      v-model.number="item.precio_unitario"
                      label="Precio Unitario *"
                      type="number"
                      step="0.01"
                      min="0"
                      prefix="$"
                      variant="outlined"
                      prepend-inner-icon="mdi-currency-usd"
                      required
                      :rules="[v => v > 0 || 'Debe ser mayor a 0']"
                      :error="parseFloat(item.precio_unitario) <= 0"
                      @input="calcularSubtotalItem(item)"
                    />
                  </VCol>

                  <VCol cols="12" md="4">
                    <VTextField
                      v-model.number="item.descuento_item"
                      label="Descuento"
                      type="number"
                      step="0.01"
                      prefix="$"
                      variant="outlined"
                      prepend-inner-icon="mdi-sale"
                      @input="calcularSubtotalItem(item)"
                    />
                  </VCol>

                  <VCol cols="12" md="4">
                    <VTextField
                      v-model.number="item.impuesto_porcentaje"
                      label="IVA %"
                      type="number"
                      step="0.01"
                      suffix="%"
                      variant="outlined"
                      prepend-inner-icon="mdi-percent"
                    />
                  </VCol>

                  <VCol cols="12" md="4">
                    <VTextField
                      :model-value="formatPrice(item.subtotal)"
                      label="Subtotal"
                      variant="outlined"
                      readonly
                      prepend-inner-icon="mdi-calculator"
                      class="font-weight-bold"
                    />
                  </VCol>
                </VRow>
              </VCard>
            </VCol>

            <!-- Resumen de totales -->
            <VCol cols="12">
              <VDivider class="my-2" />
            </VCol>

            <VCol cols="12">
              <VCard color="primary" variant="tonal" class="pa-4">
                <VRow>
                  <VCol cols="12" md="4">
                    <div class="text-caption">Subtotal</div>
                    <div class="text-h6">{{ formatPrice(subtotalCompra) }}</div>
                  </VCol>
                  <VCol cols="12" md="4">
                    <div class="text-caption">IVA</div>
                    <div class="text-h6">{{ formatPrice(totalImpuestos) }}</div>
                  </VCol>
                  <VCol cols="12" md="4">
                    <div class="text-caption">TOTAL</div>
                    <div class="text-h5 font-weight-bold">{{ formatPrice(montoTotal) }}</div>
                  </VCol>
                </VRow>
              </VCard>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          color="grey"
          variant="outlined"
          @click="dialogCompra = false; resetFormulario()"
          :disabled="loading"
        >
          <VIcon icon="mdi-close" start />
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="guardarCompra"
          :loading="loading"
          :disabled="tieneErrores || loading"
        >
          <VIcon icon="mdi-check" start />
          Guardar Compra
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
