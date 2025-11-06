<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getClientes } from '@/services/clientes'
import { getProductos } from '@/services/productos'
import { enviarPresupuestoEmail } from '@/services/presupuestos'
import { toast } from '@/plugins/toast'
import NumberInput from '@/components/NumberInput.vue'
// import jsPDF from 'jspdf'
// import html2canvas from 'html2canvas'

const router = useRouter()

const clientes = ref([])
const productos = ref([])
const loading = ref(false)
const showPreview = ref(false)

const presupuesto = ref({
  cliente_id: null,
  fecha: new Date().toISOString().split('T')[0],
  fecha_vencimiento: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 15 días
  productos: [],
  observaciones: '',
  condiciones_pago: 'Pago contado / Transferencia bancaria',
  validez: '15 días',
})

const productoSeleccionado = ref(null)
const cantidadProducto = ref(1)
const precioProducto = ref(0)
const busquedaProducto = ref('')
const mostrarResultadosProducto = ref(false)

// Búsqueda de clientes
const busquedaCliente = ref('')
const mostrarResultadosCliente = ref(false)

// Computed
const totalCalculado = computed(() => {
  return presupuesto.value.productos.reduce((sum, item) => {
    return sum + (item.cantidad * item.precio_unitario)
  }, 0)
})

const clienteSeleccionado = computed(() => {
  return clientes.value.find(c => c.id === presupuesto.value.cliente_id)
})

