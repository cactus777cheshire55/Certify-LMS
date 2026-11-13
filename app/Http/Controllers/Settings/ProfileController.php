<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * プロフィール設定画面の表示
     */
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    /**
     * プロフィール情報の更新
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $userData = [
            'name' => $validated['name'],
            'bio' => $validated['bio'] ?? null,
        ];

        // コーチのみ固定面談URLを保存
        if ($user->role === UserRole::Coach && array_key_exists('meeting_url', $validated)) {
            $userData['meeting_url'] = $validated['meeting_url'];
        }

        $user->update($userData);

        return redirect()->route('settings.profile.edit')
            ->with('success', 'プロフィール情報を更新しました。');
    }
}
