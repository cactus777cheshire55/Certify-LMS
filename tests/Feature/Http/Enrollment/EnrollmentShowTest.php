<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\Learning\ProgressSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_enrollment_show_with_progress_summary(): void
    {
        // 1. 管理者と受講生・受講登録・教材データの準備
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();

        $part = Part::factory()->for($cert)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();
        $section = Section::factory()->for($chapter)->published()->create();

        SectionProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
        ]);

        // 2. 管理者でログインして詳細画面を表示
        $response = $this->actingAs($admin)
            ->get(route('enrollments.show', $enrollment));

        // 3. レスポンスと View 変数の検証
        $response->assertOk();
        $response->assertViewIs('enrollment.show');
        $response->assertViewHas('progress', function ($progress) {
            return $progress instanceof ProgressSummary
                && $progress->sectionsCompleted === 1
                && $progress->sectionsTotal === 1;
        });
    }

    public function test_student_view_does_not_calculate_staff_progress_summary(): void
    {
        // 受講生本人が開いた場合は管理者・コーチ向けプログレスカード用集計を走らせない仕様の検証
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();

        $response = $this->actingAs($student)
            ->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertViewHas('progress', null);
    }
}
