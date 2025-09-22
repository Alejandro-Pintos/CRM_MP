<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\Api\ClientesController;
use App\Http\Controllers\Api\ProductosController;
use App\Http\Controllers\VentaController;
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
