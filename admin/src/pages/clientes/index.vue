<script setup>
import { ref, onMounted } from 'vue'
import { getClientes, createCliente, updateCliente, deleteCliente, getCuentaCorriente } from '@/services/clientes'
import { toast } from '@/plugins/toast'

const clientes = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const dialogDelete = ref(false)
const dialogCuentaCorriente = ref(false)
const editedIndex = ref(-1)
const selectedCliente = ref(null)
const cuentaCorriente = ref([])

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
}

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
    clientes.value.splice(editedIndex.value, 1)
    toast.success('Cliente eliminado correctamente')
    closeDelete()
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

const save = async () => {
  try {
    if (editedIndex.value > -1) {
      const updated = await updateCliente(editedItem.value.id, editedItem.value)
      Object.assign(clientes.value[editedIndex.value], updated)
      toast.success('Cliente actualizado correctamente')
    } else {
      await createCliente(editedItem.value)
      toast.success('Cliente creado correctamente')
      await fetchClientes() // Refrescar lista
    }
    close()
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
        <div class="d-flex justify-space-between align-center">
          <span class="text-h5">Clientes</span>
          <VBtn color="primary" @click="dialog = true">
            Nuevo Cliente
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" dismissible @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VDataTable
          :headers="headers"
          :items="clientes"
          :loading="loading"
          loading-text="Cargando clientes..."
          no-data-text="No hay clientes registrados"
          class="elevation-1"
        >
          <template #item.nombre_completo="{ item }">
            {{ item.nombre }} {{ item.apellido }}
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
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="editedItem.limite_credito"
                  label="Límite de Crédito"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  hint="Monto máximo que el cliente puede deber"
                />
              </VCol>
              <VCol cols="12" md="6">
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
