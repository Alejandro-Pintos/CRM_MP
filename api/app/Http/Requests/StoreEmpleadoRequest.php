<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmpleadoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Empleado::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nombre_completo' => ['required', 'string', 'max:255'],
            'documento' => ['required', 'string', 'max:50', 'unique:empleados,documento'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'puesto' => ['required', 'string', 'max:100'],
            'notas' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre_completo.required' => 'El nombre completo es obligatorio.',
            'documento.required' => 'El documento es obligatorio.',
            'documento.unique' => 'Ya existe un empleado con este documento.',
            'puesto.required' => 'El puesto es obligatorio.',
            'email.email' => 'El email debe ser una dirección válida.',
        ];
    }
}
