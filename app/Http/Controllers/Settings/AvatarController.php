<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAvatarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    /**
     * アバター画像のアップロード
     */
    public function store(StoreAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        // 既存の画像があればストレージから削除
        if ($user->avatar_url && Storage::disk('public')->exists($this->getStoragePath($user->avatar_url))) {
            Storage::disk('public')->delete($this->getStoragePath($user->avatar_url));
        }

        // 新しい画像を保存 (storage/app/public/avatars)
        $path = $request->file('avatar')->store('avatars', 'public');
        $avatarUrl = Storage::url($path);

        $user->update(['avatar_url' => $avatarUrl]);

        return redirect()->route('settings.profile.edit')
            ->with('success', 'アイコン画像を更新しました。');
    }

    /**
     * アバター画像の削除
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_url) {
            $path = $this->getStoragePath($user->avatar_url);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            $user->update(['avatar_url' => null]);
        }

        return redirect()->route('settings.profile.edit')
            ->with('success', 'アイコン画像を削除しました。');
    }

    /**
     * URLからストレージ相対パスを抽出
     */
    private function getStoragePath(string $url): string
    {
        return str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
    }
}
