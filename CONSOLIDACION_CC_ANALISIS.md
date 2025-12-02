# 📊 ANÁLISIS COMPLETO DE CONSOLIDACIÓN - SISTEMA CUENTA CORRIENTE

**Fecha:** Generado automáticamente  
**Estado:** 🔴 ANÁLISIS PRE-IMPLEMENTACIÓN (NO APLICAR CAMBIOS AÚN)  
**Objetivo:** Unificar fórmulas y agregar validaciones estrictas en todo el sistema

---

## 🎯 PROBLEMA IDENTIFICADO

El sistema actual tiene **INCONSISTENCIAS GRAVES** en el cálculo de saldos/deudas:

1. **Campos duplicados:** Tabla `movimientos_cuenta_corriente` tiene TANTO `monto` como `debe/haber`
2. **Fórmulas diferentes:** Algunos métodos usan `sum('monto')`, otros usan `sum('debe') - sum('haber')`
3. **Sin validaciones:** Nada impide estados imposibles:
   - Saldo > Límite de crédito
   - Disponible negativo
   - Sobrepagos
4. **Resultado:** Valores diferentes en distintas vistas del frontend

### Evidencia del Problema

**Imagen proporcionada por usuario:**
- Cliente con saldo = $6,000,000
- Límite de crédito = $5,000,000
- **Crédito disponible = -$3,000,000** ❌ IMPOSIBLE

---

## 📋 INVENTARIO COMPLETO DE FÓRMULAS

### 1️⃣ `app/Models/Cliente.php`

#### **Método: `calcularSaldoReal()`** (líneas 60-78)

**Código Actual:**
```php
public function calcularSaldoReal()
{
    $ventas = $this->movimientosCuentaCorriente()
        ->where('tipo', 'venta')
        ->sum('monto'); // ❌ USA CAMPO 'monto'
    
    $pagos = $this->movimientosCuentaCorriente()
        ->where('tipo', 'pago')
        ->sum('monto'); // ❌ USA CAMPO 'monto'
    
    return round((float)$ventas - (float)$pagos, 2);
}
```

**Problema:**
- ✅ La fórmula conceptual es correcta (ventas - pagos)
- ❌ Usa campo `monto` que tiene convención de signos inconsistente
- ❌ Debería usar campos `debe` y `haber` exclusivamente

**Estado:** 🔴 CRÍTICO - Este método es usado en muchos lugares

---

#### **Método: `recalcularSaldo()`** (líneas 88-99)

**Código Actual:**
```php
public function recalcularSaldo()
{
    $saldoCalculado = $this->calcularSaldoReal(); // ⚠️ Llama al método con bug
    
    if (abs((float)$this->saldo_actual - $saldoCalculado) > 0.01) {
        \Log::info("Recalculando saldo cliente #{$this->id}");
        $this->saldo_actual = $saldoCalculado;
        return $this->save();
    }
    
    return false;
}
```

**Problema:**
- Depende de `calcularSaldoReal()` que tiene el bug de campo `monto`

**Estado:** 🟡 INDIRECTO - Se arreglará cuando arreglemos `calcularSaldoReal()`

---

### 2️⃣ `app/Services/CuentaCorrienteService.php`

#### **Método: `calcularDeudaCCVenta()`** (líneas 138-149)

**Código Actual:**
```php
public function calcularDeudaCCVenta(int $ventaId): float
{
    $debe = MovimientoCuentaCorriente::where('venta_id', $ventaId)
        ->where('tipo', 'venta')
        ->sum('debe'); // ✅ USA CAMPO 'debe'
    
    $haber = MovimientoCuentaCorriente::where('venta_id', $ventaId)
        ->where('tipo', 'pago')
        ->sum('haber'); // ✅ USA CAMPO 'haber'
    
    return round($debe - $haber, 2);
}
```

**Estado:** ✅ **CORRECTO** - Este es el patrón GOLD STANDARD que debemos replicar

---

#### **Método: `obtenerDeudaPorVenta()`** (líneas 150-175)

**Código Actual:**
```php
public function obtenerDeudaPorVenta(int $clienteId): array
{
    $movimientos = MovimientoCuentaCorriente::where('cliente_id', $clienteId)
        ->whereNotNull('venta_id')
        ->get()
        ->groupBy('venta_id')
        ->map(function ($movs, $ventaId) {
            $debe = $movs->where('tipo', 'venta')->sum('debe');  // ✅ CORRECTO
            $haber = $movs->where('tipo', 'pago')->sum('haber'); // ✅ CORRECTO
            
            return [
                'venta_id' => $ventaId,
                'saldo'    => round($debe - $haber, 2), // ✅ Fórmula correcta
            ];
        })
        ->filter(fn($item) => $item['saldo'] > 0.01)
        ->values()
        ->toArray();
    
    return $movimientos;
}
```

