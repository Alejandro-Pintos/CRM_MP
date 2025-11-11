# Plan de Pruebas Manual - CRM MP

## 📋 Estado Inicial
- ✅ Base de datos limpia (migrate:fresh --seed)
- ✅ Solo existe usuario: `admin@example.com` / `secret123`
- ✅ Métodos de pago básicos creados
- ✅ Sin datos de prueba

---

## 🎯 Orden de Pruebas por Módulo

### 1️⃣ MÓDULO: Clientes
**Objetivo:** Validar creación, edición, límites de crédito y cuenta corriente

#### Pruebas:
1. **Crear Cliente Básico**
   - Nombre: "Cliente Prueba 1"
   - CUIT: 20-12345678-9
   - Email: cliente1@test.com
   - Teléfono: +54 9 11 1234-5678
   - Límite de crédito: $0 (sin crédito)
   - ✅ Verificar: `saldo_actual = 0`, `disponible = 0`

2. **Crear Cliente con Crédito**
   - Nombre: "Cliente con Crédito"
   - CUIT: 20-87654321-9
   - Límite de crédito: $500,000
   - ✅ Verificar: `saldo_actual = 0`, `disponible = $500,000`

3. **Editar Cliente**
   - Cambiar límite de crédito a $1,000,000
   - ✅ Verificar: `disponible = $1,000,000`

4. **Validaciones**
   - ❌ Intentar crear con CUIT duplicado (debe fallar)
   - ❌ Intentar crear con email duplicado (debe fallar)
   - ❌ Intentar límite de crédito negativo (debe fallar)

---

### 2️⃣ MÓDULO: Productos
**Objetivo:** Validar inventario, precios y stock

#### Pruebas:
1. **Crear Producto Simple**
   - Código: "PROD001"
   - Nombre: "Producto Test 1"
   - Precio Costo: $100
   - Precio Venta: $150
   - Stock: 50 unidades
   - ✅ Verificar: margen de ganancia 50%

2. **Crear Producto con Stock Mínimo**
   - Código: "PROD002"
   - Stock: 10 unidades
   - Stock Mínimo: 15 unidades
   - ✅ Verificar: alerta de stock bajo

3. **Editar Producto**
   - Cambiar precio de venta
   - ✅ Verificar: nuevo margen calculado

4. **Validaciones**
   - ❌ Intentar código duplicado (debe fallar)
   - ❌ Intentar precio negativo (debe fallar)
   - ❌ Intentar stock negativo (debe fallar)

---

### 3️⃣ MÓDULO: Ventas - Pago Contado
**Objetivo:** Validar venta completa pagada al contado

#### Pruebas:
1. **Venta Contado - Efectivo**
   - Cliente: "Cliente Prueba 1" (sin crédito)
   - Productos: 
     - PROD001 x 2 unidades = $300
   - Método de Pago: Efectivo - $300
   - ✅ Verificar:
     - `total = $300`
     - `estado_pago = 'pagado'`
     - Stock PROD001 = 48 (reducido)
     - Cliente `saldo_actual = 0` (sin cambios)

2. **Venta Contado - Transferencia**
   - Cliente: "Cliente Prueba 1"
   - Productos: PROD002 x 3 = $450
   - Método de Pago: Transferencia - $450
   - ✅ Verificar: igual que anterior

---

### 4️⃣ MÓDULO: Ventas - Cuenta Corriente
**Objetivo:** Validar deuda en cuenta corriente

#### Pruebas:
1. **Venta a Cuenta Corriente (100% Deuda)**
   - Cliente: "Cliente con Crédito" (límite $1,000,000)
   - Productos: PROD001 x 5 = $750
   - Método de Pago: Cuenta Corriente - $750
   - ✅ Verificar:
     - `total = $750`
     - `estado_pago = 'pendiente'`
     - Cliente `saldo_actual = -$750` (deuda)
     - Cliente `disponible = $999,250` ($1M - $750)
     - Movimiento CC: tipo='venta', monto=+$750

2. **Validar Límite de Crédito**
   - Intentar venta por $1,000,000 (excede disponible)
   - ❌ Debe fallar con error "Excede límite de crédito"

---

### 5️⃣ MÓDULO: Pagos - Efectivo/Transferencia
**Objetivo:** Validar pagos inmediatos que reducen deuda

