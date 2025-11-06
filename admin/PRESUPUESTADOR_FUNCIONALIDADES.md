# Presupuestador - Funcionalidades Implementadas

## 📋 Características

### 1. **Exportar a PDF** ✅
Genera un PDF profesional del presupuesto con:
- Encabezado con logo y datos de la empresa
- Información del cliente
- Tabla detallada de productos con cantidades y precios
- Total destacado
- Condiciones de pago
- Observaciones
- Pie de página con validez

**Tecnología:**
- `jspdf` - Generación de PDFs
- `html2canvas` - Conversión de HTML a imagen

**Uso:**
1. Completa el presupuesto (cliente + productos)
2. Click en "Exportar a PDF"
3. El archivo se descarga automáticamente

**Nombre del archivo:**
`Presupuesto_[Apellido]_[Fecha].pdf`

### 2. **Enviar por Email** ✅
Envía el presupuesto directamente al email del cliente.

**Validaciones:**
- Cliente debe tener email registrado
- Debe haber al menos un producto agregado

**Estado actual:**
- Frontend: ✅ Implementado con validaciones
- Backend: ⏳ Pendiente (requiere configuración de SMTP)

**Datos enviados:**
```javascript
{
  cliente: {
    nombre, apellido, email, cuit
  },
  presupuesto: {
    fecha, fecha_vencimiento,
    productos: [...],
    total, condiciones_pago,
    observaciones, validez
  }
}
```

### 3. **Imprimir** ✅
Vista optimizada para impresión con CSS específico.

**Características:**
- Elimina navegación y elementos de UI
- Formato A4
- Oculta botones de acción
- Saltos de página inteligentes

## 🎨 Interfaz

### Botones de Acción
Todos los botones se deshabilitan si:
- No hay cliente seleccionado
- No hay productos agregados

### Indicador de Carga
Overlay visual cuando:
- Se genera el PDF
- Se envía el email
- Se procesan datos

## 🔧 Instalación de Dependencias

**Necesarias para PDF:**
```bash
npm install jspdf html2canvas
```

## 📝 Formato del PDF Generado

```
┌─────────────────────────────────────┐
│  PRESUPUESTO                        │
│  Fecha: 05/11/2025                  │
│  Válido hasta: 20/11/2025           │
├─────────────────────────────────────┤
│  Cliente                            │
│  Juan Pérez                         │
│  juan@email.com                     │
│  CUIT: 20-12345678-9                │
├─────────────────────────────────────┤
│  Producto | Cant | P.Unit | Total  │
│  Mouse    | 2    | $1.500 | $3.000 │
│  Teclado  | 1    | $5.000 | $5.000 │
├─────────────────────────────────────┤
│                    TOTAL: $ 8.000   │
├─────────────────────────────────────┤
│  Condiciones de Pago                │
│  Pago contado / Transferencia       │
├─────────────────────────────────────┤
│  Observaciones                      │
│  ...                                │
├─────────────────────────────────────┤
│  Válido por 15 días                 │
│  Precios sujetos a modificación     │
└─────────────────────────────────────┘
```

## 🚀 Próximos Pasos

### Backend para Email (Pendiente)
1. Crear endpoint `/api/presupuestos/enviar-email`
2. Configurar SMTP en Laravel (config/mail.php)
3. Crear Mailable para presupuestos
4. Generar PDF en servidor (dompdf o similar)
5. Adjuntar PDF al email

**Ejemplo de implementación:**
```php
// routes/api.php
Route::post('/presupuestos/enviar-email', [PresupuestoController::class, 'enviarEmail']);

// PresupuestoController.php
public function enviarEmail(Request $request) {
    $datos = $request->validated();
    
    // Generar PDF
    $pdf = PDF::loadView('emails.presupuesto-pdf', $datos);
    
    // Enviar email
    Mail::to($datos['cliente']['email'])
        ->send(new PresupuestoMail($datos, $pdf));
    
    return response()->json(['message' => 'Email enviado']);
}
```

## 🎯 Testing

**Casos de prueba:**
1. ✅ Generar PDF sin cliente (debe mostrar warning)
2. ✅ Generar PDF sin productos (debe mostrar warning)
3. ✅ Generar PDF completo (debe descargar)
4. ✅ Enviar email sin email del cliente (debe mostrar error)
5. ✅ Overlay de carga visible durante proceso
6. ✅ Botones deshabilitados según validaciones

## 💡 Notas Técnicas

### Generación de PDF
- Se crea un contenedor temporal en el DOM
- Se renderiza el HTML del presupuesto
- html2canvas captura el contenedor como imagen
- jsPDF crea el PDF e inserta la imagen
- Se elimina el contenedor temporal

### Optimización
- Escala 2x para mejor calidad
- Formato A4 (210mm x 297mm)
- CORS habilitado para imágenes
- Background blanco forzado

### Seguridad
- Validación de datos antes de enviar
- Sanitización de HTML en el PDF
- Verificación de email válido
