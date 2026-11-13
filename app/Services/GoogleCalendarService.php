<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    /**
     * コーチの Google カレンダーから指定期間の Busy（予定あり）スロットを取得
     */
    public function getBusySlots(User $coach, Carbon $start, Carbon $end): array
    {
        $credential = $coach->googleCredential;
        if ($credential === null) {
            return [];
        }

        $accessToken = $this->ensureValidAccessToken($credential);
        if ($accessToken === null) {
            return [];
        }

        try {
            $endpoint = base64_decode('aHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20vY2FsZW5kYXIvdjMvZnJlZUJ1c3k=');

            $response = Http::withToken($accessToken)
                ->post($endpoint, [
                    'timeMin' => $start->toRfc3339String(),
                    'timeMax' => $end->toRfc3339String(),
                    'items' => [
                        ['id' => 'primary'],
                        ['id' => $credential->calendar_id],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Google FreeBusy API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $json = $response->json();
            $busySlots = [];

            // primary または calendar_id のいずれかで返却された busy スロットを全抽出
            if (isset($json['calendars']) && is_array($json['calendars'])) {
                foreach ($json['calendars'] as $calData) {
                    if (! empty($calData['busy']) && is_array($calData['busy'])) {
                        $busySlots = array_merge($busySlots, $calData['busy']);
                    }
                }
            }

            Log::info('Google FreeBusy 取得成功', [
                'coach_id' => $coach->id,
                'calendar_id' => $credential->calendar_id,
                'timeMin' => $start->toRfc3339String(),
                'timeMax' => $end->toRfc3339String(),
                'busy_count' => count($busySlots),
                'busy_slots' => $busySlots,
            ]);

            return $busySlots;
        } catch (\Throwable $e) {
            Log::error('GoogleCalendarService::getBusySlots exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Google カレンダーへ面談予定を自動登録
     */
    public function createMeetingEvent(User $coach, Meeting $meeting): ?string
    {
        $credential = $coach->googleCredential;
        if ($credential === null) {
            return null;
        }

        $accessToken = $this->ensureValidAccessToken($credential);
        if ($accessToken === null) {
            return null;
        }

        $meeting->loadMissing('student');
        $studentName = $meeting->student?->name ?? '受講生';
        $meetingUrl = $meeting->meeting_url_snapshot ?? $coach->meeting_url ?? 'LMS内面談ルーム';

        $start = $meeting->scheduled_at;
        $end = $start->copy()->addHour();

        try {
            $calendarId = urlencode($credential->calendar_id);
            $baseUrl = base64_decode('aHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20vY2FsZW5kYXIvdjMvY2FsZW5kYXJzLw==');
            $endpoint = "{$baseUrl}{$calendarId}/events";

            $response = Http::withToken($accessToken)
                ->post($endpoint, [
                    'summary' => "【CertifyLMS】面談予約: {$studentName} 様",
                    'description' => "受講生: {$studentName}\n面談URL: {$meetingUrl}",
                    'start' => [
                        'dateTime' => $start->toRfc3339String(),
                    ],
                    'end' => [
                        'dateTime' => $end->toRfc3339String(),
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Google Calendar createMeetingEvent failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('id');
        } catch (\Throwable $e) {
            Log::error('GoogleCalendarService::createMeetingEvent exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Google カレンダーのイベントを削除
     */
    public function deleteMeetingEvent(User $coach, string $googleEventId): bool
    {
        $credential = $coach->googleCredential;
        if ($credential === null) {
            return false;
        }

        $accessToken = $this->ensureValidAccessToken($credential);
        if ($accessToken === null) {
            return false;
        }

        try {
            $calendarId = urlencode($credential->calendar_id);
            $eventId = urlencode($googleEventId);
            $baseUrl = base64_decode('aHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20vY2FsZW5kYXIvdjMvY2FsZW5kYXJzLw==');
            $endpoint = "{$baseUrl}{$calendarId}/events/{$eventId}";

            $response = Http::withToken($accessToken)->delete($endpoint);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('GoogleCalendarService::deleteMeetingEvent exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * アクセストークンの有効性を確認し、期限切れの場合はリフレッシュ
     */
    private function ensureValidAccessToken(GoogleCredential $credential): ?string
    {
        if (! $credential->isExpired()) {
            return $credential->access_token;
        }

        try {
            $endpoint = base64_decode('aHR0cHM6Ly9vYXV0aDIuZ29vZ2xlYXBpcy5jb20vdG9rZW4=');
            $response = Http::asForm()->post($endpoint, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $credential->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->failed()) {
                Log::error('Google access token refresh failed', ['body' => $response->body()]);
                return null;
            }

            $data = $response->json();
            $newAccessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 3600;

            $credential->update([
                'access_token' => $newAccessToken,
                'expires_at' => now()->addSeconds($expiresIn),
            ]);

            return $newAccessToken;
        } catch (\Throwable $e) {
            Log::error('GoogleCalendarService token refresh exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}