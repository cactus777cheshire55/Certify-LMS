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

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        // ロール別のテストユーザーを作成
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->student = User::factory()->create(['role' => UserRole::Student]);
        $this->coach = User::factory()->create(['role' => UserRole::Coach]);
    }

    /* =========================================================================
     * 1. 認可テスト（受講生・コーチ・未ログインユーザーのアクセス拒否）
     * ========================================================================= */

    /** @test */
    public function 受講生は面談パック管理の全画面および全操作_apiにアクセスできず403エラーになる(): void
    {
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);
        $params = $this->validParams();

        // GET /admin/meeting-packs (一覧表示)
        $this->actingAs($this->student)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();

        // GET /admin/meeting-packs/create (新規作成画面)
        $this->actingAs($this->student)
            ->get(route('admin.meeting-packs.create'))
            ->assertForbidden();

        // POST /admin/meeting-packs (新規保存)
        $this->actingAs($this->student)
            ->post(route('admin.meeting-packs.store'), $params)
            ->assertForbidden();

        // GET /admin/meeting-packs/{meetingPack} (詳細画面)
        $this->actingAs($this->student)
            ->get(route('admin.meeting-packs.show', $pack))
            ->assertForbidden();

        // GET /admin/meeting-packs/{meetingPack}/edit (編集画面)
        $this->actingAs($this->student)
            ->get(route('admin.meeting-packs.edit', $pack))
            ->assertForbidden();

        // PATCH /admin/meeting-packs/{meetingPack} (更新)
        $this->actingAs($this->student)
            ->patch(route('admin.meeting-packs.update', $pack), $params)
            ->assertForbidden();

        // DELETE /admin/meeting-packs/{meetingPack} (削除)
        $this->actingAs($this->student)
            ->delete(route('admin.meeting-packs.destroy', $pack))
            ->assertForbidden();

        // POST /admin/meeting-packs/{meetingPack}/publish (公開)
        $this->actingAs($this->student)
            ->post(route('admin.meeting-packs.publish', $pack))
            ->assertForbidden();

        // POST /admin/meeting-packs/{meetingPack}/archive (アーカイブ)
        $this->actingAs($this->student)
            ->post(route('admin.meeting-packs.archive', $pack))
            ->assertForbidden();

        // POST /admin/meeting-packs/{meetingPack}/unarchive (下書き復元)
        $this->actingAs($this->student)
            ->post(route('admin.meeting-packs.unarchive', $pack))
            ->assertForbidden();
    }

    /** @test */
    public function コーチは面談パック管理の全画面および全操作_apiにアクセスできず403エラーになる(): void
    {
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);
        $params = $this->validParams();

        // GET /admin/meeting-packs
        $this->actingAs($this->coach)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();

        // POST /admin/meeting-packs
        $this->actingAs($this->coach)
            ->post(route('admin.meeting-packs.store'), $params)
            ->assertForbidden();

        // PATCH /admin/meeting-packs/{meetingPack}
        $this->actingAs($this->coach)
            ->patch(route('admin.meeting-packs.update', $pack), $params)
            ->assertForbidden();

        // DELETE /admin/meeting-packs/{meetingPack}
        $this->actingAs($this->coach)
            ->delete(route('admin.meeting-packs.destroy', $pack))
            ->assertForbidden();
    }

    /** @test */
    public function 未ログインユーザーはログイン画面へリダイレクトされる(): void
    {
        $this->get(route('admin.meeting-packs.index'))
            ->assertRedirect(route('login'));
    }

    /* =========================================================================
     * 2. 管理者（Admin）の機能テスト（正常系・バリデーションエラー・表示）
     * ========================================================================= */

    /** @test */
    public function 管理者はget_admin_meeting_packsで一覧画面を表示できる(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.meeting-packs.index'));

        $response->assertOk()
            ->assertViewIs('meeting-pack.management.index');
    }

    /** @test */
    public function 管理者は_get_admin_meeting_packs_createで新規作成画面を表示できる(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.meeting-packs.create'));

        $response->assertOk()
            ->assertViewIs('meeting-pack.management.create');
    }

    /** @test */
    public function 管理者は_post_admin_meeting_packsで下書きとして新規保存できる(): void
    {
        $postData = [
            'name' => '5回追加面談パック',
            'description' => 'テスト用の説明文です。',
            'meeting_count' => 5,
            'price' => 15000,
            'stripe_price_id' => 'price_test_123',
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.store'), $postData);

        $this->assertDatabaseHas('meeting_packs', [
            'name' => '5回追加面談パック',
            'meeting_count' => 5,
            'price' => 15000,
            'status' => MeetingPackStatus::Draft->value,
            'created_by_user_id' => $this->admin->id,
        ]);

        $pack = MeetingPack::where('name', '5回追加面談パック')->first();
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
    }

    /** @test */
    public function 入力値が不正な場合はバリデーションエラーになり保存されない(): void
    {
        // 名前未入力・面談回数0・マイナス価格
        $invalidData = [
            'name' => '',
            'meeting_count' => 0,
            'price' => -100,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.store'), $invalidData);

        $response->assertSessionHasErrors(['name', 'meeting_count', 'price']);
        $this->assertDatabaseCount('meeting_packs', 0);
    }

    /** @test */
    public function 管理者は_get_admin_meeting_packs_planで詳細画面を表示できる(): void
    {
        $pack = MeetingPack::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.meeting-packs.show', $pack));

        $response->assertOk()
            ->assertViewIs('meeting-pack.management.show');
    }

    /** @test */
    public function 管理者は_get_admin_meeting_packs_plan_editで編集画面を表示できる(): void
    {
        $pack = MeetingPack::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.meeting-packs.edit', $pack));

        $response->assertOk()
            ->assertViewIs('meeting-pack.management.edit');
    }

    /** @test */
    public function 管理者は_patch_admin_meeting_packs_planで基本情報を更新できる(): void
    {
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);

        $updateData = [
            'name' => '更新後の10回パック',
            'description' => '更新後の説明文です。',
            'meeting_count' => 10,
            'price' => 28000,
            'stripe_price_id' => 'price_updated_456',
            'sort_order' => 2,
        ];

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.meeting-packs.update', $pack), $updateData);

        $response->assertRedirect(route('admin.meeting-packs.show', $pack));

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'name' => '更新後の10回パック',
            'price' => 28000,
            'updated_by_user_id' => $this->admin->id,
        ]);
    }

    /* =========================================================================
     * 3. ステータス遷移テスト（publish / archive / unarchive）
     * ========================================================================= */

    /** @test */
    public function 管理者は_post_publishで下書きから公開中に遷移できる(): void
    {
        $draftPack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.publish', $draftPack));

        $response->assertRedirect(route('admin.meeting-packs.show', $draftPack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $draftPack->id,
            'status' => MeetingPackStatus::Published->value,
        ]);
    }

    /** @test */
    public function 管理者は_post_archiveで公開中からアーカイブに遷移できる(): void
    {
        $publishedPack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Published]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.archive', $publishedPack));

        $response->assertRedirect(route('admin.meeting-packs.show', $publishedPack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $publishedPack->id,
            'status' => MeetingPackStatus::Archived->value,
        ]);
    }

    /** @test */
    public function 管理者は_post_unarchiveでアーカイブから下書きに復元できる(): void
    {
        $archivedPack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Archived]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.meeting-packs.unarchive', $archivedPack));

        $response->assertRedirect(route('admin.meeting-packs.show', $archivedPack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $archivedPack->id,
            'status' => MeetingPackStatus::Draft->value,
        ]);
    }

    /* =========================================================================
     * 4. 削除ガードテスト（【最重要要件】公開中パックの削除保護）
     * ========================================================================= */

    /** @test */
    public function 公開中の面談パックは_deleteで削除できず403エラーになりdbにデータが残る(): void
    {
        $publishedPack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Published]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.meeting-packs.destroy', $publishedPack));

        // 403 Forbidden になること
        $response->assertForbidden();

        // DBにデータが削除されず残っていること
        $this->assertDatabaseHas('meeting_packs', ['id' => $publishedPack->id]);
    }

    /** @test */
    public function 下書き状態の面談パックはdeleteで正常に削除できる(): void
    {
        $draftPack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.meeting-packs.destroy', $draftPack));

        $response->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $draftPack->id]);
    }

    /**
     * テスト用パラメータヘルパー
     */
    private function validParams(): array
    {
        return [
            'name' => 'テスト用面談パック',
            'description' => '説明文',
            'meeting_count' => 3,
            'price' => 10000,
            'sort_order' => 0,
        ];
    }
}
