<?php

declare(strict_types=1);

use App\Enums\QaThreadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('certification_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status', 20)->default(QaThreadStatus::Unresolved->value);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // 複合インデックス（資格・ステータス別の絞り込み・新着順ソートを高速化）
            $table->index(['certification_id', 'status', 'updated_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_threads');
    }
};
