<?php

use App\Http\Controllers\Api\ClientesController;
use App\Http\Controllers\Api\ProductosController;
use App\Http\Controllers\AuthController;

Route::prefix('v1')->middleware('auth:api')->group(function () {
    Route::apiResource('clientes', ClientesController::class);
    Route::apiResource('productos', ProductosController::class);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
});
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API funcionando correctamente 🚀'
    ]);
});
