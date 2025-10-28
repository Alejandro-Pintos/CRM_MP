<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { login as apiLogin } from '@/services/auth'

const router = useRouter()
const form = ref({ email: '', password: '' })
const loading = ref(false)
const errorMsg = ref('')

async function onSubmit() {
  loading.value = true
  errorMsg.value = ''
  try {
    await apiLogin(form.value.email, form.value.password)
    router.push('/clientes') // o a donde quieras entrar post-login
  } catch (e) {
    errorMsg.value = e.message || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="pa-6 max-w-96 mx-auto">
    <h1 class="mb-4">Iniciar sesión</h1>
    <form @submit.prevent="onSubmit">
      <div class="mb-3">
        <label>Email</label>
        <input v-model="form.email" type="email" class="w-100" />
      </div>
      <div class="mb-3">
        <label>Password</label>
        <input v-model="form.password" type="password" class="w-100" />
      </div>
      <button :disabled="loading" type="submit">Entrar</button>
      <p v-if="errorMsg" style="color:#f44" class="mt-2">{{ errorMsg }}</p>
    </form>
  </main>
</template>
