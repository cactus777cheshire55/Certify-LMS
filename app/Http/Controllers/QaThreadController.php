<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaThread;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class QaThreadController extends Controller
{
    use AuthorizesRequests;

    /**
     * スレッド一覧の表示（絞り込み・ページネーション・N+1対策）
     */
    public function index(Request $request)
    {
        // 1. Policy 経由で認可チェック（失敗すると自動的に 403.blade.php が表示される）
        $this->authorize('viewAny', QaThread::class);

        // または直接受講生ステータスをチェックして 403 へ飛ばす場合
        $user = $request->user();
        if ($user->role === UserRole::Student && $user->status !== UserStatus::InProgress) {
            abort(403);
        }

        // ロールに応じた閲覧可能資格の取得
        $certificationsQuery = Certification::query();
        if ($user->role === UserRole::Student) {
            $certificationsQuery->where('status', CertificationStatus::Published);
        } elseif ($user->role === UserRole::Coach) {
            $certificationsQuery->whereIn('id', $user->assignedCertifications()->pluck('certifications.id'));
        }
        $certifications = $certificationsQuery->orderBy('name')->get();

        // 絞り込み条件の取得
        $filters = [
            'status' => $request->query('status', ''),
            'certification_id' => $request->query('certification_id', ''),
            'keyword' => $request->query('keyword', ''),
        ];

        // クエリの構築（N+1対策のため関連モデルを一括取得・カウント）
        $threadsQuery = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies');

        // アクセス可能な資格のみに限定
        $threadsQuery->whereIn('certification_id', $certifications->pluck('id'));

        // 解決状態フィルター
        if ($filters['status'] === 'unresolved') {
            $threadsQuery->where('status', QaThreadStatus::Unresolved);
        } elseif ($filters['status'] === 'resolved') {
            $threadsQuery->where('status', QaThreadStatus::Resolved);
        }

        // 資格フィルター
        if (! empty($filters['certification_id'])) {
            $threadsQuery->where('certification_id', $filters['certification_id']);
        }

        // キーワード検索（本文・タイトルの部分一致）
        if (! empty($filters['keyword'])) {
            $keyword = '%'.$filters['keyword'].'%';
            $threadsQuery->where(function ($q) use ($keyword) {
                $q->where('title', 'like', $keyword)
                    ->orWhere('body', 'like', $keyword);
            });
        }

        $threads = $threadsQuery->latest('updated_at')->paginate(10)->withQueryString();

        return view('qa-thread.index', [
            'threads' => $threads,
            'certifications' => $certifications,
            'filters' => $filters,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    /**
     * 新規質問投稿画面
     */
    public function create()
    {
        $this->authorize('create', QaThread::class);

        $certifications = Certification::where('status', CertificationStatus::Published)
            ->orderBy('name')
            ->get();

        return view('qa-thread.create', compact('certifications'));
    }

    /**
     * 新規質問の保存
     */
    public function store(Request $request)
    {
        $this->authorize('create', QaThread::class);

        $validated = $request->validate([
            'certification_id' => ['required', 'exists:certifications,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $thread = QaThread::create([
            'user_id' => $request->user()->id,
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => QaThreadStatus::Unresolved,
        ]);

        return redirect()->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    /**
     * スレッド詳細表示
     */
    public function show(QaThread $thread)
    {
        $this->authorize('view', $thread);

        $thread->load(['user', 'certification', 'replies.user']);

        return view('qa-thread.show', [
            'thread' => $thread,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    /**
     * 質問編集画面
     */
    public function edit(QaThread $thread)
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', compact('thread'));
    }

    /**
     * 質問の更新（資格の切り替えは不可）
     */
    public function update(Request $request, QaThread $thread)
    {
        $this->authorize('update', $thread);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $thread->update($validated);

        return redirect()->route('qa-board.show', $thread)
            ->with('success', '質問内容を更新しました。');
    }

    /**
     * 質問の物理削除
     */
    public function destroy(QaThread $thread)
    {
        $this->authorize('delete', $thread);

        $thread->delete();

        return redirect()->route('qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    /**
     * 解決済にする
     */
    public function resolve(QaThread $thread)
    {
        $this->authorize('resolve', $thread);

        $thread->update([
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]);

        return redirect()->route('qa-board.show', $thread)
            ->with('success', '質問を解決済にしました。');
    }

    /**
     * 未解決に戻す
     */
    public function unresolve(QaThread $thread)
    {
        $this->authorize('unresolve', $thread);

        $thread->update([
            'status' => QaThreadStatus::Unresolved,
            'resolved_at' => null,
        ]);

        return redirect()->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }
}
