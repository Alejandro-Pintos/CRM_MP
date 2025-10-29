<script setup>
import { ref, onMounted } from 'vue'

const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000'
const clientes = ref([])
const loading = ref(false)
const error = ref('')

const fetchClientes = async () => {
  loading.value = true
  error.value = ''
  try {
    const token =
      localStorage.getItem('accessToken') ||
      localStorage.getItem('token') ||
      ''

    const res = await fetch(`${API}/api/v1/clientes`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}, // ✅ DESCOMENTAR ESTA LÍNEA
    })

    if (!res.ok) throw new Error(`HTTP ${res.status}`)

    const data = await res.json()
    // Soporta respuestas tipo array o paginadas { data: [...] }
    clientes.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e) {
    error.value = e.message || String(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchClientes)
</script>

<template>
  <div class="pa-6">
    <h2 class="text-h5 mb-4">Clientes</h2>

    <div v-if="loading">Cargando clientes...</div>
    <div v-else-if="error" class="text-error">Error: {{ error }}</div>
    <div v-else>
      <VTable density="compact">
        <thead>
          <tr>
            <th class="text-left">ID</th>
            <th class="text-left">Nombre</th>
            <th class="text-left">Email</th>
            <th class="text-left">Teléfono</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in clientes" :key="c.id">
            <td>{{ c.id }}</td>
            <td>{{ c.nombre || c.name }}</td>
            <td>{{ c.email }}</td>
            <td>{{ c.telefono || c.phone }}</td>
          </tr>
        </tbody>
      </VTable>
      <p v-if="!clientes.length" class="mt-4">No hay clientes cargados.</p>
    </div>
  </div>
</template>
