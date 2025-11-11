<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePedidoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'fecha_pedido' => ['nullable', 'date'],
            'fecha_entrega_aprox' => ['nullable', 'date', 'after:fecha_pedido'],
            'fecha_despacho' => ['nullable', 'date'],
            'estado' => ['nullable', 'in:pendiente,en_proceso,entregado,cancelado'],
            'direccion_entrega' => ['nullable', 'string', 'max:255'],
            'ciudad_entrega' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],
            
            // Clima (opcional, se puede obtener automáticamente)
            'clima_estado' => ['nullable', 'string', 'max:100'],
            'clima_temperatura' => ['nullable', 'numeric'],
            'clima_humedad' => ['nullable', 'integer', 'min:0', 'max:100'],
            'clima_descripcion' => ['nullable', 'string'],
            'clima_json' => ['nullable', 'string'],
            'pronostico_extendido' => ['nullable', 'string'], // JSON con pronóstico extendido
            
            // Items del pedido
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'gte:0'],
            'items.*.observaciones' => ['nullable', 'string'],
        ];
    }
}
