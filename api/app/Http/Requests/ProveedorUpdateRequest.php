<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Proveedor;

class ProveedorUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $routeModel = $this->route('proveedor'); // gracias al parameters(['proveedores' => 'proveedor'])
        $id = $routeModel instanceof Proveedor ? $routeModel->id : $routeModel;

        return [
            'nombre'   => ['sometimes','required','string','max:255'],
            'cuit'     => ['sometimes','required','string','max:20', Rule::unique('proveedores','cuit')->ignore($id)],
            'direccion'=> ['nullable','string','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'email'    => ['nullable','email','max:255'],
            'estado'   => ['nullable','in:activo,inactivo'],
        ];
    }
}
