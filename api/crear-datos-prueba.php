<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;

echo "=== CREANDO DATOS DE PRUEBA ===\n\n";

try {
    DB::beginTransaction();
    
    // 1. Crear proveedores
    echo "📦 Creando proveedores...\n";
    $prov1 = Proveedor::create([
        'nombre' => 'Maderera del Norte',
        'contacto' => 'Juan Pérez',
        'telefono' => '3456-123456',
        'email' => 'ventas@madereradelnorte.com',
        'direccion' => 'Ruta 11 Km 45',
        'cuit' => '20-12345678-9',
    ]);
    echo "  ✅ {$prov1->nombre}\n";
    
    // 2. Crear productos
    echo "\n📦 Creando productos...\n";
    $productos = [
        ['nombre' => 'Poste de quebracho 2.40m', 'precio' => 70180.00, 'stock' => 50],
        ['nombre' => 'Poste de quebracho 3.00m', 'precio' => 95500.00, 'stock' => 30],
        ['nombre' => 'Varilla de hierro 8mm', 'precio' => 12500.00, 'stock' => 100],
        ['nombre' => 'Varilla de hierro 10mm', 'precio' => 18900.00, 'stock' => 80],
        ['nombre' => 'Alambre calibre 17', 'precio' => 45000.00, 'stock' => 25],
        ['nombre' => 'Grapa para alambre x100', 'precio' => 3500.00, 'stock' => 200],
        ['nombre' => 'Torniquete p/alambrado', 'precio' => 8900.00, 'stock' => 40],
    ];
    
    $productosCreados = [];
    foreach ($productos as $index => $prod) {
        $producto = Producto::create([
            'codigo' => 'PROD-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
            'nombre' => $prod['nombre'],
            'descripcion' => 'Material para alambrado rural',
            'precio' => $prod['precio'],
            'stock_actual' => $prod['stock'],
            'stock_minimo' => 10,
            'proveedor_id' => $prov1->id,
            'estado' => 'activo',
        ]);
        $productosCreados[] = $producto;
        echo "  ✅ {$producto->nombre} - \${$producto->precio}\n";
    }
    
    // 3. Crear clientes
    echo "\n👥 Creando clientes...\n";
    $clientes = [
        [
            'nombre' => 'María',
            'apellido' => 'González',
            'email' => 'maria.gonzalez@email.com',
            'telefono' => '3456-111222',
            'direccion' => 'Estancia La Esperanza',
            'ciudad' => 'Resistencia',
            'provincia' => 'Chaco',
            'cuit_cuil' => '27-12345678-9',
            'limite_credito' => 5000000.00,
        ],
        [
            'nombre' => 'Carlos',
            'apellido' => 'Fernández',
            'email' => 'carlos.fernandez@email.com',
            'telefono' => '3456-333444',
            'direccion' => 'Campo Los Alamos Km 12',
            'ciudad' => 'Presidencia Roque Sáenz Peña',
            'provincia' => 'Chaco',
            'cuit_cuil' => '20-98765432-1',
            'limite_credito' => 3000000.00,
        ],
        [
            'nombre' => 'Ana',
            'apellido' => 'Martínez',
            'email' => 'ana.martinez@email.com',
            'telefono' => '3456-555666',
            'direccion' => 'Ruta 16 Km 89',
            'ciudad' => 'Castelli',
            'provincia' => 'Chaco',
            'cuit_cuil' => '27-55555555-5',
            'limite_credito' => 2000000.00,
        ],
        [
            'nombre' => 'Roberto',
            'apellido' => 'López',
            'email' => 'roberto.lopez@email.com',
            'telefono' => '3456-777888',
            'direccion' => 'Av. Principal 456',
            'ciudad' => 'Villa Ángela',
            'provincia' => 'Chaco',
            'cuit_cuil' => '20-44444444-4',
            'limite_credito' => 0, // Sin crédito, paga al contado
        ],
    ];
    
    foreach ($clientes as $cli) {
        $cliente = Cliente::create($cli);
        echo "  ✅ {$cliente->nombre} {$cliente->apellido}";
        if ($cliente->limite_credito > 0) {
            echo " (Crédito: \$" . number_format($cliente->limite_credito, 2) . ")";
        } else {
            echo " (Sin crédito - Al contado)";
        }
        echo "\n";
    }
    
    DB::commit();
    
    echo "\n✅ DATOS DE PRUEBA CREADOS EXITOSAMENTE\n";
    echo "\n📊 RESUMEN:\n";
    echo "  • Proveedores: 1\n";
    echo "  • Productos: " . count($productos) . "\n";
    echo "  • Clientes: " . count($clientes) . "\n";
    echo "\n🎉 El sistema está listo para probar ventas con cheques\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
    exit(1);
}
