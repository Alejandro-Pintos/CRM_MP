# CRM Maderas Pani

Sistema de gestión de relaciones con clientes (CRM) completo desarrollado para Maderas Pani, con funcionalidades de ventas, inventario, gestión de clientes, proveedores, empleados y reportes.

## 🚀 Stack Tecnológico

### Backend
- **Framework:** Laravel 12
- **Base de datos:** MySQL 8.0+
- **Autenticación:** JWT (tymon/jwt-auth)
- **Permisos:** Spatie Laravel Permission
- **API:** RESTful API con versionado (v1)

### Frontend
- **Framework:** Vue 3 (Composition API)
- **Build tool:** Vite 5.2.10
- **UI Framework:** Vuetify 3
- **Routing:** Vue Router (file-based routing)
- **State Management:** Pinia
- **HTTP Client:** Fetch API nativo

---

## 📋 Funcionalidades Principales

### 🔐 Autenticación y Autorización
- Login con JWT
- Gestión de permisos basada en roles (admin, vendedor, operador)
- Perfil de usuario editable (nombre, email, contraseña, avatar)
- Sistema de tokens con refresh automático

### 👥 Gestión de Clientes
- CRUD completo de clientes
- Cuenta corriente por cliente
- Historial de ventas
- Filtros y búsqueda avanzada
- Exportación de datos (CSV, Excel)

### 📦 Gestión de Productos
- Inventario completo
- Control de stock
- Categorías y subcategorías
- Sistema de precios dinámico
- Búsqueda y filtros

### 🏢 Gestión de Proveedores
- CRUD de proveedores
- Estado de cuenta por proveedor
- Registro de pagos a proveedores
- Movimientos y saldos
- Exportación de reportes

### 👨‍💼 Gestión de Empleados
- CRUD de empleados
- Registro de pagos a empleados
- Historial de pagos
- Filtros por estado (activo/inactivo)

### 💰 Ventas y Facturación
- Creación de ventas con múltiples productos
- Previsualización de número de comprobante
- Asociación con pedidos
- Múltiples métodos de pago
- Sistema de cheques (pendientes, cobrados, rechazados)
- Cuenta corriente de clientes

### 📊 Pedidos
- Gestión de pedidos
- Estados: pendiente, procesando, completado, cancelado
- Asociación automática con ventas
- Consulta de clima para planificación de entregas
- Filtros avanzados

### 💳 Métodos de Pago
- Efectivo
- Transferencia
- Cheques (con control de vencimiento)
- Cuenta corriente
- Consolidación de pagos

### 📈 Reportes
- Dashboard con métricas en tiempo real
- Reportes de ventas
- Reportes de clientes
- Reportes de productos
- Reportes de proveedores
- Exportación en múltiples formatos (CSV, Excel)
- Reporte full consolidado

### 🔔 Sistema de Notificaciones
- Alertas de cheques próximos a vencer
- Alertas de stock bajo
- Resumen de notificaciones en tiempo real
- Badges dinámicos en el menú

### 👤 Gestión de Usuarios (ABM)
- CRUD completo de usuarios
- Asignación de roles
- Gestión de permisos
- Solo accesible para administradores

---

## 🏗️ Arquitectura del Proyecto

