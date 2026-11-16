<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreQaReplyRequest;
use App\Http\Requests\UpdateQaReplyRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class QaReplyController extends Controller
{
    use AuthorizesRequests;

    /**
     * 回答の投稿
     */
    public function store(StoreQaReplyRequest $request, QaThread $thread)
    {
        $validated = $request->validated();

        $reply = QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        // 2. 質問者本人以外の回答の場合に通知を発行
        if ($thread->user_id !== $request->user()->id) {
            $thread->user->notify(new QaReplyReceivedNotification($reply));
        }

        // スレッドの最終更新日時を更新
        $thread->touch();

        return redirect()->route('qa-board.show', $thread)
            ->with('success', '回答を投稿しました。');
    }

    /**
     * 回答編集画面
     */
    public function edit(QaThread $thread, QaReply $reply)
    {
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', compact('thread', 'reply'));
    }

    /**
     * 回答の更新
     */
    public function update(UpdateQaReplyRequest $request, QaThread $thread, QaReply $reply)
    {
        $validated = $request->validated();

        $reply->update($validated);

        return redirect()->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    /**
     * 回答の物理削除
     */
    public function destroy(QaThread $thread, QaReply $reply)
    {
        $this->authorize('delete', $reply);

        $reply->delete();

        return redirect()->route('qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
