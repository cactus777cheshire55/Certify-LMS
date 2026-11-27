<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 【レビュー指摘 4: Google連携解除・再連携およびトークン管理の不備】の回帰テスト（ドラフト）。
 *
 * 既存の tests/Feature/Http/Settings/GoogleCalendarControllerTest.php は
 * connect/callback の正常系と state 不一致時のリジェクトのみを検証しており、
 * 以下の3点はいずれも未カバーである。
 */
final class GoogleCalendarControllerSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * (1) 連携解除時に Google 側の OAuth トークンを Revoke していること。
     *
     * 現状の GoogleCalendarController::destroy は DB レコードの削除のみで、
     * Google の Revoke エンドポイント（https://oauth2.googleapis.com/revoke）を一切呼んでいないため
     * 本テストは失敗する。
     */
    public function test_disconnecting_revokes_the_token_on_google_side(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->create([
            'user_id' => $coach->id,
            'access_token' => 'token-to-be-revoked',
        ]);

        Http::fake([
            '*oauth2.googleapis.com/revoke*' => Http::response([], 200),
        ]);

        $response = $this->actingAs($coach)->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect();
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'oauth2.googleapis.com/revoke');
        });
        $this->assertDatabaseMissing('google_credentials', ['user_id' => $coach->id]);
    }

    /**
     * (2) 再連携時に Google から refresh_token が返却されないケースで、
     * 既存の refresh_token を null で上書きしないこと。
     *
     * 現状の callback() は `'refresh_token' => $data['refresh_token'] ?? null` としており、
     * 既存値へのフォールバックが無いため本テストは失敗する（null で上書きされる）。
     */
    public function test_reconnecting_without_a_new_refresh_token_keeps_the_existing_one(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->create([
            'user_id' => $coach->id,
            'refresh_token' => 'existing-refresh-token',
        ]);

        Http::fake([
            '*oauth2.googleapis.com/token*' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
                // refresh_token キーなし = Google が再発行しなかったケースを再現
            ], 200),
            '*googleapis.com/oauth2/v2/userinfo*' => Http::response([
                'email' => 'coach@example.com',
            ], 200),
        ]);

        $state = 'test_state_reconnect';
        session(['google_oauth_state' => $state]);

        $this->actingAs($coach)->get(route('settings.google-calendar.callback', [
            'code' => 'mock_code',
            'state' => $state,
        ]));

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'access_token' => 'new-access-token',
            'refresh_token' => 'existing-refresh-token',
        ]);
    }

    /**
     * (3) GoogleCredential をシリアライズ（配列化/JSON化）した際に、
     * access_token / refresh_token が露出しないこと。
     *
     * 現状のモデルには $hidden が定義されていないため本テストは失敗する。
     */
    public function test_access_and_refresh_tokens_are_hidden_from_serialization(): void
    {
        $credential = GoogleCredential::factory()->create([
            'access_token' => 'secret-access-token',
            'refresh_token' => 'secret-refresh-token',
        ]);

        $array = $credential->toArray();

        $this->assertArrayNotHasKey('access_token', $array);
        $this->assertArrayNotHasKey('refresh_token', $array);
    }
}
