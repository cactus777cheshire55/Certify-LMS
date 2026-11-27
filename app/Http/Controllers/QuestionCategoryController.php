<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Content\QuestionCategoryInUseException;
use App\Http\Requests\QuestionCategory\StoreRequest;
use App\Http\Requests\QuestionCategory\UpdateRequest;
use App\Models\Certification;
use App\Models\QuestionCategory;
use App\UseCases\QuestionCategory\DestroyAction;
use App\UseCases\QuestionCategory\IndexAction;
use App\UseCases\QuestionCategory\StoreAction;
use App\UseCases\QuestionCategory\UpdateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionCategoryController extends Controller
{
    public function index(Certification $certification, IndexAction $action): View
    {
        $this->authorize('viewAny', [QuestionCategory::class, $certification]);

        return view('question-category.management.index', [
            'certification' => $certification,
            'categories' => $action($certification),
        ]);
    }

    public function store(Certification $certification, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($certification, $request->validated());

        return redirect()
            ->route('admin.certifications.question-categories.index', $certification)
            ->with('success', '出題分野を作成しました。');
    }

    public function update(QuestionCategory $category, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($category, $request->validated());

        return redirect()
            ->route('admin.certifications.question-categories.index', $category->certification_id)
            ->with('success', '出題分野を更新しました。');
    }

    public function destroy(Request $request, QuestionCategory $category, DestroyAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $category);
        $certificationId = $category->certification_id;

        try {
            $action($category);

            return redirect()
                ->route('admin.certifications.question-categories.index', $certificationId)
                ->with('success', '出題分野を削除しました。');
        } catch (QuestionCategoryInUseException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 409);
            }

            return redirect()
                ->route('admin.certifications.question-categories.index', $certificationId)
                ->with('error', '問題が紐づいている出題分野は削除できません。');
        }
    }
}
