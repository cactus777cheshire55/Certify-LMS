<?php

declare(strict_types=1);

namespace Tests\Feature\Http\ContentManagement;

use App\Enums\CertificationStatus;
use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $assignedCoach;
    private User $unassignedCoach;
    private User $student;
    private Certification $certificationX;
    private Part $partX;
    private Chapter $chapterX;
    private Section $sectionX;
    private QuestionCategory $categoryX;
    private SectionQuestion $questionX;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. 各種権限のユーザーを作成
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->assignedCoach = User::factory()->create(['role' => UserRole::Coach]);
        $this->unassignedCoach = User::factory()->create(['role' => UserRole::Coach]);
        $this->student = User::factory()->create(['role' => UserRole::Student]);

        // 2. 資格の作成
        $this->certificationX = Certification::factory()->create(['status' => CertificationStatus::Published]);

        // 3. assignedCoach を 資格 X の担当コーチとして割り当て
        $this->certificationX->coaches()->attach($this->assignedCoach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        // 4. 資格 X 配下のテストデータを準備
        $this->partX = Part::factory()->create([
            'certification_id' => $this->certificationX->id,
            'status' => ContentStatus::Draft,
        ]);

        $this->chapterX = Chapter::factory()->create([
            'part_id' => $this->partX->id,
            'status' => ContentStatus::Draft,
        ]);

        $this->sectionX = Section::factory()->create([
            'chapter_id' => $this->chapterX->id,
            'status' => ContentStatus::Draft,
        ]);

        $this->categoryX = QuestionCategory::factory()->create([
            'certification_id' => $this->certificationX->id,
        ]);

        $this->questionX = SectionQuestion::factory()->create([
            'section_id' => $this->sectionX->id,
            'category_id' => $this->categoryX->id,
            'status' => ContentStatus::Draft,
        ]);
    }

    /* =========================================================================
     * 1. 担当コーチ（Assigned Coach）の正常操作 & リダイレクト検証
     * ========================================================================= */

    /** @test */
    public function 担当コーチはPartの一覧・作成・詳細・更新・公開・下書き化・並び替え・削除が許可され正しくリダイレクトされる(): void
    {
        // 閲覧（200 OK）
        $this->actingAs($this->assignedCoach)
            ->get(route('admin.certifications.parts.index', $this->certificationX))
            ->assertOk();

        $this->actingAs($this->assignedCoach)
            ->get(route('admin.parts.show', $this->partX))
            ->assertOk();

        // 新規作成 ➔ 作成されたPartの詳細画面へ
        $response = $this->actingAs($this->assignedCoach)
            ->post(route('admin.certifications.parts.store', $this->certificationX), [
                'title' => '新規Part',
                'description' => '説明',
            ]);
        $createdPart = Part::where('title', '新規Part')->firstOrFail();
        $response->assertRedirectToRoute('admin.parts.show', $createdPart);

        // 更新 ➔ Part詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->patch(route('admin.parts.update', $this->partX), [
                'title' => '更新後のPart',
                'description' => '更新説明',
            ])
            ->assertRedirectToRoute('admin.parts.show', $this->partX);

        // 公開・下書き化 ➔ Part詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->post(route('admin.parts.publish', $this->partX))
            ->assertRedirectToRoute('admin.parts.show', $this->partX);

        $this->actingAs($this->assignedCoach)
            ->post(route('admin.parts.unpublish', $this->partX))
            ->assertRedirectToRoute('admin.parts.show', $this->partX);

        // 並び替え ➔ Part詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->patch(route('admin.certifications.parts.reorder', $this->certificationX), [
                'ids' => [$this->partX->id],
            ])
            ->assertRedirectToRoute('admin.parts.show', $this->partX);

        // 削除 ➔ 親資格のPart一覧画面へ
        $this->actingAs($this->assignedCoach)
            ->delete(route('admin.parts.destroy', $this->partX))
            ->assertRedirectToRoute('admin.certifications.parts.index', $this->certificationX);
    }

    /** @test */
    public function 担当コーチはChapterの詳細・更新・公開・下書き化・削除が許可され正しくリダイレクトされる(): void
    {
        $this->actingAs($this->assignedCoach)
            ->get(route('admin.chapters.show', $this->chapterX))
            ->assertOk();

        // 更新 ➔ Chapter詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->patch(route('admin.chapters.update', $this->chapterX), [
                'title' => '更新後のChapter',
            ])
            ->assertRedirectToRoute('admin.chapters.show', $this->chapterX);

        // 公開・下書き化 ➔ Chapter詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->post(route('admin.chapters.publish', $this->chapterX))
            ->assertRedirectToRoute('admin.chapters.show', $this->chapterX);

        $this->actingAs($this->assignedCoach)
            ->post(route('admin.chapters.unpublish', $this->chapterX))
            ->assertRedirectToRoute('admin.chapters.show', $this->chapterX);

        // 削除 ➔ 親Partの詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->delete(route('admin.chapters.destroy', $this->chapterX))
            ->assertRedirectToRoute('admin.parts.show', $this->partX);
    }

    /** @test */
    public function 担当コーチはSectionの詳細・更新・公開・下書き化・削除が許可され正しくリダイレクトされる(): void
    {
        $this->actingAs($this->assignedCoach)
            ->get(route('admin.sections.show', $this->sectionX))
            ->assertOk();

        // 更新 ➔ Section詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->patch(route('admin.sections.update', $this->sectionX), [
                'title' => '更新後のSection',
                'body' => '本文',
            ])
            ->assertRedirectToRoute('admin.sections.show', $this->sectionX);

        // 公開・下書き化 ➔ Section詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->post(route('admin.sections.publish', $this->sectionX))
            ->assertRedirectToRoute('admin.sections.show', $this->sectionX);

        $this->actingAs($this->assignedCoach)
            ->post(route('admin.sections.unpublish', $this->sectionX))
            ->assertRedirectToRoute('admin.sections.show', $this->sectionX);

        // 削除 ➔ 親Chapterの詳細画面へ
        $this->actingAs($this->assignedCoach)
            ->delete(route('admin.sections.destroy', $this->sectionX))
            ->assertRedirectToRoute('admin.chapters.show', $this->chapterX);
    }

    /** @test */
    public function 担当コーチは出題分野マスタの作成・更新・削除が許可され正しくリダイレクトされる(): void
    {
        // 一覧閲覧
        $this->actingAs($this->assignedCoach)
            ->get(route('admin.certifications.question-categories.index', $this->certificationX))
            ->assertOk();

        // 作成 ➔ 出題分野一覧へ
        $this->actingAs($this->assignedCoach)
            ->post(route('admin.certifications.question-categories.store', $this->certificationX), [
                'name' => '新分野',
                'slug' => 'new-cat',
                'sort_order' => 1,
            ])
            ->assertRedirectToRoute('admin.certifications.question-categories.index', $this->certificationX);

        // 更新 ➔ 出題分野一覧へ
        $this->actingAs($this->assignedCoach)
            ->patch(route('admin.question-categories.update', $this->categoryX), [
                'name' => '更新分野',
                'slug' => 'updated-cat',
                'sort_order' => 2,
            ])
            ->assertRedirectToRoute('admin.certifications.question-categories.index', $this->certificationX);

        // 削除 ➔ 出題分野一覧へ
        $this->actingAs($this->assignedCoach)
            ->delete(route('admin.question-categories.destroy', $this->categoryX))
            ->assertRedirectToRoute('admin.certifications.question-categories.index', $this->certificationX);
    }

    /* =========================================================================
     * 2. 担当外コーチ（Unassigned Coach）のアクセス遮断テスト（403 Forbidden）
     * ========================================================================= */

    /** @test */
    public function 担当外コーチが資格配下の教材管理にアクセスすると403エラーが発生する(): void
    {
        $this->actingAs($this->unassignedCoach)
            ->get(route('admin.certifications.parts.index', $this->certificationX))
            ->assertForbidden();

        $this->actingAs($this->unassignedCoach)
            ->get(route('admin.parts.show', $this->partX))
            ->assertForbidden();

        $this->actingAs($this->unassignedCoach)
            ->get(route('admin.chapters.show', $this->chapterX))
            ->assertForbidden();

        $this->actingAs($this->unassignedCoach)
            ->get(route('admin.sections.show', $this->sectionX))
            ->assertForbidden();

        $this->actingAs($this->unassignedCoach)
            ->get(route('admin.certifications.question-categories.index', $this->certificationX))
            ->assertForbidden();
    }

    /* =========================================================================
     * 3. 受講生（Student）のアクセス遮断テスト（403 Forbidden）
     * ========================================================================= */

    /** @test */
    public function 受講生が教材管理にアクセスすると403エラーが発生する(): void
    {
        $this->actingAs($this->student)
            ->get(route('admin.certifications.parts.index', $this->certificationX))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('admin.parts.show', $this->partX))
            ->assertForbidden();
    }

    /* =========================================================================
     * 4. 管理者（Admin）のアクセス許可テスト（200 OK）
     * ========================================================================= */

    /** @test */
    public function 管理者は全資格の教材管理画面へアクセスできる(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.certifications.parts.index', $this->certificationX))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.parts.show', $this->partX))
            ->assertOk();
    }
}