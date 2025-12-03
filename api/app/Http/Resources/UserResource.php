<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'nombre' => $this->nombre,
            'email' => $this->email,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Roles del usuario (siempre incluidos para ABM)
            'roles' => $this->getRoleNames()->toArray(),
            
            // Permisos solo si se solicitan
            'permissions' => $this->when(
                $request->get('include_permissions'),
                fn() => $this->getAllPermissions()->pluck('name')->toArray()
            ),
        ];
    }
}