const productosFiltrados = computed(() => {
  if (!busquedaProducto.value || busquedaProducto.value.trim() === '') {
    return []
  }
  
  const termino = busquedaProducto.value.toLowerCase().trim()
  
  return productos.value.filter(producto => {
    const nombre = (producto.nombre || '').toLowerCase()
    const codigo = (producto.codigo || '').toLowerCase()
    return nombre.includes(termino) || codigo.includes(termino)
  }).slice(0, 10)
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

// Métodos
const seleccionarCliente = (cliente) => {
  presupuesto.value.cliente_id = cliente.id
  busquedaCliente.value = `${cliente.nombre} ${cliente.apellido}`
  mostrarResultadosCliente.value = false
}

const seleccionarProducto = (producto) => {
  productoSeleccionado.value = producto.id
  busquedaProducto.value = producto.nombre
  precioProducto.value = producto.precio || 0
  mostrarResultadosProducto.value = false
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
  
  presupuesto.value.productos.push({
    producto_id: productoSeleccionado.value,
    nombre: producto.nombre,
    codigo: producto.codigo,
    cantidad: cantidadProducto.value,
    precio_unitario: precioProducto.value,
  })

  // Limpiar
  productoSeleccionado.value = null
  busquedaProducto.value = ''
  cantidadProducto.value = 1
  precioProducto.value = 0
  toast.success('Producto agregado')
}

const eliminarProducto = (index) => {
  presupuesto.value.productos.splice(index, 1)
  toast.info('Producto eliminado')
}

const verVistaPrevia = () => {
  if (!presupuesto.value.cliente_id) {
    toast.warning('Debe seleccionar un cliente')
    return
  }

  if (presupuesto.value.productos.length === 0) {
    toast.warning('Debe agregar al menos un producto')
    return
  }

  showPreview.value = true
}

const imprimir = () => {
  // Guardar scroll actual
  const scrollY = window.scrollY
  
  // Abrir vista de impresión
  window.print()
  
  // Restaurar scroll después de cerrar el diálogo de impresión
  setTimeout(() => {
    window.scrollTo(0, scrollY)
  }, 100)
}

const exportarPDF = async () => {
  if (!clienteSeleccionado.value) {
    toast.warning('Debe seleccionar un cliente antes de exportar')
    return
  }
  
  if (presupuesto.value.productos.length === 0) {
    toast.warning('Debe agregar al menos un producto')
    return
  }

  // Verificar que las librerías estén disponibles
  if (typeof window.html2canvas === 'undefined' || typeof window.jspdf === 'undefined') {
    toast.error('Las librerías de PDF no están cargadas. Recargue la página.')
    return
  }

  try {
    loading.value = true
    toast.info('Generando PDF...')

    // Crear un contenedor temporal para el contenido del presupuesto
    const contenedor = document.createElement('div')
    contenedor.style.position = 'absolute'
    contenedor.style.left = '-9999px'
    contenedor.style.width = '210mm' // Ancho A4
    contenedor.style.padding = '20mm'
    contenedor.style.background = 'white'
    contenedor.style.fontFamily = 'Arial, sans-serif'
    
    // Construir el HTML del presupuesto
    contenedor.innerHTML = `
      <div style="margin-bottom: 30px;">
        <h1 style="color: #1976d2; margin: 0 0 10px 0;">PRESUPUESTO</h1>
        <p style="margin: 0; color: #666;">Fecha: ${new Date(presupuesto.value.fecha).toLocaleDateString('es-AR')}</p>
        <p style="margin: 0; color: #666;">Válido hasta: ${new Date(presupuesto.value.fecha_vencimiento).toLocaleDateString('es-AR')}</p>
      </div>

      <div style="margin-bottom: 30px; padding: 15px; background: #f5f5f5; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0; color: #333;">Cliente</h3>
        <p style="margin: 5px 0;"><strong>${clienteSeleccionado.value.nombre} ${clienteSeleccionado.value.apellido}</strong></p>
        <p style="margin: 5px 0; color: #666;">${clienteSeleccionado.value.email || ''}</p>
        ${clienteSeleccionado.value.cuit ? `<p style="margin: 5px 0; color: #666;">CUIT: ${clienteSeleccionado.value.cuit}</p>` : ''}
      </div>

      <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
          <tr style="background: #1976d2; color: white;">
            <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Producto</th>
            <th style="padding: 12px; text-align: center; border: 1px solid #ddd; width: 100px;">Cantidad</th>
            <th style="padding: 12px; text-align: right; border: 1px solid #ddd; width: 120px;">Precio Unit.</th>
            <th style="padding: 12px; text-align: right; border: 1px solid #ddd; width: 120px;">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          ${presupuesto.value.productos.map((item, index) => {
            const producto = productos.value.find(p => p.id === item.producto_id)
            const subtotal = item.cantidad * item.precio_unitario
            return `
              <tr style="background: ${index % 2 === 0 ? 'white' : '#f9f9f9'};">
                <td style="padding: 10px; border: 1px solid #ddd;">${producto?.nombre || 'Producto'}</td>
                <td style="padding: 10px; text-align: center; border: 1px solid #ddd;">${item.cantidad}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">$ ${item.precio_unitario.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">$ ${subtotal.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
              </tr>
            `
          }).join('')}
          <tr style="background: #e3f2fd; font-weight: bold;">
            <td colspan="3" style="padding: 12px; text-align: right; border: 1px solid #ddd;">TOTAL</td>
            <td style="padding: 12px; text-align: right; border: 1px solid #ddd; color: #1976d2; font-size: 18px;">$ ${totalCalculado.value.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          </tr>
        </tbody>
      </table>

      ${presupuesto.value.condiciones_pago ? `
        <div style="margin-bottom: 20px;">
          <h4 style="margin: 0 0 10px 0; color: #333;">Condiciones de Pago</h4>
          <p style="margin: 0; color: #666;">${presupuesto.value.condiciones_pago}</p>
        </div>
      ` : ''}

      ${presupuesto.value.observaciones ? `
        <div style="margin-bottom: 20px;">
          <h4 style="margin: 0 0 10px 0; color: #333;">Observaciones</h4>
          <p style="margin: 0; color: #666;">${presupuesto.value.observaciones}</p>
        </div>
      ` : ''}

      <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; color: #999; font-size: 12px; text-align: center;">
        <p style="margin: 5px 0;">Presupuesto válido por ${presupuesto.value.validez}</p>
        <p style="margin: 5px 0;">Los precios están sujetos a modificación sin previo aviso</p>
      </div>
    `

    document.body.appendChild(contenedor)

    // Generar canvas del contenido usando la librería global
    const canvas = await window.html2canvas(contenedor, {
      scale: 2,
      useCORS: true,
      logging: false,
      backgroundColor: '#ffffff'
    })

    // Remover el contenedor temporal
    document.body.removeChild(contenedor)

    // Crear PDF usando la librería global jsPDF
    const { jsPDF } = window.jspdf
    const imgData = canvas.toDataURL('image/png')
    const pdf = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4'
    })

    const imgWidth = 210 // Ancho A4 en mm
    const imgHeight = (canvas.height * imgWidth) / canvas.width

    pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight)

    // Descargar el PDF
    const nombreArchivo = `Presupuesto_${clienteSeleccionado.value.apellido}_${new Date().toISOString().split('T')[0]}.pdf`
    pdf.save(nombreArchivo)

    toast.success('PDF generado correctamente')
  } catch (error) {
    console.error('Error al generar PDF:', error)
    toast.error('Error al generar el PDF: ' + error.message)
  } finally {
    loading.value = false
  }
}

