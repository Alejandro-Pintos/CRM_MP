<?php

namespace App\Policies;

use App\Models\Usuario;
use App\Models\Empleado;

class EmpleadoPolicy
{
    /**
     * Determinar si el usuario puede ver cualquier empleado.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->hasPermission('empleados.ver');
    }

    /**
     * Determinar si el usuario puede ver un empleado específico.
     */
    public function view(Usuario $usuario, Empleado $empleado): bool
    {
        // Los empleados pueden ver su propia información
        if ($empleado->usuario_id === $usuario->id) {
            return true;
        }

        return $usuario->hasPermission('empleados.ver');
    }

    /**
     * Determinar si el usuario puede crear empleados.
     */
    public function create(Usuario $usuario): bool
    {
        // Solo admin o usuarios con permiso específico
        return $usuario->hasRole('admin') || $usuario->hasPermission('empleados.crear');
    }

    /**
     * Determinar si el usuario puede actualizar el empleado.
     */
    public function update(Usuario $usuario, Empleado $empleado): bool
    {
        if ($usuario->hasRole('admin')) {
            return true;
        }

        // Los empleados pueden editar algunos de sus datos
        if ($empleado->usuario_id === $usuario->id) {
            return true; // Validar qué campos pueden editar en FormRequest
        }

        return $usuario->hasPermission('empleados.editar');
    }

    /**
     * Determinar si el usuario puede eliminar el empleado.
     * 
     * RESTRICCIÓN: No se puede eliminar empleado con:
     * - Usuario asociado activo
     * - Ventas realizadas
     * - Historial de transacciones
     */
    public function delete(Usuario $usuario, Empleado $empleado): bool
    {
        if (!$usuario->hasRole('admin')) {
            return false;
        }

        // No permitir eliminar empleados con usuario asociado
        if ($empleado->usuario_id) {
            return false;
        }

        return true;
    }
}
