<?php

declare(strict_types=1);

use App\Models\MlDailyFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function createTrainingDatasetFeature(
    string $date,
    int $pizzas,
    int $orders,
): MlDailyFeature {
    return MlDailyFeature::query()->create([
        'date' => $date,

        'total_pizzas_sold' => $pizzas,

        'mini_sales' => 0,

        'small_sales' => 0,

        'medium_sales' => $pizzas,

        'family_sales' => 0,

        'giant_sales' => 0,

        'basic_sales' => 0,

        'special_sales' => $pizzas,

        'promotion_sales' => 0,

        'regular_sales' => $pizzas,

        'delivered_orders' => $orders,

        'cancelled_orders' => 0,

        'net_sales' => $pizzas * 10,

        'pickup_orders' => $orders,

        'delivery_orders' => 0,

        'consolidated_at' => now(),

        'source' => 'laravel_sales',
    ]);
}

it(
    'requiere autenticación para consultar el dataset de entrenamiento',
    function (): void {
        getJson(
            '/api/v1/admin/machine-learning/dataset',
        )->assertUnauthorized();
    },
);

it(
    'impide a un cliente consultar el dataset de entrenamiento',
    function (): void {
        $customer =
            User::factory()
                ->customer()
                ->create();

        Sanctum::actingAs(
            $customer,
        );

        getJson(
            '/api/v1/admin/machine-learning/dataset',
        )->assertForbidden();
    },
);

it(
    'devuelve el dataset cronológico con resumen y madurez',
    function (): void {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Sanctum::actingAs(
            $admin,
        );

        createTrainingDatasetFeature(
            date: '2026-08-01',

            pizzas: 3,

            orders: 2,
        );

        createTrainingDatasetFeature(
            date: '2026-08-02',

            pizzas: 5,

            orders: 3,
        );

        $response =
            getJson(
                '/api/v1/admin/machine-learning/dataset',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.schema_version',
                '1.0',
            )
            ->assertJsonPath(
                'data.summary.records',
                2,
            )
            ->assertJsonPath(
                'data.summary.active_days',
                2,
            )
            ->assertJsonPath(
                'data.summary.total_delivered_orders',
                5,
            )
            ->assertJsonPath(
                'data.summary.total_pizzas_sold',
                8,
            )
            ->assertJsonPath(
                'data.summary.total_net_sales',
                80,
            )
            ->assertJsonPath(
                'data.maturity.status',
                'collecting',
            )
            ->assertJsonPath(
                'data.maturity.can_train_experimental',
                false,
            )
            ->assertJsonPath(
                'data.records.0.date',
                '2026-08-01',
            )
            ->assertJsonPath(
                'data.records.1.date',
                '2026-08-02',
            )
            ->assertJsonPath(
                'data.records.1.medium_sales',
                5,
            );
    },
);

it(
    'filtra el dataset por fechas',
    function (): void {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Sanctum::actingAs(
            $admin,
        );

        createTrainingDatasetFeature(
            '2026-08-01',
            2,
            1,
        );

        createTrainingDatasetFeature(
            '2026-08-02',
            4,
            2,
        );

        createTrainingDatasetFeature(
            '2026-08-03',
            6,
            3,
        );

        getJson(
            '/api/v1/admin/machine-learning/dataset'
            .'?date_from=2026-08-02'
            .'&date_to=2026-08-03',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.records',
                2,
            )
            ->assertJsonPath(
                'data.records.0.date',
                '2026-08-02',
            )
            ->assertJsonPath(
                'data.records.1.date',
                '2026-08-03',
            );
    },
);

it(
    'puede excluir días sin actividad comercial',
    function (): void {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Sanctum::actingAs(
            $admin,
        );

        createTrainingDatasetFeature(
            '2026-08-01',
            0,
            0,
        );

        createTrainingDatasetFeature(
            '2026-08-02',
            4,
            2,
        );

        getJson(
            '/api/v1/admin/machine-learning/dataset'
            .'?include_empty_days=false',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.records',
                1,
            )
            ->assertJsonPath(
                'data.records.0.date',
                '2026-08-02',
            );
    },
);

it(
    'valida el rango de fechas del dataset',
    function (): void {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Sanctum::actingAs(
            $admin,
        );

        getJson(
            '/api/v1/admin/machine-learning/dataset'
            .'?date_from=2026-08-10'
            .'&date_to=2026-08-01',
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    },
);
