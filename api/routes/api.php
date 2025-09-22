<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\Api\ClientesController;
use App\Http\Controllers\Api\ProductosController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\CuentaCorrienteController;
use App\Http\Controllers\AuthController;

Route::prefix('v1')->middleware('auth:api')->group(function () {

    Route::apiResource('clientes', ClientesController::class)
        ->parameters(['clientes' => 'cliente']);        // explícito 

    Route::apiResource('productos', ProductosController::class)
        ->parameters(['productos' => 'producto']);      // explícito 

    Route::apiResource('proveedores', ProveedorController::class)
        ->parameters(['proveedores' => 'proveedor']);   // <-- explícito
    
        Route::apiResource('ventas', VentaController::class)
        ->parameters(['ventas' => 'venta'])
        ->only(['index','store','show'])
        ->names('ventas');

    // Pagos por venta  
    Route::get('ventas/{venta}/pagos', [PagoController::class, 'index'])->name('pagos.index');
    Route::post('ventas/{venta}/pagos', [PagoController::class, 'store'])->name('pagos.store');

    // Catálogo de métodos de pago
    Route::get('metodos-pago', [MetodoPagoController::class, 'index'])->name('metodos_pago.index');

    // Cuenta corriente por cliente
    Route::get('clientes/{cliente}/cuenta-corriente', [CuentaCorrienteController::class, 'show'])->name('cta_cte.show');
    
    
    // Rutas de autenticación
    Route::post('logout',  [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me',      [AuthController::class, 'me']);

});




// Rutas públicas
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::get('/ping', function () {
    return response()->json([
        'status'  => 'ok',
        'message' => 'API funcionando correctamente 🚀'
    ]);
});
