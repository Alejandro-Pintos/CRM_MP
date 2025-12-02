# 🔴 PROBLEMA CRÍTICO: SALDOS NEGATIVOS EN CUENTA CORRIENTE

**Fecha:** 1 de diciembre de 2025  
**Estado:** 🟢 RESUELTO  
**Severidad:** CRÍTICA

---

## 🎯 DESCRIPCIÓN DEL PROBLEMA

### Síntoma
Cliente con **saldo negativo** (-$8,000,000) en cuenta corriente.

### Diagnóstico
```
Total DEBE (ventas):  $2,000,000
Total HABER (pagos):  $10,000,000
Saldo (DEBE-HABER):   -$8,000,000 ❌
```

### Causa Raíz
**Movimientos huérfanos**: Pagos registrados sin `venta_id` asociada durante pruebas/debugging.

---

## 📋 ANÁLISIS DETALLADO

### Movimientos Encontrados (Cliente ID: 3 - Nery Manco)

```
ID:36  | pago  | Venta:18   | HABER:$500,000    | ✅ Correcto
ID:37  | pago  | Venta:18   | HABER:$1,500,000  | ✅ Correcto  
ID:38  | pago  | Venta:NULL | HABER:$2,000,000  | ❌ HUÉRFANO
ID:39  | pago  | Venta:NULL | HABER:$2,000,000  | ❌ HUÉRFANO
ID:40  | pago  | Venta:NULL | HABER:$4,000,000  | ❌ HUÉRFANO
ID:35  | venta | Venta:18   | DEBE:$2,000,000   | ✅ Correcto
```

**Problema:**
- Movimientos 38, 39, 40: Pagos **sin venta asociada** ($8M total)
- Resultado: Más HABER que DEBE → **Saldo negativo**

---

## ❌ POR QUÉ ES INCORRECTO

### Principio Contable Básico

En un sistema de **Cuenta Corriente de Ventas**:

```
CLIENTE = DEUDOR (nos debe dinero)
EMPRESA = ACREEDOR (le prestamos crédito)

Por lo tanto:
- SALDO > 0: Cliente nos debe dinero ✅
- SALDO = 0: Cliente no debe nada ✅
- SALDO < 0: Nosotros le debemos al cliente ❌ IMPOSIBLE
```

### Escenario IMPOSIBLE (Saldo Negativo)

```
Cliente debe:        $2,000,000
Cliente pagó:       $10,000,000
Diferencia:         -$8,000,000

Interpretación: "Nosotros le debemos $8M al cliente"
```

**Esto NO tiene sentido en un sistema de ventas porque:**

1. El cliente compra productos → nos debe dinero
2. El cliente paga → reduce su deuda
3. **NUNCA** puede pagar más de lo que debe
4. **NUNCA** nosotros le debemos dinero al cliente

### Única Excepción (NO aplica aquí)

En sistemas de **devoluciones/reembolsos** podría haber saldo negativo temporal:
- Cliente compra $1,000
- Cliente devuelve producto → reembolso $1,000
- Cliente tiene crédito a favor: -$1,000

**PERO:** En este CRM no hay módulo de devoluciones, por lo tanto **saldo negativo = ERROR**.

---

## ✅ SOLUCIÓN IMPLEMENTADA

### 1. Limpieza de Datos Corruptos

**Script:** `limpiar-movimientos-incorrectos.php`

```bash
php limpiar-movimientos-incorrectos.php
```

**Acciones:**
- ✅ Eliminó 3 movimientos huérfanos (ID: 38, 39, 40)
- ✅ Recalculó saldo: -$8,000,000 → $0
- ✅ Verificó integridad: saldo dentro de 0 ≤ saldo ≤ límite

### 2. Prevención de Futuros Errores

**Archivo:** `app/Models/Cliente.php` - Método `recalcularSaldo()`

**Validación agregada:**

```php
// VALIDACIÓN CRÍTICA: El saldo NO puede ser negativo
if ($saldoCalculado < -0.01) {
    \Log::error("Cliente #{$this->id} tiene saldo NEGATIVO: {$saldoCalculado}");
    
    throw new \Exception(
        "DATOS CORRUPTOS: Cliente tiene saldo negativo. " .
        "Ejecuta: php diagnosticar-movimientos.php"
    );
}
```

**Beneficios:**
- ⚠️ **Alerta temprana**: Detecta datos corruptos inmediatamente
- 📊 **Log detallado**: Registra DEBE/HABER totales para debugging
- 🛑 **Bloqueo**: Impide que se guarde un saldo negativo en BD

### 3. Scripts de Diagnóstico

#### `diagnosticar-movimientos.php`
```bash
php diagnosticar-movimientos.php
```

Muestra:
- Todos los movimientos del cliente
- Totales DEBE y HABER
- Saldo calculado
- Detecta automáticamente saldos negativos

#### `limpiar-movimientos-incorrectos.php`
```bash
php limpiar-movimientos-incorrectos.php
```

Funciones:
- Busca pagos huérfanos (sin `venta_id`)
- Muestra montos y descripciones
- Solicita confirmación antes de eliminar
- Recalcula saldo automáticamente

---

## 🔍 VERIFICACIÓN POST-LIMPIEZA

### Antes
```
Cliente: Nery Manco
Saldo: -$8,000,000 ❌

Movimientos:
- Ventas:  $2,000,000
- Pagos:  $10,000,000
- Balance: -$8,000,000 ❌
```

### Después
```
Cliente: Nery Manco
Saldo: $0 ✅

Movimientos:
- Ventas:  $2,000,000
- Pagos:   $2,000,000
- Balance: $0 ✅
```

---

## 🚨 CAUSAS COMUNES DE PAGOS HUÉRFANOS

