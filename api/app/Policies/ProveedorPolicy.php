<?php

namespace App\Policies;

use App\Models\Usuario;
use App\Models\Proveedor;

class ProveedorPolicy
{
    /**
     * Determinar si el usuario puede ver cualquier proveedor.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->hasPermission('proveedores.ver');
    }

    /**
     * Determinar si el usuario puede ver un proveedor específico.
     */
    public function view(Usuario $usuario, Proveedor $proveedor): bool
    {
        return $usuario->hasPermission('proveedores.ver');
    }

    /**
     * Determinar si el usuario puede crear proveedores.
     */
    public function create(Usuario $usuario): bool
    {
        return $usuario->hasPermission('proveedores.crear');
    }

    /**
     * Determinar si el usuario puede actualizar el proveedor.
     */
    public function update(Usuario $usuario, Proveedor $proveedor): bool
    {
        if ($usuario->hasRole('admin')) {
            return true;
        }

        return $usuario->hasPermission('proveedores.editar');
    }

    /**
     * Determinar si el usuario puede eliminar el proveedor.
     * 
     * RESTRICCIÓN: No se puede eliminar proveedor con:
     * - Compras asociadas
     * - Productos asociados
     */
    public function delete(Usuario $usuario, Proveedor $proveedor): bool
    {
        if (!$usuario->hasRole('admin')) {
            return false;
        }

        // Verificar si tiene relaciones
        // TODO: Agregar validación de compras cuando el módulo esté implementado
        
        return true;
    }
}
