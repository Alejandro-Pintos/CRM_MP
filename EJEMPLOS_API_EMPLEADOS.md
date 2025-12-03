# Ejemplos de Uso - API de Empleados

Este documento contiene ejemplos prácticos de cómo consumir la API del módulo de Empleados.

## 🔐 Autenticación

Todas las peticiones requieren el header de autenticación:

```http
Authorization: Bearer {token}
```

Obtener token:
```bash
POST /api/v1/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "secret123"
}
```

---

## 👥 Gestión de Empleados

### 1. Listar Todos los Empleados

```bash
GET /api/v1/empleados?per_page=all
```

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "nombre_completo": "Juan Pérez",
      "documento": "12345678",
      "telefono": "123456789",
      "email": null,
      "direccion": null,
      "puesto": "Operario",
      "notas": null,
      "activo": true,
      "created_at": "2025-12-02T21:29:09.000000Z",
      "updated_at": "2025-12-02T21:29:09.000000Z"
    }
  ]
}
```

### 2. Buscar Empleados

```bash
# Búsqueda por nombre, documento, teléfono o puesto
GET /api/v1/empleados?q=Juan&per_page=10

# Filtrar solo empleados activos
GET /api/v1/empleados?activo=1

# Filtrar solo empleados inactivos
GET /api/v1/empleados?activo=0

# Combinación de filtros
GET /api/v1/empleados?q=Operario&activo=1&per_page=20
```

### 3. Ver Detalle de un Empleado

```bash
GET /api/v1/empleados/1
```

**Respuesta:**
```json
{
  "data": {
    "id": 1,
    "nombre_completo": "Juan Pérez",
    "documento": "12345678",
    "telefono": "123456789",
    "email": "juan@example.com",
    "direccion": "Calle Falsa 123",
    "puesto": "Operario",
    "notas": "Empleado de confianza",
    "activo": true,
    "created_at": "2025-12-02T21:29:09.000000Z",
    "updated_at": "2025-12-02T21:29:09.000000Z",
    "total_pagos": 450000.00,
    "cantidad_pagos": 3
  }
}
```

### 4. Crear un Empleado

```bash
POST /api/v1/empleados
Content-Type: application/json

{
  "nombre_completo": "María García",
  "documento": "87654321",
  "telefono": "987654321",
  "email": "maria@example.com",
  "direccion": "Av. Siempre Viva 742",
  "puesto": "Administrativa",
  "notas": "Encargada de facturación",
  "activo": true
}
```

**Respuesta:**
```json
{
  "data": {
    "id": 2,
    "nombre_completo": "María García",
    "documento": "87654321",
    "telefono": "987654321",
    "email": "maria@example.com",
    "direccion": "Av. Siempre Viva 742",
    "puesto": "Administrativa",
    "notas": "Encargada de facturación",
    "activo": true,
    "created_at": "2025-12-02T22:00:00.000000Z",
    "updated_at": "2025-12-02T22:00:00.000000Z"
  },
  "message": "Empleado creado correctamente"
}
```

**Códigos de Estado:**
- `201 Created` - Empleado creado exitosamente
- `422 Unprocessable Entity` - Errores de validación

**Validaciones:**
- `nombre_completo`: requerido, máximo 255 caracteres
- `documento`: requerido, único, máximo 50 caracteres
- `puesto`: requerido, máximo 100 caracteres
- `telefono`: opcional, máximo 50 caracteres
- `email`: opcional, formato email válido, máximo 150 caracteres
- `direccion`: opcional, máximo 255 caracteres
- `notas`: opcional, texto libre
- `activo`: opcional, boolean (default: true)

### 5. Actualizar un Empleado

```bash
PUT /api/v1/empleados/1
Content-Type: application/json

{
  "telefono": "111222333",
  "email": "juan.perez@nuevoemail.com",
  "puesto": "Encargado de Depósito"
}
```

**Nota:** Solo los campos enviados serán actualizados (actualización parcial).

**Respuesta:**
```json
{
  "data": {
    "id": 1,
    "nombre_completo": "Juan Pérez",
    "documento": "12345678",
    "telefono": "111222333",
    "email": "juan.perez@nuevoemail.com",
    "direccion": null,
    "puesto": "Encargado de Depósito",
    "notas": null,
    "activo": true,
    "created_at": "2025-12-02T21:29:09.000000Z",
    "updated_at": "2025-12-02T22:15:00.000000Z"
  },
  "message": "Empleado actualizado correctamente"
}
```

### 6. Desactivar un Empleado

```bash
PUT /api/v1/empleados/1
Content-Type: application/json

