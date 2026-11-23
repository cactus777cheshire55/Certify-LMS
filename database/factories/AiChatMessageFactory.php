<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    public function definition(): array
    {
        return [
            'ai_chat_conversation_id' => AiChatConversation::factory(),
            'role' => AiChatMessageRole::User,
            'content' => $this->faker->sentence(),
            'status' => AiChatMessageStatus::Completed,
        ];
    }

    public function forConversation(AiChatConversation $conversation): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_chat_conversation_id' => $conversation->id,
        ]);
    }

    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AiChatMessageRole::User,
        ]);
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AiChatMessageRole::Assistant,
        ]);
    }
}
