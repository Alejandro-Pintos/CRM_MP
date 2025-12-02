# 🔍 DIAGNÓSTICO Y CORRECCIÓN - ERROR "No se pudo conectar al servidor"

## 📋 PROBLEMA REPORTADO

**Síntomas:**
- UI muestra: "No se pudo conectar al servidor. Verifica que el backend esté corriendo en http://127.0.0.1:8000"
- Consola del navegador muestra: `Error de conexión: No se pudo conectar al servidor`
- **PERO** el backend Laravel muestra en logs que las requests SÍ están llegando:
  ```
  2025-12-01 22:29:14 /api/v1/ventas
  2025-12-01 22:29:14 /api/v1/ventas
  ```

## 🎯 CAUSA RAÍZ IDENTIFICADA

**El problema NO es que el backend esté apagado. El problema es CORS bloqueando la lectura de la respuesta 401.**

### Explicación técnica:

1. **Frontend hace fetch a `/api/v1/ventas`** (sin token válido o sin token)
2. **Request llega al backend Laravel** (logs lo confirman)
3. **Backend responde con 401 Unauthorized** con JSON: `{"message":"Unauthenticated."}`
4. **PERO** Laravel NO está agregando headers CORS a las respuestas de error de autenticación
5. **El navegador bloquea la lectura de la respuesta** porque falta el header `Access-Control-Allow-Origin`
6. **fetch() lanza un TypeError** (error de red) en lugar de devolver el status 401
7. **apiFetch cae en el catch(networkError)** y muestra "No se pudo conectar al servidor"

### Por qué pasaba esto:

En Laravel 11, el middleware `HandleCors` está configurado globalmente, pero las **excepciones de autenticación (401) se lanzan ANTES** de que la respuesta pase por el middleware de respuesta CORS.

Resultado: El header `Access-Control-Allow-Origin` NO se agrega a las respuestas 401, y el navegador considera esto una violación de CORS.

## ✅ SOLUCIONES IMPLEMENTADAS

### 1. **api/bootstrap/app.php** - Agregar CORS en respuestas de error

```php
->withExceptions(function (Exceptions $exceptions): void {
    // Asegurar que las respuestas de error tengan headers CORS
    $exceptions->respond(function ($response, $exception, $request) {
        // Solo aplicar CORS en rutas API
        if ($request->is('api/*')) {
            $origin = $request->header('Origin');
            $allowedOrigins = config('cors.allowed_origins', []);
            
            // Si el origin está en la lista de permitidos, agregar headers CORS
            if (in_array($origin, $allowedOrigins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
                $response->headers->set('Access-Control-Max-Age', '3600');
            }
        }
        
        return $response;
    });
})->create();
```

**Qué hace:**
- Intercepta TODAS las respuestas de excepción (401, 403, 500, etc.)
- Verifica si el `Origin` del request está en la lista de orígenes permitidos
- Agrega los headers CORS necesarios MANUALMENTE a la respuesta
- Garantiza que el navegador pueda leer las respuestas de error

### 2. **admin/src/services/api.js** - Logging detallado para debugging

**Agregado logging en 4 puntos críticos:**

**a) Cuando fetch() falla con networkError:**
```javascript
} catch (networkError) {
  console.error('[apiFetch] NetworkError:', networkError)
  console.error('[apiFetch] URL:', `${API}${path}`)
  console.error('[apiFetch] Method:', method)
  console.error('[apiFetch] Headers:', { Accept: 'application/json', ...(token ? { Authorization: 'Bearer ***' } : {}) })
  
  const error = new Error(`Error de conexión: No se pudo conectar al servidor (${API}). Verifica que el backend esté corriendo.`)
  error.isNetworkError = true
  error.originalError = networkError
  throw error
}
```

**b) Cuando se recibe una respuesta (exitosa o error):**
```javascript
console.log('[apiFetch] Response received:', {
  url: `${API}${path}`,
  status: res.status,
  statusText: res.statusText,
  ok: res.ok,
  headers: {
    'content-type': res.headers.get('content-type'),
    'access-control-allow-origin': res.headers.get('access-control-allow-origin'),
  }
})
```

