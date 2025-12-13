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
            
            // Campos de cheque (condicionales si metodo_pago_id == 4)
            'banco_cheque' => ['required_if:metodo_pago_id,4', 'string', 'max:100'],
            'numero_cheque' => ['required_if:metodo_pago_id,4', 'string', 'max:50'],
            'fecha_emision_cheque' => ['required_if:metodo_pago_id,4', 'date'],
            'fecha_vencimiento_cheque' => ['nullable', 'date', 'after_or_equal:fecha_emision_cheque'],
            'observaciones_cheque' => ['nullable', 'string'],
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
            'banco_cheque.required_if' => 'El banco del cheque es requerido cuando el método de pago es Cheque.',
            'numero_cheque.required_if' => 'El número de cheque es requerido cuando el método de pago es Cheque.',
            'fecha_emision_cheque.required_if' => 'La fecha de emisión del cheque es requerida.',
            'fecha_vencimiento_cheque.after_or_equal' => 'La fecha de vencimiento debe ser posterior o igual a la fecha de emisión.',
        ];
    }
}
