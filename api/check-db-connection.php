<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

echo "=== INFORMACIÓN DE CONEXIÓN A LA BASE DE DATOS ===\n\n";

// Configuración de la base de datos
$connection = Config::get('database.default');
$database = Config::get("database.connections.{$connection}.database");
$driver = Config::get("database.connections.{$connection}.driver");

echo "Driver: {$driver}\n";
echo "Conexión: {$connection}\n";

if ($driver === 'sqlite') {
    echo "Base de datos: {$database}\n";
    echo "Ruta completa: " . realpath($database) . "\n\n";
    
    // Verificar si el archivo existe
    if (file_exists($database)) {
        echo "✓ El archivo de base de datos existe\n";
        echo "Tamaño: " . round(filesize($database) / 1024 / 1024, 2) . " MB\n";
        echo "Última modificación: " . date('Y-m-d H:i:s', filemtime($database)) . "\n\n";
    } else {
        echo "✗ El archivo de base de datos NO existe\n\n";
    }
} else {
    $host = Config::get("database.connections.{$connection}.host");
    $port = Config::get("database.connections.{$connection}.port");
    $username = Config::get("database.connections.{$connection}.username");
    
    echo "Host: {$host}\n";
    echo "Puerto: {$port}\n";
    echo "Base de datos: {$database}\n";
    echo "Usuario: {$username}\n\n";
}

// Probar la conexión
try {
    DB::connection()->getPdo();
    echo "✓ Conexión exitosa a la base de datos\n\n";
    
    // Información de las tablas
    echo "=== TABLAS EN LA BASE DE DATOS ===\n";
    
    if ($driver === 'sqlite') {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        foreach ($tables as $table) {
            if ($table->name !== 'sqlite_sequence') {
                $count = DB::table($table->name)->count();
                echo "- {$table->name}: {$count} registros\n";
            }
        }
    } else {
        // Para MySQL
        $tables = DB::select("SHOW TABLES");
        $tableKey = "Tables_in_{$database}";
        foreach ($tables as $table) {
            $tableName = $table->$tableKey;
            $count = DB::table($tableName)->count();
            echo "- {$tableName}: {$count} registros\n";
        }
    }
    
    echo "\n=== CHEQUES PENDIENTES ===\n";
    $cheques = DB::table('pagos')
        ->where('estado_cheque', 'pendiente')
        ->get(['id', 'venta_id', 'numero_cheque', 'fecha_cheque', 'fecha_cobro', 'monto']);
    
    if ($cheques->count() > 0) {
        foreach ($cheques as $cheque) {
            echo "\nCheque ID: {$cheque->id}\n";
            echo "  Venta: #{$cheque->venta_id}\n";
            echo "  Número: " . ($cheque->numero_cheque ?? 'NULL') . "\n";
            echo "  Fecha Cheque: " . ($cheque->fecha_cheque ?? 'NULL') . "\n";
            echo "  Fecha Cobro: " . ($cheque->fecha_cobro ?? 'NULL') . "\n";
            echo "  Monto: $" . number_format($cheque->monto, 2) . "\n";
        }
    } else {
        echo "No hay cheques pendientes\n";
    }
    
} catch (\Exception $e) {
    echo "✗ Error al conectar: " . $e->getMessage() . "\n";
}
