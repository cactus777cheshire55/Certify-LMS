<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GoogleCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /** @test */
    public function コーチは認証URLへリダイレクトされる(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->get(route('settings.google-calendar.redirect'));

        $response->assertRedirect();
        // 31行目：'://google.com' から 'accounts.google.com' に修正
        $this->assertStringContainsString('accounts.google.com', $response->getTargetUrl());
        $this->assertTrue(session()->has('google_oauth_state'));
    }

    /** @test */
    public function コールバック時にトークンが保存される(): void
    {
        Http::fake([
            '*googleapis.com*' => Http::response([
                'access_token' => 'mock_access_token',
                'refresh_token' => 'mock_refresh_token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $coach = User::factory()->coach()->create();
        $state = 'test_state_12345';
        session(['google_oauth_state' => $state]);

        $response = $this->actingAs($coach)->get(route('settings.google-calendar.callback', [
            'code' => 'mock_code',
            'state' => $state,
        ]));

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'access_token' => 'mock_access_token',
        ]);
    }

    /** @test */
    public function 不正なstateの場合はリジェクトされる(): void
    {
        $coach = User::factory()->coach()->create();
        session(['google_oauth_state' => 'valid_state']);

        $response = $this->actingAs($coach)->get(route('settings.google-calendar.callback', [
            'code' => 'mock_code',
            'state' => 'invalid_state',
        ]));

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('error');
    }
}
