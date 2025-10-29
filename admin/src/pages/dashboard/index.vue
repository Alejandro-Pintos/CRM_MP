<!-- src/pages/Dashboard.vue -->
<template>
  <v-container class="pa-6">
    <div class="d-flex align-center justify-space-between">
      <h2>Panel</h2>
    </div>
    <v-divider class="my-4" />
    <p>Bienvenido al CRM-MP Admin.</p>
  </v-container>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

const logout = () => {
  auth.logout()
  router.push({ name: 'login' })
}
</script>
