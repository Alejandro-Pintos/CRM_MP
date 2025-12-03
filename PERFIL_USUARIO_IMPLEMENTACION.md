# Implementación de Perfil de Usuario Dinámico - CRM Maderas Pani

## 📋 Descripción de la Solución

Se ha implementado un **perfil de usuario completamente dinámico** que obtiene los datos en tiempo real desde el backend mediante el endpoint existente `POST /api/v1/me`. La solución es conservadora y no rompe ninguna funcionalidad existente.

### Flujo de Datos

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend (Vue 3)                        │
│                                                              │
│  1. Usuario hace clic en "Mi Perfil" en dropdown           │
│  2. Router navega a /perfil                                 │
│  3. Componente perfil.vue se monta                          │
│  4. onMounted() llama a getMe() del servicio auth           │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ HTTP POST /api/v1/me
                         │ Authorization: Bearer {token}
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                  Backend (Laravel 12)                        │
│                                                              │
│  1. Middleware auth:api valida el token JWT                 │
│  2. AuthController::me() obtiene auth('api')->user()        │
│  3. UserProfileResource formatea la respuesta                │
│  4. Incluye roles y permisos de Spatie Permission           │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ JSON Response
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                     Frontend (Vue 3)                        │
│                                                              │
│  5. Respuesta guardada en ref(usuario)                      │
│  6. Template reactivo muestra los datos                     │
│  7. Usuario ve: nombre, email, rol, permisos                │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Archivos Creados/Modificados

### ✅ Archivos NUEVOS Creados

1. **`admin/src/pages/perfil.vue`** (394 líneas)
   - Componente de perfil de usuario dinámico
   - Llama a `getMe()` en `onMounted()`
   - Muestra estados: loading, error, datos del usuario
   - Diseño responsive con 2 columnas (tarjeta de avatar + información detallada)
   - Incluye roles y permisos expandibles

2. **`api/app/Http/Resources/UserProfileResource.php`** (38 líneas)
   - Resource para formatear respuesta del endpoint `/me`
   - Incluye campos: id, nombre, email, created_at, updated_at
   - Agrega roles y permisos dinámicamente usando Spatie Permission

### 🔧 Archivos MODIFICADOS

1. **`api/app/Http/Controllers/AuthController.php`**
   - **Cambio**: Importado `UserProfileResource`
   - **Cambio**: Método `me()` ahora usa el Resource para formatear respuesta
   - Mantiene toda la lógica existente intacta

2. **`admin/src/layouts/components/UserProfile.vue`**
   - **Cambio**: Agregada opción "Mi Perfil" en el menú dropdown (línea 42-47)
   - **Icono**: `ri-user-3-line`
   - **Ruta**: `{ name: 'perfil' }`
   - **Ubicación**: Primera opción después del divider inicial
   - NO se eliminaron opciones existentes (Alertas, Cheques, Pedidos, Cerrar Sesión)

---

## 💻 Código Completo

### 1. Backend: AuthController (método actualizado)

**Archivo**: `api/app/Http/Controllers/AuthController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\UserProfileResource;

class AuthController extends Controller
{
    public function __construct()
    {
        // Protege estas rutas con el guard api; login queda libre
        $this->middleware('auth:api')->only(['me', 'logout', 'refresh']);
    }

    // ... métodos login, logout, refresh sin cambios ...

    /**
     * Obtiene el perfil del usuario autenticado
     * 
     * POST /api/v1/me
     */
    public function me(Request $request)
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Token inválido o expirado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Usar el Resource para formatear la respuesta
            return new UserProfileResource($user);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Token inválido o expirado',
                'error' => $e->getMessage()
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    // ... resto de métodos sin cambios ...
}
```

---

### 2. Backend: UserProfileResource

**Archivo**: `api/app/Http/Resources/UserProfileResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para el perfil del usuario autenticado
 */
class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Roles (Spatie Permission)
            'roles' => $this->when(
                method_exists($this->resource, 'getRoleNames'),
                fn() => $this->getRoleNames()->toArray()
            ),
            
            // Permisos (Spatie Permission)
            'permissions' => $this->when(
                method_exists($this->resource, 'getAllPermissions'),
                fn() => $this->getAllPermissions()->pluck('name')->toArray()
            ),
        ];
    }
}
```

---

### 3. Backend: Rutas (sin cambios necesarios)

**Archivo**: `api/routes/api.php`

La ruta ya existe y está correctamente configurada:

```php
Route::prefix('v1')->middleware('auth:api')->group(function () {
    // ... otras rutas ...
    
    // Rutas de autenticación
    Route::post('logout',  [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me',      [AuthController::class, 'me']); // ✅ Ya existe
});
```

---

### 4. Frontend: Componente de Perfil

