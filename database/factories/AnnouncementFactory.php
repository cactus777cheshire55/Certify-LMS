<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'title' => $this->faker->realText(50),
            'body' => $this->faker->realText(500),
            'target_type' => AnnouncementTargetType::cases()[0],
            'target_certification_id' => null,
            'target_user_id' => null,
            'dispatched_count' => 0,
            'dispatched_at' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    /**
     * 配信済みの状態にする状態遷移メソッド
     */
    public function dispatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispatched_at' => now(),
            'dispatched_count' => 1,
        ]);
    }
}
