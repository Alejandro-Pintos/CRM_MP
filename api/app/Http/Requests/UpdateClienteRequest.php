<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('cliente')->id; // Ignorar validación de único en el mismo cliente

        return [
            'nombre' => 'required|string|max:150',
            'cuit_dni' => "nullable|string|max:50|unique:clientes,cuit_dni,$id",
            'telefono' => 'nullable|string|max:50',
            'email' => "nullable|email|max:150|unique:clientes,email,$id",
            'direccion' => 'nullable|string|max:255',
            'limite_credito' => 'nullable|numeric|min:0',
        ];
    }
}
