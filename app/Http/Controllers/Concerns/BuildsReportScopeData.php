<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\IndexScope;
use App\Enums\IndexType;
use App\Models\ReportGroup;
use App\Models\ReportGroupItem;
use App\Services\ReportGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait BuildsReportScopeData
{
    private function selectedScope(
        Request $request,
        ?ReportGroup $selectedReportGroup,
    ): IndexScope {
        $requestedScope = IndexScope::tryFrom($request->string('scope')->toString());

        if ($requestedScope !== null) {
            return $requestedScope === IndexScope::Report && $selectedReportGroup === null
                ? IndexScope::All
                : $requestedScope;
        }

        return $selectedReportGroup !== null ? IndexScope::Report : IndexScope::All;
    }

    private function selectedReportGroup(?string $tag, ReportGroupService $reportGroupService): ?ReportGroup
    {
        return $reportGroupService->findByTag($tag);
    }

    private function liveEntriesForType(IndexType $type, IndexScope $scope = IndexScope::All)
    {
        $modelClass = $type->modelClass();
        $query = $modelClass::query();

        if ($scope === IndexScope::Unsaved) {
            $lockedEntryIds = ReportGroupItem::query()
                ->where('index_type', $type->value)
                ->whereNotNull('source_entry_id')
                ->pluck('source_entry_id');

            if ($lockedEntryIds->isNotEmpty()) {
                $query->whereNotIn('id', $lockedEntryIds);
            }
        }

        return $query
            ->orderBy('served_on')
            ->orderBy('source_order')
            ->get();
    }

    private function scopedEntriesForType(
        IndexType $type,
        IndexScope $scope,
        ?ReportGroup $selectedReportGroup,
    ) {
        if ($scope !== IndexScope::Report || $selectedReportGroup === null) {
            return $this->liveEntriesForType($type, $scope);
        }

        return $selectedReportGroup->itemsFor($type->value);
    }

    private function summaryForType(IndexType $type, Collection $entries): array
    {
        $totalMinutes = $entries->sum('duration_minutes');

        return [
            'type' => $type,
            'label' => $type->label(),
            'card_title' => $type->cardTitle(),
            'count' => $entries->count(),
            'total_minutes' => $totalMinutes,
            'total_label' => $this->formatMinutes($totalMinutes),
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($minutes === 0) {
            return '0 hr';
        }

        if ($remainingMinutes === 0) {
            return $hours.' hr';
        }

        return sprintf('%d hr %02d min', $hours, $remainingMinutes);
    }
}
