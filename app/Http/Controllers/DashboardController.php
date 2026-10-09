<?php

namespace App\Http\Controllers;

use App\Enums\IndexType;
use App\Http\Controllers\Concerns\BuildsReportScopeData;
use App\Services\ObsidianSyncService;
use App\Services\ReportGroupService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use BuildsReportScopeData;

    public function __invoke(
        ObsidianSyncService $syncService,
        ReportGroupService $reportGroupService,
    ): View {
        $syncService->syncAll();
        $reportGroups = $reportGroupService->all();
        $assignedReportLookup = $reportGroupService->assignedReportLookup();
        $builder = $this->saveGroupData($assignedReportLookup);

        $cards = [];
        $entries = [];
        $meta = [];

        foreach (IndexType::cases() as $type) {
            $cards[$type->value] = $this->summaryForType($type, $builder['live_entries'][$type->value]);
            $entries[$type->value] = $builder['live_entries'][$type->value]->take(5)->values();
            $meta[$type->value] = $syncService->cardMeta($type);
        }

        $grandTotalMinutes = collect($cards)->sum('total_minutes');

        return view('dashboard', [
            'cards' => $cards,
            'entries' => $entries,
            'saveGroupEntries' => $builder['save_group_entries'],
            'liveEntryStats' => $builder['live_entry_stats'],
            'meta' => $meta,
            'vaultPath' => $syncService->vaultPath(),
            'reportGroups' => $reportGroups,
            'assignedReportLookup' => $assignedReportLookup,
            'grandTotalMinutes' => $grandTotalMinutes,
            'grandTotalLabel' => $this->formatMinutes($grandTotalMinutes),
            'reportGroupsFilePath' => $reportGroupService->reportGroupsPath(),
        ]);
    }
}
