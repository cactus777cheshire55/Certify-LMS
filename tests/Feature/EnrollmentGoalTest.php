<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentGoalTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $otherStudent;

    private User $coach;

    private User $admin;

    private Enrollment $enrollment;

    private EnrollmentGoal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. 受講生（目標の所有者）
        $this->student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        // 2. 他の受講生（認可拒否の検証用）
        $this->otherStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        // 3. コーチおよび管理者（閲覧のみ・操作拒否の検証用）
        $this->coach = User::factory()->create(['role' => UserRole::Coach]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        // 4. 受講登録データと初期目標データの作成
        $cert = Certification::factory()->create(['status' => CertificationStatus::Published]);
        $this->enrollment = Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'certification_id' => $cert->id,
        ]);

        $this->goal = EnrollmentGoal::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'title' => 'テスト目標',
            'description' => '詳細テキスト',
        ]);
    }

    /* =========================================================================
     * 1. 認可テスト（他受講生・コーチ・管理者・未ログインの変更操作を遮断）
     * ========================================================================= */

    /** @test */
    public function 他の受講生は全6エンドポイントの操作を行えず403エラーになる(): void
    {
        // 1. POST /enrollments/{enrollment}/goals (新規追加)
        $this->actingAs($this->otherStudent)
            ->post(route('enrollments.goals.store', $this->enrollment), ['title' => '不正追加'])
            ->assertForbidden();

        // 2. GET /enrollment-goals/{goal}/edit (編集画面表示)
        $this->actingAs($this->otherStudent)
            ->get(route('enrollment-goals.edit', $this->goal))
            ->assertForbidden();

        // 3. PATCH /enrollment-goals/{goal} (基本情報更新)
        $this->actingAs($this->otherStudent)
            ->patch(route('enrollment-goals.update', $this->goal), ['title' => '不正更新'])
            ->assertForbidden();

        // 4. DELETE /enrollment-goals/{goal} (物理削除)
        $this->actingAs($this->otherStudent)
            ->delete(route('enrollment-goals.destroy', $this->goal))
            ->assertForbidden();

        // 5. POST /enrollment-goals/{goal}/achieve (達成マーク付与)
        $this->actingAs($this->otherStudent)
            ->post(route('enrollment-goals.markAchieved', $this->goal))
            ->assertForbidden();

        // 6. DELETE /enrollment-goals/{goal}/achieve (達成マーク解除)
        $this->actingAs($this->otherStudent)
            ->delete(route('enrollment-goals.unmarkAchieved', $this->goal))
            ->assertForbidden();
    }

    /** @test */
    public function コーチや管理者は全6エンドポイントの変更操作を行えず403エラーになる(): void
    {
        // コーチによる各操作の拒否 (403)
        $this->actingAs($this->coach)
            ->post(route('enrollments.goals.store', $this->enrollment), ['title' => 'コーチの追加'])
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->get(route('enrollment-goals.edit', $this->goal))
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->patch(route('enrollment-goals.update', $this->goal), ['title' => 'コーチの更新'])
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->delete(route('enrollment-goals.destroy', $this->goal))
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->post(route('enrollment-goals.markAchieved', $this->goal))
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->delete(route('enrollment-goals.unmarkAchieved', $this->goal))
            ->assertForbidden();

        // 管理者による変更操作の拒否 (403)
        $this->actingAs($this->admin)
            ->patch(route('enrollment-goals.update', $this->goal), ['title' => '管理者の更新'])
            ->assertForbidden();
    }

    /** @test */
    public function 未ログインユーザーはログイン画面へリダイレクトされる(): void
    {
        $this->get(route('enrollment-goals.edit', $this->goal))
            ->assertRedirect(route('login'));
    }

    /* =========================================================================
     * 2. 正常系機能テスト（受講生本人による全6エンドポイントの正常操作）
     * ========================================================================= */

    /** @test */
    public function 受講生本人はpost_goalsで個人目標を新規追加できる(): void
    {
        $response = $this->actingAs($this->student)
            ->post(route('enrollments.goals.store', $this->enrollment), [
                'title' => '過去問5年分完了',
                'target_date' => now()->addMonth()->format('Y-m-d'),
                'description' => '1日1年分のペースで進める',
            ]);

        $response->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $this->enrollment->id,
            'title' => '過去問5年分完了',
        ]);
    }

    /** @test */
    public function 受講生本人はget_editで編集画面を表示できる(): void
    {
        $response = $this->actingAs($this->student)
            ->get(route('enrollment-goals.edit', $this->goal));

        $response->assertOk()
            ->assertViewIs('enrollment-goal.edit');
    }

    /** @test */
    public function 受講生本人はpatch_goalで基本情報を更新できる(): void
    {
        $response = $this->actingAs($this->student)
            ->patch(route('enrollment-goals.update', $this->goal), [
                'title' => '更新後の目標タイトル',
                'description' => '更新後の詳細テキスト',
                'target_date' => now()->addWeeks(2)->format('Y-m-d'),
            ]);

        $response->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $this->goal->id,
            'title' => '更新後の目標タイトル',
            'description' => '更新後の詳細テキスト',
        ]);
    }

    /** @test */
    public function 受講生本人はpost_achieveおよびdelete_achieveで達成マークの切り替えができる(): void
    {
        // 1. POST /enrollment-goals/{goal}/achieve (達成マーク付与)
        $responseMark = $this->actingAs($this->student)
            ->post(route('enrollment-goals.markAchieved', $this->goal));

        $responseMark->assertRedirect(route('enrollments.show', $this->enrollment));
        $this->assertNotNull($this->goal->fresh()->achieved_at);

        // 2. DELETE /enrollment-goals/{goal}/achieve (達成マーク解除)
        $responseUnmark = $this->actingAs($this->student)
            ->delete(route('enrollment-goals.unmarkAchieved', $this->goal));

        $responseUnmark->assertRedirect(route('enrollments.show', $this->enrollment));
        $this->assertNull($this->goal->fresh()->achieved_at);
    }

    /** @test */
    public function 受講生本人はdelete_goalで目標を物理削除できる(): void
    {
        $response = $this->actingAs($this->student)
            ->delete(route('enrollment-goals.destroy', $this->goal));

        $response->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $this->goal->id,
        ]);
    }
}
