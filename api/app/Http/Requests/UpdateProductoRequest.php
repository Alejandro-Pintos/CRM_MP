<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'unidad_medida' => 'required|string|max:50',
            'precio_unitario' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0|max:100',
            'estado' => 'in:activo,inactivo',
        ];
    }
}