**c) Cuando se detecta 401:**
```javascript
if (res.status === 401) {
  console.warn('[apiFetch] 401 Unauthorized - Limpiando sesión y redirigiendo al login')
  // ... limpieza y redirect
}
```

**d) Cuando se parsea el error:**
```javascript
try {
  errorData = await res.json()
  console.log('[apiFetch] Error data (JSON):', errorData)
} catch (parseError) {
  console.error('[apiFetch] Failed to parse error response as JSON:', parseError)
  const textBody = await res.text().catch(() => 'Error desconocido')
  console.log('[apiFetch] Error data (TEXT):', textBody)
  errorData = { message: textBody }
}
```

**e) Antes de lanzar el error HTTP:**
```javascript
console.error('[apiFetch] Throwing HTTP error:', {
  status: error.status,
  message: errorMessage,
  errors: error.errors,
  data: errorData
})
```

## 🧪 CÓMO VERIFICAR LA SOLUCIÓN

### Paso 1: Reiniciar servidor Laravel

**IMPORTANTE:** Los cambios en `bootstrap/app.php` requieren reiniciar el servidor.

```bash
# En la terminal donde corre php artisan serve
# 1. Presionar Ctrl+C
# 2. Ejecutar:
cd C:\laragon\www\CRM-MP\CRM_MP\api
php artisan serve --host=127.0.0.1 --port=8000
```

### Paso 2: Limpiar caché del navegador

```
1. Abrir DevTools (F12)
2. Ir a Network tab
3. Click derecho → "Clear browser cache"
4. Refrescar la página (Ctrl+Shift+R)
```

### Paso 3: Ir a Historial de Ventas

```
URL: http://localhost:5174/ventas
```

### Paso 4: Verificar en consola del navegador

**ANTES (error de CORS):**
```
[apiFetch] NetworkError: TypeError: Failed to fetch
[apiFetch] URL: http://127.0.0.1:8000/api/v1/ventas
Error al cargar ventas: Error: Error de conexión: No se pudo conectar al servidor
```

**DESPUÉS (CORS arreglado, muestra 401 correctamente):**
```
[apiFetch] Response received: {
  url: "http://127.0.0.1:8000/api/v1/ventas",
  status: 401,
  statusText: "Unauthorized",
  ok: false,
  headers: {
    content-type: "application/json",
    access-control-allow-origin: "http://localhost:5174"  ← ✅ CORS presente
  }
}
[apiFetch] 401 Unauthorized - Limpiando sesión y redirigiendo al login
```

### Paso 5: Verificar en Network tab del navegador

**Request a /api/v1/ventas:**
```
Request URL: http://127.0.0.1:8000/api/v1/ventas?per_page=all
Request Method: GET
Status Code: 401 Unauthorized
```

**Response Headers (CRÍTICO - debe incluir):**
```
Access-Control-Allow-Origin: http://localhost:5174  ← ✅ Debe estar presente
Content-Type: application/json
```

**Response Body:**
```json
{
  "message": "Unauthenticated."
}
```

## 📊 COMPORTAMIENTOS ESPERADOS DESPUÉS DEL FIX

### Escenario 1: Sin token (no autenticado)

**Logs en consola:**
```
[apiFetch] Response received: { status: 401, ... }
[apiFetch] 401 Unauthorized - Limpiando sesión y redirigiendo al login
```

**UI:**
- Mensaje: "Sesión expirada. Redirigiendo al login..."
- Redirección automática a `/login` después de 100ms

### Escenario 2: Con token válido

**Logs en consola:**
```
[apiFetch] Response received: { status: 200, ok: true, ... }
Ventas response: { data: [...] }
```

**UI:**
- Tabla con ventas listadas correctamente
- Sin errores

### Escenario 3: Con token válido pero sin permiso `ventas.index`

**Logs en consola:**
```
[apiFetch] Response received: { status: 403, ... }
[apiFetch] Error data (JSON): { message: "No tienes permisos..." }
[apiFetch] Throwing HTTP error: { status: 403, message: "No tienes permisos..." }
```

