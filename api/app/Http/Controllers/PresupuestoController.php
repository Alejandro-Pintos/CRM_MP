<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\PresupuestoMail;
use App\Http\Requests\PresupuestoEmailRequest;

class PresupuestoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
    }

    /**
     * Enviar presupuesto por email
     */
    public function enviarEmail(Request $request)
    {
        $validated = $request->validate([
            'cliente.nombre' => 'required|string',
            'cliente.apellido' => 'required|string',
            'cliente.email' => 'required|email',
            'cliente.cuit' => 'nullable|string',
            'presupuesto.fecha' => 'required|date',
            'presupuesto.fecha_vencimiento' => 'required|date',
            'presupuesto.productos' => 'required|array|min:1',
            'presupuesto.productos.*.nombre' => 'required|string',
            'presupuesto.productos.*.cantidad' => 'required|numeric|min:1',
            'presupuesto.productos.*.precio_unitario' => 'required|numeric|min:0',
            'presupuesto.productos.*.subtotal' => 'required|numeric|min:0',
            'presupuesto.total' => 'required|numeric|min:0',
            'presupuesto.condiciones_pago' => 'nullable|string',
            'presupuesto.observaciones' => 'nullable|string',
            'presupuesto.validez' => 'nullable|string',
        ]);

        try {
            // Enviar email
            Mail::to($validated['cliente']['email'])
                ->send(new PresupuestoMail($validated));

            return response()->json([
                'message' => 'Presupuesto enviado correctamente',
                'email' => $validated['cliente']['email']
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error al enviar presupuesto por email: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al enviar el presupuesto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
