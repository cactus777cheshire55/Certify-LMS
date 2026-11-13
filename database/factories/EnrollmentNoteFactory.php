<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentNote>
 */
class EnrollmentNoteFactory extends Factory
{
    /**
     * 対応するモデル名
     *
     * @var string
     */
    protected $model = EnrollmentNote::class;

    /**
     * モデルのデフォルト状態の定義
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // HasUlidsトレイトが自動でIDを生成するため、本来は明示的なStr::ulid()の指定は不要です
            'enrollment_id' => Enrollment::factory(),
            'author_id' => User::factory(),
            'body' => $this->faker->realText(200),
        ];
    }
}
