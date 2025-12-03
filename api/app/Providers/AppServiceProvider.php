<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\Empleado;
use App\Policies\VentaPolicy;
use App\Policies\ClientePolicy;
use App\Policies\ProveedorPolicy;
use App\Policies\EmpleadoPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar policies
        Gate::policy(Venta::class, VentaPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Proveedor::class, ProveedorPolicy::class);
        Gate::policy(Empleado::class, EmpleadoPolicy::class);
    }
}
