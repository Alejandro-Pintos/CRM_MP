# Implementación de Gestión de Usuarios - ABM Completo

## Descripción General

Se ha implementado un sistema completo de ABM (Alta, Baja, Modificación) de usuarios para el CRM, incluyendo:

1. **Corrección del perfil de usuario** - Ahora muestra datos reales del usuario autenticado
2. **Backend completo** - API RESTful para gestión de usuarios
3. **Frontend completo** - Interfaz intuitiva para administrar usuarios
4. **Control de permisos** - Solo usuarios con rol admin pueden gestionar usuarios
5. **Validaciones** - Validaciones robustas tanto en frontend como backend

---

## 🎯 Problemas Corregidos

### 1. Perfil mostraba datos incorrectos

**Problema:**
- Nombre: "Usuario"
- Email: "No disponible"
- Miembro desde: "Invalid Date"
- Roles: "Sin roles asignados"

**Causa:**
- Laravel Resources envuelven la respuesta en `{ data: {...} }`
- El frontend hacía `usuario.value = data` sin extraer el objeto interno

**Solución aplicada:**

```javascript
// ANTES
const data = await getMe()
usuario.value = data

// DESPUÉS
const response = await getMe()
usuario.value = response.data || response
```

**Archivos modificados:**
- `admin/src/pages/perfil.vue` - Línea 26
- `admin/src/stores/auth.js` - Línea 31

---

## 📁 Estructura de Archivos Implementados

### Backend (Laravel)

```
api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── UserController.php          ✅ NUEVO - Controlador CRUD
│   │   ├── Requests/
│   │   │   ├── StoreUserRequest.php        ✅ NUEVO - Validación creación
│   │   │   └── UpdateUserRequest.php       ✅ NUEVO - Validación actualización
│   │   └── Resources/
│   │       └── UserResource.php            ✅ NUEVO - Formateo respuestas API
│   └── Models/
│       └── Usuario.php                     ✅ EXISTENTE - Sin cambios
├── database/
│   └── seeders/
│       └── RolesAndPermissionsSeeder.php   ✅ MODIFICADO - Agregados permisos
└── routes/
    └── api.php                             ✅ MODIFICADO - Agregadas rutas
```

### Frontend (Vue 3)

```
admin/
└── src/
    ├── pages/
    │   ├── perfil.vue                      ✅ MODIFICADO - Corrección datos
    │   └── usuarios/
    │       ├── index.vue                   ✅ NUEVO - Lista de usuarios
    │       ├── [id].vue                    ✅ NUEVO - Editar usuario
    │       └── nuevo.vue                   ✅ NUEVO - Crear usuario
    ├── services/
    │   └── users.js                        ✅ NUEVO - API service
    └── stores/
        └── auth.js                         ✅ MODIFICADO - Corrección extracción data
```

---

## 🔐 Permisos y Roles

### Permisos Agregados

```php
'users.manage'   // Gestión general de usuarios (ruta protegida)
'users.create'   // Crear usuarios
'users.edit'     // Editar usuarios
'users.delete'   // Eliminar usuarios
```

### Asignación de Permisos

El rol `admin` tiene todos los permisos, incluyendo los de gestión de usuarios.

**Comando ejecutado:**
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

---

## 🛣️ Rutas API Implementadas

### Endpoint Base
```
/api/v1/users
```

### Rutas Disponibles

| Método | Ruta | Acción | Descripción |
|--------|------|--------|-------------|
| GET | `/api/v1/users` | `index` | Listar usuarios (paginado) |
| POST | `/api/v1/users` | `store` | Crear usuario |
| GET | `/api/v1/users/{id}` | `show` | Ver usuario |
| PUT | `/api/v1/users/{id}` | `update` | Actualizar usuario |
| DELETE | `/api/v1/users/{id}` | `destroy` | Eliminar usuario |

### Middleware Aplicado

```php
Route::middleware(['auth:api', 'permission:users.manage'])->group(function () {
    Route::apiResource('users', UserController::class)
        ->parameters(['users' => 'usuario']);
});
```

**Protección:**
- ✅ Autenticación JWT requerida
- ✅ Permiso `users.manage` requerido
- ✅ Solo usuarios con rol admin pueden acceder

---

## 📋 Funcionalidades del Backend

### UserController

