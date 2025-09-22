<?php
namespace App\Http\Controllers;

use App\Models\MetodoPago;

class MetodoPagoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:metodos_pago.index')->only(['index']);
    }

    public function index()
    {
        return response()->json(
            MetodoPago::where('estado','activo')->orderBy('nombre')->get()
        );
    }
}
