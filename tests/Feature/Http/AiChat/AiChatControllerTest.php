<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create(['role' => 'student']);
    }

    /**
     * 会話の新規作成と初期メッセージ送信（正常系）
     */
    public function test_can_start_conversation_and_receive_gemini_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'こんにちは！学習のご質問についてお答えします。'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 20,
                    'candidatesTokenCount' => 40,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson(route('ai-chat.conversations.store'), [
                'message' => 'Laravelのルーティングについて教えてください',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'conversation' => ['id', 'title'],
                'user_message' => ['id', 'content'],
                'assistant_message' => ['id', 'content', 'status'],
            ]);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'user_id' => $this->student->id,
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => 'こんにちは！学習のご質問についてお答えします。',
        ]);
    }

    /**
     * 教材 (Section) コンテキスト付きメッセージ送信
     */
    public function test_sends_context_aware_message_with_section(): void
    {
        $section = Section::factory()->create();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '該当セクションのポイントを解説します。'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson(route('ai-chat.conversations.store'), [
                'section_id' => $section->id,
                'message' => 'このセクションのポイントは何ですか？',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'user_id' => $this->student->id,
            'section_id' => $section->id,
        ]);
    }

    /**
     * Gemini API 通信エラー時のフォールバック処理（異常系）
     */
    public function test_handles_gemini_api_error_gracefully(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 500,
                    'message' => 'Internal Server Error',
                ],
            ], 500),
        ]);

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $this->student->id,
        ]);

        $response = $this->actingAs($this->student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => 'エラーテスト質問',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Error->value,
        ]);
    }

    /**
     * 他人の会話履歴へのアクセス制限（認可テスト）
     */
    public function test_user_cannot_access_other_users_conversation(): void
    {
        $otherUser = User::factory()->create(['role' => 'student']);
        $conversation = AiChatConversation::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->student)
            ->getJson(route('ai-chat.conversations.show', $conversation));

        $response->assertStatus(403);
    }

    /**
     * 1日のメッセージ送信上限制限（Daily Limit）
     */
    public function test_enforces_daily_message_limit(): void
    {
        config(['ai-chat.daily_limit' => 2]);

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $this->student->id,
        ]);

        AiChatMessage::factory()->count(2)->create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), [
                'content' => '制限超過テスト',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }
}