**Archivo**: `admin/src/pages/perfil.vue`

```vue
<script setup>
import { ref, computed, onMounted } from 'vue'
import { getMe } from '@/services/auth'

definePage({
  meta: {
    requiresAuth: true,
  },
})

// Estado del componente
const loading = ref(true)
const error = ref(null)
const usuario = ref(null)

// Cargar datos del usuario al montar el componente
onMounted(async () => {
  try {
    loading.value = true
    error.value = null
    
    // Obtener datos del usuario autenticado desde el backend
    const data = await getMe()
    usuario.value = data
  } catch (err) {
    console.error('Error al cargar perfil:', err)
    error.value = 'No se pudo cargar el perfil del usuario. Por favor, intenta nuevamente.'
  } finally {
    loading.value = false
  }
})

// Obtener iniciales del nombre para el avatar
const iniciales = computed(() => {
  if (!usuario.value?.nombre) return 'U'
  const partes = usuario.value.nombre.trim().split(' ')
  if (partes.length === 1) return partes[0][0].toUpperCase()
  return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase()
})

// Obtener el primer rol del usuario
const rolPrincipal = computed(() => {
  if (!usuario.value?.roles) return 'Usuario'
  if (Array.isArray(usuario.value.roles)) {
    return usuario.value.roles[0] || 'Usuario'
  }
  return 'Usuario'
})
</script>

<template>
  <div>
    <!-- Breadcrumb -->
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

    <!-- Estado de carga -->
    <VRow v-if="loading">
      <VCol cols="12">
        <VCard>
          <VCardText class="text-center py-12">
            <VProgressCircular
              indeterminate
              color="primary"
              size="64"
            />
            <div class="mt-4 text-body-1">
              Cargando perfil...
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Estado de error -->
    <VRow v-else-if="error">
      <VCol cols="12">
        <VAlert
          type="error"
          variant="tonal"
          closable
        >
          <VAlertTitle class="mb-1">
            Error al cargar el perfil
          </VAlertTitle>
          <div>{{ error }}</div>
        </VAlert>
      </VCol>
    </VRow>

    <!-- Contenido del perfil -->
    <VRow v-else-if="usuario">
      <!-- Tarjeta principal con información del usuario -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardText class="text-center py-12">
            <!-- Avatar con iniciales -->
            <VAvatar
              size="120"
              color="primary"
              class="mb-4"
            >
              <span class="text-h3 text-white">
                {{ iniciales }}
              </span>
            </VAvatar>

            <!-- Nombre del usuario -->
            <h5 class="text-h5 mb-2">
              {{ usuario.nombre || 'Usuario' }}
            </h5>

            <!-- Rol del usuario -->
            <VChip
              color="primary"
              variant="tonal"
              size="small"
              class="text-capitalize"
            >
              <VIcon
                start
                icon="ri-shield-star-line"
                size="18"
              />
              {{ rolPrincipal }}
            </VChip>
          </VCardText>

          <VDivider />

          <VCardText>
            <div class="d-flex align-center mb-4">
              <VIcon
                icon="ri-mail-line"
                size="20"
                class="me-3"
              />
              <div class="flex-grow-1">
                <div class="text-caption text-disabled">
                  Correo Electrónico
                </div>
                <div class="text-body-1">
                  {{ usuario.email || 'No disponible' }}
                </div>
              </div>
            </div>

            <div class="d-flex align-center">
              <VIcon
                icon="ri-calendar-line"
                size="20"
                class="me-3"
              />
              <div class="flex-grow-1">
                <div class="text-caption text-disabled">
                  Miembro desde
                </div>
                <div class="text-body-1">
                  {{ new Date(usuario.created_at).toLocaleDateString('es-ES', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                  }) }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Información detallada -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon
                icon="ri-information-line"
                size="24"
                class="me-2"
              />
              Información de la Cuenta
            </VCardTitle>
          </VCardItem>

          <VDivider />

          <VCardText>
            <VRow>
              <!-- ID de Usuario -->
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-6">
                  <div class="text-caption text-disabled mb-1">
                    ID de Usuario
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    #{{ usuario.id }}
                  </div>
                </div>
              </VCol>

              <!-- Email -->
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-6">
                  <div class="text-caption text-disabled mb-1">
                    Correo Electrónico
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ usuario.email }}
                  </div>
                </div>
              </VCol>

              <!-- Nombre -->
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-6">
                  <div class="text-caption text-disabled mb-1">
                    Nombre Completo
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ usuario.nombre }}
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Tarjeta de Roles y Permisos -->
        <VCard class="mt-6">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon
                icon="ri-shield-check-line"
                size="24"
                class="me-2"
              />
              Roles y Permisos
            </VCardTitle>
          </VCardItem>

          <VDivider />

          <VCardText>
            <!-- Roles -->
            <div class="mb-6">
              <div class="text-caption text-disabled mb-2">
                Roles Asignados
              </div>
              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="rol in usuario.roles"
                  :key="rol"
                  color="primary"
                  variant="tonal"
                  size="small"
                  class="text-capitalize"
                >
                  {{ rol }}
                </VChip>
                <VChip
                  v-if="!usuario.roles || usuario.roles.length === 0"
                  color="default"
                  variant="tonal"
                  size="small"
                >
                  Sin roles asignados
                </VChip>
              </div>
            </div>

            <!-- Permisos (si existen) -->
            <div v-if="usuario.permissions && usuario.permissions.length > 0">
              <div class="text-caption text-disabled mb-2">
                Permisos ({{ usuario.permissions.length }})
              </div>
              <VExpansionPanels>
                <VExpansionPanel>
                  <VExpansionPanelTitle>
                    Ver todos los permisos
                  </VExpansionPanelTitle>
                  <VExpansionPanelText>
                    <div class="d-flex flex-wrap gap-2">
                      <VChip
                        v-for="permiso in usuario.permissions"
                        :key="permiso"
                        color="success"
                        variant="tonal"
                        size="small"
                      >
                        {{ permiso }}
                      </VChip>
                    </div>
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
```

