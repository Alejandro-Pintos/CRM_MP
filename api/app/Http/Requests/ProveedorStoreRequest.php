<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Proveedor::class);
    }

    public function rules(): array
    {
        return [
            'nombre'   => ['required','string','max:255'],
            'cuit'     => ['required','string','max:20', Rule::unique('proveedores','cuit')],
            'direccion'=> ['nullable','string','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'email'    => ['nullable','email','max:255'],
            'estado'   => ['nullable','in:activo,inactivo'],
        ];
    }
}
