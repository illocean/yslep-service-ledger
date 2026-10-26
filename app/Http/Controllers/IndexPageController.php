<?php

namespace App\Http\Controllers;

use App\Enums\IndexScope;
use App\Enums\IndexType;
use App\Http\Controllers\Concerns\BuildsReportScopeData;
use App\Services\ObsidianSyncService;
use App\Services\ReportGroupService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexPageController extends Controller
{
    use BuildsReportScopeData;

    public function show(
        IndexType $type,
        Request $request,
        ObsidianSyncService $syncService,
        ReportGroupService $reportGroupService,
    ): View {
        $indexType = $type;

        $syncService->syncAll();
        $selectedReportGroup = $this->selectedReportGroup($request->string('report')->toString(), $reportGroupService);
        $selectedScope = $this->selectedScope($request, $selectedReportGroup);
        $reportGroups = $reportGroupService->all();
        $assignedReportLookup = $selectedScope->isLive() ? $reportGroupService->assignedReportLookup() : [];

        // Newest first: after a save the redirect lands here, and the entry that
        // was just created must be visible without scrolling.
        $entries = $this->scopedEntriesForType($indexType, $selectedScope, $selectedReportGroup)
            ->sortByDesc(fn ($entry): array => [$entry->served_on?->getTimestamp(), $entry->source_order])
            ->values();

        // Optional month lens from the dashboard chart drill-in (?month=2025-09).
        $monthFilter = $request->string('month')->toString();
        $monthFilter = preg_match('/^\d{4}-\d{2}$/', $monthFilter) === 1 ? $monthFilter : '';

        if ($monthFilter !== '') {
            $entries = $entries
                ->filter(fn ($entry): bool => $entry->served_on?->format('Y-m') === $monthFilter)
                ->values();
        }

        $summary = $this->summaryForType($indexType, $entries);
        $cardMeta = $syncService->cardMeta($indexType);
        $otherCards = [];

        foreach (IndexType::cases() as $candidate) {
            if ($candidate === $indexType) {
                continue;
            }

            $candidateEntries = $this->scopedEntriesForType($candidate, $selectedScope, $selectedReportGroup);
            $otherCards[$candidate->value] = $this->summaryForType($candidate, $candidateEntries);
        }

        $selectedScopeLabel = $selectedScope === IndexScope::Report && $selectedReportGroup !== null
            ? $selectedReportGroup->compact_label
            : $selectedScope->label();

        return view('indexes.show', [
            'type' => $indexType,
            'entries' => $entries,
            'summary' => $summary,
            'cardMeta' => $cardMeta,
            'otherCards' => $otherCards,
            'vaultPath' => $syncService->vaultPath(),
            'selectedReportGroup' => $selectedReportGroup,
            'selectedReportTag' => $selectedReportGroup?->tag,
            'selectedScope' => $selectedScope,
            'selectedScopeLabel' => $selectedScopeLabel,
            'monthFilter' => $monthFilter,
            'monthLabel' => $monthFilter === '' ? null : CarbonImmutable::createFromFormat('Y-m', $monthFilter)->format('F Y'),
            'reportGroups' => $reportGroups,
            'sourceMode' => $selectedScope->isLive() ? 'live' : 'report',
            'assignedReportLookup' => $assignedReportLookup,
            'reportGroupsFilePath' => $reportGroupService->reportGroupsPath(),
            'academicYears' => $cardMeta['entry_options']['academic_years'] ?? [],
        ]);
    }
}
