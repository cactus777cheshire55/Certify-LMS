<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class MeetingAvailabilityService
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function slotsForCertification(Certification $certification, Carbon $date): Collection
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        $dayStart = $date->copy()->setTimezone($tz)->startOfDay();
        $dayEnd = $date->copy()->setTimezone($tz)->endOfDay();
        $dayOfWeek = $date->dayOfWeek;

        $coaches = $certification->coaches()->get();

        if ($coaches->isEmpty()) {
            return collect();
        }

        $coachIds = $coaches->pluck('id')->all();

        $availabilities = CoachAvailability::query()
            ->whereIn('coach_id', $coachIds)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        $existingMeetings = Meeting::query()
            ->whereIn('coach_id', $coachIds)
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value])
            ->get(['coach_id', 'scheduled_at']);

        $bookedByCoach = $existingMeetings
            ->groupBy('coach_id')
            ->map(fn ($rows) => $rows->map(fn (Meeting $m) => $m->scheduled_at->format('H:i'))->all());

        // 各コーチの Google Calendar Busy スロットを取得
        $googleBusyByCoach = [];
        foreach ($coaches as $coach) {
            $googleBusyByCoach[$coach->id] = $this->googleCalendarService->getBusySlots($coach, $dayStart, $dayEnd);
        }

        $slotCounts = [];

        foreach ($availabilities as $availability) {
            $slot = Carbon::parse($date->format('Y-m-d').' '.$availability->start_time, $tz);
            $end = Carbon::parse($date->format('Y-m-d').' '.$availability->end_time, $tz);

            while ($slot->copy()->addHour() <= $end) {
                $slotKey = $slot->format('H:i');
                $coachId = $availability->coach_id;
                $booked = $bookedByCoach[$coachId] ?? [];

                $isLmsBooked = in_array($slotKey, $booked, true);

                $isGoogleBusy = false;
                $busySlots = $googleBusyByCoach[$coachId] ?? [];

                if (! empty($busySlots)) {
                    $slotStart = $slot->copy();
                    $slotEnd = $slotStart->copy()->addHour();

                    foreach ($busySlots as $busy) {
                        // タイムゾーンを一致させて比較
                        $busyStart = Carbon::parse($busy['start'])->setTimezone($tz);
                        $busyEnd = Carbon::parse($busy['end'])->setTimezone($tz);

                        if ($slotStart->lt($busyEnd) && $slotEnd->gt($busyStart)) {
                            $isGoogleBusy = true;
                            break;
                        }
                    }
                }

                if (! $isLmsBooked && ! $isGoogleBusy) {
                    $slotCounts[$slotKey] = ($slotCounts[$slotKey] ?? 0) + 1;
                }

                $slot->addHour();
            }
        }

        ksort($slotCounts);

        return collect($slotCounts)->map(function (int $count, string $time) use ($date, $tz) {
            $start = Carbon::parse($date->format('Y-m-d').' '.$time, $tz);

            return [
                'slot_start' => $start,
                'slot_end' => $start->copy()->addHour(),
                'available_coach_count' => $count,
            ];
        })->values();
    }

    public function validateSlot(Certification $certification, Carbon $scheduledAt): void
    {
        $slots = $this->slotsForCertification($certification, $scheduledAt->copy()->startOfDay());

        $matched = $slots->contains(
            fn (array $slot) => $slot['slot_start']->equalTo($scheduledAt) && $slot['available_coach_count'] > 0,
        );

        if (! $matched) {
            throw new MeetingOutOfAvailabilityException;
        }
    }
}