---

### 5. Frontend: UserProfile Dropdown (cambio)

**Archivo**: `admin/src/layouts/components/UserProfile.vue`

```javascript
// Menú de usuario con opciones relevantes para el CRM
const userProfileList = computed(() => {
  const items = [
    { type: 'divider' },
    
    // Mi Perfil - NUEVO ✅
    {
      type: 'navItem',
      icon: 'ri-user-3-line',
      title: 'Mi Perfil',
      to: { name: 'perfil' },
    },
    
    { type: 'divider' },
    
    // Alertas y notificaciones
    {
      type: 'navItem',
      icon: 'ri-notification-3-line',
      title: 'Alertas',
      to: { name: 'pagos-cheques' },
      chipsProps: notificaciones.value.total_alertas > 0 ? {
        color: 'error',
        text: notificaciones.value.total_alertas.toString(),
        size: 'small',
      } : null,
      badge: notificaciones.value.total_alertas,
    },
    
    { type: 'divider' },
    
    // Cheques próximos a vencer
    {
      type: 'navItem',
      icon: 'ri-file-list-3-line',
      title: 'Cheques a vencer',
      to: { name: 'pagos-cheques' },
      chipsProps: notificaciones.value.cheques_proximos_vencer > 0 ? {
        color: 'warning',
        text: notificaciones.value.cheques_proximos_vencer.toString(),
        size: 'small',
      } : null,
    },
    
    // Pedidos pendientes
    {
      type: 'navItem',
      icon: 'ri-truck-line',
      title: 'Pedidos pendientes',
      to: { name: 'pedidos' },
      chipsProps: notificaciones.value.pedidos_pendientes > 0 ? {
        color: 'info',
        text: notificaciones.value.pedidos_pendientes.toString(),
        size: 'small',
      } : null,
    },
    
    { type: 'divider' },
    
    // Configuración de cuenta (solo para admin/contador)
    ...(hasPermission('configuracion.ver') ? [{
      type: 'navItem',
      icon: 'ri-settings-4-line',
      title: 'Configuración',
      to: { name: 'dashboard' },
    }] : []),
  ]
  
  return items.filter(item => item !== null)
})
```

---

### 6. Frontend: Router (auto-generado por vue-router/auto)

**No requiere modificación manual** - El archivo `admin/src/pages/perfil.vue` será detectado automáticamente por `unplugin-vue-router` y generará la ruta:

```javascript
// Ruta generada automáticamente
{
  path: '/perfil',
  name: 'perfil',
  component: () => import('@/pages/perfil.vue'),
  meta: { requiresAuth: true }
}
```

---

## 🌐 Ejemplo de Respuesta JSON del Endpoint

### Request

```http
POST /api/v1/me HTTP/1.1
Host: localhost:8000
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Accept: application/json
```

### Response (200 OK)

```json
{
  "data": {
    "id": 1,
    "nombre": "Administrador",
    "email": "admin@example.com",
    "created_at": "2025-11-15T10:30:00.000000Z",
    "updated_at": "2025-12-02T08:45:00.000000Z",
    "roles": [
      "admin"
    ],
    "permissions": [
      "clientes.index",
      "clientes.store",
      "clientes.update",
      "clientes.destroy",
      "productos.index",
      "productos.store",
      "ventas.index",
      "ventas.store",
      "reportes.export",
      "configuracion.ver"
    ]
  }
}
```

