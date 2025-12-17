<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Company;
use App\Models\Customer;
use App\Models\EmissionType;
use App\Models\EnvironmentType;
use App\Models\IdentificationType;
use Illuminate\Database\Seeder;

class SriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    
        // Environment types (SRI)
        $testEnv = EnvironmentType::updateOrCreate(
            ['code' => 1],
            ['environment_name' => 'PRUEBAS']
        );

        $prodEnv = EnvironmentType::updateOrCreate(
            ['code' => 2],
            ['environment_name' => 'PRODUCCION']
        );

        // Emission types (SRI)
        $normalEmission = EmissionType::updateOrCreate(
            ['code' => '1'],
            ['emission_name' => 'NORMAL']
        );

        // Identification types (SRI comunes)
        $cedula = IdentificationType::updateOrCreate(
            ['code' => '05'],
            ['identification_name' => 'CEDULA']
        );

        $ruc = IdentificationType::updateOrCreate(
            ['code' => '04'],
            ['identification_name' => 'RUC']
        );

        $passport = IdentificationType::updateOrCreate(
            ['code' => '06'],
            ['identification_name' => 'PASAPORTE']
        );

        $finalConsumer = IdentificationType::updateOrCreate(
            ['code' => '07'],
            ['identification_name' => 'CONSUMIDOR_FINAL']
        );

        // Company (mínima para avanzar en dev)
        Company::firstOrCreate(
            ['ruc' => '0999999999001'],
            [
                'business_name' => 'CHEOF PIZZA DEMO',
                'headquarters_address' => 'Manta, Ecuador',
                'establishment_code' => '001',
                'emission_point_code' => '001',
                'special_taxpayer' => null,
                'accounting_required' => false,
                'logo_path' => null,
                'environment_type_id' => $testEnv->id,        // PRUEBAS por defecto
                'emission_type_id' => $normalEmission->id,
                'signature_path' => 'signatures/demo.p12',
                'signature_password' => 'demo123',
            ]
        );

        // Cliente "Consumidor Final"
        Customer::firstOrCreate(
            ['identification' => '9999999999999'],
            [
                'identification_type_id' => $finalConsumer->id,
                'business_name' => 'CONSUMIDOR FINAL',
                'email' => null,
            ]
        );
    }
}
