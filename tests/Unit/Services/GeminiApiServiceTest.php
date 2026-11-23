<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @group external-api
 */
class GeminiApiServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeminiApiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-2.5-flash',
            'ai-chat.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
        ]);
        $this->service = new GeminiApiService;
    }

    public function test_ask_sends_prompt_and_saves_successful_response(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();
        AiChatMessage::factory()->forConversation($conversation)->user()->create([
            'content' => 'Laravelのコントローラーについて教えてください',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'コントローラーはリクエストを受け取り処理するクラスです。'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 50,
                    'candidatesTokenCount' => 30,
                ],
            ], 200),
        ]);

        $message = $this->service->ask($conversation, 'Laravelのコントローラーについて教えてください');

        $this->assertInstanceOf(AiChatMessage::class, $message);
        $this->assertSame(AiChatMessageRole::Assistant, $message->role);
        $this->assertSame(AiChatMessageStatus::Completed, $message->status);
        $this->assertSame('コントローラーはリクエストを受け取り処理するクラスです。', $message->content);
        $this->assertSame(50, $message->input_tokens);
        $this->assertSame(30, $message->output_tokens);

        // プロンプト構造およびヘッダーの検証
        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['system_instruction'])
                && isset($data['contents'])
                && $request->hasHeader('x-goog-api-key', 'test-api-key');
        });
    }

    public function test_ask_handles_500_server_error_gracefully(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response([
                'error' => [
                    'code' => 500,
                    'message' => 'Internal server error',
                ],
            ], 500),
        ]);

        $message = $this->service->ask($conversation, '質問テキスト');

        // エラー時も例外で落ちず Error ステータスで保存されること
        $this->assertInstanceOf(AiChatMessage::class, $message);
        $this->assertSame(AiChatMessageStatus::Error, $message->status);
    }

    public function test_ask_handles_empty_response_gracefully(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response([
                'candidates' => [],
            ], 200),
        ]);

        $message = $this->service->ask($conversation, '空応答テスト');

        $this->assertInstanceOf(AiChatMessage::class, $message);
        $this->assertSame(AiChatMessageStatus::Error, $message->status);
    }

    public function test_ask_returns_error_message_when_api_key_is_not_configured(): void
    {
        config(['ai-chat.gemini.api_key' => null]);

        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $message = $this->service->ask($conversation, 'APIキー未設定');

        $this->assertSame(AiChatMessageStatus::Error, $message->status);
        $this->assertStringContainsString('Gemini API キーが未設定です', $message->error_detail);
    }
}
