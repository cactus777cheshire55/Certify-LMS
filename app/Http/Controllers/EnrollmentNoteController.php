<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentNoteRequest;
use App\Http\Requests\UpdateEnrollmentNoteRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EnrollmentNoteController extends Controller
{
    /**
     * メモの新規作成
     */
    public function store(StoreEnrollmentNoteRequest $request, Enrollment $enrollment): RedirectResponse
    {
        $enrollment->notes()->create([
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return redirect()->route('enrollments.show', $enrollment)
            ->with('success', 'コーチメモを追加しました。');
    }

    /**
     * メモの編集画面表示
     */
    public function edit(EnrollmentNote $note): View
    {
        Gate::authorize('update', $note);

        return view('enrollment-note.edit', compact('note'));
    }

    /**
     * メモの更新
     */
    public function update(UpdateEnrollmentNoteRequest $request, EnrollmentNote $note): RedirectResponse
    {
        $note->update($request->validated());

        return redirect()->route('enrollments.show', $note->enrollment_id)
            ->with('success', 'コーチメモを更新しました。');
    }

    /**
     * メモの削除
     */
    public function destroy(EnrollmentNote $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        $enrollmentId = $note->enrollment_id;
        $note->delete();

        return redirect()->route('enrollments.show', $enrollmentId)
            ->with('success', 'コーチメモを削除しました。');
    }
}