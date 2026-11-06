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
            $table->timestamp('reminded_eve_at')->nullable()->after('meeting_url_snapshot');
            $table->timestamp('reminded_one_hour_at')->nullable()->after('reminded_eve_at');

            $table->index(['status', 'scheduled_at', 'reminded_eve_at']);
            $table->index(['status', 'scheduled_at', 'reminded_one_hour_at']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['reminded_eve_at', 'reminded_one_hour_at']);
        });
    }
};