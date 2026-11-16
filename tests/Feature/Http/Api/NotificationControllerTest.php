<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_未ログインユーザーは通知_ap_iにアクセスできない(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertUnauthorized();
    }

    public function test_認証ユーザーが自身の通知一覧を取得できる(): void
    {
        $user = User::factory()->student()->create();
        Sanctum::actingAs($user);

        // 通知データの作成
        $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'data' => ['title' => 'テスト通知', 'message' => '本文テスト', 'url' => '/dashboard'],
            'read_at' => null,
        ]);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'notifications' => [
                    '*' => ['id', 'title', 'message', 'url', 'read_at', 'is_unread', 'created_at_human'],
                ],
                'unread_count',
            ])
            ->assertJsonPath('unread_count', 1);
    }

    public function test_他人の通知に対して既読化を呼び出すと403エラーになる(): void
    {
        $user = User::factory()->student()->create();
        $otherUser = User::factory()->student()->create();

        Sanctum::actingAs($user);

        $otherNotification = $otherUser->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'data' => ['title' => '他人宛て通知'],
            'read_at' => null,
        ]);

        $response = $this->postJson("/api/v1/notifications/{$otherNotification->id}/read");
        $response->assertForbidden();
    }

    public function test_自分の通知を既読化できる(): void
    {
        $user = User::factory()->student()->create();
        Sanctum::actingAs($user);

        $notification = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'data' => ['title' => '自分宛て通知'],
            'read_at' => null,
        ]);

        $response = $this->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_全件既読化が正しく機能する(): void
    {
        $user = User::factory()->student()->create();
        Sanctum::actingAs($user);

        // 未読通知を2件作成
        for ($i = 0; $i < 2; $i++) {
            $user->notifications()->create([
                'id' => Str::uuid()->toString(),
                'type' => 'App\Notifications\TestNotification',
                'data' => ['title' => "通知 {$i}"],
                'read_at' => null,
            ]);
        }

        $response = $this->postJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_管理者ユーザーが通知_ap_iにアクセスすると403エラーになる(): void
    {
        // 管理者ユーザーを作成
        $admin = User::factory()->create([
            'role' => UserRole::Admin, // または 'role' => 'admin'
        ]);

        // 管理者として Sanctum 認証
        Sanctum::actingAs($admin);

        // 一覧取得 API の直叩き
        $response = $this->getJson('/api/v1/notifications');

        // 403 Forbidden であることを検証
        $response->assertForbidden();
    }
}
