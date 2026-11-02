<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class QaReplyController extends Controller
{
    use AuthorizesRequests;

    /**
     * 回答の投稿
     */
    public function store(Request $request, QaThread $thread)
    {
        $this->authorize('create', [QaReply::class, $thread]);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

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
    public function update(Request $request, QaThread $thread, QaReply $reply)
    {
        $this->authorize('update', $reply);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

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
