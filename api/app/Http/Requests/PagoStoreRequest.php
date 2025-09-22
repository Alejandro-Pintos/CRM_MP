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
        ];
    }
}
