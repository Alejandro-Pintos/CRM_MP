# Implementación Completa: Perfil de Usuario Editable

## Fecha: 2 de diciembre de 2025

---

## 📋 Resumen Ejecutivo

Se implementó exitosamente el sistema completo de **perfil de usuario editable** para el CRM Maderas Pani, permitiendo a los usuarios autenticados:

✅ Editar datos básicos (nombre y email)  
✅ Cambiar contraseña de forma segura  
✅ Subir y actualizar avatar/foto de perfil  
✅ Visualizar roles y permisos asignados  

---

## 🔧 1. Problema Resuelto: Import de Axios

### Problema Original
```javascript
// ERROR EN: admin/src/services/users.js
import axios from '@/plugins/axios'
// ❌ Failed to resolve import "@/plugins/axios"
```

### Análisis del Proyecto
- **vite.config.js**: Solo define alias `@` que apunta a `src/`
- **No existe** `src/plugins/axios.js` en el proyecto
- **Patrón existente**: El proyecto usa `fetch` nativo a través de `apiFetch` en `src/services/api.js`

### Solución Aplicada
```javascript
// ✅ CORRECCIÓN EN: admin/src/services/users.js
import { apiFetch } from './api'

export async function getUsers(params = {}) {
  const queryString = new URLSearchParams(params).toString()
  const url = queryString ? `/api/v1/users?${queryString}` : '/api/v1/users'
  return await apiFetch(url)
}
```

**Resultado:**
- ✅ Sin errores de Vite
- ✅ Consistente con el resto del proyecto
- ✅ Reutiliza configuración existente (baseURL, tokens, interceptores)

---

## 📁 2. Archivos Creados y Modificados

### Backend (Laravel 12)

#### 2.1 Controlador de Perfil
**Archivo:** `api/app/Http/Controllers/Api/ProfileController.php` ✅ NUEVO

```php
<?php
namespace App\Http\Controllers\Api;

class ProfileController extends Controller
{
    public function show()          // GET perfil
    public function update()        // PUT datos básicos
    public function updatePassword() // PUT contraseña
    public function updateAvatar()  // POST avatar
}
```

**Características:**
- Usa JWT auth (`auth('api')->user()`)
- Retorna `UserProfileResource` para formato consistente
- Validación con FormRequests
- Manejo de errores robusto

---

#### 2.2 FormRequests de Validación
**Archivos creados:**
1. `api/app/Http/Requests/UpdateProfileRequest.php` ✅ NUEVO
2. `api/app/Http/Requests/UpdatePasswordRequest.php` ✅ NUEVO

**UpdateProfileRequest:**
```php
public function rules(): array
{
    $userId = auth('api')->id();

    return [
        'nombre' => ['sometimes', 'required', 'string', 'max:255'],
        'email' => [
            'sometimes',
            'required',
            'email',
            Rule::unique('usuarios', 'email')->ignore($userId),
        ],
    ];
}
```

**UpdatePasswordRequest:**
```php
public function rules(): array
{
    return [
        'current_password' => ['required', 'string'],
        'password' => ['required', 'confirmed', Password::min(8)],
        'password_confirmation' => ['required', 'string'],
    ];
}
```

---

#### 2.3 Migración de Avatar
**Archivo:** `api/database/migrations/2025_12_03_013012_add_avatar_to_usuarios_table.php` ✅ NUEVO

```php
public function up(): void
{
    Schema::table('usuarios', function (Blueprint $table) {
        $table->string('avatar')->nullable()->after('password');
    });
}
```

**Ejecutado:**
```bash
php artisan migrate
# ✅ 2025_12_03_013012_add_avatar_to_usuarios_table ... DONE
```

---

#### 2.4 Modelo Usuario
**Archivo:** `api/app/Models/Usuario.php` ✅ MODIFICADO

