<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Services\Learning\ProgressSummary;

class LearningProgressService
{
    /**
     * 受講登録（Enrollment）に対する学習進捗の 4 階層サマリ（Section / Chapter / Part / 資格）を算出する。
     */
    public function calculate(Enrollment $enrollment): ProgressSummary
    {
        $totals = $this->fetchSectionTotals($enrollment);

        $partsTotal = Part::query()
            ->where('certification_id', $enrollment->certification_id)
            ->where('status', ContentStatus::Published->value)
            ->count();

        $chaptersTotal = Chapter::query()
            ->whereHas('part', function ($q) use ($enrollment) {
                $q->where('certification_id', $enrollment->certification_id)
                    ->where('status', ContentStatus::Published->value);
            })
            ->where('status', ContentStatus::Published->value)
            ->count();

        $sectionsTotal = (int) $totals->sections_total;
        $sectionsCompleted = (int) $totals->sections_completed;

        $sectionRatio = $sectionsTotal > 0 ? round($sectionsCompleted / $sectionsTotal, 4) : 0.0;

        $chaptersCompleted = $this->countCompletedChapters($enrollment);
        $chapterRatio = $chaptersTotal > 0 ? round($chaptersCompleted / $chaptersTotal, 4) : 0.0;

        $partsCompleted = $this->countCompletedParts($enrollment);
        $partRatio = $partsTotal > 0 ? round($partsCompleted / $partsTotal, 4) : 0.0;

        return new ProgressSummary(
            sectionsTotal: $sectionsTotal,
            sectionsCompleted: $sectionsCompleted,
            sectionCompletionRatio: $sectionRatio,
            chaptersTotal: $chaptersTotal,
            chaptersCompleted: $chaptersCompleted,
            chapterCompletionRatio: $chapterRatio,
            partsTotal: $partsTotal,
            partsCompleted: $partsCompleted,
            partCompletionRatio: $partRatio,
            overallCompletionRatio: $sectionRatio,
        );
    }

    /**
     * 公開済み Section の総数と読了済み Section 数を一括取得する。
     */
    private function fetchSectionTotals(Enrollment $enrollment): object
    {
        return Part::query()
            ->join('chapters', 'chapters.part_id', '=', 'parts.id')
            ->join('sections', 'sections.chapter_id', '=', 'chapters.id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->where('sections.status', ContentStatus::Published->value)
            ->selectRaw('COUNT(sections.id) AS sections_total, COUNT(section_progresses.id) AS sections_completed')
            ->first() ?? (object) ['sections_total' => 0, 'sections_completed' => 0];
    }

    /**
     * 配下の全公開 Section が読了済みの Chapter 数をカウントする。
     */
    private function countCompletedChapters(Enrollment $enrollment): int
    {
        $rows = Chapter::query()
            ->whereHas('part', function ($q) use ($enrollment) {
                $q->where('certification_id', $enrollment->certification_id)
                    ->where('status', ContentStatus::Published->value);
            })
            ->where('chapters.status', ContentStatus::Published->value)
            ->join('sections', 'sections.chapter_id', '=', 'chapters.id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('sections.status', ContentStatus::Published->value)
            ->groupBy('chapters.id')
            ->selectRaw('chapters.id AS chapter_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get();

        $completed = 0;
        foreach ($rows as $row) {
            if ((int) $row->total > 0 && (int) $row->total === (int) $row->done) {
                $completed++;
            }
        }

        return $completed;
    }

    /**
     * 配下の全公開 Section が読了済みの Part 数をカウントする。
     */
    private function countCompletedParts(Enrollment $enrollment): int
    {
        $rows = Part::query()
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->join('chapters', 'chapters.part_id', '=', 'parts.id')
            ->join('sections', 'sections.chapter_id', '=', 'chapters.id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('chapters.status', ContentStatus::Published->value)
            ->where('sections.status', ContentStatus::Published->value)
            ->groupBy('parts.id')
            ->selectRaw('parts.id AS part_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get();

        $completed = 0;
        foreach ($rows as $row) {
            if ((int) $row->total > 0 && (int) $row->total === (int) $row->done) {
                $completed++;
            }
        }

        return $completed;
    }
}
