<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // coach_id と scheduled_at の組み合わせに UNIQUE 制約を追加
            $table->unique(['coach_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique(['coach_id', 'scheduled_at']);
        });
    }
};
