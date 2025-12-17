<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $adminRole = Role::where('role_name', 'admin')->firstOrFail();
        $customerRole = Role::where('role_name', 'customer')->firstOrFail();

        // Admin (para panel / pruebas)
        User::firstOrCreate(
            ['email' => 'admin@cheofpizza.test'],
            [
                'role_id' => $adminRole->id,
                'first_name' => 'Admin',
                'last_name' => 'CheofPizza',
                'phone' => '0999999999',
                'password' => 'Admin123456!', // Se hashea por cast "hashed" en tu modelo
            ]
        );

        // Cliente (para pruebas de carrito/checkout)
        User::firstOrCreate(
            ['email' => 'cliente@cheofpizza.test'],
            [
                'role_id' => $customerRole->id,
                'first_name' => 'Cliente',
                'last_name' => 'Demo',
                'phone' => '0988888888',
                'password' => 'Cliente123456!',
            ]
        );
    }
}
