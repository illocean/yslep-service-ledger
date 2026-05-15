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

        $cards = [];
        $entries = [];
        $meta = [];
        $liveEntries = [];
        $saveGroupEntries = [];
        $liveEntryStats = [];

        foreach (IndexType::cases() as $type) {
            $liveEntries[$type->value] = $this->liveEntriesForType($type);
            $saveGroupEntries[$type->value] = $liveEntries[$type->value]
                ->reject(fn ($entry): bool => array_key_exists($type->value.':'.$entry->id, $assignedReportLookup))
                ->values();
            $lockedCount = $liveEntries[$type->value]
                ->filter(fn ($entry): bool => array_key_exists($type->value.':'.$entry->id, $assignedReportLookup))
                ->count();

            $cards[$type->value] = $this->summaryForType($type, $liveEntries[$type->value]);
            $entries[$type->value] = $liveEntries[$type->value]->take(5)->values();
            $meta[$type->value] = $syncService->cardMeta($type);
            $liveEntryStats[$type->value] = [
                'total' => $liveEntries[$type->value]->count(),
                'locked' => $lockedCount,
                'available' => $liveEntries[$type->value]->count() - $lockedCount,
            ];
        }

        $grandTotalMinutes = collect($cards)->sum('total_minutes');

        return view('dashboard', [
            'cards' => $cards,
            'entries' => $entries,
            'liveEntries' => $liveEntries,
            'saveGroupEntries' => $saveGroupEntries,
            'liveEntryStats' => $liveEntryStats,
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
