<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => 'required|string|max:50|unique:productos,codigo',
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string|max:255',
            'unidad_medida' => 'required|string|max:50',
            'precio' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0|max:100',
            'estado' => 'required|in:activo,inactivo',
            'proveedor_id' => ['nullable','integer','exists:proveedores,id'],
        ];
    }
}
