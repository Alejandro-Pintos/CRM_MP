<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoEmpleadoRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'fecha_pago' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['nullable', 'exists:metodos_pago,id'],
            'concepto' => ['required', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha_pago.required' => 'La fecha de pago es obligatoria.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.min' => 'El monto debe ser mayor a cero.',
            'concepto.required' => 'El concepto es obligatorio.',
            'metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
        ];
    }
}
