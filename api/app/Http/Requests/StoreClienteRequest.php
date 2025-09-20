<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permitir a cualquier usuario autenticado (los permisos ya se manejan con Spatie)
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:150',
            'cuit_dni' => 'nullable|string|max:50|unique:clientes,cuit_dni',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150|unique:clientes,email',
            'direccion' => 'nullable|string|max:255',
            'limite_credito' => 'nullable|numeric|min:0',
        ];
    }
}
