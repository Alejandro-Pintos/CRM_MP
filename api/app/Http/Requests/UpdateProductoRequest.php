<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Producto;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeModel = $this->route('producto'); // model binding
        $id = $routeModel instanceof Producto ? $routeModel->id : $routeModel;

        return [
            'codigo'          => ['sometimes', 'required', 'string', 'max:50', Rule::unique('productos', 'codigo')->ignore($id)],
            'nombre'          => ['sometimes', 'required', 'string', 'max:150'],
            'descripcion'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'unidad_medida'   => ['sometimes', 'required', 'string', 'max:50'],
            'precio_compra'   => ['sometimes', 'required', 'numeric', 'min:0'],
            'precio_venta'    => ['sometimes', 'required', 'numeric', 'min:0'],
            'precio'          => ['sometimes', 'required', 'numeric', 'min:0'],
            'iva'             => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'estado'          => ['sometimes',  'required', 'in:activo,inactivo'],
            'proveedor_id' => ['nullable','integer','exists:proveedores,id'],
        ];
    }
    public function messages(): array
    {
        return [
            'codigo.unique' => 'El código ya está en uso por otro producto.',
            'estado.in'     => 'El estado debe ser "activo" o "inactivo".',
        ];
    }
}