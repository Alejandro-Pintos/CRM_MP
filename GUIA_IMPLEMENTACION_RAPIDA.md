# 🚀 GUÍA RÁPIDA DE IMPLEMENTACIÓN - Refactorización ERP

## 📌 Resumen Ejecutivo

Se ha completado el **diseño arquitectónico** de la refactorización del ERP para centralizar toda la lógica de negocio en el backend (Laravel), siguiendo el principio de **"Single Source of Truth"**.

### Archivos Creados

#### 📂 Backend - Servicios de Dominio
```
✅ api/app/Services/Finanzas/ChequeService.php
✅ api/app/Services/Finanzas/CuentaCorrienteService.php (refactorizado)
✅ api/app/Models/Cheque.php
✅ api/database/migrations/2025_12_01_000001_create_cheques_table.php
✅ api/app/Http/Controllers/ChequeController.php
✅ api/app/Http/Resources/ChequeResource.php
```

#### 📄 Documentación
```
✅ ARQUITECTURA_REFACTORIZACION.md (documento completo de 500+ líneas)
```

---

## 🎯 Próximos Pasos - EJECUTAR EN ORDEN

### PASO 1: Ejecutar Migraciones (5 minutos)

```bash
cd api
php artisan migrate
```

**Resultado esperado:**
```
Migration table created successfully.
Migrating: 2025_12_01_000001_create_cheques_table
Migrated:  2025_12_01_000001_create_cheques_table (XX.XXms)
```

**Verificar:**
```bash
php artisan db:show
# Debe aparecer tabla 'cheques' con 15 columnas
```

---

### PASO 2: Registrar Rutas API (10 minutos)

**Archivo:** `api/routes/api.php`

Agregar al grupo `Route::prefix('v1')`:

```php
// Cheques - Nuevos endpoints
Route::get('cheques', [ChequeController::class, 'index'])->name('cheques.index');
Route::get('cheques/historial', [ChequeController::class, 'historial'])->name('cheques.historial');
Route::get('cheques/{cheque}', [ChequeController::class, 'show'])->name('cheques.show');
Route::patch('cheques/{cheque}', [ChequeController::class, 'update'])->name('cheques.update');
Route::post('cheques/{cheque}/cobrar', [ChequeController::class, 'cobrar'])->name('cheques.cobrar');
Route::post('cheques/{cheque}/rechazar', [ChequeController::class, 'rechazar'])->name('cheques.rechazar');
```

**Agregar el import:**
```php
use App\Http\Controllers\ChequeController;
```

**Verificar:**
```bash
php artisan route:list --path=cheques
```

---

### PASO 3: Migrar Datos Existentes (CRÍTICO - 15 minutos)

Los cheques actualmente están en la tabla `pagos` con campos:
- `numero_cheque`
- `fecha_cheque`
- `fecha_cobro`
- `estado_cheque`
- `observaciones_cheque`

**Crear script de migración:**

```bash
php artisan make:command MigrarChequesExistentes
```

**Archivo:** `api/app/Console/Commands/MigrarChequesExistentes.php`

```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pago;
use App\Models\Cheque;
use App\Models\MetodoPago;

class MigrarChequesExistentes extends Command
{
    protected $signature = 'cheques:migrar';
    protected $description = 'Migrar cheques de tabla pagos a tabla cheques';

    public function handle()
    {
        $metodoChequeId = MetodoPago::where('nombre', 'Cheque')
            ->orWhere('nombre', 'LIKE', '%cheque%')
            ->first()?->id;

        if (!$metodoChequeId) {
            $this->error('No se encontró método de pago "Cheque"');
            return 1;
        }

        $pagosConCheque = Pago::with('venta')
            ->where('metodo_pago_id', $metodoChequeId)
            ->orWhereNotNull('numero_cheque')
            ->get();

        $this->info("Encontrados {$pagosConCheque->count()} pagos con cheque");

        $migrados = 0;
        $errores = 0;

        foreach ($pagosConCheque as $pago) {
            try {
                // Verificar si ya existe
                $existe = Cheque::where('pago_id', $pago->id)->exists();
                if ($existe) {
                    $this->warn("Cheque ya migrado para pago ID {$pago->id}");
                    continue;
                }

                Cheque::create([
                    'venta_id' => $pago->venta_id,
                    'cliente_id' => $pago->venta->cliente_id,
                    'pago_id' => $pago->id,
                    'numero' => $pago->numero_cheque,
                    'monto' => $pago->monto,
                    'fecha_emision' => $pago->fecha_cheque,
                    'fecha_vencimiento' => $pago->fecha_cobro,
                    'estado' => $this->mapearEstado($pago->estado_cheque),
                    'observaciones' => $pago->observaciones_cheque,
                    'created_at' => $pago->created_at,
                    'updated_at' => $pago->updated_at,
                ]);

                $migrados++;
                $this->info("✓ Migrado pago ID {$pago->id}");

            } catch (\Exception $e) {
                $errores++;
                $this->error("✗ Error en pago ID {$pago->id}: {$e->getMessage()}");
            }
        }

        $this->info("\n=== RESUMEN ===");
        $this->info("Migrados: {$migrados}");
        $this->info("Errores: {$errores}");

        return 0;
    }

    private function mapearEstado(?string $estadoAntiguo): string
    {
        if (!$estadoAntiguo) return 'pendiente';

        return match(strtolower($estadoAntiguo)) {
            'cobrado' => 'cobrado',
            'rechazado' => 'rechazado',
            default => 'pendiente',
        };
    }
}
```

