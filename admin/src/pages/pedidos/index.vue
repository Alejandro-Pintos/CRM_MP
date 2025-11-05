<script setup>
import { ref, onMounted, computed } from 'vue'
import { getPedidos, createPedido, updatePedido, deletePedido, getClimaLocal } from '@/services/pedidos'
import { getClientes } from '@/services/clientes'
import { getProductos } from '@/services/productos'
import { toast } from '@/plugins/toast'

const pedidos = ref([])
const clientes = ref([])
const productos = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const dialogDelete = ref(false)
const editedIndex = ref(-1)
const loadingClima = ref(false)
const climaInfo = ref(null)
const pronosticoExtendido = ref([])
const search = ref('')
const filtroEstado = ref('')
const busquedaProductos = ref({}) // Objeto para guardar búsqueda por item index

// Función para obtener valores por defecto del formulario
const getDefaultItem = () => ({
  id: null,
  cliente_id: null,
  fecha_pedido: new Date().toISOString().split('T')[0],
  fecha_entrega_aprox: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  fecha_despacho: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  estado: 'pendiente',
  direccion_entrega: '',
  ciudad_entrega: '',
  observaciones: '',
  clima_estado: null,
  clima_temperatura: null,
  clima_humedad: null,
  clima_descripcion: null,
  clima_json: null,
  pronostico_extendido: null,
  items: [],
})

const editedItem = ref(getDefaultItem())

const defaultItem = getDefaultItem()

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Cliente', key: 'cliente_nombre', sortable: false },
  { title: 'Fecha Pedido', key: 'fecha_pedido' },
  { title: 'Fecha Entrega', key: 'fecha_entrega_aprox' },
  { title: 'Clima', key: 'clima', sortable: false },
  { title: 'Estado', key: 'estado' },
  { title: 'Venta', key: 'venta_id' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const fetchPedidos = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await getPedidos()
    if (response.data && Array.isArray(response.data)) {
      pedidos.value = response.data
    } else if (Array.isArray(response)) {
      pedidos.value = response
    } else {
      pedidos.value = []
    }
  } catch (e) {
    const errorMsg = e.message || 'Error al cargar pedidos'
    error.value = errorMsg
    toast.error(errorMsg)
  } finally {
    loading.value = false
  }
}

const fetchClientes = async () => {
  try {
    const response = await getClientes()
    if (response.data && Array.isArray(response.data)) {
      clientes.value = response.data
    } else if (Array.isArray(response)) {
      clientes.value = response
    }
  } catch (e) {
    toast.error('Error al cargar clientes')
    console.error('Error al cargar clientes:', e)
  }
}

const fetchProductos = async () => {
  try {
    const response = await getProductos()
    if (response.data && Array.isArray(response.data)) {
      productos.value = response.data
    } else if (Array.isArray(response)) {
      productos.value = response
    }
  } catch (e) {
    toast.error('Error al cargar productos')
    console.error('Error al cargar productos:', e)
  }
}

// Filtrar productos por búsqueda (nombre o código)
const productosFiltrados = computed(() => {
  return (index) => {
    const busqueda = busquedaProductos.value[index]
    if (!busqueda || busqueda.trim() === '') {
      return productos.value
    }
    
    const searchLower = busqueda.toLowerCase().trim()
    return productos.value.filter(p => {
      const nombre = (p.nombre || '').toLowerCase()
      const codigo = (p.codigo || '').toLowerCase()
      return nombre.includes(searchLower) || codigo.includes(searchLower)
    })
  }
})

// Filtrar pedidos por búsqueda y estado
const pedidosFiltrados = computed(() => {
  let resultado = pedidos.value
  
  // Filtrar por estado
  if (filtroEstado.value) {
    resultado = resultado.filter(pedido => pedido.estado === filtroEstado.value)
  }
  
  // Filtrar por búsqueda
  if (search.value) {
    const searchLower = search.value.toLowerCase()
    resultado = resultado.filter(pedido => {
      const cliente = (pedido.cliente_nombre || '').toLowerCase()
      const id = String(pedido.id || '')
      const ciudad = (pedido.ciudad_entrega || '').toLowerCase()
      
      return cliente.includes(searchLower) ||
             id.includes(searchLower) ||
             ciudad.includes(searchLower)
    })
  }
  
  return resultado
})

