<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n🧪 Creando proveedores de prueba...\n\n";

$p1 = App\Models\Proveedor::create([
    'nombre' => 'Proveedor Test 1',
    'cuit' => '20-12345678-1',
    'email' => 'test1@proveedor.com',
    'telefono' => '111-222-3333',
    'direccion' => 'Calle Falsa 123',
    'estado' => 'activo'
]);

echo "✓ Proveedor 1 creado: ID {$p1->id} - {$p1->nombre}\n";

$p2 = App\Models\Proveedor::create([
    'nombre' => 'Proveedor Test 2',
    'cuit' => '20-98765432-1',
    'email' => 'test2@proveedor.com',
    'telefono' => '444-555-6666',
    'direccion' => 'Avenida Siempreviva 742',
    'estado' => 'activo'
]);

echo "✓ Proveedor 2 creado: ID {$p2->id} - {$p2->nombre}\n";

$total = App\Models\Proveedor::count();
echo "\n📊 Total proveedores en el sistema: {$total}\n\n";
