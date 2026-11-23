<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\Learning\ProgressSummary;
use App\Services\LearningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private LearningProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LearningProgressService::class);
    }

    public function test_calculate_returns_correct_progress_summary(): void
    {
        // 1. テストデータのセットアップ
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();

        // 階層データ作成: Part(1) -> Chapter(2) -> 各ChapterにSection(2つずつ = 合計4つ)
        $part = Part::factory()->for($cert)->published()->create();

        $chapter1 = Chapter::factory()->for($part)->published()->create();
        $section1_1 = Section::factory()->for($chapter1)->published()->create();
        $section1_2 = Section::factory()->for($chapter1)->published()->create();

        $chapter2 = Chapter::factory()->for($part)->published()->create();
        $section2_1 = Section::factory()->for($chapter2)->published()->create();
        $section2_2 = Section::factory()->for($chapter2)->published()->create();

        // 進捗マーク: Chapter1 配下の Section 2つを全て読了済みにする (Chapter1完了 & Part未完了)
        SectionProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'section_id' => $section1_1->id,
        ]);
        SectionProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'section_id' => $section1_2->id,
        ]);

        // 2. Service実行
        $summary = $this->service->calculate($enrollment);

        // 3. 検証 (Assert)
        $this->assertInstanceOf(ProgressSummary::class, $summary);

        // Section の検証 (4問中 2問完了 = 50%)
        $this->assertSame(4, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertSame(0.5, $summary->sectionCompletionRatio);

        // Chapter の検証 (2章中 1章全完了 = 50%)
        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(0.5, $summary->chapterCompletionRatio);

        // Part の検証 (1Part中 0Part完了 = 0%)
        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        // 全体進捗率は Section 単位
        $this->assertSame(0.5, $summary->overallCompletionRatio);
    }

    public function test_calculate_ignores_draft_contents(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();

        $part = Part::factory()->for($cert)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();

        // 公開済み 1件、下書き 1件
        $publishedSection = Section::factory()->for($chapter)->published()->create();
        Section::factory()->for($chapter)->create(['status' => ContentStatus::Draft->value]);

        SectionProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'section_id' => $publishedSection->id,
        ]);

        // Act
        $summary = $this->service->calculate($enrollment);

        // Assert: 下書きコンテンツが集計対象から除外されていること
        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(1.0, $summary->sectionCompletionRatio);
        $this->assertSame(1, $summary->chaptersCompleted); // 配下の公開Sectionが全完了なのでChapter完了
    }

    public function test_calculate_handles_zero_sections_without_division_by_zero(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();

        // Section を持たない Part / Chapter
        $part = Part::factory()->for($cert)->published()->create();
        Chapter::factory()->for($part)->published()->create();

        $summary = $this->service->calculate($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }
}
