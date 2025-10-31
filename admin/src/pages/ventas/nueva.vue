<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { createVenta } from '@/services/ventas'
import { getClientes } from '@/services/clientes'
import { getProductos } from '@/services/productos'
import { getMetodosPago } from '@/services/metodosPago'
import { getPedidosPendientes, getPedido } from '@/services/pedidos'
import { toast } from '@/plugins/toast'

const router = useRouter()
const route = useRoute()

const clientes = ref([])
const productos = ref([])
const metodosPago = ref([])
const loading = ref(false)
const error = ref('')

const venta = ref({
  cliente_id: null,
  fecha: new Date().toISOString().split('T')[0],
  tipo_comprobante: null,
  numero_comprobante: null,
  productos: [],
  pagos: [], // Array de pagos
  observaciones: '',
  pedido_id: null, // Para vincular con pedido si viene de uno
})

const productoSeleccionado = ref(null)
const cantidadProducto = ref(1)
const precioProducto = ref(0)
const pedidosPendientes = ref([])
const mostrarPedidos = ref(false)
const numeroComprobantePreview = ref(null)

// Pago
const pagoActual = ref({
  metodo_pago_id: null,
  monto: 0,
})

// Cargar pedido desde la ruta si viene de pedidos
const pedidoId = computed(() => {
  return router.currentRoute.value.query.pedido_id
})

// Computed
const totalCalculado = computed(() => {
  return venta.value.productos.reduce((sum, item) => {
    return sum + (item.cantidad * item.precio_unitario)
  }, 0)
})

const totalPagos = computed(() => {
  return venta.value.pagos.reduce((sum, pago) => sum + parseFloat(pago.monto || 0), 0)
})

const saldoPendiente = computed(() => {
  return Math.max(0, totalCalculado.value - totalPagos.value)
})

const clienteSeleccionado = computed(() => {
  return clientes.value.find(c => c.id === venta.value.cliente_id)
})

const tieneCuentaCorriente = computed(() => {
  return clienteSeleccionado.value && (clienteSeleccionado.value.limite_credito ?? 0) > 0
})

const creditoDisponible = computed(() => {
  if (!tieneCuentaCorriente.value) return 0
  const limite = parseFloat(clienteSeleccionado.value.limite_credito || 0)
  const saldo = parseFloat(clienteSeleccionado.value.saldo_actual || 0)
  return Math.max(0, limite - saldo)
})

const requiereFactura = computed(() => {
  return clienteSeleccionado.value && (clienteSeleccionado.value.requiere_factura ?? true)
})

// Methods
const fetchClientes = async () => {
  try {
    const response = await getClientes()
    clientes.value = Array.isArray(response) ? response : (response.data ?? [])
  } catch (e) {
    toast.error('Error al cargar clientes')
    console.error('Error al cargar clientes:', e)
  }
}

const fetchProductos = async () => {
  try {
    const response = await getProductos()
    productos.value = Array.isArray(response) ? response : (response.data ?? [])
  } catch (e) {
    toast.error('Error al cargar productos')
    console.error('Error al cargar productos:', e)
  }
}

const fetchMetodosPago = async () => {
  try {
    const response = await getMetodosPago()
    metodosPago.value = Array.isArray(response) ? response : (response.data ?? [])
  } catch (e) {
    toast.error('Error al cargar métodos de pago')
    console.error('Error al cargar métodos de pago:', e)
  }
}

const fetchPedidosPendientes = async (clienteId) => {
  if (!clienteId) {
    pedidosPendientes.value = []
    return
  }
  
  try {
    const response = await getPedidosPendientes(clienteId)
    pedidosPendientes.value = Array.isArray(response) ? response : (response.data ?? [])
  } catch (e) {
    console.error('Error al cargar pedidos pendientes:', e)
  }
}

const cargarPedido = async (pedidoId) => {
  try {
    const response = await getPedido(pedidoId)
    const pedido = response.data || response
    
    // Cargar datos del pedido en la venta
    venta.value.cliente_id = pedido.cliente_id
    venta.value.pedido_id = pedido.id
    venta.value.observaciones = pedido.observaciones || ''
    
    // Cargar productos del pedido
    if (pedido.items && Array.isArray(pedido.items)) {
      venta.value.productos = pedido.items.map(item => ({
        producto_id: item.producto_id,
        producto_nombre: item.producto?.nombre || 'Producto',
        cantidad: item.cantidad,
        precio_unitario: item.precio_unitario,
      }))
    }
    
    toast.success('Pedido cargado correctamente')
  } catch (e) {
    toast.error('Error al cargar el pedido')
    console.error('Error al cargar pedido:', e)
  }
}

