<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 退会済みユーザーは退会済フィルタ指定時のみ一覧に表示される(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::InProgress,
        ]);

        $activeUser = User::factory()->create([
            'name' => '在籍太郎',
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $withdrawnUser = User::factory()->create([
            'name' => '退会花子',
            'role' => UserRole::Student,
            'status' => UserStatus::Withdrawn,
            'deleted_at' => now(),
        ]);

        // 1. フィルタ未指定（全ステータス）時：退会済みユーザーは含まれない
        $response1 = $this->actingAs($admin)->get(route('admin.users.index'));
        $response1->assertOk();
        $users1 = $response1->viewData('users');
        $this->assertTrue($users1->contains('id', $activeUser->id));
        $this->assertFalse($users1->contains('id', $withdrawnUser->id));

        // 2. 「受講中」フィルタ指定時：退会済みユーザーは含まれない
        $response2 = $this->actingAs($admin)->get(route('admin.users.index', ['status' => UserStatus::InProgress->value]));
        $response2->assertOk();
        $users2 = $response2->viewData('users');
        $this->assertTrue($users2->contains('id', $activeUser->id));
        $this->assertFalse($users2->contains('id', $withdrawnUser->id));

        // 3. 「退会済」フィルタ指定時：退会済みユーザーのみが表示される
        $response3 = $this->actingAs($admin)->get(route('admin.users.index', ['status' => UserStatus::Withdrawn->value]));
        $response3->assertOk();
        $users3 = $response3->viewData('users');
        $this->assertFalse($users3->contains('id', $activeUser->id));
        $this->assertTrue($users3->contains('id', $withdrawnUser->id));
    }
}