<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    /**
     * 配信履歴一覧
     */
    public function index(): View
    {
        $announcements = Announcement::with(['targetCertification', 'targetUser', 'createdBy'])
            ->orderByDesc('dispatched_at')
            ->paginate(15);

        return view('announcement.management.index', compact('announcements'));
    }

    /**
     * 新規配信フォーム表示
     */
    public function create(): View
    {
        $certifications = Certification::where('status', \App\Enums\CertificationStatus::Published)
            ->orderBy('name')
            ->get();

        $students = User::where('role', UserRole::Student)
            ->where('status', UserStatus::InProgress)
            ->orderBy('name')
            ->get();

        return view('announcement.management.create', compact('certifications', 'students'));
    }

    /**
     * お知らせ配信実行
     */
    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $targetType = AnnouncementTargetType::from($validated['target_type']);

        // 1. 配信対象ユーザーの抽出
        $targetUsers = match ($targetType) {
            AnnouncementTargetType::AllStudents => User::where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
                ->get(),

            AnnouncementTargetType::Certification => User::whereHas('enrollments', function ($query) use ($validated) {
                $query->where('certification_id', $validated['target_certification_id'])
                    ->where('status', \App\Enums\EnrollmentStatus::Learning);
            })->where('role', UserRole::Student)->where('status', UserStatus::InProgress)->get(),

            AnnouncementTargetType::User => User::where('id', $validated['target_user_id'])->get(),
        };

        // 2. お知らせレコードの永続化と通知送信
        DB::transaction(function () use ($validated, $targetUsers, $request) {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $validated['target_type'],
                'target_certification_id' => $validated['target_certification_id'] ?? null,
                'target_user_id' => $validated['target_user_id'] ?? null,
                'dispatched_count' => $targetUsers->count(),
                'dispatched_at' => now(),
                'created_by_user_id' => $request->user()->id,
            ]);

            if ($targetUsers->isNotEmpty()) {
                Notification::send($targetUsers, new AdminAnnouncementNotification($announcement));
            }
        });

        return redirect()->route('admin.announcements.index')
            ->with('success', 'お知らせを配信しました。');
    }

    /**
     * 配信詳細表示
     */
    public function show(Announcement $announcement): View
    {
        $announcement->load(['targetCertification', 'targetUser', 'createdBy']);

        return view('announcement.management.show', compact('announcement'));
    }
}