<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PlanStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
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
    public function 受講生はプラン管理画面や各種操作_ap_iにアクセスできず403エラーになる(): void
    {
        $plan = Plan::factory()->create(['status' => PlanStatus::Draft]);
        $params = $this->validParams();

        // 一覧画面表示 (GET /admin/plans)
        $this->actingAs($this->student)
            ->get(route('admin.plans.index'))
            ->assertForbidden();

        // 新規作成 (POST /admin/plans)
        $this->actingAs($this->student)
            ->post(route('admin.plans.store'), $params)
            ->assertForbidden();

        // 更新 (PUT /admin/plans/{plan})
        $this->actingAs($this->student)
            ->put(route('admin.plans.update', $plan), $params)
            ->assertForbidden();

        // 削除 (DELETE /admin/plans/{plan})
        $this->actingAs($this->student)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertForbidden();

        // 公開 (POST /admin/plans/{plan}/publish)
        $this->actingAs($this->student)
            ->post(route('admin.plans.publish', $plan))
            ->assertForbidden();

        // アーカイブ (POST /admin/plans/{plan}/archive)
        $this->actingAs($this->student)
            ->post(route('admin.plans.archive', $plan))
            ->assertForbidden();

        // 下書き復元 (POST /admin/plans/{plan}/unarchive)
        $this->actingAs($this->student)
            ->post(route('admin.plans.unarchive', $plan))
            ->assertForbidden();
    }

    /** @test */
    public function コーチはプラン管理画面や各種操作_ap_iにアクセスできず403エラーになる(): void
    {
        $plan = Plan::factory()->create(['status' => PlanStatus::Draft]);

        // 一覧表示
        $this->actingAs($this->coach)
            ->get(route('admin.plans.index'))
            ->assertForbidden();

        // 更新
        $this->actingAs($this->coach)
            ->put(route('admin.plans.update', $plan), $this->validParams())
            ->assertForbidden();

        // 削除
        $this->actingAs($this->coach)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertForbidden();

        // 公開
        $this->actingAs($this->coach)
            ->post(route('admin.plans.publish', $plan))
            ->assertForbidden();
    }

    /** @test */
    public function 未ログインユーザーはログイン画面へリダイレクトされる(): void
    {
        $response = $this->get(route('admin.plans.index'));

        $response->assertRedirect(route('login'));
    }

    /* =========================================================================
     * 2. 管理者（Admin）の機能テスト（正常系・ステータス遷移・削除ガード）
     * ========================================================================= */

    /** @test */
    public function 管理者はプラン一覧画面を閲覧できる(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.plans.index'));

        $response->assertOk()
            ->assertViewIs('plan.management.index');
    }

    /** @test */
    public function 管理者はプランを下書きとして新規作成できる(): void
    {
        $postData = [
            'name' => '1ヶ月集中プラン',
            'description' => 'テスト用プラン説明',
            'duration_days' => 30,
            'default_meeting_quota' => 4,
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.plans.store'), $postData);

        $this->assertDatabaseHas('plans', [
            'name' => '1ヶ月集中プラン',
            'status' => PlanStatus::Draft->value,
            'created_by_user_id' => $this->admin->id,
        ]);

        $plan = Plan::where('name', '1ヶ月集中プラン')->first();
        $response->assertRedirect(route('admin.plans.show', $plan));
    }

    /** @test */
    public function 管理者はプランの基本情報を更新できる(): void
    {
        $plan = Plan::factory()->create(['status' => PlanStatus::Draft]);

        $updateData = [
            'name' => '更新後の3ヶ月プラン',
            'description' => '更新された説明文です。',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 5,
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.plans.update', $plan), $updateData);

        $response->assertRedirect(route('admin.plans.show', $plan));

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後の3ヶ月プラン',
            'duration_days' => 90,
            'updated_by_user_id' => $this->admin->id,
        ]);
    }

    /** @test */
    public function 管理者は下書きプランを公開中に変更できる(): void
    {
        $draftPlan = Plan::factory()->create(['status' => PlanStatus::Draft]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.plans.publish', $draftPlan));

        $response->assertRedirect(route('admin.plans.show', $draftPlan));
        $this->assertDatabaseHas('plans', [
            'id' => $draftPlan->id,
            'status' => PlanStatus::Published->value,
        ]);
    }

    /** @test */
    public function 管理者は公開中プランをアーカイブに変更できる(): void
    {
        $publishedPlan = Plan::factory()->create(['status' => PlanStatus::Published]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.plans.archive', $publishedPlan));

        $response->assertRedirect(route('admin.plans.show', $publishedPlan));
        $this->assertDatabaseHas('plans', [
            'id' => $publishedPlan->id,
            'status' => PlanStatus::Archived->value,
        ]);
    }

    /** @test */
    public function 管理者はアーカイブプランを下書きに戻せる(): void
    {
        $archivedPlan = Plan::factory()->create(['status' => PlanStatus::Archived]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.plans.unarchive', $archivedPlan));

        $response->assertRedirect(route('admin.plans.show', $archivedPlan));
        $this->assertDatabaseHas('plans', [
            'id' => $archivedPlan->id,
            'status' => PlanStatus::Draft->value,
        ]);
    }

    /** @test */
    public function 公開中や受講生が紐づくプランは削除リクエストが403エラーになり_d_bに残る(): void
    {
        // 1. 公開中プランの削除不可テスト
        $publishedPlan = Plan::factory()->create(['status' => PlanStatus::Published]);
        $response1 = $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $publishedPlan));
        $response1->assertForbidden();
        $this->assertDatabaseHas('plans', ['id' => $publishedPlan->id]);

        // 2. 下書きだが受講生が紐づくプランの削除不可テスト
        $draftPlanWithUser = Plan::factory()->create(['status' => PlanStatus::Draft]);
        User::factory()->create(['role' => UserRole::Student, 'plan_id' => $draftPlanWithUser->id]);

        $response2 = $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $draftPlanWithUser));
        $response2->assertForbidden();
        $this->assertDatabaseHas('plans', ['id' => $draftPlanWithUser->id]);
    }

    /** @test */
    public function 受講生が未紐づきの指示書下書きプランは削除できる(): void
    {
        $draftPlan = Plan::factory()->create(['status' => PlanStatus::Draft]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.plans.destroy', $draftPlan));

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $draftPlan->id]);
    }

    /**
     * リクエストパラメータ生成ヘルパー
     */
    private function validParams(): array
    {
        return [
            'name' => 'テストプラン',
            'description' => '説明',
            'duration_days' => 30,
            'default_meeting_quota' => 4,
            'sort_order' => 0,
        ];
    }
}
