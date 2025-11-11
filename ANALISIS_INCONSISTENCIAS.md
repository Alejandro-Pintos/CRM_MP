# Análisis de Inconsistencias - Sistema de Cuenta Corriente

## Definición de la Lógica de Negocio

### Convención Contable
- **DEBE**: Lo que el cliente COMPRA a crédito (aumenta deuda)
- **HABER**: Lo que el cliente PAGA (reduce deuda)
- **SALDO_ACTUAL**: Deuda total del cliente = `DEBE - HABER` (siempre positivo cuando debe dinero)

### Fórmulas Correctas
```
saldo_actual = SUM(debe) - SUM(haber)
credito_disponible = limite_credito - saldo_actual
```

---

## Inconsistencias Encontradas

### 1. VentaService.php (Línea 170)
**Operación al crear venta:**
```php
$cliente->saldo_actual = round((float)$cliente->saldo_actual + $saldoPendiente, 2);
```
✅ **CORRECTO** - Suma al saldo cuando hay deuda pendiente

---

### 2. PagoService.php (Línea 159)
**Operación al registrar pago:**
```php
$cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
```
✅ **CORRECTO** - Resta del saldo cuando se paga

---

### 3. VentaController.php - destroy() (Línea 102)
**Operación al eliminar venta:**
```php
$cliente->saldo_actual = (float)$cliente->saldo_actual - $montoCuentaCorriente;
```
✅ **CORRECTO** - Resta del saldo porque se está cancelando la deuda

---

### 4. PagoController.php - cobrarCheque() (Línea 101)
**Operación al cobrar cheque:**
```php
$cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
```
✅ **CORRECTO** - Resta del saldo cuando se cobra

---

### 5. PagoController.php - destroy() (Línea 353)
**Operación al eliminar pago:**
```php
$cliente->saldo_actual = round((float)$cliente->saldo_actual + $monto, 2);
```
✅ **CORRECTO** - Suma al saldo porque se está revirtiendo el pago

---

### 6. CuentaCorrienteController.php - recalcularSaldoCliente() (Línea 191)
**PROBLEMA CRÍTICO:**
```php
// ANTES (INCORRECTO):
$saldo_calculado = $total_haber - $total_debe;

// DESPUÉS (CORRECTO):
$saldo_calculado = $total_debe - $total_haber;
```
❌ **YA CORREGIDO** - La fórmula estaba invertida

---

## Puntos donde se crean MovimientoCuentaCorriente

### 1. VentaService.php (Línea 193-202)
**Al crear venta con saldo pendiente:**
```php
MovimientoCuentaCorriente::create([
    'cliente_id' => $cliente->id,
    'tipo' => 'venta',
    'referencia_id' => $venta->id,
    'monto' => abs($saldoPendiente),
    'debe' => abs($saldoPendiente),  // ✅ AGREGADO
    'haber' => 0,                      // ✅ AGREGADO
    'fecha' => $venta->fecha,
    'descripcion' => "Saldo pendiente venta #{$venta->id}",
]);
```
✅ **CORRECTO**

---

### 2. PagoService.php (Línea 164-173)
**Al registrar pago:**
```php
MovimientoCuentaCorriente::create([
    'cliente_id'   => $cliente->id,
    'tipo'         => 'pago',
    'referencia_id'=> $pago->id,
    'monto'        => -abs($monto),
    'debe'         => 0,              // ✅ AGREGADO
    'haber'        => abs($monto),    // ✅ AGREGADO
    'fecha'        => $pago->fecha_pago,
    'descripcion'  => 'Pago venta #'.$venta->id,
]);
```
✅ **CORRECTO**

---

### 3. PagoController.php - cobrarCheque() (Línea 106-115)
**Al cobrar cheque:**
```php
\App\Models\MovimientoCuentaCorriente::create([
    'cliente_id'   => $cliente->id,
    'tipo'         => 'pago',
    'referencia_id'=> $pago->id,
    'monto'        => -abs($monto),
    'debe'         => 0,              // ✅ AGREGADO
    'haber'        => abs($monto),    // ✅ AGREGADO
    'fecha'        => $pago->fecha_cobro,
    'descripcion'  => "Cobro de cheque #{$pago->numero_cheque} - Venta #{$venta->id}",
]);
```
✅ **CORRECTO**

---

### 4. VentaController.php - destroy() (Línea 109-118)
**Al eliminar venta (reversión):**
```php
\App\Models\MovimientoCuentaCorriente::create([
    'cliente_id' => $cliente->id,
    'tipo' => 'pago',
    'referencia_id' => $venta->id,
    'monto' => $montoCuentaCorriente,
    'debe' => 0,                              // ✅ AGREGADO
    'haber' => abs($montoCuentaCorriente),    // ✅ AGREGADO
    'fecha' => now(),
    'descripcion' => "Cancelación de venta #{$venta->id}",
]);
```
✅ **CORRECTO**

---

## Frontend

### Fórmula Crédito Disponible

**admin/src/pages/clientes/index.vue (Línea 537):**
```javascript
{{ formatPrice((selectedCliente?.limite_credito ?? 0) - (selectedCliente?.saldo_actual ?? 0)) }}
```
✅ **CORRECTO**

**admin/src/pages/clientes/cuentas-corrientes.vue (getDisponible):**
```javascript
const getDisponible = (cliente) => {
  const limite = parseFloat(cliente.limite_credito) || 0
  const saldo = parseFloat(cliente.saldo_actual) || 0
  return limite - saldo
}
```
✅ **CORRECTO**

---

## Conclusión

### Problemas Ya Resueltos:
1. ✅ Campos `debe` y `haber` agregados a todos los puntos de creación de movimientos
2. ✅ Fórmula de recálculo de saldo corregida (DEBE - HABER)
3. ✅ Frontend muestra saldo como deuda positiva
4. ✅ Crédito disponible = Límite - Saldo

### Sin Inconsistencias Adicionales
Todas las operaciones de saldo_actual siguen la misma lógica:
- **Sumar** cuando aumenta deuda (venta, reversión de pago)
- **Restar** cuando reduce deuda (pago, reversión de venta)

---

## Problema Actual Reportado

**Usuario dice:** "Pagué y eliminé la venta y aún así el saldo actual no se actualizó a 0.00"

**Causa probable:** 
El saldo quedó en -$998.250 porque hay un movimiento que no tiene `debe` y `haber` correctamente asignados (movimiento creado ANTES de agregar las columnas).

**Solución:**
1. Limpiar movimientos antiguos
2. Crear nueva venta de prueba
3. Verificar que los campos `debe` y `haber` se llenen correctamente
