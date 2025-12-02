<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PagoStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'metodo_pago_id' => ['required','integer','exists:metodos_pago,id'],
            'monto'          => ['required','numeric','gt:0'],
            'fecha_pago'     => ['nullable','date'],
            
            // Campos de cheque (opcionales)
            'numero_cheque'  => ['nullable','string','max:100'],
            'fecha_cheque'   => ['nullable','date'],
            'fecha_vencimiento' => ['nullable','date'],
            'observaciones_cheque' => ['nullable','string','max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'metodo_pago_id.required' => 'El método de pago es obligatorio',
            'metodo_pago_id.exists' => 'El método de pago seleccionado no existe',
            'monto.required' => 'El monto es obligatorio',
            'monto.gt' => 'El monto debe ser mayor a 0',
        ];
    }
}
