<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\QaThread;
use App\Models\QaReply;
use Illuminate\Database\Eloquent\Factories\Factory;

class QaReplyFactory extends Factory
{
    protected $model = QaReply::class;

    public function definition(): array
    {
        return [
            // 親スレッドと投稿ユーザーを自動作成して紐付け
            'qa_thread_id' => QaThread::factory(),
            'user_id' => User::factory(),
            'body' => $this->faker->realText(300),
        ];
    }
}