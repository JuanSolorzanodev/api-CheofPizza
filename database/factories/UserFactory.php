<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::query()->firstOrCreate(
                [
                    'role_name' => 'customer',
                ]
            )->id,

            'first_name' => fake()->firstName(),

            'last_name' => fake()->lastName(),

            'phone' => fake()->unique()->numerify(
                '09########'
            ),

            'email' => fake()
                ->unique()
                ->safeEmail(),

            'password' => static::$password ??=
                Hash::make('password'),
        ];
    }

    public function customer(): static
    {
        return $this->withRole('customer');
    }

    public function operator(): static
    {
        return $this->withRole('operator');
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    private function withRole(
        string $roleName
    ): static {
        return $this->state(function () use (
            $roleName
        ): array {
            return [
                'role_id' => Role::query()
                    ->firstOrCreate([
                        'role_name' => $roleName,
                    ])
                    ->id,
            ];
        });
    }
}
