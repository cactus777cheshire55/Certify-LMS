<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QaReply extends Model
{
    use HasFactory;

    /**
     * 一括代入可能な属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'qa_thread_id',
        'user_id',
        'body',
    ];

    /**
     * 回答を投稿したユーザー（受講生またはコーチ）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 回答が属する質問スレッド
     */
    public function qaThread(): BelongsTo
    {
        return $this->belongsTo(QaThread::class, 'qa_thread_id');
    }
}