**UI:**
- Mensaje: "No tienes permisos para ver las ventas. Contacta al administrador."

### Escenario 4: Backend realmente apagado

**Logs en consola:**
```
[apiFetch] NetworkError: TypeError: Failed to fetch
[apiFetch] URL: http://127.0.0.1:8000/api/v1/ventas
```

**UI:**
- Mensaje: "No se pudo conectar al servidor. Verifica que el backend esté corriendo en http://127.0.0.1:8000"

## 🎓 LECCIONES APRENDIDAS

### 1. **Diferencia entre error de red vs error HTTP**

- **Error de red (NetworkError):** `fetch()` lanza `TypeError`
  - Servidor apagado
  - Sin internet
  - CORS bloqueando la request o la respuesta
  - Timeout

- **Error HTTP:** `fetch()` devuelve `Response` con `res.ok === false`
  - 401 Unauthorized
  - 403 Forbidden
  - 404 Not Found
  - 500 Internal Server Error

### 2. **CORS en Laravel debe aplicarse a TODAS las respuestas**

No es suficiente con agregar `HandleCors` al middleware. Las **excepciones de autenticación** se lanzan antes del ciclo de respuesta del middleware.

**Solución:** Usar `$exceptions->respond()` para interceptar TODAS las respuestas de error y agregar headers CORS manualmente.

### 3. **Logging es crítico para debugging**

Sin los logs detallados, era imposible saber si:
- El fetch llegaba al servidor (sí llegaba)
- La respuesta tenía headers CORS (no tenía)
- El error era de red o HTTP (parecía de red pero era CORS)

### 4. **Mensajes de error deben ser precisos**

El mensaje "No se pudo conectar al servidor" era engañoso porque:
- El servidor SÍ estaba corriendo
- La request SÍ llegaba al backend
- El problema era CORS bloqueando la lectura de la respuesta

## 📝 ARCHIVOS MODIFICADOS

1. **api/bootstrap/app.php**
   - Agregado handler de excepciones con CORS headers

2. **admin/src/services/api.js**
   - Agregado logging detallado en 5 puntos críticos
   - Sin cambios en la lógica (solo debugging)

3. **admin/public/test-cors.html** (NUEVO)
   - Página de prueba para verificar CORS directamente

## ✅ CHECKLIST DE VERIFICACIÓN

- [ ] Servidor Laravel reiniciado (cambios en bootstrap/app.php)
- [ ] Caché del navegador limpiada
- [ ] Consola del navegador abierta (F12)
- [ ] Network tab abierto
- [ ] Ir a Historial de Ventas
- [ ] Verificar que `[apiFetch] Response received` muestra `access-control-allow-origin`
- [ ] Si no hay token: debe redirigir a /login
- [ ] Si hay token válido: debe cargar ventas
- [ ] Si hay token sin permisos: mensaje claro de "No tienes permisos"
- [ ] Si backend apagado: mensaje claro de "No se pudo conectar"

## 🚀 PRÓXIMOS PASOS RECOMENDADOS

1. **Después de verificar que funciona, QUITAR los logs de producción**
   - Los `console.log` son útiles para debugging, pero no deben estar en producción
   - Envolver en `if (import.meta.env.DEV)` o usar una variable de entorno

2. **Verificar permisos del usuario actual**
   ```bash
   php artisan tinker
   $user = User::find(1);
   $user->getAllPermissions()->pluck('name');
   # Debe incluir: "ventas.index"
   ```

3. **Revisar otros endpoints que puedan tener el mismo problema**
   - Cualquier endpoint con `auth:api` middleware
   - Especialmente los que requieren permisos de Spatie

4. **Considerar un interceptor global para CORS en producción**
   - Si el problema persiste, considerar un middleware personalizado
   - O usar un proxy Nginx/Apache con headers CORS

---

**Autor:** Senior Dev - Debugging Session
**Fecha:** 1 de diciembre de 2025
**Status:** ✅ Correcciones implementadas - Pendiente verificación
