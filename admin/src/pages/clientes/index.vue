<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { getClientes, createCliente, updateCliente, deleteCliente, getCuentaCorriente } from '@/services/clientes'
import { toast } from '@/plugins/toast'
import NumberInput from '@/components/NumberInput.vue'

const clientes = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const dialogDelete = ref(false)
const dialogCuentaCorriente = ref(false)
const editedIndex = ref(-1)
const selectedCliente = ref(null)
const cuentaCorriente = ref([])
const search = ref('')
const tieneCuentaCorriente = ref(false)
const requiereFactura = ref(true)

const editedItem = ref({
  id: null,
  nombre: '',
  apellido: '',
  email: '',
  telefono: '',
  direccion: '',
  ciudad: '',
  provincia: '',
  cuit_cuil: '',
  limite_credito: 0,
  estado: 'activo',
  requiere_factura: true,
})

const defaultItem = {
  id: null,
  nombre: '',
  apellido: '',
  email: '',
  telefono: '',
  direccion: '',
  ciudad: '',
  provincia: '',
  cuit_cuil: '',
  limite_credito: 0,
  estado: 'activo',
  requiere_factura: true,
}

// Watcher para resetear límite de crédito cuando se desactiva cuenta corriente
watch(tieneCuentaCorriente, (nuevoValor) => {
  if (!nuevoValor) {
    editedItem.value.limite_credito = 0
  }
})

// Watcher para sincronizar requiere_factura con el modelo
watch(requiereFactura, (nuevoValor) => {
  editedItem.value.requiere_factura = nuevoValor
})

// Filtrar clientes por búsqueda
const clientesFiltrados = computed(() => {
  if (!search.value) return clientes.value
  
  const searchLower = search.value.toLowerCase()
  return clientes.value.filter(cliente => {
    const nombreCompleto = `${cliente.nombre} ${cliente.apellido}`.toLowerCase()
    const email = (cliente.email || '').toLowerCase()
    const telefono = (cliente.telefono || '').toLowerCase()
    const ciudad = (cliente.ciudad || '').toLowerCase()
    const cuit = (cliente.cuit_cuil || '').toLowerCase()
    
    return nombreCompleto.includes(searchLower) ||
           email.includes(searchLower) ||
           telefono.includes(searchLower) ||
           ciudad.includes(searchLower) ||
           cuit.includes(searchLower)
  })
})

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre Completo', key: 'nombre_completo' },
  { title: 'Email', key: 'email' },
  { title: 'Teléfono', key: 'telefono' },
  { title: 'Límite Crédito', key: 'limite_credito' },
  { title: 'Saldo Actual', key: 'saldo_actual' },
  { title: 'Estado', key: 'estado' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const headersCuentaCorriente = [
  { title: 'Fecha', key: 'fecha' },
  { title: 'Descripción', key: 'descripcion' },
  { title: 'Debe', key: 'debe' },
  { title: 'Haber', key: 'haber' },
  { title: 'Saldo', key: 'saldo' },
]

const fetchClientes = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await getClientes()
    if (response.data && Array.isArray(response.data)) {
      clientes.value = response.data
    } else if (Array.isArray(response)) {
      clientes.value = response
    } else {
      clientes.value = []
    }
  } catch (e) {
    const errorMsg = e.message || 'Error al cargar clientes'
    error.value = errorMsg
    toast.error(errorMsg)
  } finally {
    loading.value = false
  }
}

const editItem = (item) => {
  editedIndex.value = clientes.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  // Establecer el estado del switch basado en si tiene límite de crédito
  tieneCuentaCorriente.value = (item.limite_credito ?? 0) > 0
  // Establecer el estado del switch de factura
  requiereFactura.value = item.requiere_factura ?? true
  dialog.value = true
}

const deleteItem = (item) => {
  editedIndex.value = clientes.value.indexOf(item)
  editedItem.value = Object.assign({}, item)
  dialogDelete.value = true
}