```
CRM_MP/
├── admin/                          # Frontend Vue 3
│   ├── src/
│   │   ├── @core/                  # Componentes core del template
│   │   ├── @layouts/               # Layouts de la aplicación
│   │   ├── assets/                 # Assets estáticos
│   │   ├── components/             # Componentes reutilizables
│   │   ├── composables/            # Composables de Vue
│   │   ├── layouts/                # Layouts personalizados
│   │   ├── navigation/             # Configuración de navegación
│   │   ├── pages/                  # Páginas (file-based routing)
│   │   │   ├── clientes/
│   │   │   ├── productos/
│   │   │   ├── proveedores/
│   │   │   ├── empleados/
│   │   │   ├── ventas/
│   │   │   ├── pedidos/
│   │   │   ├── reportes/
│   │   │   ├── usuarios/
│   │   │   └── perfil.vue
│   │   ├── plugins/                # Plugins de Vue
│   │   ├── router/                 # Configuración de rutas
│   │   ├── services/               # Servicios de API
│   │   │   ├── api.js              # Cliente HTTP base (apiFetch)
│   │   │   ├── auth.js             # Autenticación y perfil
│   │   │   ├── clientes.js
│   │   │   ├── productos.js
│   │   │   ├── proveedores.js
│   │   │   ├── empleados.js
│   │   │   ├── ventas.js
│   │   │   ├── pedidos.js
│   │   │   ├── users.js
│   │   │   └── notificaciones.js
│   │   ├── stores/                 # Stores de Pinia
│   │   │   └── auth.js
│   │   ├── utils/                  # Utilidades
│   │   └── views/                  # Vistas adicionales
│   ├── vite.config.js
│   └── package.json
│
├── api/                            # Backend Laravel 12
│   ├── app/
│   │   ├── Exports/                # Clases de exportación
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/
│   │   │   │   │   ├── ClientesController.php
│   │   │   │   │   ├── ProductosController.php
│   │   │   │   │   ├── ProveedorEstadoCuentaController.php
│   │   │   │   │   ├── PagoProveedorController.php
│   │   │   │   │   ├── EmpleadoController.php
│   │   │   │   │   ├── PagoEmpleadoController.php
│   │   │   │   │   ├── PedidoController.php
│   │   │   │   │   ├── ReporteController.php
│   │   │   │   │   ├── NotificationController.php
│   │   │   │   │   ├── UserController.php
│   │   │   │   │   └── ProfileController.php
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── VentaController.php
│   │   │   │   ├── PagoController.php
│   │   │   │   ├── ChequeController.php
│   │   │   │   ├── MetodoPagoController.php
│   │   │   │   ├── CuentaCorrienteController.php
│   │   │   │   ├── PresupuestoController.php
│   │   │   │   └── ProveedorController.php
│   │   │   ├── Requests/           # Form Requests
│   │   │   │   ├── StoreUserRequest.php
│   │   │   │   ├── UpdateUserRequest.php
│   │   │   │   ├── UpdateProfileRequest.php
│   │   │   │   ├── UpdatePasswordRequest.php
│   │   │   │   ├── StoreEmpleadoRequest.php
│   │   │   │   └── UpdateEmpleadoRequest.php
│   │   │   ├── Resources/          # API Resources
│   │   │   │   ├── UserProfileResource.php
│   │   │   │   ├── UserResource.php
│   │   │   │   └── EmpleadoResource.php
│   │   │   └── Middleware/
│   │   ├── Mail/                   # Mailable classes
│   │   ├── Models/
│   │   │   ├── Usuario.php
│   │   │   ├── Cliente.php
│   │   │   ├── Producto.php
│   │   │   ├── Proveedor.php
│   │   │   ├── Empleado.php
│   │   │   ├── Venta.php
│   │   │   ├── VentaDetalle.php
│   │   │   ├── Pago.php
│   │   │   ├── Pedido.php
│   │   │   └── ...
│   │   └── Services/
│   │       └── SystemAlertsService.php
│   ├── config/
│   │   ├── auth.php                # Configuración de autenticación
│   │   ├── jwt.php                 # Configuración JWT
│   │   └── permission.php          # Configuración Spatie
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   │       └── RolesAndPermissionsSeeder.php
│   └── routes/
│       └── api.php                 # Rutas de la API
│
└── README.md                       # Este archivo
```

---

## 🔧 Instalación y Configuración

### Requisitos Previos
- PHP 8.2+
- Composer
- Node.js 18+
- npm o pnpm
- MySQL 8.0+
- Laravel CLI

### Backend (Laravel)

1. **Instalar dependencias:**
```bash
cd api
composer install
```

2. **Configurar variables de entorno:**
```bash
cp .env.example .env
```

Editar `.env` con tus credenciales:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_maderas_pani
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=tu_secret_key_aqui
```

3. **Generar claves:**
```bash
php artisan key:generate
php artisan jwt:secret
```

4. **Ejecutar migraciones y seeders:**
```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

5. **Crear enlace simbólico del storage:**
```bash
php artisan storage:link
```

6. **Limpiar caché de permisos:**
```bash
php artisan permission:cache-reset
```

7. **Iniciar servidor de desarrollo:**
```bash
php artisan serve
```
El backend estará disponible en `http://localhost:8000`

---

### Frontend (Vue 3)

1. **Instalar dependencias:**
```bash
cd admin
npm install
# o con pnpm
pnpm install
```

2. **Configurar variables de entorno:**
```bash
cp .env.example .env
```

Editar `.env`:
```env
VITE_API_BASE_URL=http://127.0.0.1:8000
```

3. **Iniciar servidor de desarrollo:**
```bash
npm run dev
# o con pnpm
pnpm dev
```
El frontend estará disponible en `http://localhost:5173`

---

## 🔑 Credenciales por Defecto

Después de ejecutar el seeder, usa estas credenciales para acceder:

**Usuario Administrador:**
- Email: `admin@example.com`
- Password: `password`

**Roles disponibles:**
- `admin` - Acceso completo al sistema
- `vendedor` - Acceso a ventas y clientes
- `operador` - Acceso limitado

---

## 🌐 API Endpoints

### Autenticación
```http
POST   /api/login                    # Login
POST   /api/v1/logout                # Logout
POST   /api/v1/refresh               # Refresh token
POST   /api/v1/me                    # Usuario autenticado
```

### Perfil de Usuario
```http
GET    /api/v1/profile               # Ver perfil
PUT    /api/v1/profile               # Actualizar datos
PUT    /api/v1/profile/password      # Cambiar contraseña
POST   /api/v1/profile/avatar        # Subir avatar
```

### Usuarios (ABM)
```http
GET    /api/v1/users                 # Listar usuarios
POST   /api/v1/users                 # Crear usuario
GET    /api/v1/users/{id}            # Ver usuario
PUT    /api/v1/users/{id}            # Actualizar usuario
DELETE /api/v1/users/{id}            # Eliminar usuario
```

### Clientes
```http
GET    /api/v1/clientes              # Listar
POST   /api/v1/clientes              # Crear
GET    /api/v1/clientes/{id}         # Ver
PUT    /api/v1/clientes/{id}         # Actualizar
DELETE /api/v1/clientes/{id}         # Eliminar
GET    /api/v1/clientes/{id}/cuenta-corriente  # Cuenta corriente
```

### Productos
```http
GET    /api/v1/productos             # Listar
POST   /api/v1/productos             # Crear
GET    /api/v1/productos/{id}        # Ver
PUT    /api/v1/productos/{id}        # Actualizar
DELETE /api/v1/productos/{id}        # Eliminar
```

### Proveedores
```http
GET    /api/v1/proveedores           # Listar
POST   /api/v1/proveedores           # Crear
GET    /api/v1/proveedores/{id}      # Ver
PUT    /api/v1/proveedores/{id}      # Actualizar
DELETE /api/v1/proveedores/{id}      # Eliminar
GET    /api/v1/proveedores/{id}/cuenta/resumen        # Resumen
GET    /api/v1/proveedores/{id}/cuenta/movimientos    # Movimientos
GET    /api/v1/proveedores/{id}/pagos                 # Pagos
POST   /api/v1/proveedores/{id}/pagos                 # Registrar pago
```

### Empleados
```http
GET    /api/v1/empleados             # Listar
POST   /api/v1/empleados             # Crear
GET    /api/v1/empleados/{id}        # Ver
PUT    /api/v1/empleados/{id}        # Actualizar
DELETE /api/v1/empleados/{id}        # Eliminar
GET    /api/v1/empleados/{id}/pagos  # Pagos del empleado
POST   /api/v1/empleados/{id}/pagos  # Registrar pago
```

### Ventas
```http
GET    /api/v1/ventas                # Listar
POST   /api/v1/ventas                # Crear
GET    /api/v1/ventas/{id}           # Ver
DELETE /api/v1/ventas/{id}           # Eliminar
GET    /api/v1/ventas/previsualizar-numero  # Próximo número
GET    /api/v1/ventas/{id}/pagos/resumen    # Resumen de pagos
GET    /api/v1/ventas/{id}/pagos            # Pagos de la venta
POST   /api/v1/ventas/{id}/pagos            # Registrar pago
```

### Pedidos
```http
GET    /api/v1/pedidos               # Listar
POST   /api/v1/pedidos               # Crear
GET    /api/v1/pedidos/{id}          # Ver
PUT    /api/v1/pedidos/{id}          # Actualizar
DELETE /api/v1/pedidos/{id}          # Eliminar
GET    /api/v1/pedidos-pendientes    # Solo pendientes
POST   /api/v1/pedidos/{id}/asociar-venta  # Asociar a venta
GET    /api/v1/clima                 # Consulta de clima
```

### Cheques
```http
GET    /api/v1/cheques               # Listar todos
GET    /api/v1/cheques/pendientes    # Pendientes
GET    /api/v1/cheques/historial     # Procesados
GET    /api/v1/cheques/{id}          # Ver detalle
POST   /api/v1/cheques/{id}/cobrar   # Marcar como cobrado
POST   /api/v1/cheques/{id}/rechazar # Marcar como rechazado
PATCH  /api/v1/cheques/{id}          # Actualizar datos
```