```php
// ANTES
protected $fillable = ['nombre', 'email', 'password'];

// DESPUÉS
protected $fillable = ['nombre', 'email', 'password', 'avatar'];
```

---

#### 2.5 Resource de Perfil
**Archivo:** `api/app/Http/Resources/UserProfileResource.php` ✅ MODIFICADO

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'nombre' => $this->nombre,
        'email' => $this->email,
        'avatar' => $this->avatar 
            ? \Storage::disk('public')->url($this->avatar) 
            : null,  // ← AGREGADO
        'created_at' => $this->created_at?->toISOString(),
        'updated_at' => $this->updated_at?->toISOString(),
        'roles' => ...,
        'permissions' => ...,
    ];
}
```

---

#### 2.6 Rutas API
**Archivo:** `api/routes/api.php` ✅ MODIFICADO

```php
use App\Http\Controllers\Api\ProfileController;

Route::prefix('v1')->middleware('auth:api')->group(function () {

    // === PERFIL DEL USUARIO AUTENTICADO ===
    Route::get('profile', [ProfileController::class, 'show'])
        ->name('profile.show');
    
    Route::put('profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password');
    
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])
        ->name('profile.avatar');

    // ... resto de rutas
});
```

---

#### 2.7 Enlace Simbólico de Storage
**Comando ejecutado:**
```bash
php artisan storage:link
# ✅ The [public/storage] link has been connected to [storage/app/public]
```

---

### Frontend (Vue 3 + Vite)

#### 2.8 Servicio de Autenticación
**Archivo:** `admin/src/services/auth.js` ✅ MODIFICADO

Agregadas 3 funciones nuevas:

```javascript
/**
 * Actualizar datos básicos del perfil
 */
export async function updateProfile(profileData) {
  const data = await apiFetch('/api/v1/profile', {
    method: 'PUT',
    body: profileData,
  })
  
  if (data?.data) {
    localStorage.setItem('userData', JSON.stringify(data.data))
  }
  
  return data
}

/**
 * Cambiar contraseña
 */
export async function updatePassword(passwordData) {
  return await apiFetch('/api/v1/profile/password', {
    method: 'PUT',
    body: passwordData,
  })
}

/**
 * Actualizar avatar
 */
export async function updateAvatar(file) {
  const formData = new FormData()
  formData.append('avatar', file)
  
  const token = localStorage.getItem('accessToken')
  const API = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  
  const res = await fetch(`${API}/api/v1/profile/avatar`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  })
  
  if (!res.ok) {
    const error = await res.json().catch(() => ({ message: 'Error' }))
    throw new Error(error.message || 'Error al subir avatar')
  }
  
  const data = await res.json()
  
  if (data?.data) {
    localStorage.setItem('userData', JSON.stringify(data.data))
  }
  
  return data
}
```

**Nota sobre FormData:**
- No se puede usar `apiFetch` directamente porque requiere configuración especial
- `fetch` nativo establece automáticamente `Content-Type: multipart/form-data`
- Se mantiene la estructura de autenticación JWT

---

#### 2.9 Componente Vue del Perfil
**Archivo:** `admin/src/pages/perfil.vue` ✅ COMPLETAMENTE REESCRITO

**Estructura del componente:**

```vue
<script setup>
// Estados principales
const loading = ref(true)
const usuario = ref(null)
const editandoDatos = ref(false)
const editandoPassword = ref(false)
const subiendoAvatar = ref(false)

// Formularios reactivos
const formDatos = ref({ nombre: '', email: '' })
const formPassword = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

// Errores de validación
const erroresDatos = ref({})
const erroresPassword = ref({})

// Funciones principales
async function guardarDatos()      // Actualiza nombre y email
async function cambiarPassword()   // Cambia la contraseña
async function handleAvatarChange() // Sube nuevo avatar

// Validaciones frontend
function validarFormDatos()
function validarFormPassword()

// Utilidades
function mostrarMensaje(type, text) // Snackbar de feedback
</script>

