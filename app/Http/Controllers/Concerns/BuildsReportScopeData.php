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

    /**
     * Live entries grouped for the save-group builder: report-eligible rows
     * plus available/locked counts per index type.
     *
     * @param  array<string, ReportGroup>  $assignedReportLookup
     * @return array{live_entries: array<string, Collection>, save_group_entries: array<string, Collection>, live_entry_stats: array<string, array{total: int, locked: int, available: int}>}
     */
    private function saveGroupData(array $assignedReportLookup): array
    {
        $liveEntries = [];
        $saveGroupEntries = [];
        $liveEntryStats = [];

        foreach (IndexType::cases() as $type) {
            $entries = $this->liveEntriesForType($type);
            $liveEntries[$type->value] = $entries;
            $saveGroupEntries[$type->value] = $entries
                ->reject(fn ($entry): bool => array_key_exists($type->value.':'.$entry->id, $assignedReportLookup))
                ->values();
            $lockedCount = $entries
                ->filter(fn ($entry): bool => array_key_exists($type->value.':'.$entry->id, $assignedReportLookup))
                ->count();

            $liveEntryStats[$type->value] = [
                'total' => $entries->count(),
                'locked' => $lockedCount,
                'available' => $entries->count() - $lockedCount,
            ];
        }

        return [
            'live_entries' => $liveEntries,
            'save_group_entries' => $saveGroupEntries,
            'live_entry_stats' => $liveEntryStats,
        ];
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
