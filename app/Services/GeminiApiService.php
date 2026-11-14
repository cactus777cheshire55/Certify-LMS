<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiApiService
{
    /**
     * Gemini API へプロンプトを送信し、回答メッセージを保存・返却する
     */
    public function ask(AiChatConversation $conversation, string $userMessageText): AiChatMessage
    {
        $apiKey = config('ai-chat.gemini.api_key');
        $model = config('ai-chat.gemini.model', 'gemini-3.5-flash-lite');
        $baseUrl = config('ai-chat.gemini.base_url');

        // 1. API キー未設定時の制御（仕様要件）
        if (empty($apiKey)) {
            return AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant,
                'content' => '',
                'status' => AiChatMessageStatus::Error,
                'error_detail' => 'Gemini API キーが未設定です。管理者にお問い合わせください。',
                'model_name' => $model,
            ]);
        }

        // 2. 直近会話履歴（最大10件）の抽出
        //    呼び出し元で今回のユーザーメッセージは保存済みのため、最新の1件（今回分）は除外する
        $previousMessages = $conversation->messages()
            ->where('status', AiChatMessageStatus::Completed->value)
            ->orderByDesc('id')
            ->skip(1)
            ->take(10)
            ->get()
            ->reverse();

        // 3. システムプロンプトおよびコンテキストの構築
        $systemPrompt = $this->buildSystemPrompt($conversation);

        // 4. contents 配列の構築（★正しく配列の配列にする）
        $contents = [];
        foreach ($previousMessages as $msg) {
            $role = $msg->role === AiChatMessageRole::User ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $msg->content],
                ],
            ];
        }

        // 現在のユーザーメッセージを追加
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $userMessageText],
            ],
        ];

        // 5. リクエストペイロードの作成
        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048,
            ],
        ];

        // API キーは URL ではなくヘッダーで渡す（例外メッセージ・ログ・DB にキーが残らないようにする）
        $endpoint = "{$baseUrl}/{$model}:generateContent";
        $timeout = (int) config('ai-chat.gemini.timeout', 60);
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout($timeout)
                ->post($endpoint, $payload);
            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            // 6. 通信失敗時ハンドリング（仕様要件: Errorステータス保存・ログ記録）
            if ($response->failed()) {
                Log::warning('Gemini API Error', ['status' => $response->status(), 'body' => $response->body()]);

                return AiChatMessage::create([
                    'ai_chat_conversation_id' => $conversation->id,
                    'role' => AiChatMessageRole::Assistant,
                    'content' => '',
                    'status' => AiChatMessageStatus::Error,
                    'error_detail' => "HTTP {$response->status()}: {$response->body()}",
                    'model_name' => $model,
                    'response_time_ms' => $responseTimeMs,
                ]);
            }

            // 7. 成功時の回答・運用観測メタデータ（トークン数・応答時間）の保存
            $json = $response->json();
            // 思考モデルでは parts が複数に分かれることがあるため、thought 以外の text を連結する
            $responseText = collect(data_get($json, 'candidates.0.content.parts', []))
                ->reject(fn (array $part) => ! empty($part['thought']))
                ->pluck('text')
                ->filter()
                ->implode('');
            $inputTokens = $json['usageMetadata']['promptTokenCount'] ?? null;
            $outputTokens = $json['usageMetadata']['candidatesTokenCount'] ?? null;

            return AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant,
                'content' => $responseText,
                'status' => AiChatMessageStatus::Completed,
                'model_name' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'response_time_ms' => $responseTimeMs,
            ]);
        } catch (Throwable $e) {
            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
            Log::error('GeminiApiService Exception', ['error' => $e->getMessage()]);

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

    /**
     * 資格・教材のコンテキストを含んだシステムプロンプトの作成
     */
    private function buildSystemPrompt(AiChatConversation $conversation): string
    {
        $prompt = "あなたは資格学習プラットフォーム「Certify LMS」のAI相談アシスタントです。受講生の質問に対して分かりやすく親切に回答してください。\n";

        $conversation->loadMissing(['enrollment.certification', 'section.chapter.part']);

        if ($conversation->enrollment?->certification) {
            $certName = $conversation->enrollment->certification->name;
            $prompt .= "【目標資格】: {$certName}\n";
        }

        if ($conversation->section) {
            $partTitle = $conversation->section->chapter?->part?->title ?? '';
            $chapterTitle = $conversation->section->chapter?->title ?? '';
            $sectionTitle = $conversation->section->title;
            $sectionBody = mb_strimwidth($conversation->section->body ?? '', 0, 3000, '...');

            $prompt .= "【閲覧中教材】: {$partTitle} ＞ {$chapterTitle} ＞ {$sectionTitle}\n";
            $prompt .= "教材本文抜粋:\n{$sectionBody}\n";
            $prompt .= "受講生がこの教材を閲覧中に質問していることを念頭において回答してください。\n";
        }

        return $prompt;
    }
}