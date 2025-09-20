<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        Usuario::updateOrCreate(
            ['email' => 'admin@crm.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
            ]
        );
    }
}