**Ejecutar migración:**
```bash
php artisan cheques:migrar
```

---

### PASO 4: Probar Endpoints API (15 minutos)

**A) Listar cheques pendientes:**
```bash
curl -X GET "http://localhost/api/v1/cheques?estado=pendiente" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Respuesta esperada:**
```json
{
  "cheques": [
    {
      "id": 1,
      "numero": "12345678",
      "monto": 5000.00,
      "estado": "pendiente",
      "venta": {
        "id": 10,
        "numero": "FC-A 0001-00000010"
      },
      "cliente": {
        "id": 5,
        "nombre": "Juan Pérez"
      }
    }
  ],
  "resumen": {
    "total": 10,
    "vencidos": 2,
    "proximos_a_vencer": 3,
    "sin_fecha": 1,
    "monto_total": 50000.00
  }
}
```

**B) Cobrar un cheque:**
```bash
curl -X POST "http://localhost/api/v1/cheques/1/cobrar" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"fecha_cobro": "2025-12-01"}'
```

**C) Ver historial:**
```bash
curl -X GET "http://localhost/api/v1/cheques/historial" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### PASO 5: Actualizar Frontend Vue (30-45 minutos)

#### A) Modificar Servicio de API

**Archivo:** `admin/src/services/cheques.js` (NUEVO)

```javascript
import { apiFetch } from './api'

const BASE_PATH = '/api/v1/cheques'

export async function getCheques(params = {}) {
  const query = new URLSearchParams(params).toString()
  return await apiFetch(`${BASE_PATH}?${query}`, { method: 'GET' })
}

export async function getCheque(id) {
  return await apiFetch(`${BASE_PATH}/${id}`, { method: 'GET' })
}

export async function cobrarCheque(id, data = {}) {
  return await apiFetch(`${BASE_PATH}/${id}/cobrar`, {
    method: 'POST',
    body: data
  })
}

export async function rechazarCheque(id, data = {}) {
  return await apiFetch(`${BASE_PATH}/${id}/rechazar`, {
    method: 'POST',
    body: data
  })
}

export async function actualizarCheque(id, data) {
  return await apiFetch(`${BASE_PATH}/${id}`, {
    method: 'PATCH',
    body: data
  })
}

export async function getHistorialCheques(params = {}) {
  const query = new URLSearchParams(params).toString()
  return await apiFetch(`${BASE_PATH}/historial?${query}`, { method: 'GET' })
}
```

#### B) Actualizar Componente de Seguimiento

**Archivo:** `admin/src/pages/pagos/cheques.vue`

```vue
<script setup>
import { ref, onMounted, computed } from 'vue'
import { getCheques, getHistorialCheques, cobrarCheque, rechazarCheque } from '@/services/cheques'
import { toast } from '@/plugins/toast'

const cheques = ref([])
const historial = ref([])
const resumen = ref({})
const loading = ref(false)

const fetchCheques = async () => {
  loading.value = true
  try {
    const response = await getCheques({ estado: 'pendiente', dias_alerta: 7 })
    cheques.value = response.cheques || []
    resumen.value = response.resumen || {}
  } catch (e) {
    toast.error('Error al cargar cheques: ' + e.message)
  } finally {
    loading.value = false
  }
}

const fetchHistorial = async () => {
  loading.value = true
  try {
    const response = await getHistorialCheques()
    historial.value = response.cheques || []
  } catch (e) {
    toast.error('Error al cargar historial: ' + e.message)
  } finally {
    loading.value = false
  }
}

const marcarCobrado = async (cheque) => {
  try {
    await cobrarCheque(cheque.id, { fecha_cobro: new Date().toISOString().split('T')[0] })
    toast.success('Cheque marcado como cobrado')
    await fetchCheques()
  } catch (e) {
    toast.error('Error: ' + e.message)
  }
}

const marcarRechazado = async (cheque) => {
  try {
    const motivo = prompt('Motivo del rechazo:')
    if (!motivo) return
    
    await rechazarCheque(cheque.id, { motivo_rechazo: motivo })
    toast.success('Cheque marcado como rechazado')
    await fetchCheques()
  } catch (e) {
    toast.error('Error: ' + e.message)
  }
}

onMounted(() => {
  fetchCheques()
  fetchHistorial()
})
</script>

<template>
  <!-- EL RESTO DEL TEMPLATE PUEDE QUEDAR IGUAL -->
  <!-- Los datos ahora vienen del backend en el formato correcto -->
</template>
```

