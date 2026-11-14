<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Learning;

use App\Enums\CertificationStatus;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ArchivedCertificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 公開停止された資格の_part_chapter_sectionにアクセスすると404エラーになる(): void
    {
        $student = User::factory()->create();

        // 1. 公開中状態の資格・教材データの作成
        $certification = Certification::factory()->create([
            'status' => CertificationStatus::Published,
        ]);

        $part = Part::factory()->create([
            'certification_id' => $certification->id,
            'status' => ContentStatus::Published,
        ]);

        $chapter = Chapter::factory()->create([
            'part_id' => $part->id,
            'status' => ContentStatus::Published,
        ]);

        $section = Section::factory()->create([
            'chapter_id' => $chapter->id,
            'status' => ContentStatus::Published,
        ]);

        // 受講生を受講登録状態にする
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => EnrollmentStatus::Learning,
        ]);

        // 2. 資格を公開停止（Archived）に変更
        $certification->update(['status' => CertificationStatus::Archived]);

        // 3. 各教材詳細エンドポイントへのアクセス検証（すべて 404 Not Found）
        $this->actingAs($student)
            ->get(route('learning.parts.show', $part))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('learning.chapters.show', $chapter))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('learning.sections.show', $section))
            ->assertNotFound();
    }
}
