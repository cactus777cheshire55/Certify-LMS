<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Learning;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BrowseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 受講登録していない資格のPart詳細へ直リンクアクセスした場合は403が返る(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => 'in_progress',
        ]);

        $unregisteredCert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($unregisteredCert)->published()->create();

        $response = $this->actingAs($student)->get(route('learning.parts.show', $part));

        $response->assertForbidden();
    }

    /** @test */
    public function 受講登録していない資格のChapter詳細へ直リンクアクセスした場合は403が返る(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => 'in_progress',
        ]);

        $unregisteredCert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($unregisteredCert)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();

        $response = $this->actingAs($student)->get(route('learning.chapters.show', $chapter));

        $response->assertForbidden();
    }

    /** @test */
    public function 受講登録していない資格のSection詳細へ直リンクアクセスした場合は403が返る(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => 'in_progress',
        ]);

        $unregisteredCert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($unregisteredCert)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();
        $section = Section::factory()->forChapter($chapter)->published()->create();

        $response = $this->actingAs($student)->get(route('learning.sections.show', $section));

        $response->assertForbidden();
    }

    /** @test */
    public function 受講登録済みの資格配下の教材詳細へは正常にアクセスできる(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => 'in_progress',
        ]);

        $registeredCert = Certification::factory()->published()->create();
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $registeredCert->id,
            'status' => EnrollmentStatus::Learning,
        ]);

        $part = Part::factory()->forCertification($registeredCert)->published()->create();

        $response = $this->actingAs($student)->get(route('learning.parts.show', $part));

        $response->assertOk();
    }
}