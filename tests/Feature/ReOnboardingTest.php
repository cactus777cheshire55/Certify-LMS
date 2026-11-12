<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidInvitationTokenException;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\Services\InvitationTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class ReOnboardingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 一度オンボーディングを完了した招待_ur_lは使い回せない(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $plan = Plan::factory()->create();

        // 招待中ユーザーおよび招待データを作成
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Invited,
            'plan_id' => $plan->id,
        ]);

        $invitation = Invitation::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => UserRole::Student,
            'invited_by_user_id' => $admin->id,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
        ]);

        $tokenService = new InvitationTokenService;
        $showUrl = $tokenService->generateUrl($invitation);

        // 1回目のオンボーディング（正常完了）
        $storeUrl = URL::temporarySignedRoute(
            'onboarding.store',
            $invitation->expires_at,
            ['invitation' => $invitation->id]
        );

        $response = $this->post($storeUrl, [
            'name' => 'テスト太郎',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status' => InvitationStatus::Accepted->value,
        ]);

        // ログアウトして再度同じ招待 URL にアクセスを試みる
        $this->post(route('logout'));

        // 2回目の GET アクセス（招待無効画面 auth.invitation-invalid が表示されること）
        $secondShowResponse = $this->get($showUrl);
        $secondShowResponse->assertOk();
        $secondShowResponse->assertViewIs('auth.invitation-invalid');

        // 2回目の POST アクセス（InvalidInvitationTokenException が発生し拒否されること）
        $this->withoutExceptionHandling();
        $this->expectException(InvalidInvitationTokenException::class);

        $this->post($storeUrl, [
            'name' => '乗っ取り太郎',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
    }
}