{
  "activo": false
}
```

### 7. Eliminar un Empleado (Soft Delete)

```bash
DELETE /api/v1/empleados/1
```

**Respuesta:**
- `204 No Content` - Empleado eliminado exitosamente

**Nota:** El empleado se marca como eliminado (soft delete) pero no se borra físicamente de la base de datos. Su historial de pagos se mantiene intacto.

---

## 💰 Gestión de Pagos a Empleados

### 8. Listar Pagos de un Empleado

```bash
GET /api/v1/empleados/1/pagos
```

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "empleado_id": 1,
      "fecha_pago": "2025-12-01",
      "monto": 150000.00,
      "metodo_pago_id": 1,
      "concepto": "sueldo",
      "observaciones": "Pago mensual",
      "metodo_pago": {
        "id": 1,
        "nombre": "Efectivo"
      },
      "empleado": {
        "id": 1,
        "nombre_completo": "Juan Pérez",
        "documento": "12345678"
      }
    },
    {
      "id": 2,
      "empleado_id": 1,
      "fecha_pago": "2025-11-15",
      "monto": 50000.00,
      "metodo_pago_id": 2,
      "concepto": "anticipo",
      "observaciones": "Anticipo quincenal",
      "metodo_pago": {
        "id": 2,
        "nombre": "Transferencia"
      },
      "empleado": {
        "id": 1,
        "nombre_completo": "Juan Pérez",
        "documento": "12345678"
      }
    }
  ],
  "resumen": {
    "total_pagos": 2,
    "monto_total": 200000.00
  }
}
```

### 9. Filtrar Pagos por Fecha

```bash
# Pagos desde una fecha específica
GET /api/v1/empleados/1/pagos?fecha_desde=2025-11-01

# Pagos hasta una fecha específica
GET /api/v1/empleados/1/pagos?fecha_hasta=2025-12-31

# Pagos en un rango de fechas
GET /api/v1/empleados/1/pagos?fecha_desde=2025-11-01&fecha_hasta=2025-12-31
```

### 10. Registrar un Pago a un Empleado

```bash
POST /api/v1/empleados/1/pagos
Content-Type: application/json

{
  "fecha_pago": "2025-12-02",
  "monto": 150000.00,
  "metodo_pago_id": 1,
  "concepto": "sueldo",
  "observaciones": "Pago mensual diciembre 2025"
}
```

**Respuesta:**
```json
{
  "data": {
    "id": 3,
    "empleado_id": 1,
    "fecha_pago": "2025-12-02",
    "monto": 150000.00,
    "metodo_pago_id": 1,
    "concepto": "sueldo",
    "observaciones": "Pago mensual diciembre 2025",
    "metodo_pago": {
      "id": 1,
      "nombre": "Efectivo"
    }
  },
  "message": "Pago registrado correctamente"
}
```

**Códigos de Estado:**
- `201 Created` - Pago registrado exitosamente
- `422 Unprocessable Entity` - Errores de validación

**Validaciones:**
- `fecha_pago`: requerido, formato fecha válido
- `monto`: requerido, numérico, mínimo 0.01
- `concepto`: requerido, máximo 100 caracteres
- `metodo_pago_id`: opcional, debe existir en tabla metodos_pago
- `observaciones`: opcional, texto libre

**Conceptos Válidos:**
- `sueldo` - Pago de sueldo mensual
- `anticipo` - Anticipo quincenal o adelanto
- `extra` - Horas extra o trabajo adicional
- `bono` - Bonificaciones especiales
- `aguinaldo` - Sueldo anual complementario
- `otro` - Otros conceptos

### 11. Registrar Diferentes Tipos de Pagos

#### Pago de Sueldo
```json
{
  "fecha_pago": "2025-12-01",
  "monto": 250000.00,
  "metodo_pago_id": 2,
  "concepto": "sueldo",
  "observaciones": "Sueldo mensual diciembre"
}
```

#### Anticipo Quincenal
```json
{
  "fecha_pago": "2025-12-15",
  "monto": 75000.00,
  "metodo_pago_id": 1,
  "concepto": "anticipo",
  "observaciones": "Anticipo segunda quincena"
}
```

#### Bono por Desempeño
```json
{
  "fecha_pago": "2025-12-20",
  "monto": 50000.00,
  "metodo_pago_id": 1,
  "concepto": "bono",
  "observaciones": "Bono por cumplimiento de objetivos"
}
```

#### Pago de Horas Extra
```json
{
  "fecha_pago": "2025-12-10",
  "monto": 35000.00,
  "metodo_pago_id": 1,
  "concepto": "extra",
  "observaciones": "20 horas extra en noviembre"
}
```