#### Pruebas:
1. **Pago Parcial - Efectivo**
   - Cliente: "Cliente con Crédito" (debe $750)
   - Monto: $300
   - Método: Efectivo
   - ✅ Verificar:
     - Cliente `saldo_actual = -$450` (deuda restante)
     - Cliente `disponible = $999,550`
     - Venta `estado_pago = 'parcial'`
     - Movimiento CC: tipo='pago', monto=-$300

2. **Pago Completo - Transferencia**
   - Cliente: "Cliente con Crédito" (debe $450)
   - Monto: $450
   - Método: Transferencia
   - ✅ Verificar:
     - Cliente `saldo_actual = $0` (sin deuda)
     - Cliente `disponible = $1,000,000` (límite completo)
     - Venta `estado_pago = 'pagado'`
     - Alerta verde: "✅ Esta venta está completamente pagada"

---

### 6️⃣ MÓDULO: Pagos - Cheques PENDIENTES
**Objetivo:** Validar que cheques pendientes NO reducen deuda

#### Pruebas:
1. **Crear Nueva Venta a CC**
   - Cliente: "Cliente con Crédito"
   - Productos: PROD001 x 10 = $1,500
   - Método: Cuenta Corriente - $1,500
   - ✅ Verificar:
     - Cliente `saldo_actual = -$1,500`
     - `estado_pago = 'pendiente'`

2. **Registrar Cheque PENDIENTE**
   - Cliente: "Cliente con Crédito"
   - Venta: la del paso anterior
   - Monto: $1,000
   - Método: Cheque
   - Número: 00112233
   - Fecha Emisión: hoy
   - Fecha Cobro: +30 días
   - Estado: **PENDIENTE**
   - ✅ Verificar CRÍTICO:
     - Cliente `saldo_actual = -$1,500` (NO CAMBIA)
     - Cliente `disponible = $998,500` (NO CAMBIA)
     - Venta `estado_pago = 'parcial'`
     - Venta `totalChequesPendientes = $1,000`
     - Alerta amarilla: "⚠️ Hay $1,000 en cheques pendientes de cobro"
     - **NO se crea MovimientoCuentaCorriente**

3. **Validar Cheques Pendientes en Módulo**
   - Ir a Pagos → Cheques
   - ✅ Verificar:
     - Aparece cheque 00112233
     - Estado: "Pendiente"
     - Monto: $1,000
     - Vencimiento: fecha +30 días
     - Botón: "Marcar como Cobrado"

---

### 7️⃣ MÓDULO: Pagos - Cheques COBRADOS
**Objetivo:** Validar que al cobrar cheque SE REDUCE la deuda

#### Pruebas:
1. **Marcar Cheque como COBRADO**
   - Cheque: 00112233 ($1,000)
   - Acción: Botón "Marcar como Cobrado"
   - ✅ Verificar CRÍTICO:
     - Cheque `estado_cheque = 'cobrado'`
     - Cliente `saldo_actual = -$500` (se redujo $1,000)
     - Cliente `disponible = $999,500`
     - Venta `estado_pago = 'parcial'` (aún debe $500)
     - Venta `totalChequesPendientes = $0`
     - Movimiento CC creado: tipo='pago', monto=-$1,000, descripción="Cobro de cheque #00112233"

2. **Pagar Saldo Restante**
   - Pago: $500 en Efectivo
   - ✅ Verificar:
     - Cliente `saldo_actual = $0`
     - Venta `estado_pago = 'pagado'`
     - Alerta verde: "✅ Esta venta está completamente pagada"

---

### 8️⃣ MÓDULO: Pagos - Cheques RECHAZADOS
**Objetivo:** Validar que cheque rechazado no afecta saldo

#### Pruebas:
1. **Crear Venta y Cheque Pendiente**
   - Venta: $2,000 a CC
   - Cheque: $2,000 PENDIENTE
   - ✅ `saldo_actual = -$2,000` (sin cambios)

2. **Marcar Cheque como RECHAZADO**
   - Acción: Botón "Marcar como Rechazado"
   - ✅ Verificar:
     - Cheque `estado_cheque = 'rechazado'`
     - Cliente `saldo_actual = -$2,000` (sin cambios)
     - Venta `estado_pago = 'pendiente'`
     - Venta `totalChequesPendientes = $0`