**Estado:** ✅ **CORRECTO** - Usa debe/haber consistentemente

---

### 3️⃣ `app/Services/VentaService.php`

#### **Validación de Crédito** (líneas 76-92)

**Código Actual:**
```php
if ($saldoPendiente > $tolerancia) {
    if ($tieneCuentaCorriente) {
        $credito_disponible = (float)$cliente->limite_credito - (float)$cliente->saldo_actual;
        
        if ($saldoPendiente > $credito_disponible + $tolerancia) {
            throw ValidationException::withMessages([
                'limite_credito' => sprintf(
                    'El saldo pendiente ($%s) supera el límite...',
                    // ...
                )
            ]);
        }
    } else {
        // Cliente sin cuenta corriente no puede tener saldo pendiente
        throw ValidationException::withMessages([
            'pago' => 'El cliente no tiene cuenta corriente...'
        ]);
    }
}
```

**Análisis:**
- ✅ Hay validación de límite de crédito
- ❌ Depende de `$cliente->saldo_actual` (campo de BD, no calculado en tiempo real)
- ❌ **BUG POTENCIAL:** Si `saldo_actual` está desincronizado, la validación falla

**Problema Conceptual:**
```
Escenario:
- Cliente tiene límite $5,000,000
- BD dice saldo_actual = $0 (desactualizado)
- Realidad: ya debe $4,500,000 en CC
- Usuario crea venta por $2,000,000
- Validación: $2M < ($5M - $0) = OK ✅ (PERO DEBERÍA FALLAR!)
- Nuevo saldo real: $6,500,000 > $5,000,000 ❌ IMPOSIBLE
```

**Estado:** 🔴 CRÍTICO - Validación existe pero usa dato potencialmente stale

---

### 4️⃣ `app/Services/PagoService.php`

#### **Manejo de Cuenta Corriente** (líneas 187-210)

**Código Actual:**
```php
if ($esCuentaCorriente) {
    // CASO 1: Asignación a Cuenta Corriente (FIAR saldo pendiente)
    
    \Log::info("Registrando asignación a Cuenta Corriente...");
    
    // Incrementar saldo_actual del cliente (deuda)
    $cliente->saldo_actual = round((float)$cliente->saldo_actual + $monto, 2);
    $cliente->save();
    
    // Crear movimiento tipo "venta" (DEBE)
    if ((float)$cliente->limite_credito > 0) {
        MovimientoCuentaCorriente::create([
            'cliente_id'   => $cliente->id,
            'tipo'         => 'venta',
            'monto'        => abs($monto),  // ⚠️ Guarda en campo 'monto'
            'debe'         => abs($monto),  // ✅ También en 'debe'
            'haber'        => 0,
            // ...
        ]);
        
        $cliente->refresh();
        $cliente->recalcularSaldo(); // ⚠️ Recalcula pero usando 'monto'
    }
}
```

**Análisis:**
- ✅ Guarda correctamente en `debe`
- ⚠️ También guarda en `monto` (redundante)
- ❌ NO VALIDA si excede límite de crédito

**Estado:** 🟡 MEDIO - Funciona pero falta validación crítica

---

#### **Manejo de Pagos Reales** (líneas 212-240)

**Código Actual:**
```php
else {
    // CASO 2: Pago Real (Efectivo, Transferencia, etc.)
    $debeReducirSaldo = !$esCheque || ($esCheque && $estado === 'cobrado');
    
    if ($debeReducirSaldo) {
        // Disminuir saldo del cliente
        $cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
        $cliente->save();

        if ((float)$cliente->limite_credito > 0) {
            MovimientoCuentaCorriente::create([
                'cliente_id'   => $cliente->id,
                'tipo'         => 'pago',
                'monto'        => -abs($monto), // ⚠️ Negativo en 'monto'
                'debe'         => 0,
                'haber'        => abs($monto),  // ✅ Correcto en 'haber'
                // ...
            ]);
            
            $cliente->refresh();
            $cliente->recalcularSaldo();
        }
    }
}
```

**Análisis:**
- ✅ Guarda correctamente en `haber`
- ⚠️ Usa convención de signo negativo en `monto`
- ❌ NO VALIDA si el pago excede la deuda (sobrepago)

**Estado:** 🟡 MEDIO - Funciona pero falta validación

---

### 5️⃣ `app/Http/Controllers/CuentaCorrienteController.php`