const usarPedido = (pedido) => {
  venta.value.cliente_id = pedido.cliente_id
  venta.value.pedido_id = pedido.id
  venta.value.observaciones = pedido.observaciones || ''
  
  // Cargar productos del pedido
  if (pedido.items && Array.isArray(pedido.items)) {
    venta.value.productos = pedido.items.map(item => ({
      producto_id: item.producto_id,
      producto_nombre: item.producto?.nombre || 'Producto',
      cantidad: item.cantidad,
      precio_unitario: item.precio_unitario,
    }))
  }
  
  mostrarPedidos.value = false
  toast.info('Productos del pedido agregados')
}

const agregarProducto = () => {
  if (!productoSeleccionado.value) {
    toast.warning('Debe seleccionar un producto')
    return
  }

  if (cantidadProducto.value <= 0) {
    toast.warning('La cantidad debe ser mayor a 0')
    return
  }

  const producto = productos.value.find(p => p.id === productoSeleccionado.value)
  
  // Verificar si el producto ya está en la lista
  const index = venta.value.productos.findIndex(p => p.producto_id === productoSeleccionado.value)
  
  if (index !== -1) {
    // Actualizar cantidad si ya existe
    venta.value.productos[index].cantidad += cantidadProducto.value
  } else {
    // Agregar nuevo producto
    venta.value.productos.push({
      producto_id: productoSeleccionado.value,
      producto_nombre: producto.nombre,
      cantidad: cantidadProducto.value,
      precio_unitario: precioProducto.value || producto.precio,
    })
  }

  // Limpiar selección
  productoSeleccionado.value = null
  cantidadProducto.value = 1
  precioProducto.value = 0
}

const eliminarProducto = (index) => {
  venta.value.productos.splice(index, 1)
}

const onProductoChange = () => {
  const producto = productos.value.find(p => p.id === productoSeleccionado.value)
  if (producto) {
    precioProducto.value = producto.precio
  }
}

const agregarPago = () => {
  if (!pagoActual.value.metodo_pago_id) {
    toast.warning('Debe seleccionar un método de pago')
    return
  }

  if (pagoActual.value.monto <= 0) {
    toast.warning('El monto debe ser mayor a 0')
    return
  }

  if (pagoActual.value.monto > saldoPendiente.value) {
    toast.warning('El monto del pago no puede ser mayor al saldo pendiente')
    return
  }

  venta.value.pagos.push({
    metodo_pago_id: pagoActual.value.metodo_pago_id,
    monto: parseFloat(pagoActual.value.monto),
    metodo_nombre: metodosPago.value.find(m => m.id === pagoActual.value.metodo_pago_id)?.nombre,
  })

  // Limpiar pago actual
  pagoActual.value = {
    metodo_pago_id: null,
    monto: 0,
  }

  toast.success('Pago agregado')
}

const eliminarPago = (index) => {
  venta.value.pagos.splice(index, 1)
  toast.info('Pago eliminado')
}

const pagarTotal = () => {
  if (!pagoActual.value.metodo_pago_id) {
    toast.warning('Primero seleccione un método de pago')
    return
  }

  if (saldoPendiente.value <= 0) {
    toast.info('No hay saldo pendiente para pagar')
    return
  }

  // Agregar el pago con el total pendiente
  venta.value.pagos.push({
    metodo_pago_id: pagoActual.value.metodo_pago_id,
    monto: parseFloat(saldoPendiente.value),
    metodo_nombre: metodosPago.value.find(m => m.id === pagoActual.value.metodo_pago_id)?.nombre,
  })

  // Limpiar pago actual
  pagoActual.value = {
    metodo_pago_id: null,
    monto: 0,
  }

  toast.success('Pago total agregado correctamente')
}

