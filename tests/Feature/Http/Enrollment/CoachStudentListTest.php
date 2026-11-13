<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CoachStudentListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function コーチの受講生一覧には担当資格の受講生のみが表示され担当外資格の受講生は表示されない(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        // 担当資格と受講生
        $assignedCert = Certification::factory()->published()->create(['name' => '担当資格PHP']);
        $studentA = User::factory()->student()->inProgress()->create(['name' => '担当受講生A']);
        Enrollment::factory()->for($studentA, 'user')->for($assignedCert)->learning()->create();

        // 担当資格に割当
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        // 担当外資格と受講生
        $unassignedCert = Certification::factory()->published()->create(['name' => '担当外資格Ruby']);
        $studentB = User::factory()->student()->inProgress()->create(['name' => '担当外受講生B']);
        Enrollment::factory()->for($studentB, 'user')->for($unassignedCert)->learning()->create();

        // 実行・検証
        $response = $this->actingAs($coach)->get(route('enrollments.index'));

        $response->assertOk();
        $response->assertSee('担当受講生A');
        $response->assertSee('担当資格PHP');
        $response->assertDontSee('担当外受講生B');
        $response->assertDontSee('担当外資格Ruby');
    }
}