#### **Método: `show()`** (líneas 47-105)

**Código Actual:**
```php
$movimientos = MovimientoCuentaCorriente::where('cliente_id', $id)
    ->orderBy('fecha')
    ->get()
    ->map(function ($mov) {
        $monto = (float)$mov->monto;
        $debe = 0.0;
        $haber = 0.0;
        $montoParaSaldo = 0.0;

        if ($mov->tipo === 'venta') {
            $debe = abs($monto);
            $montoParaSaldo = abs($monto); // ❌ Calcula desde 'monto'
        } else { // pago
            $haber = abs($monto);
            $montoParaSaldo = -abs($monto); // ❌ Calcula desde 'monto'
        }
        
        return [
            'debe'  => $debe,
            'haber' => $haber,
            'monto' => $montoParaSaldo, // ⚠️ Devuelve monto calculado
            // ...
        ];
    });

// Calcular saldo acumulado
$saldo = 0.0;
foreach ($movimientos as &$m) {
    $saldo += $m['monto']; // ❌ Suma usando campo 'monto' calculado
    $m['saldo_acumulado'] = round($saldo, 2);
}
```

**Problema:**
- ❌ Recalcula `debe/haber` desde `monto` en lugar de usar campos existentes
- ❌ Lógica redundante: los campos `debe/haber` YA existen en BD

**Estado:** 🔴 CRÍTICO - Lógica duplicada e innecesaria

---

### 6️⃣ `app/Models/Venta.php`

#### **Atributo Calculado: `estado_pago`** (líneas 35-95)

**Código Actual:**
```php
protected function estadoPago(): Attribute
{
    return Attribute::make(
        get: function () {
            $total = (float) $this->total;
            
            // Obtener ID de "Cuenta Corriente"
            $cuentaCorrienteId = MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
            
            // Calcular pagos reales (sin CC, solo cheques cobrados)
            $totalPagado = $cuentaCorrienteId 
                ? (float) $this->pagos
                    ->where('metodo_pago_id', '!=', $cuentaCorrienteId)
                    ->filter(fn($p) => is_null($p->estado_cheque) || $p->estado_cheque === 'cobrado')
                    ->sum('monto') // ✅ Usa 'monto' de pagos (tabla diferente, OK)
                : /* ... */;
            
            // Deuda en cuenta corriente
            $totalCuentaCorriente = $cuentaCorrienteId
                ? (float) $this->pagos->where('metodo_pago_id', $cuentaCorrienteId)->sum('monto')
                : 0;
            
            // Cheques pendientes
            $totalChequesPendientes = (float) $this->pagos
                ->where('estado_cheque', 'pendiente')
                ->sum('monto');

            // LÓGICA:
            // - "pagado": Sin deuda CC ni cheques pendientes
            // - "parcial": Hay deuda o cheques pendientes
            // - "pendiente": Sin pagos
            
            if ($totalCuentaCorriente > 0) {
                return 'parcial'; // Hay deuda
            }
            
            if ($totalChequesPendientes > 0) {
                return 'parcial'; // Cheques sin cobrar
            }
            
            $saldoSinPagar = round($total - $totalPagado, 2);
            
            if ($saldoSinPagar <= 0.01) {
                return 'pagado';
            } elseif ($totalPagado > 0) {
                return 'parcial';
            } else {
                return 'pendiente';
            }
        }
    );
}
```

**Análisis:**
- ✅ Lógica correcta y bien documentada
- ✅ Aquí el uso de `monto` es correcto (tabla `pagos`, no `movimientos_cuenta_corriente`)
- ✅ Considera casos especiales: cheques, cuenta corriente

**Estado:** ✅ **CORRECTO** - No necesita cambios

---

## 🎯 RESUMEN DE PROBLEMAS

| # | Archivo | Método/Línea | Problema | Severidad |
|---|---------|--------------|----------|-----------|
| 1 | Cliente.php | `calcularSaldoReal()` (60-78) | Usa `sum('monto')` en lugar de `debe/haber` | 🔴 CRÍTICO |
| 2 | CuentaCorrienteController.php | `show()` (47-105) | Recalcula debe/haber desde monto redundantemente | 🔴 CRÍTICO |
| 3 | VentaService.php | Validación crédito (76-92) | Usa `saldo_actual` de BD (potencialmente stale) | 🔴 CRÍTICO |
| 4 | PagoService.php | Pago CC (187-210) | NO valida si excede límite | 🟡 ALTO |
| 5 | PagoService.php | Pago real (212-240) | NO valida sobrepago | 🟡 ALTO |
| 6 | CuentaCorrienteService.php | `calcularDeudaCCVenta()` | ✅ Ninguno - Patrón correcto | ✅ OK |
| 7 | Venta.php | `estadoPago` | ✅ Ninguno - Lógica correcta | ✅ OK |

