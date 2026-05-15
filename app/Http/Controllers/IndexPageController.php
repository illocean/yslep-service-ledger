<?php

namespace App\Http\Controllers;

use App\Enums\IndexScope;
use App\Enums\IndexType;
use App\Http\Controllers\Concerns\BuildsReportScopeData;
use App\Services\ObsidianSyncService;
use App\Services\ReportGroupService;
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

        $entries = $this->scopedEntriesForType($indexType, $selectedScope, $selectedReportGroup)->values();
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
            'reportGroups' => $reportGroups,
            'sourceMode' => $selectedScope->isLive() ? 'live' : 'report',
            'assignedReportLookup' => $assignedReportLookup,
            'reportGroupsFilePath' => $reportGroupService->reportGroupsPath(),
        ]);
    }
}
