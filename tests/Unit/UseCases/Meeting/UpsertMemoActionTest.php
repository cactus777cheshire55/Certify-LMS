<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_memo_creates_and_updates_memo(): void
    {
        $meeting = Meeting::factory()->create([
            'status' => MeetingStatus::Reserved->value,
        ]);

        $action = app(UpsertMemoAction::class);

        // 新規作成の検証
        $memo = $action($meeting, '初回面談のメモテキスト');
        $this->assertSame('初回面談のメモテキスト', $memo->body);
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '初回面談のメモテキスト',
        ]);

        // 更新の検証
        $updatedMemo = $action($meeting, '追記事項を追加したメモ');
        $this->assertSame('追記事項を追加したメモ', $updatedMemo->body);
        $this->assertDatabaseCount('meeting_memos', 1);
    }
}