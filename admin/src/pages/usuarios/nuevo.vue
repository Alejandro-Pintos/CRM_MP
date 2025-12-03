<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { createUser } from '@/services/users'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

// Estado
const saving = ref(false)
const usuario = ref({
  nombre: '',
  email: '',
  password: '',
  password_confirmation: '',
  roles: [],
})

const errors = ref({})
const showPassword = ref(false)
const showPasswordConfirm = ref(false)

// Roles disponibles
const rolesDisponibles = [
  { value: 'admin', title: 'Administrador' },
  { value: 'vendedor', title: 'Vendedor' },
  { value: 'operador', title: 'Operador' },
]

// Computed
const canManageUsers = computed(() => {
  return authStore.user?.permissions?.includes('users.manage') ||
         authStore.user?.roles?.some(r => r.toLowerCase() === 'admin' || r.toLowerCase() === 'superadmin')
})

const formularioValido = computed(() => {
  return usuario.value.nombre &&
         usuario.value.email &&
         usuario.value.password &&
         usuario.value.password === usuario.value.password_confirmation &&
         usuario.value.password.length >= 8
})

// Reglas de validación
const nombreRules = [
  v => !!v || 'El nombre es obligatorio',
  v => v.length >= 3 || 'El nombre debe tener al menos 3 caracteres',
]

const emailRules = [
  v => !!v || 'El email es obligatorio',
  v => /.+@.+\..+/.test(v) || 'El email debe ser válido',
]

const passwordRules = [
  v => !!v || 'La contraseña es obligatoria',
  v => v.length >= 8 || 'La contraseña debe tener al menos 8 caracteres',
]

const passwordConfirmRules = [
  v => !!v || 'Confirma la contraseña',
  v => v === usuario.value.password || 'Las contraseñas no coinciden',
]

// Métodos
async function guardar() {
  if (!formularioValido.value) return
  
  saving.value = true
  errors.value = {}
  
  try {
    const payload = {
      nombre: usuario.value.nombre,
      email: usuario.value.email,
      password: usuario.value.password,
      roles: usuario.value.roles,
    }
    
    await createUser(payload)
    router.push({ name: 'usuarios-index' })
  } catch (error) {
    console.error('Error al crear usuario:', error)
    
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      alert('Error al crear el usuario')
    }
  } finally {
    saving.value = false
  }
}

function cancelar() {
  router.push({ name: 'usuarios-index' })
}

// Lifecycle
onMounted(() => {
  if (!canManageUsers.value) {
    router.push({ name: 'dashboard' })
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle class="d-flex align-center">
          <VBtn
            icon
            variant="text"
            class="me-3"
            @click="cancelar"
          >
            <VIcon icon="ri-arrow-left-line" />
          </VBtn>
          <div class="d-flex align-center">
            <VIcon
              icon="ri-user-add-line"
              size="28"
              class="me-3"
            />
            <div>
              <div class="text-h5">
                Nuevo Usuario
              </div>
              <div class="text-caption text-disabled">
                Crea un nuevo usuario en el sistema
              </div>
            </div>
          </div>
        </VCardTitle>
      </VCardItem>
    </VCard>

    <!-- Formulario -->
    <VCard>
      <VCardText>
        <VForm @submit.prevent="guardar">
          <VRow>
            <!-- Nombre -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="usuario.nombre"
                label="Nombre completo *"
                placeholder="Ej: Juan Pérez"
                prepend-inner-icon="ri-user-line"
                :rules="nombreRules"
                :error-messages="errors.nombre"
                :disabled="saving"
              />
            </VCol>

            <!-- Email -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="usuario.email"
                label="Correo electrónico *"
                type="email"
                placeholder="Ej: usuario@example.com"
                prepend-inner-icon="ri-mail-line"
                :rules="emailRules"
                :error-messages="errors.email"
                :disabled="saving"
              />
            </VCol>

            <!-- Contraseña -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="usuario.password"
                label="Contraseña *"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                prepend-inner-icon="ri-lock-line"
                :append-inner-icon="showPassword ? 'ri-eye-off-line' : 'ri-eye-line'"
                :rules="passwordRules"
                :error-messages="errors.password"
                :disabled="saving"
                autocomplete="new-password"
                @click:append-inner="showPassword = !showPassword"
              />
            </VCol>

            <!-- Confirmar contraseña -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="usuario.password_confirmation"
                label="Confirmar contraseña *"
                :type="showPasswordConfirm ? 'text' : 'password'"
                placeholder="••••••••"
                prepend-inner-icon="ri-lock-line"
                :append-inner-icon="showPasswordConfirm ? 'ri-eye-off-line' : 'ri-eye-line'"
                :rules="passwordConfirmRules"
                :disabled="saving"
                autocomplete="new-password"
                @click:append-inner="showPasswordConfirm = !showPasswordConfirm"
              />
            </VCol>

            <!-- Roles -->
            <VCol cols="12">
              <VSelect
                v-model="usuario.roles"
                :items="rolesDisponibles"
                label="Roles"
                placeholder="Selecciona uno o más roles"
                prepend-inner-icon="ri-shield-user-line"
                multiple
                chips
                closable-chips
                :disabled="saving"
                hint="Selecciona los roles que tendrá el usuario"
                persistent-hint
              />
            </VCol>

            <!-- Divider -->
            <VCol cols="12">
              <VDivider class="my-4" />
            </VCol>

            <!-- Botones -->
            <VCol
              cols="12"
              class="d-flex gap-4"
            >
              <VBtn
                color="primary"
                type="submit"
                :loading="saving"
                :disabled="!formularioValido"
              >
                <VIcon
                  start
                  icon="ri-add-line"
                />
                Crear Usuario
              </VBtn>
              <VBtn
                color="default"
                variant="outlined"
                :disabled="saving"
                @click="cancelar"
              >
                Cancelar
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Info adicional -->
    <VCard class="mt-6">
      <VCardText>
        <div class="text-caption text-disabled">
          <VIcon
            icon="ri-information-line"
            size="16"
            class="me-1"
          />
          Los campos marcados con * son obligatorios
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
