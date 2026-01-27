<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Coordenadas del punto exacto seleccionado por el cliente
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('address');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');

            // Link directo a Maps (para copiar/compartir por WhatsApp)
            $table->string('delivery_maps_url', 500)->nullable()->after('delivery_lng');

            // Opcionales (si luego usas Google Places o guardas referencia)
            $table->string('delivery_place_id', 255)->nullable()->after('delivery_maps_url');
            $table->string('delivery_reference', 255)->nullable()->after('delivery_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_lat',
                'delivery_lng',
                'delivery_maps_url',
                'delivery_place_id',
                'delivery_reference',
            ]);
        });
    }
};
