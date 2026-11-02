<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\MeetingPackStatus;
use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->student = User::factory()->create(['role' => UserRole::Student]);
    }

    /** @test */
    public function 受講生やコーチは管理者画面にアクセスできず403エラーになる(): void
    {
        $response = $this->actingAs($this->student)
            ->get(route('admin.meeting-packs.index'));

        $response->assertForbidden();
    }

    /** @test */
    public function 管理者は面談パックを下書きとして新規作成できる(): void
    {
        $postData = [
            'name' => '5回追加パック',
            'description' => 'テスト用パック説明',
            'meeting_count' => 5,
            'price' => 15000,
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.store'), $postData);

        $this->assertDatabaseHas('meeting_packs', [
            'name' => '5回追加パック',
            'status' => MeetingPackStatus::Draft->value,
            'created_by_user_id' => $this->admin->id,
        ]);

        $pack = MeetingPack::where('name', '5回追加パック')->first();
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
    }

    /** @test */
    public function 下書き状態の面談パックは公開へ遷移できる(): void
    {
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.publish', $pack));

        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'status' => MeetingPackStatus::Published->value,
        ]);
    }

    /** @test */
    public function 公開中の面談パックは削除しようとすると403エラーになり_d_bに残る(): void
    {
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Published]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.meeting-packs.destroy', $pack));

        // 削除が拒否され 403 になること
        $response->assertForbidden();

        // データベースに削除されずに存在し続けていること
        $this->assertDatabaseHas('meeting_packs', ['id' => $pack->id]);
    }

    /** @test */
    public function 下書き状態の面談パックは正常に削除できる(): void
    {
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.meeting-packs.destroy', $pack));

        $response->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $pack->id]);
    }

    /** @test */
    public function 面談パックのステータスが正常に一連の遷移を行えること_下書き_公開中_アーカイブ_下書き戻し(): void
    {
        // 1. 初期状態：下書き (Draft) でデータを作成
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);

        // ----------------------------------------------------
        // 【遷移1】 下書き → 公開中 (publish)
        // ----------------------------------------------------
        $response1 = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.publish', $pack));

        $response1->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'status' => MeetingPackStatus::Published->value,
        ]);

        // ----------------------------------------------------
        // 【遷移2】 公開中 → アーカイブ (archive)
        // ----------------------------------------------------
        $response2 = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.archive', $pack));

        $response2->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'status' => MeetingPackStatus::Archived->value,
        ]);

        // ----------------------------------------------------
        // 【遷移3】 アーカイブ → 下書き戻す (unarchive)
        // ----------------------------------------------------
        $response3 = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.unarchive', $pack));

        $response3->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'status' => MeetingPackStatus::Draft->value, // 最初の状態に完全に戻る
        ]);
    }
}
