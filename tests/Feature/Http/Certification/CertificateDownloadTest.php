<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certification;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /certificates/{certificate}/download の認可 / 配信を検証する。
 */
class CertificateDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Certification $certification;

    private User $student;

    private Certificate $certificate;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->certification = Certification::factory()->published()->create();
        $this->student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($this->student)->for($this->certification)->passed()->create();
        $this->certificate = Certificate::factory()->forEnrollment($enrollment)->create();

        Storage::disk('private')->put($this->certificate->pdf_path, '%PDF-1.4 dummy');
    }

    public function test_student_can_download_own_certificate_as_attachment(): void
    {
        $response = $this->actingAs($this->student)
            ->get(route('certificates.download', $this->certificate));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload("certificate_{$this->certificate->id}.pdf");
        $this->assertSame('%PDF-1.4 dummy', $response->streamedContent());
    }

    public function test_graduated_student_can_download_own_certificate(): void
    {
        $this->student->update(['status' => 'graduated']);

        $this->actingAs($this->student->fresh())
            ->get(route('certificates.download', $this->certificate))
            ->assertOk();
    }

    public function test_student_cannot_download_others_certificate(): void
    {
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($other)
            ->get(route('certificates.download', $this->certificate))
            ->assertForbidden();
    }

    public function test_assigned_coach_can_download(): void
    {
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach, $this->certification);

        $this->actingAs($coach)
            ->get(route('certificates.download', $this->certificate))
            ->assertOk();
    }

    public function test_unassigned_coach_cannot_download(): void
    {
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach, Certification::factory()->published()->create());

        $this->actingAs($coach)
            ->get(route('certificates.download', $this->certificate))
            ->assertForbidden();
    }

    public function test_coach_whose_assignment_ended_cannot_download(): void
    {
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach, $this->certification, unassignedAt: now());

        $this->actingAs($coach)
            ->get(route('certificates.download', $this->certificate))
            ->assertForbidden();
    }

    public function test_admin_can_download_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('certificates.download', $this->certificate))
            ->assertOk();
    }

    public function test_returns_404_when_pdf_file_is_missing(): void
    {
        Storage::disk('private')->delete($this->certificate->pdf_path);

        $this->actingAs($this->student)
            ->get(route('certificates.download', $this->certificate))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('certificates.download', $this->certificate))
            ->assertRedirect('/login');
    }

    private function assignCoach(User $coach, Certification $certification, mixed $unassignedAt = null): void
    {
        CertificationCoachAssignment::query()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => $unassignedAt,
        ]);
    }
}
