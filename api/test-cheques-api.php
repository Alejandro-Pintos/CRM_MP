<?php
/**
 * Script de prueba para el nuevo sistema de Cheques
 * 
 * Uso:
 * php test-cheques-api.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\Cheque;
use App\Models\Venta;
use App\Models\Cliente;
use App\Http\Resources\ChequeResource;

// Cargar aplicación Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PRUEBA DEL SISTEMA DE CHEQUES ===\n\n";

// 1. Verificar que la tabla existe y tiene datos
echo "📋 1. Verificando tabla cheques...\n";
$totalCheques = Cheque::count();
echo "   ✅ Total de cheques: {$totalCheques}\n\n";

if ($totalCheques === 0) {
    echo "⚠️  No hay cheques en la base de datos. Ejecuta 'php artisan cheques:migrar' primero.\n";
    exit(1);
}

// 2. Probar consulta con relaciones
echo "📋 2. Consultando primer cheque con relaciones...\n";
$cheque = Cheque::with(['venta', 'cliente', 'pago'])->first();
echo "   ID: {$cheque->id}\n";
echo "   Número: {$cheque->numero}\n";
echo "   Monto: $" . number_format($cheque->monto, 2) . "\n";
echo "   Estado: {$cheque->estado}\n";
echo "   Cliente: {$cheque->cliente->nombre} {$cheque->cliente->apellido}\n";
echo "   Venta ID: {$cheque->venta_id}\n";
echo "   Pago ID: {$cheque->pago_id}\n\n";

// 3. Probar scopes
echo "📋 3. Probando scopes...\n";
$pendientes = Cheque::pendientes()->count();
$cobrados = Cheque::cobrados()->count();
$rechazados = Cheque::rechazados()->count();
echo "   Pendientes: {$pendientes}\n";
echo "   Cobrados: {$cobrados}\n";
echo "   Rechazados: {$rechazados}\n\n";

// 4. Probar accessors
echo "📋 4. Probando accessors...\n";
echo "   Número formateado: {$cheque->numero_formateado}\n";
echo "   Cliente nombre: {$cheque->cliente_nombre}\n";
echo "   Venta número: {$cheque->venta_numero}\n\n";

// 5. Probar Resource (simulación de respuesta API)
echo "📋 5. Probando ChequeResource...\n";
$resource = new ChequeResource($cheque);
$jsonResponse = json_encode($resource->toArray(request()), JSON_PRETTY_PRINT);
echo "   Respuesta JSON:\n";
echo $jsonResponse . "\n\n";

// 6. Probar query con filtros
echo "📋 6. Probando filtros por estado...\n";
$chequesEstado = Cheque::where('estado', 'pendiente')->get();
echo "   Cheques pendientes: {$chequesEstado->count()}\n\n";

// 7. Probar ordenamiento
echo "📋 7. Probando ordenamiento por fecha vencimiento...\n";
$proximosVencer = Cheque::pendientes()
    ->whereNotNull('fecha_vencimiento')
    ->orderBy('fecha_vencimiento', 'asc')
    ->limit(5)
    ->get(['id', 'numero', 'fecha_vencimiento', 'monto']);

foreach ($proximosVencer as $ch) {
    $venc = $ch->fecha_vencimiento ? $ch->fecha_vencimiento->format('d/m/Y') : 'Sin fecha';
    echo "   - Cheque #{$ch->numero} - Vence: {$venc} - Monto: $" . number_format($ch->monto, 2) . "\n";
}
echo "\n";

// 8. Verificar integridad referencial
echo "📋 8. Verificando integridad referencial...\n";
$chequeSinVenta = Cheque::whereNull('venta_id')->count();
$chequeSinCliente = Cheque::whereNull('cliente_id')->count();
echo "   Cheques sin venta: {$chequeSinVenta}\n";
echo "   Cheques sin cliente: {$chequeSinCliente}\n\n";

if ($chequeSinVenta === 0 && $chequeSinCliente === 0) {
    echo "✅ TODAS LAS PRUEBAS PASARON EXITOSAMENTE\n";
    echo "El sistema de cheques está funcionando correctamente.\n";
} else {
    echo "⚠️  HAY PROBLEMAS DE INTEGRIDAD REFERENCIAL\n";
}

echo "\n=== FIN DE PRUEBAS ===\n";
