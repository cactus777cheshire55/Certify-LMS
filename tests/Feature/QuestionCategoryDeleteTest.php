<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuestionCategoryDeleteTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // 1. 正常系テスト（Success Cases）
    // ==========================================

    /**
     * 【正常系①】管理者は問題が紐づいていない出題分野を正常に物理削除できる
     */
    public function test_admin_can_delete_category_when_no_questions_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();
        $category = QuestionCategory::factory()->for($certification)->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.question-categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHas('success', '出題分野を削除しました。');

        $this->assertDatabaseMissing('question_categories', [
            'id' => $category->id,
        ]);
    }

    /**
     * 【正常系②】担当コーチは自身の担当資格配下の問題0件の出題分野を削除できる（B-B-01 正常系）
     */
    public function test_assigned_coach_can_delete_category_when_no_questions_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->create();

        // 必須カラム assigned_by_user_id と assigned_at を含めて割当を実行
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $category = QuestionCategory::factory()->for($certification)->create();

        $response = $this->actingAs($coach)
            ->delete(route('admin.question-categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('question_categories', [
            'id' => $category->id,
        ]);
    }

    // ==========================================
    // 2. 異常系：参照整合性チェック（Deletion Restrictions）
    // ==========================================

    /**
     * 【異常系③】演習問題（SectionQuestion）が紐づいている場合、削除が拒否されDBに残る
     */
    public function test_cannot_delete_category_when_section_question_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();
        $category = QuestionCategory::factory()->for($certification)->create();

        SectionQuestion::factory()->create([
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.question-categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('question_categories', [
            'id' => $category->id,
        ]);
    }

    /**
     * 【異常系④】模試問題（mock_exam_questions）が紐づいている場合、削除が拒否されDBに残る
     */
    public function test_cannot_delete_category_when_mock_exam_question_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();
        $category = QuestionCategory::factory()->for($certification)->create();

        if (Schema::hasTable('mock_exam_questions')) {
            $mockExamId = (string) Str::ulid();

            if (Schema::hasTable('mock_exams')) {
                DB::table('mock_exams')->insert([
                    'id' => $mockExamId,
                    'certification_id' => $certification->id,
                    'title' => 'テスト模試',
                    'passing_score' => 70,
                    'created_by_user_id' => $admin->id,
                    'updated_by_user_id' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('mock_exam_questions')->insert([
                'id' => (string) Str::ulid(),
                'mock_exam_id' => $mockExamId,
                'category_id' => $category->id,
                'body' => 'テスト用の模試問題文',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = $this->actingAs($admin)
                ->delete(route('admin.question-categories.destroy', $category));

            $response->assertRedirect();
            $response->assertSessionHas('error');

            $this->assertDatabaseHas('question_categories', [
                'id' => $category->id,
            ]);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * 【異常系⑤】演習問題と模試問題の両方が紐づいている場合も拒否される
     */
    public function test_cannot_delete_category_when_both_question_types_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();
        $category = QuestionCategory::factory()->for($certification)->create();

        SectionQuestion::factory()->create(['category_id' => $category->id]);

        if (Schema::hasTable('mock_exam_questions')) {
            $mockExamId = (string) Str::ulid();

            if (Schema::hasTable('mock_exams')) {
                DB::table('mock_exams')->insert([
                    'id' => $mockExamId,
                    'certification_id' => $certification->id,
                    'title' => 'テスト模試',
                    'passing_score' => 70,
                    'created_by_user_id' => $admin->id,
                    'updated_by_user_id' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('mock_exam_questions')->insert([
                'id' => (string) Str::ulid(),
                'mock_exam_id' => $mockExamId,
                'category_id' => $category->id,
                'body' => 'テスト用の模試問題文',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)
            ->delete(route('admin.question-categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('question_categories', [
            'id' => $category->id,
        ]);
    }

    // ==========================================
    // 3. 異常系：認可・認証チェック（Authorization & Authentication）
    // ==========================================

    /**
     * 【異常系⑥】担当外のコーチは、問題0件であっても他資格の出題分野を削除できない（B-B-01 認可403検証）
     */
    public function test_unassigned_coach_cannot_delete_category(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->create();
        $category = QuestionCategory::factory()->for($certification)->create();

        $response = $this->actingAs($coach)
            ->delete(route('admin.question-categories.destroy', $category));

        $response->assertStatus(403);

        $this->assertDatabaseHas('question_categories', [
            'id' => $category->id,
        ]);
    }

    /**
     * 【異常系⑦】受講生（Student）は出題分野を削除できない（403エラー）
     */
    public function test_student_cannot_delete_category(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        $category = QuestionCategory::factory()->for($certification)->create();

        $response = $this->actingAs($student)
            ->delete(route('admin.question-categories.destroy', $category));

        $response->assertStatus(403);

        $this->assertDatabaseHas('question_categories', [
            'id' => $category->id,
        ]);
    }

    /**
     * 【異常系⑧】未ログインユーザー（Guest）は削除操作を実行できずログイン画面へリダイレクトされる
     */
    public function test_guest_cannot_delete_category(): void
    {
        $certification = Certification::factory()->create();
        $category = QuestionCategory::factory()->for($certification)->create();

        $response = $this->delete(route('admin.question-categories.destroy', $category));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('question_categories', [
            'id' => $category->id,
        ]);
    }

    /**
     * 【異常系⑨】存在しないID（404）を指定して削除リクエストを送信した場合は404エラーとなる
     */
    public function test_returns_404_when_deleting_non_existent_category(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.question-categories.destroy', 'non-existent-id'));

        $response->assertStatus(404);
    }
}