---

## 💡 SOLUCIÓN PROPUESTA

### Principio Rector: **INVARIANTES CONTABLES**

```
INVARIANTE #1: 0 ≤ saldo_actual ≤ limite_credito
INVARIANTE #2: credito_disponible = limite_credito - saldo_actual ≥ 0
INVARIANTE #3: saldo_actual = Σ(debe) - Σ(haber) SIEMPRE
INVARIANTE #4: Campo 'monto' será ELIMINADO de cálculos (solo histórico)
```

### Convención Universal de Signos

```
DEBE  = Cliente DEBE dinero (ventas, asignaciones a CC) → POSITIVO
HABER = Cliente HA PAGADO (pagos, abonos)               → POSITIVO

SALDO = DEBE - HABER

Ejemplo:
- Venta $1000 → debe=$1000, haber=$0  → saldo=$1000
- Pago $300   → debe=$0,    haber=$300 → saldo=$700
- Pago $700   → debe=$0,    haber=$700 → saldo=$0
```

---

## 📝 CAMBIOS DETALLADOS (DIFFS)

### CAMBIO #1: Cliente.php - `calcularSaldoReal()`

**ANTES:**
```php
public function calcularSaldoReal()
{
    $ventas = $this->movimientosCuentaCorriente()
        ->where('tipo', 'venta')
        ->sum('monto'); // ❌ Campo incorrecto
    
    $pagos = $this->movimientosCuentaCorriente()
        ->where('tipo', 'pago')
        ->sum('monto'); // ❌ Campo incorrecto
    
    return round((float)$ventas - (float)$pagos, 2);
}
```

**DESPUÉS:**
```php
public function calcularSaldoReal()
{
    // Calcular usando DEBE - HABER (convención contable estándar)
    $debe = $this->movimientosCuentaCorriente()
        ->where('tipo', 'venta')
        ->sum('debe'); // ✅ Campo correcto: cliente DEBE
    
    $haber = $this->movimientosCuentaCorriente()
        ->where('tipo', 'pago')
        ->sum('haber'); // ✅ Campo correcto: cliente HA PAGADO
    
    return round($debe - $haber, 2);
}
```

**Razón del Cambio:**
- Unifica con `CuentaCorrienteService::calcularDeudaCCVenta()` (patrón correcto)
- Elimina dependencia del campo `monto` con convención de signos inconsistente
- **Previene:** Discrepancias entre diferentes vistas del saldo

---

### CAMBIO #2: VentaService.php - Validación de Crédito

**ANTES:**
```php
if ($saldoPendiente > $tolerancia) {
    if ($tieneCuentaCorriente) {
        // ❌ Usa saldo_actual de BD (puede estar desactualizado)
        $credito_disponible = (float)$cliente->limite_credito - (float)$cliente->saldo_actual;
        
        if ($saldoPendiente > $credito_disponible + $tolerancia) {
            throw ValidationException::withMessages([/* ... */]);
        }
    } else {
        throw ValidationException::withMessages([
            'pago' => 'El cliente no tiene cuenta corriente...'
        ]);
    }
}
```

**DESPUÉS:**
```php
if ($saldoPendiente > $tolerancia) {
    if ($tieneCuentaCorriente) {
        // ✅ Calcular saldo REAL en tiempo real
        $saldoRealActual = $cliente->calcularSaldoReal();
        
        // ✅ Calcular crédito disponible actual
        $credito_disponible = (float)$cliente->limite_credito - $saldoRealActual;
        
        // ✅ VALIDAR que el nuevo monto NO exceda el límite
        $saldoProyectado = $saldoRealActual + $saldoPendiente;
        
        if ($saldoProyectado > (float)$cliente->limite_credito + $tolerancia) {
            throw ValidationException::withMessages([
                'limite_credito' => sprintf(
                    'La operación excedería el límite de crédito. ' .
                    'Límite: $%s, Deuda actual: $%s, Saldo pendiente: $%s, ' .
                    'Total proyectado: $%s (exceso: $%s)',
                    number_format($cliente->limite_credito, 2, ',', '.'),
                    number_format($saldoRealActual, 2, ',', '.'),
                    number_format($saldoPendiente, 2, ',', '.'),
                    number_format($saldoProyectado, 2, ',', '.'),
                    number_format($saldoProyectado - $cliente->limite_credito, 2, ',', '.')
                )
            ]);
        }
    } else {
        throw ValidationException::withMessages([
            'pago' => 'El cliente no tiene cuenta corriente. Debe pagar el total de la venta.'
        ]);
    }
}
```

