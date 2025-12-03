<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoProveedorRequest extends FormRequest
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
            'fecha_pago' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['nullable', 'exists:metodos_pago,id'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'concepto' => ['required', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],
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
            'fecha_pago.required' => 'La fecha de pago es requerida.',
            'fecha_pago.date' => 'La fecha de pago debe ser una fecha válida.',
            'monto.required' => 'El monto es requerido.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder los 150 caracteres.',
        ];
    }
}
