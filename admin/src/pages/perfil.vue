<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMe, updateProfile, updatePassword, updateAvatar } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    requiresAuth: true,
  },
})

const router = useRouter()
const authStore = useAuthStore()

// Estado
const loading = ref(true)
const error = ref(null)
const usuario = ref(null)

// Estados de edición
const editandoDatos = ref(false)
const editandoPassword = ref(false)
const subiendoAvatar = ref(false)

// Formularios
const formDatos = ref({
  nombre: '',
  email: '',
})

const formPassword = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

// Errores
const erroresDatos = ref({})
const erroresPassword = ref({})

// Visibilidad passwords
const showCurrentPassword = ref(false)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

// Mensajes
const mensaje = ref({
  show: false,
  type: 'success',
  text: '',
})

// Input file ref
const avatarInput = ref(null)

// Cargar perfil
async function cargarPerfil() {
  try {
    loading.value = true
    error.value = null
    
    const response = await getMe()
    usuario.value = response.data || response
    
    formDatos.value = {
      nombre: usuario.value.nombre || '',
      email: usuario.value.email || '',
    }
  } catch (err) {
    console.error('Error al cargar perfil:', err)
    error.value = 'No se pudo cargar el perfil'
  } finally {
    loading.value = false
  }
}

// Guardar datos
async function guardarDatos() {
  if (!validarFormDatos()) return
  
  editandoDatos.value = true
  erroresDatos.value = {}
  
  try {
    const response = await updateProfile(formDatos.value)
    usuario.value = response.data || response
    
    // Actualizar el store de auth para que el header se actualice
    authStore.setUser(usuario.value)
    
    mostrarMensaje('success', 'Datos actualizados exitosamente')
  } catch (err) {
    console.error('Error:', err)
    if (err.response?.data?.errors) {
      erroresDatos.value = err.response.data.errors
    } else {
      mostrarMensaje('error', 'Error al actualizar los datos')
    }
  } finally {
    editandoDatos.value = false
  }
}

// Cambiar password
async function cambiarPassword() {
  if (!validarFormPassword()) return
  
  editandoPassword.value = true
  erroresPassword.value = {}
  
  try {
    await updatePassword(formPassword.value)
    
    formPassword.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    }
    
    mostrarMensaje('success', 'Contraseña actualizada exitosamente')
  } catch (err) {
    console.error('Error:', err)
    if (err.response?.data?.errors) {
      erroresPassword.value = err.response.data.errors
    } else {
      mostrarMensaje('error', 'La contraseña actual es incorrecta')
    }
  } finally {
    editandoPassword.value = false
  }
}

// Cambiar avatar
async function handleAvatarChange(event) {
  const file = event.target.files[0]
  if (!file) return
  
  if (!file.type.startsWith('image/')) {
    mostrarMensaje('error', 'Solo se permiten imágenes')
    return
  }
  
  if (file.size > 2 * 1024 * 1024) {
    mostrarMensaje('error', 'La imagen no debe superar 2MB')
    return
  }
  
  subiendoAvatar.value = true
  
  try {
    const response = await updateAvatar(file)
    usuario.value = response.data || response
    
    // Actualizar el store de auth para que el header se actualice
    authStore.setUser(usuario.value)
    
    mostrarMensaje('success', 'Avatar actualizado exitosamente')
  } catch (err) {
    console.error('Error:', err)
    mostrarMensaje('error', 'Error al subir el avatar')
  } finally {
    subiendoAvatar.value = false
    if (avatarInput.value) {
      avatarInput.value.value = ''
    }
  }
}

// Validaciones
function validarFormDatos() {
  erroresDatos.value = {}
  let valido = true
  
  if (!formDatos.value.nombre || formDatos.value.nombre.trim().length < 3) {
    erroresDatos.value.nombre = ['El nombre debe tener al menos 3 caracteres']
    valido = false
  }
  
  if (!formDatos.value.email || !/.+@.+\..+/.test(formDatos.value.email)) {
    erroresDatos.value.email = ['El correo debe ser válido']
    valido = false
  }
  
  return valido
}

function validarFormPassword() {
  erroresPassword.value = {}
  let valido = true
  
  if (!formPassword.value.current_password) {
    erroresPassword.value.current_password = ['La contraseña actual es obligatoria']
    valido = false
  }
  
  if (!formPassword.value.password || formPassword.value.password.length < 8) {
    erroresPassword.value.password = ['La contraseña debe tener al menos 8 caracteres']
    valido = false
  }
  
  if (formPassword.value.password !== formPassword.value.password_confirmation) {
    erroresPassword.value.password_confirmation = ['Las contraseñas no coinciden']
    valido = false
  }
  
  return valido
}

