<?php

namespace Database\Seeders;

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
            'operator',
        ];

        foreach ($roles as $roleName) {
            Role::updateOrCreate(
                ['role_name' => $roleName],
                []
            );
        }
    }
}
