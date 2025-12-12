<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Usuario;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CleanDatabaseSeeder extends Seeder
{
    /**
     * Limpia TODA la base de datos excepto:
     * - Sistema de permisos (roles, permissions, model_has_permissions, etc.)
     * - Usuario administrador
     * - Métodos de pago por defecto
     */
    public function run(): void
    {
        $this->command->warn('🧹 LIMPIANDO BASE DE DATOS...');
        $this->command->warn('⚠️  Esto eliminará TODOS los datos excepto admin y permisos');
        
        // Desactivar foreign key checks temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        try {
            // 1. Limpiar datos de negocio (en orden por dependencias)
            $this->cleanBusinessData();
            
            // 2. Recrear métodos de pago por defecto
            $this->seedMetodosPago();
            
            // 3. Asegurar que existe admin con todos los permisos
            $this->ensureAdminExists();
            
        } finally {
            // Reactivar foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
        
        $this->command->info('✅ Base de datos limpiada exitosamente');
        $this->command->info('📊 Estado final:');
        $this->command->info("   - Usuarios: " . Usuario::count());
        $this->command->info("   - Roles: " . Role::count());
        $this->command->info("   - Permisos: " . Permission::count());
        $this->command->info("   - Métodos de pago: " . DB::table('metodos_pago')->count());
    }
    
    private function cleanBusinessData(): void
    {
        $tables = [
            // Nivel 4: Detalles de transacciones
            'detalle_venta',
            'detalle_pedido',
            'compra_detalles',
            
            // Nivel 3: Transacciones financieras
            'pagos',
            'pagos_empleados',
            'pagos_proveedores',
            'cheques',
            'movimientos_cuenta_corriente',
            
            // Nivel 2: Documentos principales
            'ventas',
            'pedidos',
            'compras',
            'comprobantes_numeracion',
            
            // Nivel 1: Entidades base (solo datos, no usuarios/empleados)
            'clientes',
            'productos',
            'proveedores',
        ];
        
        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            if ($count > 0) {
                DB::table($table)->truncate();
                $this->command->info("   ✓ Limpiada tabla: {$table} ({$count} registros eliminados)");
            }
        }
        
        // Limpiar empleados (no tienen relación directa con usuarios)
        $empleadosCount = DB::table('empleados')->count();
        if ($empleadosCount > 0) {
            DB::table('empleados')->truncate();
            $this->command->info("   ✓ Limpiada tabla: empleados ({$empleadosCount} registros eliminados)");
        }
        
        // Limpiar usuarios excepto admin
        $usuariosCount = Usuario::whereDoesntHave('roles', function($q) {
            $q->where('name', 'admin');
        })->count();
        
        if ($usuariosCount > 0) {
            Usuario::whereDoesntHave('roles', function($q) {
                $q->where('name', 'admin');
            })->delete();
            $this->command->info("   ✓ Limpiados usuarios no-admin ({$usuariosCount} registros eliminados)");
        }
    }
    
    private function seedMetodosPago(): void
    {
        // Limpiar métodos de pago existentes
        DB::table('metodos_pago')->truncate();
        
        $metodos = [
            ['id' => 1, 'nombre' => 'Efectivo', 'descripcion' => 'Pago en efectivo'],
            ['id' => 2, 'nombre' => 'Transferencia', 'descripcion' => 'Transferencia bancaria'],
            ['id' => 3, 'nombre' => 'Cuenta Corriente', 'descripcion' => 'Pago a crédito'],
            ['id' => 4, 'nombre' => 'Cheque', 'descripcion' => 'Pago con cheque'],
        ];
        
        foreach ($metodos as $metodo) {
            DB::table('metodos_pago')->insert(array_merge($metodo, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        
        $this->command->info('   ✓ Métodos de pago recreados (4 métodos)');
    }
    
    private function ensureAdminExists(): void
    {
        // Verificar que existe el rol admin
        $adminRole = Role::where('name', 'admin')
            ->where('guard_name', 'api')
            ->first();
            
        if (!$adminRole) {
            $this->command->error('❌ El rol admin no existe. Ejecuta primero:');
            $this->command->error('   php artisan db:seed --class=FixMissingPermissionsSeeder');
            return;
        }
        
        // Asegurar que admin tiene TODOS los permisos
        $allPermissions = Permission::where('guard_name', 'api')->get();
        $adminRole->syncPermissions($allPermissions);
        
        // Verificar que existe al menos un usuario admin
        $adminUser = Usuario::role('admin')->first();
        
        if (!$adminUser) {
            // Crear usuario admin por defecto
            $adminUser = Usuario::create([
                'nombre' => 'Administrador',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'estado' => 'activo',
            ]);
            
            $adminUser->assignRole('admin');
            
            $this->command->info('   ✓ Usuario admin creado:');
            $this->command->info('     Email: admin@example.com');
            $this->command->info('     Password: password');
            $this->command->warn('     ⚠️  CAMBIAR CONTRASEÑA EN PRODUCCIÓN');
        } else {
            $this->command->info('   ✓ Usuario admin existente: ' . $adminUser->email);
        }
        
        // Verificar permisos del admin
        $permCount = $adminUser->getAllPermissions()->count();
        $this->command->info("   ✓ Admin tiene {$permCount} permisos asignados");
    }
}
