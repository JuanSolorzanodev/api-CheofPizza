<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $roles = [
            'admin',
            'customer',
        ];

        foreach ($roles as $roleName) {
            Role::updateOrCreate(
                ['role_name' => $roleName],
                []
            );
        }
    }
}
