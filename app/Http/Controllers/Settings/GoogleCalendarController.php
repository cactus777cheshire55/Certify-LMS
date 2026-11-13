<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GoogleCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GoogleCalendarController extends Controller
{
    /**
     * OAuth 認可画面へのリダイレクト（CSRF防止用の state を生成）
     */
    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        if ($request->has('redirect_path')) {
            $request->session()->put('google_oauth_redirect_path', $request->input('redirect_path'));
        }

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('settings.google-calendar.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    /**
     * OAuth コールバック受取・トークン保存
     */
    public function callback(Request $request): RedirectResponse
    {
        $savedState = $request->session()->pull('google_oauth_state');
        $inputState = $request->input('state');
        $redirectPath = $request->session()->pull('google_oauth_redirect_path');

        $redirectTo = $redirectPath ? redirect($redirectPath) : redirect()->route('settings.profile.edit');

        // CSRF（なりすまし）チェック
        if (empty($savedState) || $savedState !== $inputState) {
            return $redirectTo
                ->with('error', '不正なリクエストが検出されました。もう一度お試しください。');
        }

        $code = $request->input('code');
        if (empty($code)) {
            return $redirectTo
                ->with('error', 'Google アカウント連携がキャンセルされました。');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('settings.google-calendar.callback'),
        ]);

        if ($response->failed()) {
            return $redirectTo
                ->with('error', 'Google トークンの取得に失敗しました。');
        }

        $data = $response->json();
        $user = $request->user();

        // Google アカウントのメールアドレスを取得して calendar_id として保存
        $userInfoResponse = Http::withToken($data['access_token'])
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        $calendarId = $userInfoResponse->successful()
            ? ($userInfoResponse->json('email') ?? 'primary')
            : 'primary';

        GoogleCredential::updateOrCreate(
            ['user_id' => $user->id],
            [
                'calendar_id' => $calendarId,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                'connected_at' => now(),
            ]
        );

        return $redirectTo
            ->with('status', 'Google カレンダーとの連携が完了しました。');
    }

    /**
     * Google カレンダー連携の解除
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->googleCredential()?->delete();

        return back()
            ->with('status', 'Google カレンダーとの連携を解除しました。');
    }
}