<script setup>
import { ref, computed, onMounted, onActivated, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { createVenta } from '@/services/ventas'
import { getClientes } from '@/services/clientes'
import { getProductos } from '@/services/productos'
import { getMetodosPago } from '@/services/metodosPago'
import { getPedidosPendientes, getPedido } from '@/services/pedidos'
import { toast } from '@/plugins/toast'
import NumberInput from '@/components/NumberInput.vue'

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
  requiere_factura: false, // ← NUEVO: Decisión manual en cada venta
})

const productoSeleccionado = ref(null)
const busquedaProducto = ref('')
const mostrarResultadosProducto = ref(false)
const cantidadProducto = ref(1)
const precioProducto = ref(0)
const pedidosPendientes = ref([])
const mostrarPedidos = ref(false)
const numeroComprobantePreview = ref(null)

// Búsqueda de clientes
const busquedaCliente = ref('')
const mostrarResultadosCliente = ref(false)

// Pago
const pagoActual = ref({
  metodo_pago_id: null,
  monto: 0,
})

// Modal de cheque
const dialogCheque = ref(false)
const datosCheque = ref({
  numero_cheque: '',
  fecha_cheque: new Date().toISOString().split('T')[0],
  fecha_cobro: null,
  observaciones_cheque: '',
})

// Diálogos de confirmación
const dialogEliminarProducto = ref(false)
const productoAEliminar = ref(null)
const dialogEliminarPago = ref(false)
const pagoAEliminar = ref(null)

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
  const saldo = totalCalculado.value - totalPagos.value
  // Redondear a 2 decimales para evitar problemas de precisión
  return Math.max(0, Math.round(saldo * 100) / 100)
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

// Calcular el monto total que se pagará por Cuenta Corriente
const montoEnCuentaCorriente = computed(() => {
  if (!venta.value.pagos || venta.value.pagos.length === 0) return 0
  
  // Encontrar el ID del método "Cuenta Corriente"
  const metodoCuentaCorriente = metodosPago.value.find(m => m.nombre === 'Cuenta Corriente')
  if (!metodoCuentaCorriente) return 0
  
  // Sumar todos los pagos con ese método
  return venta.value.pagos
    .filter(pago => pago.metodo_pago_id === metodoCuentaCorriente.id)
    .reduce((sum, pago) => sum + parseFloat(pago.monto || 0), 0)
})

// Validar si el monto en cuenta corriente + saldo actual supera el límite
const superaLimiteCredito = computed(() => {
  if (!tieneCuentaCorriente.value) return false
  
  const saldoActual = parseFloat(clienteSeleccionado.value.saldo_actual || 0)
  const montoCC = montoEnCuentaCorriente.value
  const nuevoSaldo = saldoActual + montoCC
  const limite = parseFloat(clienteSeleccionado.value.limite_credito || 0)
  
  return nuevoSaldo > limite
})

// Ya no es computed, ahora se controla manualmente en cada venta
// const requiereFactura = computed(() => {
//   return clienteSeleccionado.value && (clienteSeleccionado.value.requiere_factura ?? true)
// })

const productosFiltrados = computed(() => {
  if (!busquedaProducto.value || busquedaProducto.value.trim() === '') {
    return [] // No mostrar nada si no hay búsqueda
  }
  
  const termino = busquedaProducto.value.toLowerCase().trim()
  
  return productos.value.filter(producto => {
    const nombre = (producto.nombre || '').toLowerCase()
    const codigo = (producto.codigo || '').toLowerCase()
    return nombre.includes(termino) || codigo.includes(termino)
  }).slice(0, 10) // Limitar a 10 resultados
})