function mostrarMensaje(type, text) {
  mensaje.value = { show: true, type, text }
  setTimeout(() => {
    mensaje.value.show = false
  }, 5000)
}

// Computeds
const iniciales = computed(() => {
  if (!usuario.value?.nombre) return 'U'
  const partes = usuario.value.nombre.trim().split(' ')
  if (partes.length === 1) return partes[0][0].toUpperCase()
  return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase()
})

const rolPrincipal = computed(() => {
  if (!usuario.value?.roles || usuario.value.roles.length === 0) return 'Usuario'
  return usuario.value.roles[0] || 'Usuario'
})

const esAdministrador = computed(() => {
  if (!usuario.value?.roles) return false
  return usuario.value.roles.some(rol => 
    rol.toLowerCase() === 'admin' || rol.toLowerCase() === 'superadmin'
  )
})

const formatearFecha = (fecha) => {
  if (!fecha) return 'No disponible'
  try {
    const date = new Date(fecha)
    if (isNaN(date.getTime())) return 'No disponible'
    return date.toLocaleDateString('es-ES', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    })
  } catch {
    return 'No disponible'
  }
}

function irAGestionUsuarios() {
  router.push({ name: 'usuarios-index' })
}

onMounted(cargarPerfil)
</script>

<template>
  <div>
    <!-- Snackbar de mensajes -->
    <VSnackbar
      v-model="mensaje.show"
      :color="mensaje.type"
      location="top"
      :timeout="5000"
    >
      {{ mensaje.text }}
      
      <template #actions>
        <VBtn
          variant="text"
          @click="mensaje.show = false"
        >
          Cerrar
        </VBtn>
      </template>
    </VSnackbar>

    <!-- Header -->
    <VRow>
      <VCol cols="12">
        <div class="d-flex align-center mb-6">
          <VIcon
            icon="ri-user-line"
            size="24"
            class="me-2"
          />
          <h4 class="text-h4">
            Mi Perfil
          </h4>
        </div>
      </VCol>
    </VRow>

    <!-- Loading -->
    <VRow v-if="loading">
      <VCol cols="12">
        <VCard>
          <VCardText class="text-center py-12">
            <VProgressCircular
              indeterminate
              color="primary"
              size="64"
            />
            <div class="mt-4">
              Cargando perfil...
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Error -->
    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
      closable
      class="mb-6"
    >
      {{ error }}
    </VAlert>

    <!-- Contenido -->
    <VRow v-else-if="usuario">
      <!-- Columna izquierda -->
      <VCol
        cols="12"
        md="4"
      >
        <!-- Avatar e info -->
        <VCard class="mb-6">
          <VCardText class="text-center py-8">
            <div class="position-relative d-inline-block">
              <VAvatar
                size="120"
                :color="usuario.avatar ? undefined : 'primary'"
                class="mb-4 cursor-pointer"
                @click="$refs.avatarInput.click()"
              >
                <VImg
                  v-if="usuario.avatar"
                  :src="usuario.avatar"
                />
                <span
                  v-else
                  class="text-h3 text-white"
                >
                  {{ iniciales }}
                </span>
              </VAvatar>

              <VBtn
                icon
                size="small"
                color="primary"
                class="position-absolute"
                style="bottom: 20px; right: -10px"
                :loading="subiendoAvatar"
                @click="$refs.avatarInput.click()"
              >
                <VIcon icon="ri-camera-line" />
              </VBtn>

              <input
                ref="avatarInput"
                type="file"
                accept="image/*"
                style="display: none"
                @change="handleAvatarChange"
              >
            </div>

            <h5 class="text-h5 mb-2">
              {{ usuario.nombre }}
            </h5>

            <VChip
              color="primary"
              variant="tonal"
              size="small"
            >
              <VIcon
                start
                icon="ri-shield-star-line"
              />
              {{ rolPrincipal }}
            </VChip>
          </VCardText>

          <VDivider />

          <VCardText>
            <div class="d-flex align-center mb-4">
              <VIcon
                icon="ri-mail-line"
                class="me-3"
              />
              <div>
                <div class="text-caption text-disabled">
                  Correo
                </div>
                <div class="text-body-1">
                  {{ usuario.email }}
                </div>
              </div>
            </div>

            <div class="d-flex align-center">
              <VIcon
                icon="ri-calendar-line"
                class="me-3"
              />
              <div>
                <div class="text-caption text-disabled">
                  Miembro desde
                </div>
                <div class="text-body-1">
                  {{ formatearFecha(usuario.created_at) }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- Gestión de usuarios (solo admin) -->
        <VCard v-if="esAdministrador">
          <VCardItem>
            <VCardTitle>
              <VIcon
                icon="ri-group-line"
                class="me-2"
              />
              Gestión de Usuarios
            </VCardTitle>
          </VCardItem>

          <VDivider />

          <VCardText>
            <VBtn
              block
              color="primary"
              prepend-icon="ri-user-line"
              @click="irAGestionUsuarios"
            >
              Ver todos los usuarios
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Columna derecha -->
      <VCol
        cols="12"
        md="8"
      >
        <!-- Datos básicos -->
        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle>
              <VIcon
                icon="ri-user-settings-line"
                class="me-2"
              />
              Datos de la Cuenta
            </VCardTitle>
            <VCardSubtitle>
              Actualiza tu nombre y correo
            </VCardSubtitle>
          </VCardItem>

          <VDivider />

          <VCardText>
            <VForm @submit.prevent="guardarDatos">
              <VRow>
                <VCol cols="12">
                  <VTextField
                    v-model="formDatos.nombre"
                    label="Nombre completo"
                    prepend-inner-icon="ri-user-line"
                    :error-messages="erroresDatos.nombre"
                    :disabled="editandoDatos"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextField
                    v-model="formDatos.email"
                    label="Correo electrónico"
                    type="email"
                    prepend-inner-icon="ri-mail-line"
                    :error-messages="erroresDatos.email"
                    :disabled="editandoDatos"
                  />
                </VCol>

                <VCol cols="12">
                  <VBtn
                    type="submit"
                    color="primary"
                    :loading="editandoDatos"
                  >
                    <VIcon
                      start
                      icon="ri-save-line"
                    />
                    Guardar Cambios
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VCard>

        <!-- Cambiar contraseña -->
        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle>
              <VIcon
                icon="ri-lock-password-line"
                class="me-2"
              />
              Cambiar Contraseña
            </VCardTitle>
            <VCardSubtitle>
              Actualiza tu contraseña de acceso
            </VCardSubtitle>
          </VCardItem>

          <VDivider />

          <VCardText>
            <VForm @submit.prevent="cambiarPassword">
              <VRow>
                <VCol cols="12">
                  <VTextField
                    v-model="formPassword.current_password"
                    label="Contraseña actual"
                    :type="showCurrentPassword ? 'text' : 'password'"
                    prepend-inner-icon="ri-lock-line"
                    :append-inner-icon="showCurrentPassword ? 'ri-eye-off-line' : 'ri-eye-line'"
                    :error-messages="erroresPassword.current_password"
                    :disabled="editandoPassword"
                    @click:append-inner="showCurrentPassword = !showCurrentPassword"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextField
                    v-model="formPassword.password"
                    label="Nueva contraseña"
                    :type="showPassword ? 'text' : 'password'"
                    prepend-inner-icon="ri-lock-line"
                    :append-inner-icon="showPassword ? 'ri-eye-off-line' : 'ri-eye-line'"
                    :error-messages="erroresPassword.password"
                    :disabled="editandoPassword"
                    @click:append-inner="showPassword = !showPassword"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextField
                    v-model="formPassword.password_confirmation"
                    label="Confirmar nueva contraseña"
                    :type="showPasswordConfirmation ? 'text' : 'password'"
                    prepend-inner-icon="ri-lock-line"
                    :append-inner-icon="showPasswordConfirmation ? 'ri-eye-off-line' : 'ri-eye-line'"
                    :error-messages="erroresPassword.password_confirmation"
                    :disabled="editandoPassword"
                    @click:append-inner="showPasswordConfirmation = !showPasswordConfirmation"
                  />
                </VCol>

                <VCol cols="12">
                  <VBtn
                    type="submit"
                    color="warning"
                    :loading="editandoPassword"
                  >
                    <VIcon
                      start
                      icon="ri-shield-check-line"
                    />
                    Actualizar Contraseña
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VCard>

        <!-- Roles y permisos -->
        <VCard>
          <VCardItem>
            <VCardTitle>
              <VIcon
                icon="ri-shield-user-line"
                class="me-2"
              />
              Roles y Permisos
            </VCardTitle>
          </VCardItem>

          <VDivider />

          <VCardText>
            <div class="mb-4">
              <div class="text-caption text-disabled mb-2">
                Roles asignados
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <VChip
                  v-for="rol in usuario.roles"
                  :key="rol"
                  color="primary"
                  variant="tonal"
                >
                  {{ rol }}
                </VChip>
                <VChip
                  v-if="!usuario.roles || !usuario.roles.length"
                  color="default"
                >
                  Sin roles
                </VChip>
              </div>
            </div>

            <div v-if="usuario.permissions && usuario.permissions.length">
              <div class="text-caption text-disabled mb-2">
                Permisos ({{ usuario.permissions.length }})
              </div>
              <div class="text-body-2">
                {{ usuario.permissions.join(', ') }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
