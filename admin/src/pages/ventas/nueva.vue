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
  metodo_pago_id: null,
  estado_pago: 'pendiente',
  productos: [],
  observaciones: '',
  pedido_id: null, // Para vincular con pedido si viene de uno
})

const productoSeleccionado = ref(null)
const cantidadProducto = ref(1)
const precioProducto = ref(0)
const pedidosPendientes = ref([])
const mostrarPedidos = ref(false)

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

const clienteSeleccionado = computed(() => {
  return clientes.value.find(c => c.id === venta.value.cliente_id)
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

  if (!venta.value.metodo_pago_id) {
    toast.warning('Debe seleccionar un método de pago')
    return
  }

  try {
    loading.value = true
    error.value = ''

    const dataToSend = {
      ...venta.value,
      total: totalCalculado.value,
      items: venta.value.productos,
    }
    delete dataToSend.productos

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
      <VCol cols="12" lg="8">
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
      <VCol cols="12" lg="4">
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

        <!-- Detalles de Pago -->
        <VCard variant="outlined" class="mb-3">
          <VCardTitle class="text-body-1 pa-3 pb-2">
            <VIcon class="mr-2" size="18">ri-calendar-check-line</VIcon>
            Detalles de Pago
          </VCardTitle>
          <VCardText class="pa-3 pt-1">
            <VTextField
              v-model="venta.fecha"
              label="Fecha de Venta*"
              type="date"
              required
              prepend-inner-icon="ri-calendar-line"
              variant="outlined"
              density="compact"
              class="mb-3"
              hide-details
            />

            <VSelect
              v-model="venta.metodo_pago_id"
              :items="metodosPago"
              item-title="nombre"
              item-value="id"
              label="Método de Pago*"
              required
              prepend-inner-icon="ri-money-dollar-circle-line"
              variant="outlined"
              density="compact"
              class="mb-3"
              hide-details
            />

            <VSelect
              v-model="venta.estado_pago"
              :items="[
                { value: 'pendiente', title: 'Pendiente' },
                { value: 'pagado', title: 'Pagado' },
                { value: 'parcial', title: 'Parcial' }
              ]"
              label="Estado de Pago*"
              required
              prepend-inner-icon="ri-checkbox-circle-line"
              variant="outlined"
              density="compact"
              hide-details
            />
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
}

.bg-primary {
  background-color: rgb(var(--v-theme-primary));
  color: white;
}

.bg-warning {
  background-color: rgb(var(--v-theme-warning));
  color: white;
}
</style>
