# 🚀 GUÍA DE DEPLOYMENT Y TROUBLESHOOTING - E.R.P MADERAS PANI

## 📋 Resumen de Problemas Resueltos

### ✅ Problema 1: "Unexpected token '<', '<!DOCTYPE' is not valid JSON"
**Síntoma:** Barra roja en dashboard con error de parseo JSON  
**Causa:** El backend devolvía HTML (error 500 o redirect) en lugar de JSON  
**Solución Implementada:**
- `apiFetch` ahora verifica `Content-Type` antes de parsear
- Mensajes de error específicos cuando se recibe HTML
- Logs detallados de URL, status y tipo de respuesta

### ✅ Problema 2: Login muestra "Error desconocido"
**Síntoma:** Login fallaba con mensaje genérico sin información útil  
**Causa:** El composable `useAuth` tragaba el error real del backend  
**Solución Implementada:**
- Mensajes específicos según tipo de error (401, network, JSON)
- Logging completo del flujo de autenticación
- Diferenciación clara entre credenciales incorrectas vs errores del servidor

### ✅ Problema 3: Ruta /api/v1/me no existía
**Síntoma:** Error 404 al cargar perfil del usuario  
**Causa:** Ruta no registrada en `api/routes/api.php`  
**Solución Implementada:**
- Agregadas rutas `/v1/me`, `/v1/logout`, `/v1/refresh`
- Login ahora devuelve usuario completo con roles y permisos
- `UserProfileResource` incluye toda la información necesaria

### ✅ Problema 4: Router guards sin debugging
**Síntoma:** Difícil diagnosticar problemas de auth en producción  
**Causa:** Falta de logs en puntos críticos  
**Solución Implementada:**
- Logs en cada navegación de ruta
- Verificación de Content-Type en `refreshUserData`
- Estado de token y usuario loggeado en cada guard

---

## 🔧 CONFIGURACIÓN DE PRODUCCIÓN

### Backend (Laravel API)

#### 1. Variables de Entorno (`.env`)

```bash
# Copiar template y editar
cp .env.production.example .env
nano .env  # o vim, code, etc.
```

**Configuraciones críticas:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com

DB_DATABASE=crm_mp_production
DB_USERNAME=tu_usuario
DB_PASSWORD=contraseña_segura

FRONTEND_URL=https://tudominio.com

# Generar con: php artisan jwt:secret
JWT_SECRET=tu_jwt_secret_generado
```

#### 2. Instalación y Setup

```bash
# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Generar keys
php artisan key:generate
php artisan jwt:secret

# Ejecutar migraciones
php artisan migrate --force

# Seed de datos iniciales (roles, permisos, admin)
php artisan db:seed --class=AdminSeeder

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### 3. Permisos de Archivos

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### 4. CORS - Verificar Configuración

Archivo: `api/config/cors.php`

```php
'allowed_origins' => array_filter([
    'http://localhost:5173',
    'http://localhost:5174',
    env('FRONTEND_URL'),    // ← Asegúrate de que esté en .env
    env('APP_URL'),
]),
```

---

### Frontend (Vue 3 + Vite)

#### 1. Variables de Entorno (`.env.production`)

```bash
# Copiar template y editar
cp .env.production.example .env.production
nano .env.production
```

**Configuración crítica:**

```env
VITE_API_BASE_URL=https://api.tudominio.com
```

⚠️ **IMPORTANTE:** NO incluir barra final `/` en la URL

#### 2. Build de Producción

```bash
# Instalar dependencias
pnpm install

# Build para producción
pnpm build

# Output: carpeta dist/
```

#### 3. Desplegar con Nginx

**Archivo de configuración Nginx:**

```nginx
server {
    listen 80;
    server_name tudominio.com www.tudominio.com;
    root /var/www/crm_mp/admin/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Caché para assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

#### 4. Verificar Build

```bash
# Verificar que .env.production se usó en el build
grep -r "VITE_API_BASE_URL" dist/

# El resultado debe mostrar la URL de producción
```

---

## 🐛 DEBUGGING EN PRODUCCIÓN

### 1. Verificar Logs del Frontend (Consola del Navegador)

Los logs críticos incluyen:

```
[apiFetch] Response received: { url, status, statusText, ok, headers }
[Guards] Navegando de: X a: Y
[useAuth] Iniciando login para: email
```

**Qué buscar:**
- ❌ Content-Type que no sea `application/json`
- ❌ Status codes 500, 404, 401 inesperados
- ✅ Token guardado correctamente
- ✅ Usuario con roles y permisos

### 2. Verificar Endpoints de la API

```bash
# Test de conectividad
curl https://api.tudominio.com/api/ping