const deleteItemConfirm = async () => {
  try {
    await deleteCliente(editedItem.value.id)
    toast.success('Cliente eliminado correctamente')
    closeDelete()
    await fetchClientes() // Refrescar lista después de eliminar
  } catch (e) {
    const errorMsg = e.message || 'Error al eliminar cliente'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const verCuentaCorriente = async (item) => {
  selectedCliente.value = item
  try {
    const data = await getCuentaCorriente(item.id)
    cuentaCorriente.value = Array.isArray(data) ? data : (data.data ?? [])
    dialogCuentaCorriente.value = true
  } catch (e) {
    const errorMsg = e.message || 'Error al cargar cuenta corriente'
    error.value = errorMsg
    toast.error(errorMsg)
  }
}

const abrirWhatsApp = (telefono, nombre, apellido) => {
  // Limpiar el número de teléfono (quitar espacios, guiones, paréntesis)
  const numeroLimpio = telefono.replace(/\D/g, '')
  
  // Mensaje predeterminado personalizado
  const mensaje = `Hola ${nombre} ${apellido}, `
  
  // Construir URL de WhatsApp Web
  // Si el número no tiene código de país, asumir Argentina (+54)
  const numeroCompleto = numeroLimpio.startsWith('54') ? numeroLimpio : `54${numeroLimpio}`
  const url = `https://wa.me/${numeroCompleto}?text=${encodeURIComponent(mensaje)}`
  
  // Abrir en nueva pestaña
  window.open(url, '_blank')
}

const close = () => {
  dialog.value = false
  error.value = ''
  tieneCuentaCorriente.value = false
  requiereFactura.value = true
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

const save = async () => {
  try {
    if (editedIndex.value > -1) {
      const updated = await updateCliente(editedItem.value.id, editedItem.value)
      Object.assign(clientes.value[editedIndex.value], updated)
      toast.success('Cliente actualizado correctamente')
    } else {
      await createCliente(editedItem.value)
      toast.success('Cliente creado correctamente')
    }
    close()
    await fetchClientes() // Refrescar lista siempre
  } catch (e) {
    const errorMsg = e.message || 'Error al guardar cliente'
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

onMounted(fetchClientes)
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        <div class="d-flex justify-space-between align-center flex-wrap ga-4">
          <span class="text-h5">Clientes</span>
          <div class="d-flex ga-2 align-center">
            <VTextField
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Buscar clientes"
              single-line
              hide-details
              density="compact"
              style="min-width: 300px;"
              clearable
            />
            <VBtn color="primary" @click="dialog = true">
              <VIcon start>mdi-plus</VIcon>
              Nuevo Cliente
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
          :items="clientesFiltrados"
          :loading="loading"
          loading-text="Cargando clientes..."
          no-data-text="No hay clientes registrados"
          class="elevation-1"
        >
          <template #item.nombre_completo="{ item }">
            {{ item.nombre }} {{ item.apellido }}
          </template>
          <template #item.telefono="{ item }">
            <div class="d-flex align-center ga-2">
              <span>{{ item.telefono || '-' }}</span>
              <VBtn
                v-if="item.telefono"
                icon
                size="x-small"
                color="success"
                variant="text"
                @click="abrirWhatsApp(item.telefono, item.nombre, item.apellido)"
                title="Enviar mensaje por WhatsApp"
              >
                <VIcon size="20">ri-whatsapp-line</VIcon>
              </VBtn>
            </div>
          </template>
          <template #item.limite_credito="{ item }">
            {{ formatPrice(item.limite_credito ?? 0) }}
          </template>
          <template #item.saldo_actual="{ item }">
            <span :class="item.saldo_actual > 0 ? 'text-error' : 'text-success'">
              {{ formatPrice(item.saldo_actual ?? 0) }}
            </span>
          </template>
          <template #item.estado="{ item }">
            <VChip
              :color="item.estado === 'activo' ? 'success' : 'error'"
              size="small"
            >
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
                @click="verCuentaCorriente(item)"
                title="Ver cuenta corriente"
              >
                <VIcon>ri-eye-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="primary"
                variant="tonal"
                @click="editItem(item)"
                title="Editar cliente"
              >
                <VIcon>ri-pencil-line</VIcon>
              </VBtn>
              <VBtn
                icon
                size="small"
                color="error"
                variant="tonal"
                @click="deleteItem(item)"
                title="Eliminar cliente"
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
          <span class="text-h5">{{ editedIndex === -1 ? 'Nuevo' : 'Editar' }} Cliente</span>
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
                  v-model="editedItem.apellido"
                  label="Apellido*"
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
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.cuit_cuil"
                  label="CUIT/CUIL"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.ciudad"
                  label="Ciudad"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.provincia"
                  label="Provincia"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="editedItem.direccion"
                  label="Dirección"
                />
              </VCol>
              
              <!-- Divisor visual para sección de cuenta corriente -->
              <VCol cols="12">
                <VDivider class="my-2" />
              </VCol>

              <!-- Switch para requerir factura -->
              <VCol cols="12" md="6">
                <VSwitch
                  v-model="requiereFactura"
                  label="¿Requiere Factura?"
                  color="primary"
                  hide-details
                  density="comfortable"
                >
                  <template #label>
                    <div class="d-flex flex-column">
                      <span class="text-body-1 font-weight-medium">¿Requiere Factura?</span>
                      <span class="text-caption text-medium-emphasis">
                        Se emitirá comprobante fiscal para este cliente
                      </span>
                    </div>
                  </template>
                </VSwitch>
              </VCol>

              <!-- Switch para habilitar cuenta corriente -->
              <VCol cols="12" md="6">
                <VSwitch
                  v-model="tieneCuentaCorriente"
                  label="¿Habilitar Cuenta Corriente?"
                  color="primary"
                  hide-details
                  density="comfortable"
                >
                  <template #label>
                    <div class="d-flex flex-column">
                      <span class="text-body-1 font-weight-medium">¿Habilitar Cuenta Corriente?</span>
                      <span class="text-caption text-medium-emphasis">
                        Permite al cliente realizar compras a crédito y gestionar saldos pendientes
                      </span>
                    </div>
                  </template>
                </VSwitch>
              </VCol>

              <!-- Campo de límite de crédito (solo visible si cuenta corriente está habilitada) -->
              <VCol v-if="tieneCuentaCorriente" cols="12" md="6">
                <NumberInput
                  v-model="editedItem.limite_credito"
                  label="Límite de Crédito*"
                  prefix="$ "
                  hint="Monto máximo que el cliente puede deber"
                  persistent-hint
                  prepend-inner-icon="ri-money-dollar-circle-line"
                  :rules="[v => v > 0 || 'El límite debe ser mayor a 0']"
                />
              </VCol>

              <VCol cols="12" :md="tieneCuentaCorriente ? 6 : 12">
                <VSelect
                  v-model="editedItem.estado"
                  :items="['activo', 'inactivo']"
                  label="Estado"
                />
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
          ¿Está seguro de eliminar este cliente?
        </VCardTitle>
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

    <!-- Dialog de cuenta corriente -->
    <VDialog v-model="dialogCuentaCorriente" max-width="800px">
      <VCard>
        <VCardTitle>
          <span class="text-h5">Cuenta Corriente - {{ selectedCliente?.nombre }}</span>
        </VCardTitle>

        <VCardText>
          <VDataTable
            v-if="cuentaCorriente.length > 0"
            :headers="headersCuentaCorriente"
            :items="cuentaCorriente"
            density="compact"
            class="elevation-1"
          >
            <template #item.debe="{ item }">
              {{ item.debe ? formatPrice(item.debe) : '-' }}
            </template>
            <template #item.haber="{ item }">
              {{ item.haber ? formatPrice(item.haber) : '-' }}
            </template>
            <template #item.saldo="{ item }">
              <span :class="item.saldo < 0 ? 'text-error' : 'text-success'">
                {{ formatPrice(item.saldo) }}
              </span>
            </template>
          </VDataTable>
          <p v-else class="text-center text-disabled">No hay movimientos en la cuenta corriente</p>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="text" @click="dialogCuentaCorriente = false">
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
