<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Venta::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'cliente_id' => 'required|exists:clientes,id',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            
            'pagos' => 'nullable|array',
            'pagos.*.metodo_pago_id' => 'required_with:pagos|exists:metodos_pago,id',
            'pagos.*.monto' => 'required_with:pagos|numeric|min:0',
            'pagos.*.numero_cheque' => 'nullable|string|max:50',
            'pagos.*.fecha_cheque' => 'nullable|date',
            'pagos.*.fecha_cobro' => 'nullable|date',
            'pagos.*.banco' => 'nullable|string|max:100',
            'pagos.*.observaciones_cheque' => 'nullable|string|max:500',
            
            'observaciones' => 'nullable|string|max:1000',
            'fecha' => 'nullable|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'items.required' => 'Debe incluir al menos un producto.',
            'items.min' => 'La venta debe tener al menos un producto.',
            'items.*.producto_id.required' => 'El producto es obligatorio.',
            'items.*.producto_id.exists' => 'El producto seleccionado no existe.',
            'items.*.cantidad.required' => 'La cantidad es obligatoria.',
            'items.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'items.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
            'items.*.precio_unitario.min' => 'El precio unitario no puede ser negativo.',
            'pagos.*.metodo_pago_id.exists' => 'El método de pago no existe.',
            'pagos.*.monto.min' => 'El monto del pago no puede ser negativo.',
        ];
    }
}
