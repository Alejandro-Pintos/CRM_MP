<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\Proveedor;
use App\Http\Resources\ChequeEmitidoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChequeEmitidoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
    }

    /**
     * GET /api/v1/cheques-emitidos
     * Listado general de cheques emitidos con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cheque::emitidos()->with(['proveedor', 'pagoProveedor']);

        // Filtros
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }

        $cheques = $query->orderBy('fecha_emision', 'desc')->get();

        // Resumen
        $resumen = [
            'total' => $cheques->count(),
            'monto_total' => (float) $cheques->sum('monto'),
            'pendientes' => $cheques->where('estado', 'pendiente')->count(),
            'debitados' => $cheques->where('estado', 'debitado')->count(),
            'anulados' => $cheques->where('estado', 'anulado')->count(),
        ];

        return response()->json([
            'data' => ChequeEmitidoResource::collection($cheques),
            'resumen' => $resumen,
        ]);
    }

    /**
     * GET /api/v1/proveedores/{proveedor}/cheques-emitidos
     * Cheques emitidos de un proveedor específico
     */
    public function byProveedor(Proveedor $proveedor): JsonResponse
    {
        $cheques = Cheque::emitidos()
            ->where('proveedor_id', $proveedor->id)
            ->with(['pagoProveedor'])
            ->orderBy('fecha_emision', 'desc')
            ->get();

        return response()->json([
            'data' => ChequeEmitidoResource::collection($cheques),
        ]);
    }

    /**
     * POST /api/v1/proveedores/{proveedor}/cheques-emitidos
     * Crear cheque emitido
     */
    public function store(Request $request, Proveedor $proveedor): JsonResponse
    {
        $data = $request->validate([
            'banco' => 'required|string|max:100',
            'numero' => 'required|string|max:50',
            'monto' => 'required|numeric|min:0.01',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_emision',
            'pago_proveedor_id' => 'nullable|exists:pagos_proveedores,id',
            'observaciones' => 'nullable|string',
        ]);

        // Validar duplicado (mismo banco y número)
        $existe = Cheque::emitidos()
            ->where('banco', $data['banco'])
            ->where('numero', $data['numero'])
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'numero' => ['Ya existe un cheque emitido con ese banco y número.']
            ]);
        }

        try {
            $cheque = Cheque::create([
                'tipo' => Cheque::TIPO_EMITIDO,
                'proveedor_id' => $proveedor->id,
                'banco' => $data['banco'],
                'numero' => $data['numero'],
                'monto' => $data['monto'],
                'fecha_emision' => $data['fecha_emision'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'pago_proveedor_id' => $data['pago_proveedor_id'] ?? null,
                'estado' => Cheque::ESTADO_PENDIENTE,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            return response()->json([
                'data' => new ChequeEmitidoResource($cheque->load('proveedor')),
                'message' => 'Cheque emitido registrado correctamente'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el cheque emitido',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/v1/cheques-emitidos/{cheque}
     * Ver detalle de un cheque emitido
     */
    public function show(Cheque $cheque): JsonResponse
    {
        if (!$cheque->esEmitido()) {
            return response()->json([
                'message' => 'Este cheque no es un cheque emitido'
            ], 400);
        }

        $cheque->load(['proveedor', 'pagoProveedor']);
        
        return response()->json([
            'data' => new ChequeEmitidoResource($cheque)
        ]);
    }

    /**
     * PATCH /api/v1/cheques-emitidos/{cheque}
     * Actualizar cheque emitido
     */
    public function update(Request $request, Cheque $cheque): JsonResponse
    {
        if (!$cheque->esEmitido()) {
            return response()->json([
                'message' => 'Este cheque no es un cheque emitido'
            ], 400);
        }

        if ($cheque->estado !== Cheque::ESTADO_PENDIENTE) {
            return response()->json([
                'message' => 'No se puede modificar un cheque que ya fue procesado'
            ], 400);
        }

        $data = $request->validate([
            'banco' => 'sometimes|string|max:100',
            'numero' => 'sometimes|string|max:50',
            'fecha_emision' => 'sometimes|date',
            'fecha_vencimiento' => 'nullable|date',
            'observaciones' => 'nullable|string',
        ]);

        // Si se cambia banco o número, validar que no exista duplicado
        if (isset($data['banco']) || isset($data['numero'])) {
            $banco = $data['banco'] ?? $cheque->banco;
            $numero = $data['numero'] ?? $cheque->numero;

            $existe = Cheque::emitidos()
                ->where('id', '!=', $cheque->id)
                ->where('banco', $banco)
                ->where('numero', $numero)
                ->exists();

            if ($existe) {
                throw ValidationException::withMessages([
                    'numero' => ['Ya existe otro cheque con ese banco y número.']
                ]);
            }
        }

        $cheque->update($data);

        return response()->json([
            'data' => new ChequeEmitidoResource($cheque->fresh('proveedor')),
            'message' => 'Cheque actualizado correctamente'
        ]);
    }

    /**
     * POST /api/v1/cheques-emitidos/{cheque}/debitar
     * Marcar como debitado (cobrado por el proveedor)
     */
    public function debitar(Cheque $cheque): JsonResponse
    {
        if (!$cheque->esEmitido()) {
            return response()->json([
                'message' => 'Este cheque no es un cheque emitido'
            ], 400);
        }

        if ($cheque->estado !== Cheque::ESTADO_PENDIENTE) {
            return response()->json([
                'message' => 'Este cheque ya fue procesado'
            ], 400);
        }

        $cheque->update([
            'estado' => Cheque::ESTADO_DEBITADO,
            'fecha_cobro' => now(),
        ]);

        return response()->json([
            'data' => new ChequeEmitidoResource($cheque->fresh('proveedor')),
            'message' => 'Cheque marcado como debitado'
        ]);
    }

    /**
     * POST /api/v1/cheques-emitidos/{cheque}/anular
     * Anular cheque emitido
     */
    public function anular(Request $request, Cheque $cheque): JsonResponse
    {
        if (!$cheque->esEmitido()) {
            return response()->json([
                'message' => 'Este cheque no es un cheque emitido'
            ], 400);
        }

        if ($cheque->estado !== Cheque::ESTADO_PENDIENTE) {
            return response()->json([
                'message' => 'Solo se pueden anular cheques pendientes'
            ], 400);
        }

        $data = $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        $cheque->update([
            'estado' => Cheque::ESTADO_ANULADO,
            'motivo_rechazo' => $data['motivo'],
            'fecha_rechazo' => now(),
        ]);

        return response()->json([
            'data' => new ChequeEmitidoResource($cheque->fresh('proveedor')),
            'message' => 'Cheque anulado correctamente'
        ]);
    }

    /**
     * DELETE /api/v1/cheques-emitidos/{cheque}
     * Eliminar cheque emitido (solo si está pendiente y no vinculado a pago)
     */
    public function destroy(Cheque $cheque): JsonResponse
    {
        if (!$cheque->esEmitido()) {
            return response()->json([
                'message' => 'Este cheque no es un cheque emitido'
            ], 400);
        }

        if ($cheque->estado !== Cheque::ESTADO_PENDIENTE) {
            return response()->json([
                'message' => 'Solo se pueden eliminar cheques pendientes'
            ], 400);
        }

        if ($cheque->pago_proveedor_id) {
            return response()->json([
                'message' => 'No se puede eliminar un cheque vinculado a un pago'
            ], 400);
        }

        $cheque->delete();

        return response()->json([
            'message' => 'Cheque eliminado correctamente'
        ], 204);
    }
}
