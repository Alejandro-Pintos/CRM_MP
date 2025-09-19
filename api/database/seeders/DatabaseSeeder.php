<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario; // Cambia el import

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Usuario::factory(10)->create();

        Usuario::factory()->create([  // Cambia User por Usuario
            'nombre' => 'Test User',  // Cambia name por nombre
            'email' => 'test@example.com',
        ]);
    }
}
