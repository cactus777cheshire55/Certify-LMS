<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $coach;
    private User $admin;
    private User $graduatedStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $this->coach = User::factory()->create([
            'role' => UserRole::Coach,
            'status' => UserStatus::InProgress,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::InProgress,
        ]);

        $this->graduatedStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Graduated,
        ]);
    }

    /* =========================================================================
     * 1. GET /settings/profile （全ロール本人による画面表示 ＆ 未ログイン制御）
     * ========================================================================= */

    /** @test */
    public function 未ログインユーザーは設定画面にアクセスできずログイン画面へリダイレクトされる(): void
    {
        $this->get(route('settings.profile.edit'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function 全ロール（受講生・コーチ・管理者・修了済受講生）が自分の設定画面を表示できる(): void
    {
        $roles = [$this->student, $this->coach, $this->admin, $this->graduatedStudent];

        foreach ($roles as $user) {
            $this->actingAs($user)
                ->get(route('settings.profile.edit'))
                ->assertOk()
                ->assertViewIs('settings.profile');
        }
    }

    /* =========================================================================
     * 2. PATCH /settings/profile （全ロール本人による基本情報更新）
     * ========================================================================= */

    /** @test */
    public function 全ロールが自分の氏名と自己紹介を更新できる(): void
    {
        $roles = [$this->student, $this->coach, $this->admin, $this->graduatedStudent];

        foreach ($roles as $user) {
            $response = $this->actingAs($user)
                ->patch(route('settings.profile.update'), [
                    'name' => "更新後_{$user->role->value}",
                    'bio' => '新しい自己紹介文です。',
                ]);

            $response->assertRedirect(route('settings.profile.edit'));

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'name' => "更新後_{$user->role->value}",
                'bio' => '新しい自己紹介文です。',
            ]);
        }
    }

    /** @test */
    public function コーチのみ固定面談URLを更新でき受講生や管理者の入力は無視される(): void
    {
        // 1. コーチは meeting_url を更新できる
        $this->actingAs($this->coach)
            ->patch(route('settings.profile.update'), [
                'name' => $this->coach->name,
                'meeting_url' => 'https://zoom.us', 
            ])
            ->assertRedirect(route('settings.profile.edit'));

        $this->assertEquals('https://zoom.us', $this->coach->fresh()->meeting_url); 

        // 2. 受講生が meeting_url を送信しても更新されない（無視される）
        $this->actingAs($this->student)
            ->patch(route('settings.profile.update'), [
                'name' => $this->student->name,
                'meeting_url' => 'https://zoom.us', 
            ])
            ->assertRedirect(route('settings.profile.edit'));

        $this->assertNull($this->student->fresh()->meeting_url);
    }

    /* =========================================================================
     * 3. POST /settings/avatar & DELETE /settings/avatar （アバター管理）
     * ========================================================================= */

    /** @test */
    public function 全ロールがアバター画像をアップロードおよび削除できる(): void
    {
        Storage::fake('public');
        $roles = [$this->student, $this->coach, $this->admin, $this->graduatedStudent];

        foreach ($roles as $user) {
            $file = UploadedFile::fake()->image("avatar_{$user->id}.png", 200, 200);

            // POST /settings/avatar
            $this->actingAs($user)
                ->post(route('settings.avatar.store'), ['avatar' => $file])
                ->assertRedirect(route('settings.profile.edit'));

            $this->assertNotNull($user->fresh()->avatar_url);

            // DELETE /settings/avatar
            $this->actingAs($user)
                ->delete(route('settings.avatar.destroy'))
                ->assertRedirect(route('settings.profile.edit'));

            $this->assertNull($user->fresh()->avatar_url);
        }
    }

    /** @test */
    public function 画像以外のファイルや2MBを超える画像アップロードはバリデーションエラーになる(): void
    {
        Storage::fake('public');

        // テキストファイル
        $textFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($this->student)
            ->post(route('settings.avatar.store'), ['avatar' => $textFile])
            ->assertSessionHasErrors(['avatar']);

        // 2MB (2048KB) を超える画像 (2049KB)
        $largeImage = UploadedFile::fake()->image('large.jpg')->size(2049);

        $this->actingAs($this->student)
            ->post(route('settings.avatar.store'), ['avatar' => $largeImage])
            ->assertSessionHasErrors(['avatar']);
    }

    /* =========================================================================
     * 4. PUT /settings/password （パスワード変更）
     * ========================================================================= */

    /** @test */
    public function 全ロールが正しい現在のパスワードを入力してパスワードを変更できる(): void
    {
        $roles = [$this->student, $this->coach, $this->admin, $this->graduatedStudent];

        foreach ($roles as $user) {
            $user->update(['password' => Hash::make('old-password-123')]);

            $response = $this->actingAs($user)
                ->put(route('settings.password.update'), [
                    'current_password' => 'old-password-123',
                    'password' => 'new-password-456',
                    'password_confirmation' => 'new-password-456',
                ]);

            $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
            $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
        }
    }

    /** @test */
    public function 誤った現在のパスワードや確認用不一致の場合はパスワード変更が失敗する(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        // 誤った現在パスワード
        $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        // 新パスワードの確認用不一致
        $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'correct-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'mismatch-password',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['password']);
    }
}