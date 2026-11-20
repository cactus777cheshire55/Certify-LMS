<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;

final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    /**
     * @throws MeetingStatusTransitionException
     * @throws MeetingAlreadyStartedException
     */
    public function __invoke(Meeting $meeting, User $actor): Meeting
    {
        DB::transaction(function () use ($meeting, $actor) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($locked->student, $locked->id);
        });

        if (! empty($meeting->google_event_id)) {
            $this->googleCalendarService->deleteMeetingEvent($meeting->coach, $meeting->google_event_id);
        }

        return $meeting->fresh();
    }
}