const enviarPorEmail = async () => {
  if (!clienteSeleccionado.value) {
    toast.warning('Debe seleccionar un cliente antes de enviar')
    return
  }
  
  if (!clienteSeleccionado.value.email) {
    toast.error('El cliente seleccionado no tiene un email registrado')
    return
  }
  
  if (presupuesto.value.productos.length === 0) {
    toast.warning('Debe agregar al menos un producto')
    return
  }

  try {
    loading.value = true
    toast.info('Enviando presupuesto por email...')

    // Preparar datos para enviar al backend
    const datosEmail = {
      cliente: {
        nombre: clienteSeleccionado.value.nombre,
        apellido: clienteSeleccionado.value.apellido,
        email: clienteSeleccionado.value.email,
        cuit: clienteSeleccionado.value.cuit
      },
      presupuesto: {
        fecha: presupuesto.value.fecha,
        fecha_vencimiento: presupuesto.value.fecha_vencimiento,
        productos: presupuesto.value.productos.map(item => {
          const producto = productos.value.find(p => p.id === item.producto_id)
          return {
            nombre: producto?.nombre || 'Producto',
            cantidad: item.cantidad,
            precio_unitario: item.precio_unitario,
            subtotal: item.cantidad * item.precio_unitario
          }
        }),
        total: totalCalculado.value,
        condiciones_pago: presupuesto.value.condiciones_pago,
        observaciones: presupuesto.value.observaciones,
        validez: presupuesto.value.validez
      }
    }

    // Enviar al backend
    const response = await enviarPresupuestoEmail(datosEmail)
    
    toast.success(`Presupuesto enviado correctamente a ${clienteSeleccionado.value.email}`)
    console.log('Respuesta del servidor:', response)
    
  } catch (error) {
    console.error('Error al enviar email:', error)
    toast.error('Error al enviar el presupuesto por email')
  } finally {
    loading.value = false
  }
}

const limpiar = () => {
  presupuesto.value = {
    cliente_id: null,
    fecha: new Date().toISOString().split('T')[0],
    fecha_vencimiento: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    productos: [],
    observaciones: '',
    condiciones_pago: 'Pago contado / Transferencia bancaria',
    validez: '15 días',
  }
  showPreview.value = false
  toast.success('Presupuesto limpiado')
}

const formatPrice = (value) => {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
  }).format(value || 0)
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('es-AR').format(date)
}