const guardarVenta = async () => {
  // Validaciones
  if (!venta.value.cliente_id) {
    toast.warning('Debe seleccionar un cliente')
    return
  }

  if (venta.value.productos.length === 0) {
    toast.warning('Debe agregar al menos un producto')
    return
  }

  // Validar facturación si el cliente lo requiere
  if (requiereFactura.value) {
    if (!venta.value.tipo_comprobante) {
      toast.warning('Debe especificar el tipo de comprobante')
      return
    }
    // El número se genera automáticamente, no validar
  }

  // Validar pagos según si tiene cuenta corriente
  if (!tieneCuentaCorriente.value && saldoPendiente.value > 0) {
    toast.warning('El cliente no tiene cuenta corriente. Debe pagar el total de la venta.')
    return
  }

  if (saldoPendiente.value > creditoDisponible.value && tieneCuentaCorriente.value) {
    toast.warning(`El saldo pendiente (${formatPrice(saldoPendiente.value)}) supera el crédito disponible (${formatPrice(creditoDisponible.value)})`)
    return
  }

  try {
    loading.value = true
    error.value = ''

    const dataToSend = {
      cliente_id: venta.value.cliente_id,
      fecha: venta.value.fecha,
      tipo_comprobante: venta.value.tipo_comprobante,
      numero_comprobante: venta.value.numero_comprobante,
      pedido_id: venta.value.pedido_id,
      items: venta.value.productos.map(p => ({
        producto_id: p.producto_id,
        cantidad: p.cantidad,
        precio_unitario: p.precio_unitario,
        iva: 0,
      })),
      pagos: venta.value.pagos.map(p => ({
        metodo_pago_id: p.metodo_pago_id,
        monto: p.monto,
        fecha_pago: venta.value.fecha,
      })),
    }

    await createVenta(dataToSend)
    toast.success('Venta creada correctamente')
    router.push('/ventas')
  } catch (e) {
    console.error('Error al guardar venta:', e)
    let errorMsg = ''
    if (e.message.includes('límite de crédito')) {
      errorMsg = 'El cliente ha superado su límite de crédito. Verifique el saldo o ajuste el límite.'
    } else {
      errorMsg = e.message || 'Error al guardar venta'
    }
    error.value = errorMsg
    toast.error(errorMsg)
  } finally {
    loading.value = false
  }
}

