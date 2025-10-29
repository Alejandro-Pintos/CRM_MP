<script setup>
import { ref } from 'vue'

const email = ref('')
const loading = ref(false)
const message = ref('')

async function onSubmit() {
  loading.value = true
  message.value = ''
  
  try {
    // Aquí llamarías a tu API para recuperar contraseña
    // await apiFetch('/api/forgot-password', { method: 'POST', body: { email: email.value } })
    message.value = 'Se ha enviado un correo con instrucciones'
  } catch (error) {
    message.value = error.message || 'Error al enviar el correo'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <h1>Recuperar contraseña</h1>
    <form @submit.prevent="onSubmit">
      <input v-model="email" type="email" placeholder="Email" required />
      <button type="submit" :disabled="loading">
        {{ loading ? 'Enviando...' : 'Recuperar contraseña' }}
      </button>
    </form>
    <p v-if="message">{{ message }}</p>
    <RouterLink to="/login">Volver al login</RouterLink>
  </div>
</template>