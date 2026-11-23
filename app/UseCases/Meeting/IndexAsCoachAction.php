<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class IndexAsCoachAction
{
    /**
     * @param array{tab?: string, filter?: string, enrollment_id?: string} $filters
     *
     * @return array{meetings: LengthAwarePaginator, filter: string, tab: string, enrollmentFilter: ?string}
     */
    public function __invoke(User $coach, array $filters = []): array
    {
        $filter = $filters['tab'] ?? $filters['filter'] ?? 'upcoming';
        $enrollmentId = $filters['enrollment_id'] ?? null;

        $query = Meeting::query()
            ->with('enrollment.certification', 'student')
            ->forCoach($coach);

        if ($enrollmentId) {
            $query->where('enrollment_id', $enrollmentId);
        }

        $meetings = match ($filter) {
            'past' => $query->past()->orderByDesc('scheduled_at')->paginate(20),
            'all' => $query->orderByDesc('scheduled_at')->paginate(20),
            default => $query->upcoming()->orderBy('scheduled_at')->paginate(20),
        };

        return [
            'meetings' => $meetings,
            'filter' => $filter,
            'tab' => $filter,
            'enrollmentFilter' => $enrollmentId,
        ];
    }
}
