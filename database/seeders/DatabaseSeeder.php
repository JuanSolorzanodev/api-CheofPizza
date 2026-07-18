<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        /*
         * Datos comerciales iniciales.
         *
         * La aplicación ya no depende de estos seeders
         * para arrancar ni para funcionar técnicamente.
         */
        $this->call([
            CommerceSeeder::class,
            CatalogSeeder::class,
            PromotionSeeder::class,
            SriSeeder::class,
        ]);

        /*
         * Usuarios de desarrollo y pruebas.
         *
         * Nunca se crean automáticamente en producción.
         */
        if (
            app()->environment([
                'local',
                'testing',
            ])
        ) {
            $this->call([
                UserSeeder::class,
            ]);
        }
    }
}
