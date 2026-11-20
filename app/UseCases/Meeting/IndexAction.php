<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingQuotaService;

final class IndexAction
{
    public function __construct(
        private readonly MeetingQuotaService $meetingQuota,
    ) {}

    /**
     * @param array<string, mixed>|string|null $filters
     * @return array{meetings: \Illuminate\Contracts\Pagination\LengthAwarePaginator, filter: string, tab: string, meetingsRemaining: int}
     */
    public function __invoke(User $student, mixed $filters = null): array
    {
        if (is_array($filters)) {
            $filter = $filters['tab'] ?? $filters['filter'] ?? 'upcoming';
        } else {
            $filter = $filters ?? 'upcoming';
        }

        $query = Meeting::query()
            ->with('enrollment.certification', 'coach')
            ->forStudent($student);

        $meetings = match ($filter) {
            'past' => $query->past()->orderByDesc('scheduled_at')->paginate(20),
            'all' => $query->orderByDesc('scheduled_at')->paginate(20),
            default => $query->upcoming()->orderBy('scheduled_at')->paginate(20),
        };

        return [
            'meetings' => $meetings,
            'filter' => $filter,
            'tab' => $filter, 
            'meetingsRemaining' => $this->meetingQuota->remaining($student),
        ];
    }
}