// Lifecycle
onMounted(async () => {
  loading.value = true
  try {
    const [clientesData, productosData] = await Promise.all([
      getClientes(),
      getProductos(),
    ])
    clientes.value = Array.isArray(clientesData) ? clientesData : (clientesData.data ?? [])
    productos.value = Array.isArray(productosData) ? productosData : (productosData.data ?? [])
  } catch (e) {
    toast.error('Error al cargar datos: ' + (e.message || 'Error desconocido'))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="pa-6">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="d-flex align-center">
        <VBtn
          icon
          variant="text"
          color="secondary"
          @click="router.push('/ventas')"
          class="mr-3"
        >
          <VIcon>ri-arrow-left-line</VIcon>
        </VBtn>
        <div>
          <h2 class="text-h4 mb-1">Presupuestador</h2>
          <p class="text-body-2 text-medium-emphasis">Genere presupuestos profesionales para sus clientes</p>
        </div>
      </div>
      <div class="d-flex ga-2">
        <VBtn
          color="secondary"
          variant="outlined"
          prepend-icon="ri-refresh-line"
          @click="limpiar"
        >
          Limpiar
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="ri-eye-line"
          @click="verVistaPrevia"
        >
          Vista Previa
        </VBtn>
      </div>
    </div>

    <VRow>
      <!-- Columna Principal - Formulario -->
      <VCol cols="12" lg="8">
        <VRow>
          <!-- Información del Cliente -->
          <VCol cols="12">
            <VCard variant="outlined" class="mb-3" style="position: relative; z-index: 200; overflow: visible;">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-user-line</VIcon>
                Información del Cliente
              </VCardTitle>
              <VCardText class="pa-3" style="overflow: visible; min-height: 250px;">
                <VRow>
                  <VCol cols="12" md="6" style="position: relative; overflow: visible; z-index: 1000;">
                    <div style="position: relative; overflow: visible;">
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
                        @click:clear="presupuesto.cliente_id = null; mostrarResultadosCliente = false; busquedaCliente = ''"
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
                            :active="presupuesto.cliente_id === cliente.id"
                          >
                            <VListItemTitle>{{ cliente.nombre }} {{ cliente.apellido }}</VListItemTitle>
                            <VListItemSubtitle>
                              Email: {{ cliente.email }} | Tel: {{ cliente.telefono }}
                              <span v-if="cliente.cuit"> | CUIT: {{ cliente.cuit }}</span>
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
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField
                      v-model="presupuesto.fecha"
                      label="Fecha de Emisión"
                      type="date"
                      prepend-inner-icon="ri-calendar-line"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField
                      v-model="presupuesto.fecha_vencimiento"
                      label="Fecha de Vencimiento"
                      type="date"
                      prepend-inner-icon="ri-calendar-check-line"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                </VRow>

                <div v-if="clienteSeleccionado" class="mt-3 pa-3 bg-surface rounded">
                  <VRow dense>
                    <VCol cols="12" md="6">
                      <div class="text-caption text-medium-emphasis">Email</div>
                      <div class="text-body-2 font-weight-medium">{{ clienteSeleccionado.email }}</div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="text-caption text-medium-emphasis">Teléfono</div>
                      <div class="text-body-2 font-weight-medium">{{ clienteSeleccionado.telefono }}</div>
                    </VCol>
                  </VRow>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Agregar Productos -->
          <VCol cols="12">
            <VCard variant="outlined" class="mb-6" style="position: relative; z-index: 100; overflow: visible;">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-add-box-line</VIcon>
                Agregar Productos
              </VCardTitle>
              <VCardText class="pa-3 pb-6" style="overflow: visible; min-height: 400px;">
                <VRow style="overflow: visible;">
                  <VCol cols="12" md="5" style="position: relative; overflow: visible; z-index: 1000;">
                    <div style="position: relative; overflow: visible;">
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
                      />
                      
                      <!-- Dropdown de resultados -->
                      <VCard
                        v-if="mostrarResultadosProducto && busquedaProducto && busquedaProducto.trim().length > 0 && productosFiltrados.length > 0"
                        class="position-absolute elevation-8"
                        style="top: 100%; left: 0; right: 0; z-index: 10000; max-height: 350px; overflow-y: auto; width: 100%; margin-top: 4px;"
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
                        v-if="mostrarResultadosProducto && busquedaProducto && busquedaProducto.trim().length > 0 && productosFiltrados.length === 0"
                        class="position-absolute elevation-4"
                        style="top: 100%; left: 0; right: 0; z-index: 10000; width: 100%; margin-top: 4px;"
                      >
                        <VCardText class="text-center text-grey pa-3">
                          <VIcon>ri-search-line</VIcon>
                          <div class="text-body-2 mt-1">No se encontraron productos</div>
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
                      label="Precio Unitario"
                      prefix="$ "
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

          <!-- Productos Agregados -->
          <VCol cols="12">
            <VCard variant="outlined">
              <VCardTitle class="text-body-1 pa-3 bg-primary d-flex justify-space-between align-center">
                <div>
                  <VIcon class="mr-2" size="18">ri-shopping-cart-line</VIcon>
                  Productos del Presupuesto
                </div>
                <VChip v-if="presupuesto.productos.length > 0" color="white" size="small">
                  {{ presupuesto.productos.length }} {{ presupuesto.productos.length === 1 ? 'producto' : 'productos' }}
                </VChip>
              </VCardTitle>
              <VCardText class="pa-0">
                <VTable v-if="presupuesto.productos.length > 0">
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
                    <tr v-for="(item, index) in presupuesto.productos" :key="index">
                      <td>
                        <div class="font-weight-medium">{{ item.nombre }}</div>
                        <div class="text-caption text-medium-emphasis">Cód: {{ item.codigo }}</div>
                      </td>
                      <td class="text-center">{{ item.cantidad }}</td>
                      <td class="text-end">{{ formatPrice(item.precio_unitario) }}</td>
                      <td class="text-end font-weight-bold">{{ formatPrice(item.cantidad * item.precio_unitario) }}</td>
                      <td class="text-center">
                        <VBtn
                          icon
                          size="small"
                          color="error"
                          variant="text"
                          @click="eliminarProducto(index)"
                        >
                          <VIcon size="20">ri-delete-bin-line</VIcon>
                        </VBtn>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
                <VAlert v-else type="info" variant="tonal" class="ma-3">
                  No hay productos agregados. Agregue productos usando el formulario de arriba.
                </VAlert>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Condiciones y Observaciones -->
          <VCol cols="12">
            <VCard variant="outlined">
              <VCardTitle class="text-body-1 pa-3 bg-primary">
                <VIcon class="mr-2" size="18">ri-file-text-line</VIcon>
                Condiciones y Observaciones
              </VCardTitle>
              <VCardText class="pa-3">
                <VRow>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="presupuesto.condiciones_pago"
                      label="Condiciones de Pago"
                      prepend-inner-icon="ri-hand-coin-line"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="presupuesto.validez"
                      label="Validez del Presupuesto"
                      prepend-inner-icon="ri-time-line"
                      density="compact"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12">
                    <VTextarea
                      v-model="presupuesto.observaciones"
                      label="Observaciones adicionales (opcional)"
                      rows="3"
                      variant="outlined"
                      density="compact"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCol>

      <!-- Columna Lateral - Resumen -->
      <VCol cols="12" lg="4">
        <div class="sticky-sidebar">
          <!-- Resumen Total -->
          <VCard class="mb-3 elevation-2" variant="tonal" color="success">
            <VCardText class="pa-4">
              <div class="text-center">
                <div class="text-caption mb-1">Total del Presupuesto</div>
                <div class="text-h3 font-weight-bold text-success">{{ formatPrice(totalCalculado) }}</div>
              </div>
              
              <VDivider class="my-3" />
              
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-body-2">Productos:</span>
                <VChip color="success" size="small" variant="flat">{{ presupuesto.productos.length }}</VChip>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-body-2">Ítems totales:</span>
                <VChip color="success" size="small" variant="flat">
                  {{ presupuesto.productos.reduce((sum, p) => sum + p.cantidad, 0) }}
                </VChip>
              </div>
            </VCardText>
          </VCard>

          <!-- Acciones -->
          <VCard variant="outlined">
            <VCardTitle class="text-body-1 pa-3 bg-surface-variant">
              <VIcon class="mr-2" size="18">ri-tools-line</VIcon>
              Acciones
            </VCardTitle>
            <VCardText class="pa-3">
              <VBtn
                block
                color="primary"
                prepend-icon="ri-printer-line"
                class="mb-2"
                @click="imprimir"
                :disabled="!presupuesto.cliente_id || presupuesto.productos.length === 0"
              >
                Imprimir Presupuesto
              </VBtn>
              <VBtn
                block
                color="error"
                variant="outlined"
                prepend-icon="ri-file-pdf-line"
                class="mb-2"
                @click="exportarPDF"
                :disabled="!presupuesto.cliente_id || presupuesto.productos.length === 0"
              >
                Exportar a PDF
              </VBtn>
              <VBtn
                block
                color="info"
                variant="outlined"
                prepend-icon="ri-mail-send-line"
                @click="enviarPorEmail"
                :disabled="!presupuesto.cliente_id || presupuesto.productos.length === 0"
              >
                Enviar por Email
              </VBtn>
            </VCardText>
          </VCard>
        </div>
      </VCol>
    </VRow>

    <!-- Vista Previa / Impresión -->
    <VDialog
      v-model="showPreview"
      max-width="900px"
      scrollable
    >
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center pa-4">
          <span class="text-h5">Vista Previa del Presupuesto</span>
          <VBtn
            icon
            variant="text"
            @click="showPreview = false"
          >
            <VIcon>ri-close-line</VIcon>
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-6" id="presupuesto-content">
          <!-- Contenido imprimible -->
          <div class="presupuesto-print">
            <!-- Encabezado -->
            <div class="d-flex justify-space-between align-start mb-6">
              <div>
                <h1 class="text-h4 font-weight-bold mb-2">PRESUPUESTO</h1>
                <p class="text-body-2 text-medium-emphasis mb-0">Tu Empresa S.A.</p>
                <p class="text-body-2 text-medium-emphasis mb-0">Dirección de la empresa</p>
                <p class="text-body-2 text-medium-emphasis mb-0">Tel: (XXX) XXXX-XXXX</p>
                <p class="text-body-2 text-medium-emphasis">Email: contacto@empresa.com</p>
              </div>
              <div class="text-end">
                <div class="text-h6 font-weight-bold mb-2">N° XXXX-XXXX</div>
                <div class="text-body-2"><strong>Fecha:</strong> {{ formatDate(presupuesto.fecha) }}</div>
                <div class="text-body-2"><strong>Vencimiento:</strong> {{ formatDate(presupuesto.fecha_vencimiento) }}</div>
                <div class="text-body-2"><strong>Validez:</strong> {{ presupuesto.validez }}</div>
              </div>
            </div>

            <!-- Datos del Cliente -->
            <div class="mb-6 pa-4 bg-grey-lighten-4 rounded">
              <h3 class="text-h6 mb-3">Cliente</h3>
              <VRow dense v-if="clienteSeleccionado">
                <VCol cols="12" md="6">
                  <div><strong>Nombre:</strong> {{ clienteSeleccionado.nombre }} {{ clienteSeleccionado.apellido }}</div>
                </VCol>
                <VCol cols="12" md="6">
                  <div><strong>CUIT/DNI:</strong> {{ clienteSeleccionado.cuit || 'N/A' }}</div>
                </VCol>
                <VCol cols="12" md="6">
                  <div><strong>Email:</strong> {{ clienteSeleccionado.email }}</div>
                </VCol>
                <VCol cols="12" md="6">
                  <div><strong>Teléfono:</strong> {{ clienteSeleccionado.telefono }}</div>
                </VCol>
                <VCol cols="12" v-if="clienteSeleccionado.direccion">
                  <div><strong>Dirección:</strong> {{ clienteSeleccionado.direccion }}</div>
                </VCol>
              </VRow>
            </div>

            <!-- Tabla de Productos -->
            <VTable class="mb-6">
              <thead>
                <tr style="background-color: #f5f5f5;">
                  <th class="font-weight-bold">Producto</th>
                  <th class="text-center font-weight-bold">Cantidad</th>
                  <th class="text-end font-weight-bold">Precio Unit.</th>
                  <th class="text-end font-weight-bold">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in presupuesto.productos" :key="index">
                  <td>
                    <div class="font-weight-medium">{{ item.nombre }}</div>
                    <div class="text-caption text-medium-emphasis">Código: {{ item.codigo }}</div>
                  </td>
                  <td class="text-center">{{ item.cantidad }}</td>
                  <td class="text-end">{{ formatPrice(item.precio_unitario) }}</td>
                  <td class="text-end">{{ formatPrice(item.cantidad * item.precio_unitario) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr style="background-color: #e3f2fd;">
                  <td colspan="3" class="text-end font-weight-bold text-h6">TOTAL:</td>
                  <td class="text-end font-weight-bold text-h6 text-primary">{{ formatPrice(totalCalculado) }}</td>
                </tr>
              </tfoot>
            </VTable>

            <!-- Condiciones de Pago -->
            <div class="mb-4">
              <h3 class="text-h6 mb-2">Condiciones de Pago</h3>
              <p class="text-body-2">{{ presupuesto.condiciones_pago }}</p>
            </div>

            <!-- Observaciones -->
            <div v-if="presupuesto.observaciones" class="mb-4">
              <h3 class="text-h6 mb-2">Observaciones</h3>
              <p class="text-body-2" style="white-space: pre-line;">{{ presupuesto.observaciones }}</p>
            </div>

            <!-- Pie de página -->
            <div class="mt-8 pt-4 border-t text-center text-caption text-medium-emphasis">
              <p>Este presupuesto tiene una validez de {{ presupuesto.validez }} desde la fecha de emisión.</p>
              <p>Los precios están expresados en pesos argentinos (ARS) y pueden estar sujetos a modificaciones.</p>
            </div>
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VBtn
            color="primary"
            prepend-icon="ri-printer-line"
            @click="imprimir"
          >
            Imprimir
          </VBtn>
          <VBtn
            color="error"
            variant="outlined"
            prepend-icon="ri-file-pdf-line"
            @click="exportarPDF"
          >
            Exportar PDF
          </VBtn>
          <VSpacer />
          <VBtn
            color="secondary"
            variant="text"
            @click="showPreview = false"
          >
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Overlay de carga -->
    <VOverlay
      :model-value="loading"
      class="align-center justify-center"
      persistent
    >
      <VProgressCircular
        indeterminate
        size="64"
        color="primary"
      />
      <div class="mt-4 text-center">
        <p class="text-h6 text-white">Procesando...</p>
      </div>
    </VOverlay>
  </div>
</template>

<style scoped>
.sticky-sidebar {
  position: sticky;
  top: 80px;
}

/* Estilos para impresión */
@media print {
  /* Ocultar todo excepto el contenido del presupuesto */
  body * {
    visibility: hidden;
  }
  
  #presupuesto-content,
  #presupuesto-content * {
    visibility: visible;
  }
  
  #presupuesto-content {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  
  .presupuesto-print {
    padding: 20px;
  }
  
  /* Evitar saltos de página dentro de elementos */
  .presupuesto-print h1,
  .presupuesto-print h2,
  .presupuesto-print h3,
  .presupuesto-print table {
    page-break-inside: avoid;
  }
  
  /* Agregar saltos de página donde sea necesario */
  .page-break {
    page-break-before: always;
  }
}
</style>
