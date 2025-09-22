<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $hash = Hash::make('password', ['rounds' => 12]);

            if (str_starts_with($hash, '$2y$')) {
                $hash = '$2b$'.substr($hash, 4);
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['password' => $hash]);

            $user->refresh();

            static::$password = $hash;
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->userName(),
            'phone_number' => '62'.fake()->unique()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password', ['rounds' => 12]),
            'status' => 'gratis',
        ];
    }

    /**
     * Placeholder to mirror previous API.
     */
    public function unverified(): static
    {
        return $this;
    }
}