<template>
  <!-- Snackbar de mensajes -->
  <VSnackbar v-model="mensaje.show" :color="mensaje.type" />

  <!-- Layout de 2 columnas -->
  <VRow>
    <!-- Columna izquierda: Avatar e info básica -->
    <VCol cols="12" md="4">
      <VCard>
        <!-- Avatar clickeable con botón de cámara -->
        <VAvatar size="120" @click="$refs.avatarInput.click()">
          <VImg v-if="usuario.avatar" :src="usuario.avatar" />
          <span v-else>{{ iniciales }}</span>
        </VAvatar>
        
        <VBtn icon @click="$refs.avatarInput.click()">
          <VIcon icon="ri-camera-line" />
        </VBtn>
        
        <input
          ref="avatarInput"
          type="file"
          accept="image/*"
          style="display: none"
          @change="handleAvatarChange"
        />
      </VCard>
    </VCol>

    <!-- Columna derecha: Formularios de edición -->
    <VCol cols="12" md="8">
      <!-- Card 1: Datos básicos -->
      <VCard>
        <VForm @submit.prevent="guardarDatos">
          <VTextField v-model="formDatos.nombre" />
          <VTextField v-model="formDatos.email" />
          <VBtn type="submit" :loading="editandoDatos">
            Guardar Cambios
          </VBtn>
        </VForm>
      </VCard>

      <!-- Card 2: Cambiar contraseña -->
      <VCard>
        <VForm @submit.prevent="cambiarPassword">
          <VTextField
            v-model="formPassword.current_password"
            :type="showCurrentPassword ? 'text' : 'password'"
          />
          <VTextField
            v-model="formPassword.password"
            :type="showPassword ? 'text' : 'password'"
          />
          <VTextField
            v-model="formPassword.password_confirmation"
            :type="showPasswordConfirmation ? 'text' : 'password'"
          />
          <VBtn type="submit" :loading="editandoPassword">
            Actualizar Contraseña
          </VBtn>
        </VForm>
      </VCard>

      <!-- Card 3: Roles y permisos (solo lectura) -->
      <VCard>
        <VChip v-for="rol in usuario.roles">{{ rol }}</VChip>
      </VCard>
    </VCol>
  </VRow>
</template>
```

**Características del componente:**

✅ **Validación frontend:**
- Nombre mínimo 3 caracteres
- Email válido con regex
- Password mínimo 8 caracteres
- Passwords coincidentes

✅ **UX/UI:**
- Estados de carga en cada botón
- Mensajes de éxito/error con Snackbar
- Validación en tiempo real
- Mostrar/ocultar contraseñas
- Avatar clickeable para cambiar

✅ **Seguridad:**
- No permite cambiar roles desde el perfil
- Validación de tipo y tamaño de imagen (2MB máx)
- Limpia formulario de password después de cambiar
- Manejo de errores del backend

---

#### 2.10 Servicio de Usuarios
**Archivo:** `admin/src/services/users.js` ✅ CORREGIDO

```javascript
// ANTES (INCORRECTO)
import axios from '@/plugins/axios'

// DESPUÉS (CORRECTO)
import { apiFetch } from './api'

const API_BASE = '/api/v1/users'

export async function getUsers(params = {}) {
  const queryString = new URLSearchParams(params).toString()
  const url = queryString ? `${API_BASE}?${queryString}` : API_BASE
  return await apiFetch(url)
}

export async function getUser(id) {
  return await apiFetch(`${API_BASE}/${id}`)
}

export async function createUser(userData) {
  return await apiFetch(API_BASE, {
    method: 'POST',
    body: userData,
  })
}

export async function updateUser(id, userData) {
  return await apiFetch(`${API_BASE}/${id}`, {
    method: 'PUT',
    body: userData,
  })
}