**Razón del Cambio:**
- **Previene:** El caso de la imagen ($6M > $5M límite)
- Calcula saldo en tiempo real usando método ya corregido
- Valida ANTES de crear la venta
- Mensajes de error informativos con todos los montos

---

### CAMBIO #3: PagoService.php - Validación en Asignación a CC

**ANTES:**
```php
if ($esCuentaCorriente) {
    // ❌ NO HAY VALIDACIÓN de límite
    \Log::info("Registrando asignación a Cuenta Corriente...");
    
    $cliente->saldo_actual = round((float)$cliente->saldo_actual + $monto, 2);
    $cliente->save();
    
    MovimientoCuentaCorriente::create([/* ... */]);
    
    $cliente->refresh();
    $cliente->recalcularSaldo();
}
```

**DESPUÉS:**
```php
if ($esCuentaCorriente) {
    \Log::info("Validando asignación a Cuenta Corriente...");
    
    // ✅ VALIDAR límite de crédito ANTES de asignar
    $saldoActual = $cliente->calcularSaldoReal();
    $nuevoSaldo = $saldoActual + $monto;
    
    if ($nuevoSaldo > (float)$cliente->limite_credito + 0.01) {
        throw ValidationException::withMessages([
            'monto' => sprintf(
                'No se puede asignar $%s a cuenta corriente. ' .
                'Excedería el límite de crédito ($%s). ' .
                'Deuda actual: $%s, Disponible: $%s',
                number_format($monto, 2, ',', '.'),
                number_format($cliente->limite_credito, 2, ',', '.'),
                number_format($saldoActual, 2, ',', '.'),
                number_format(max(0, $cliente->limite_credito - $saldoActual), 2, ',', '.')
            )
        ]);
    }
    
    \Log::info("Asignación válida. Registrando en Cuenta Corriente...");
    
    // Incrementar saldo
    $cliente->saldo_actual = round((float)$cliente->saldo_actual + $monto, 2);
    $cliente->save();
    
    // Crear movimiento
    if ((float)$cliente->limite_credito > 0) {
        MovimientoCuentaCorriente::create([
            'cliente_id'   => $cliente->id,
            'tipo'         => 'venta',
            'referencia_id'=> $venta->id,
            'monto'        => abs($monto),
            'debe'         => abs($monto),
            'haber'        => 0,
            'fecha'        => $pago->fecha_pago,
            'descripcion'  => "Venta a crédito #{$venta->id} (pago posterior asignado a CC)",
        ]);
        
        $cliente->refresh();
        $cliente->recalcularSaldo();
    }
}
```

**Razón del Cambio:**
- **Previene:** Asignar más deuda cuando ya se excedió el límite
- Consistencia con validación en creación de ventas

---

### CAMBIO #4: PagoService.php - Validación de Sobrepago

**ANTES:**
```php
else {
    // CASO 2: Pago Real
    $debeReducirSaldo = !$esCheque || ($esCheque && $estado === 'cobrado');
    
    if ($debeReducirSaldo) {
        // ❌ NO VALIDA sobrepago
        $cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
        $cliente->save();
        
        MovimientoCuentaCorriente::create([/* ... */]);
        
        $cliente->refresh();
        $cliente->recalcularSaldo();
    }
}
```

**DESPUÉS:**
```php
else {
    // CASO 2: Pago Real
    $debeReducirSaldo = !$esCheque || ($esCheque && $estado === 'cobrado');
    
    if ($debeReducirSaldo) {
        // ✅ VALIDAR que no haya sobrepago
        $saldoActual = $cliente->calcularSaldoReal();
        
        if ($monto > $saldoActual + 0.01) {
            throw ValidationException::withMessages([
                'monto' => sprintf(
                    'El monto del pago ($%s) excede la deuda actual del cliente ($%s). ' .
                    'Máximo permitido: $%s',
                    number_format($monto, 2, ',', '.'),
                    number_format($saldoActual, 2, ',', '.'),
                    number_format($saldoActual, 2, ',', '.')
                )
            ]);
        }
        
        // Disminuir saldo
        $cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
        $cliente->save();

        // Crear movimiento
        if ((float)$cliente->limite_credito > 0) {
            MovimientoCuentaCorriente::create([
                'cliente_id'   => $cliente->id,
                'tipo'         => 'pago',
                'referencia_id'=> $pago->id,
                'monto'        => -abs($monto),
                'debe'         => 0,
                'haber'        => abs($monto),
                'fecha'        => $pago->fecha_pago,
                'descripcion'  => 'Pago venta #'.$venta->id . ($esCheque ? ' (Cheque cobrado)' : ''),
            ]);
            
            $cliente->refresh();
            $cliente->recalcularSaldo();
        }
    }
}
```