### Notificaciones
```http
GET    /api/v1/notificaciones/resumen      # Resumen con contadores
GET    /api/v1/notificaciones              # Listado completo
POST   /api/v1/notificaciones/limpiar-cache # Limpiar caché
```

### Reportes
```http
GET    /api/v1/reportes/ventas              # Reporte de ventas
GET    /api/v1/reportes/clientes            # Reporte de clientes
GET    /api/v1/reportes/productos           # Reporte de productos
GET    /api/v1/reportes/proveedores         # Reporte de proveedores
GET    /api/v1/reportes/ventas/export.csv   # Exportar ventas CSV
GET    /api/v1/reportes/ventas/export.xlsx  # Exportar ventas Excel
GET    /api/v1/reportes/full/single.xlsx    # Reporte completo Excel
```

### Métodos de Pago
```http
GET    /api/v1/metodos-pago          # Catálogo de métodos
```

---

## 🔐 Sistema de Permisos

### Módulos y Permisos

| Módulo | Permisos |
|--------|----------|
| Clientes | `clientes.index`, `clientes.store`, `clientes.update`, `clientes.destroy` |
| Productos | `productos.index`, `productos.store`, `productos.update`, `productos.destroy` |
| Proveedores | `proveedores.index`, `proveedores.store`, `proveedores.update`, `proveedores.destroy` |
| Empleados | `empleados.index`, `empleados.store`, `empleados.update`, `empleados.destroy` |
| Ventas | `ventas.index`, `ventas.store`, `ventas.show` |
| Pagos | `pagos.index`, `pagos.store` |
| Cuenta Corriente | `cta_cte.show` |
| Reportes | `reportes.view`, `reportes.export` |
| Usuarios (ABM) | `users.manage`, `users.create`, `users.edit`, `users.delete` |
| Roles | `roles.index`, `roles.store`, `roles.update`, `roles.destroy` |
| Métodos de Pago | `metodos_pago.index` |

### Asignación de Permisos

El rol **admin** tiene todos los permisos por defecto.

Para asignar permisos manualmente:
```php
use App\Models\Usuario;
use Spatie\Permission\Models\Permission;

$user = Usuario::find(1);
$user->givePermissionTo('clientes.index');
$user->syncPermissions(['clientes.index', 'ventas.index']);
```

---

## 🎨 Características del Frontend

### Tecnologías UI
- **Vuetify 3:** Componentes Material Design
- **Composition API:** Código más mantenible y reutilizable
- **File-based Routing:** Rutas automáticas basadas en estructura de carpetas
- **Auto-imports:** Componentes y composables auto-importados
- **TypeScript:** Tipado opcional para mayor seguridad

### Servicios de API

Todos los servicios usan `apiFetch` que proporciona:
- Autenticación JWT automática
- Manejo centralizado de errores
- Interceptor para tokens expirados
- Logging de requests/responses

**Ejemplo de uso:**
```javascript
import { apiFetch } from '@/services/api'

// GET request
const clientes = await apiFetch('/api/v1/clientes')

// POST request
const nuevoCliente = await apiFetch('/api/v1/clientes', {
  method: 'POST',
  body: { nombre: 'Juan Pérez', email: 'juan@example.com' }
})
```

### Stores de Pinia

**Auth Store:**
```javascript
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
authStore.login({ email, password })
authStore.logout()
console.log(authStore.isAuthenticated)
console.log(authStore.user)
```

---

## 📊 Base de Datos

### Tablas Principales

- `usuarios` - Usuarios del sistema
- `clientes` - Clientes
- `productos` - Inventario de productos
- `proveedores` - Proveedores
- `empleados` - Empleados
- `ventas` - Cabecera de ventas
- `venta_detalles` - Detalle de productos por venta
- `pagos` - Pagos recibidos/realizados
- `pedidos` - Pedidos de clientes
- `cuentas_corrientes` - Estado de cuenta de clientes
- `movimientos_cuenta` - Movimientos de cuenta corriente
- `roles` - Roles del sistema (Spatie)
- `permissions` - Permisos (Spatie)
- `model_has_roles` - Relación usuario-rol
- `model_has_permissions` - Relación usuario-permiso

---

## 🔄 Flujos de Trabajo

### Flujo de Venta

1. Crear nueva venta
2. Agregar productos con cantidad y precio
3. Seleccionar cliente
4. Obtener número de comprobante automático
5. Registrar métodos de pago:
   - Efectivo
   - Transferencia
   - Cheque (con control de vencimiento)
   - Cuenta corriente
6. Confirmar venta
7. Actualizar stock automáticamente
8. Generar movimiento en cuenta corriente (si aplica)

