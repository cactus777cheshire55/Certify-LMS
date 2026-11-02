<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaThreadTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $otherStudent;

    private User $graduatedStudent;

    private User $admin;

    private Certification $certification;

    protected function setUp(): void
    {
        parent::setUp();

        // 受講中の受講生
        $this->student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $this->otherStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        // 卒業生
        $this->graduatedStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Graduated,
        ]);

        // 管理者
        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        // 公開中の資格
        $this->certification = Certification::factory()->create([
            'status' => CertificationStatus::Published,
        ]);
    }

    /** @test */
    public function 受講生は質問一覧を閲覧できる(): void
    {
        $response = $this->actingAs($this->student)->get(route('qa-board.index'));

        $response->assertOk()
            ->assertViewIs('qa-thread.index');
    }

    /** @test */
    public function 受講生は新規質問を投稿できる(): void
    {
        $postData = [
            'certification_id' => $this->certification->id,
            'title' => 'テスト質問タイトル',
            'body' => 'テスト質問の本文です。',
        ];

        $response = $this->actingAs($this->student)
            ->post(route('qa-board.store'), $postData);

        $this->assertDatabaseHas('qa_threads', [
            'user_id' => $this->student->id,
            'certification_id' => $this->certification->id,
            'title' => 'テスト質問タイトル',
            'status' => QaThreadStatus::Unresolved->value,
        ]);

        $thread = QaThread::where('title', 'テスト質問タイトル')->first();
        $response->assertRedirect(route('qa-board.show', $thread));
    }

    /** @test */
    public function 他人の質問は編集できない(): void
    {
        $thread = QaThread::factory()->create([
            'user_id' => $this->otherStudent->id,
            'certification_id' => $this->certification->id,
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('qa-board.edit', $thread));

        $response->assertForbidden();
    }

    /** @test */
    public function 投稿者本人は解決済にマークできる(): void
    {
        $thread = QaThread::factory()->create([
            'user_id' => $this->student->id,
            'certification_id' => $this->certification->id,
            'status' => QaThreadStatus::Unresolved,
        ]);

        $response = $this->actingAs($this->student)
            ->post(route('qa-board.resolve', $thread));

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => QaThreadStatus::Resolved->value,
        ]);
    }

    /** @test */
    public function 管理者はモデレーション削除できる(): void
    {
        $thread = QaThread::factory()->create([
            'user_id' => $this->student->id,
            'certification_id' => $this->certification->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.qa-board.destroy', $thread));

        $response->assertRedirect(route('admin.qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    /** @test */
    public function 卒業生は質問一覧にアクセスできない(): void
    {
        $graduatedStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Graduated,
        ]);

        $response = $this->actingAs($graduatedStudent)
            ->get(route('qa-board.index'));

        // 403 (errors/403.blade.php) が返ることを検証
        $response->assertForbidden();
    }

    /** @test *//
    public function 未ログインユーザーは質問一覧にアクセスできずログイン画面へリダイレクトされる(): void
    {
        $response = $this->get(route('qa-board.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function 受講生およびコーチは回答を投稿できる(): void
    {
        $thread = QaThread::factory()->create([
            'certification_id' => $this->certification->id,
        ]);

        $response = $this->actingAs($this->student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'テスト回答本文です。',
            ]);

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $this->student->id,
            'body' => 'テスト回答本文です。',
        ]);
    }

    /** @test */
    public function タイトルが空の場合はバリデーションエラーになる(): void
    {
        $response = $this->actingAs($this->student)
            ->post(route('qa-board.store'), [
                'certification_id' => $this->certification->id,
                'title' => '',
                'body' => '本文のみ',
            ]);

        $response->assertSessionHasErrors(['title']);
    }
}