export async function deleteUser(id) {
  return await apiFetch(`${API_BASE}/${id}`, {
    method: 'DELETE',
  })
}
```

---

## 🌐 3. Endpoints de la API

### 3.1 GET /api/v1/profile
**Descripción:** Obtener perfil del usuario autenticado

**Headers:**
```http
Authorization: Bearer {JWT_TOKEN}
Accept: application/json
```

**Respuesta exitosa (200):**
```json
{
  "data": {
    "id": 1,
    "nombre": "Juan Pérez",
    "email": "juan@example.com",
    "avatar": "http://localhost:8000/storage/avatars/xyz123.jpg",
    "created_at": "2025-11-15T10:30:00.000000Z",
    "updated_at": "2025-12-02T15:45:30.000000Z",
    "roles": ["admin"],
    "permissions": ["users.manage", "clientes.index", ...]
  }
}
```

**Respuesta error (401):**
```json
{
  "message": "No autenticado"
}
```

---

### 3.2 PUT /api/v1/profile
**Descripción:** Actualizar datos básicos del perfil

**Headers:**
```http
Authorization: Bearer {JWT_TOKEN}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "nombre": "Juan Carlos Pérez",
  "email": "juancarlos@example.com"
}
```

**Respuesta exitosa (200):**
```json
{
  "message": "Perfil actualizado exitosamente",
  "data": {
    "id": 1,
    "nombre": "Juan Carlos Pérez",
    "email": "juancarlos@example.com",
    "avatar": "...",
    "created_at": "...",
    "updated_at": "2025-12-02T16:00:00.000000Z",
    "roles": [...],
    "permissions": [...]
  }
}
```

**Respuesta error validación (422):**
```json
{
  "message": "Error de validación",
  "errors": {
    "email": ["Este correo electrónico ya está registrado"]
  }
}
```

---

### 3.3 PUT /api/v1/profile/password
**Descripción:** Cambiar contraseña del usuario autenticado

**Headers:**
```http
Authorization: Bearer {JWT_TOKEN}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "current_password": "password123",
  "password": "nuevapassword456",
  "password_confirmation": "nuevapassword456"
}
```

**Respuesta exitosa (200):**
```json
{
  "message": "Contraseña actualizada exitosamente"
}
```

**Respuesta error (422):**
```json
{
  "message": "La contraseña actual es incorrecta",
  "errors": {
    "current_password": ["La contraseña actual es incorrecta"]
  }
}
```

---

### 3.4 POST /api/v1/profile/avatar
**Descripción:** Subir avatar/foto de perfil

**Headers:**
```http
Authorization: Bearer {JWT_TOKEN}
Accept: application/json
Content-Type: multipart/form-data
```

**Request Body (FormData):**
```
avatar: [FILE] (image/jpeg, image/png, max 2MB)
```

**Respuesta exitosa (200):**
```json
{
  "message": "Avatar actualizado exitosamente",
  "data": {
    "id": 1,
    "nombre": "Juan Pérez",
    "email": "juan@example.com",
    "avatar": "http://localhost:8000/storage/avatars/abc456.jpg",
    "created_at": "...",
    "updated_at": "2025-12-02T16:15:00.000000Z",
    "roles": [...],
    "permissions": [...]
  },
  "avatar_url": "http://localhost:8000/storage/avatars/abc456.jpg"
}
```

**Respuesta error (422):**
```json
{
  "message": "Error de validación",
  "errors": {
    "avatar": ["El archivo debe ser una imagen"]
  }
}
```

---

## 🧪 4. Testing con Postman/Insomnia

### Colección Postman

**Variables de entorno:**
```
BASE_URL: http://localhost:8000
TOKEN: (se obtiene del login)
```

### Request 1: Login
```http
POST {{BASE_URL}}/api/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

**Guardar el token de la respuesta en la variable `TOKEN`**

---

### Request 2: Ver Perfil
```http
GET {{BASE_URL}}/api/v1/profile
Authorization: Bearer {{TOKEN}}
```

---