**Razón del Cambio:**
- **Previene:** Saldos negativos (cliente "nos debe dinero negativo")
- Protege integridad contable

---

### CAMBIO #5: CuentaCorrienteController.php - Eliminar Lógica Redundante

**ANTES:**
```php
$movimientos = MovimientoCuentaCorriente::where('cliente_id', $id)
    ->orderBy('fecha')
    ->get()
    ->map(function ($mov) {
        $monto = (float)$mov->monto;
        $debe = 0.0;
        $haber = 0.0;
        $montoParaSaldo = 0.0;

        // ❌ Recalcula debe/haber desde monto (redundante)
        if ($mov->tipo === 'venta') {
            $debe = abs($monto);
            $montoParaSaldo = abs($monto);
        } else {
            $haber = abs($monto);
            $montoParaSaldo = -abs($monto);
        }
        
        return [
            'fecha'         => $mov->fecha,
            'tipo'          => $mov->tipo,
            'descripcion'   => $mov->descripcion,
            'debe'          => $debe,
            'haber'         => $haber,
            'monto'         => $montoParaSaldo,
            // ...
        ];
    })
    ->toArray();

// ❌ Calcula saldo desde campo 'monto' calculado
$saldo = 0.0;
$totalDebe = 0.0;
$totalHaber = 0.0;

foreach ($movimientos as &$m) {
    $totalDebe += $m['debe'];
    $totalHaber += $m['haber'];
    $saldo += $m['monto']; // ❌ Usa 'monto'
    $m['saldo_acumulado'] = round($saldo, 2);
}
```

**DESPUÉS:**
```php
$movimientos = MovimientoCuentaCorriente::where('cliente_id', $id)
    ->orderBy('fecha')
    ->orderBy('id') // ✅ Orden determinístico
    ->get()
    ->map(function ($mov) {
        // ✅ Usar campos debe/haber directamente de BD
        return [
            'fecha'         => $mov->fecha,
            'tipo'          => $mov->tipo,
            'descripcion'   => $mov->descripcion,
            'debe'          => (float)$mov->debe,  // ✅ Desde BD
            'haber'         => (float)$mov->haber, // ✅ Desde BD
            'referencia_id' => $mov->referencia_id,
            'detalles'      => $mov->detalles,
        ];
    })
    ->toArray();

// ✅ Calcular saldo acumulado usando debe - haber
$saldo = 0.0;
$totalDebe = 0.0;
$totalHaber = 0.0;

foreach ($movimientos as &$m) {
    $totalDebe += $m['debe'];
    $totalHaber += $m['haber'];
    
    // ✅ DEBE incrementa, HABER decrementa
    $saldo += $m['debe'] - $m['haber'];
    $m['saldo_acumulado'] = round($saldo, 2);
}
unset($m);

// ✅ Verificar consistencia
$saldoCalculado = $cliente->calcularSaldoReal();

return response()->json([
    'cliente' => [
        'id'              => $cliente->id,
        'nombre'          => $cliente->nombre,
        'apellido'        => $cliente->apellido,
        'limite_credito'  => (float)$cliente->limite_credito,
        'saldo_actual'    => $saldoCalculado, // ✅ Saldo real calculado
        'saldo_bd'        => (float)$cliente->saldo_actual, // Para debug
    ],
    'filtros' => [
        'desde' => $desde?->toDateString(),
        'hasta' => $hasta?->toDateString(),
    ],
    'resumen' => [
        'total_debe'   => round($totalDebe, 2),
        'total_haber'  => round($totalHaber, 2),
        'saldo_final'  => round($saldo, 2),
        // ✅ Validación de integridad
        'discrepancia' => round(abs($saldoCalculado - $saldo), 2),
    ],
    'movimientos' => $movimientos,
]);
```

**Razón del Cambio:**
- Elimina lógica duplicada e innecesaria
- Usa campos de BD directamente (single source of truth)
- Agrega verificación de integridad (detecta inconsistencias)
- Más eficiente (no recalcula lo que ya existe)

---

## 🧪 CASOS DE PRUEBA

### Test #1: Validación de Límite en Venta

**Escenario:**
- Cliente: límite = $5,000,000, saldo actual = $0
- Intenta crear venta con CC por $6,000,000

