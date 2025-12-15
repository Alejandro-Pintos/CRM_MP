<script setup>
definePage({ name: 'login', meta: { layout: 'blank', public: true } })
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useGenerateImageVariant } from '@/@core/composable/useGenerateImageVariant'
import authV1LoginMaskLight from '@images/pages/auth-v1-login-mask-light.png'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const { login, loading, error } = useAuth()

const form = ref({
  email: '',
  password: '',
  remember: false,
})

const errorMsg = ref(null)

async function onSubmit() {
  errorMsg.value = null
  
  try {
    await login(form.value.email, form.value.password)
    // La redirección se maneja en useAuth
  } catch (err) {
    errorMsg.value = err.message || 'Error al iniciar sesión'
    console.error('Error en login:', err)
  }
}
const authV1ThemeLoginMask = useGenerateImageVariant(authV1LoginMaskLight)
const isPasswordVisible = ref(false)
</script>

<template>
  <div class="auth-split">
    <!-- Mitad izquierda -->
    <div class="left-half">
      <div class="left-overlay">
        
      </div>
    </div>

    <!-- Mitad derecha -->
    <div class="right-half">
      <VCard 
        class="auth-card pa-6"
        :color="$vuetify.theme.current.dark ? 'surface' : 'background'"
      >
        <!-- Logo y título -->
        <VCardTitle class="text-center pb-4">
          <h4 class="text-h4">Bienvenido 👋</h4>
          <p class="mt-2">Inicia sesión para acceder</p>
        </VCardTitle>

        <!-- Formulario -->
        <VCardText>
          <form @submit.prevent="onSubmit">
            <VTextField
              v-model="form.email"
              label="Email"
              type="email"
              placeholder="usuario@correo.com"
              class="mb-3"
              required
            />

            <VTextField
              v-model="form.password"
              label="Contraseña"
              :type="isPasswordVisible ? 'text' : 'password'"
              :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
              @click:append-inner="isPasswordVisible = !isPasswordVisible"
              class="mb-3"
              required
            />

            <VCheckbox
              v-model="form.remember"
              label="Recordarme"
              class="mb-3"
            />

            <VBtn 
              block 
              color="primary"
              type="submit"
              :loading="loading"
              class="mb-6"
            >
              Iniciar sesión
            </VBtn>
            

            <!-- Mensajes de error/éxito -->
            <VAlert
              v-if="errorMsg"
              type="error"
              variant="tonal"
              class="mb-3"
            >
              {{ errorMsg }}
            </VAlert>

            <!-- Agregar esto después de los alerts -->
            <div class="text-center mt-4">
              <VDivider class="mb-4">
                <span class="mx-2">O</span>
              </VDivider>
              
            </div>
            <div class="d-flex justify-center mb-3">
              <RouterLink
                :to="{ name: 'forgot-password' }"
                class="text-body-2"
              >
                ¿Olvidaste tu contraseña?
              </RouterLink>
            </div>
          </form>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.auth-split {
  display: flex;
  height: 100vh; /* Cambiado de min-height a height */
  overflow: hidden; /* Previene scroll en desktop */
}

.left-half {
  position: relative;
  flex: 2;
  height: 100vh;
  overflow: hidden;
  background-image: url('/images/login-bg.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-color: #2c3e50;
}

.left-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.5) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.right-half {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 3rem 2rem;
  height: 100%;
  overflow-y: auto;
  background: rgba(255, 255, 255, 0.7);
}

:deep(.v-theme--dark) .right-half {
  background: rgba(15, 23, 42, 0.7);
}

.auth-card {
  width: 100%;
  max-width: 460px;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  background: rgba(255, 255, 255, 0.9) !important;
  border: 1px solid rgba(255, 255, 255, 0.3);
  box-shadow: 
    0 8px 32px rgba(31, 38, 135, 0.15),
    0 0 0 1px rgba(255, 255, 255, 0.2);
  border-radius: 16px !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-card:hover {
  transform: translateY(-4px);
  box-shadow: 
    0 12px 48px rgba(31, 38, 135, 0.2),
    0 0 0 1px rgba(255, 255, 255, 0.3);
}

:deep(.v-theme--dark) .auth-card {
  background: rgba(30, 41, 59, 0.85) !important;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 
    0 8px 32px rgba(0, 0, 0, 0.3),
    0 0 0 1px rgba(255, 255, 255, 0.1);
}

:deep(.v-theme--dark) .auth-card:hover {
  transform: translateY(-4px);
  box-shadow: 
    0 12px 48px rgba(0, 0, 0, 0.4),
    0 0 0 1px rgba(255, 255, 255, 0.15);
}

@media (max-width: 959px) {
  .auth-split {
    position: relative;
    display: block;
  }
  
  .left-half {
    position: absolute;
    inset: 0;
    height: 100%;
    width: 100%;
  }
  
  .right-half {
    position: relative;
    z-index: 1;
    padding: 1rem;
    height: 100%;
    background: transparent;
  }

  .auth-card {
    max-height: none;
    margin: 1rem 0;
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
  }
  
  :deep(.v-theme--dark) .auth-card {
    background: rgba(30, 41, 59, 0.95) !important;
  }
}
</style>
