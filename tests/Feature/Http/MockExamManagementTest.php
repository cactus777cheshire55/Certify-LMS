<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Certification;
use App\Models\MockExam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MockExamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Certification $certification;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->certification = Certification::factory()->create();
    }

    /** @test */
    public function 模試作成時に合格点が1から100の範囲外の場合はバリデーションエラーになる(): void
    {
        // 100% を超える数値（150%）で作成を試みる
        $response = $this->actingAs($this->admin)
            ->post(route('admin.mock-exams.store'), [
                'certification_id' => $this->certification->id,
                'title' => 'テスト模試',
                'description' => '模試の説明文',
                'order' => 1,
                'passing_score' => 150,
            ]);

        $response->assertSessionHasErrors(['passing_score']);

        // 0% で作成を試みる
        $response0 = $this->actingAs($this->admin)
            ->post(route('admin.mock-exams.store'), [
                'certification_id' => $this->certification->id,
                'title' => 'テスト模試',
                'description' => '模試の説明文',
                'order' => 1,
                'passing_score' => 0,
            ]);

        $response0->assertSessionHasErrors(['passing_score']);
    }

    /** @test */
    public function 模試更新時に合格点が1から100の範囲外の場合はバリデーションエラーになる(): void
    {
        $mockExam = MockExam::factory()->create([
            'certification_id' => $this->certification->id,
            'passing_score' => 60,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.mock-exams.update', $mockExam), [
                'title' => $mockExam->title,
                'description' => $mockExam->description,
                'order' => $mockExam->order,
                'passing_score' => 101,
            ]);

        $response->assertSessionHasErrors(['passing_score']);
    }

    /** @test */
    public function 模試作成時に1から100の正常値の合格点であれば保存できる(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.mock-exams.store'), [
                'certification_id' => $this->certification->id,
                'title' => '正常な模試',
                'description' => '説明',
                'order' => 1,
                'passing_score' => 80,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mock_exams', [
            'title' => '正常な模試',
            'passing_score' => 80,
        ]);
    }
}