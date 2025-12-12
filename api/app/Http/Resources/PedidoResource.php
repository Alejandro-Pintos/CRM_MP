<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'cliente_nombre' => $this->whenLoaded('cliente', function() {
                return $this->cliente->nombre . ' ' . $this->cliente->apellido;
            }),
            'cliente' => $this->whenLoaded('cliente', function() {
                return [
                    'id' => $this->cliente->id,
                    'nombre' => $this->cliente->nombre,
                    'apellido' => $this->cliente->apellido,
                    'ciudad' => $this->cliente->ciudad,
                ];
            }),
            'venta_id' => $this->venta_id,
            'venta' => $this->whenLoaded('venta'),
            'fecha_pedido' => $this->fecha_pedido?->format('Y-m-d H:i:s'),
            'fecha_entrega_aprox' => $this->fecha_entrega_aprox?->format('Y-m-d H:i:s'),
            'estado' => $this->estado,
            'direccion_entrega' => $this->direccion_entrega,
            'ciudad_entrega' => $this->ciudad_entrega,
            
            // Información del clima
            'clima' => [
                'estado' => $this->clima_estado,
                'temperatura' => $this->clima_temperatura,
                'humedad' => $this->clima_humedad,
                'descripcion' => $this->clima_descripcion,
                'icono' => $this->getClimaIcono(),
            ],
            
            'items' => $this->whenLoaded('items', function() {
                return $this->items->map(function($item) {
                    // Obtener datos del producto (solo para información adicional)
                    $producto = $item->producto;
                    
                    return [
                        'id' => $item->id,
                        'producto_id' => $item->producto_id,
                        'producto_nombre' => $producto->nombre ?? null,
                        'producto_codigo' => $producto->codigo ?? null,
                        'cantidad' => (float) $item->cantidad,
                        
                        // IMPORTANTE: Usar datos PERSISTIDOS del detalle_pedido (no recalcular desde producto)
                        'precio_compra' => (float) $item->precio_compra,
                        'precio_venta' => (float) $item->precio_venta,
                        'porcentaje_iva' => (float) $item->porcentaje_iva,
                        'porcentaje_extra' => (float) $item->porcentaje_extra,
                        'precio_unitario' => (float) $item->precio_unitario,
                        
                        // Subtotal calculado con datos persistidos
                        'subtotal' => (float) $item->subtotal,
                        'observaciones' => $item->observaciones,
                    ];
                });
            }),
            
            'total' => $this->whenLoaded('items', function() {
                return $this->items->sum(function($item) {
                    // Calcular total con datos persistidos del detalle
                    return (float) $item->subtotal;
                });
            }),
            
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getClimaIcono()
    {
        $estado = strtolower($this->clima_estado ?? '');
        
        if (str_contains($estado, 'sol') || str_contains($estado, 'clear')) return '☀️';
        if (str_contains($estado, 'nubl') || str_contains($estado, 'cloud')) return '☁️';
        if (str_contains($estado, 'lluv') || str_contains($estado, 'rain')) return '🌧️';
        if (str_contains($estado, 'torment') || str_contains($estado, 'storm')) return '⛈️';
        if (str_contains($estado, 'nieve') || str_contains($estado, 'snow')) return '❄️';
        
        return '🌤️';
    }
}