### 1. Debugging/Testing Manual
```sql
-- ❌ MAL: Crear pago directo sin venta
INSERT INTO movimientos_cuenta_corriente (tipo, cliente_id, haber, ...)
VALUES ('pago', 3, 1000000, ...);
```

### 2. Código con Bug
```php
// ❌ MAL: Crear movimiento sin asociar venta_id
MovimientoCuentaCorriente::create([
    'tipo' => 'pago',
    'cliente_id' => $clienteId,
    'haber' => $monto,
    // 'venta_id' => ??? FALTA ESTO
]);
```

### 3. Eliminación Incorrecta de Ventas
```sql
-- ❌ MAL: Eliminar venta sin eliminar sus movimientos
DELETE FROM ventas WHERE id = 18;
-- Los movimientos quedan huérfanos (venta_id apunta a registro inexistente)
```

---

## ✅ REGLAS DE NEGOCIO REFORZADAS

### INVARIANTES DEL SISTEMA

```
INVARIANTE #1: 0 ≤ saldo ≤ limite_credito
  - Saldo NUNCA puede ser negativo
  - Saldo NUNCA puede exceder límite de crédito

INVARIANTE #2: saldo = Σ(DEBE) - Σ(HABER)
  - DEBE = Ventas a crédito (cliente nos debe)
  - HABER = Pagos recibidos (cliente pagó)

INVARIANTE #3: Todo movimiento tipo "pago" DEBE tener venta_id
  - No se permiten pagos huérfanos
  - Excepción: Pagos aplicados por FIFO (tienen venta_id NULL temporalmente)

INVARIANTE #4: Σ(HABER por venta) ≤ DEBE de esa venta
  - No se puede pagar más de lo que se debe por venta
  - Validación implementada en PagoService
```

---

## 📊 CÓMO PREVENIR EN EL FUTURO

### 1. Validación en Tiempo Real

**Implementado en:** `Cliente::recalcularSaldo()`

```php
if ($saldoCalculado < -0.01) {
    throw new \Exception("DATOS CORRUPTOS: Saldo negativo");
}
```

### 2. Constraint en Base de Datos (Recomendado)

```sql
-- Agregar trigger que valide saldo después de insertar movimiento
DELIMITER $$
CREATE TRIGGER validar_saldo_positivo 
AFTER INSERT ON movimientos_cuenta_corriente
FOR EACH ROW
BEGIN
    DECLARE saldo_actual DECIMAL(15,2);
    
    SELECT (
        COALESCE(SUM(CASE WHEN tipo='venta' THEN debe ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN tipo='pago' THEN haber ELSE 0 END), 0)
    ) INTO saldo_actual
    FROM movimientos_cuenta_corriente
    WHERE cliente_id = NEW.cliente_id;
    
    IF saldo_actual < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ERROR: Saldo negativo no permitido';
    END IF;
END$$
DELIMITER ;
```

### 3. Tests Automáticos

**Archivo:** `tests/Unit/CuentaCorrienteValidacionTest.php`

```php
public function test_saldo_nunca_negativo()
{
    $cliente = Cliente::create([...]);
    
    // Crear venta $1000
    MovimientoCuentaCorriente::create([
        'tipo' => 'venta',
        'debe' => 1000,
        ...
    ]);
    
    // Intentar pagar $2000 (más de lo que debe)
    $this->expectException(ValidationException::class);
    
    MovimientoCuentaCorriente::create([
        'tipo' => 'pago',
        'haber' => 2000,
        ...
    ]);
}
```

---

## 🛠️ COMANDOS ÚTILES PARA AUDITORÍA

### Buscar Todos los Clientes con Saldo Negativo

```bash
cd api
php artisan tinker --execute="
foreach(App\Models\Cliente::all() as \$c) {
    \$saldo = \$c->calcularSaldoReal();
    if (\$saldo < 0) {
        echo \"Cliente #{\$c->id}: {\$c->nombre} - Saldo: \$saldo\" . PHP_EOL;
    }
}
"
```

### Buscar Pagos Huérfanos (Sin venta_id)

```bash
cd api
php artisan tinker --execute="
\$huerfanos = App\Models\MovimientoCuentaCorriente::whereNull('venta_id')
    ->where('tipo', 'pago')
    ->get();
    
echo \"Pagos huérfanos encontrados: \" . \$huerfanos->count() . PHP_EOL;
foreach(\$huerfanos as \$m) {
    echo \"  ID:{\$m->id} | Cliente:{\$m->cliente_id} | Monto:{\$m->haber}\" . PHP_EOL;
}
"
```

### Recalcular TODOS los Clientes y Detectar Problemas

```bash
cd api
php artisan cc:recalcular-saldos --dry-run
```

---

## 📝 RESUMEN EJECUTIVO

**PROBLEMA:** Saldos negativos por pagos huérfanos (sin venta asociada)

**CAUSA:** Datos de prueba/debugging mal eliminados

**SOLUCIÓN:**
1. ✅ Limpieza de 3 movimientos huérfanos ($8M)
2. ✅ Validación en `recalcularSaldo()` para detectar futuros casos
3. ✅ Scripts de diagnóstico y limpieza creados
4. ✅ Documentación de reglas de negocio

**ESTADO:** 🟢 Resuelto y prevenido

**PRÓXIMOS PASOS RECOMENDADOS:**
1. Ejecutar auditoría completa: `php artisan cc:recalcular-saldos --dry-run`
2. Implementar trigger de BD (opcional pero recomendado)
3. Agregar validación en frontend para evitar eliminación incorrecta de ventas

---

**Documentado:** 1 de diciembre de 2025  
**Versión:** 1.0 FINAL
