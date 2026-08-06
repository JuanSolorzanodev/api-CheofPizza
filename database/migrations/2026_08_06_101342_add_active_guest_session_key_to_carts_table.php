<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'carts',
            function (Blueprint $table): void {
                $table
                    ->string(
                        'active_guest_session_key',
                        100,
                    )
                    ->nullable()
                    ->after('session_id');
            },
        );

        $activeStatusId = DB::table('cart_statuses')
            ->where('status_name', 'active')
            ->value('id');

        if ($activeStatusId !== null) {
            DB::table('carts')
                ->whereNull('user_id')
                ->where(
                    'cart_status_id',
                    $activeStatusId,
                )
                ->whereNotNull('session_id')
                ->update([
                    'active_guest_session_key' => DB::raw('session_id'),
                ]);
        }

        Schema::table(
            'carts',
            function (Blueprint $table): void {
                $table->unique(
                    'active_guest_session_key',
                    'carts_active_guest_session_key_unique',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'carts',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'carts_active_guest_session_key_unique',
                );

                $table->dropColumn(
                    'active_guest_session_key',
                );
            },
        );
    }
};
