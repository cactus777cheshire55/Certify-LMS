<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentGoal>
 */
class EnrollmentGoalFactory extends Factory
{
    protected $model = EnrollmentGoal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // EnrollmentがULIDや通常のIDであっても追従できるようにリレーションを設定
            'enrollment_id' => Enrollment::factory(),
            'title' => $this->faker->realText(20),
            'description' => $this->faker->realText(100),
            'target_date' => $this->faker->optional()->date(),
            'achieved_at' => null,
        ];
    }

    /**
     * 達成済みの状態を作るためのステート（必要に応じて）
     */
    public function achieved(): static
    {
        return $this->state(fn (array $attributes) => [
            'achieved_at' => now(),
        ]);
    }
}