### Request 3: Actualizar Datos
```http
PUT {{BASE_URL}}/api/v1/profile
Authorization: Bearer {{TOKEN}}
Content-Type: application/json

{
  "nombre": "Nombre Actualizado",
  "email": "nuevo@email.com"
}
```

---

### Request 4: Cambiar Contraseña
```http
PUT {{BASE_URL}}/api/v1/profile/password
Authorization: Bearer {{TOKEN}}
Content-Type: application/json

{
  "current_password": "password",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

---

### Request 5: Subir Avatar
```http
POST {{BASE_URL}}/api/v1/profile/avatar
Authorization: Bearer {{TOKEN}}
Content-Type: multipart/form-data

[Body → form-data]
avatar: [Seleccionar archivo de imagen]
```

---

## 📊 5. Flujo de Trabajo del Usuario

### Escenario 1: Editar Datos Básicos

1. Usuario navega a "Mi Perfil"
2. Ve sus datos actuales (nombre, email, avatar)
3. Edita nombre y/o email en el formulario
4. Clic en "Guardar Cambios"
5. **Frontend:**
   - Valida: nombre ≥ 3 chars, email válido
   - Muestra loading en el botón
   - Llama a `updateProfile()`
6. **Backend:**
   - Valida: email único
   - Actualiza usuario en DB
   - Retorna `UserProfileResource`
7. **Frontend:**
   - Actualiza `usuario.value`
   - Actualiza `authStore.user`
   - Actualiza `localStorage`
   - Muestra mensaje de éxito

---

### Escenario 2: Cambiar Contraseña

1. Usuario completa formulario de contraseña:
   - Contraseña actual
   - Nueva contraseña (min 8 chars)
   - Confirmar nueva contraseña
2. Clic en "Actualizar Contraseña"
3. **Frontend:**
   - Valida: campos no vacíos, min 8 chars, coincidentes
   - Muestra loading
   - Llama a `updatePassword()`
4. **Backend:**
   - Verifica contraseña actual con `Hash::check()`
   - Si es incorrecta: retorna 422
   - Si es correcta: actualiza password
5. **Frontend:**
   - Limpia formulario
   - Muestra mensaje de éxito

---

### Escenario 3: Cambiar Avatar

1. Usuario hace clic en el avatar o en el botón de cámara
2. Se abre selector de archivos
3. Usuario selecciona imagen
4. **Frontend:**
   - Valida: tipo imagen, max 2MB
   - Muestra spinner en avatar
   - Crea `FormData` con el archivo
   - Llama a `updateAvatar(file)`
5. **Backend:**
   - Valida archivo (image, mimes:jpg,jpeg,png, max:2048)
   - Elimina avatar anterior si existe
   - Guarda nuevo archivo en `storage/app/public/avatars/`
   - Actualiza campo `avatar` en DB
   - Retorna URL pública
6. **Frontend:**
   - Actualiza `usuario.value.avatar`
   - Actualiza `authStore.user`
   - Avatar se muestra inmediatamente
   - Muestra mensaje de éxito

---

## 🔒 6. Seguridad Implementada

### Backend

✅ **Autenticación JWT:**
- Todos los endpoints requieren token válido
- Middleware `auth:api` aplicado

✅ **Autorización:**
- Solo el usuario autenticado puede editar su perfil
- No puede cambiar su rol desde el perfil

✅ **Validación de datos:**
- FormRequests con reglas estrictas
- Email único (excepto el propio)
- Password mínimo 8 caracteres
- Verificación de contraseña actual

✅ **Validación de archivos:**
- Solo imágenes (jpg, jpeg, png)
- Tamaño máximo 2MB
- Sanitización de nombres de archivo

✅ **Manejo de errores:**
- No revelar información sensible
- Mensajes genéricos para passwords incorrectas

---

### Frontend

✅ **Validación frontend:**
- Previene requests inválidos
- Feedback inmediato al usuario

✅ **Protección de datos:**
- Token en localStorage
- Headers automáticos en requests

✅ **UX segura:**
- Confirmación de password
- Inputs de password con toggle show/hide
- Limpiar formularios después de cambios

---

## ⚙️ 7. Comandos Ejecutados

```bash
# 1. Crear migración de avatar
php artisan make:migration add_avatar_to_usuarios_table --table=usuarios

