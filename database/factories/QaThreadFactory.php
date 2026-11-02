<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\QaThread;
use App\Enums\QaThreadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class QaThreadFactory extends Factory
{
    protected $model = QaThread::class;

    public function definition(): array
    {
        return [
            // user_id は既存の UserFactory からULIDを自動生成
            'user_id' => User::factory(), 
            
            // certification_id はプロジェクトに存在する Certification モデル等のファクトリを指定してください
            // もしモデル名が異なる場合は修正してください（例: App\Models\Certification）
            'certification_id' => \App\Models\Certification::factory(), 
            
            'title' => $this->faker->realText(50), // 200文字以内
            'body' => $this->faker->realText(500),
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
        ];
    }
}