const cancelar = () => {
  router.push('/ventas')
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const previsualizarNumeroComprobante = async (tipoComprobante) => {
  if (!tipoComprobante) {
    numeroComprobantePreview.value = null
    return
  }

  try {
    const token = localStorage.getItem('token')
    const response = await fetch(`http://localhost:8000/api/ventas/previsualizar-numero?tipo_comprobante=${encodeURIComponent(tipoComprobante)}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      }
    })

    if (response.ok) {
      const data = await response.json()
      numeroComprobantePreview.value = data.numero
    }
  } catch (e) {
    console.error('Error al previsualizar número:', e)
  }
}

const getEstadoColor = (estado) => {
  const colors = {
    'pendiente': 'warning',
    'en_proceso': 'info',
    'entregado': 'success',
    'cancelado': 'error'
  }
  return colors[estado] || 'secondary'
}

onMounted(async () => {
  await Promise.all([
    fetchClientes(),
    fetchProductos(),
    fetchMetodosPago()
  ])
  
  // Cargar pedido si viene desde pedidos
  if (pedidoId.value) {
    await cargarPedido(pedidoId.value)
  }
})

// Cargar pedidos pendientes cuando cambie el cliente
watch(() => venta.value.cliente_id, (newVal) => {
  if (newVal) {
    fetchPedidosPendientes(newVal)
  } else {
    pedidosPendientes.value = []
  }
})

// Previsualizar número cuando cambie el tipo de comprobante
watch(() => venta.value.tipo_comprobante, (newVal) => {
  previsualizarNumeroComprobante(newVal)
})
</script>

<template>
  <div class="pa-6">
    <!-- Header -->
    <div class="d-flex align-center mb-4">
      <VBtn
        icon
        variant="text"
        color="secondary"
        @click="cancelar"
        class="mr-3"
      >
        <VIcon>ri-arrow-left-line</VIcon>
      </VBtn>
      <div>
        <h2 class="text-h4 mb-1">Nueva Venta</h2>
        <VChip
          v-if="venta.pedido_id"
          color="info"
          size="small"
        >
          <VIcon size="16" class="mr-1">ri-file-list-line</VIcon>
          Desde Pedido #{{ venta.pedido_id }}
        </VChip>
      </div>
    </div>

    <VAlert v-if="error" type="error" dismissible @click:close="error = ''" class="mb-4">
      {{ error }}
    </VAlert>

    <VRow>
      <!-- Columna Principal - Formulario -->
      <VCol cols="12" xl="8" lg="7">
        <VRow>
          <!-- Información del Cliente -->
          <VCol cols="12">
            <VCard variant="outlined" class="mb-3">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-user-line</VIcon>
                Información del Cliente
              </VCardTitle>
              <VCardText class="pa-3">
                <VSelect
                  v-model="venta.cliente_id"
                  :items="clientes"
                  item-title="nombre"
                  item-value="id"
                  label="Cliente*"
                  required
                  prepend-inner-icon="ri-user-3-line"
                  density="compact"
                  variant="outlined"
                >
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      :subtitle="`CUIT: ${item.raw.cuit} | Crédito: ${formatPrice(item.raw.limite_credito)}`"
                    />
                  </template>
                </VSelect>

                <div v-if="clienteSeleccionado" class="mt-3 pa-3 bg-surface rounded">
                  <VRow dense>
                    <VCol cols="12" md="4">
                      <div class="text-caption text-medium-emphasis">Email</div>
                      <div class="text-body-2 font-weight-medium">{{ clienteSeleccionado.email }}</div>
                    </VCol>
                    <VCol cols="12" md="4">
                      <div class="text-caption text-medium-emphasis">Teléfono</div>
                      <div class="text-body-2 font-weight-medium">{{ clienteSeleccionado.telefono }}</div>
                    </VCol>
                    <VCol cols="12" md="4">
                      <div class="text-caption text-medium-emphasis">Límite de Crédito</div>
                      <div class="text-body-2 font-weight-medium text-success">{{ formatPrice(clienteSeleccionado.limite_credito) }}</div>
                    </VCol>
                  </VRow>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Pedidos Pendientes -->
          <VCol v-if="venta.cliente_id && pedidosPendientes.length > 0" cols="12">
            <VCard variant="outlined" class="mb-3 border-warning">
              <VCardTitle class="text-body-1 pa-3 bg-warning">
                <VIcon class="mr-2" size="18">ri-file-list-3-line</VIcon>
                Pedidos Pendientes del Cliente
                <VChip class="ml-2" color="white" size="small">{{ pedidosPendientes.length }}</VChip>
              </VCardTitle>
              <VCardText class="pa-2">
                <VExpansionPanels v-model="mostrarPedidos">
                  <VExpansionPanel>
                    <VExpansionPanelTitle>
                      <VIcon class="mr-2" size="18">ri-arrow-down-s-line</VIcon>
                      Ver pedidos pendientes ({{ pedidosPendientes.length }})
                    </VExpansionPanelTitle>
                    <VExpansionPanelText>
                      <VRow>
                        <VCol
                          v-for="pedido in pedidosPendientes"
                          :key="pedido.id"
                          cols="12"
                          md="6"
                        >
                          <VCard
                            variant="outlined"
                            :class="venta.pedido_id === pedido.id ? 'border-success elevation-2' : ''"
                          >
                            <VCardText class="pa-3">
                              <div class="d-flex justify-space-between align-center mb-2">
                                <VChip color="info" size="small">
                                  #{{ pedido.id }}
                                </VChip>
                                <VChip :color="getEstadoColor(pedido.estado)" size="small">
                                  {{ pedido.estado }}
                                </VChip>
                              </div>
                              
                              <div class="text-body-2 mb-2">
                                <VIcon size="16" class="mr-1">ri-calendar-line</VIcon>
                                {{ new Date(pedido.fecha_pedido).toLocaleDateString('es-AR') }}
                              </div>
                              
                              <div v-if="pedido.clima_actual" class="text-body-2 mb-2">
                                <VIcon size="16" class="mr-1">ri-sun-line</VIcon>
                                {{ pedido.clima_actual }}
                              </div>
                              
                              <div class="text-body-2 mb-3">
                                <VIcon size="16" class="mr-1">ri-shopping-bag-line</VIcon>
                                {{ pedido.items?.length || 0 }} productos
                              </div>

                              <VBtn
                                block
                                color="primary"
                                variant="flat"
                                size="small"
                                @click="usarPedido(pedido)"
                                :disabled="venta.pedido_id === pedido.id"
                              >
                                <VIcon size="18" class="mr-1">ri-add-circle-line</VIcon>
                                {{ venta.pedido_id === pedido.id ? 'Pedido Cargado' : 'Usar Este Pedido' }}
                              </VBtn>
                            </VCardText>
                          </VCard>
                        </VCol>
                      </VRow>
                    </VExpansionPanelText>
                  </VExpansionPanel>
                </VExpansionPanels>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Agregar Productos -->
          <VCol cols="12">
            <VCard variant="outlined" class="mb-3">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-add-box-line</VIcon>
                Agregar Productos
              </VCardTitle>
              <VCardText class="pa-3">
                <VRow>
                  <VCol cols="12" md="5">
                    <VSelect
                      v-model="productoSeleccionado"
                      :items="productos"
                      item-title="nombre"
                      item-value="id"
                      label="Producto"
                      @update:model-value="onProductoChange"
                      prepend-inner-icon="ri-product-hunt-line"
                      density="compact"
                      variant="outlined"
                    >
                      <template #item="{ props, item }">
                        <VListItem
                          v-bind="props"
                          :subtitle="`${item.raw.codigo} | ${formatPrice(item.raw.precio)}`"
                        />
                      </template>
                    </VSelect>
                  </VCol>
                  <VCol cols="12" md="2">
                    <VTextField
                      v-model.number="cantidadProducto"
                      label="Cantidad"
                      type="number"
                      min="1"
                      prepend-inner-icon="ri-hashtag"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField
                      v-model.number="precioProducto"
                      label="Precio Unitario"
                      type="number"
                      min="0"
                      step="0.01"
                      prepend-inner-icon="ri-money-dollar-circle-line"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12" md="2">
                    <VBtn
                      block
                      color="primary"
                      @click="agregarProducto"
                    >
                      <VIcon size="18">ri-add-line</VIcon>
                      Agregar
                    </VBtn>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Lista de Productos -->
          <VCol cols="12">
            <VCard variant="outlined">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-list-check</VIcon>
                Productos en la Venta
                <VChip class="ml-2" color="white" size="small">{{ venta.productos.length }}</VChip>
              </VCardTitle>
              <VCardText class="pa-3">
                <VAlert v-if="venta.productos.length === 0" type="info" variant="tonal" class="mb-0">
                  <div class="d-flex align-center">
                    <VIcon class="mr-2">ri-information-line</VIcon>
                    No hay productos agregados. Agregue productos usando el formulario de arriba.
                  </div>
                </VAlert>

                <div v-else class="table-responsive">
                  <VTable>
                    <thead>
                      <tr>
                        <th>Producto</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Precio Unit.</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-center">Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, index) in venta.productos" :key="index">
                        <td>
                          <div class="font-weight-medium">{{ item.producto_nombre }}</div>
                        </td>
                        <td class="text-center">
                          <VChip size="small" color="info">{{ item.cantidad }}</VChip>
                        </td>
                        <td class="text-end">{{ formatPrice(item.precio_unitario) }}</td>
                        <td class="text-end font-weight-medium">{{ formatPrice(item.cantidad * item.precio_unitario) }}</td>
                        <td class="text-center">
                          <VBtn
                            icon
                            size="small"
                            color="error"
                            variant="text"
                            @click="eliminarProducto(index)"
                          >
                            <VIcon>ri-delete-bin-line</VIcon>
                          </VBtn>
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Observaciones -->
          <VCol cols="12">
            <VCard variant="outlined">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-message-2-line</VIcon>
                Observaciones
              </VCardTitle>
              <VCardText class="pa-3">
                <VTextarea
                  v-model="venta.observaciones"
                  label="Observaciones adicionales (opcional)"
                  rows="2"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCol>

      <!-- Columna Lateral - Resumen y Detalles -->
      <VCol cols="12" xl="4" lg="5">
        <!-- Resumen Total -->
        <VCard class="mb-3 elevation-2" variant="tonal" color="success">
          <VCardText class="pa-3">
            <div class="text-center">
              <div class="text-caption mb-1">Total a Pagar</div>
              <div class="text-h4 font-weight-bold text-success">{{ formatPrice(totalCalculado) }}</div>
            </div>
            
            <VDivider class="my-2" />
            
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-body-2">Productos:</span>
              <VChip color="success" size="small" variant="flat">{{ venta.productos.length }}</VChip>
            </div>
            <div class="d-flex justify-space-between align-center">
              <span class="text-body-2">Ítems totales:</span>
              <VChip color="success" size="small" variant="flat">{{ venta.productos.reduce((sum, p) => sum + p.cantidad, 0) }}</VChip>
            </div>
          </VCardText>
        </VCard>

        <!-- Información del Cliente -->
        <VCard v-if="clienteSeleccionado" variant="outlined" class="mb-3">
          <VCardTitle class="text-body-1 pa-3 pb-2 d-flex align-center justify-space-between">
            <div>
              <VIcon class="mr-2" size="18">ri-information-line</VIcon>
              Información del Cliente
            </div>
            <VChip
              v-if="requiereFactura"
              color="primary"
              size="small"
              prepend-icon="ri-file-list-3-line"
            >
              Requiere Factura
            </VChip>
          </VCardTitle>
          <VCardText class="pa-3 pt-1">
            <VRow dense>
              <VCol v-if="tieneCuentaCorriente" cols="12" sm="6" md="4">
                <div class="text-caption text-medium-emphasis">Cuenta Corriente</div>
                <div class="text-body-2 font-weight-medium">
                  <VIcon size="16" color="success" class="mr-1">ri-checkbox-circle-line</VIcon>
                  Habilitada
                </div>
              </VCol>
              <VCol v-if="tieneCuentaCorriente" cols="12" sm="6" md="4">
                <div class="text-caption text-medium-emphasis">Límite de Crédito</div>
                <div class="text-body-2 font-weight-medium">{{ formatPrice(clienteSeleccionado.limite_credito) }}</div>
              </VCol>
              <VCol v-if="tieneCuentaCorriente" cols="12" sm="6" md="4">
                <div class="text-caption text-medium-emphasis">Crédito Disponible</div>
                <div class="text-body-2 font-weight-medium" :class="creditoDisponible > 0 ? 'text-success' : 'text-error'">
                  {{ formatPrice(creditoDisponible) }}
                </div>
              </VCol>
              <VCol v-if="!tieneCuentaCorriente" cols="12">
                <VAlert type="warning" variant="tonal" density="compact">
                  Cliente sin cuenta corriente - Se requiere pago total
                </VAlert>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Facturación (solo si el cliente requiere factura) -->
        <VCard v-if="requiereFactura" variant="outlined" class="mb-3">
          <VCardTitle class="text-body-1 pa-3 pb-2 bg-surface-variant">
            <VIcon class="mr-2" size="18">ri-file-text-line</VIcon>
            Datos de Facturación
          </VCardTitle>
          <VCardText class="pa-3 pt-3">
            <VRow>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="venta.tipo_comprobante"
                  :items="[
                    { value: 'Factura A', title: 'Factura A' },
                    { value: 'Factura B', title: 'Factura B' },
                    { value: 'Factura C', title: 'Factura C' },
                    { value: 'Ticket', title: 'Ticket' }
                  ]"
                  label="Tipo de Comprobante*"
                  prepend-inner-icon="ri-file-list-line"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  :model-value="numeroComprobantePreview"
                  label="Número de Comprobante (Generado Automáticamente)"
                  prepend-inner-icon="ri-hashtag"
                  variant="outlined"
                  density="comfortable"
                  readonly
                  :placeholder="venta.tipo_comprobante ? 'Se generará al guardar...' : 'Seleccione tipo de comprobante'"
                  :hint="numeroComprobantePreview ? `Próximo número: ${numeroComprobantePreview}` : ''"
                  persistent-hint
                >
                  <template #append-inner>
                    <VIcon v-if="numeroComprobantePreview" color="success">ri-checkbox-circle-fill</VIcon>
                  </template>
                </VTextField>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Gestión de Pagos -->
        <VCard variant="outlined" class="mb-3">
          <VCardTitle class="d-flex align-center justify-space-between pa-4">
            <div class="d-flex align-center">
              <VIcon class="mr-2" size="20" color="primary">ri-money-dollar-circle-line</VIcon>
              <span class="text-h6">Gestión de Pagos</span>
            </div>
            <VChip
              v-if="venta.pagos.length > 0"
              color="success"
              variant="tonal"
              size="small"
            >
              {{ venta.pagos.length }} pago(s)
            </VChip>
          </VCardTitle>
          
          <VDivider />
          
          <VCardText class="pa-4">
            <!-- Fecha de Venta -->
            <VTextField
              v-model="venta.fecha"
              label="Fecha de Venta*"
              type="date"
              required
              prepend-inner-icon="ri-calendar-line"
              variant="outlined"
              density="comfortable"
              class="mb-5"
            />

            <!-- Formulario Agregar Pago -->
            <VCard variant="tonal" color="primary" class="mb-4">
              <VCardText class="pa-4">
                <div class="d-flex align-center mb-4">
                  <VIcon class="mr-2" size="22">ri-add-circle-line</VIcon>
                  <span class="text-h6 font-weight-medium">Registrar Pago</span>
                </div>
                
                <VRow>
                  <VCol cols="12" md="6">
                    <VSelect
                      v-model="pagoActual.metodo_pago_id"
                      :items="metodosPago"
                      item-value="id"
                      label="Método de Pago"
                      prepend-inner-icon="ri-bank-card-line"
                      variant="outlined"
                      density="comfortable"
                      bg-color="white"
                    >
                      <template #selection="{ item }">
                        {{ item.raw.nombre }}
                      </template>
                      <template #item="{ props, item }">
                        <VListItem v-bind="props" :title="item.raw.nombre" :subtitle="item.raw.descripcion">
                        </VListItem>
                      </template>
                    </VSelect>
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model.number="pagoActual.monto"
                      label="Monto del Pago"
                      type="number"
                      min="0"
                      step="0.01"
                      prefix="$"
                      variant="outlined"
                      density="comfortable"
                      bg-color="white"
                      :hint="saldoPendiente > 0 ? `Saldo pendiente: ${formatPrice(saldoPendiente)}` : ''"
                      persistent-hint
                    />
                  </VCol>
                </VRow>

                <VRow class="mt-4">
                  <VCol cols="12" md="6" class="pb-2 pb-md-3">
                    <VBtn
                      color="white"
                      size="large"
                      variant="flat"
                      block
                      class="text-none font-weight-medium px-2"
                      @click="agregarPago"
                      :disabled="!pagoActual.metodo_pago_id || pagoActual.monto <= 0"
                    >
                      <VIcon start size="20">ri-add-line</VIcon>
                      <span>Agregar Pago</span>
                    </VBtn>
                  </VCol>
                  
                  <VCol cols="12" md="6" class="pt-2 pt-md-3">
                    <VBtn
                      color="success"
                      size="large"
                      variant="flat"
                      block
                      class="text-none font-weight-medium px-2"
                      @click="pagarTotal"
                      :disabled="saldoPendiente <= 0 || !pagoActual.metodo_pago_id"
                    >
                      <VIcon start size="20">ri-money-dollar-circle-fill</VIcon>
                      <span class="d-inline-block" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis;">
                        Pagar Total ({{ formatPrice(saldoPendiente) }})
                      </span>
                    </VBtn>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <!-- Lista de Pagos Registrados -->
            <div v-if="venta.pagos.length > 0" class="mb-4">
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="d-flex align-center">
                  <VIcon class="mr-2" color="success" size="20">ri-checkbox-circle-line</VIcon>
                  <span class="text-subtitle-1 font-weight-medium">Pagos Registrados</span>
                </div>
                <VChip color="success" variant="outlined" size="small">
                  Total: {{ formatPrice(totalPagos) }}
                </VChip>
              </div>
              
              <VCard variant="outlined">
                <VList lines="two" class="py-0">
                  <VListItem
                    v-for="(pago, index) in venta.pagos"
                    :key="index"
                    class="px-4 py-3"
                    :class="{ 'border-b': index < venta.pagos.length - 1 }"
                  >
                    <template #prepend>
                      <VAvatar color="success" variant="tonal" size="48">
                        <VIcon size="24">ri-money-dollar-circle-fill</VIcon>
                      </VAvatar>
                    </template>
                    
                    <VListItemTitle class="font-weight-medium text-body-1 mb-1">
                      {{ pago.metodo_nombre }}
                    </VListItemTitle>
                    <VListItemSubtitle class="d-flex align-center">
                      <VIcon size="16" class="mr-1">ri-price-tag-3-line</VIcon>
                      <span class="text-success font-weight-bold">{{ formatPrice(pago.monto) }}</span>
                    </VListItemSubtitle>
                    
                    <template #append>
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="eliminarPago(index)"
                      >
                        <VIcon>ri-delete-bin-6-line</VIcon>
                      </VBtn>
                    </template>
                  </VListItem>
                </VList>
              </VCard>
            </div>

            <!-- Mensaje cuando no hay pagos -->
            <VAlert
              v-else
              type="info"
              variant="tonal"
              class="mb-4"
            >
              <template #prepend>
                <VIcon>ri-information-line</VIcon>
              </template>
              No se han registrado pagos. Agregue al menos un pago o deje el saldo pendiente si el cliente tiene cuenta corriente.
            </VAlert>

            <!-- Resumen de Pagos -->
            <VDivider class="my-4" />
            
            <VCard variant="flat" color="primary" class="pa-4">
              <VRow class="mb-3">
                <VCol cols="6" sm="6">
                  <div class="text-caption text-white opacity-80 mb-1">Total Venta</div>
                  <div class="text-h6 text-sm-h5 font-weight-bold text-white">{{ formatPrice(totalCalculado) }}</div>
                </VCol>
                <VCol cols="6" sm="6" class="text-right">
                  <div class="text-caption text-white opacity-80 mb-1">Total Pagado</div>
                  <div class="text-h6 text-sm-h5 font-weight-bold text-white">{{ formatPrice(totalPagos) }}</div>
                </VCol>
              </VRow>
              
              <VDivider class="my-3" color="white" opacity="0.3" />
              
              <div class="text-center py-2">
                <div class="text-caption text-white opacity-80 mb-2">Saldo Pendiente</div>
                <div 
                  class="text-h4 text-sm-h3 font-weight-bold"
                  :class="saldoPendiente > 0 ? 'text-warning' : 'text-white'"
                >
                  {{ formatPrice(saldoPendiente) }}
                </div>
              </div>
            </VCard>

            <!-- Alertas Contextuales -->
            <div class="mt-4">
              <VAlert
                v-if="saldoPendiente > 0 && tieneCuentaCorriente && saldoPendiente <= creditoDisponible"
                type="info"
                variant="tonal"
                density="comfortable"
              >
                <template #prepend>
                  <VIcon>ri-information-line</VIcon>
                </template>
                <div class="text-body-2">
                  <strong>Cuenta Corriente:</strong> El saldo pendiente de {{ formatPrice(saldoPendiente) }} quedará registrado en la cuenta corriente del cliente.
                </div>
              </VAlert>
              
              <VAlert
                v-if="saldoPendiente > creditoDisponible && tieneCuentaCorriente"
                type="error"
                variant="tonal"
                density="comfortable"
              >
                <template #prepend>
                  <VIcon>ri-error-warning-line</VIcon>
                </template>
                <div class="text-body-2">
                  <strong>Límite Excedido:</strong> El saldo pendiente ({{ formatPrice(saldoPendiente) }}) supera el crédito disponible ({{ formatPrice(creditoDisponible) }}).
                </div>
              </VAlert>
              
              <VAlert
                v-if="saldoPendiente > 0 && !tieneCuentaCorriente"
                type="warning"
                variant="tonal"
                density="comfortable"
              >
                <template #prepend>
                  <VIcon>ri-alert-line</VIcon>
                </template>
                <div class="text-body-2">
                  <strong>Pago Requerido:</strong> El cliente no tiene cuenta corriente habilitada. Debe registrar el pago total de la venta.
                </div>
              </VAlert>

              <VAlert
                v-if="saldoPendiente === 0 && venta.pagos.length > 0"
                type="success"
                variant="tonal"
                density="comfortable"
              >
                <template #prepend>
                  <VIcon>ri-checkbox-circle-line</VIcon>
                </template>
                <div class="text-body-2">
                  <strong>Venta Completa:</strong> El pago total ha sido registrado correctamente.
                </div>
              </VAlert>
            </div>
          </VCardText>
        </VCard>

        <!-- Botones de Acción -->
        <VBtn
          block
          color="primary"
          size="large"
          @click="guardarVenta"
          :loading="loading"
          :disabled="loading || venta.productos.length === 0 || !venta.cliente_id"
          class="mb-2"
        >
          <VIcon class="mr-2">ri-save-line</VIcon>
          Guardar Venta
        </VBtn>
        
        <VBtn
          block
          color="secondary"
          variant="outlined"
          size="large"
          @click="cancelar"
          :disabled="loading"
        >
          <VIcon class="mr-2">ri-close-line</VIcon>
          Cancelar
        </VBtn>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.bg-primary {
  background-color: rgb(var(--v-theme-primary));
  color: white;
}

.bg-warning {
  background-color: rgb(var(--v-theme-warning));
  color: white;
}

/* Responsividad mejorada */
@media (max-width: 599px) {
  .text-h6 {
    font-size: 1.1rem !important;
  }
  
  .text-h4 {
    font-size: 1.5rem !important;
  }
}

@media (max-width: 959px) {
  .table-responsive table {
    min-width: 600px;
  }
}

/* Mejora el truncado de texto en botones */
.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Asegura que los botones no se desborden */
.v-btn {
  overflow: hidden;
}
</style>
