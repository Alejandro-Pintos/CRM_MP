# 🚀 Guía de Inicio Rápido - Módulo de Empleados

Esta guía te ayudará a levantar el sistema y probar el módulo de empleados recién implementado.

## 📋 Requisitos Previos

- Laragon con PHP 8.x y MySQL
- Node.js 18+ y npm/pnpm
- Composer instalado

## 🔧 Paso 1: Iniciar el Backend (Laravel)

### 1.1 Verificar Base de Datos

Asegúrate de que el servicio MySQL esté corriendo en Laragon.

### 1.2 Configurar Variables de Entorno

El archivo `.env` ya debería estar configurado. Verifica que tenga:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_mp
DB_USERNAME=root
DB_PASSWORD=
```

### 1.3 Ejecutar Migraciones (si no se hizo antes)

```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan migrate
```

### 1.4 Ejecutar Seeders (si no se hizo antes)

```powershell
php artisan db:seed
```

Esto creará:
- Usuario admin: `admin@example.com` / `secret123`
- Permisos del sistema
- Métodos de pago básicos

### 1.5 Iniciar Servidor Laravel

Laragon debería servir automáticamente la API en:
```
http://localhost/api/
```

Si prefieres usar el servidor de desarrollo de Laravel:
```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan serve --host=127.0.0.1 --port=8000
```

**Verificar que funciona:**
```powershell
# Prueba rápida
curl http://localhost/api/v1/empleados
```

Deberías ver un error 401 (Unauthenticated) - esto es correcto, significa que la API está funcionando.

---

## 🎨 Paso 2: Iniciar el Frontend (Vue 3)

### 2.1 Instalar Dependencias (solo primera vez)

```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\admin
pnpm install
```

Si no tienes `pnpm`:
```powershell
npm install -g pnpm
```

### 2.2 Configurar Variables de Entorno

Verifica que el archivo `.env` en `/admin` tenga la URL correcta del API:

```env
VITE_API_URL=http://localhost/api
```

### 2.3 Iniciar Servidor de Desarrollo

```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\admin
pnpm dev
```

El frontend debería iniciarse en:
```
http://localhost:5173
```

---

## 🧪 Paso 3: Probar el Módulo de Empleados

### 3.1 Iniciar Sesión

1. Abre el navegador en `http://localhost:5173`
2. Usa las credenciales:
   - **Email:** `admin@example.com`
   - **Password:** `secret123`

### 3.2 Navegar al Módulo de Empleados

1. En el menú lateral, ve a la sección **CATÁLOGO Y RECURSOS**
2. Haz clic en **Empleados** (icono de equipo)

### 3.3 Crear un Empleado de Prueba

1. Haz clic en el botón **+ Nuevo Empleado**
2. Completa el formulario:
   - **Nombre Completo:** Carlos Martínez
   - **DNI/CUIT:** 20123456789
   - **Teléfono:** 3564123456
   - **Email:** carlos@example.com
   - **Puesto:** Operario de Producción
   - **Dirección:** Calle Falsa 123
   - **Notas:** Empleado de confianza
   - **Estado:** Activo (checkbox marcado)
3. Haz clic en **Guardar**

### 3.4 Registrar un Pago al Empleado

1. En la tabla de empleados, busca al empleado recién creado
2. Haz clic en el icono de **$ (Ver pagos)** (botón azul)
3. En el diálogo que se abre, haz clic en **+ Registrar Pago**
4. Completa el formulario de pago:
   - **Fecha de Pago:** 01/12/2025
   - **Monto:** 150000
   - **Concepto:** Sueldo
   - **Método de Pago:** Efectivo (opcional)
   - **Observaciones:** Pago mensual diciembre 2025
5. Haz clic en **Guardar Pago**

### 3.5 Verificar el Historial de Pagos

Deberías ver:
- ✅ Tarjeta "Total de Pagos": 1
- ✅ Tarjeta "Monto Total": $150.000,00
- ✅ Tabla con el pago registrado

### 3.6 Probar Otras Funcionalidades

**Buscar Empleado:**
- Usa el campo de búsqueda en la parte superior
- Prueba buscar por nombre, documento, teléfono o puesto

**Editar Empleado:**
- Haz clic en el icono de lápiz (editar)
- Modifica algún campo
- Guarda los cambios

