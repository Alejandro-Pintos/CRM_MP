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
        ];
    }
}
