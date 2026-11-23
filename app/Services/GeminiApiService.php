<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiApiService
{
    /**
     * Gemini API へプロンプトを送信し、アシスタントの応答メッセージ（AiChatMessage）を保存して返す。
     */
    public function ask(AiChatConversation $conversation, string $promptText): AiChatMessage
    {
        $apiKey = config('ai-chat.gemini.api_key');
        $model = config('ai-chat.gemini.model', 'gemini-2.5-flash');
        $baseUrl = config('ai-chat.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/models');
        $url = "{$baseUrl}/{$model}:generateContent";

        // API キー未設定時の防御処理
        if (empty($apiKey)) {
            return AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant,
                'content' => '',
                'status' => AiChatMessageStatus::Error,
                'error_detail' => 'Gemini API キーが未設定です。',
                'model_name' => $model,
            ]);
        }

        $systemInstruction = [
            'parts' => [
                ['text' => 'あなたは資格学習をサポートする優秀なAIアシスタントです。受講生の質問に分かりやすく丁寧に対応してください。'],
            ],
        ];

        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $promptText],
                ],
            ],
        ];

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
            ])->post($url, [
                'system_instruction' => $systemInstruction,
                'contents' => $contents,
            ]);

            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            // HTTP エラー（500 や 429 等）の場合
            if ($response->failed()) {
                return AiChatMessage::create([
                    'ai_chat_conversation_id' => $conversation->id,
                    'role' => AiChatMessageRole::Assistant,
                    'content' => '',
                    'status' => AiChatMessageStatus::Error,
                    'error_detail' => 'Gemini API Error: '.$response->body(),
                    'model_name' => $model,
                    'response_time_ms' => $responseTimeMs,
                ]);
            }

            $data = $response->json();
            $candidates = $data['candidates'] ?? [];
            // ★ 配列の最初の要素 [0] からテキストを取得するように修正
            $replyText = $candidates[0]['content']['parts'][0]['text'] ?? '';

            // 空応答の場合
            if (empty($candidates) || $replyText === '') {
                return AiChatMessage::create([
                    'ai_chat_conversation_id' => $conversation->id,
                    'role' => AiChatMessageRole::Assistant,
                    'content' => '',
                    'status' => AiChatMessageStatus::Error,
                    'error_detail' => 'Gemini API から応答テキストが返されませんでした。',
                    'model_name' => $model,
                    'response_time_ms' => $responseTimeMs,
                ]);
            }

            $inputTokens = $data['usageMetadata']['promptTokenCount'] ?? null;
            $outputTokens = $data['usageMetadata']['candidatesTokenCount'] ?? null;

            return AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant,
                'content' => $replyText,
                'status' => AiChatMessageStatus::Completed,
                'model_name' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'response_time_ms' => $responseTimeMs,
            ]);
        } catch (Throwable $e) {
            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            return AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant,
                'content' => '',
                'status' => AiChatMessageStatus::Error,
                'error_detail' => $e->getMessage(),
                'model_name' => $model,
                'response_time_ms' => $responseTimeMs,
            ]);
        }
    }
}
