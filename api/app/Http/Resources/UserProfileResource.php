<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para el perfil del usuario autenticado
 */
class UserProfileResource extends JsonResource
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
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Roles (Spatie Permission)
            'roles' => $this->when(
                method_exists($this->resource, 'getRoleNames'),
                fn() => $this->getRoleNames()->toArray()
            ),
            
            // Permisos (Spatie Permission)
            'permissions' => $this->when(
                method_exists($this->resource, 'getAllPermissions'),
                fn() => $this->getAllPermissions()->pluck('name')->toArray()
            ),
        ];
    }
}
