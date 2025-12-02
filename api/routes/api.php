<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\Api\ClientesController;
use App\Http\Controllers\Api\ProductosController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\CuentaCorrienteController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\ChequeController;

Route::prefix('v1')->middleware('auth:api')->group(function () {

    Route::apiResource('clientes', ClientesController::class)
        ->parameters(['clientes' => 'cliente']);        // explícito 

    Route::apiResource('productos', ProductosController::class)
        ->parameters(['productos' => 'producto']);      // explícito 

    Route::apiResource('proveedores', ProveedorController::class)
        ->parameters(['proveedores' => 'proveedor']);   // <-- explícito
    
    // Pedidos
    Route::apiResource('pedidos', PedidoController::class)
        ->parameters(['pedidos' => 'pedido']);
    Route::get('pedidos-pendientes', [PedidoController::class, 'pendientes'])->name('pedidos.pendientes');
    Route::post('pedidos/{pedido}/asociar-venta', [PedidoController::class, 'asociarVenta'])->name('pedidos.asociar_venta');
    Route::get('clima', [PedidoController::class, 'getClima'])->name('clima.get');
    
    Route::apiResource('ventas', VentaController::class)
        ->parameters(['ventas' => 'venta'])
        ->only(['index','store','show','destroy'])
        ->names('ventas');

    // Previsualizar próximo número de comprobante
    Route::get('ventas/previsualizar-numero', [VentaController::class, 'previsualizarNumero'])
        ->name('ventas.previsualizar_numero');

    // Pagos por venta  
    Route::get('ventas/{venta}/pagos', [PagoController::class, 'index'])->name('pagos.index');
    Route::post('ventas/{venta}/pagos', [PagoController::class, 'store'])->name('pagos.store');
    Route::post('ventas/{venta}/consolidar-pagos', [PagoController::class, 'consolidarPagos'])->name('pagos.consolidar');
    
    // Actualizar datos de pago/cheque
    Route::patch('pagos/{pago}', [PagoController::class, 'update'])->name('pagos.update');
    
    // Actualizar estado de cheque
    Route::patch('pagos/{pago}/estado-cheque', [PagoController::class, 'actualizarEstadoCheque'])->name('pagos.estado_cheque');
    
    // === NUEVO SISTEMA DE CHEQUES (ChequeController) ===
    // IMPORTANTE: Las rutas específicas DEBEN ir ANTES de las rutas con parámetros
    // para evitar que Laravel intente hacer route model binding con "pendientes" o "historial"
    
    // Cheques pendientes (con alertas de vencimiento)
    Route::get('cheques/pendientes', [ChequeController::class, 'pendientes'])->name('cheques.pendientes');
    
    // Historial de cheques procesados (cobrados/rechazados)
    Route::get('cheques/historial', [ChequeController::class, 'historial'])->name('cheques.historial');
    
    // Listar cheques con filtros generales
    Route::get('cheques', [ChequeController::class, 'index'])->name('cheques.index');
    
    // Marcar cheque como cobrado
    Route::post('cheques/{cheque}/cobrar', [ChequeController::class, 'cobrar'])->name('cheques.cobrar');
    
    // Marcar cheque como rechazado
    Route::post('cheques/{cheque}/rechazar', [ChequeController::class, 'rechazar'])->name('cheques.rechazar');
    
    // Actualizar datos administrativos del cheque
    Route::patch('cheques/{cheque}', [ChequeController::class, 'update'])->name('cheques.update');
    
    // Ver detalle de un cheque (DEBE IR AL FINAL para no capturar "pendientes" o "historial")
    Route::get('cheques/{cheque}', [ChequeController::class, 'show'])->name('cheques.show');
    
    // Corregir cheques históricos
    Route::post('pagos/corregir-cheques-historicos', [PagoController::class, 'corregirChequesHistoricos'])->name('pagos.corregir_cheques');

    // Catálogo de métodos de pago
    Route::get('metodos-pago', [MetodoPagoController::class, 'index'])->name('metodos_pago.index');

    // Cuenta corriente por cliente
    Route::get('clientes/{cliente}/cuenta-corriente', [CuentaCorrienteController::class, 'show'])->name('cta_cte.show');
    Route::post('clientes/{cliente}/cuenta-corriente/pagos', [CuentaCorrienteController::class, 'registrarPago'])->name('cta_cte.registrar_pago');
    
    // Recalcular saldos de cuenta corriente
    Route::post('cuentas-corrientes/recalcular', [CuentaCorrienteController::class, 'recalcular'])->name('cta_cte.recalcular');

    // Presupuestos
    Route::post('presupuestos/enviar-email', [PresupuestoController::class, 'enviarEmail'])->name('presupuestos.enviar_email');

    // Reportes
    Route::get('reportes/ventas', [ReporteController::class, 'ventas'])->name('reportes.ventas');
    Route::get('reportes/clientes', [ReporteController::class, 'clientes'])->name('reportes.clientes');
    Route::get('reportes/productos', [ReporteController::class, 'productos'])->name('reportes.productos');
    Route::get('reportes/proveedores', [ReporteController::class, 'proveedores'])->name('reportes.proveedores');
    // Exportar reportes
    Route::get('reportes/ventas/export.csv', [ReporteController::class, 'exportVentasCsv'])
    ->middleware('permission:reportes.export')
    ->name('reportes.ventas.export.csv');
    Route::get('reportes/ventas/export.xlsx', [ReporteController::class, 'exportVentasXlsx'])
    ->middleware('permission:reportes.export')
    ->name('reportes.ventas.export.xlsx');
    Route::get('reportes/proveedores/export.xlsx', [ReporteController::class, 'exportProveedoresXlsx'])
    ->middleware('permission:reportes.export')
    ->name('reportes.proveedores.export.xlsx');
    Route::get('reportes/proveedores/export.csv', [ReporteController::class, 'exportProveedoresCsv'])
    ->middleware('permission:reportes.export')
    ->name('reportes.proveedores.export.csv');
    Route::get('reportes/clientes/export.xlsx', [ReporteController::class, 'exportClientesXlsx'])
    ->middleware('permission:reportes.export')
    ->name('reportes.clientes.export.xlsx');
    Route::get('reportes/clientes/export.csv', [ReporteController::class, 'exportClientesCsv'])
    ->middleware('permission:reportes.export')
    ->name('reportes.clientes.export.csv');
    Route::get('reportes/productos/export.xlsx', [ReporteController::class, 'exportProductosXlsx'])
    ->middleware('permission:reportes.export')
    ->name('reportes.productos.export.xlsx');
    Route::get('reportes/productos/export.csv', [ReporteController::class, 'exportProductosCsv'])
    ->middleware('permission:reportes.export')
    ->name('reportes.productos.export.csv');

    //Reporte full
    Route::get('reportes/full/single.xlsx',
    [ReporteController::class, 'exportFullSingleSheetXlsx']
    )->middleware('permission:reportes.export')
    ->name('reportes.full_single_export');

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