**Resultado Esperado:**
```
❌ ValidationException: "La operación excedería el límite de crédito.
    Límite: $5,000,000.00
    Deuda actual: $0.00
    Saldo pendiente: $6,000,000.00
    Total proyectado: $6,000,000.00 (exceso: $1,000,000.00)"
```

**Código de Prueba:**
```php
public function test_no_permite_exceder_limite_credito_en_venta()
{
    $cliente = Cliente::factory()->create([
        'limite_credito' => 5000000,
        'saldo_actual' => 0,
    ]);
    
    $this->expectException(ValidationException::class);
    
    $ventaService = new VentaService();
    $ventaService->crear([
        'cliente_id' => $cliente->id,
        'total' => 6000000,
        'metodos_pago' => [
            ['metodo_id' => MetodoPago::cuentaCorriente()->id, 'monto' => 6000000]
        ],
    ]);
}
```

---

### Test #2: Validación de Sobrepago

**Escenario:**
- Cliente: saldo actual = $1,000,000
- Intenta pagar $1,500,000

**Resultado Esperado:**
```
❌ ValidationException: "El monto del pago ($1,500,000.00) excede la deuda 
    actual del cliente ($1,000,000.00). Máximo permitido: $1,000,000.00"
```

**Código de Prueba:**
```php
public function test_no_permite_sobrepago()
{
    $cliente = Cliente::factory()->create([
        'limite_credito' => 5000000,
        'saldo_actual' => 1000000,
    ]);
    
    // Crear venta con CC
    $venta = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'total' => 1000000,
    ]);
    
    MovimientoCuentaCorriente::create([
        'cliente_id' => $cliente->id,
        'tipo' => 'venta',
        'venta_id' => $venta->id,
        'debe' => 1000000,
        'haber' => 0,
        'monto' => 1000000,
        'fecha' => now(),
    ]);
    
    $this->expectException(ValidationException::class);
    
    $pagoService = new PagoService();
    $pagoService->registrarPago([
        'venta_id' => $venta->id,
        'cliente_id' => $cliente->id,
        'monto' => 1500000, // Excede deuda
        'metodo_pago_id' => MetodoPago::efectivo()->id,
    ]);
}
```

---

### Test #3: Verificar Consistencia de Fórmulas

**Escenario:**
- Crear venta $2,000,000 en CC
- Pagar $800,000
- Pagar $1,200,000

**Resultado Esperado:**
```
✅ Saldo final = $0
✅ calcularSaldoReal() = obtenerDeudaPorVenta() = CuentaCorrienteController saldo
✅ estado_pago = 'pagado'
```

**Código de Prueba:**
```php
public function test_consistencia_formulas_debe_haber()
{
    $cliente = Cliente::factory()->create([
        'limite_credito' => 5000000,
        'saldo_actual' => 0,
    ]);
    
    // Crear venta
    $venta = Venta::factory()->create([
        'cliente_id' => $cliente->id,
        'total' => 2000000,
    ]);
    
    MovimientoCuentaCorriente::create([
        'cliente_id' => $cliente->id,
        'tipo' => 'venta',
        'venta_id' => $venta->id,
        'debe' => 2000000,
        'haber' => 0,
        'monto' => 2000000,
        'fecha' => now(),
    ]);
    
    $cliente->recalcularSaldo();
    $cliente->refresh();
    
    // Verificar saldo inicial
    $this->assertEquals(2000000, $cliente->calcularSaldoReal());
    
    // Pago 1: $800,000
    MovimientoCuentaCorriente::create([
        'cliente_id' => $cliente->id,
        'tipo' => 'pago',
        'venta_id' => $venta->id,
        'debe' => 0,
        'haber' => 800000,
        'monto' => -800000,
        'fecha' => now(),
    ]);
    
    $cliente->recalcularSaldo();
    $cliente->refresh();
    
    // Verificar saldo intermedio
    $this->assertEquals(1200000, $cliente->calcularSaldoReal());
    
    // Pago 2: $1,200,000 (completa)
    MovimientoCuentaCorriente::create([
        'cliente_id' => $cliente->id,
        'tipo' => 'pago',
        'venta_id' => $venta->id,
        'debe' => 0,
        'haber' => 1200000,
        'monto' => -1200000,
        'fecha' => now(),
    ]);
    
    $cliente->recalcularSaldo();
    $cliente->refresh();
    
    // Verificar saldo final
    $this->assertEquals(0, $cliente->calcularSaldoReal());
    
    // Verificar consistencia entre métodos
    $service = new CuentaCorrienteService();
    $deudaVenta = $service->calcularDeudaCCVenta($venta->id);
    $this->assertEquals(0, $deudaVenta);
    
    // Verificar estado de venta
    $venta->refresh();
    $this->assertEquals('pagado', $venta->estado_pago);
}
```