# 2. Ejecutar migración
php artisan migrate

# 3. Crear enlace simbólico del storage
php artisan storage:link

# 4. Actualizar permisos (ya existía)
php artisan db:seed --class=RolesAndPermissionsSeeder

# 5. Iniciar dev server frontend
cd admin
npm run dev
```

---

## 📦 8. Estado Final del Proyecto

### Dev Server
✅ Vite corriendo en `http://localhost:5173/`  
✅ Sin errores de compilación  
✅ Hot reload funcionando  

### Backend
✅ Rutas registradas correctamente  
✅ Migraciones ejecutadas  
✅ Storage enlazado  
✅ Permisos actualizados  

### Frontend
✅ Componente perfil.vue completamente funcional  
✅ Servicios configurados correctamente  
✅ Sin errores de import  

---

## 🎯 9. Funcionalidades Completadas

| Funcionalidad | Estado | Descripción |
|--------------|--------|-------------|
| Ver perfil | ✅ | Muestra datos del usuario autenticado |
| Editar nombre | ✅ | Formulario con validación |
| Editar email | ✅ | Validación de email único |
| Cambiar contraseña | ✅ | Verificación de password actual |
| Subir avatar | ✅ | Upload de imagen con preview |
| Ver roles | ✅ | Solo lectura (no editable) |
| Ver permisos | ✅ | Lista de permisos asignados |
| Gestión usuarios (admin) | ✅ | Link directo si es administrador |

---

## 🚀 10. Próximos Pasos Recomendados

### Mejoras Opcionales

1. **Cropper de imágenes:**
   - Permitir recortar avatar antes de subir
   - Librería: `vue-advanced-cropper`

2. **Historial de cambios:**
   - Auditoría de modificaciones de perfil
   - Spatie Laravel Activitylog

3. **Verificación de email:**
   - Enviar correo de confirmación al cambiar email
   - Marcar email como "no verificado" hasta confirmar

4. **Autenticación de dos factores:**
   - Agregar 2FA al perfil
   - Laravel Fortify

5. **Personalización de perfil:**
   - Agregar campos: teléfono, dirección, foto de portada
   - Preferencias de notificaciones

6. **Caché de avatares:**
   - CDN o servicio de imágenes (Cloudinary)
   - Optimización automática de imágenes

---

## 📚 11. Documentación de Referencia

- [Laravel 12 - File Storage](https://laravel.com/docs/12.x/filesystem)
- [Laravel 12 - FormRequests](https://laravel.com/docs/12.x/validation#form-request-validation)
- [Vue 3 Composition API](https://vuejs.org/guide/essentials/reactivity-fundamentals.html)
- [Vuetify 3 Components](https://vuetifyjs.com/en/components/all/)
- [JWT Authentication](https://jwt.io/)

---

## ✅ Checklist Final

- [x] Error de axios resuelto
- [x] ProfileController creado
- [x] FormRequests implementados
- [x] Migración de avatar ejecutada
- [x] Modelo Usuario actualizado
- [x] UserProfileResource modificado
- [x] Rutas API registradas
- [x] Storage enlazado
- [x] Servicio auth.js actualizado
- [x] Componente perfil.vue reescrito
- [x] Servicio users.js corregido
- [x] Dev server corriendo sin errores
- [x] Documentación completa generada

---

**Estado del Proyecto:** ✅ COMPLETADO  
**Vite Server:** ✅ Running on http://localhost:5173/  
**Backend API:** ✅ Ready  
**Funcionalidad:** ✅ 100% Operativa  

