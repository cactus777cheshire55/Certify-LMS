<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaThreadTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $otherStudent;

    private User $graduatedStudent;

    private User $coach;

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

        // 別の受講生
        $this->otherStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        // 卒業生（アクセス拒否対象）
        $this->graduatedStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Graduated,
        ]);

        // コーチ
        $this->coach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        // 管理者
        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        // 公開中の資格
        $this->certification = Certification::factory()->create([
            'status' => CertificationStatus::Published,
        ]);

        // コーチを該当資格の担当として割り当てる（要件：コーチは担当資格のみ閲覧・回答可能）
        $this->certification->coaches()->attach($this->coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $this->admin->id,
            'assigned_at' => now(),
        ]);
    }

    /* =========================================================================
     * 1. 認可・アクセス制限のテスト（卒業生拒否・他人の投稿操作拒否・未ログイン）
     * ========================================================================= */

    /** @test */
    public function 卒業生は質問掲示板の機能にアクセスできず403エラーになる(): void
    {
        $thread = QaThread::factory()->create([
            'certification_id' => $this->certification->id,
        ]);

        // 一覧表示
        $this->actingAs($this->graduatedStudent)
            ->get(route('qa-board.index'))
            ->assertForbidden();

        // 作成画面
        $this->actingAs($this->graduatedStudent)
            ->get(route('qa-board.create'))
            ->assertForbidden();

        // 詳細表示
        $this->actingAs($this->graduatedStudent)
            ->get(route('qa-board.show', $thread))
            ->assertForbidden();

        // 新規投稿
        $this->actingAs($this->graduatedStudent)
            ->post(route('qa-board.store'), [
                'certification_id' => $this->certification->id,
                'title' => 'テスト質問',
                'body' => '本文テスト',
            ])
            ->assertForbidden();
    }

    /** @test */
    public function 未ログインユーザーはログイン画面へリダイレクトされる(): void
    {
        $this->get(route('qa-board.index'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function コーチは質問作成画面アクセスおよびスレッド投稿を行えず403エラーになる(): void
    {
        // GET /qa-board/create (コーチ不可)
        $this->actingAs($this->coach)
            ->get(route('qa-board.create'))
            ->assertForbidden();

        // POST /qa-board (コーチ不可)
        $this->actingAs($this->coach)
            ->post(route('qa-board.store'), [
                'certification_id' => $this->certification->id,
                'title' => 'コーチからの質問テスト',
                'body' => '本文',
            ])
            ->assertForbidden();
    }

    /** @test */
    public function 他人のスレッドおよび回答の編集・更新・削除・解決操作は403エラーになる(): void
    {
        $otherThread = QaThread::factory()->create([
            'user_id' => $this->otherStudent->id,
            'certification_id' => $this->certification->id,
        ]);

        $otherReply = QaReply::factory()->create([
            'qa_thread_id' => $otherThread->id,
            'user_id' => $this->otherStudent->id,
        ]);

        // 他人のスレッド編集・更新・削除・解決操作の拒否
        $this->actingAs($this->student)->get(route('qa-board.edit', $otherThread))->assertForbidden();
        $this->actingAs($this->student)->patch(route('qa-board.update', $otherThread), ['title' => '更新', 'body' => '本文'])->assertForbidden();
        $this->actingAs($this->student)->delete(route('qa-board.destroy', $otherThread))->assertForbidden();
        $this->actingAs($this->student)->post(route('qa-board.resolve', $otherThread))->assertForbidden();
        $this->actingAs($this->student)->post(route('qa-board.unresolve', $otherThread))->assertForbidden();

        // 他人の回答編集・更新・削除操作の拒否
        $this->actingAs($this->student)->get(route('qa-board.replies.edit', [$otherThread, $otherReply]))->assertForbidden();
        $this->actingAs($this->student)->patch(route('qa-board.replies.update', [$otherThread, $otherReply]), ['body' => '更新本文'])->assertForbidden();
        $this->actingAs($this->student)->delete(route('qa-board.replies.destroy', [$otherThread, $otherReply]))->assertForbidden();
    }

    /* =========================================================================
     * 2. 一般機能の正常系テスト（受講生・担当コーチ）
     * ========================================================================= */

    /** @test */
    public function 受講生および担当コーチは質問一覧と詳細を閲覧できる(): void
    {
        $thread = QaThread::factory()->create(['certification_id' => $this->certification->id]);

        // GET /qa-board
        $this->actingAs($this->student)->get(route('qa-board.index'))->assertOk();
        $this->actingAs($this->coach)->get(route('qa-board.index'))->assertOk();

        // GET /qa-board/{thread}
        $this->actingAs($this->student)->get(route('qa-board.show', $thread))->assertOk();
        $this->actingAs($this->coach)->get(route('qa-board.show', $thread))->assertOk();
    }

    /** @test */
    public function 受講生のみ質問作成画面表示および新規投稿ができる(): void
    {
        // GET /qa-board/create
        $this->actingAs($this->student)
            ->get(route('qa-board.create'))
            ->assertOk();

        // POST /qa-board
        $response = $this->actingAs($this->student)
            ->post(route('qa-board.store'), [
                'certification_id' => $this->certification->id,
                'title' => '質問タイトル',
                'body' => '質問本文',
            ]);

        $this->assertDatabaseHas('qa_threads', [
            'user_id' => $this->student->id,
            'title' => '質問タイトル',
        ]);

        $thread = QaThread::where('title', '質問タイトル')->first();
        $response->assertRedirect(route('qa-board.show', $thread));
    }

    /** @test */
    public function 投稿者本人はスレッドの編集・更新・削除・解決マーク変更ができる(): void
    {
        $thread = QaThread::factory()->create([
            'user_id' => $this->student->id,
            'certification_id' => $this->certification->id,
            'status' => QaThreadStatus::Unresolved,
        ]);

        // GET /qa-board/{thread}/edit
        $this->actingAs($this->student)->get(route('qa-board.edit', $thread))->assertOk();

        // PATCH /qa-board/{thread}
        $this->actingAs($this->student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '更新後タイトル',
                'body' => '更新後本文',
            ])
            ->assertRedirect(route('qa-board.show', $thread));

        // POST /qa-board/{thread}/resolve
        $this->actingAs($this->student)
            ->post(route('qa-board.resolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id, 'status' => QaThreadStatus::Resolved->value]);

        // DELETE /qa-board/{thread} (本人の削除)
        $this->actingAs($this->student)
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect(route('qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    /** @test */
    public function 受講生および担当コーチは回答投稿でき、投稿者本人は回答の編集・更新・削除ができる(): void
    {
        $thread = QaThread::factory()->create(['certification_id' => $this->certification->id]);

        // POST /qa-board/{thread}/replies (受講生・担当コーチ投稿可能)
        $this->actingAs($this->student)->post(route('qa-board.replies.store', $thread), ['body' => '受講生回答'])->assertRedirect();
        $this->actingAs($this->coach)->post(route('qa-board.replies.store', $thread), ['body' => 'コーチ回答'])->assertRedirect();

        $reply = QaReply::where('body', '受講生回答')->first();

        // GET /qa-board/{thread}/replies/{reply}/edit
        $this->actingAs($this->student)->get(route('qa-board.replies.edit', [$thread, $reply]))->assertOk();

        // PATCH /qa-board/{thread}/replies/{reply}
        $this->actingAs($this->student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => '更新された受講生回答'])
            ->assertRedirect(route('qa-board.show', $thread));

        // DELETE /qa-board/{thread}/replies/{reply}
        $this->actingAs($this->student)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    /* =========================================================================
     * 3. 管理者モデレーション機能テスト（admin/qa-board）
     * ========================================================================= */

    /** @test */
    public function 一般ユーザーは管理者用モデレーション_ap_iにアクセスできず403エラーになる(): void
    {
        $thread = QaThread::factory()->create(['certification_id' => $this->certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id]);

        // 受講生拒否
        $this->actingAs($this->student)->get(route('admin.qa-board.index'))->assertForbidden();
        $this->actingAs($this->student)->get(route('admin.qa-board.show', $thread))->assertForbidden();
        $this->actingAs($this->student)->delete(route('admin.qa-board.destroy', $thread))->assertForbidden();
        $this->actingAs($this->student)->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]))->assertForbidden();

        // コーチ拒否
        $this->actingAs($this->coach)->get(route('admin.qa-board.index'))->assertForbidden();
    }

    /** @test */
    public function 管理者は全質問の閲覧およびスレッド・回答のモデレーション削除ができる(): void
    {
        $thread = QaThread::factory()->create(['certification_id' => $this->certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id]);

        // GET /admin/qa-board
        $this->actingAs($this->admin)->get(route('admin.qa-board.index'))->assertOk();

        // GET /admin/qa-board/{thread}
        $this->actingAs($this->admin)->get(route('admin.qa-board.show', $thread))->assertOk();

        // DELETE /admin/qa-board/{thread}/replies/{reply}
        $this->actingAs($this->admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('admin.qa-board.show', $thread));

        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);

        // DELETE /admin/qa-board/{thread}
        $this->actingAs($this->admin)
            ->delete(route('admin.qa-board.destroy', $thread))
            ->assertRedirect(route('admin.qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }
}
