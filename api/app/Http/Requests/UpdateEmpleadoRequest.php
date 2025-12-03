<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $empleado = $this->route('empleado');
        return $this->user()->can('update', $empleado);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $empleadoId = $this->route('empleado')->id ?? $this->route('empleado');

        return [
            'nombre_completo' => ['sometimes', 'required', 'string', 'max:255'],
            'documento' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('empleados', 'documento')->ignore($empleadoId),
            ],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'puesto' => ['sometimes', 'required', 'string', 'max:100'],
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
