<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompraProveedorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_compra' => ['required', 'date'],
            'monto_total' => ['required', 'numeric', 'min:0.01'],
            'estado' => ['nullable', 'in:pendiente,pagado,anulado'],
            'metodo_pago' => ['nullable', 'in:efectivo,tarjeta,transferencia,otro'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'descuento_global' => ['nullable', 'numeric', 'min:0'],
            'impuestos_total' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            
            // Detalles de la compra (items)
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'exists:productos,id'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.unidad_medida' => ['nullable', 'string', 'max:50'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento_item' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.impuesto_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'detalles.*.impuesto_monto' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.subtotal' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'fecha_compra.required' => 'La fecha de compra es requerida.',
            'fecha_compra.date' => 'La fecha de compra debe ser una fecha válida.',
            'monto_total.required' => 'El monto total es requerido.',
            'monto_total.numeric' => 'El monto total debe ser un número.',
            'monto_total.min' => 'El monto total debe ser mayor a 0.',
            'detalles.required' => 'Debe agregar al menos un item a la compra.',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Debe agregar al menos un item a la compra.',
            'detalles.*.descripcion.required' => 'La descripción del item es requerida.',
            'detalles.*.cantidad.required' => 'La cantidad es requerida.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es requerido.',
            'detalles.*.subtotal.required' => 'El subtotal del item es requerido.',
        ];
    }
}
