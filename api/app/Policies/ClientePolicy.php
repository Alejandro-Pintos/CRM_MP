<?php

namespace App\Policies;

use App\Models\Usuario;
use App\Models\Cliente;

class ClientePolicy
{
    /**
     * Determinar si el usuario puede ver cualquier cliente.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->hasPermission('clientes.index');
    }

    /**
     * Determinar si el usuario puede ver un cliente específico.
     */
    public function view(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->hasPermission('clientes.index');
    }

    /**
     * Determinar si el usuario puede crear clientes.
     */
    public function create(Usuario $usuario): bool
    {
        return $usuario->hasPermission('clientes.store');
    }

    /**
     * Determinar si el usuario puede actualizar el cliente.
     */
    public function update(Usuario $usuario, Cliente $cliente): bool
    {
        // Admin puede editar cualquier cliente
        if ($usuario->hasRole('admin')) {
            return true;
        }

        // Usuarios regulares solo si tienen permiso
        return $usuario->hasPermission('clientes.update');
    }

    /**
     * Determinar si el usuario puede eliminar el cliente.
     * 
     * RESTRICCIÓN: No se puede eliminar cliente con:
     * - Ventas asociadas
     * - Movimientos de cuenta corriente
     * - Cheques registrados
     */
    public function delete(Usuario $usuario, Cliente $cliente): bool
    {
        // Solo admin puede eliminar clientes
        if (!$usuario->hasRole('admin')) {
            return false;
        }

        // Protección: no eliminar clientes con historial financiero
        if ($cliente->ventas()->exists()) {
            return false;
        }

        if ($cliente->movimientosCuentaCorriente()->exists()) {
            return false;
        }

        if ($cliente->cheques()->exists()) {
            return false;
        }

        return true;
    }
}
