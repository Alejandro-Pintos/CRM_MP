# CRM-MP - Sistema de Gestión Empresarial

<div align="center">
  
  [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
  [![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel)](https://laravel.com)
  [![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?logo=vue.js)](https://vuejs.org)
  [![Vuetify](https://img.shields.io/badge/Vuetify-3.x-1867C0?logo=vuetify)](https://vuetifyjs.com)
  
</div>

## 📋 Descripción

CRM-MP es un sistema integral de gestión empresarial desarrollado con tecnologías modernas, diseñado para optimizar la administración de clientes, proveedores, ventas, compras, inventarios y finanzas. Ofrece una interfaz intuitiva y profesional para empresas que buscan digitalizar y centralizar sus operaciones comerciales.

---

## ✨ Características Principales

### 🧑‍💼 Gestión de Clientes
- ✅ CRUD completo de clientes con datos de contacto
- ✅ Historial de ventas por cliente
- ✅ Sistema de cuenta corriente con seguimiento de deuda
- ✅ Consulta de estado de cuenta con movimientos detallados
- ✅ Filtros avanzados y búsqueda rápida
- ✅ Exportación de datos a Excel/CSV

### 🏢 Gestión de Proveedores
- ✅ Administración de proveedores con datos completos
- ✅ Registro de compras a proveedores con detalles de items
- ✅ Sistema de pagos con múltiples métodos y conceptos
- ✅ Control de cheques emitidos
- ✅ Ranking por participación en ventas
- ✅ Estado de cuenta con movimientos de débitos y créditos

### 💰 Gestión de Ventas
- ✅ Registro de ventas con múltiples productos
- ✅ Cálculo automático de subtotales, impuestos y descuentos
- ✅ Indicador reactivo de subtotal por producto
- ✅ Múltiples métodos de pago: Efectivo, Transferencia, Débito, Crédito, Cheque, Cuenta Corriente
- ✅ Validación de datos de cheques (banco, número, fecha)
- ✅ Control de estado de pago (Pagado, Parcial, Pendiente)
- ✅ Historial completo con filtros avanzados

### 🛒 Gestión de Compras
- ✅ Registro de compras con detalles de items editables
- ✅ Edición y eliminación de items antes de confirmar
- ✅ Campos editables: descripción, cantidad, precio, descuento, impuestos
- ✅ Validación para mantener al menos 1 item
- ✅ Cálculo automático de totales
- ✅ Estados: Pendiente, Pagado, Anulado

### 💳 Sistema de Pagos
- ✅ Registro de pagos a proveedores
- ✅ Múltiples conceptos: Factura, Anticipo, Cancelación, Devolución
- ✅ Integración con cuenta corriente
- ✅ Historial con filtros por fecha y estado

### 🏦 Gestión de Cheques
- **Cheques Recibidos** (de clientes):
  - Estados: Cartera, Depositado, Rechazado, Endosado
  - Validación de fechas de pago
  - Gestión de acciones según estado
- **Cheques Emitidos** (a proveedores):
  - Estados: Emitido, Cobrado, Anulado
  - Control de números de cheques
  - Integración con pagos

### 📊 Reportes y Análisis
- ✅ **Ranking de Clientes**: Top clientes por monto de compras
- ✅ **Ranking de Productos**: Productos más vendidos con estadísticas
- ✅ **Ranking de Proveedores**: Participación en ventas, compras e ingresos
- ✅ **Reporte de Ventas**: Análisis por período con métodos de pago
- ✅ Gráficos interactivos con Chart.js
- ✅ Exportación a Excel/CSV de todos los reportes

### 🏭 Gestión de Productos
- ✅ Catálogo completo de productos
- ✅ Asignación de proveedores
- ✅ Control de stock
- ✅ Precios y descripciones
- ✅ Estados activo/inactivo

### 👥 Gestión de Usuarios y Permisos
- ✅ Sistema de roles y permisos con Spatie Permission
- ✅ Autenticación JWT
- ✅ Control de acceso granular por módulo
- ✅ Administración de empleados

### 📱 Características de la Interfaz
- ✅ Dashboard con estadísticas en tiempo real
- ✅ Diseño responsive para móviles y tablets
- ✅ Tema claro/oscuro
- ✅ Notificaciones toast para feedback inmediato
- ✅ Validación en tiempo real de formularios
- ✅ Componentes reutilizables
- ✅ Manual de usuario integrado con guías por módulo

---

## 🛠️ Stack Tecnológico

### Backend
- **Framework**: Laravel 12.x
- **Base de Datos**: MySQL 8.0+
- **Autenticación**: JWT (tymon/jwt-auth)
- **Permisos**: Spatie Laravel Permission
- **Exportaciones**: Maatwebsite Laravel Excel
- **API**: RESTful API con Laravel Resources
- **Validación**: Form Requests de Laravel

### Frontend
- **Framework**: Vue.js 3.x (Composition API)
- **UI Framework**: Vuetify 3.x (Material Design)
- **Gestión de Estado**: Pinia
- **Router**: Vue Router 4.x
- **HTTP Client**: Fetch API
- **Gráficos**: Chart.js
- **Build Tool**: Vite
- **Notificaciones**: Vue Toastification

### DevOps
- **Contenedores**: Docker & Docker Compose
- **Servidor Web**: Nginx
- **Control de Versiones**: Git & GitHub
- **Desarrollo Local**: Laragon

---

## 📦 Instalación

### Requisitos Previos
- PHP >= 8.2
- Composer
- Node.js >= 18.x
- npm o pnpm
- MySQL >= 8.0
- Git

### Backend (Laravel API)

1. **Clonar el repositorio**
```bash
git clone https://github.com/Alejandro-Pintos/CRM_MP.git
cd CRM_MP/api
```

2. **Instalar dependencias de PHP**
```bash
composer install
```

3. **Configurar el archivo .env**
```bash
cp .env.example .env
```

Editar `.env` con tu configuración de base de datos:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_mp
DB_USERNAME=root
DB_PASSWORD=
```

4. **Generar clave de aplicación y JWT**
```bash
php artisan key:generate
php artisan jwt:secret
```

5. **Ejecutar migraciones y seeders**
```bash
php artisan migrate --seed
```

6. **Iniciar servidor de desarrollo**
```bash
php artisan serve
```

El backend estará disponible en `http://localhost:8000`

### Frontend (Vue.js Admin)

1. **Navegar al directorio del frontend**
```bash
cd ../admin
```

2. **Instalar dependencias**
```bash
npm install
# o con pnpm
pnpm install
```

3. **Configurar variables de entorno**
```bash
cp .env.example .env
```

Editar `.env`:
```env
VITE_API_BASE_URL=http://127.0.0.1:8000
```

4. **Iniciar servidor de desarrollo**
```bash
npm run dev
# o con pnpm
pnpm dev
```

El frontend estará disponible en `http://localhost:5173`

### Credenciales por Defecto
- **Email**: admin@example.com
- **Password**: password

---

## 🐳 Instalación con Docker

```bash
# Backend
cd api
docker-compose -f docker-compose.dev.yml up -d

# Frontend
cd ../admin
docker-compose -f docker-compose.dev.yml up -d
```

---

## 📁 Estructura del Proyecto

```
CRM_MP/
├── api/                          # Backend Laravel
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/      # Controladores de la API
│   │   │   ├── Requests/         # Form Requests de validación
│   │   │   └── Resources/        # API Resources
│   │   ├── Models/               # Modelos Eloquent
│   │   ├── Services/             # Lógica de negocio
│   │   └── Exports/              # Exportaciones Excel
│   ├── database/
│   │   ├── migrations/           # Migraciones de BD
│   │   └── seeders/              # Seeders
│   ├── routes/
│   │   └── api.php               # Rutas de la API
│   └── config/                   # Configuraciones
│
├── admin/                        # Frontend Vue.js
│   ├── src/
│   │   ├── pages/                # Páginas/Vistas
│   │   │   ├── clientes/
│   │   │   ├── proveedores/
│   │   │   ├── ventas/
│   │   │   ├── productos/
│   │   │   └── reportes/
│   │   ├── components/           # Componentes reutilizables
│   │   ├── layouts/              # Layouts de la aplicación
│   │   ├── router/               # Configuración de rutas
│   │   ├── stores/               # Stores de Pinia
│   │   ├── services/             # Servicios de API
│   │   └── composables/          # Composables de Vue
│   ├── public/                   # Archivos estáticos
│   └── vite.config.js            # Configuración de Vite
│
├── LICENSE                       # Licencia MIT
└── README.md                     # Este archivo
```

---

## 🔌 API Endpoints Principales

### Autenticación
- `POST /api/login` - Iniciar sesión
- `POST /api/v1/logout` - Cerrar sesión
- `POST /api/v1/me` - Obtener usuario autenticado
- `POST /api/v1/refresh` - Refrescar token

### Clientes
- `GET /api/v1/clientes` - Listar clientes
- `POST /api/v1/clientes` - Crear cliente
- `GET /api/v1/clientes/{id}` - Ver cliente
- `PUT /api/v1/clientes/{id}` - Actualizar cliente
- `DELETE /api/v1/clientes/{id}` - Eliminar cliente
- `GET /api/v1/clientes/{id}/cuenta` - Estado de cuenta

### Proveedores
- `GET /api/v1/proveedores` - Listar proveedores
- `POST /api/v1/proveedores` - Crear proveedor
- `GET /api/v1/proveedores/{id}/compras` - Compras del proveedor
- `POST /api/v1/proveedores/{id}/compras` - Registrar compra
- `GET /api/v1/proveedores/{id}/pagos` - Pagos del proveedor
- `POST /api/v1/proveedores/{id}/pagos` - Registrar pago

### Ventas
- `GET /api/v1/ventas` - Listar ventas
- `POST /api/v1/ventas` - Registrar venta
- `GET /api/v1/ventas/{id}` - Ver detalle de venta

### Productos
- `GET /api/v1/productos` - Listar productos
- `POST /api/v1/productos` - Crear producto
- `PUT /api/v1/productos/{id}` - Actualizar producto

### Reportes
- `GET /api/v1/reportes/clientes` - Ranking de clientes
- `GET /api/v1/reportes/productos` - Ranking de productos
- `GET /api/v1/reportes/proveedores` - Ranking de proveedores
- `GET /api/v1/reportes/ventas` - Reporte de ventas

### Métodos de Pago
- `GET /api/v1/metodos-pago` - Listar métodos disponibles

---

## 🔐 Sistema de Permisos

El sistema utiliza permisos granulares por módulo:

- `clientes.*` - CRUD de clientes
- `proveedores.*` - CRUD de proveedores
- `proveedores.compras.*` - Gestión de compras
- `proveedores.pagos.*` - Gestión de pagos
- `productos.*` - CRUD de productos
- `ventas.*` - Gestión de ventas
- `reportes.view` - Ver reportes
- `reportes.export` - Exportar reportes
- `usuarios.*` - Gestión de usuarios
- `roles.*` - Gestión de roles

---

## 🧪 Testing

```bash
# Backend
cd api
php artisan test

# Frontend
cd admin
npm run test
```

---

## 📝 Scripts de Utilidad

En el directorio `api/` se incluyen scripts PHP para tareas administrativas:

- `agregar-permisos-proveedores.php` - Agregar permisos faltantes
- `listar-usuarios-permisos.php` - Listar usuarios y sus permisos
- `actualizar-estados-pago.php` - Actualizar estados de pago de ventas

---

## 🚀 Deployment

### Producción

1. **Backend**
```bash
cd api
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

2. **Frontend**
```bash
cd admin
npm run build
```

Los archivos estáticos se generarán en `admin/dist/`

### Docker Production

```bash
docker-compose -f docker-compose.prod.yml up -d
```

---

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

---

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo [LICENSE](LICENSE) para más detalles.

---

## 👥 Desarrolladores

<table>
  <tr>
    <td align="center">
      <a href="https://github.com/Alejandro-Pintos">
        <img src="https://github.com/Alejandro-Pintos.png" width="100px;" alt="Alejandro Pintos"/><br />
        <sub><b>Alejandro Pintos</b></sub>
      </a><br />
      <a href="https://github.com/Alejandro-Pintos" title="GitHub">
        <img src="https://img.shields.io/badge/GitHub-100000?style=for-the-badge&logo=github&logoColor=white" />
      </a><br />
      <a href="https://www.linkedin.com/in/alejandropintos" title="LinkedIn">
        <img src="https://img.shields.io/badge/LinkedIn-0077B5?style=for-the-badge&logo=linkedin&logoColor=white" />
      </a>
    </td>
    <td align="center">
      <a href="https://github.com/marcelo-coronel">
        <img src="https://github.com/marcelo-coronel.png" width="100px;" alt="Marcelo Hugo Coronel"/><br />
        <sub><b>Marcelo Hugo Coronel</b></sub>
      </a><br />
      <a href="https://github.com/marcelo-coronel" title="GitHub">
        <img src="https://img.shields.io/badge/GitHub-100000?style=for-the-badge&logo=github&logoColor=white" />
      </a><br />
      <a href="https://www.linkedin.com/in/marcelo-coronel" title="LinkedIn">
        <img src="https://img.shields.io/badge/LinkedIn-0077B5?style=for-the-badge&logo=linkedin&logoColor=white" />
      </a>
    </td>
  </tr>
</table>

---

## 📧 Contacto

Para soporte o consultas:
- **Email**: [soporte.crmmp@gmail.com](mailto:soporte.crmmp@gmail.com)
- **GitHub Issues**: [Reportar un problema](https://github.com/Alejandro-Pintos/CRM_MP/issues)

---

## 🙏 Agradecimientos

- [Laravel](https://laravel.com) - Framework PHP
- [Vue.js](https://vuejs.org) - Framework JavaScript
- [Vuetify](https://vuetifyjs.com) - UI Framework
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) - Sistema de permisos
- [Chart.js](https://www.chartjs.org) - Gráficos
- Comunidad open source

---

<div align="center">
  
  **Desarrollado con dedicación por Alejandro Pintos & Marcelo Hugo Coronel**
  
  © 2025 CRM-MP. Todos los derechos reservados.
  
</div>
