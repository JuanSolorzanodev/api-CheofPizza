<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_settings',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Información del negocio
                |--------------------------------------------------------------------------
                */

                $table
                    ->string('business_name', 150)
                    ->default("CHEO' PIZZA");

                $table
                    ->string('phone', 30)
                    ->nullable();

                $table
                    ->string('email')
                    ->nullable();

                $table
                    ->string('address', 500)
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Estado de la tienda
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean('accepts_orders')
                    ->default(true)
                    ->index();

                $table
                    ->string('closed_message', 500)
                    ->nullable();

                $table
                    ->unsignedSmallInteger('estimated_minutes')
                    ->default(35);

                $table
                    ->char('currency', 3)
                    ->default('USD');

                $table
                    ->string('timezone', 80)
                    ->default('America/Guayaquil');

                /*
                |--------------------------------------------------------------------------
                | Entrega
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean('pickup_enabled')
                    ->default(true);

                $table
                    ->boolean('delivery_enabled')
                    ->default(true);

                $table
                    ->decimal('delivery_fee', 10, 2)
                    ->default(0);

                $table
                    ->decimal('minimum_order', 10, 2)
                    ->default(0);

                /*
                |--------------------------------------------------------------------------
                | Métodos de pago
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean('paypal_enabled')
                    ->default(true);

                $table
                    ->boolean('transfer_enabled')
                    ->default(true);

                $table
                    ->boolean('cash_enabled')
                    ->default(true);

                $table->timestamps();
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_settings',
        );
    }
};
