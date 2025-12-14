# Solución al Error de Permisos en Compras de Proveedores

## Problema
El usuario recibe el error "User does not have the right permissions" al intentar crear una compra a un proveedor.

## Causa
Los permisos `proveedores.compras.*` no existían en la base de datos. Se han creado correctamente pero el token JWT actual del usuario no incluye estos nuevos permisos.

## Permisos Agregados
Se han creado y asignado al rol 'admin' los siguientes permisos:
- `proveedores.compras.index` - Ver listado de compras
- `proveedores.compras.store` - Crear nueva compra
- `proveedores.compras.show` - Ver detalle de compra
- `proveedores.compras.destroy` - Eliminar compra
- `proveedores.pagos.show` - Ver detalle de pago (faltante)

## Solución

### Opción 1: Cerrar Sesión y Volver a Iniciar (RECOMENDADO)
1. En la aplicación web, hacer clic en el menú de usuario (esquina superior derecha)
2. Seleccionar "Cerrar Sesión"
3. Volver a iniciar sesión con las mismas credenciales
4. Los nuevos permisos estarán disponibles automáticamente

### Opción 2: Limpiar Caché del Navegador
1. Presionar F12 para abrir las herramientas de desarrollo
2. Ir a la pestaña "Application" (Chrome) o "Storage" (Firefox)
3. En el panel izquierdo, expandir "Local Storage"
4. Seleccionar el dominio de la aplicación
5. Eliminar las siguientes claves:
   - `crmmp:token`
   - `userData`
6. Recargar la página (F5)
7. Iniciar sesión nuevamente

### Opción 3: Usar la Consola del Navegador
1. Presionar F12 para abrir las herramientas de desarrollo
2. Ir a la pestaña "Console"
3. Ejecutar el siguiente código:
```javascript
localStorage.removeItem('crmmp:token');
localStorage.removeItem('userData');
location.reload();
```
4. Iniciar sesión nuevamente

## Verificación
Después de cerrar sesión y volver a iniciar, el usuario debería poder:
- Registrar nuevas compras a proveedores
- Ver el listado de compras
- Ver detalles de compras existentes
- Eliminar compras (si es necesario)

## Cambios Realizados en el Backend

### 1. Script: `agregar-permisos-proveedores.php`
Creado para agregar los permisos faltantes a la base de datos y asignarlos al rol admin.

### 2. Seeder: `RolesAndPermissionsSeeder.php`
Actualizado para incluir permanentemente los permisos de compras y pagos de proveedores:
```php
$proveedoresCompras = [
    'proveedores.compras.index',
    'proveedores.compras.store',
    'proveedores.compras.show',
    'proveedores.compras.destroy'
];

$proveedoresPagos = [
    'proveedores.pagos.index',
    'proveedores.pagos.store',
    'proveedores.pagos.show',
    'proveedores.pagos.destroy'
];
```

### 3. Auth Store: `auth.js`
Agregada acción `refreshPermissions()` para actualizar permisos sin cerrar sesión (uso futuro).

## Notas Técnicas
- Los tokens JWT incluyen los permisos del usuario en el momento de generación
- Los permisos NO se actualizan automáticamente en tokens existentes
- Es necesario generar un nuevo token (cerrar/iniciar sesión) para obtener los nuevos permisos
- La caché de permisos de Laravel se ha limpiado correctamente en el backend