#### `index()` - Listar usuarios
- Paginación (15 por página por defecto)
- Filtro por rol: `?rol=admin`
- Búsqueda: `?search=nombre`
- Ordenamiento: `?sort_by=created_at&sort_order=desc`

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "nombre": "Administrador",
      "email": "admin@example.com",
      "created_at": "2025-11-15T10:30:00.000000Z",
      "updated_at": "2025-11-15T10:30:00.000000Z",
      "roles": ["admin"],
      "permissions": ["users.manage", "..."]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "total": 67
  }
}
```

#### `store()` - Crear usuario
**Request:**
```json
{
  "nombre": "Juan Pérez",
  "email": "juan@example.com",
  "password": "password123",
  "roles": ["vendedor"]
}
```

**Validaciones:**
- Nombre: obligatorio, min 3 caracteres
- Email: obligatorio, válido, único
- Password: obligatorio, min 8 caracteres
- Roles: opcional, debe existir en tabla `roles`

#### `update()` - Actualizar usuario
**Request:**
```json
{
  "nombre": "Juan Pérez Modificado",
  "email": "juan.nuevo@example.com",
  "password": "nuevapassword",  // Opcional
  "roles": ["admin", "vendedor"]
}
```

**Características:**
- Password es opcional (solo se actualiza si se proporciona)
- Email único (excepto el del propio usuario)
- Roles se sincronizan completamente

#### `destroy()` - Eliminar usuario

**Protección:**
- ❌ No permite que el usuario se elimine a sí mismo
- ✅ Elimina el usuario y sus relaciones

---

## 🎨 Funcionalidades del Frontend

### Página de Perfil (`perfil.vue`)

**Mejoras implementadas:**

1. **Extracción correcta de datos del Resource Laravel**
   ```javascript
   usuario.value = response.data || response
   ```

2. **Función de formateo de fechas**
   ```javascript
   const formatearFecha = (fecha) => {
     if (!fecha) return 'No disponible'
     return date.toLocaleDateString('es-ES', { 
       year: 'numeric', 
       month: 'long', 
       day: 'numeric' 
     })
   }
   ```

3. **Detección de administrador**
   ```javascript
   const esAdministrador = computed(() => {
     return usuario.value?.roles?.some(rol => 
       rol.toLowerCase() === 'admin' || 
       rol.toLowerCase() === 'superadmin'
     )
   })
   ```

4. **Sección de Gestión de Usuarios (solo para admin)**
   - Botón "Ver todos los usuarios"
   - Botón "Crear nuevo usuario"
   - Solo visible si el usuario es administrador

---

### Página de Lista (`usuarios/index.vue`)

**Características:**

- ✅ Tabla con DataTable de Vuetify
- ✅ Búsqueda en tiempo real (nombre y email)
- ✅ Filtro por rol
- ✅ Paginación
- ✅ Botón "Actualizar"
- ✅ Acciones por fila: Editar | Eliminar
- ✅ Dialog de confirmación para eliminar
- ✅ Protección: No permite eliminar cuenta propia
- ✅ Badges de colores por rol

**Colores de roles:**
- Admin: `error` (rojo)
- Superadmin: `purple`
- Vendedor: `primary` (azul)
- Operador: `info` (cyan)

---

### Página de Creación (`usuarios/nuevo.vue`)

**Características:**

- ✅ Formulario con validación en tiempo real
- ✅ Campos: Nombre, Email, Contraseña, Confirmar Contraseña, Roles
- ✅ Validaciones frontend:
  - Nombre mínimo 3 caracteres
  - Email válido
  - Password mínimo 8 caracteres
  - Passwords coincidentes
- ✅ Selector múltiple de roles con chips
- ✅ Mostrar/ocultar contraseña
- ✅ Botón deshabilitado si formulario inválido
- ✅ Manejo de errores del backend

---

### Página de Edición (`usuarios/[id].vue`)

**Características:**

- ✅ Formulario precargado con datos del usuario
- ✅ Password opcional (solo se actualiza si se proporciona)
- ✅ Validación: Email único excepto el del propio usuario
- ✅ Sincronización de roles
- ✅ Breadcrumb con botón "Volver"

---

## 🔧 Servicio API (`services/users.js`)

```javascript
import axios from '@/plugins/axios'

export async function getUsers(params = {})    // Listar
export async function getUser(id)              // Ver
export async function createUser(userData)     // Crear
export async function updateUser(id, userData) // Actualizar
export async function deleteUser(id)           // Eliminar
```

**Axios configurado con:**
- Base URL: `/api/v1`
- Token JWT en headers automáticamente
- Interceptors para manejo de errores

---

## ✅ Testing Recomendado

### Backend

```bash
# Verificar rutas registradas
php artisan route:list --name=users

