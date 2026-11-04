<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Http\Request;

class QaThreadController extends Controller
{
    /**
     * モデレーション一覧（公開停止中を含む全資格を横断表示）
     */
    public function index(Request $request)
    {
        $certifications = Certification::orderBy('name')->get();

        $filters = [
            'status' => $request->query('status', ''),
            'certification_id' => $request->query('certification_id', ''),
            'keyword' => $request->query('keyword', ''),
        ];

        $threadsQuery = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies');

        if (! empty($filters['certification_id'])) {
            $threadsQuery->where('certification_id', $filters['certification_id']);
        }

        if ($filters['status'] === 'unresolved') {
            $threadsQuery->where('status', QaThreadStatus::Unresolved);
        } elseif ($filters['status'] === 'resolved') {
            $threadsQuery->where('status', QaThreadStatus::Resolved);
        }

        if (! empty($filters['keyword'])) {
            $keyword = '%'.$filters['keyword'].'%';
            $threadsQuery->where(function ($q) use ($keyword) {
                $q->where('title', 'like', $keyword)
                    ->orWhere('body', 'like', $keyword);
            });
        }

        $threads = $threadsQuery->latest('updated_at')->paginate(10)->withQueryString();

        // Blade 側は共用するため isAdminContext=true の判定で動線を切り替えます
        return view('qa-thread.index', [
            'threads' => $threads,
            'certifications' => $certifications,
            'filters' => $filters,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    /**
     * モデレーション詳細画面
     */
    public function show(QaThread $thread)
    {
        $thread->load(['user', 'certification', 'replies.user']);

        return view('qa-thread.show', [
            'thread' => $thread,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    /**
     * 管理者によるスレッドのモデレーション削除
     */
    public function destroy(QaThread $thread)
    {
        $thread->delete();

        return redirect()->route('admin.qa-board.index')
            ->with('success', '不適切なスレッドを削除しました。');
    }

    /**
     * 管理者による回答のモデレーション削除
     */
    public function destroyReply(QaThread $thread, QaReply $reply)
    {
        $reply->delete();

        return redirect()->route('admin.qa-board.show', $thread)
            ->with('success', '不適切な回答を削除しました。');
    }
}
