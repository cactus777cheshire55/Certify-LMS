<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $assignedCoach;
    private User $otherCoach;
    private User $admin;
    private User $student;
    private Enrollment $enrollment;
    private EnrollmentNote $assignedCoachNote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assignedCoach = User::factory()->create(['role' => UserRole::Coach]);
        $this->otherCoach = User::factory()->create(['role' => UserRole::Coach]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $cert = Certification::factory()->create(['status' => CertificationStatus::Published]);
        
        // 担当コーチの割り当て
        $cert->coaches()->attach($this->assignedCoach->id, [
            'assigned_by_user_id' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        $this->enrollment = Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'certification_id' => $cert->id,
        ]);

        $this->assignedCoachNote = EnrollmentNote::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'author_id' => $this->assignedCoach->id,
            'body' => '担当コーチが書いたメモ',
        ]);
    }

    /** @test */
    public function 担当コーチは担当受講生にメモを追加・編集・削除できる(): void
    {
        // 1. 追加 (POST)
        $this->actingAs($this->assignedCoach)
            ->post(route('enrollments.notes.store', $this->enrollment), [
                'body' => '新しい日常観察メモ',
            ])
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $this->enrollment->id,
            'author_id' => $this->assignedCoach->id,
            'body' => '新しい日常観察メモ',
        ]);

        // 2. 編集画面表示 (GET)
        $this->actingAs($this->assignedCoach)
            ->get(route('enrollment-notes.edit', $this->assignedCoachNote))
            ->assertOk()
            ->assertViewIs('enrollment-note.edit');

        // 3. 更新 (PATCH)
        $this->actingAs($this->assignedCoach)
            ->patch(route('enrollment-notes.update', $this->assignedCoachNote), [
                'body' => '更新後のメモ本文',
            ])
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertEquals('更新後のメモ本文', $this->assignedCoachNote->fresh()->body);

        // 4. 削除 (DELETE)
        $this->actingAs($this->assignedCoach)
            ->delete(route('enrollment-notes.destroy', $this->assignedCoachNote))
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $this->assignedCoachNote->id]);
    }

    /** @test */
    public function 担当外コーチはメモの作成・編集・削除を行えず403エラーになる(): void
    {
        $this->actingAs($this->otherCoach)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => '不正メモ'])
            ->assertForbidden();

        $this->actingAs($this->otherCoach)
            ->get(route('enrollment-notes.edit', $this->assignedCoachNote))
            ->assertForbidden();

        $this->actingAs($this->otherCoach)
            ->patch(route('enrollment-notes.update', $this->assignedCoachNote), ['body' => '不正更新'])
            ->assertForbidden();

        $this->actingAs($this->otherCoach)
            ->delete(route('enrollment-notes.destroy', $this->assignedCoachNote))
            ->assertForbidden();
    }

    /** @test */
    public function 管理者は全受講生のメモの作成・編集・削除が可能(): void
    {
        // 管理者の追加
        $this->actingAs($this->admin)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => '管理者メモ'])
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        // 管理者による他コーチメモの更新・削除
        $this->actingAs($this->admin)
            ->patch(route('enrollment-notes.update', $this->assignedCoachNote), ['body' => '管理者による修正'])
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertEquals('管理者による修正', $this->assignedCoachNote->fresh()->body);

        $this->actingAs($this->admin)
            ->delete(route('enrollment-notes.destroy', $this->assignedCoachNote))
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $this->assignedCoachNote->id]);
    }

    /** @test */
    public function 受講生本人はメモの閲覧および全エンドポイントの操作を行えずアクセス拒否される(): void
    {
        // 1. 詳細画面でメモカード自体が非表示になることを検証
        $this->actingAs($this->student)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertOk()
            ->assertDontSee('コーチメモ');

        // 2. 直リンク・API操作に対する 403 遮断検証
        $this->actingAs($this->student)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => '不正'])
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('enrollment-notes.edit', $this->assignedCoachNote))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->patch(route('enrollment-notes.update', $this->assignedCoachNote), ['body' => '不正'])
            ->assertForbidden();

        $this->actingAs($this->student)
            ->delete(route('enrollment-notes.destroy', $this->assignedCoachNote))
            ->assertForbidden();
    }
    
    /** @test */
    public function 同じ資格の担当コーチであっても他人が書いたメモは編集・削除できず403エラーになる(): void
    {
        // 1. 同じ資格に 2 人目のコーチ（coCoach）を割り当てる
        $coCoach = User::factory()->create(['role' => UserRole::Coach]);
        $this->enrollment->certification->coaches()->attach($coCoach->id, [
            'assigned_by_user_id' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        // 2. coCoach は担当コーチなので新規メモの投稿は可能
        $this->actingAs($coCoach)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => 'coCoachのメモ'])
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        // 3. しかし、assignedCoach が書いたメモの編集画面表示・更新・削除は拒否される (403)
        $this->actingAs($coCoach)
            ->get(route('enrollment-notes.edit', $this->assignedCoachNote))
            ->assertForbidden();

        $this->actingAs($coCoach)
            ->patch(route('enrollment-notes.update', $this->assignedCoachNote), ['body' => '他人のメモを不正書き換え'])
            ->assertForbidden();

        $this->actingAs($coCoach)
            ->delete(route('enrollment-notes.destroy', $this->assignedCoachNote))
            ->assertForbidden();
    }

    /** @test */
    public function メモ本文が空または2000文字を超える場合はバリデーションエラーになる(): void
    {
        // 1. 本文が空の場合
        $this->actingAs($this->assignedCoach)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => ''])
            ->assertSessionHasErrors(['body']);

        // 2. 本文が 2001 文字（2000文字超）の場合
        $longBody = str_repeat('あ', 2001);

        $this->actingAs($this->assignedCoach)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => $longBody])
            ->assertSessionHasErrors(['body']);
    }

    /** @test */
    public function 未ログインユーザーはメモ関連のエンドポイントにアクセスできずリダイレクトされる(): void
    {
        $this->get(route('enrollment-notes.edit', $this->assignedCoachNote))
            ->assertRedirect(route('login'));

        $this->post(route('enrollments.notes.store', $this->enrollment), ['body' => 'テスト'])
            ->assertRedirect(route('login'));
    }
}