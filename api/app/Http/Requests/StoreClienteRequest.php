<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Cliente::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:150',
            'apellido' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150|unique:clientes,email',
            'telefono' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:150',
            'provincia' => 'nullable|string|max:150',
            'cuit_cuil' => 'nullable|string|max:50|unique:clientes,cuit_cuil',
            'limite_credito' => 'nullable|numeric|min:0',
        ];
    }
}
