<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Section;
use App\Services\GeminiApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiChatController extends Controller
{
    /**
     * AI 相談フル画面（未選択または最新会話）
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! config('ai-chat.enabled', false)) {
            abort(403, 'AI 相談機能は無効化されています。');
        }

        $user = $request->user();
        $latestConversation = $user->aiChatConversations()
            ->orderByDesc('last_message_at')
            ->first();

        if ($latestConversation) {
            return redirect()->route('ai-chat.conversations.show', $latestConversation);
        }

        // 既存の storeConversation と同じルールで初期タイトルを決定して空の会話を作成
        $defaultEnrollment = $user->defaultEnrollment;
        $title = $defaultEnrollment?->certification?->name
            ? "🎓 {$defaultEnrollment->certification->name}"
            : '新規 AI 相談';

        $conversation = AiChatConversation::create([
            'user_id' => $user->id,
            'enrollment_id' => $defaultEnrollment?->id,
            'section_id' => null,
            'title' => Str::limit($title, 100),
            'last_message_at' => now(),
        ]);

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    /**
     * 会話の新規作成
     */
    public function storeConversation(StoreConversationRequest $request, GeminiApiService $geminiService): RedirectResponse|JsonResponse
    {
        if (! config('ai-chat.enabled', false)) {
            abort(403);
        }

        $user = $request->user();
        $sectionId = $request->validated('section_id');
        $initialMessageText = $request->validated('message');

        // 教材から作成された場合、同一 Section の最新会話を再利用
        if ($sectionId) {
            $existing = $user->aiChatConversations()
                ->where('section_id', $sectionId)
                ->orderByDesc('last_message_at')
                ->first();

            if ($existing) {
                if ($request->wantsJson()) {
                    // 200 = 既存会話の再開（Widget は data.conversation.id を参照する）
                    return response()->json($this->conversationPayload($existing), 200);
                }
                return redirect()->route('ai-chat.conversations.show', $existing);
            }
        }

        $section = $sectionId ? Section::find($sectionId) : null;
        $defaultEnrollment = $user->defaultEnrollment;

        $title = $section?->title ? "📚 {$section->title}" : ($defaultEnrollment?->certification?->name ? "🎓 {$defaultEnrollment->certification->name}" : '新規 AI 相談');

        $conversation = DB::transaction(function () use ($user, $defaultEnrollment, $sectionId, $title) {
            return AiChatConversation::create([
                'user_id' => $user->id,
                'enrollment_id' => $defaultEnrollment?->id,
                'section_id' => $sectionId,
                'title' => Str::limit($title, 100),
                'last_message_at' => now(),
            ]);
        });

        $userMessage = null;
        $assistantMessage = null;

        if (! empty($initialMessageText)) {
            $userMessage = AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::User,
                'content' => $initialMessageText,
                'status' => AiChatMessageStatus::Completed,
            ]);

            //戻り値を $assistantMessage に代入する
            $assistantMessage = $geminiService->ask($conversation, $initialMessageText);
            $conversation->update(['last_message_at' => now()]);
        }


        if ($request->wantsJson()) {
            // 第2・第3引数に渡す
            return response()->json($this->conversationPayload($conversation, $userMessage, $assistantMessage), 201);
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    /**
     * Widget / フル画面 JS 向けの会話 JSON
     *
     * @return array<string, mixed>
     */
    private function conversationPayload(
        AiChatConversation $conversation,
        ?AiChatMessage $userMessage = null,
        ?AiChatMessage $assistantMessage = null,
    ): array
    {
        return [
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'section_id' => $conversation->section_id,
            ],
            'redirect_url' => route('ai-chat.conversations.show', $conversation),
            'user_message' => $userMessage ? [
                'id' => $userMessage->id,
                'role' => $userMessage->role->value,
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at?->format('H:i'),
            ] : null,
            'assistant_message' => $assistantMessage ? [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role->value,
                'content' => $assistantMessage->content,
                'status' => $assistantMessage->status->value,
                'created_at' => $assistantMessage->created_at?->format('H:i'),
            ] : null,
        ];
    }

    /**
     * 1会話分の詳細表示
     */
    public function showConversation(Request $request, AiChatConversation $conversation): View|JsonResponse
    {
        if (! config('ai-chat.enabled', false)) {
            abort(403);
        }

        $this->authorize('view', $conversation);
        $conversation->load(['messages', 'enrollment.certification', 'section']);

        // Widget の履歴復元（Accept: application/json）。運用メタデータは受講生に返さない
        if ($request->wantsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'section_id' => $conversation->section_id,
                ],
                'messages' => $conversation->messages->map(fn (AiChatMessage $m) => [
                    'id' => $m->id,
                    'role' => $m->role->value,
                    'content' => $m->content,
                    'status' => $m->status->value,
                    'created_at' => $m->created_at?->format('H:i'),
                ])->values(),
            ]);
        }

        return view('ai-chat.show', [
            'conversation' => $conversation,
        ]);
    }

    /**
     * 会話タイトルの更新
     */
    public function updateConversation(UpdateConversationRequest $request, AiChatConversation $conversation): RedirectResponse|JsonResponse
    {
        if (! config('ai-chat.enabled', false)) {
            abort(403);
        }

        $conversation->update([
            'title' => $request->validated('title'),
            'auto_title_enabled' => false,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'title' => $conversation->title]);
        }

        return redirect()->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを変更しました。');
    }

    /**
     * 会話の削除
     */
    public function destroyConversation(AiChatConversation $conversation): RedirectResponse
    {
        if (! config('ai-chat.enabled', false)) {
            abort(403);
        }

        $this->authorize('delete', $conversation);
        $conversation->delete();

        return redirect()->route('ai-chat.index')
            ->with('success', '会話を削除しました。');
    }

    /**
     * メッセージの非同期送信・Gemini応答の取得
     */
    public function storeMessage(StoreMessageRequest $request, AiChatConversation $conversation, GeminiApiService $geminiService): JsonResponse
    {
        if (! config('ai-chat.enabled', false)) {
            return response()->json(['error' => 'AI 相談機能は無効です。'], 403);
        }

        $user = $request->user();

        // 1日の送信数上限チェック（config のキー名の揺れにも対応）
        $dailyLimit = (int) config('ai-chat.daily_limit', config('ai-chat.daily_message_limit', 30));
        $todaySentCount = AiChatMessage::whereHas('conversation', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->where('role', AiChatMessageRole::User->value)
        ->where('created_at', '>=', now()->startOfDay())
        ->count();

        if ($todaySentCount >= $dailyLimit) {
            $limitMessage = "本日の AI 相談上限数（{$dailyLimit} 回）に達しました。明日再度お試しください。";

            return response()->json([
                'message' => $limitMessage,
                'error' => $limitMessage,
                'errors' => [
                    'message' => [$limitMessage],
                ],
            ], 422);
        }

        $userContent = $request->validated('content');

        // 1. 受講生の質問本文を保存
        $userMessage = AiChatMessage::create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User,
            'content' => $userContent,
            'status' => AiChatMessageStatus::Completed,
        ]);

        // 2. Gemini API 通信
        $assistantMessage = $geminiService->ask($conversation, $userContent);

        // 会話の最終送信日時を更新
        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'user_message' => [
                'id' => $userMessage->id,
                'role' => $userMessage->role->value,
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at->format('H:i'),
            ],
            'assistant_message' => [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role->value,
                'content' => $assistantMessage->content,
                'status' => $assistantMessage->status->value,
                'error_detail' => $assistantMessage->error_detail,
                'model_name' => $assistantMessage->model_name,
                'response_time_ms' => $assistantMessage->response_time_ms,
                'output_tokens' => $assistantMessage->output_tokens,
                'created_at' => $assistantMessage->created_at->format('H:i'),
            ],
        ]);
    }
}