**Eliminar Pago:**
- En el historial de pagos, haz clic en el icono de basura
- Confirma la eliminación

**Desactivar Empleado:**
- Edita el empleado
- Desmarca el checkbox "Empleado Activo"
- Guarda

**Eliminar Empleado:**
- Haz clic en el icono de basura (rojo)
- Confirma la eliminación
- El empleado se marca como eliminado pero su historial se mantiene

---

## 🧰 Resolución de Problemas

### Error: "SQLSTATE[HY000] [1049] Unknown database"

**Solución:**
```powershell
# En MySQL, crear la base de datos
mysql -u root
CREATE DATABASE crm_mp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;

# Luego ejecutar migraciones
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan migrate
```

### Error: "Class 'App\Models\Empleado' not found"

**Solución:**
```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\api
composer dump-autoload
```

### Error: Frontend no conecta con API

**Solución:**
1. Verifica que el backend esté corriendo
2. Revisa la URL en `admin/.env`:
   ```env
   VITE_API_URL=http://localhost/api
   ```
3. Reinicia el servidor de Vite:
   ```powershell
   cd c:\laragon\www\CRM-MP\CRM_MP\admin
   pnpm dev
   ```

### Error: "Unauthenticated" al hacer peticiones

**Solución:**
1. Cierra sesión en el frontend
2. Vuelve a iniciar sesión con las credenciales de admin
3. Si persiste, limpia el localStorage del navegador

### Error: CORS al hacer peticiones desde el frontend

**Solución:**
Verifica en `api/config/cors.php` que esté configurado correctamente:
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://localhost:5173'],
```

---

## 📊 Verificar que Todo Funciona

### Checklist de Funcionalidades

Backend:
- [ ] API responde en `/api/v1/empleados`
- [ ] Se puede crear un empleado
- [ ] Se puede listar empleados
- [ ] Se puede actualizar un empleado
- [ ] Se puede eliminar un empleado
- [ ] Se puede registrar un pago
- [ ] Se puede listar pagos de un empleado
- [ ] Se puede eliminar un pago

Frontend:
- [ ] Menú muestra opción "Empleados"
- [ ] Vista de empleados carga correctamente
- [ ] Se puede crear empleado desde el formulario
- [ ] Se puede editar empleado
- [ ] Se puede buscar empleados
- [ ] Se puede ver historial de pagos
- [ ] Se puede registrar nuevo pago
- [ ] Se pueden eliminar pagos
- [ ] Se puede eliminar empleado

---

## 🔍 Comandos Útiles para Depuración

### Ver logs del backend
```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\api
tail -f storage/logs/laravel.log
```

### Ver rutas registradas
```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan route:list --path=empleados
```

### Verificar permisos creados
```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan tinker --execute="echo json_encode(Spatie\Permission\Models\Permission::where('name', 'like', 'empleados%')->pluck('name'), JSON_PRETTY_PRINT);"
```

### Limpiar cache
```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Verificar estado de la base de datos
```powershell
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan migrate:status
```

---

## 📚 Recursos Adicionales

- **Documentación Completa:** Ver `MODULO_EMPLEADOS_COMPLETADO.md`
- **Ejemplos de API:** Ver `EJEMPLOS_API_EMPLEADOS.md`
- **Estructura del Proyecto:** Ver estructura de archivos en la raíz

---

## 🎯 Próximos Pasos

Una vez que hayas verificado que todo funciona:

1. **Crear Empleados Reales**
   - Registra los empleados reales de la empresa
   
2. **Registrar Pagos Históricos** (opcional)
   - Si deseas, puedes registrar pagos anteriores
   
3. **Configurar Permisos** (opcional)
   - Crea roles específicos con permisos limitados
   - Asigna usuarios a esos roles

4. **Personalizar Conceptos de Pago** (opcional)
   - Edita `admin/src/pages/empleados/index.vue`
   - Modifica el array `conceptosPago` según tus necesidades

---

## ✅ ¡Todo Listo!

Si llegaste hasta aquí y todas las pruebas funcionaron correctamente, el módulo de empleados está **100% funcional** y listo para usar en producción.

---

**¿Necesitas ayuda?**
Revisa los logs en `api/storage/logs/laravel.log` y la consola del navegador para ver detalles de cualquier error.