---

## 📊 IMPACTO DE LOS CAMBIOS

### Beneficios

1. **Consistencia Total:**
   - Todas las vistas mostrarán el mismo saldo
   - Fórmula única: `DEBE - HABER`

2. **Prevención de Estados Imposibles:**
   - ✅ NO más saldos > límites
   - ✅ NO más disponibles negativos
   - ✅ NO más sobrepagos

3. **Integridad Contable:**
   - Campo `monto` se vuelve histórico (no usado en cálculos)
   - Campos `debe/haber` son la fuente única de verdad

4. **Debugging Mejorado:**
   - Logs informativos con todos los montos
   - Verificaciones de integridad en respuestas API

### Riesgos

1. **Cambios en Cliente.php:**
   - Método `calcularSaldoReal()` usado en muchos lugares
   - **Mitigación:** Probar exhaustivamente antes de deploy

2. **Validaciones Nuevas:**
   - Podrían bloquear operaciones que antes pasaban
   - **Mitigación:** Logs detallados + mensajes de error claros

3. **Performance:**
   - Más cálculos en tiempo real vs usar `saldo_actual` de BD
   - **Mitigación:** Agregar índices a tabla `movimientos_cuenta_corriente`

---

## 🚀 PLAN DE IMPLEMENTACIÓN

### Fase 1: Preparación (NO destructiva)

1. ✅ Crear tests unitarios para casos críticos
2. ✅ Agregar índices a BD:
   ```sql
   CREATE INDEX idx_movimientos_cliente_tipo ON movimientos_cuenta_corriente(cliente_id, tipo);
   CREATE INDEX idx_movimientos_venta ON movimientos_cuenta_corriente(venta_id);
   ```

### Fase 2: Implementación por Orden de Dependencias

1. ✅ **CAMBIO #1:** Cliente.php - `calcularSaldoReal()`
   - Ejecutar tests inmediatamente
   
2. ✅ **CAMBIO #5:** CuentaCorrienteController.php
   - Verificar que respuestas API sean consistentes
   
3. ✅ **CAMBIO #2:** VentaService.php - Validación
   - Probar creación de ventas con límites
   
4. ✅ **CAMBIO #3:** PagoService.php - Validación CC
   - Probar asignación a cuenta corriente
   
5. ✅ **CAMBIO #4:** PagoService.php - Validación sobrepago
   - Probar pagos límite

### Fase 3: Validación

1. ✅ Ejecutar suite completa de tests
2. ✅ Probar manualmente en staging:
   - Crear venta con CC cerca del límite
   - Intentar exceder límite (debe fallar)
   - Pagar parcialmente
   - Intentar sobrepago (debe fallar)
   - Pagar completamente
3. ✅ Verificar logs de Laravel para errores

### Fase 4: Deploy

1. ✅ Backup de BD producción
2. ✅ Deploy en horario de bajo tráfico
3. ✅ Monitorear logs por 24h
4. ✅ Verificar métricas:
   - Tiempo de respuesta de endpoints CC
   - Errores de validación (esperado: aumenten temporalmente)

---

## 🔍 VERIFICACIONES POST-DEPLOY

### Checklist

- [ ] Endpoint `/api/clientes/{id}/cuenta-corriente` responde correctamente
- [ ] Crear venta con CC funciona (dentro de límite)
- [ ] Crear venta con CC falla (excede límite) con mensaje claro
- [ ] Pagar cuenta corriente funciona
- [ ] Sobrepago falla con mensaje claro
- [ ] Frontend muestra valores consistentes:
  - Saldo cliente
  - Crédito disponible
  - Deuda por venta
- [ ] No hay errores 500 en logs
- [ ] Tiempo de respuesta < 500ms para operaciones CC

---

## 📞 CONTACTO Y APROBACIÓN

**Este documento es PRE-IMPLEMENTACIÓN.**

**¿Procedo con los cambios?**

Opciones:
1. ✅ **Aprobar todo** → Implementar todos los cambios en orden
2. 🟡 **Aprobar parcial** → Especificar qué cambios aplicar primero
3. ⏸️ **Revisar** → Necesitas más detalles o ajustes
4. ❌ **Cancelar** → No aplicar cambios

**Responde para continuar.**

---

**Generado:** Automáticamente  
**Versión:** 1.0  
**Estado:** 🔴 PENDIENTE DE APROBACIÓN
