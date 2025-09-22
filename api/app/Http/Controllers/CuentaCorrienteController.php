<?php
namespace App\Http\Controllers;

use App\Http\Resources\MovimientoCtaCteResource;
use App\Models\Cliente;
use Illuminate\Http\Request;

class CuentaCorrienteController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:cta_cte.show')->only(['show']);
    }

    public function show(Cliente $cliente, Request $request)
    {
        $query = $cliente->hasMany(\App\Models\MovimientoCuentaCorriente::class,'cliente_id')
                         ->orderByDesc('fecha')
                         ->orderByDesc('id');

        $items = $query->paginate(15);

        return response()->json([
            'cliente_id' => $cliente->id,
            'saldo_actual' => (float)$cliente->saldo_actual,
            'movimientos' => MovimientoCtaCteResource::collection($items),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }
}