const clientesFiltrados = computed(() => {
  if (!busquedaCliente.value || busquedaCliente.value.trim() === '') {
    return []
  }
  
  const termino = busquedaCliente.value.toLowerCase().trim()
  
  return clientes.value.filter(cliente => {
    const nombre = (cliente.nombre || '').toLowerCase()
    const apellido = (cliente.apellido || '').toLowerCase()
    const email = (cliente.email || '').toLowerCase()
    const cuit = (cliente.cuit || '').toLowerCase()
    const nombreCompleto = `${nombre} ${apellido}`
    
    return nombreCompleto.includes(termino) || 
           email.includes(termino) || 
           cuit.includes(termino)
  }).slice(0, 10)
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

const seleccionarCliente = (cliente) => {
  venta.value.cliente_id = cliente.id
  busquedaCliente.value = `${cliente.nombre} ${cliente.apellido}`
  mostrarResultadosCliente.value = false
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

const descargarPedido = () => {
  venta.value.pedido_id = null
  venta.value.productos = []
  venta.value.observaciones = ''
  pedidosPendientes.value = []
  toast.info('Pedido descargado. Ahora puede crear una venta independiente')
}

const seleccionarProducto = (producto) => {
  productoSeleccionado.value = producto.id
  busquedaProducto.value = `${producto.nombre} (${producto.codigo})`
  // IMPORTANTE: Usar precio_total que ya viene calculado desde el backend
  // precio_total = precio (que ya incluye IVA)
  precioProducto.value = parseFloat(producto.precio_total || producto.precio || 0)
  mostrarResultadosProducto.value = false
  
  // Focus en el campo de cantidad después de seleccionar
  setTimeout(() => {
    const cantidadInput = document.querySelector('input[type="number"]')
    if (cantidadInput) cantidadInput.focus()
  }, 100)
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
      precio_unitario: precioProducto.value, // Ya calculado con IVA incluido
    })
  }

  // Limpiar selección
  productoSeleccionado.value = null
  busquedaProducto.value = ''
  cantidadProducto.value = 1
  precioProducto.value = 0
  mostrarResultadosProducto.value = false
}

const eliminarProducto = (index) => {
  productoAEliminar.value = index
  dialogEliminarProducto.value = true
}

const confirmarEliminarProducto = () => {
  if (productoAEliminar.value !== null) {
    venta.value.productos.splice(productoAEliminar.value, 1)
    toast.info('Producto eliminado')
  }
  dialogEliminarProducto.value = false
  productoAEliminar.value = null
}

const cancelarEliminarProducto = () => {
  dialogEliminarProducto.value = false
  productoAEliminar.value = null
}

