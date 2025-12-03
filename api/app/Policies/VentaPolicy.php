<?php

namespace App\Policies;

use App\Models\Venta;
use App\Models\Usuario;

class VentaPolicy
{
    /**
     * Solo el creador o un admin puede eliminar una venta
     */
    public function delete(Usuario $usuario, Venta $venta): bool
    {
        // Admin puede eliminar cualquier venta
        if ($usuario->hasRole('admin')) {
            return true;
        }

        // Solo el vendedor que creó la venta puede eliminarla
        // Y solo si no tiene movimientos en cuenta corriente
        if ($venta->usuario_id !== $usuario->id) {
            return false;
        }

        // Validar que no tenga movimientos en CC (para evitar eliminar ventas que ya impactaron finanzas)
        return $venta->movimientosCuentaCorriente()->count() === 0;
    }

    /**
     * Solo se puede editar una venta si:
     * 1. Es el creador o un admin
     * 2. No tiene movimientos en cuenta corriente (no impactó finanzas)
     */
    public function update(Usuario $usuario, Venta $venta): bool
    {
        // Admin puede editar cualquier venta
        if ($usuario->hasRole('admin')) {
            return true;
        }

        // Vendedor puede editar solo si:
        // 1. Es su venta
        // 2. No tiene movimientos en CC (no impactó cuenta corriente)
        if ($venta->usuario_id !== $usuario->id) {
            return false;
        }

        return $venta->movimientosCuentaCorriente()->count() === 0;
    }

    /**
     * Cualquier usuario con permiso puede ver ventas
     */
    public function view(Usuario $usuario, Venta $venta): bool
    {
        return $usuario->can('ventas.index');
    }

    /**
     * Cualquier usuario con permiso puede listar ventas
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('ventas.index');
    }

    /**
     * Solo usuarios con permiso pueden crear ventas
     */
    public function create(Usuario $usuario): bool
    {
        return $usuario->can('ventas.store');
    }
}
