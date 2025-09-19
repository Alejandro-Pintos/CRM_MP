<?php

use App\Http\Controllers\Api\ClientesController;
use App\Http\Controllers\Api\ProductosController;

Route::prefix('v1')->middleware('auth:api')->group(function () {
    Route::apiResource('clientes', ClientesController::class);
    Route::apiResource('productos', ProductosController::class);
});