const onProductoChange = () => {
  const producto = productos.value.find(p => p.id === productoSeleccionado.value)
  if (producto) {
    // IMPORTANTE: Usar precio_total que ya viene calculado desde el backend
    // precio_total = precio (que ya incluye IVA)
    precioProducto.value = parseFloat(producto.precio_total || producto.precio || 0)
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

  const metodoPago = metodosPago.value.find(m => m.id === pagoActual.value.metodo_pago_id)
  const esCheque = metodoPago?.nombre === 'Cheque'

  // Si es cheque, abrir modal para capturar datos
  if (esCheque) {
    // Calcular fecha de cobro por defecto (+30 días)
    const fechaCobro = new Date()
    fechaCobro.setDate(fechaCobro.getDate() + 30)
    datosCheque.value.fecha_cobro = fechaCobro.toISOString().split('T')[0]
    
    dialogCheque.value = true
    return
  }

  // Si NO es cheque, agregar directamente
  finalizarAgregarPago()
}

const finalizarAgregarPago = () => {
  const metodoPago = metodosPago.value.find(m => m.id === pagoActual.value.metodo_pago_id)
  const esCheque = metodoPago?.nombre === 'Cheque'

  const pagoData = {
    metodo_pago_id: pagoActual.value.metodo_pago_id,
    monto: parseFloat(pagoActual.value.monto),
    metodo_nombre: metodoPago?.nombre,
  }

  // Si es cheque, agregar datos del cheque
  if (esCheque) {
    pagoData.estado_cheque = 'pendiente'
    pagoData.numero_cheque = datosCheque.value.numero_cheque
    pagoData.fecha_cheque = datosCheque.value.fecha_cheque
    pagoData.fecha_cobro = datosCheque.value.fecha_cobro
    pagoData.observaciones_cheque = datosCheque.value.observaciones_cheque
  }

  venta.value.pagos.push(pagoData)

  // Limpiar pago actual
  pagoActual.value = {
    metodo_pago_id: null,
    monto: 0,
  }

  // Limpiar datos de cheque
  datosCheque.value = {
    numero_cheque: '',
    fecha_cheque: new Date().toISOString().split('T')[0],
    fecha_cobro: null,
    observaciones_cheque: '',
  }

  // Cerrar modal
  dialogCheque.value = false

  toast.success('Pago agregado')
}

const cancelarCheque = () => {
  dialogCheque.value = false
  datosCheque.value = {
    numero_cheque: '',
    fecha_cheque: new Date().toISOString().split('T')[0],
    fecha_cobro: null,
    observaciones_cheque: '',
  }
}

const eliminarPago = (index) => {
  pagoAEliminar.value = index
  dialogEliminarPago.value = true
}

const confirmarEliminarPago = () => {
  if (pagoAEliminar.value !== null) {
    venta.value.pagos.splice(pagoAEliminar.value, 1)
    toast.info('Pago eliminado')
  }
  dialogEliminarPago.value = false
  pagoAEliminar.value = null
}

const cancelarEliminarPago = () => {
  dialogEliminarPago.value = false
  pagoAEliminar.value = null
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

  // Establecer el monto al total pendiente
  pagoActual.value.monto = saldoPendiente.value

  const metodoPago = metodosPago.value.find(m => m.id === pagoActual.value.metodo_pago_id)
  const esCheque = metodoPago?.nombre === 'Cheque'

  // Si es cheque, abrir modal para capturar datos
  if (esCheque) {
    // Calcular fecha de cobro por defecto (+30 días)
    const fechaCobro = new Date()
    fechaCobro.setDate(fechaCobro.getDate() + 30)
    datosCheque.value.fecha_cobro = fechaCobro.toISOString().split('T')[0]
    
    dialogCheque.value = true
    return
  }

  // Si NO es cheque, agregar directamente
  finalizarAgregarPago()
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

  // Validar facturación si se requiere
  if (venta.value.requiere_factura) {
    if (!venta.value.tipo_comprobante) {
      toast.warning('Debe especificar el tipo de comprobante')
      return
    }
    // El número se genera automáticamente, no validar
  }

  // Validar pagos según si tiene cuenta corriente
  // Tolerancia de 0.01 para evitar errores de redondeo
  const tolerancia = 0.01
  if (!tieneCuentaCorriente.value && saldoPendiente.value > tolerancia) {
    toast.warning('El cliente no tiene cuenta corriente. Debe pagar el total de la venta.')
    return
  }

  // Solo validar límite de crédito si efectivamente queda saldo pendiente
  if (tieneCuentaCorriente.value && saldoPendiente.value > tolerancia) {
    if (saldoPendiente.value > creditoDisponible.value) {
      toast.warning(`El saldo pendiente (${formatPrice(saldoPendiente.value)}) supera el crédito disponible (${formatPrice(creditoDisponible.value)})`)
      return
    }
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
        // Campos de cheque (si existen)
        numero_cheque: p.numero_cheque,
        fecha_cheque: p.fecha_cheque,
        fecha_cobro: p.fecha_cobro,
        observaciones_cheque: p.observaciones_cheque,
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

// Recargar clientes cuando el componente se activa (útil después de eliminar ventas en otra pestaña)
onActivated(async () => {
  await fetchClientes()
})

// Cargar pedidos pendientes cuando cambie el cliente (SOLO si el usuario lo solicita)
// Ya no se carga automáticamente
// watch(() => venta.value.cliente_id, (newVal) => {
//   if (newVal) {
//     fetchPedidosPendientes(newVal)
//   } else {
//     pedidosPendientes.value = []
//   }
// })

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
            <VCard variant="outlined" class="mb-3" style="position: relative; z-index: 200; overflow: visible;">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-user-line</VIcon>
                Información del Cliente
              </VCardTitle>
              <VCardText class="pa-3" style="overflow: visible; min-height: 250px;">
                <div style="position: relative; overflow: visible; z-index: 1000;">
                  <VTextField
                    v-model="busquedaCliente"
                    label="Buscar cliente por nombre, email o CUIT*"
                    prepend-inner-icon="ri-user-search-line"
                    density="compact"
                    variant="outlined"
                    clearable
                    @input="mostrarResultadosCliente = true"
                    @blur="setTimeout(() => mostrarResultadosCliente = false, 200)"
                    @focus="busquedaCliente && (mostrarResultadosCliente = true)"
                    @click:clear="venta.cliente_id = null; mostrarResultadosCliente = false; busquedaCliente = ''"
                  />
                  
                  <!-- Dropdown de resultados de clientes -->
                  <VCard
                    v-if="mostrarResultadosCliente && busquedaCliente && busquedaCliente.trim().length > 0 && clientesFiltrados.length > 0"
                    class="position-absolute elevation-8"
                    style="top: 100%; left: 0; right: 0; z-index: 10000; max-height: 350px; overflow-y: auto; width: 100%; margin-top: 4px;"
                  >
                    <VList density="compact">
                      <VListItem
                        v-for="cliente in clientesFiltrados"
                        :key="cliente.id"
                        @click="seleccionarCliente(cliente)"
                        class="cursor-pointer"
                        :active="venta.cliente_id === cliente.id"
                      >
                        <VListItemTitle>{{ cliente.nombre }} {{ cliente.apellido }}</VListItemTitle>
                        <VListItemSubtitle>
                          CUIT: {{ cliente.cuit || 'N/A' }} | Crédito: {{ formatPrice(cliente.limite_credito) }}
                          <br>Email: {{ cliente.email }} | Tel: {{ cliente.telefono }}
                        </VListItemSubtitle>
                      </VListItem>
                    </VList>
                  </VCard>
                  
                  <!-- Mensaje si no hay resultados -->
                  <VCard
                    v-if="mostrarResultadosCliente && busquedaCliente && busquedaCliente.trim().length > 0 && clientesFiltrados.length === 0"
                    class="position-absolute elevation-4"
                    style="top: 100%; left: 0; right: 0; z-index: 10000; width: 100%; margin-top: 4px;"
                  >
                    <VCardText class="text-center text-grey pa-3">
                      <VIcon>ri-search-line</VIcon>
                      <div class="text-body-2 mt-1">No se encontraron clientes</div>
                    </VCardText>
                  </VCard>
                </div>

                <div v-if="clienteSeleccionado" class="mt-3 pa-3 bg-surface rounded">
                  <VRow dense align="center">
                    <VCol cols="12" md="3">
                      <div class="text-caption text-medium-emphasis">Email</div>
                      <div class="text-body-2 font-weight-medium">{{ clienteSeleccionado.email }}</div>
                    </VCol>
                    <VCol cols="12" md="3">
                      <div class="text-caption text-medium-emphasis">Teléfono</div>
                      <div class="text-body-2 font-weight-medium">{{ clienteSeleccionado.telefono }}</div>
                    </VCol>
                    <VCol cols="12" md="3">
                      <div class="text-caption text-medium-emphasis">Límite de Crédito</div>
                      <div class="text-body-2 font-weight-medium text-success">{{ formatPrice(clienteSeleccionado.limite_credito) }}</div>
                    </VCol>
                    <VCol cols="12" md="3" class="text-right">
                      <VBtn
                        v-if="!venta.pedido_id"
                        color="warning"
                        variant="outlined"
                        size="small"
                        @click="fetchPedidosPendientes(venta.cliente_id); mostrarPedidos = 0"
                      >
                        <VIcon size="18" class="mr-1">ri-file-list-3-line</VIcon>
                        Ver Pedidos Pendientes
                      </VBtn>
                      <VChip v-else color="success" variant="flat" size="small" closable @click:close="descargarPedido">
                        <VIcon size="16" class="mr-1">ri-checkbox-circle-line</VIcon>
                        Desde Pedido #{{ venta.pedido_id }}
                      </VChip>
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
            <VCard variant="outlined" class="mb-6" style="position: relative; z-index: 100;">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-add-box-line</VIcon>
                Agregar Productos
              </VCardTitle>
              <VCardText class="pa-3 pb-6" style="overflow: visible; min-height: 400px;">
                <VRow style="overflow: visible;">
                  <VCol cols="12" md="5" style="position: relative; overflow: visible;">
                    <div style="position: relative;">
                      <!-- Campo de búsqueda con autocompletado predictivo -->
                        <VTextField
                          v-model="busquedaProducto"
                          label="Buscar producto por nombre o código"
                          prepend-inner-icon="ri-search-line"
                          density="compact"
                          variant="outlined"
                          clearable
                          @input="mostrarResultadosProducto = true"
                          @blur="setTimeout(() => mostrarResultadosProducto = false, 200)"
                          @focus="busquedaProducto && (mostrarResultadosProducto = true)"
                          @click:clear="productoSeleccionado = null; precioProducto = 0; mostrarResultadosProducto = false; busquedaProducto = ''"
                        />                      <!-- Dropdown de resultados predictivos -->
                      <VCard
                        v-if="mostrarResultadosProducto && busquedaProducto && busquedaProducto.trim().length > 0 && productosFiltrados.length > 0"
                        class="position-absolute"
                        style="top: 100%; left: 0; right: 0; z-index: 9999; max-height: 300px; overflow-y: auto; width: 100%;"
                        elevation="3"
                      >
                        <VList density="compact">
                          <VListItem
                            v-for="producto in productosFiltrados"
                            :key="producto.id"
                            @click="seleccionarProducto(producto)"
                            class="cursor-pointer"
                            :active="productoSeleccionado === producto.id"
                          >
                            <VListItemTitle>{{ producto.nombre }}</VListItemTitle>
                            <VListItemSubtitle>
                              Código: {{ producto.codigo }} | Precio: {{ formatPrice(producto.precio) }}
                            </VListItemSubtitle>
                          </VListItem>
                        </VList>
                      </VCard>
                      
                      <!-- Mensaje si no hay resultados -->
                      <VCard
                        v-if="mostrarResultadosProducto && busquedaProducto && busquedaProducto.length > 2 && productosFiltrados.length === 0"
                        class="position-absolute"
                        style="top: 100%; left: 0; right: 0; z-index: 9999; width: 100%;"
                        elevation="3"
                      >
                        <VCardText class="text-center text-grey pa-3">
                          <VIcon>ri-search-line</VIcon>
                          No se encontraron productos
                        </VCardText>
                      </VCard>
                    </div>
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
                    <NumberInput
                      v-model="precioProducto"
                      label="Precio Final (con IVA)"
                      prefix="$ "
                      prepend-inner-icon="ri-money-dollar-circle-line"
                      density="compact"
                      variant="outlined"
                      readonly
                      hint="Precio del producto con IVA incluido"
                      persistent-hint
                      :disabled="!productoSeleccionado"
                    />
                  </VCol>
                  <VCol cols="12" md="2">
                    <VBtn
                      block
                      color="primary"
                      @click="agregarProducto"
                      :disabled="!productoSeleccionado"
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
          </VCardTitle>
          <VCardText class="pa-3 pt-1">
            <VRow dense>
              <!-- Checkbox para decidir si requiere factura -->
              <VCol cols="12">
                <VCheckbox
                  v-model="venta.requiere_factura"
                  label="Esta venta requiere factura"
                  color="primary"
                  density="compact"
                  hide-details
                />
              </VCol>
              
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

        <!-- Facturación (solo si se marca como requiere factura) -->
        <VCard v-if="venta.requiere_factura" variant="outlined" class="mb-3">
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
                    <NumberInput
                      v-model="pagoActual.monto"
                      label="Monto del Pago"
                      prefix="$ "
                      variant="outlined"
                      density="comfortable"
                      prepend-inner-icon="ri-money-dollar-circle-line"
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
                      <VAvatar 
                        :color="pago.estado_cheque === 'pendiente' ? 'warning' : 'success'" 
                        variant="tonal" 
                        size="48"
                      >
                        <VIcon size="24">
                          {{ pago.estado_cheque ? 'ri-bank-card-line' : 'ri-money-dollar-circle-fill' }}
                        </VIcon>
                      </VAvatar>
                    </template>
                    
                    <VListItemTitle class="font-weight-medium text-body-1 mb-1">
                      {{ pago.metodo_nombre }}
                      <VChip 
                        v-if="pago.estado_cheque === 'pendiente'" 
                        size="x-small" 
                        color="warning" 
                        class="ml-2"
                      >
                        Pendiente
                      </VChip>
                    </VListItemTitle>
                    <VListItemSubtitle>
                      <div class="d-flex align-center mb-1">
                        <VIcon size="16" class="mr-1">ri-price-tag-3-line</VIcon>
                        <span class="text-success font-weight-bold">{{ formatPrice(pago.monto) }}</span>
                      </div>
                      <div v-if="pago.numero_cheque" class="text-caption">
                        <VIcon size="14" class="mr-1">ri-hashtag</VIcon>
                        Cheque Nº {{ pago.numero_cheque }}
                        <span v-if="pago.fecha_cobro" class="ml-2">
                          <VIcon size="14" class="mr-1">ri-calendar-check-line</VIcon>
                          Cobro: {{ formatDate(pago.fecha_cobro) }}
                        </span>
                      </div>
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
                v-if="superaLimiteCredito"
                type="error"
                variant="tonal"
                density="comfortable"
              >
                <template #prepend>
                  <VIcon>ri-error-warning-line</VIcon>
                </template>
                <div class="text-body-2">
                  <strong>Límite de Crédito Excedido:</strong> Los pagos registrados en Cuenta Corriente ({{ formatPrice(montoEnCuentaCorriente) }}) sumados al saldo actual ({{ formatPrice(clienteSeleccionado?.saldo_actual || 0) }}) superan el límite de crédito ({{ formatPrice(clienteSeleccionado?.limite_credito || 0) }}).
                </div>
              </VAlert>
              
              <VAlert
                v-if="saldoPendiente > 0.01 && !tieneCuentaCorriente"
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
                v-if="saldoPendiente <= 0.01 && venta.pagos.length > 0"
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
          :disabled="loading || venta.productos.length === 0 || !venta.cliente_id || superaLimiteCredito"
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

    <!-- Modal de Datos de Cheque -->
    <VDialog v-model="dialogCheque" max-width="600" persistent>
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center bg-primary pa-4">
          <div class="d-flex align-center ga-2">
            <VIcon color="white" size="28">ri-bank-card-line</VIcon>
            <span class="text-h6 text-white">Datos del Cheque</span>
          </div>
          <VBtn
            icon
            variant="text"
            color="white"
            size="small"
            @click="cancelarCheque"
          >
            <VIcon>ri-close-line</VIcon>
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-6">
          <VAlert type="info" variant="tonal" class="mb-4">
            <VIcon>ri-information-line</VIcon>
            Complete los datos del cheque para un mejor seguimiento
          </VAlert>

          <VRow>
            <!-- Monto del cheque (readonly, ya establecido) -->
            <VCol cols="12">
              <VTextField
                :model-value="formatPrice(pagoActual.monto)"
                label="Monto del Cheque"
                prepend-inner-icon="ri-money-dollar-circle-line"
                readonly
                variant="filled"
                color="primary"
                class="text-h6"
              />
            </VCol>

            <!-- Número de Cheque -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model="datosCheque.numero_cheque"
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
                v-model="datosCheque.fecha_cheque"
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
                v-model="datosCheque.fecha_cobro"
                label="Fecha de Cobro Estimada *"
                type="date"
                prepend-inner-icon="ri-calendar-check-line"
                variant="outlined"
                hint="Fecha estimada en que se podrá cobrar el cheque"
                persistent-hint
                :rules="[v => !!v || 'Fecha de cobro requerida']"
              />
            </VCol>

            <!-- Observaciones -->
            <VCol cols="12">
              <VTextarea
                v-model="datosCheque.observaciones_cheque"
                label="Observaciones (opcional)"
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
            @click="cancelarCheque"
          >
            <VIcon class="mr-2">ri-close-line</VIcon>
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            @click="finalizarAgregarPago"
            :disabled="!datosCheque.numero_cheque || !datosCheque.fecha_cheque || !datosCheque.fecha_cobro"
          >
            <VIcon class="mr-2">ri-check-line</VIcon>
            Agregar Cheque
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo de confirmación para eliminar producto -->
    <VDialog v-model="dialogEliminarProducto" max-width="500px">
      <VCard>
        <VCardTitle class="text-h5">
          ¿Está seguro de eliminar este producto?
        </VCardTitle>
        <VCardText>
          Esta acción no se puede deshacer.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="cancelarEliminarProducto">
            Cancelar
          </VBtn>
          <VBtn color="error" variant="text" @click="confirmarEliminarProducto">
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo de confirmación para eliminar pago -->
    <VDialog v-model="dialogEliminarPago" max-width="500px">
      <VCard>
        <VCardTitle class="text-h5">
          ¿Está seguro de eliminar este pago?
        </VCardTitle>
        <VCardText>
          Esta acción no se puede deshacer.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="cancelarEliminarPago">
            Cancelar
          </VBtn>
          <VBtn color="error" variant="text" @click="confirmarEliminarPago">
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
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

/* Estilos para búsqueda de productos */
.cursor-pointer {
  cursor: pointer;
}

.position-relative {
  position: relative;
}

.position-absolute {
  position: absolute;
}

.w-100 {
  width: 100%;
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
