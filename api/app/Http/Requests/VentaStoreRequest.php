<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VentaStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required','integer','exists:clientes,id'],
            'fecha' => ['nullable','date'],
            'tipo_comprobante' => ['nullable','string','max:50'],
            'numero_comprobante' => ['nullable','string','max:50'],
            'pedido_id' => ['nullable','integer','exists:pedidos,id'],

            'items' => ['required','array','min:1'],
            'items.*.producto_id' => ['required','integer','exists:productos,id'],
            'items.*.cantidad' => ['required','numeric','gt:0'],
            'items.*.precio_unitario' => ['required','numeric','gte:0'],
            'items.*.iva' => ['nullable','numeric','gte:0'], // % IVA

            'pagos' => ['nullable','array'],
            'pagos.*.metodo_pago_id' => ['required','integer','exists:metodos_pago,id'],
            'pagos.*.monto' => ['required','numeric','gt:0'],
            'pagos.*.fecha_pago' => ['nullable','date'],
            
            // Campos específicos para cheques
            'pagos.*.numero_cheque' => ['nullable','string','max:50'],
            'pagos.*.fecha_cheque' => ['nullable','date'],
            'pagos.*.fecha_cobro' => ['nullable','date'], // BUG 2: Agregar validación de fecha_cobro
            'pagos.*.fecha_vencimiento' => ['nullable','date'],
            'pagos.*.observaciones_cheque' => ['nullable','string','max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'El cliente es obligatorio',
            'cliente_id.exists' => 'El cliente seleccionado no existe',
            'items.required' => 'Debe agregar al menos un producto',
            'items.min' => 'Debe agregar al menos un producto',
            'items.*.producto_id.required' => 'El producto es obligatorio',
            'items.*.producto_id.exists' => 'El producto seleccionado no existe',
            'items.*.cantidad.required' => 'La cantidad es obligatoria',
            'items.*.cantidad.gt' => 'La cantidad debe ser mayor a 0',
            'items.*.precio_unitario.required' => 'El precio unitario es obligatorio',
            'items.*.precio_unitario.gte' => 'El precio unitario debe ser mayor o igual a 0',
            'pagos.*.metodo_pago_id.required' => 'El método de pago es obligatorio',
            'pagos.*.metodo_pago_id.exists' => 'El método de pago seleccionado no existe',
            'pagos.*.monto.required' => 'El monto del pago es obligatorio',
            'pagos.*.monto.gt' => 'El monto del pago debe ser mayor a 0',
        ];
    }
}
