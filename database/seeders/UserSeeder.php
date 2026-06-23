<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole    = Role::where('role_name', 'admin')->firstOrFail();
        $customerRole = Role::where('role_name', 'customer')->firstOrFail();
        $operatorRole = Role::where('role_name', 'operator')->firstOrFail();

        // Admin (para panel / pruebas)
        User::firstOrCreate(
            ['email' => 'admin@cheofpizza.test'],
            [
                'role_id' => $adminRole->id,
                'first_name' => 'Admin',
                'last_name' => 'CheofPizza',
                'phone' => '0999999999',
                'password' => Hash::make('Admin123456!'),
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
                'password' => Hash::make('Cliente123456!'),
            ]
        );

        // Operativo con Gmail (entra por Google, pero el rol ya viene en BD)
        User::firstOrCreate(
            ['email' => 'operationalemail@gmail.com'],
            [
                'role_id' => $operatorRole->id,
                'first_name' => 'Operativo',
                'last_name' => 'CheofPizza',
                'phone' => '0980350189',
                // password fuerte por si luego habilitas login tradicional
                'password' => Hash::make('Operador99@'),
            ]
        );
    }
}