### Flujo de Cheque

1. Recibir cheque en pago de venta
2. Sistema registra cheque como "Pendiente"
3. Alerta automática 7 días antes del vencimiento
4. Al vencimiento:
   - Cobrar cheque → Estado "Cobrado"
   - Rechazar cheque → Estado "Rechazado"
5. Historial completo de cheques procesados

### Flujo de Pedido

1. Cliente realiza pedido
2. Sistema registra como "Pendiente"
3. Consulta de clima para planificación
4. Procesamiento → Estado "Procesando"
5. Asociar pedido a venta automáticamente
6. Estado final: "Completado" o "Cancelado"

---

## 🛡️ Seguridad

### Backend
- ✅ Autenticación JWT con refresh tokens
- ✅ Validación de datos con FormRequests
- ✅ Autorización basada en permisos (Spatie)
- ✅ Protección CSRF en formularios
- ✅ Sanitización de inputs
- ✅ Rate limiting en endpoints críticos
- ✅ Encriptación de passwords (bcrypt)
- ✅ Validación de tipos de archivo en uploads

### Frontend
- ✅ Validación de formularios en tiempo real
- ✅ Token JWT en localStorage
- ✅ Headers de autorización automáticos
- ✅ Redirección automática en sesión expirada
- ✅ Sanitización de datos antes de renderizar
- ✅ Protección de rutas por permisos

---

## 📝 Buenas Prácticas Implementadas

### Backend
- Controllers delgados con lógica en Services
- FormRequests para validación
- API Resources para formateo de respuestas
- Relaciones Eloquent optimizadas
- Eager loading para evitar N+1 queries
- Transacciones para operaciones críticas
- Logs estructurados

### Frontend
- Composition API con setup script
- Composables reutilizables
- Servicios centralizados
- Manejo de estados con Pinia
- Loading states en acciones asíncronas
- Error boundaries
- Componentes atómicos

---

## 🧪 Testing

### Backend
```bash
php artisan test
```

### Frontend
```bash
npm run test
```

---

## 📦 Build para Producción

### Backend
```bash
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Frontend
```bash
npm run build
```

Los archivos compilados estarán en `admin/dist/`

---

## 🐛 Troubleshooting

### Error: "Token inválido"
**Solución:**
```bash
# Backend
php artisan jwt:secret
php artisan config:clear

# Frontend: Hacer logout y login nuevamente
```

### Error: "Permission denied"
**Solución:**
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
```

### Error: "CORS policy"
**Solución:** Verificar `config/cors.php`:
```php
'allowed_origins' => ['http://localhost:5173'],
```

### Error: "Storage link not found"
**Solución:**
```bash
php artisan storage:link
```

### Error de Vite: "Failed to resolve import"
**Solución:**
```bash
cd admin
rm -rf node_modules
npm install
```

---

## 📚 Documentación Adicional

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Vue 3 Documentation](https://vuejs.org/)
- [Vuetify 3 Documentation](https://vuetifyjs.com/)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6/introduction)
- [JWT Auth](https://jwt-auth.readthedocs.io/)

---

## 🤝 Contribuciones

Este proyecto es privado para Maderas Pani. Para reportar bugs o solicitar features, contactar al equipo de desarrollo.

---

## 📄 Licencia

Propietario: Maderas Pani  
Todos los derechos reservados © 2025

---

## 👨‍💻 Desarrollador

**Alejandro Pintos**  
GitHub: [@Alejandro-Pintos](https://github.com/Alejandro-Pintos)

---

## 📞 Soporte

Para soporte técnico o consultas, contactar a través del repositorio o email del proyecto.

---

## 🎯 Roadmap

### Implementado ✅
- Sistema de autenticación JWT
- Gestión completa de clientes
- Gestión de productos e inventario
- Gestión de proveedores y pagos
- Gestión de empleados
- Sistema de ventas y facturación
- Gestión de pedidos
- Sistema de cheques
- Cuenta corriente
- Reportes y exportaciones
- Sistema de notificaciones
- Perfil de usuario editable
- ABM de usuarios
- Dashboard con métricas

### Futuras Mejoras 🚀
- Notificaciones push en tiempo real
- App móvil (React Native)
- Integración con sistemas de facturación electrónica
- Dashboard de BI con gráficos avanzados
- Sistema de backup automático
- Integración con WhatsApp Business
- Sistema de cotizaciones
- Gestión de garantías
- Control de múltiples sucursales
- API pública con documentación Swagger

---

**Versión:** 1.0.0  
**Última actualización:** 2 de diciembre de 2025