**CAMBIOS CLAVE en el template:**
- Usar `cheque.cliente.nombre` en lugar de `cheque.cliente_nombre`
- Usar `cheque.venta.numero` en lugar de `cheque.numero_venta`
- Los estados ya vienen correctos desde el backend

---

### PASO 6: Refactorizar VentaController (OPCIONAL - Backend sólido)

**Este paso es opcional pero RECOMENDADO para completar la arquitectura.**

**Crear:** `api/app/Services/Ventas/RegistrarVentaService.php`

Copiar el código del documento `ARQUITECTURA_REFACTORIZACION.md` sección "Servicios de Dominio".

**Actualizar:** `api/app/Http/Controllers/VentaController.php`

```php
public function store(VentaStoreRequest $request, RegistrarVentaService $service)
{
    $usuarioId = auth()->id();
    $cliente = Cliente::findOrFail($request->cliente_id);
    
    $venta = $service->ejecutar($cliente, $request->validated());
    
    return (new VentaResource($venta))
        ->response()
        ->setStatusCode(201);
}
```

---

## ✅ Checklist de Implementación

```markdown
- [ ] 1. Migración de tabla cheques ejecutada
- [ ] 2. Rutas API registradas en routes/api.php
- [ ] 3. Script de migración de datos ejecutado
- [ ] 4. Endpoints probados con curl/Postman
- [ ] 5. Servicio cheques.js creado en frontend
- [ ] 6. Componente cheques.vue actualizado
- [ ] 7. Prueba manual en navegador (crear venta con cheque)
- [ ] 8. Prueba manual: cobrar cheque
- [ ] 9. Prueba manual: rechazar cheque
- [ ] 10. Verificar historial de cheques
```

---

## 🔧 Troubleshooting

### Error: "Class ChequeService not found"

**Solución:**
```bash
composer dump-autoload
```

### Error: "Table cheques doesn't exist"

**Solución:**
```bash
php artisan migrate:fresh  # CUIDADO: Borra todos los datos
# O mejor:
php artisan migrate:rollback
php artisan migrate
```

### Error: "Undefined property: cliente"

**Causa:** Falta eager loading
**Solución:** Verificar que los controladores hagan:
```php
$cheques = Cheque::with(['cliente', 'venta'])->get();
```

---

## 📊 Métricas de Éxito

Después de implementar, verificar:

1. **No hay lógica de negocio en Vue**
   - Buscar en archivos `.vue`: NO debe haber cálculos de saldo, límite de crédito, etc.

2. **Todos los cambios pasan por servicios**
   - Controladores < 30 líneas por método
   - Servicios tienen todas las validaciones

3. **Consistencia de datos**
   - Cliente con 3 ventas → mismo saldo desde cualquier endpoint
   - Cheque cobrado → reduce saldo automáticamente

---

## 🎓 Capacitación del Equipo

### Para Desarrolladores Backend
- Leer `ARQUITECTURA_REFACTORIZACION.md` completo
- Entender patrón de servicios de dominio
- Practicar creando un servicio nuevo

### Para Desarrolladores Frontend
- Los servicios Vue solo hacen fetch
- No calcular nada crítico en frontend
- Confiar en el JSON del backend

---

## 🚀 Deployment

### Staging
```bash
git checkout -b feature/refactorizacion-cc-cheques
git add .
git commit -m "feat: Refactorización arquitectónica - Centralización lógica de negocio"
git push origin feature/refactorizacion-cc-cheques
```

### Producción (Después de QA)
```bash
# 1. Backup de BD
mysqldump -u root -p crm_mp > backup_antes_refactorizacion.sql

# 2. Merge a main
git checkout main
git merge feature/refactorizacion-cc-cheques

# 3. Deployment
php artisan down
git pull
composer install --no-dev
php artisan migrate --force
php artisan cheques:migrar
php artisan config:cache
php artisan route:cache
php artisan up
```

---

## 📞 Soporte

**Arquitecto:** GitHub Copilot  
**Fecha:** 1 de diciembre de 2025

**¿Problemas?** Consultar `ARQUITECTURA_REFACTORIZACION.md` para detalles completos.

**¿Dudas?** Abrir issue en el repositorio con etiqueta `refactorización`.

---

## 🎯 Próxima Fase (Opcional)

Después de estabilizar esta refactorización:

1. **Crear RegistrarPagoVentaService** (unificar pagos de ventas)
2. **Crear tests automáticos** (PHPUnit)
3. **Optimizar queries N+1** (eager loading automático)
4. **Agregar cache** (Redis para saldos de clientes)

**Total tiempo estimado implementación:** 2-3 horas para backend + frontend básico
