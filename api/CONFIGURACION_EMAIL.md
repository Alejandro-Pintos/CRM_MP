# Configuración de Envío de Emails - Presupuestos

## ✅ Funcionalidad Implementada

El sistema ahora puede enviar presupuestos por email con:
- Email HTML profesional
- Tabla de productos con precios
- Información del cliente
- Condiciones de pago y observaciones
- Diseño responsive

## 🔧 Configuración Actual (Desarrollo)

**Estado:** Los emails se guardan en logs (`storage/logs/laravel.log`)

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="ERP-MP"
```

**Ventaja:** Puedes ver el contenido del email sin configurar un servidor SMTP.

## 📧 Configuración para Producción

### Opción 1: Gmail (Recomendado para pruebas)

1. **Habilita acceso de apps menos seguras** o **crea una contraseña de aplicación**:
   - Ve a: https://myaccount.google.com/security
   - Activa verificación en 2 pasos
   - Genera contraseña de aplicación

2. **Actualiza `.env`**:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-contraseña-de-aplicacion
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="tu-email@gmail.com"
MAIL_FROM_NAME="Tu Empresa"
```

### Opción 2: Mailtrap (Recomendado para desarrollo)

1. **Regístrate** en https://mailtrap.io (gratis)

2. **Copia las credenciales** de tu inbox

3. **Actualiza `.env`**:
```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=tu-username
MAIL_PASSWORD=tu-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="presupuestos@tuempresa.com"
MAIL_FROM_NAME="Tu Empresa"
```

### Opción 3: SendGrid (Recomendado para producción)

1. **Regístrate** en https://sendgrid.com

2. **Crea una API Key**

3. **Actualiza `.env`**:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=tu-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@tuempresa.com"
MAIL_FROM_NAME="Tu Empresa"
```

## 🧪 Cómo Probar

### 1. Con MAIL_MAILER=log (actual):

1. Crea un presupuesto
2. Click en "Enviar por Email"
3. Abre `api/storage/logs/laravel.log`
4. Verás el HTML del email completo

### 2. Con servicio SMTP:

1. Configura el `.env` con una de las opciones anteriores
2. Ejecuta: `cd api; php artisan config:clear`
3. Crea un presupuesto
4. Click en "Enviar por Email"
5. El email se enviará al cliente

## 📝 Archivos Creados

### Backend:
- `app/Http/Controllers/PresupuestoController.php` - Controlador
- `app/Mail/PresupuestoMail.php` - Mailable
- `resources/views/emails/presupuesto.blade.php` - Template HTML
- `routes/api.php` - Ruta agregada

### Frontend:
- `services/presupuestos.js` - Servicio API
- Actualizado: `pages/ventas/presupuesto.vue`

## 🎨 Personalizar el Email

Edita el archivo: `api/resources/views/emails/presupuesto.blade.php`

**Ejemplo - Agregar logo:**
```html
<div class="header">
    <img src="https://tuempresa.com/logo.png" alt="Logo" style="max-width: 150px;">
    <h1>PRESUPUESTO</h1>
    ...
</div>
```

**Ejemplo - Cambiar colores:**
```css
.header {
    background-color: #your-color;
}
```

## 🚀 Testing

**Comando para probar envío:**
```bash
cd api
php artisan tinker
```

```php
Mail::raw('Test', function($message) {
    $message->to('test@example.com')->subject('Test Email');
});
```

Si devuelve `null` sin errores, la configuración está correcta.

## ⚠️ Troubleshooting

### "Connection refused"
- Verifica MAIL_HOST y MAIL_PORT
- Comprueba firewall

### "Authentication failed"
- Verifica MAIL_USERNAME y MAIL_PASSWORD
- Si usas Gmail, usa contraseña de aplicación

### "Address in mailbox given does not comply"
- Verifica MAIL_FROM_ADDRESS (debe ser email válido)

### Email no llega
- Revisa carpeta de spam
- Verifica logs: `storage/logs/laravel.log`

## 📊 Monitoreo

Para ver si los emails se envían correctamente:

```bash
# Ver últimos emails en log
tail -f api/storage/logs/laravel.log | grep MAIL
```

## 🔐 Seguridad

**Importante:**
- Nunca subas `.env` al repositorio
- Usa variables de entorno en producción
- Limita tasa de envío para evitar spam

## 💡 Mejoras Futuras

- [ ] Adjuntar PDF del presupuesto al email
- [ ] Cola de emails (Queue) para no bloquear la UI
- [ ] Plantillas personalizables desde la UI
- [ ] Historial de emails enviados
- [ ] Tracking de apertura de emails
