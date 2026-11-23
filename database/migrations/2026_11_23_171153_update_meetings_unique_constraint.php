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
            $table->dateTime('reserved_scheduled_at')
                ->nullable()
                ->storedAs("CASE WHEN status = 'reserved' THEN scheduled_at ELSE NULL END")
                ->after('scheduled_at');

            $table->unique(['coach_id', 'reserved_scheduled_at'], 'meetings_coach_id_reserved_scheduled_at_unique');

            $table->dropUnique(['coach_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unique(['coach_id', 'scheduled_at']);
            $table->dropUnique('meetings_coach_id_reserved_scheduled_at_unique');
            $table->dropColumn('reserved_scheduled_at');
        });
    }
};
