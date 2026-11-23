<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoogleCredential>
 */
class GoogleCredentialFactory extends Factory
{
    protected $model = GoogleCredential::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'calendar_id' => fake()->safeEmail(),
            'access_token' => 'fake-access-token-'.Str::random(16),
            'refresh_token' => 'fake-refresh-token-'.Str::random(16),
            'expires_at' => now()->addHour(),
        ];
    }
}
