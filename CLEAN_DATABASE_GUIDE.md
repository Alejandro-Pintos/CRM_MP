# Guía: Limpiar Base de Datos en Producción

## ⚠️ ADVERTENCIA
Este proceso **ELIMINARÁ TODOS LOS DATOS** excepto:
- ✅ Sistema de permisos (roles, permissions)
- ✅ Usuario administrador
- ✅ Métodos de pago por defecto

## 📋 Prerequisitos

1. **Backup de seguridad** (por si acaso):
```bash
# En el VPS
cd /ruta/al/proyecto/api
php artisan backup:database
# O manualmente con mysqldump
mysqldump -u usuario -p nombre_bd > backup_$(date +%Y%m%d_%H%M%S).sql
```

2. **Verificar que existen los permisos**:
```bash
cd /ruta/al/proyecto/api
php artisan db:seed --class=FixMissingPermissionsSeeder
```

## 🧹 Ejecutar Limpieza

### Paso 1: Conectarse al VPS
```bash
ssh usuario@ip_del_vps
cd /ruta/al/proyecto/api
```

### Paso 2: Ejecutar el Seeder
```bash
php artisan db:seed --class=CleanDatabaseSeeder
```

**Salida esperada:**
```
🧹 LIMPIANDO BASE DE DATOS...
⚠️  Esto eliminará TODOS los datos excepto admin y permisos
   ✓ Limpiada tabla: detalle_venta (X registros eliminados)
   ✓ Limpiada tabla: ventas (X registros eliminados)
   ✓ Limpiada tabla: clientes (X registros eliminados)
   ...
   ✓ Métodos de pago recreados (4 métodos)
   ✓ Usuario admin existente: admin@example.com
   ✓ Admin tiene 60 permisos asignados
✅ Base de datos limpiada exitosamente
📊 Estado final:
   - Usuarios: 1
   - Roles: X
   - Permisos: 60
   - Métodos de pago: 4
```

## 🔍 Verificación Post-Limpieza

### 1. Verificar tablas en estado correcto
```bash
php artisan tinker
```

```php
// Dentro de tinker
\DB::table('usuarios')->count();        // Debe ser 1
\DB::table('clientes')->count();        // Debe ser 0
\DB::table('productos')->count();       // Debe ser 0
\DB::table('ventas')->count();          // Debe ser 0
\DB::table('pagos')->count();           // Debe ser 0
\DB::table('permissions')->count();     // Debe ser ~60
\DB::table('metodos_pago')->count();    // Debe ser 4

// Verificar admin
$admin = \App\Models\Usuario::first();
$admin->email;                          // Ver email del admin
$admin->roles->pluck('name');           // Debe mostrar ['admin']
$admin->getAllPermissions()->count();   // Debe ser ~60

// Salir
exit
```

### 2. Probar login en frontend
```
URL: https://tu-dominio.com/login
Email: admin@example.com (o el email que viste en tinker)
Password: password (si se creó nuevo) O tu contraseña actual (si ya existía)
```

**Respuesta esperada del API:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "nombre": "Administrador",
    "email": "admin@example.com",
    "roles": ["admin"],
    "permissions": ["clientes.view", "clientes.create", ...]
  }
}
```

## 🚨 Solución de Problemas

### Error: "The role 'admin' does not exist"
```bash
php artisan db:seed --class=FixMissingPermissionsSeeder
php artisan db:seed --class=CleanDatabaseSeeder
```

### Error: Foreign key constraint
El seeder maneja esto automáticamente con `SET FOREIGN_KEY_CHECKS=0`, pero si falla:
```bash
# Verificar que no hay sesiones activas bloqueando
php artisan cache:clear
php artisan config:clear
# Reintentar
php artisan db:seed --class=CleanDatabaseSeeder
```

### Login sigue mostrando "<!DOCTYPE" error
Esto significa que el backend NO está retornando JSON. Verificar:

1. **Archivo .env correcto:**
```bash
cat .env | grep -E "(APP_URL|FRONTEND_URL|DB_)"
```

Debe mostrar:
```
APP_URL=https://api.tu-dominio.com
FRONTEND_URL=https://tu-dominio.com
DB_DATABASE=nombre_correcto
DB_USERNAME=usuario_correcto
```

2. **Verificar ruta directamente:**
```bash
curl -X POST https://api.tu-dominio.com/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

Si retorna HTML (`<!DOCTYPE`), el problema es configuración de servidor (nginx/apache), no base de datos.

3. **Revisar logs Laravel:**
```bash
tail -f storage/logs/laravel.log
```

## 📝 Después de la Limpieza

### Cambiar contraseña del admin (IMPORTANTE)
```bash
php artisan tinker
```

```php
$admin = \App\Models\Usuario::where('email', 'admin@example.com')->first();
$admin->password = bcrypt('TuNuevaContraseñaSegura123!');
$admin->save();
exit
```

### Crear nuevos datos de prueba (opcional)
Si necesitas datos demo después:
```bash
php artisan db:seed --class=TestDataSeeder
```

## ✅ Checklist Final

- [ ] Backup realizado
- [ ] Seeder ejecutado sin errores
- [ ] Verificación en tinker exitosa
- [ ] Login funciona en frontend
- [ ] API retorna JSON (no HTML)
- [ ] Contraseña de admin cambiada
- [ ] .env de producción correcto
- [ ] Logs sin errores críticos

## 🔄 Volver a Estado Original (si algo sale mal)

Si necesitas revertir:
```bash
# Restaurar backup
mysql -u usuario -p nombre_bd < backup_FECHA.sql

# O volver a correr todos los seeders
php artisan migrate:fresh --seed
```

---

**Notas:**
- Este seeder es **idempotente**: puedes ejecutarlo múltiples veces sin problemas
- **NO afecta** el esquema de la base de datos (migraciones), solo los datos
- Preserva la integridad referencial automáticamente