### 12. Eliminar un Pago

```bash
DELETE /api/v1/pagos-empleados/3
```

**Respuesta:**
```json
{
  "message": "Pago eliminado correctamente"
}
```

**Códigos de Estado:**
- `204 No Content` - Pago eliminado exitosamente

---

## 🔍 Ejemplos de Búsqueda y Filtrado

### Buscar Empleados por Nombre
```bash
GET /api/v1/empleados?q=Juan
```

### Buscar Empleados por Documento
```bash
GET /api/v1/empleados?q=12345678
```

### Buscar Empleados por Puesto
```bash
GET /api/v1/empleados?q=Operario
```

### Listar Solo Empleados Activos
```bash
GET /api/v1/empleados?activo=1&per_page=all
```

### Listar Empleados Inactivos con Paginación
```bash
GET /api/v1/empleados?activo=0&per_page=15
```

### Pagos de un Empleado del Mes Actual
```bash
GET /api/v1/empleados/1/pagos?fecha_desde=2025-12-01&fecha_hasta=2025-12-31
```

---

## 📊 Casos de Uso Completos

### Caso 1: Nuevo Empleado con Primer Pago

```bash
# 1. Crear empleado
POST /api/v1/empleados
{
  "nombre_completo": "Carlos Rodríguez",
  "documento": "20304050",
  "telefono": "1122334455",
  "puesto": "Chofer",
  "activo": true
}

# 2. Registrar primer pago (asumiendo que el empleado creado tiene ID 3)
POST /api/v1/empleados/3/pagos
{
  "fecha_pago": "2025-12-01",
  "monto": 180000.00,
  "metodo_pago_id": 2,
  "concepto": "sueldo",
  "observaciones": "Primer pago"
}
```

### Caso 2: Consultar Historial Completo de un Empleado

```bash
# 1. Ver datos del empleado con resumen de pagos
GET /api/v1/empleados/1

# 2. Ver todos los pagos del empleado
GET /api/v1/empleados/1/pagos
```

### Caso 3: Corrección de Pago Erróneo

```bash
# 1. Eliminar pago incorrecto
DELETE /api/v1/pagos-empleados/5

# 2. Registrar pago correcto
POST /api/v1/empleados/1/pagos
{
  "fecha_pago": "2025-12-01",
  "monto": 160000.00,
  "metodo_pago_id": 1,
  "concepto": "sueldo",
  "observaciones": "Pago corregido"
}
```

---

## ⚠️ Manejo de Errores

### Error 422: Validación Fallida

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "documento": [
      "El campo documento ya ha sido registrado."
    ],
    "email": [
      "El campo email debe ser una dirección de correo válida."
    ],
    "monto": [
      "El campo monto debe ser al menos 0.01."
    ]
  }
}
```

### Error 404: Recurso No Encontrado

```json
{
  "message": "No query results for model [App\\Models\\Empleado] 999"
}
```

### Error 401: No Autenticado

```json
{
  "message": "Unauthenticated."
}
```

### Error 403: Sin Permisos

```json
{
  "message": "This action is unauthorized."
}
```

---

## 🧪 Prueba con cURL

### Crear Empleado
```bash
curl -X POST http://localhost/api/v1/empleados \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre_completo": "Test Usuario",
    "documento": "99887766",
    "puesto": "Test"
  }'
```

### Listar Empleados
```bash
curl -X GET "http://localhost/api/v1/empleados?per_page=all" \
  -H "Authorization: Bearer {token}"
```

### Registrar Pago
```bash
curl -X POST http://localhost/api/v1/empleados/1/pagos \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "fecha_pago": "2025-12-02",
    "monto": 100000,
    "concepto": "sueldo"
  }'
```

---

## 📱 Prueba con Postman

### Configuración de Environment

```
base_url = http://localhost/api/v1
token = {tu_token_aqui}
```

### Collection de Endpoints

1. **Login** → `POST {{base_url}}/login`
2. **Listar Empleados** → `GET {{base_url}}/empleados`
3. **Crear Empleado** → `POST {{base_url}}/empleados`
4. **Ver Empleado** → `GET {{base_url}}/empleados/:id`
5. **Actualizar Empleado** → `PUT {{base_url}}/empleados/:id`
6. **Eliminar Empleado** → `DELETE {{base_url}}/empleados/:id`
7. **Listar Pagos** → `GET {{base_url}}/empleados/:id/pagos`
8. **Registrar Pago** → `POST {{base_url}}/empleados/:id/pagos`
9. **Eliminar Pago** → `DELETE {{base_url}}/pagos-empleados/:pagoId`

---

**Última actualización:** 02 de Diciembre de 2025