# Test de login
curl -X POST https://api.tudominio.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Test de /me (con token)
curl -X POST https://api.tudominio.com/api/v1/me \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -H "Accept: application/json"
```

**Respuestas esperadas:**

✅ Login exitoso:
```json
{
  "access_token": "eyJ0eXAi...",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "nombre": "Admin",
    "email": "admin@example.com",
    "roles": ["admin"],
    "permissions": ["users.manage", "products.view", ...]
  }
}
```

✅ /me exitoso:
```json
{
  "data": {
    "id": 1,
    "nombre": "Admin",
    "email": "admin@example.com",
    "roles": ["admin"],
    "permissions": [...]
  }
}
```

❌ Error esperado (credenciales incorrectas):
```json
{
  "message": "Email o contraseña incorrectos"
}
```

### 3. Errores Comunes y Soluciones

#### Error: "CORS policy: No 'Access-Control-Allow-Origin' header"

**Causa:** CORS mal configurado en el backend  
**Solución:**
```bash
# En .env del backend
FRONTEND_URL=https://tudominio.com

# Limpiar cache
php artisan config:clear
php artisan config:cache
```

#### Error: "Network Error" o "Failed to fetch"

**Causa:** Backend caído o URL incorrecta  
**Solución:**
1. Verificar que el backend esté corriendo: `php artisan serve` o servicio web activo
2. Verificar `VITE_API_BASE_URL` en frontend
3. Verificar DNS/firewall

#### Error: "Unexpected token '<', '<!DOCTYPE'"

**Causa:** Endpoint devolviendo HTML en lugar de JSON  
**Debugging:**
1. Ver logs de consola: buscar `[apiFetch] Content-Type:` 
2. Ver la respuesta completa en Network tab
3. Verificar logs del backend Laravel: `storage/logs/laravel.log`

**Soluciones:**
- Si es error 500: revisar logs de Laravel
- Si es redirect: asegurar que la ruta existe y no requiere autenticación HTML
- Verificar que el endpoint esté en `api/routes/api.php`

#### Error: "Sesión expirada" inmediatamente después del login

**Causa:** Token no se está guardando o validando correctamente  
**Debugging:**
```javascript
// En consola del navegador
console.log(localStorage.getItem('accessToken'))
console.log(localStorage.getItem('userData'))
```

**Soluciones:**
- Verificar que login devuelve `access_token`
- Verificar que `apiFetch` incluye el header `Authorization: Bearer ...`
- Limpiar localStorage: `localStorage.clear()` y volver a hacer login

---

## 📊 CHECKLIST DE DEPLOYMENT

### Backend
- [ ] `.env` configurado correctamente
- [ ] `APP_ENV=production` y `APP_DEBUG=false`
- [ ] Database migrada: `php artisan migrate --force`
- [ ] Seed inicial ejecutado: `php artisan db:seed --class=AdminSeeder`
- [ ] Caches generados: `config:cache`, `route:cache`, `view:cache`
- [ ] Permisos de archivos correctos en `storage/` y `bootstrap/cache/`
- [ ] CORS configurado con `FRONTEND_URL`
- [ ] SSL/HTTPS configurado

### Frontend
- [ ] `.env.production` con `VITE_API_BASE_URL` correcto
- [ ] Build ejecutado: `pnpm build`
- [ ] Carpeta `dist/` desplegada en servidor web
- [ ] Nginx configurado con `try_files` para SPA routing
- [ ] SSL/HTTPS configurado
- [ ] Test de login funcional
- [ ] Test de navegación post-login funcional

---

## 🔐 SEGURIDAD

### Producción

1. **NUNCA** subir `.env` a Git
2. **NUNCA** usar `APP_DEBUG=true` en producción
3. Usar contraseñas seguras para DB
4. Rotar `JWT_SECRET` periódicamente
5. Habilitar HTTPS/SSL obligatorio
6. Limitar acceso SSH al servidor
7. Configurar firewall (solo puertos 80, 443, 22)

---

## 📞 SOPORTE

Si encuentras problemas no cubiertos en esta guía:

1. Revisar logs del backend: `tail -f storage/logs/laravel.log`
2. Revisar consola del navegador (F12 → Console)
3. Revisar Network tab para ver requests/responses reales
4. Documentar el error con screenshots y logs
5. Contactar al equipo de desarrollo

---

**Fecha de última actualización:** 3 de diciembre de 2025  
**Versión:** 1.0.0