---

### 9️⃣ MÓDULO: Cuenta Corriente - Historial
**Objetivo:** Validar movimientos y saldos acumulados

#### Pruebas:
1. **Ver Historial de Movimientos**
   - Cliente: "Cliente con Crédito"
   - ✅ Verificar orden cronológico:
     - Venta #1: +$750 (debe)
     - Pago efectivo: -$300 (haber)
     - Pago transferencia: -$450 (haber)
     - Venta #2: +$1,500 (debe)
     - Pago cheque cobrado: -$1,000 (haber)
     - Pago efectivo: -$500 (haber)
     - Venta #3: +$2,000 (debe)
   - ✅ Saldo actual: -$2,000

2. **Exportar Cuenta Corriente**
   - Acción: Botón "Exportar a Excel"
   - ✅ Verificar: archivo descargado con todos los movimientos

---

### 🔟 MÓDULO: Consolidar Pagos
**Objetivo:** Limpiar deudas cubiertas por pagos reales

#### Pruebas:
1. **Escenario: Deuda Cubierta**
   - Venta: $1,000 a CC (deuda)
   - Pago: $1,000 en Efectivo
   - ✅ Estado antes: `saldo_actual = $0`, pero venta sigue como 'pendiente'
   - Acción: Botón "Consolidar Pagos"
   - ✅ Verificar:
     - Venta `estado_pago = 'pagado'`
     - Alerta verde

---

### 1️⃣1️⃣ MÓDULO: Reportes
**Objetivo:** Validar métricas y exportaciones

#### Pruebas:
1. **Reporte de Ventas**
   - Filtrar: último mes
   - ✅ Verificar: todas las ventas listadas
   - ✅ Total correcto

2. **Reporte de Cuenta Corriente**
   - Cliente: "Cliente con Crédito"
   - ✅ Verificar: deuda actual correcta

3. **Exportar a Excel**
   - Ventas, Productos, Clientes
   - ✅ Verificar: archivos generados correctamente

---

### 1️⃣2️⃣ MÓDULO: WhatsApp
**Objetivo:** Validar envío de mensajes

#### Pruebas:
1. **Enviar Presupuesto**
   - Acción: Botón WhatsApp en venta
   - ✅ Verificar: se abre WhatsApp Web con mensaje formateado

2. **Enviar Recordatorio de Pago**
   - Cliente con deuda
   - ✅ Verificar: mensaje con monto adeudado

---

## 🚨 Casos de Error a Validar

### Límites de Crédito
- ❌ Venta CC que excede disponible
- ❌ Cliente sin límite intenta comprar a CC

### Stock
- ❌ Venta de producto sin stock
- ❌ Cantidad mayor al stock disponible

### Pagos
- ❌ Pago mayor al saldo adeudado
- ❌ Monto de pago negativo o cero
- ❌ Cheque sin número o fecha

### Validaciones Generales
- ❌ Campos obligatorios vacíos
- ❌ Formatos inválidos (email, CUIT, teléfono)
- ❌ Duplicados (códigos, emails, CUITs)

---

## ✅ Checklist de Corrección de Errores

Después de cada prueba que FALLE, documentar:

1. **Módulo:** _______________
2. **Acción:** _______________
3. **Resultado Esperado:** _______________
4. **Resultado Obtenido:** _______________
5. **Error/Bug Identificado:** _______________
6. **Solución Aplicada:** _______________
7. **Re-test:** ✅ / ❌

---

## 📝 Notas Importantes

- Realizar pruebas en **orden secuencial** (no saltear pasos)
- Verificar **cada ✅** antes de continuar
- Si algo falla, **documentar y corregir** antes de avanzar
- Prestar especial atención al flujo de **cheques pendientes/cobrados**
- Validar que `saldo_actual` y `disponible` sean **siempre consistentes**

---

## 🎯 Criterios de Éxito

✅ Todos los módulos funcionan sin errores
✅ Cálculos de cuenta corriente correctos
✅ Cheques pendientes NO reducen deuda
✅ Cheques cobrados SÍ reducen deuda
✅ Estados de pago precisos (pendiente/parcial/pagado)
✅ Alertas correctas en cada caso
✅ Límites de crédito respetados
✅ Stock actualizado correctamente
✅ Exportaciones funcionales
