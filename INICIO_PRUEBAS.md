# ✅ Base de Datos Limpia - Lista para Pruebas Manuales

## 📊 Estado Actual

### Base de Datos Reseteada
```
✅ Tablas eliminadas y recreadas
✅ Migraciones ejecutadas: 25 tablas
✅ Seeders ejecutados: Solo datos esenciales
```

### Datos Iniciales
| Tabla | Registros |
|-------|-----------|
| Usuarios | **1** (solo admin) |
| Clientes | **0** |
| Productos | **0** |
| Ventas | **0** |
| Pagos | **0** |
| Métodos de Pago | **7** |

---

## 🔐 Credenciales de Acceso

```
📧 Email: admin@example.com
🔑 Password: secret123
👤 Rol: Administrador
✅ Permisos: TODOS
```

---

## 🎯 Métodos de Pago Disponibles

Los siguientes métodos están listos para usar:

1. **Efectivo**
2. **Transferencia**
3. **Débito**
4. **Crédito**
5. **Cheque** (con gestión de estados: pendiente/cobrado/rechazado)
6. **Mercado Pago**
7. **Cuenta Corriente** (deuda del cliente)

---

## 📋 Siguiente Paso: Ejecutar Plan de Pruebas

Abrir el archivo: **`PLAN_PRUEBAS_MANUAL.md`**

### Orden de Ejecución:
1. **Módulo Clientes** - Crear clientes con y sin crédito
2. **Módulo Productos** - Crear productos con stock
3. **Módulo Ventas Contado** - Validar pagos inmediatos
4. **Módulo Ventas CC** - Validar cuenta corriente
5. **Módulo Pagos Efectivo/Transferencia** - Reducir deuda
6. **Módulo Cheques Pendientes** - CRÍTICO: NO deben reducir saldo
7. **Módulo Cheques Cobrados** - CRÍTICO: SÍ deben reducir saldo
8. **Módulo Cheques Rechazados** - No afectan saldo
9. **Módulo Cuenta Corriente** - Validar historial
10. **Módulo Consolidar Pagos** - Limpiar inconsistencias
11. **Módulo Reportes** - Exportaciones
12. **Módulo WhatsApp** - Mensajes

---

## ⚠️ Puntos Críticos a Validar

### 🎯 Cheques (Máxima Prioridad)
- ✅ Cheque PENDIENTE → `saldo_actual` NO cambia
- ✅ Cheque COBRADO → `saldo_actual` SE REDUCE
- ✅ Cheque RECHAZADO → `saldo_actual` NO cambia
- ✅ Venta con cheque pendiente → `estado_pago = 'parcial'`
- ✅ Alerta amarilla cuando hay cheques pendientes

### 🎯 Cuenta Corriente
- ✅ `disponible = limite_credito + saldo_actual`
- ✅ `saldo_actual` negativo = deuda
- ✅ No permitir ventas que excedan el disponible
- ✅ Cada movimiento registrado correctamente

### 🎯 Estados de Pago
- ✅ `pendiente` = deuda sin pagar
- ✅ `parcial` = pagado parcialmente O hay cheques pendientes
- ✅ `pagado` = 100% pagado sin cheques pendientes

---

## 🔧 Comandos Útiles

### Ver estado de la base de datos
```bash
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan tinker --execute="
  echo 'Usuarios: ' . \App\Models\Usuario::count() . PHP_EOL;
  echo 'Clientes: ' . \App\Models\Cliente::count() . PHP_EOL;
  echo 'Productos: ' . \App\Models\Producto::count() . PHP_EOL;
  echo 'Ventas: ' . \App\Models\Venta::count() . PHP_EOL;
"
```

### Resetear base de datos (si es necesario)
```bash
cd c:\laragon\www\CRM-MP\CRM_MP\api
php artisan migrate:fresh --seed
```

### Ver métodos de pago
```bash
php artisan tinker --execute="
  \App\Models\MetodoPago::all()->each(function(\$mp) {
    echo \$mp->id . ' - ' . \$mp->nombre . PHP_EOL;
  });
"
```

---

## 📝 Registro de Errores

Durante las pruebas, documentar cada error encontrado:

### Template:
```
FECHA: _______
MÓDULO: _______
ACCIÓN: _______
ESPERADO: _______
OBTENIDO: _______
ERROR: _______
SOLUCIÓN: _______
✅ CORREGIDO
```

---

## 🚀 ¡Listo para Empezar!

1. Login en el sistema con `admin@example.com` / `secret123`
2. Abrir `PLAN_PRUEBAS_MANUAL.md`
3. Seguir paso a paso desde **1️⃣ MÓDULO: Clientes**
4. Marcar cada ✅ al completar
5. Documentar errores encontrados
6. Corregir y re-testear

**El flujo de pagos es lo más importante del sistema** - Prestar especial atención a los módulos 6️⃣, 7️⃣ y 8️⃣ (Cheques).