### Response para usuario sin permisos (vendedor)

```json
{
  "data": {
    "id": 5,
    "nombre": "Juan Pérez",
    "email": "vendedor@example.com",
    "created_at": "2025-10-20T14:00:00.000000Z",
    "updated_at": "2025-12-01T16:30:00.000000Z",
    "roles": [
      "vendedor"
    ],
    "permissions": [
      "clientes.index",
      "ventas.index",
      "ventas.store"
    ]
  }
}
```

---

## ✅ Checklist de Implementación

### Backend
- [x] Endpoint `POST /api/v1/me` ya existía y funciona
- [x] Creado `UserProfileResource` para formatear respuesta
- [x] AuthController actualizado para usar el Resource
- [x] Middleware `auth:api` protege el endpoint
- [x] Incluye roles y permisos de Spatie Permission

### Frontend
- [x] Creada página `/perfil` (`admin/src/pages/perfil.vue`)
- [x] Componente llama a `getMe()` en `onMounted()`
- [x] Maneja estados: loading, error, datos cargados
- [x] Muestra información del usuario dinámicamente
- [x] Avatar con iniciales generadas del nombre
- [x] Diseño responsive (2 columnas en desktop, 1 en mobile)
- [x] Tarjeta de roles y permisos expandible
- [x] Opción "Mi Perfil" agregada al dropdown
- [x] Ruta auto-generada por vue-router/auto
- [x] NO se eliminaron opciones existentes del dropdown

### Seguridad
- [x] Endpoint protegido por `auth:api`
- [x] Solo devuelve datos del usuario autenticado
- [x] No expone información sensible (password oculto)
- [x] Token JWT validado en cada request

---

## 🧪 Testing Manual

### 1. Verificar el endpoint en el backend

```bash
# Desde la carpeta api/
cd api

# Login para obtener token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secret123"}'

# Copiar el access_token de la respuesta

# Obtener perfil
curl -X POST http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer {TU_TOKEN_AQUI}" \
  -H "Accept: application/json"
```

### 2. Verificar en el frontend

1. **Iniciar dev server del frontend**:
   ```bash
   cd admin
   npm run dev
   ```

2. **Login en la aplicación**:
   - Navegar a http://localhost:5174/login
   - Ingresar credenciales (admin@example.com / secret123)

3. **Acceder al perfil**:
   - Hacer clic en el avatar (esquina superior derecha)
   - Hacer clic en "Mi Perfil" (primera opción)
   - Verificar que se carguen los datos reales del usuario

4. **Verificar consola del navegador** (F12):
   ```javascript
   // Debe haber una llamada a /api/v1/me
   // Debe mostrar la respuesta JSON con los datos del usuario
   ```

---

## 🎯 Próximos Pasos Opcionales

### 1. Edición de Perfil (futuro)

Si se desea permitir editar el perfil, crear:

**Backend**:
```php
// app/Http/Controllers/Api/ProfileController.php
public function update(Request $request)
{
    $validated = $request->validate([
        'nombre' => 'required|string|max:100',
        'email' => 'required|email|unique:usuarios,email,' . auth()->id(),
    ]);
    
    $user = auth()->user();
    $user->update($validated);
    
    return new UserProfileResource($user);
}

// routes/api.php
Route::put('profile', [ProfileController::class, 'update'])->middleware('auth:api');
```

**Frontend**: Agregar formulario de edición en `perfil.vue`.

### 2. Avatar de Usuario

Si se desea agregar avatar:

1. Agregar campo `avatar` a la migración de usuarios
2. Implementar upload de archivos
3. Mostrar avatar en lugar de iniciales

### 3. Cambio de Contraseña

Crear endpoint separado:

```php
// PUT /api/v1/profile/password
public function updatePassword(Request $request)
{
    $validated = $request->validate([
        'current_password' => 'required',
        'new_password' => 'required|min:8|confirmed',
    ]);
    
    // Verificar contraseña actual
    // Actualizar contraseña
}
```

---

## 📖 Resumen de la Implementación

✅ **Conservador**: No se modificaron rutas ni middlewares existentes  
✅ **Dinámico**: Datos cargados en tiempo real desde el backend  
✅ **Seguro**: Endpoint protegido por JWT y middleware `auth:api`  
✅ **Completo**: Muestra id, nombre, email, roles y permisos  
✅ **UX Mejorado**: Estados de carga, error y éxito  
✅ **Responsive**: Diseño adaptable a mobile y desktop  
✅ **Integrado**: Opción "Mi Perfil" agregada al dropdown sin eliminar opciones existentes  
✅ **Escalable**: Fácil agregar edición de perfil en el futuro  

**Fecha**: 2 de diciembre de 2025  
**Estado**: ✅ Implementación completa y lista para uso
