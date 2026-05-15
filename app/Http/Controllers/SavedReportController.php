<?php

namespace App\Http\Controllers;

use App\Enums\IndexType;
use App\Http\Controllers\Concerns\BuildsReportScopeData;
use App\Http\Requests\UpdateReportGroupRequest;
use App\Models\ReportGroup;
use App\Services\ReportGroupService;
use App\Services\ReportGroupVaultSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SavedReportController extends Controller
{
    use BuildsReportScopeData;

    public function index(ReportGroupService $reportGroupService): View
    {
        $reportGroups = $reportGroupService->all();
        $totalRecords = $reportGroups->sum(fn (ReportGroup $reportGroup): int => $reportGroup->items->count());
        $grandTotalMinutes = $reportGroups->sum(fn (ReportGroup $reportGroup): int => $reportGroup->items->sum('duration_minutes'));

        return view('reports.index', [
            'reportGroups' => $reportGroups,
            'totalRecords' => $totalRecords,
            'grandTotalMinutes' => $grandTotalMinutes,
            'grandTotalLabel' => $this->formatMinutes($grandTotalMinutes),
            'reportGroupsFilePath' => $reportGroupService->reportGroupsPath(),
            'reportNotesRoot' => rtrim(config('obsidian.vault_path'), '\\/').DIRECTORY_SEPARATOR.trim(config('obsidian.report_notes_directory', 'REPORTS'), '\\/'),
        ]);
    }

    public function show(
        ReportGroup $reportGroup,
        ReportGroupService $reportGroupService,
        ReportGroupVaultSyncService $reportGroupVaultSyncService,
    ): View {
        $reportGroup->load('items');
        $cards = [];

        foreach (IndexType::cases() as $type) {
            $cards[$type->value] = $this->summaryForType($type, $reportGroup->itemsFor($type->value));
        }

        $grandTotalMinutes = collect($cards)->sum('total_minutes');

        return view('reports.show', [
            'reportGroup' => $reportGroup,
            'cards' => $cards,
            'grandTotalMinutes' => $grandTotalMinutes,
            'grandTotalLabel' => $this->formatMinutes($grandTotalMinutes),
            'reportGroupsFilePath' => $reportGroupService->reportGroupsPath(),
            'reportNoteDirectory' => $reportGroup->obsidian_directory ?: $reportGroupVaultSyncService->reportDirectory($reportGroup),
            'reportIndexNotePath' => $reportGroup->obsidian_index_note_path ?: $reportGroupVaultSyncService->indexNotePath($reportGroup),
        ]);
    }

    public function update(
        UpdateReportGroupRequest $request,
        ReportGroup $reportGroup,
        ReportGroupService $reportGroupService,
    ): RedirectResponse {
        $reportGroup = $reportGroupService->update($reportGroup, $request->reportTitle());

        return redirect()
            ->route('reports.show', $reportGroup)
            ->with('status', 'Saved report updated as '.$reportGroup->display_label.'.');
    }

    public function destroy(ReportGroup $reportGroup, ReportGroupService $reportGroupService): RedirectResponse
    {
        $label = $reportGroup->display_label;
        $reportGroupService->delete($reportGroup);

        return redirect()
            ->route('reports.index')
            ->with('status', 'Saved report '.$label.' was deleted and its entries are available again.');
    }
}