const editItem = (item) => {
  editedIndex.value = pedidos.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  
  // Cargar items si existen
  if (item.items && Array.isArray(item.items)) {
    editedItem.value.items = item.items.map(i => ({
      producto_id: i.producto_id,
      cantidad: i.cantidad,
      precio_unitario: i.precio_unitario,
      observaciones: i.observaciones || '',
    }))
  }
  
  // Cargar info de clima si existe
  if (item.clima) {
    climaInfo.value = item.clima
  }
  
  dialog.value = true
}

const openNewPedido = async () => {
  // Reiniciar el formulario con valores por defecto
  editedItem.value = getDefaultItem()
  editedIndex.value = -1
  climaInfo.value = null
  pronosticoExtendido.value = []
  error.value = ''
  
  dialog.value = true
  
  // Obtener clima automáticamente al crear un nuevo pedido
  setTimeout(() => {
    obtenerClima()
  }, 500)
}

const deleteItem = (item) => {
  editedIndex.value = pedidos.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialogDelete.value = true
}

const deleteItemConfirm = async () => {
  try {
    await deletePedido(editedItem.value.id)
    toast.success('Pedido eliminado correctamente')
    closeDelete()
    await fetchPedidos() // Refrescar lista después de eliminar
  } catch (e) {
    const errorMsg = e.message || 'Error al eliminar pedido'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const close = () => {
  dialog.value = false
  error.value = ''
  climaInfo.value = null
  pronosticoExtendido.value = []
  setTimeout(() => {
    editedItem.value = getDefaultItem()
    editedIndex.value = -1
  }, 300)
}

const closeDelete = () => {
  dialogDelete.value = false
  error.value = ''
  setTimeout(() => {
    editedItem.value = getDefaultItem()
    editedIndex.value = -1
  }, 300)
}

const agregarItem = () => {
  editedItem.value.items.push({
    producto_id: null,
    cantidad: 1,
    precio_compra: 0,
    precio_venta: 0,
    porcentaje_iva: 0,
    precio_unitario: 0,
    observaciones: '',
    mostrarResultados: false,
  })
}

const eliminarItem = (index) => {
  editedItem.value.items.splice(index, 1)
}

const seleccionarProducto = (item, producto, index) => {
  // Asignar el producto seleccionado
  item.producto_id = producto.id
  
  // Asignar precios del producto
  item.precio_compra = parseFloat(producto.precio_compra || 0)
  item.precio_venta = parseFloat(producto.precio_venta || 0)
  item.porcentaje_iva = parseFloat(producto.porcentaje_iva || 0)
  
  // El precio unitario es el precio de venta (lo que se cobra al cliente)
  item.precio_unitario = item.precio_venta
  
  // Actualizar campo de búsqueda con el nombre del producto
  busquedaProductos.value[index] = `${producto.codigo} - ${producto.nombre}`
  
  // Cerrar la lista de resultados
  item.mostrarResultados = false
}

const actualizarPrecio = (item) => {
  // Si se modifica manualmente el precio de venta, actualizar el precio unitario
  if (item.precio_venta) {
    item.precio_unitario = parseFloat(item.precio_venta)
  }
}

// Calcular total del producto (cantidad × precio_venta)
const calcularTotalProducto = (item) => {
  const cantidad = parseFloat(item.cantidad || 0)
  const precioVenta = parseFloat(item.precio_venta || 0)
  
  // El total es simplemente cantidad × precio de venta (que ya incluye ganancia + IVA)
  return cantidad * precioVenta
}

// Calcular subtotal sin IVA (para información)
const calcularSubtotalSinIVA = (item) => {
  const precioVenta = parseFloat(item.precio_venta || 0)
  const porcentajeIva = parseFloat(item.porcentaje_iva || 0)
  
  // Calcular el precio sin IVA
  const precioSinIVA = precioVenta / (1 + porcentajeIva / 100)
  
  return precioSinIVA
}

const totalPedido = computed(() => {
  return editedItem.value.items.reduce((sum, item) => {
    return sum + (item.cantidad * item.precio_unitario)
  }, 0)
})

const obtenerClima = async () => {
  loadingClima.value = true
  error.value = ''
  
  try {
    const data = await getClimaLocal()
    
    // Guardar datos del clima actual en el pedido
    if (data.clima_actual) {
      editedItem.value.clima_estado = data.clima_actual.clima_estado
      editedItem.value.clima_temperatura = data.clima_actual.clima_temperatura
      editedItem.value.clima_humedad = data.clima_actual.clima_humedad
      editedItem.value.clima_descripcion = data.clima_actual.clima_descripcion
    }
    
    // Guardar pronóstico extendido
    if (data.pronostico && data.pronostico.length > 0) {
      editedItem.value.pronostico_extendido = JSON.stringify(data.pronostico)
      pronosticoExtendido.value = data.pronostico
    }
    
    // Si la ciudad no está definida, usar la detectada por el clima
    if (!editedItem.value.ciudad_entrega && data.ciudad) {
      editedItem.value.ciudad_entrega = data.ciudad
    }
    
    // Verificar si hay un error en la respuesta de la API
    if (data.error || (data.clima_actual && (data.clima_actual.clima_estado === 'Error' || data.clima_actual.clima_estado === 'Desconocido'))) {
      // Mostrar advertencia pero no bloquear
      error.value = data.error || data.clima_actual?.clima_descripcion || 'API de clima no configurada'
      
      // Mostrar info visual con el error
      climaInfo.value = {
        estado: data.clima_actual?.clima_estado || 'Desconocido',
        temperatura: null,
        humedad: null,
        descripcion: data.clima_actual?.clima_descripcion || 'No disponible',
        ciudad: data.ciudad || 'Ubicación detectada',
        icono: '⚠️',
        isError: true,
      }
      pronosticoExtendido.value = []
    } else {
      // Mostrar info visual con datos reales
      climaInfo.value = {
        estado: data.clima_actual.clima_estado,
        temperatura: data.clima_actual.clima_temperatura,
        humedad: data.clima_actual.clima_humedad,
        descripcion: data.clima_actual.clima_descripcion,
        ciudad: data.ciudad,
        icono: getClimaIcono(data.clima_actual.clima_estado),
        isError: false,
      }
    }
  } catch (e) {
    const errorMsg = e.message || 'Error al obtener el pronóstico. Verifica los permisos de ubicación.'
    error.value = errorMsg
    toast.error(errorMsg)
    
    // Mostrar info de error
    climaInfo.value = {
      estado: 'Error',
      temperatura: null,
      humedad: null,
      descripcion: e.message,
      ciudad: null,
      icono: '❌',
      isError: true,
    }
    pronosticoExtendido.value = []
  } finally {
    loadingClima.value = false
  }
}

// Traducir el estado del clima de inglés a español
const traducirClima = (estado) => {
  if (!estado) return ''
  const traducciones = {
    'Clear': 'Despejado',
    'Clouds': 'Nublado',
    'Rain': 'Lluvia',
    'Drizzle': 'Llovizna',
    'Snow': 'Nieve',
    'Thunderstorm': 'Tormenta',
    'Mist': 'Niebla',
    'Fog': 'Niebla',
    'Haze': 'Neblina',
    'Smoke': 'Humo',
    'Dust': 'Polvo',
    'Sand': 'Arena',
    'Ash': 'Ceniza',
    'Squall': 'Chubasco',
    'Tornado': 'Tornado'
  }
  return traducciones[estado] || estado
}

// Obtener icono de Material Design según el estado del clima (sin hardcode de emojis)
const getClimaIcono = (estado) => {
  const estadoTraducido = traducirClima(estado)
  const e = (estadoTraducido || '').toLowerCase()
  if (e.includes('despejado') || e.includes('sol')) return 'mdi-weather-sunny'
  if (e.includes('parcialmente')) return 'mdi-weather-partly-cloudy'
  if (e.includes('nublado') || e.includes('nubl')) return 'mdi-weather-cloudy'
  if (e.includes('lluvia')) return 'mdi-weather-rainy'
  if (e.includes('tormenta')) return 'mdi-weather-lightning'
  if (e.includes('nieve')) return 'mdi-weather-snowy'
  if (e.includes('niebla') || e.includes('neblina')) return 'mdi-weather-fog'
  if (e.includes('llovizna')) return 'mdi-weather-partly-rainy'
  return 'mdi-weather-partly-cloudy'
}

// Obtener color de Vuetify según el estado del clima (sin hardcode)
const getClimaColor = (estado) => {
  const estadoTraducido = traducirClima(estado)
  const e = (estadoTraducido || '').toLowerCase()
  if (e.includes('despejado') || e.includes('sol')) return 'warning'
  if (e.includes('lluvia') || e.includes('tormenta')) return 'primary'
  if (e.includes('nieve')) return 'info'
  if (e.includes('niebla') || e.includes('neblina')) return 'secondary'
  return 'info'
}

const autocargarDatosCliente = () => {
  const cliente = clientes.value.find(c => c.id === editedItem.value.cliente_id)
  if (cliente) {
    editedItem.value.direccion_entrega = cliente.direccion || ''
    editedItem.value.ciudad_entrega = cliente.ciudad || ''
  }
}

const save = async () => {
  // Validaciones
  if (!editedItem.value.cliente_id) {
    error.value = 'Debe seleccionar un cliente'
    toast.warning('Debe seleccionar un cliente')
    return
  }
  
  if (editedItem.value.items.length === 0) {
    error.value = 'Debe agregar al menos un producto al pedido'
    toast.warning('Debe agregar al menos un producto al pedido')
    return
  }
  
  try {
    error.value = ''
    
    if (editedIndex.value > -1) {
      // Actualizar
      const updated = await updatePedido(editedItem.value.id, editedItem.value)
      Object.assign(pedidos.value[editedIndex.value], updated)
      toast.success('Pedido actualizado correctamente')
    } else {
      // Crear
      await createPedido(editedItem.value)
      toast.success('Pedido creado correctamente')
    }
    close()
    await fetchPedidos() // Refrescar lista
  } catch (e) {
    const errorMsg = e.message || 'Error al guardar pedido'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS'
  }).format(value)
}

const getEstadoColor = (estado) => {
  const colores = {
    pendiente: 'warning',
    en_proceso: 'info',
    entregado: 'success',
    cancelado: 'error',
  }
  return colores[estado] || 'default'
}

onMounted(() => {
  fetchPedidos()
  fetchClientes()
  fetchProductos()
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center flex-wrap ga-4">
          <span class="text-h5">Pedidos</span>
          <div class="d-flex ga-2 align-center flex-wrap">
            <VSelect
              v-model="filtroEstado"
              :items="[
                { title: 'Todos', value: '' },
                { title: 'Pendiente', value: 'pendiente' },
                { title: 'En proceso', value: 'en_proceso' },
                { title: 'Entregado', value: 'entregado' },
                { title: 'Cancelado', value: 'cancelado' }
              ]"
              label="Estado"
              density="compact"
              hide-details
              style="min-width: 150px;"
              clearable
            />
            <VTextField
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Buscar pedidos"
              single-line
              hide-details
              density="compact"
              style="min-width: 250px;"
              clearable
            />
            <VBtn color="primary" @click="openNewPedido">
              <VIcon start>mdi-plus</VIcon>
              Nuevo Pedido
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
          :items="pedidosFiltrados"
          :loading="loading"
          loading-text="Cargando pedidos..."
          no-data-text="No hay pedidos registrados"
          class="elevation-1"
        >
          <template #item.cliente_nombre="{ item }">
            {{ item.cliente?.nombre }} {{ item.cliente?.apellido }}
          </template>
          
          <template #item.fecha_pedido="{ item }">
            {{ new Date(item.fecha_pedido).toLocaleDateString('es-AR') }}
          </template>
          
          <template #item.fecha_entrega_aprox="{ item }">
            {{ item.fecha_entrega_aprox ? new Date(item.fecha_entrega_aprox).toLocaleDateString('es-AR') : '-' }}
          </template>
          
          <template #item.clima="{ item }">
            <div v-if="item.clima?.estado" class="d-flex align-center ga-2">
              <span style="font-size: 24px;">{{ item.clima.icono || '🌤️' }}</span>
              <div>
                <div>{{ traducirClima(item.clima.estado) }}</div>
                <div class="text-caption">{{ item.clima.temperatura }}°C</div>
              </div>
            </div>
            <span v-else class="text-disabled">Sin datos</span>
          </template>
          
          <template #item.estado="{ item }">
            <VChip :color="getEstadoColor(item.estado)" size="small">
              {{ item.estado }}
            </VChip>
          </template>
          
          <template #item.venta_id="{ item }">
            <VChip v-if="item.venta_id" color="success" size="small">
              #{{ item.venta_id }}
            </VChip>
            <span v-else class="text-disabled">Sin venta</span>
          </template>
          
          <template #item.actions="{ item }">
            <div class="d-flex ga-2">
              <VBtn
                v-if="!item.venta_id && (item.estado === 'pendiente' || item.estado === 'en_proceso')"
                icon
                size="small"
                color="success"
                variant="tonal"
                :to="`/ventas/nueva?pedido_id=${item.id}`"
                title="Crear venta desde pedido"
              >
                <VIcon>ri-shopping-cart-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="primary"
                variant="tonal"
                @click="editItem(item)"
                title="Editar pedido"
              >
                <VIcon>ri-pencil-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="deleteItem(item)"
                title="Eliminar pedido"
              >
                <VIcon>ri-delete-bin-6-line</VIcon>
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Dialog para crear/editar -->
    <VDialog v-model="dialog" max-width="900px" scrollable>
      <VCard>
        <VCardTitle>
          <span class="text-h5">{{ editedIndex === -1 ? 'Nuevo' : 'Editar' }} Pedido</span>
        </VCardTitle>

        <VCardText style="max-height: 600px;">
          <VContainer>
            <VRow>
              <!-- Cliente -->
              <VCol cols="12" md="6">
                <VSelect
                  v-model="editedItem.cliente_id"
                  :items="clientes"
                  item-title="nombre"
                  item-value="id"
                  label="Cliente*"
                  required
                  @update:model-value="autocargarDatosCliente"
                >
                  <template #item="{ props, item }">
                    <VListItem v-bind="props">
                      <VListItemTitle>{{ item.raw.nombre }} {{ item.raw.apellido }}</VListItemTitle>
                      <VListItemSubtitle>{{ item.raw.ciudad }}</VListItemSubtitle>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>

              <!-- Fecha Pedido -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.fecha_pedido"
                  label="Fecha Pedido*"
                  type="date"
                  required
                />
              </VCol>

              <!-- Fecha Entrega Aproximada -->
              <VCol cols="12" md="4">
                <VTextField
                  v-model="editedItem.fecha_entrega_aprox"
                  label="Fecha Entrega Aprox."
                  type="date"
                />
              </VCol>

              <!-- Fecha Despacho -->
              <VCol cols="12" md="4">
                <VTextField
                  v-model="editedItem.fecha_despacho"
                  label="Fecha Despacho*"
                  type="date"
                  required
                  hint="Fecha estimada de salida del producto"
                />
              </VCol>

              <!-- Estado -->
              <VCol cols="12" md="4">
                <VSelect
                  v-model="editedItem.estado"
                  :items="['pendiente', 'en_proceso', 'entregado', 'cancelado']"
                  label="Estado"
                />
              </VCol>

              <!-- Dirección de Entrega -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.direccion_entrega"
                  label="Dirección de Entrega"
                />
              </VCol>

              <!-- Ciudad de Entrega -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.ciudad_entrega"
                  label="Ciudad de Entrega"
                />
              </VCol>

              <!-- Botón para obtener clima -->
              <VCol cols="12">
                <VAlert type="info" variant="tonal" class="mb-2">
                  <div class="d-flex align-center">
                    <VIcon start>mdi-information</VIcon>
                    <span>
                      El clima local se obtiene automáticamente usando tu ubicación para determinar las condiciones de despacho.
                    </span>
                  </div>
                </VAlert>
                <VBtn 
                  color="info" 
                  @click="obtenerClima"
                  :loading="loadingClima"
                  block
                >
                  <VIcon start>mdi-map-marker-radius</VIcon>
                  Obtener Clima Local (Despacho)
                </VBtn>
              </VCol>

              <!-- Mostrar información del clima -->
              <VCol v-if="climaInfo" cols="12">
                <VCard :color="climaInfo.isError ? 'warning' : getClimaColor(climaInfo.estado)" variant="tonal">
                  <VCardText>
                    <div class="d-flex align-center ga-4">
                      <VIcon :icon="climaInfo.icono" size="64" :color="climaInfo.isError ? undefined : getClimaColor(climaInfo.estado)" />
                      <div class="flex-grow-1">
                        <div class="text-h6">{{ traducirClima(climaInfo.estado) }}</div>
                        <div class="text-subtitle-1">{{ traducirClima(climaInfo.descripcion) }}</div>
                        <div v-if="!climaInfo.isError && climaInfo.temperatura" class="d-flex align-center ga-3 mt-2">
                          <VChip size="small" variant="tonal">
                            <VIcon start size="small">mdi-map-marker</VIcon>
                            {{ climaInfo.ciudad }}
                          </VChip>
                          <VChip size="small" variant="tonal" color="error">
                            <VIcon start size="small">mdi-thermometer</VIcon>
                            {{ climaInfo.temperatura }}°C
                          </VChip>
                          <VChip size="small" variant="tonal" color="info">
                            <VIcon start size="small">mdi-water-percent</VIcon>
                            {{ climaInfo.humedad }}%
                          </VChip>
                        </div>
                        <div v-else-if="climaInfo.ciudad" class="text-caption mt-2">
                          <VIcon size="small">mdi-map-marker</VIcon>
                          {{ climaInfo.ciudad }}
                        </div>
                        <VAlert v-if="climaInfo.isError" type="info" variant="tonal" class="mt-2">
                          <VIcon start>mdi-information</VIcon>
                          Para habilitar el clima real, configura OPENWEATHER_API_KEY en el archivo .env del backend
                        </VAlert>
                      </div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Pronóstico Extendido (5-7 días) -->
              <VCol v-if="pronosticoExtendido.length > 0" cols="12">
                <VCard variant="outlined">
                  <VCardTitle class="d-flex align-center pa-3">
                    <VIcon start size="20">mdi-calendar-range</VIcon>
                    Pronóstico Extendido ({{ pronosticoExtendido.length }} días)
                  </VCardTitle>
                  <VCardText class="pa-2">
                    <VRow dense>
                      <VCol 
                        v-for="(dia, index) in pronosticoExtendido.slice(0, 7)" 
                        :key="index"
                        cols="6"
                        sm="4" 
                        md="3"
                        lg="2"
                      >
                        <VCard 
                          :variant="dia.fecha === editedItem.fecha_despacho ? 'elevated' : 'outlined'" 
                          :color="dia.fecha === editedItem.fecha_despacho ? 'primary' : undefined"
                          :class="dia.fecha === editedItem.fecha_despacho ? 'elevation-4' : ''"
                          class="compact-weather-card"
                        >
                          <VCardText class="text-center pa-2">
                            <VChip 
                              v-if="dia.fecha === editedItem.fecha_despacho" 
                              color="primary" 
                              size="x-small" 
                              class="mb-1"
                            >
                              <VIcon start size="x-small">mdi-package-variant</VIcon>
                              Despacho
                            </VChip>
                            <div class="text-caption font-weight-bold">
                              {{ dia.dia_semana }}
                            </div>
                            <div class="text-caption text-medium-emphasis" style="font-size: 0.65rem;">{{ dia.fecha_formato }}</div>
                            <div class="my-1">
                              <VIcon :icon="getClimaIcono(dia.estado)" size="40" :color="getClimaColor(dia.estado)" />
                            </div>
                            <div class="text-caption mb-1" style="font-size: 0.7rem;">{{ traducirClima(dia.descripcion) }}</div>
                            <VDivider class="my-1" />
                            <div class="d-flex justify-space-around align-center mb-1">
                              <VChip size="x-small" variant="tonal" color="error" style="font-size: 0.65rem;">
                                <VIcon start size="x-small">mdi-thermometer-high</VIcon>
                                {{ Math.round(dia.temp_max) }}°
                              </VChip>
                              <VChip size="x-small" variant="tonal" color="info" style="font-size: 0.65rem;">
                                <VIcon start size="x-small">mdi-thermometer-low</VIcon>
                                {{ Math.round(dia.temp_min) }}°
                              </VChip>
                            </div>
                            <div class="d-flex justify-space-around align-center">
                              <VChip size="x-small" variant="tonal" style="font-size: 0.65rem;">
                                <VIcon start size="x-small">mdi-water-percent</VIcon>
                                {{ dia.humedad }}%
                              </VChip>
                              <VChip size="x-small" variant="tonal" color="primary" style="font-size: 0.65rem;">
                                <VIcon start size="x-small">mdi-weather-rainy</VIcon>
                                {{ Math.round(dia.probabilidad_lluvia) }}%
                              </VChip>
                            </div>
                          </VCardText>
                        </VCard>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Observaciones -->
              <VCol cols="12">
                <VTextarea
                  v-model="editedItem.observaciones"
                  label="Observaciones"
                  rows="2"
                />
              </VCol>

              <!-- Productos del Pedido -->
              <VCol cols="12">
                <VDivider class="my-2" />
                <div class="d-flex justify-space-between align-center mb-3">
                  <span class="text-h6">Productos</span>
                  <VBtn color="success" size="small" @click="agregarItem">
                    <VIcon start>mdi-plus</VIcon>
                    Agregar Producto
                  </VBtn>
                </div>

                <div v-for="(item, index) in editedItem.items" :key="index" class="mb-3">
                  <VCard variant="outlined">
                    <VCardText>
                      <VRow>
                        <VCol cols="12">
                          <!-- Campo de búsqueda con resultados desplegables -->
                          <div class="position-relative">
                            <VTextField
                              v-model="busquedaProductos[index]"
                              label="🔍 Buscar producto por nombre o código"
                              prepend-inner-icon="mdi-magnify"
                              clearable
                              density="compact"
                              variant="outlined"
                              hide-details
                              placeholder="Ej: Poste, PROD-00013, Viga..."
                              @focus="() => { item.mostrarResultados = true }"
                              @blur="() => { setTimeout(() => { item.mostrarResultados = false }, 200) }"
                            />
                            
                            <!-- Lista de resultados desplegable -->
                            <VCard
                              v-if="item.mostrarResultados && busquedaProductos[index] && productosFiltrados(index).length > 0"
                              class="position-absolute w-100 mt-1"
                              style="z-index: 1000; max-height: 300px; overflow-y: auto;"
                              elevation="8"
                            >
                              <VList density="compact">
                                <VListItem
                                  v-for="producto in productosFiltrados(index).slice(0, 10)"
                                  :key="producto.id"
                                  @click="seleccionarProducto(item, producto, index)"
                                  class="cursor-pointer"
                                  hover
                                >
                                  <template #prepend>
                                    <VChip size="x-small" color="primary" class="mr-2">
                                      {{ producto.codigo }}
                                    </VChip>
                                  </template>
                                  <VListItemTitle>{{ producto.nombre }}</VListItemTitle>
                                  <VListItemSubtitle>
                                    Compra: ${{ producto.precio_compra }} | Venta: ${{ producto.precio_venta }} | IVA: {{ producto.porcentaje_iva }}%
                                  </VListItemSubtitle>
                                </VListItem>
                              </VList>
                            </VCard>
                            
                            <!-- Mensaje cuando no hay resultados -->
                            <VCard
                              v-if="item.mostrarResultados && busquedaProductos[index] && busquedaProductos[index].length > 2 && productosFiltrados(index).length === 0"
                              class="position-absolute w-100 mt-1"
                              style="z-index: 1000;"
                              elevation="4"
                            >
                              <VCardText class="text-center text-medium-emphasis py-3">
                                <VIcon class="mb-2">mdi-magnify-close</VIcon>
                                <div>No se encontraron productos</div>
                              </VCardText>
                            </VCard>
                          </div>
                        </VCol>
                      </VRow>
                      
                      <!-- Fila 1: Producto y Cantidad -->
                      <VRow class="mt-3">
                        <VCol cols="12" md="8">
                          <!-- Mostrar producto seleccionado -->
                          <VTextField
                            :model-value="item.producto_id ? productos.find(p => p.id === item.producto_id)?.nombre : ''"
                            label="Producto Seleccionado"
                            readonly
                            density="compact"
                            variant="outlined"
                            prepend-inner-icon="mdi-check-circle"
                            :placeholder="item.producto_id ? '' : 'Ningún producto seleccionado'"
                          >
                            <template #prepend v-if="item.producto_id">
                              <VChip size="x-small" color="success" class="mr-2">
                                {{ productos.find(p => p.id === item.producto_id)?.codigo }}
                              </VChip>
                            </template>
                          </VTextField>
                        </VCol>
                        <VCol cols="12" md="4">
                          <VTextField
                            v-model.number="item.cantidad"
                            label="Cant./Metros"
                            type="number"
                            min="0.01"
                            step="0.01"
                            density="compact"
                          />
                        </VCol>
                      </VRow>
                      
                      <!-- Fila 2: Precios y Totales -->
                      <VRow>
                        <VCol cols="6" md="2">
                          <VTextField
                            v-model.number="item.precio_compra"
                            label="P. Compra"
                            type="number"
                            min="0"
                            step="0.01"
                            prefix="$"
                            density="compact"
                            readonly
                            hint="Costo proveedor"
                            persistent-hint
                            bg-color="grey-lighten-4"
                          />
                        </VCol>
                        <VCol cols="6" md="2">
                          <VTextField
                            v-model.number="item.precio_venta"
                            label="P. Venta"
                            type="number"
                            min="0"
                            step="0.01"
                            prefix="$"
                            density="compact"
                            hint="Incluye ganancia+IVA"
                            persistent-hint
                            @input="item.precio_unitario = item.precio_venta"
                          />
                        </VCol>
                        <VCol cols="6" md="2">
                          <VTextField
                            v-model.number="item.precio_unitario"
                            label="P. Venta"
                            type="number"
                            min="0"
                            step="0.01"
                            prefix="$"
                            density="compact"
                            hint="Precio final al cliente"
                            persistent-hint
                            @input="item.precio_venta = item.precio_unitario"
                          />
                        </VCol>
                        <VCol cols="6" md="2">
                          <VTextField
                            :model-value="formatPrice(item.cantidad * item.precio_unitario)"
                            label="Subtotal"
                            readonly
                            density="compact"
                            bg-color="success-lighten-5"
                          />
                        </VCol>
                        <VCol cols="12" md="1" class="d-flex align-center justify-center">
                          <VBtn
                            icon
                            size="small"
                            color="error"
                            variant="text"
                            @click="eliminarItem(index)"
                          >
                            <VIcon>ri-delete-bin-6-line</VIcon>
                          </VBtn>
                        </VCol>
                      </VRow>
                    </VCardText>
                  </VCard>
                </div>

                <VCard v-if="editedItem.items.length > 0" color="primary" variant="tonal" class="mt-4">
                  <VCardText class="pa-4">
                    <VRow align="center">
                      <VCol cols="12" md="6">
                        <div class="text-caption text-medium-emphasis mb-1">
                          <VIcon size="small" class="mr-1">mdi-information-outline</VIcon>
                          Precio Compra: Costo del proveedor
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          Precio Venta: Precio final al cliente (incluye ganancia + IVA)
                        </div>
                      </VCol>
                      <VCol cols="12" md="6" class="text-right">
                        <div class="text-caption mb-1">Total del Pedido</div>
                        <div class="text-h5 font-weight-bold">
                          {{ formatPrice(totalPedido) }}
                        </div>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VContainer>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="close">
            Cancelar
          </VBtn>
          <VBtn color="primary" variant="text" @click="save">
            Guardar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog para confirmar eliminación -->
    <VDialog v-model="dialogDelete" max-width="500px">
      <VCard>
        <VCardTitle class="text-h5">
          ¿Está seguro de eliminar este pedido?
        </VCardTitle>
        <VCardText>
          Esta acción no se puede deshacer.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="closeDelete">
            Cancelar
          </VBtn>
          <VBtn color="error" variant="text" @click="deleteItemConfirm">
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.compact-weather-card {
  min-height: auto;
}

.compact-weather-card .v-card-text {
  padding: 8px !important;
}

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
</style>