# Probar endpoint con Postman/Insomnia
GET  http://localhost:8000/api/v1/users
POST http://localhost:8000/api/v1/users
```

### Frontend

1. **Login como admin**
   - Email: `admin@example.com`
   - Password: (tu password)

2. **Navegar a Mi Perfil**
   - Verificar que muestra nombre real, email, fecha correcta
   - Verificar que muestra roles correctamente
   - Si es admin, debe ver sección "Gestión de Usuarios"

3. **Acceder a Gestión de Usuarios**
   - Clic en "Ver todos los usuarios"
   - Verificar que carga la lista
   - Probar búsqueda
   - Probar filtro por rol
   - Probar paginación

4. **Crear Usuario**
   - Clic en "Nuevo Usuario"
   - Llenar formulario
   - Verificar validaciones
   - Crear usuario
   - Verificar que aparece en la lista

5. **Editar Usuario**
   - Clic en botón "Editar" de un usuario
   - Modificar datos
   - Dejar password en blanco
   - Guardar
   - Verificar cambios

6. **Eliminar Usuario**
   - Clic en botón "Eliminar"
   - Confirmar
   - Verificar que se elimina
   - Intentar eliminar cuenta propia (debe fallar)

---

## 🚨 Validaciones y Protecciones

### Backend

✅ **Autenticación JWT**
- Todas las rutas requieren token válido

✅ **Autorización por permisos**
- Solo usuarios con `users.manage` pueden acceder

✅ **Validación de datos**
- Email único
- Password mínimo 8 caracteres
- Roles válidos existentes en DB

✅ **Protección contra auto-eliminación**
```php
if ($usuario->id === auth()->id()) {
    return response()->json(['message' => 'No puedes eliminar tu propia cuenta'], 403);
}
```

### Frontend

✅ **Validación en tiempo real**
- Formularios con reglas de validación
- Botones deshabilitados si formulario inválido

✅ **Confirmación de acciones destructivas**
- Dialog de confirmación antes de eliminar

✅ **Protección de rutas**
- Redirect a dashboard si no tiene permisos

✅ **UX mejorado**
- Tooltips explicativos
- Loading states
- Skeleton loaders
- Mensajes de error claros

---

## 📝 Próximos Pasos Recomendados

1. **Agregar endpoint para obtener roles disponibles**
   ```php
   Route::get('roles', [RoleController::class, 'index']);
   ```

2. **Implementar soft deletes**
   ```php
   use SoftDeletes;
   protected $dates = ['deleted_at'];
   ```

3. **Agregar exportación de usuarios**
   - CSV
   - Excel
   - PDF

4. **Implementar búsqueda avanzada**
   - Por fecha de registro
   - Por último acceso
   - Por permisos específicos

5. **Agregar logs de auditoría**
   - Registrar quién creó/modificó/eliminó usuarios
   - Historial de cambios

6. **Notificaciones**
   - Email de bienvenida al crear usuario
   - Email de confirmación al cambiar password

---

## 🐛 Troubleshooting

### Error: "Token inválido"
**Solución:** Verificar que el token JWT esté en localStorage con la key `crmmp:token`

### Error: "Permission denied"
**Solución:** 
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Error: "Call to undefined method syncRoles()"
**Solución:** Verificar que el modelo `Usuario` tenga el trait `HasRoles`

### Perfil muestra "Usuario" en lugar del nombre
**Solución:** Hacer hard refresh del navegador (Ctrl+Shift+R)

### Rutas no registradas
**Solución:**
```bash
php artisan route:clear
php artisan route:cache
```

---

## 📚 Recursos Adicionales

- [Laravel Resources](https://laravel.com/docs/11.x/eloquent-resources)
- [Spatie Permission](https://spatie.be/docs/laravel-permission/v6/introduction)
- [Vue Router](https://router.vuejs.org/)
- [Vuetify DataTable](https://vuetifyjs.com/en/components/data-tables/)

---

## ✨ Resumen de Cambios

### Archivos Creados (9)
1. `api/app/Http/Controllers/Api/UserController.php`
2. `api/app/Http/Requests/StoreUserRequest.php`
3. `api/app/Http/Requests/UpdateUserRequest.php`
4. `api/app/Http/Resources/UserResource.php`
5. `admin/src/services/users.js`
6. `admin/src/pages/usuarios/index.vue`
7. `admin/src/pages/usuarios/[id].vue`
8. `admin/src/pages/usuarios/nuevo.vue`
9. `IMPLEMENTACION_ABM_USUARIOS.md` (este archivo)

### Archivos Modificados (4)
1. `admin/src/pages/perfil.vue`
2. `admin/src/stores/auth.js`
3. `api/routes/api.php`
4. `api/database/seeders/RolesAndPermissionsSeeder.php`

### Comandos Ejecutados (1)
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

---

**Estado:** ✅ Implementación completada
**Fecha:** 2025-01-XX
**Desarrollador:** Senior Full Stack Developer
